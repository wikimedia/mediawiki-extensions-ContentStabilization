<?php

namespace MediaWiki\Extension\ContentStabilization\Special;

use Language;
use MediaWiki\Config\Config;
use OOUI\MessageWidget;
use SpecialPage;

class ContentStabilization extends SpecialPage {

	/**
	 * @var Config
	 */
	private $csConfig;

	/**
	 * @var Language
	 */
	private $language;

	/**
	 * @param Config $csConfig
	 * @param Language $language
	 */
	public function __construct( Config $csConfig, Language $language ) {
		parent::__construct( "ContentStabilization", "contentstabilization-oversight" );

		$this->csConfig = $csConfig;
		$this->language = $language;
	}

	/**
	 * @param string $subPage
	 *
	 * @return void
	 */
	public function execute( $subPage ) {
		parent::execute( $subPage );

		$this->getOutput()->addModules( [ 'ext.contentStabilization.special' ] );
		$this->getOutput()->enableOOUI();

		$this->addEnabledNSBanner();

		$this->getOutput()->addHTML( '<div id="contentstabilization"></div>' );
	}

	protected function addEnabledNSBanner() {
		$names = [];
		$enabled = $this->csConfig->get( 'EnabledNamespaces' );
		foreach ( $enabled as $nsId ) {
			if ( $nsId === NS_MAIN ) {
				$names[] = $this->getContext()->msg( 'blanknamespace' )->text();
				continue;
			}
			$names[] = $this->language->getNsText( $nsId );
		}

		if ( empty( $names ) ) {
			$message = $this->getContext()->msg( 'contentstabilization-overview-no-enabled' );
		} else {
			$message = $this->getContext()->msg( 'contentstabilization-overview-enabled' );
			$message->params( $this->language->listToText( $names ) );
		}

		$widget = new MessageWidget( [
			'label' => $message->text(),
			'type' => 'info'
		] );
		$this->getOutput()->addHTML( $widget );
		$this->getOutput()->addHtml( '<br><hr><br>' );
	}
}
