<?php

namespace MediaWiki\Extension\ContentStabilization\Override;

use Liuggio\StatsdClient\Factory\StatsdDataFactoryInterface;
use MediaWiki\ChangeTags\ChangeTagsStore;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Edit\ParsoidOutputStash;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Languages\LanguageConverterFactory;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Page\PageLookup;
use MediaWiki\Page\ParserOutputAccess;
use MediaWiki\Page\RedirectStore;
use MediaWiki\Parser\Parsoid\Config\SiteConfig as ParsoidSiteConfig;
use MediaWiki\Parser\Parsoid\HtmlTransformFactory;
use MediaWiki\Rest\Handler\Helper\PageContentHelper;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRenderer;
use MediaWiki\Title\TitleFactory;
use MediaWiki\Title\TitleFormatter;
use Wikimedia\Rdbms\IConnectionProvider;
use Wikimedia\Stats\StatsFactory;

class PageRestHelperFactory extends \MediaWiki\Rest\Handler\Helper\PageRestHelperFactory {

	/**
	 * @var ServiceOptions
	 */
	private ServiceOptions $options;

	/**
	 * @var RevisionLookup
	 */
	private RevisionLookup $revisionLookup;

	/**
	 * @var TitleFormatter
	 */
	private TitleFormatter $titleFormatter;

	/**
	 * @var PageLookup
	 */
	private PageLookup $pageLookup;

	/**
	 * @var TitleFactory
	 */
	private TitleFactory $titleFactory;

	/**
	 * @var IConnectionProvider
	 */
	private IConnectionProvider $connectionProvider;

	/**
	 * @var ChangeTagsStore
	 */
	private ChangeTagsStore $changeTagStore;

	/**
	 * @var StabilizationLookup
	 */
	private StabilizationLookup $stabilizationLookup;

	/**
	 * @param ServiceOptions $options
	 * @param RevisionLookup $revisionLookup
	 * @param RevisionRenderer $revisionRenderer
	 * @param TitleFormatter $titleFormatter
	 * @param PageLookup $pageLookup
	 * @param ParsoidOutputStash $parsoidOutputStash
	 * @param StatsdDataFactoryInterface $statsDataFactory
	 * @param ParserOutputAccess $parserOutputAccess
	 * @param ParsoidSiteConfig $parsoidSiteConfig
	 * @param HtmlTransformFactory $htmlTransformFactory
	 * @param IContentHandlerFactory $contentHandlerFactory
	 * @param LanguageFactory $languageFactory
	 * @param RedirectStore $redirectStore
	 * @param LanguageConverterFactory $languageConverterFactory
	 * @param TitleFactory $titleFactory
	 * @param IConnectionProvider $connectionProvider
	 * @param ChangeTagsStore $changeTagStore
	 * @param StatsFactory $statsFactory
	 * @param StabilizationLookup $stabilizationLookup
	 */
	public function __construct(
		ServiceOptions $options, RevisionLookup $revisionLookup, RevisionRenderer $revisionRenderer,
		TitleFormatter $titleFormatter, PageLookup $pageLookup, ParsoidOutputStash $parsoidOutputStash,
		StatsdDataFactoryInterface $statsDataFactory, ParserOutputAccess $parserOutputAccess,
		ParsoidSiteConfig $parsoidSiteConfig, HtmlTransformFactory $htmlTransformFactory,
		IContentHandlerFactory $contentHandlerFactory, LanguageFactory $languageFactory, RedirectStore $redirectStore,
		LanguageConverterFactory $languageConverterFactory, TitleFactory $titleFactory,
		IConnectionProvider $connectionProvider, ChangeTagsStore $changeTagStore, StatsFactory $statsFactory,
		StabilizationLookup $stabilizationLookup
	) {
		parent::__construct(
			$options, $revisionLookup, $revisionRenderer, $titleFormatter, $pageLookup, $parsoidOutputStash,
			$statsDataFactory, $parserOutputAccess, $parsoidSiteConfig, $htmlTransformFactory, $contentHandlerFactory,
			$languageFactory, $redirectStore, $languageConverterFactory, $titleFactory, $connectionProvider,
			$changeTagStore, $statsFactory
		);
		$this->options = $options;
		$this->revisionLookup = $revisionLookup;
		$this->titleFormatter = $titleFormatter;
		$this->pageLookup = $pageLookup;
		$this->titleFactory = $titleFactory;
		$this->connectionProvider = $connectionProvider;
		$this->changeTagStore = $changeTagStore;
		$this->stabilizationLookup = $stabilizationLookup;
	}

	/**
	 * @return PageContentHelper
	 */
	public function newPageContentHelper(): PageContentHelper {
		return new StabilizedPageContentHelper(
			$this->options,
			$this->revisionLookup,
			$this->titleFormatter,
			$this->pageLookup,
			$this->titleFactory,
			$this->connectionProvider,
			$this->changeTagStore,
			$this->stabilizationLookup
		);
	}
}
