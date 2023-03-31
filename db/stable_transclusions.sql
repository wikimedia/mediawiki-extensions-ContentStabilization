CREATE TABLE IF NOT EXISTS /*$wgDBprefix*/stable_transclusions (
	`st_revision` INT NOT NULL,
	`st_page` INT NOT NULL,
	`st_transclusion_revision` INT NOT NULL,
	`st_transclusion_namespace` INT NOT NULL,
	`st_transclusion_title` VARCHAR(255) NOT NULL
	) /*$wgDBTableOptions*/;
CREATE UNIQUE INDEX `st_revision_transclusion_revision` ON /*$wgDBprefix*/stable_transclusions (`st_revision`, `st_transclusion_revision` );
