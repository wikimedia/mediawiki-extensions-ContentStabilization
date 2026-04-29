<?php

namespace MediaWiki\Extension\ContentStabilization\WikiCron;

use ContentTransfer\PageContentProviderFactory;
use ContentTransfer\PagePusherFactory;
use ContentTransfer\Target;
use ContentTransfer\TargetManager;
use MediaWiki\Config\Config;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use MWStake\MediaWiki\Component\ProcessManager\IProcessStep;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\RawSQLValue;

class SyncStabilizedPagesStep implements IProcessStep {

	private LoggerInterface $logger;

	/** @var array<string,array<int,bool>> Track pushed files per target to avoid duplicates within a run */
	private array $pushedFiles = [];

	public function __construct(
		private readonly ILoadBalancer $loadBalancer,
		private readonly TitleFactory $titleFactory,
		private readonly TargetManager $targetManager,
		private readonly PagePusherFactory $pusherFactory,
		private readonly Config $config,
		private readonly StabilizationLookup $stabilizationLookup,
		private readonly PageContentProviderFactory $contentProviderFactory
	) {
		$this->logger = LoggerFactory::getInstance( 'ContentStabilization' );
	}

	/**
	 * @param array $data
	 * @return array
	 * @throws RuntimeException In case with missing/incorrect configuration
	 */
	public function execute( $data = [] ): array {
		if ( !$this->config->get( 'ContentStabilizationApprovalSyncEnabled' ) ) {
			$this->logger->info(
				'SyncStabilizedPages: sync is disabled via $wgContentStabilizationApprovalSyncEnabled'
			);
			return [ 'status' => 'skipped' ];
		}

		$sourceKey = $this->config->get( 'ContentStabilizationApprovalSyncSource' );
		if ( !$sourceKey ) {
			throw new RuntimeException(
				'No source configured. Set $wgContentStabilizationApprovalSyncSource.'
			);
		}

		$currentWiki = $this->config->get( 'ContentTransferCurrentWiki' );
		$this->logger->info( 'SyncStabilizedPages: current wiki - ' . $currentWiki );

		if ( $currentWiki !== $sourceKey ) {
			$this->logger->info( 'SyncStabilizedPages: not a source instance, skipping.' );
			return [ 'status' => 'skipped' ];
		}

		$targetKeys = $this->config->get( 'ContentStabilizationApprovalSyncTargets' );
		if ( !$targetKeys ) {
			throw new RuntimeException(
				'No targets configured. Set $wgContentStabilizationApprovalSyncTargets.'
			);
		}

		$targets = [];
		foreach ( $targetKeys as $targetKey ) {
			$target = $this->targetManager->getTarget( $targetKey );
			if ( $target === null ) {
				$this->logger->warning(
					'SyncStabilizedPages: target "{key}" not found in $wgContentTransferTargets, skipping',
					[ 'key' => $targetKey ]
				);
				continue;
			}
			$targets[] = $target;
		}

		if ( !$targets ) {
			throw new RuntimeException(
				'None of the configured targets could be resolved from $wgContentTransferTargets.'
			);
		}

		$user = User::newSystemUser( 'MediaWiki default' );
		$totalPushCount = 0;
		$totalErrorCount = 0;

		foreach ( $targets as $target ) {
			$this->logger->info(
				'SyncStabilizedPages: processing target "{target}"',
				[ 'target' => $target->getDisplayText() ]
			);

			$pages = $this->gatherPages( $target->getDisplayText() );
			$pushCount = 0;
			$errorCount = 0;

			foreach ( $pages as $pageId ) {
				$title = $this->titleFactory->newFromID( $pageId );
				if ( $title === null || !$title->exists() ) {
					$this->logger->warning(
						'SyncStabilizedPages: skipping page ID {id} — title could not be resolved',
						[ 'id' => $pageId ]
					);
					$errorCount++;
					continue;
				}

				if ( !$this->stabilizationLookup->isStabilizedNamespace( $title->getNamespace() ) ) {
					$this->logger->debug(
						'SyncStabilizedPages: skipping "{page}" — stabilization disabled in namespace',
						[ 'page' => $title->getPrefixedText() ]
					);
					continue;
				}

				$pushHistory = $this->pusherFactory->newPushHistory( $title, $user, $target->getDisplayText() );
				$pusher = $this->pusherFactory->newPusher( $title, $target, $pushHistory );

				try {
					$pusher->push();
				} catch ( RuntimeException $e ) {
					$this->logger->error(
						'SyncStabilizedPages: push failed for "{page}" to "{target}": {message}',
						[
							'page' => $title->getPrefixedText(),
							'target' => $target->getDisplayText(),
							'message' => $e->getMessage(),
						]
					);
					$errorCount++;
					continue;
				}

				$status = $pusher->getStatus();
				if ( !$status->isOK() ) {
					$this->logger->error(
						'SyncStabilizedPages: push status not OK for "{page}" to "{target}": {errors}',
						[
							'page' => $title->getPrefixedText(),
							'target' => $target->getDisplayText(),
							'errors' => $status->getMessage()->text(),
						]
					);
					$errorCount++;
				} else {
					$this->logger->debug(
						'SyncStabilizedPages: successfully pushed "{page}" to "{target}"',
						[ 'page' => $title->getPrefixedText(), 'target' => $target->getDisplayText() ]
					);
					$pushCount++;

					$this->pushRelatedFiles( $title, $target, $user );
				}
			}

			$this->logger->info(
				'SyncStabilizedPages: target "{target}" done. Pushed: {pushed}, Errors: {errors}',
				[ 'target' => $target->getDisplayText(), 'pushed' => $pushCount, 'errors' => $errorCount ]
			);

			$totalPushCount += $pushCount;
			$totalErrorCount += $errorCount;
		}

		$this->logger->info(
			'SyncStabilizedPages: all targets done. Pushed: {pushed}, Errors: {errors}',
			[ 'pushed' => $totalPushCount, 'errors' => $totalErrorCount ]
		);

		return [ 'status' => 'done', 'pushed' => $totalPushCount, 'errors' => $totalErrorCount ];
	}

