<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\AlertProvider;

use BlueSpice\AlertProviderBase;
use MediaWiki\Config\Config;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StableView;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\TitleFactory;
use OOUI\ButtonWidget;
use OOUI\HorizontalLayout;
use OOUI\LabelWidget;
use OOUI\Layout;
use SkinTemplate;
use Wikimedia\Rdbms\ILoadBalancer;

class RevisionState extends AlertProviderBase {

	/**
	 * @inheritDoc
	 */
	public static function factory( $skin = null ) {
		return new static(
			$skin,
			MediaWikiServices::getInstance()->getDBLoadBalancer(),
			MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'bsg' ),
			MediaWikiServices::getInstance()->getService( 'ContentStabilization.Lookup' ),
			MediaWikiServices::getInstance()->getTitleFactory()
		);
	}

	/**
	 * @var StabilizationLookup
	 */
	protected $lookup;

	/**
	 * @var TitleFactory
	 */
	protected $titleFactory;

	/**
	 * @var Layout
	 */
	private $revisionStateLayout;

	/**
	 * @param SkinTemplate $skin
	 * @param ILoadBalancer $loadBalancer
	 * @param Config $config
	 * @param StabilizationLookup $lookup
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		$skin, $loadBalancer, $config,
		StabilizationLookup $lookup, TitleFactory $titleFactory ) {
		parent::__construct( $skin, $loadBalancer, $config );
		$this->lookup = $lookup;
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @return string
	 */
	public function getHTML() {
		$this->initFromContext();
		if ( $this->revisionStateLayout ) {
			return $this->revisionStateLayout->toString();
		}
		return '';
	}

	/**
	 * @return string
	 */
	public function getType() {
		return 'warning';
	}

	protected function initFromContext() {
		if ( $this->skipForContextReasons() ) {
			return;
		}

		$view = $this->lookup->getStableViewFromContext( $this->skin->getContext() );
		if ( !$view ) {
			return;
		}
		if ( $view->getStatus() !== StableView::STATE_IMPLICIT_UNSTABLE ) {
			return;
		}

		$out = $this->skin->getOutput();
		$out->enableOOUI();
		$out->addModuleStyles( 'ext.contentStabilization.alert.styles' );
		$out->addModules( 'ext.contentStabilization.alert' );

		$message = $this->skin->msg(
			'contentstabilization-state-draft-resources-desc'
		);

		$labelWidget = new LabelWidget( [ 'label' => $message->parse() ] );
		$infoBtn = new ButtonWidget( [
			'icon' => 'infoFilled',
			'id' => 'content-stabilization-banner-info-btn',
			'infusable' => true,
			'framed' => false,
			'data' => $this->getChangedInclusions( $view )
		] );
		$items['items'] = [ $labelWidget, $infoBtn ];
		$this->revisionStateLayout = new HorizontalLayout( $items );
	}

	/**
	 * @return bool
	 */
	protected function skipForContextReasons() {
		if ( !$this->skin->getTitle() ) {
			return true;
		}
		if ( !$this->skin->getTitle()->exists() ) {
			return true;
		}
		if ( !$this->lookup->isStabilizationEnabled( $this->skin->getTitle() ) ) {
			return true;
		}

		$request = $this->skin->getRequest();
		$currentAction = $request->getVal( 'veaction', $request->getVal( 'action', 'view' ) );
		if ( $currentAction !== 'view' ) {
			return true;
		}

		return false;
	}

	/**
	 * Get links of out-of-sync inclusions
	 *
	 * @param StableView $view
	 *
	 * @return array
	 */
	private function getChangedInclusions( StableView $view ): array {
		$outOfSyncRaw = $view->getOutOfSyncInclusions();
		$outOfSync = [];
		if ( isset( $outOfSyncRaw['transclusions'] ) ) {
			foreach ( $outOfSyncRaw['transclusions'] as $transclusion ) {
				$title = $this->titleFactory->makeTitle( $transclusion['namespace'], $transclusion['title'] );
				$outOfSync[$title->getPrefixedText()] = $title->getLocalURL( [ 'diff' => $transclusion['revision'] ] );
			}
		}
		if ( isset( $outOfSyncRaw['images'] ) ) {
			foreach ( $outOfSyncRaw['images'] as $image ) {
				$title = $this->titleFactory->makeTitle( NS_FILE, $image['name'] );
				$outOfSync[$title->getPrefixedText()] = $title->getLocalURL();
			}
		}
		return $outOfSync;
	}
}
