-- This file is automatically generated using maintenance/generateSchemaSql.php.
-- Source: db/stable_file_points.json
-- Do not modify this file directly.
-- See https://www.mediawiki.org/wiki/Manual:Schema_changes
CREATE TABLE /*_*/stable_file_points (
  sfp_revision INTEGER NOT NULL,
  sfp_page INTEGER NOT NULL,
  sfp_file_timestamp BLOB NOT NULL,
  sfp_file_sha1 BLOB NOT NULL,
  PRIMARY KEY(sfp_revision)
);
