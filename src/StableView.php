<?php

namespace MediaWiki\Extension\ContentStabilization;

use JsonSerializable;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\User\UserIdentity;

class StableView implements JsonSerializable {

	public const STATE_FIRST_UNSTABLE = 'first-unstable';
	public const STATE_STABLE = 'stable';
	public const STATE_UNSTABLE = 'unstable';
	// Stable, with changed resources
	public const STATE_IMPLICIT_UNSTABLE = 'implicit-unstable';

	/** @var RevisionRecord|null Null if no revision that can be shown to the user is selected */
	private $revision;

	/** @var StablePoint|null */
	private $point;

	/** @var UserIdentity|null */
	private $forUser;

	/** @var array */
	private $inclusions;

	/** @var string */
	private $status;

	/** @var bool */
	private $needsStabilization;

	/** @var array */
	private $outOfSyncInclusions;

	/**
	 * @param RevisionRecord|null $revision
	 * @param UserIdentity|null $forUser
	 * @param array $inclusions
	 * @param StablePoint|null $point
	 * @param string $status
	 * @param bool $needsStabilization
	 * @param array $outOfSyncInclusions
	 */
	public function __construct(
		?RevisionRecord $revision, ?UserIdentity $forUser, array $inclusions,
		?StablePoint $point, string $status, bool $needsStabilization, array $outOfSyncInclusions
	) {
		$this->revision = $revision;
		$this->forUser = $forUser;
		$this->inclusions = $inclusions;
		$this->point = $point;
		$this->status = $status;
		$this->needsStabilization = $needsStabilization;
		$this->outOfSyncInclusions = $outOfSyncInclusions;
	}

	/**
	 * @return PageIdentity|null
	 */
	public function getPage(): ?PageIdentity {
		return $this->getRevision() instanceof RevisionRecord ?
			$this->getRevision()->getPage() : null;
	}

	/**
	 * @return RevisionRecord|null
	 */
	public function getRevision(): ?RevisionRecord {
		return $this->revision;
	}

	/**
	 * @return StablePoint|null if not stable points available
	 */
	public function getLastStablePoint(): ?StablePoint {
		return $this->point;
	}

	/**
	 * @return array
	 */
	public function getInclusions(): array {
		return $this->inclusions;
	}

	/**
	 * @return string
	 */
	public function getStatus(): string {
		return $this->status;
	}

	/**
	 * @return UserIdentity|null
	 */
	public function getTargetUser(): ?UserIdentity {
		return $this->forUser;
	}

	/**
	 * @return bool
	 */
	public function isStable(): bool {
		return $this->status === self::STATE_STABLE;
	}

	/**
	 * @return bool
	 */
	public function hasStable(): bool {
		return $this->point instanceof StablePoint;
	}

	/**
	 * @return bool
	 */
	public function doesNeedStabilization(): bool {
		return $this->needsStabilization;
	}

	/**
	 * @return array
	 */
	public function getOutOfSyncInclusions(): array {
		return $this->outOfSyncInclusions;
	}

	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'page' => $this->getPage() ? $this->getPage()->getId() : null,
			'revision' => $this->getRevision() ? $this->getRevision()->getId() : null,
			'forUser' => $this->getTargetUser() ? $this->getTargetUser()->getName() : null,
			'status' => $this->getStatus(),
			'needs_stabilization' => $this->doesNeedStabilization(),
			'inclusions' => $this->getInclusions(),
			'outOfSyncInclusions' => $this->getOutOfSyncInclusions(),
			// 'lastStablePoint' => $this->getLastStablePoint() ? $this->getLastStablePoint()->jsonSerialize() : null,
		];
	}
}
