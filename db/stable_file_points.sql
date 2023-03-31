CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/stable_file_points (
	`sfp_revision` INT NOT NULL PRIMARY KEY,
	`sfp_page` INT NOT NULL,
	`sfp_file_timestamp` VARBINARY(14) NOT NULL,
	`sfp_file_sha1` VARBINARY(32) NOT NULL
	) /*$wgDBTableOptions*/;
