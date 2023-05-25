<?php

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

namespace MediaWiki\Extension\ContentStabilization\Hook;

use IContextSource;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Permissions\PermissionManager;

class AddApproveAction implements SkinTemplateNavigation__UniversalHook {

	/** @var StabilizationLookup */
	private $lookup;

	/** @var PermissionManager */
	private $permissionManager;

	/**
	 * @param StabilizationLookup $lookup
	 * @param PermissionManager $permissionManager
	 */
	public function __construct( StabilizationLookup $lookup, PermissionManager $permissionManager ) {
		$this->lookup = $lookup;
		$this->permissionManager = $permissionManager;
	}

	/**
	 * @inheritDoc
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( !$sktemplate->getTitle()->exists() || !$this->canApprove( $sktemplate->getContext() ) ) {
			return;
		}
		$links['actions']['cs-approve'] = [
			'text' => $sktemplate->getContext()->msg( 'contentstabilization-ui-approve-title' )->text(),
			'href' => '#',
			'class' => false,
			'id' => 'ca-cs-approve',
			'position' => 12,
		];
	}

	/**
	 * @param IContextSource $context
	 *
	 * @return bool
	 */
	private function canApprove( IContextSource $context ) {
		$view = $this->lookup->getStableViewFromContext( $context );
		if ( !$view ) {
			return false;
		}
		if ( $view->isStable() || !$view->doesNeedStabilization() ) {
			return false;
		}
		return $this->permissionManager->userCan(
			'contentstabilization-stabilize',
			$context->getUser(),
			$context->getTitle()
		);
	}
}
