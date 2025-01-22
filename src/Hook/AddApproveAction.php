<?php

// phpcs:disable MediaWiki.NamingConventions.LowerCamelFunctionsName.FunctionName

namespace MediaWiki\Extension\ContentStabilization\Hook;

use EditPage;
use IContextSource;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\Extension\ContentStabilization\StableView;
use MediaWiki\Hook\EditPageGetCheckboxesDefinitionHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\PermissionManager;

class AddApproveAction implements SkinTemplateNavigation__UniversalHook, EditPageGetCheckboxesDefinitionHook {

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
		if ( $sktemplate->getRequest()->getText( 'action', 'view' ) !== 'view' ) {
			return;
		}
		if ( !$this->lookup->isStabilizationEnabled( $sktemplate->getTitle() ) ) {
			return;
		}
		$view = $this->lookup->getStableViewFromContext( $sktemplate->getContext() );
		if ( !$view ) {
			return;
		}
		if ( !$sktemplate->getTitle()->exists() ) {
			return;
		}
		if ( $this->canApproveFromViewMode( $view, $sktemplate->getContext() ) ) {
			$links['actions']['cs-approve'] = [
				'text' => $sktemplate->getContext()->msg( 'contentstabilization-ui-approve-title' )->text(),
				'href' => '#',
				'class' => false,
				'id' => 'ca-cs-approve',
				'position' => 12,
			];
		}
		if ( $this->canSwitchToDraft( $view, $sktemplate->getContext() ) ) {
			$links['views']['cs-switch-to-draft'] = [
				'text' => $sktemplate->getContext()->msg( 'contentstabilization-ui-switch-to-draft-title' )->text(),
				'href' => $sktemplate->getTitle()->getLocalURL( [ 'stable' => 0 ] ),
				'class' => false,
				'id' => 'cs-switch-to-draft',
				'position' => 13,
			];
		}
		if ( $this->canSwitchToStable( $view ) ) {
			$links['views']['cs-switch-to-stable'] = [
				'text' => $sktemplate->getContext()->msg( 'contentstabilization-ui-switch-to-stable-title' )->text(),
				'href' => $sktemplate->getTitle()->getLocalURL( [ 'stable' => 1 ] ),
				'class' => false,
				'id' => 'cs-switch-to-stable',
				'position' => 13,
			];
		}
	}

	/**
	 * @param EditPage $editpage
	 * @param array &$checkboxes
	 * @return void
	 */
	public function onEditPageGetCheckboxesDefinition( $editpage, &$checkboxes ) {
		if ( !$this->lookup->isStabilizationEnabled( $editpage->getTitle() ) ) {
			return;
		}
		if ( !$this->hasPermission( 'contentstabilization-stabilize', $editpage->getContext() ) ) {
			return;
		}

		$pending = $this->lookup->getPendingUnstableRevisions( $editpage->getTitle() );
		if ( !empty( $pending ) ) {
			$label = Message::newFromKey( 'contentstabilization-ui-editor-approve-with-pending', count( $pending ) );
			$titleMsg = 'contentstabilization-ui-editor-approve-with-pending-title';
		} else {
			$label = Message::newFromKey( 'contentstabilization-ui-editor-approve' );
			$titleMsg = 'contentstabilization-ui-editor-approve-title';
		}
		$checkboxes['wpStabilize'] = [
			'label-message' => $label,
			'id' => 'wpStabilize',
			'default' => false,
			'title-message' => $titleMsg
		];
	}

	/**
	 * @param StableView $view
	 * @param IContextSource $context
	 * @return bool
	 */
	private function canApproveFromViewMode( StableView $view, IContextSource $context ): bool {
		if ( $view->isStable() || !$view->doesNeedStabilization() ) {
			return false;
		}
		return $this->hasPermission( 'contentstabilization-stabilize', $context );
	}

	/**
	 * @param string $permission
	 * @param IContextSource $context
	 * @return bool
	 */
	private function hasPermission( string $permission, IContextSource $context ): bool {
		return $this->permissionManager->userCan(
			$permission,
			$context->getUser(),
			$context->getTitle()
		);
	}

	/**
	 * @param StableView $view
	 * @param IContextSource $context
	 *
	 * @return bool
	 */
	private function canSwitchToDraft( StableView $view, IContextSource $context ): bool {
		if ( !$view->isStable() ) {
			return false;
		}
		return $view->doesNeedStabilization() && $this->lookup->canUserSeeUnstable( $context->getUser() );
	}

	/**
	 * @param StableView $view
	 *
	 * @return bool
	 */
	private function canSwitchToStable( StableView $view ): bool {
		if ( $view->isStable() ) {
			return false;
		}
		return $view->hasStable();
	}
}
