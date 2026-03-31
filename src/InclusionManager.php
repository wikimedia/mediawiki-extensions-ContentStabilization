<?php

namespace MediaWiki\Extension\ContentStabilization;

use HashBagOStuff;
use MediaWiki\Config\Config;
use MediaWiki\Config\GlobalVarConfig;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Parser\ParserFactory;
use MediaWiki\Parser\ParserOutputLinkTypes;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\WikiMap\WikiMap;
use RepoGroup;
use Wikimedia\Rdbms\ILoadBalancer;

class InclusionManager {

	/** @var ILoadBalancer */
	private $loadBalancer;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/** @var RevisionLookup */
	private $revisionLookup;

	/** @var RepoGroup */
	private $repoGroup;

	/** @var InclusionMode|null|false */
	private $inclusionMode = false;

	/** @var GlobalVarConfig */
	private $config;

	/** @var ParserFactory */
	private $parserFactory;

	/** @var array */
	private $inclusionModes;

	/** @var HookContainer */
	private $hookContainer;

	/** @var HashBagOStuff */
	private $cache;

	/**
	 * @param ILoadBalancer $loadBalancer
	 * @param WikiPageFactory $wikiPageFactory
	 * @param RevisionLookup $revisionLookup
	 * @param RepoGroup $repoGroup
	 * @param GlobalVarConfig $config
	 * @param ParserFactory $parserFactory
	 * @param HookContainer $hookContainer
	 * @param array $inclusionModes
	 */
	public function __construct(
		ILoadBalancer $loadBalancer, WikiPageFactory $wikiPageFactory,
		RevisionLookup $revisionLookup, RepoGroup $repoGroup, Config $config,
		ParserFactory $parserFactory, HookContainer $hookContainer, array $inclusionModes
	) {
		$this->loadBalancer = $loadBalancer;
		$this->wikiPageFactory = $wikiPageFactory;
		$this->revisionLookup = $revisionLookup;
		$this->repoGroup = $repoGroup;
		$this->config = $config;
		$this->parserFactory = $parserFactory;
		$this->inclusionModes = $inclusionModes;
		$this->hookContainer = $hookContainer;
		$this->cache = new HashBagOStuff();
	}

	/**
	 * Store state of transcluded resources at this time
	 *
	 * @param RevisionRecord $revision
	 *
	 * @return array
	 */
	public function stabilizeInclusions( RevisionRecord $revision ): array {
		$inclusions = $this->getCurrentInclusions( $revision->getPageAsLinkTarget() );
		return [
			'transclusions' => $this->storeTransclusions( $revision, $inclusions['transclusions'] ),
			'images' => $this->storeImages( $revision, $inclusions['images'] ),
		];
	}