	/**
	 * Push all files referenced by a page to the target wiki.
	 * Skips files that have already been pushed and haven't changed since.
	 *
	 * @param Title $pageTitle The page whose files should be pushed
	 * @param Target $target The target wiki
	 * @param User $user System user for push history
	 */
	private function pushRelatedFiles( Title $pageTitle, Target $target, User $user ): void {
		$targetKey = $target->getDisplayText();

		try {
			$contentProvider = $this->contentProviderFactory->newFromTitle( $pageTitle );
			$relatedTitles = $contentProvider->getRelatedTitles( [] );
		} catch ( RuntimeException $e ) {
			$this->logger->warning(
				'SyncStabilizedPages: could not extract related titles for "{page}": {message}',
				[ 'page' => $pageTitle->getPrefixedText(), 'message' => $e->getMessage() ]
			);
			return;
		}

		foreach ( $relatedTitles as $relatedTitle ) {
			if ( $relatedTitle->getNamespace() !== NS_FILE ) {
				continue;
			}

			$filePageId = $relatedTitle->getArticleID();
			if ( isset( $this->pushedFiles[$targetKey][$filePageId] ) ) {
				continue;
			}

			$pushHistory = $this->pusherFactory->newPushHistory( $relatedTitle, $user, $targetKey );
			if ( !$pushHistory->isChangedSinceLastPush() ) {
				$this->pushedFiles[$targetKey][$filePageId] = true;
				continue;
			}

			$contentProviderForFile = $this->contentProviderFactory->newFromTitle( $relatedTitle );
			if ( !$contentProviderForFile->isFile() || $contentProviderForFile->getFile() === null ) {
				$this->logger->warning(
					'SyncStabilizedPages: file "{file}" has no usable binary, skipping',
					[ 'file' => $relatedTitle->getPrefixedText() ]
				);
				$this->pushedFiles[$targetKey][$filePageId] = true;
				continue;
			}

			$filePusher = $this->pusherFactory->newPusher( $relatedTitle, $target, $pushHistory );
			try {
				$filePusher->push();
			} catch ( RuntimeException $e ) {
				$this->logger->error(
					'SyncStabilizedPages: file push failed for "{file}" to "{target}": {message}',
					[
						'file' => $relatedTitle->getPrefixedText(),
						'target' => $targetKey,
						'message' => $e->getMessage(),
					]
				);
				continue;
			}

			$status = $filePusher->getStatus();
			if ( !$status->isOK() ) {
				$this->logger->error(
					'SyncStabilizedPages: file push status not OK for "{file}" to "{target}": {errors}',
					[
						'file' => $relatedTitle->getPrefixedText(),
						'target' => $targetKey,
						'errors' => $status->getMessage()->text(),
					]
				);
			} else {
				$this->logger->debug(
					'SyncStabilizedPages: successfully pushed file "{file}" to "{target}"',
					[ 'file' => $relatedTitle->getPrefixedText(), 'target' => $targetKey ]
				);
			}

			$this->pushedFiles[$targetKey][$filePageId] = true;
		}
	}

	/**
	 * Return IDs of pages whose latest revision is approved and is newer than the last push
	 * to the given target, or pages that have never been pushed to it.
	 *
	 * @param string $targetDisplayText
	 * @return int[]
	 */
	private function gatherPages( string $targetDisplayText ): array {
		$dbr = $this->loadBalancer->getConnection( DB_REPLICA );

		$res = $dbr->newSelectQueryBuilder()
			->select( 'sp_page' )
			->from( 'stable_points' )
			->join(
				'page',
				null,
				[
					'page_id = sp_page',
					'page_latest = sp_revision'
				]
			)
			->leftJoin(
				'push_history',
				null,
				[
					'ph_page = sp_page',
					'ph_target' => $targetDisplayText,
				]
			)
			->where( $dbr->orExpr( [
				$dbr->expr( 'ph_page', '=', null ),
				$dbr->expr( 'sp_time', '>', new RawSQLValue( 'ph_timestamp' ) ),
			] ) )
			->caller( __METHOD__ )
			->fetchResultSet();

		$pageIds = [];
		foreach ( $res as $row ) {
			$pageIds[] = (int)$row->sp_page;
		}

		return $pageIds;
	}
}
