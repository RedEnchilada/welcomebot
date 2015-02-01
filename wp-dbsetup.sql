/* Run this on your database with the following command:
 * mysql -u {DATABASEUSER} -p {DATABASENAME} < wp-dbsetup.sql
 * Then enter the database user's password.
 */

CREATE TABLE IF NOT EXISTS `messages` (
	`enabled`
		BOOLEAN
	,

	`user`
		VARCHAR(64)
		CHARACTER SET utf8
		NOT NULL
		UNIQUE
	,
	
	`notice`
		INT UNSIGNED
	,
	
	`message`
		VARCHAR(1000)
		CHARACTER SET utf8
	,
	
	PRIMARY KEY (notice)
);

CREATE TABLE IF NOT EXISTS `admins` (
	`id`
		INT UNSIGNED
	,
	
	PRIMARY KEY (notice)
);