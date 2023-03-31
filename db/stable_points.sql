CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/stable_points (
	`sp_revision` INT NOT NULL PRIMARY KEY,
	`sp_page` INT NOT NULL,
	`sp_time` VARCHAR(14) NOT NULL,
    `sp_user` INT NOT NULL,
    `sp_comment` TEXT NULL
	) /*$wgDBTableOptions*/;
