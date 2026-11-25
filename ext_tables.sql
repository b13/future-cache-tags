CREATE TABLE tx_futurecachetags_schedule (
	uid int(11) unsigned NOT NULL auto_increment,
	tablename varchar(255) DEFAULT '' NOT NULL,
	record_uid int(11) unsigned DEFAULT '0' NOT NULL,
	due_at int(11) unsigned DEFAULT '0' NOT NULL,
	tags mediumtext,
	cache_groups varchar(255) DEFAULT '' NOT NULL,
	updated_at int(11) unsigned DEFAULT '0' NOT NULL,

	PRIMARY KEY (uid),
	UNIQUE KEY record (tablename, record_uid),
	KEY due (due_at)
);
