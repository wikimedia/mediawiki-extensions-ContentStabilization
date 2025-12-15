<?php

namespace MediaWiki\Extension\ContentStabilization\Integration\Hook;

use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Hook\SkinAfterContentHook;
use MediaWiki\Html\Html;

class AddStabilizationInfoOnView implements SkinAfterContentHook {

	/**
	 * @param StabilizationLookup $stabilizationLookup
	 */
	public function __construct(
		private readonly StabilizationLookup $stabilizationLookup
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onSkinAfterContent( &$data, $skin ) {
		if ( get_class( $skin ) === 'BlueSpice\Discovery\Skin' ) {
			// Not supported in BlueSpice Discovery skin
			return;
		}
		if ( !$skin->getTitle() || !$skin->getTitle()->exists() ) {
			return;
		}
		if ( !$this->stabilizationLookup->isStabilizationEnabled( $skin->getTitle() ) ) {
			return;
		}
		if ( !$this->stabilizationLookup->canUserSeeUnstable( $skin->getUser() ) ) {
			return;
		}
		$view = $this->stabilizationLookup->getStableViewFromContext( $skin->getContext() );
		if ( !$view ) {
			return;
		}
		// contentstabilization-pageinfoelement-pagestatus-is-unstable-title
		// contentstabilization-pageinfoelement-pagestatus-is-first-unstable-title
		// contentstabilization-pageinfoelement-pagestatus-is-stable-title
		// contentstabilization-pageinfoelement-pagestatus-is-implicit-unstable-title
		$text = $skin->getContext()->msg(
			'contentstabilization-pageinfoelement-pagestatus-is-' . $view->getStatus() . '-title'
		);
		try {
			$stable = $view->getLastStablePoint();
			if ( $view->isStable() && $stable ) {
				$ts = $stable->getTime()->format( 'YmdHis' );
				$text .= Html::rawElement(
					'div',
					[ 'class' => 'content-stabilization-last-stable-info' ],
					$skin->getContext()->msg(
						'contentstabilization-info-last-approved-date',
						$skin->getLanguage()->userTime( $ts, $skin->getUser(), [ 'adjust' => true ] ),
						$skin->getLanguage()->userDate( $ts, $skin->getUser() )
					)->text()
				);
			}
		} catch ( \Exception $ex ) {
			// Skip
		}

		$html = Html::openElement( 'div', [ 'class' => 'content-stabilization-info' ] );
		$html .= Html::element( 'hr' );
		$html .= Html::rawElement( 'small', [], $text );
		$html .= Html::closeElement( 'div' );

		$data .= $html;
	}
}