	/**
	 * @param RevisionRecord $revisionRecord
	 *
	 * @return bool
	 */
	public function removeStableInclusionsForRevision( RevisionRecord $revisionRecord ): bool {
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );
		$r1 = $dbw->delete(
			'stable_transclusions',
			[ 'st_revision' => $revisionRecord->getId() ],
			__METHOD__
		);
		$r2 = $dbw->delete(
			'stable_file_transclusions',
			[ 'sft_revision' => $revisionRecord->getId() ],
			__METHOD__
		);
		return $r1 && $r2;
	}

	/**
	 * @param PageIdentity $page
	 *
	 * @return bool
	 */
	public function removeStableInclusionsForPage( PageIdentity $page ): bool {
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );
		$r1 = $dbw->delete(
			'stable_transclusions',
			[ 'st_page' => $page->getId() ],
			__METHOD__
		);
		$r2 = $dbw->delete(
			'stable_file_transclusions',
			[ 'sft_page' => $page->getId() ],
			__METHOD__
		);

		return $r1 && $r2;
	}

	/**
	 * Retrieve stabilized inclusions for given revision
	 *
	 * @param RevisionRecord $revisionRecord
	 *
	 * @return array[]
	 */
	public function getStableInclusions( RevisionRecord $revisionRecord ): array {
		$inclusions = [
			'transclusions' => $this->retrieveTransclusions( $revisionRecord ),
			'images' => $this->retrieveImages( $revisionRecord ),
		];
		if ( $this->getInclusionMode() === null ) {
			return $inclusions;
		}
		return $this->getInclusionMode()->stabilizeInclusions( $inclusions, $revisionRecord );
	}

	/**
	 * Retrieve current stabilized inclusions for given revision
	 * Difference to `getStableInclusions` is that this method will not base its result
	 * on the versions of the inclusions that were stable at the time of the revision,
	 * but will look up the current version of the inclusions.
	 *
	 * @param RevisionRecord $revisionRecord
	 *
	 * @return array|array[]
	 */
	public function getCurrentStabilizedInclusions( RevisionRecord $revisionRecord ) {
		$cacheKey = __METHOD__ . $revisionRecord->getId();
		$inclusionMode = $this->getInclusionMode();
		if ( $inclusionMode instanceof InclusionMode ) {
			$cacheKey .= get_class( $inclusionMode );
		}

		if ( $this->cache->hasKey( $cacheKey ) ) {
			return $this->cache->get( $cacheKey );
		}
		$current = $this->getCurrentInclusions( $revisionRecord->getPageAsLinkTarget() );
		if ( $inclusionMode === null ) {
			$this->cache->set( $cacheKey, $current );
			return $current;
		}

		$stabilized = $inclusionMode->stabilizeInclusions( $current, $revisionRecord );
		$this->cache->set( $cacheKey, $stabilized );
		return $stabilized;
	}

	/**
	 * @return InclusionMode|null
	 */
	private function getInclusionMode(): ?InclusionMode {
		if ( $this->inclusionMode === false ) {
			$enabled = $this->config->get( 'InclusionMode' );
			if ( $enabled && isset( $this->inclusionModes[$enabled] ) ) {
				$this->inclusionMode = $this->inclusionModes[$enabled];
			} else {
				$this->inclusionMode = null;
			}
		}
		return $this->inclusionMode;
	}

	/**
	 * Get the latest revisions of inclusions at this time
	 *
	 * @param LinkTarget $target
	 *
	 * @return array[]
	 */
	private function getCurrentInclusions( LinkTarget $target ): array {
		$page = $this->wikiPageFactory->newFromLinkTarget( $target );
		$parserOptions = $page->makeParserOptions( 'canonical' );
		$parser = $this->parserFactory->create();
		// Set reason to let other extension know why this render is happening
		$parserOptions->setRenderReason( 'CS_INCLUSION_FETCH' );

		$parserOutput = $parser->parse(
			$page->getContent()->getWikitextForTransclusion(),
			$page->getTitle(),
			$parserOptions,
			/* $linestart = */ true,
			/* $clearstate = */ true,
			$page->getTitle()->getLatestRevID()
		);
		$transclusions = $parserOutput->getLinkList( ParserOutputLinkTypes::TEMPLATE );
		$images = $parserOutput->getLinkList( ParserOutputLinkTypes::MEDIA );

		$res = [ 'transclusions' => [], 'images' => [] ];
		foreach ( $transclusions as $transclusion ) {
			if ( $target->isSameLinkAs( $transclusion['link'] ) ) {
				continue;
			}
			$res['transclusions'][] = [
				'source' => 'local',
				'revision' => $transclusion['revid'],
				'namespace' => $transclusion['link']->getNamespace(),
				'title' => $transclusion['link']->getDBkey(),
			];
		}

		foreach ( $images as $image ) {
			// Source for images is the repo key
			$source = 'local';
			$file = $this->repoGroup->findFile( $image['link'] );
			if ( $file ) {
				$source = $file->getRepo()->getName();
			}
			$res['images'][] = [
				'source' => $source,
				'revision' => $this->revisionLookup->getRevisionByTitle( $image['link'] )?->getId() ?: 0,
				'name' => $image['link']->getDBkey(),
				'timestamp' => $image['time'],
				'sha1' => $image['sha1'] ?: '',
			];
		}

		$this->hookContainer->run( 'ContentStabilizationGetCurrentInclusions', [ $page, &$res ] );

		return $res;
	}

	/**
	 * @param RevisionRecord $main
	 * @param array $transclusions
	 *
	 * @return array
	 */
	private function storeTransclusions( RevisionRecord $main, array $transclusions ): array {
		$data = [];
		foreach ( $transclusions as $item ) {
			$data[] = [
				'st_revision' => $main->getId(),
				'st_page' => $main->getPageId(),
				'st_transclusion_revision' => $item['revision'],
				'st_transclusion_namespace' => $item['namespace'],
				'st_transclusion_title' => $item['title'],
				'st_transclusion_source' => $item['source'] ?? ''
			];
		}
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );
		// Avoid complex comparisons, just delete and re-insert
		$dbw->delete( 'stable_transclusions', [ 'st_revision' => $main->getId() ], __METHOD__ );
		// Ignore errors, as final data returned will be what is actually inserted
		$dbw->insert( 'stable_transclusions', $data, __METHOD__, [ 'IGNORE' ] );

		// Additional query, yes, but retrieves the actual data, and its formatted
		return $this->retrieveTransclusions( $main, true );
	}

	/**
	 * @param RevisionRecord $main
	 * @param array $images
	 *
	 * @return array
	 */
	private function storeImages( RevisionRecord $main, array $images ): array {
		$data = [];
		foreach ( $images as $image ) {
			$data[] = [
				'sft_revision' => $main->getId(),
				'sft_page' => $main->getPageId(),
				'sft_file_revision' => $image['revision'],
				'sft_file_name' => $image['name'],
				'sft_file_timestamp' => $image['timestamp'],
				'sft_file_sha1' => $image['sha1'],
				'sft_file_source' => $image['source'] ?? 'local',
			];
		}
		$dbw = $this->loadBalancer->getConnection( DB_PRIMARY );
		// Avoid complex comparisons, just delete and re-insert
		$dbw->delete( 'stable_file_transclusions', [ 'sft_revision' => $main->getId() ], __METHOD__ );
		// Ignore errors, as final data returned will be what is actually inserted
		$dbw->insert( 'stable_file_transclusions', $data, __METHOD__, [ 'IGNORE' ] );

		// Additional query, yes, but retrieves the actual data, and its formatted
		return $this->retrieveImages( $main, true );
	}

	/**
	 * @param RevisionRecord $revisionRecord
	 * @param bool $recache
	 *
	 * @return array
	 */
	private function retrieveTransclusions( RevisionRecord $revisionRecord, bool $recache = false ): array {
		$cacheKey = __METHOD__ . $revisionRecord->getId();
		if ( !$recache && $this->cache->hasKey( $cacheKey ) ) {
			return $this->cache->get( $cacheKey );
		}

		$db = $this->loadBalancer->getConnection( DB_REPLICA );
		$res = $db->select(
			'stable_transclusions',
			[
				'st_transclusion_revision', 'st_transclusion_namespace',
				'st_transclusion_title', 'st_transclusion_source'
			],
			[ 'st_revision' => $revisionRecord->getId() ],
			__METHOD__
		);

		$transclusions = [];
		foreach ( $res as $row ) {
			$source = !$row->st_transclusion_source || $row->st_transclusion_source === WikiMap::getCurrentWikiId() ?
				'local' : $row->st_transclusion_source;
			$transclusions[] = [
				'revision' => (int)$row->st_transclusion_revision,
				'namespace' => (int)$row->st_transclusion_namespace,
				'title' => $row->st_transclusion_title,
				'source' => $source,
			];
		}
		$this->cache->set( $cacheKey, $transclusions );
		return $transclusions;
	}

	/**
	 * @param RevisionRecord $main
	 * @param bool $recache
	 *
	 * @return array
	 */
	private function retrieveImages( RevisionRecord $main, bool $recache = true ): array {
		$cacheKey = __METHOD__ . $main->getId();
		if ( !$recache && $this->cache->hasKey( $cacheKey ) ) {
			return $this->cache->get( $cacheKey );
		}
		$db = $this->loadBalancer->getConnection( DB_REPLICA );
		$res = $db->select(
			'stable_file_transclusions',
			[ 'sft_file_revision', 'sft_file_name', 'sft_file_timestamp', 'sft_file_sha1', 'sft_file_source' ],
			[ 'sft_revision' => $main->getId() ],
			__METHOD__
		);

		$images = [];
		foreach ( $res as $row ) {
			$images[] = [
				'revision' => (int)$row->sft_file_revision,
				'name' => $row->sft_file_name,
				'timestamp' => $row->sft_file_timestamp,
				'sha1' => $row->sft_file_sha1,
				'source' => $row->sft_file_source ?? 'local',
			];
		}

		$this->cache->set( $cacheKey, $images );
		return $images;
	}

	/**
	 * Return changed inclusions if not in sync, empty array otherwise
	 *
	 * @param StablePoint $point
	 *
	 * @return array
	 */
	public function getSyncDifference( StablePoint $point ): array {
		if ( $this->getInclusionMode() && !$this->getInclusionMode()->canBeOutOfSync( $point->getRevision() ) ) {
			return [];
		}
		$cacheKey = __METHOD__ . $point->getRevision()->getId();
		if ( $this->cache->hasKey( $cacheKey ) ) {
			return $this->cache->get( $cacheKey );
		}
		$stableInclusions = $point->getInclusions();
		$latestInclusions = $this->getCurrentStabilizedInclusions( $point->getRevision() );
		$tc = $this->compareTransclusions( $latestInclusions['transclusions'], $stableInclusions['transclusions'] );
		$ic = $this->compareImages( $latestInclusions['images'], $stableInclusions['images'] );
		$untracked = $this->getUntrackedInclusions( $latestInclusions, $stableInclusions );

		$res = [];
		if ( !empty( $tc ) ) {
			$res['transclusions'] = $tc;
		}
		if ( !empty( $ic ) ) {
			$res['images'] = $ic;
		}
		if ( !empty( $untracked ) ) {
			$res['untracked'] = $untracked;
		}
		$this->cache->set( $cacheKey, $res );
		return $res;
	}

	/**
	 * @param array $latest
	 * @param array $stable
	 *
	 * @return array empty if same
	 */
	private function compareTransclusions( array $latest, array $stable ): array {
		return $this->rawCompare( $latest, $stable, [ 'namespace', 'title', 'revision', 'source' ] );
	}

	/**
	 * @param array $latest
	 * @param array $stable
	 *
	 * @return array empty if same
	 */
	private function compareImages( array $latest, array $stable ): array {
		return $this->rawCompare( $latest, $stable, [ 'name', 'timestamp' ] );
	}

	/**
	 * @param array $a
	 * @param array $b
	 * @param array $keys
	 *
	 * @return array
	 */
	private function rawCompare( array $a, array $b, array $keys ): array {
		$a = $this->simplifyInclusionObjects( $a, $keys );
		$b = $this->simplifyInclusionObjects( $b, $keys );

		$diff = array_diff_key( $a, $b );
		if ( !empty( $diff ) ) {
			return array_values( $diff );
		}
		$diff2 = array_diff_key( $b, $a );
		if ( !empty( $diff2 ) ) {
			return array_values( $diff2 );
		}
		return [];
	}

	/**
	 * @param array $inclusions
	 * @param array $fields
	 *
	 * @return array
	 */
	private function simplifyInclusionObjects( array $inclusions, array $fields ): array {
		$simplified = [];
		foreach ( $inclusions as $inclusion ) {
			$relevantKeys = array_intersect_key( $inclusion, array_flip( $fields ) );
			// Sort $inclusion to ensure consistent key order
			ksort( $relevantKeys );
			$simpleKey = implode( '|', array_values( $relevantKeys ) );
			$simplified[$simpleKey] = $inclusion;
		}
		return $simplified;
	}

	/**
	 * @param array $latestInclusions
	 * @param array $stableInclusions
	 *
	 * @return array
	 */
	private function getUntrackedInclusions( array $latestInclusions, array $stableInclusions ): array {
		return array_values( array_diff(
			$this->simplifyForUntrackedCheck( $latestInclusions ),
			$this->simplifyForUntrackedCheck( $stableInclusions )
		) );
	}

	/**
	 * @param array $inclusions
	 *
	 * @return array
	 */
	private function simplifyForUntrackedCheck( array $inclusions ): array {
		$simplified = [];
		foreach ( $inclusions['transclusions'] as $inclusion ) {
			$simplified[] = 't|' . $inclusion['namespace'] . '|' . $inclusion['title'];
		}
		foreach ( $inclusions['images'] as $inclusion ) {
			$simplified[] = 'i|' . $inclusion['name'];
		}

		return $simplified;
	}
}
