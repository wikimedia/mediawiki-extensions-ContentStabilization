<?php

namespace MediaWiki\Extension\ContentStabilization;

use DateTime;
use File;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;

class StableFilePoint extends StablePoint {

	/** @var File */
	private $file;

	/**
	 * @param File $file
	 * @param RevisionRecord $revision
	 * @param Authority $approver
	 * @param DateTime $time
	 * @param string $comment
	 * @param array|null $inclusions
	 */
	public function __construct(
		File $file, RevisionRecord $revision, Authority $approver,
		DateTime $time, string $comment, ?array $inclusions = null
	) {
		parent::__construct( $revision, $approver, $time, $comment, $inclusions );
		$this->file = $file;
	}

	/**
	 * @return File
	 */
	public function getFile(): File {
		return $this->file;
	}

	/**
	 * @return array
	 */
	public function jsonSerialize(): array {
		return parent::jsonSerialize() + [
			'file_name' => $this->getFile()->getName(),
			'file_timestamp' => $this->getFile()->getTimestamp(),
		];
	}
}
