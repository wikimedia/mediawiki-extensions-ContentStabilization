<?php

namespace MediaWiki\Extension\ContentStabilization\Rest;

use Config;
use MediaWiki\Extension\ContentStabilization\Data\StabilizedPages\Store;
use MediaWiki\Extension\ContentStabilization\StabilizationLookup;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MWStake\MediaWiki\Component\CommonWebAPIs\Rest\QueryStore;
use MWStake\MediaWiki\Component\DataStore\IStore;
use RequestContext;
use Wikimedia\Rdbms\ILoadBalancer;

class StabilizationOverviewHandler extends QueryStore {

	/**
	 * @var PermissionManager
	 */
	private $permissionManager;

	/**
	 * @var StabilizationLookup
	 */
	private $lookup;

	/**
	 * @var ILoadBalancer
	 */
	private $lb;
	/**
	 * @var array
	 */
	private $enabledNamespace;

	/**
	 * @param HookContainer $hookContainer
	 * @param PermissionManager $permissionManager
	 * @param StabilizationLookup $lookup
	 * @param ILoadBalancer $lb
	 * @param Config $config
	 */
	public function __construct(
		HookContainer $hookContainer, PermissionManager $permissionManager,
		StabilizationLookup $lookup, ILoadBalancer $lb, Config $config
	) {
		parent::__construct( $hookContainer );
		$this->permissionManager = $permissionManager;
		$this->lookup = $lookup;
		$this->lb = $lb;
		$this->enabledNamespace = $config->get( 'ContentStabilizationEnabledNamespaces' );
	}

	/**
	 * @return Response|mixed
	 * @throws HttpException
	 */
	public function execute() {
		$this->assertPermissions();
		return parent::execute();
	}

	/**
	 * @return IStore
	 */
	protected function getStore() : IStore {
		return new Store( $this->lookup, $this->lb, $this->enabledNamespace );
	}

	/**
	 * @return void
	 * @throws HttpException
	 */
	private function assertPermissions() {
		$user = RequestContext::getMain()->getUser();
		if ( !$this->permissionManager->userHasRight( $user, 'contentstabilization-oversight' ) ) {
			throw new HttpException( 'Permission denied', 403 );
		}
	}
}
