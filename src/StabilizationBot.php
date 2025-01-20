<?php

namespace MediaWiki\Extension\ContentStabilization;

use IDBAccessObject;
use MediaWiki\Block\Block;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionStatus;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;

/**
 * Authority to be used for bot stabilization
 *
 */
class StabilizationBot implements Authority {
	/** @var string[] */
	private $permissions = [
		"contentstabilization-admin",
		"contentstabilization-oversight",
		"contentstabilization-stabilize"
	];

	/**
	 * @inheritDoc
	 */
	public function getUser(): UserIdentity {
		return User::newSystemUser( "ContentStabilizationBot", [ "steal" => true ] );
	}

	/**
	 * @inheritDoc
	 */
	public function getBlock( int $freshness = IDBAccessObject::READ_NORMAL ): ?Block {
		return null;
	}

	/**
	 * @param string $permission
	 * @param PermissionStatus|null $status
	 * @inheritDoc
	 */
	public function isAllowed( string $permission, ?PermissionStatus $status = null ): bool {
		return in_array( $permission, $this->permissions );
	}

	/**
	 * @inheritDoc
	 */
	public function isAllowedAny( ...$permissions ): bool {
		if ( !$permissions ) {
			return false;
		}
		foreach ( $permissions as $permission ) {
			if ( $this->isAllowed( $permission ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function isAllowedAll( ...$permissions ): bool {
		if ( !$permissions ) {
			return true;
		}
		foreach ( $permissions as $permission ) {
			if ( !$this->isAllowed( $permission ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function probablyCan( string $action, PageIdentity $target, ?PermissionStatus $status = null ): bool {
		return $this->isAllowed( $action );
	}

	/**
	 * @inheritDoc
	 */
	public function definitelyCan( string $action, PageIdentity $target, ?PermissionStatus $status = null ): bool {
		return $this->isAllowed( $action );
	}

	/**
	 * @inheritDoc
	 */
	public function authorizeRead( string $action, PageIdentity $target, ?PermissionStatus $status = null ): bool {
		return $this->isAllowed( $action );
	}

	/**
	 * @inheritDoc
	 */
	public function authorizeWrite( string $action, PageIdentity $target, ?PermissionStatus $status = null ): bool {
		return $this->isAllowed( $action );
	}

	/**
	 * @inheritDoc
	 */
	public function isRegistered(): bool {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function isTemp(): bool {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function isNamed(): bool {
		return true;
	}

	/**
	 * @param string $action
	 * @param PermissionStatus|null $status
	 * @return bool
	 */
	public function isDefinitelyAllowed( string $action, ?PermissionStatus $status = null ): bool {
		return $this->isAllowed( $action, $status );
	}

	/**
	 * @param string $action
	 * @param PermissionStatus|null $status
	 * @return bool
	 */
	public function authorizeAction( string $action, ?PermissionStatus $status = null ): bool {
		return $this->isAllowed( $action, $status );
	}
}
