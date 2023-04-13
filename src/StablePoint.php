<?php

namespace MediaWiki\Extension\ContentStabilization;

use DateTime;
use JsonSerializable;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;

class StablePoint implements JsonSerializable {

	/** @var PageIdentity */
	private $page;

	/** @var RevisionRecord */
	private $revision;

	/** @var DateTime */
	private $time;

	/** @var string */
	private $comment;

	/** @var Authority */
	private $approver;

	/** @var array|null */
	private $inclusions = null;

	/**
	 * @param RevisionRecord $revision
	 * @param Authority $approver
	 * @param DateTime $time
	 * @param string $comment
	 */
	public function __construct(
		RevisionRecord $revision, Authority $approver, DateTime $time, string $comment
	) {
		$this->revision = $revision;
		$this->approver = $approver;
		$this->time = $time;
		$this->comment = $comment;
		$this->page = $revision->getPage();
	}

	/**
	 * @return PageIdentity
	 */
	public function getPage(): PageIdentity {
		return $this->page;
	}

	/**
	 * @return RevisionRecord
	 */
	public function getRevision(): RevisionRecord {
		return $this->revision;
	}

	/**
	 * @return DateTime
	 */
	public function getTime(): DateTime {
		return $this->time;
	}

	/**
	 * @return string
	 */
	public function getComment(): string {
		return $this->comment;
	}

	/**
	 * @return Authority
	 */
	public function getApprover(): Authority {
		return $this->approver;
	}

	/**
	 * @return array
	 */
	public function getInclusions(): array {
		if ( $this->inclusions === null ) {
			throw new \UnexpectedValueException( 'Stable point not decorated with inclusions' );
		}
		return $this->inclusions;
	}

	/**
	 * @param array $inclusions
	 *
	 * @return void
	 */
	public function setInclusions( array $inclusions ) {
		$this->inclusions = $inclusions;
	}

	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return [
			'page' => $this->page->getId(),
			'revision' => $this->revision->getId(),
			'time' => $this->time->format( 'YmdHis' ),
			'approver' => $this->getApprover()->getName(),
			'comment' => $this->getComment(),
			'inclusions' => $this->getInclusions()
		];
	}
}
