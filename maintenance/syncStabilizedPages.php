<?php

use ContentTransfer\PageContentProviderFactory;
use ContentTransfer\PagePusherFactory;
use ContentTransfer\Target;
use ContentTransfer\TargetManager;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\ILoadBalancer;
use Wikimedia\Rdbms\RawSQLValue;

require_once dirname( __DIR__, 3 ) . '/maintenance/Maintenance.php';

class SyncStabilizedPages extends Maintenance {

	private LoggerInterface $logger;

	/** @var PageContentProviderFactory */
	private PageContentProviderFactory $contentProviderFactory;

	/** @var PagePusherFactory */
	private PagePusherFactory $pusherFactory;

	/** @var array<string,array<int,bool>> Track pushed files per target to avoid duplicates within a run */
	private array $pushedFiles = [];

	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'ContentTransfer' );

		$this->addDescription(
			'Push all approved pages that are newer than their last push ' .
			'to the configured ContentTransfer target. Intended to be run via cron.'
		);
		$this->addOption(
			'target',
			'Key of a single ContentTransfer target to push to. ' .
			'Overrides $wgContentStabilizationApprovalSyncTargets from config.',
			false,
			true
		);
		$this->addOption(
			'dry',
			'List pages that would be pushed without actually pushing them.'
		);
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->logger = LoggerFactory::getInstance( 'ContentStabilization' );

		$services = $this->getServiceContainer();
		$config = $services->getMainConfig();

		if ( !$config->get( 'ContentStabilizationApprovalSyncEnabled' ) ) {
			$this->output( "Sync is disabled. Set \$wgContentStabilizationApprovalSyncEnabled = true to enable.\n" );
			return;
		}

		$targetKeyOverride = $this->getOption( 'target' );
		$targetKeys = $targetKeyOverride
			? [ $targetKeyOverride ]
			: $config->get( 'ContentStabilizationApprovalSyncTargets' );
		if ( !$targetKeys ) {
			$this->fatalError(
				'No targets configured. Set $wgContentStabilizationApprovalSyncTargets or pass --target=<key>.'
			);
		}

		/** @var TargetManager $targetManager */
		$targetManager = $services->getService( 'ContentTransferTargetManager' );
		$targets = [];
		foreach ( $targetKeys as $targetKey ) {
			$target = $targetManager->getTarget( $targetKey );
			if ( $target === null ) {
				$this->output( "WARNING: Target '$targetKey' not found in \$wgContentTransferTargets, skipping.\n" );
				continue;
			}
			$targets[] = $target;
		}
		if ( !$targets ) {
			$this->fatalError( 'None of the configured targets could be resolved from $wgContentTransferTargets.' );
		}

		$loadBalancer = $services->getDBLoadBalancer();
		$titleFactory = $services->getTitleFactory();

		/** @var StabilizationLookup $stabilizationLookup */
		$stabilizationLookup = $services->getService( 'ContentStabilization.Lookup' );

		$this->pusherFactory = $services->getService( 'ContentTransfer.PagePusherFactory' );
		$this->contentProviderFactory = $services->getService( 'ContentTransferPageContentProviderFactory' );
		$user = User::newSystemUser( 'MediaWiki default' );

		$totalPushCount = 0;
		$totalErrorCount = 0;

		foreach ( $targets as $target ) {
			$this->output( "\n=== Target: {$target->getDisplayText()} ===\n" );

			$pages = $this->gatherPages( $loadBalancer, $target->getDisplayText() );
			$this->output( count( $pages ) . " page(s) need pushing.\n" );

			if ( !$pages ) {
				$this->output( "Nothing to do, all pages are up to date.\n" );
				continue;
			}

			if ( $this->getOption( 'dry' ) ) {
				foreach ( $pages as $pageId ) {
					$title = $titleFactory->newFromID( $pageId );

					if ( $title && !$stabilizationLookup->isStabilizedNamespace( $title->getNamespace() ) ) {
						$this->output(
							'  - ' . $title->getPrefixedText() . " [SKIP - stabilization disabled in namespace]\n"
						);
						continue;
					}

					$this->output( '  - ' . ( $title ? $title->getPrefixedText() : "#$pageId" ) . "\n" );
				}
				continue;
			}

			$pushCount = 0;
			$errorCount = 0;

			foreach ( $pages as $pageId ) {
				$title = $titleFactory->newFromID( $pageId );
				if ( $title === null || !$title->exists() ) {
					$this->output( "Skipping page ID $pageId — could not resolve title.\n" );
					$this->logger->warning(
						'SyncStabilizedPages: skipping page ID {id} — title could not be resolved',
						[ 'id' => $pageId ]
					);
					continue;
				}

				if ( !$stabilizationLookup->isStabilizedNamespace( $title->getNamespace() ) ) {
					$this->output( "Skipping '{$title->getPrefixedText()}' — stabilization disabled in namespace.\n" );
					continue;
				}

				$this->output( "Pushing '{$title->getPrefixedText()}'... " );

				$pushHistory = $this->pusherFactory->newPushHistory( $title, $user, $target->getDisplayText() );
				$pusher = $this->pusherFactory->newPusher( $title, $target, $pushHistory );

				try {
					$pusher->push();
				} catch ( RuntimeException $e ) {
					$this->output( "ERROR: {$e->getMessage()}\n" );
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
					$msg = $status->getMessage()->text();
					$this->output( "ERROR: $msg\n" );
					$this->logger->error(
						'SyncStabilizedPages: push status not OK for "{page}" to "{target}": {errors}',
						[
							'page' => $title->getPrefixedText(),
							'target' => $target->getDisplayText(),
							'errors' => $msg,
						]
					);
					$errorCount++;
				} else {
					$this->output( "OK\n" );
					$this->logger->debug(
						'SyncStabilizedPages: successfully pushed "{page}" to "{target}"',
						[ 'page' => $title->getPrefixedText(), 'target' => $target->getDisplayText() ]
					);
					$pushCount++;

					$this->pushRelatedFiles( $title, $target, $user );
				}
			}

			$this->output( "Target done. Pushed: $pushCount, Errors: $errorCount\n" );
			$totalPushCount += $pushCount;
			$totalErrorCount += $errorCount;
		}

		if ( $this->getOption( 'dry' ) ) {
			$this->output( "\nDry run complete. No pages were pushed.\n" );
			return;
		}

		$this->output( "\nAll targets done. Pushed: $totalPushCount, Errors: $totalErrorCount\n" );
		if ( $totalErrorCount > 0 ) {
			$this->fatalError( "$totalErrorCount page(s) failed to push." );
		}
	}

	/**
	 * Return IDs of pages whose latest revision is approved and is newer than the last push
	 * to the given target, or pages that have never been pushed to it.
	 *
	 * @param ILoadBalancer $loadBalancer
	 * @param string $targetDisplayText
	 * @return int[]
	 */
	private function gatherPages( ILoadBalancer $loadBalancer, string $targetDisplayText ): array {
		$dbr = $loadBalancer->getConnection( DB_REPLICA );

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
			$this->output( "    WARNING: could not extract related titles: {$e->getMessage()}\n" );
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
				$this->output( "    SKIP file '{$relatedTitle->getPrefixedText()}' — no usable binary.\n" );
				$this->pushedFiles[$targetKey][$filePageId] = true;
				continue;
			}

			$this->output( "    Pushing file '{$relatedTitle->getPrefixedText()}'... " );

			$filePusher = $this->pusherFactory->newPusher( $relatedTitle, $target, $pushHistory );
			try {
				$filePusher->push();
			} catch ( RuntimeException $e ) {
				$this->output( "ERROR: {$e->getMessage()}\n" );
				$this->pushedFiles[$targetKey][$filePageId] = true;
				continue;
			}

			$status = $filePusher->getStatus();
			if ( !$status->isOK() ) {
				$this->output( "ERROR: {$status->getMessage()->text()}\n" );
			} else {
				$this->output( "OK\n" );
			}

			$this->pushedFiles[$targetKey][$filePageId] = true;
		}
	}
}

$maintClass = SyncStabilizedPages::class;
require_once RUN_MAINTENANCE_IF_MAIN;
