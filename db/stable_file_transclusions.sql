CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/stable_file_transclusions (
	`sft_revision` INT NOT NULL,
	`sft_page` INT NOT NULL,
	`sft_file_revision` INT NOT NULL,
	`sft_file_name` VARCHAR(255) NOT NULL,
	`sft_file_timestamp` VARBINARY(14) NOT NULL,
	`sft_file_sha1` VARBINARY(32) NOT NULL
	) /*$wgDBTableOptions*/;
CREATE UNIQUE INDEX `sft_revision_file_name_timestamp` ON /*$wgDBprefix*/stable_file_transclusions (`sft_revision`, `sft_file_name`, `sft_file_timestamp` );
