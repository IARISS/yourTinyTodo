<?php

/*
This file is part of yourTinyTodo by the yourTinyTodo community.
Copyrights for portions of this file are retained by their owners.

Based on myTinyTodo by Max Pozdeev
(C) Copyright 2009-2010 Max Pozdeev <maxpozdeev@gmail.com>

Licensed under the GNU GPL v3 license. See file COPYRIGHT for details.
*/

define('YTT_VERSION', 'ytt1.0a');

set_exception_handler('myExceptionHandler');

# Check old config file (prior v1.3)
if(file_exists('./db/config.php')) {
	require_once('./db/config.php');
} else {
	require_once('./db/config.default.php');
}
if(!isset($config['db']))
{
	if(isset($config['mysql'])) {
		$config['db'] = 'mysql';
		$config['mysql.host'] = $config['mysql'][0];
		$config['mysql.db'] = $config['mysql'][3];
		$config['mysql.user'] = $config['mysql'][1];
		$config['mysql.password'] = $config['mysql'][2];
	} elseif(isset($config['postgres'])) {
		$config['db'] = 'mysql';
		$config['postgres.host'] = $config['postgres'][0];
		$config['postgres.db'] = $config['postgres'][3];
		$config['postgres.user'] = $config['postgres'][1];
		$config['postgres.password'] = $config['postgres'][2];
	} else {
		$config['db'] = 'sqlite';
	}
	if(isset($config['allow']) && $config['allow'] == 'read') $config['allowread'] = 1;
}

if($config['db'] != '')
{
	require_once('./init.php');
	if($needAuth && !is_logged())
	{
		die("Access denied!<br/> Disable password protection or Log in.");
	}
	if(strtolower(get_class($db)) == 'database_mysql') {
		$dbtype = 'mysql';
	} elseif (strtolower(get_class($db)) == 'database_postgres') {
		$dbtype = 'postgres';
	} else {
		$dbtype = 'sqlite';
	}
}
else
{
	if(!defined('YTTPATH')) define('YTTPATH', dirname(__FILE__) .'/');
	require_once(YTTPATH. 'common.php');
	Config::loadConfig($config);
	unset($config);

	$db = 0;
	$dbtype = '';
}

echo '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd"><html xmlns="http://www.w3.org/1999/xhtml"><head><meta name="robots" content="noindex,nofollow" /><title>yourTinyTodo '.YTT_VERSION.' Setup</title></head><body>';
echo "<big><b>yourTinyTodo ".YTT_VERSION." Setup</b></big><br/><br/>";

# determine current installed version
$ver = get_ver($db, $dbtype);

if(!$ver)
{
	# Which DB to select
	if(!isset($_POST['installdb']) && !isset($_POST['install']))
	{
		exitMessage('<form method="post" action="">Select database type to use:<br/><br/>
<label><input type="radio" name="installdb" value="sqlite" checked="checked" onclick="document.getElementById(\'mysqlsettings\').style.display=\'none\'" />SQLite</label><br/><br/>
<label><input type="radio" name="installdb" value="mysql" onclick="document.getElementById(\'mysqlsettings\').style.display=\'\';document.getElementById(\'postgressettings\').style.display=\'none\'" />MySQL</label><br/><br/>
<label><input type="radio" name="installdb" value="postgres" onclick="document.getElementById(\'postgressettings\').style.display=\'\';document.getElementById(\'mysqlsettings\').style.display=\'none\'" />Postgres</label><br/>
<div id="mysqlsettings" style="display:none; margin-left:30px;"><br/><table><tr><td>Host:</td><td><input type="text" name="mysql_host" value="localhost" /></td></tr>
<tr><td>Database:</td><td><input type="text" name="mysql_db" value="iariss_" /></td></tr>
<tr><td>User:</td><td><input type="text" name="mysql_user" value="iariss_" /></td></tr>
<tr><td>Password:</td><td><input type="password" name="mysql_password" /></td></tr>
<tr><td>Table prefix:</td><td><input type="text" name="mysql_prefix" value="ytt_" /></td></tr>
</table></div><div id="postgressettings" style="display:none; margin-left:30px;"><br/><table><tr><td>Host:</td><td><input type="text" name="postgres_host" value="localhost" /></td></tr>
<tr><td>Database:</td><td><input type="text" name="postgres_db" value="yourtinytodo" /></td></tr>
<tr><td>User:</td><td><input type="text" name="postgres_user" value="user" /></td></tr>
<tr><td>Password:</td><td><input type="password" name="postgres_password" /></td></tr>
<tr><td>Table prefix:</td><td><input type="text" name="postgres_prefix" value="ytt_" /></td></tr>
</table></div><br/><input type=submit value="Next" /></form>');
	}
	elseif(isset($_POST['installdb']))
	{
		# Save configuration
		$dbtype = (in_array($_POST['installdb'], array('mysql','postgres','sqlite'))) ? $_POST['installdb'] : 'sqlite';
		Config::set('db', $dbtype);
		if($dbtype == 'mysql') {
			Config::set('mysql.host', _post('mysql_host'));
			Config::set('mysql.db', _post('mysql_db'));
			Config::set('mysql.user', _post('mysql_user'));
			Config::set('mysql.password', _post('mysql_password'));
			Config::set('prefix', trim(_post('mysql_prefix')));
		}
		if($dbtype == 'postgres') {
			Config::set('postgres.host', _post('postgres_host'));
			Config::set('postgres.db', _post('postgres_db'));
			Config::set('postgres.user', _post('postgres_user'));
			Config::set('postgres.password', _post('postgres_password'));
			Config::set('prefix', trim(_post('postgres_prefix')));
		}
		if($dbtype == 'sqlite') {
			if(!is_writable('./db/')) {
				exitMessage("Database folder ('db/') is not writable.");
			}
			copy(YTTPATH.'db/todolist.default.db', YTTPATH.'db/todolist.db');
		}
		if(!testConnect($error)) {
			exitMessage("Database connection error: $error");
		}
		if(!is_writable('./db/')) {
			exitMessage("Config folder ('db/') is not writable.");
		}
		Config::save();
		exitMessage("This will create yourTinyTodo database <form method=post><input type=hidden name=install value=1><input type=submit value=' Install '></form>");
	}

	# install database
	if($dbtype == 'mysql')
	{
		try
		{

			$db->ex(
				"CREATE TABLE {$db->prefix}lists (
 id INT UNSIGNED NOT NULL auto_increment,
 uuid CHAR(36) NOT NULL default '',
 ow INT NOT NULL default 0,
 name VARCHAR(50) NOT NULL default '',
 d_created INT UNSIGNED NOT NULL default 0,
 d_edited INT UNSIGNED NOT NULL default 0,
 sorting TINYINT UNSIGNED NOT NULL default 0,
 published TINYINT UNSIGNED NOT NULL default 0,
 taskview INT UNSIGNED NOT NULL default 0,
 PRIMARY KEY(id),
 UNIQUE KEY(uuid)
) CHARSET=utf8 ");


			$db->ex(
				"CREATE TABLE {$db->prefix}todolist (
 id INT UNSIGNED NOT NULL auto_increment,
 uuid CHAR(36) NOT NULL default '',
 list_id INT UNSIGNED NOT NULL default 0,
 d_created INT UNSIGNED NOT NULL default 0,   /* time() timestamp */
 d_completed INT UNSIGNED NOT NULL default 0, /* time() timestamp */
 d_edited INT UNSIGNED NOT NULL default 0,    /* time() timestamp */
 compl TINYINT UNSIGNED NOT NULL default 0,
 title VARCHAR(250) NOT NULL,
 note TEXT,
 prio TINYINT NOT NULL default 0,			/* priority -,0,+ */
 ow INT NOT NULL default 0,				/* order weight */
 tags VARCHAR(600) NOT NULL default '',	/* for fast access to task tags */
 tags_ids VARCHAR(250) NOT NULL default '', /* no more than 22 tags (x11 chars) */
 duedate DATE default NULL,
 duration DOUBLE default NULL,
  PRIMARY KEY(id),
  KEY(list_id),
  UNIQUE KEY(uuid)
) CHARSET=utf8 ");


			$db->ex(
				"CREATE TABLE {$db->prefix}tags (
 id INT UNSIGNED NOT NULL auto_increment,
 name VARCHAR(50) NOT NULL,
 PRIMARY KEY(id),
 UNIQUE KEY name (name)
) CHARSET=utf8 ");


			$db->ex(
				"CREATE TABLE {$db->prefix}tag2task (
 tag_id INT UNSIGNED NOT NULL,
 task_id INT UNSIGNED NOT NULL,
 list_id INT UNSIGNED NOT NULL,
 KEY(tag_id),
 KEY(task_id),
 KEY(list_id)		/* for tagcloud */
) CHARSET=utf8 ");


			$db->ex(
				"CREATE TABLE IF NOT EXISTS {$db->prefix}users (
  id int(10) unsigned NOT NULL auto_increment,
  uuid varchar(36) NOT NULL,
  username varchar(50) NOT NULL,
  password varchar(32) NOT NULL,
  email varchar(100) NOT NULL,
  d_created int(10) unsigned NOT NULL,
  role enum('1','2','3') NOT NULL default '3',
  PRIMARY KEY  (id)
) CHARSET=utf8 ");


			$db->ex(
				"CREATE TABLE IF NOT EXISTS {$db->prefix}notifications (
  id int(11) NOT NULL auto_increment,
  user_id int(11) NOT NULL,
  creator_user_id int(11) NOT NULL,
  text varchar(255) NOT NULL,
  created timestamp NOT NULL default CURRENT_TIMESTAMP,
  shown tinyint(1) NOT NULL default '0',
  PRIMARY KEY  (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ; ");


			$db->ex(
				"CREATE TABLE IF NOT EXISTS {$db->prefix}notification_listeners (
  id int(11) NOT NULL auto_increment,
  user_id int(11) NOT NULL,
  type set('task','list','global') character set utf8 NOT NULL,
  value int(11) default NULL,
  PRIMARY KEY  (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ; ");


			$db->ex(
				"CREATE TABLE IF NOT EXISTS {$db->prefix}comments (
  id int(11) NOT NULL auto_increment,
  task_id int(11) NOT NULL,
  user_id int(11) NOT NULL,
  created timestamp NOT NULL default CURRENT_TIMESTAMP,
  comment varchar(255) NOT NULL,
  PRIMARY KEY  (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ; ");


			$db->ex(
				"CREATE TABLE IF NOT EXISTS {$db->prefix}time_tracker (
  id int(11) NOT NULL auto_increment,
  created timestamp NOT NULL default CURRENT_TIMESTAMP,
  task_id int(11) NOT NULL,
  user_id int(11) NOT NULL,
  minutes int(11) NOT NULL,
  PRIMARY KEY  (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ; ");


		} catch (Exception $e) {
			exitMessage("<b>Error:</b> ". htmlarray($e->getMessage()));
		}
	}
	elseif($dbtype == 'postgres')
	{
		try
		{

			$db->ex(
				"CREATE TABLE {$db->prefix}lists (
    id integer NOT NULL,
    uuid character varying(36) NOT NULL,
    ow integer DEFAULT 0 NOT NULL,
    name character varying(50) NOT NULL,
    d_created integer DEFAULT 0 NOT NULL,
    d_edited integer DEFAULT 0 NOT NULL,
    sorting integer DEFAULT 0 NOT NULL,
    published integer DEFAULT 0 NOT NULL,
    taskview integer DEFAULT 0 NOT NULL
);
CREATE SEQUENCE {$db->prefix}lists_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;
ALTER SEQUENCE {$db->prefix}lists_id_seq OWNED BY {$db->prefix}lists.id;
ALTER TABLE {$db->prefix}lists ALTER COLUMN id SET DEFAULT nextval('{$db->prefix}lists_id_seq'::regclass);
ALTER TABLE ONLY {$db->prefix}lists
    ADD CONSTRAINT {$db->prefix}lists_pkey PRIMARY KEY (id);
ALTER TABLE ONLY {$db->prefix}lists
    ADD CONSTRAINT {$db->prefix}lists_uuid_key UNIQUE (uuid);");


			$db->ex(
				"CREATE TABLE {$db->prefix}todolist (
    id integer NOT NULL,
    uuid character varying(36) NOT NULL,
    list_id integer DEFAULT 0 NOT NULL,
    d_created integer DEFAULT 0 NOT NULL,	-- time() timestamp
    d_completed integer DEFAULT 0 NOT NULL,	-- time() timestamp
    d_edited integer DEFAULT 0 NOT NULL,	-- time() timestamp
    compl integer DEFAULT 0 NOT NULL,
    title character varying(250) NOT NULL,
    note text,
    prio integer DEFAULT 0 NOT NULL,		-- priority -,0,+
    ow integer DEFAULT 0 NOT NULL,			-- order weight
    tags character varying(600),			-- for fast access to task tags
    tags_ids character varying(250),		-- no more than 22 tags (x11 chars)
    duedate date,
    duration double precision
);
CREATE SEQUENCE {$db->prefix}todolist_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;
ALTER SEQUENCE {$db->prefix}todolist_id_seq OWNED BY {$db->prefix}todolist.id;
ALTER TABLE {$db->prefix}todolist ALTER COLUMN id SET DEFAULT nextval('{$db->prefix}todolist_id_seq'::regclass);
ALTER TABLE ONLY {$db->prefix}todolist
    ADD CONSTRAINT {$db->prefix}todolist_pkey PRIMARY KEY (id);
ALTER TABLE ONLY {$db->prefix}todolist
    ADD CONSTRAINT {$db->prefix}todolist_uuid_key UNIQUE (uuid);
CREATE INDEX idx_list_id ON {$db->prefix}todolist USING btree (list_id);");


			$db->ex(
				"CREATE TABLE {$db->prefix}tags (
    id integer NOT NULL,
    name character varying(50) NOT NULL
);
CREATE SEQUENCE {$db->prefix}tags_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;
ALTER SEQUENCE {$db->prefix}tags_id_seq OWNED BY {$db->prefix}tags.id;
ALTER TABLE {$db->prefix}tags ALTER COLUMN id SET DEFAULT nextval('{$db->prefix}tags_id_seq'::regclass);
ALTER TABLE ONLY {$db->prefix}tags
    ADD CONSTRAINT {$db->prefix}tags_name_key UNIQUE (name);
ALTER TABLE ONLY {$db->prefix}tags
    ADD CONSTRAINT {$db->prefix}tags_pkey PRIMARY KEY (id);");


			$db->ex(
				"CREATE TABLE {$db->prefix}tag2task (
    id integer NOT NULL,
    tag_id integer NOT NULL,
    task_id integer NOT NULL,
    list_id integer NOT NULL			-- for tagcloud
);
CREATE SEQUENCE {$db->prefix}tag2task_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MAXVALUE
    NO MINVALUE
    CACHE 1;
ALTER SEQUENCE {$db->prefix}tag2task_id_seq OWNED BY {$db->prefix}tag2task.id;
ALTER TABLE {$db->prefix}tag2task ALTER COLUMN id SET DEFAULT nextval('{$db->prefix}tag2task_id_seq'::regclass);
ALTER TABLE ONLY {$db->prefix}tag2task
    ADD CONSTRAINT {$db->prefix}tag2task_pkey PRIMARY KEY (id);
CREATE INDEX {$db->prefix}idx_tag_id ON {$db->prefix}tag2task USING btree (tag_id);
CREATE INDEX {$db->prefix}idx_task_id ON {$db->prefix}tag2task USING btree (task_id);
CREATE INDEX {$db->prefix}tag2task_idx_list_id ON {$db->prefix}tag2task USING btree (list_id);");

			$db->ex(
				"CREATE TABLE {$db->prefix}users (
					id integer NOT NULL,
					uuid character varying(36),
					username character varying(50),
					\"password\" character varying(32),
					email character varying(100),
					d_created integer,
					\"role\" integer
				);
				CREATE SEQUENCE {$db->prefix}users_id_seq
					INCREMENT BY 1
					NO MAXVALUE
					NO MINVALUE
					CACHE 1;
				ALTER SEQUENCE {$db->prefix}users_id_seq OWNED BY {$db->prefix}users.id;
				SELECT pg_catalog.setval('{$db->prefix}users_id_seq', 1, true);
				ALTER TABLE {$db->prefix}users ALTER COLUMN id SET DEFAULT nextval('{$db->prefix}users_id_seq'::regclass);
				ALTER TABLE ONLY {$db->prefix}users
    				ADD CONSTRAINT {$db->prefix}users_pkey PRIMARY KEY (id);
				");

			$db->ex(
				"CREATE TABLE {$db->prefix}notifications (
					id integer NOT NULL,
					user_id integer,
					creator_user_id integer,
					text character varying,
					created timestamp with time zone,
					shown integer
				);
				CREATE SEQUENCE {$db->prefix}notifications_id_seq
					START WITH 1
					INCREMENT BY 1
					NO MAXVALUE
					NO MINVALUE
					CACHE 1;
				ALTER SEQUENCE {$db->prefix}notifications_id_seq OWNED BY {$db->prefix}notifications.id;
				ALTER TABLE {$db->prefix}notifications ALTER COLUMN id SET DEFAULT nextval('{$db->prefix}notifications_id_seq'::regclass);
				ALTER TABLE ONLY {$db->prefix}notifications
    				ADD CONSTRAINT {$db->prefix}notifications_pkey PRIMARY KEY (id);

				");


			$db->ex(
				"CREATE TABLE {$db->prefix}notification_listeners (
					id integer NOT NULL,
					user_id integer,
					\"type\" character varying,
					value integer
				);
				COMMENT ON COLUMN {$db->prefix}notification_listeners.\"type\" IS '''task'',''list'',''global''';
				CREATE SEQUENCE {$db->prefix}notification_listeners_id_seq
					START WITH 1
					INCREMENT BY 1
					NO MAXVALUE
					NO MINVALUE
					CACHE 1;
				ALTER SEQUENCE {$db->prefix}notification_listeners_id_seq OWNED BY {$db->prefix}notification_listeners.id;
				ALTER TABLE {$db->prefix}notification_listeners ALTER COLUMN id SET DEFAULT nextval('{$db->prefix}notification_listeners_id_seq'::regclass);
				ALTER TABLE ONLY {$db->prefix}notification_listeners
    				ADD CONSTRAINT {$db->prefix}notification_listeners_pkey PRIMARY KEY (id);
				");


			$db->ex(
				'CREATE TABLE '.$db->prefix.'comments (
					id integer NOT NULL,
					task_id integer,
					user_id integer,
					created timestamp with time zone DEFAULT now(),
					"comment" character varying
				);'."
				CREATE SEQUENCE {$db->prefix}comments_id_seq
					INCREMENT BY 1
					NO MAXVALUE
					NO MINVALUE
					CACHE 1;
				ALTER SEQUENCE {$db->prefix}comments_id_seq OWNED BY {$db->prefix}comments.id;
				ALTER TABLE {$db->prefix}comments ALTER COLUMN id SET DEFAULT nextval('{$db->prefix}comments_id_seq'::regclass);
				ALTER TABLE ONLY {$db->prefix}comments
    				ADD CONSTRAINT {$db->prefix}comments_pkey PRIMARY KEY (id);
				");


			$db->ex(
				"CREATE TABLE {$db->prefix}time_tracker (
					id integer NOT NULL,
					created timestamp with time zone,
					task_id integer,
					user_id integer,
					minutes integer
				);
				CREATE SEQUENCE {$db->prefix}time_tracker_id_seq
					INCREMENT BY 1
					NO MAXVALUE
					NO MINVALUE
					CACHE 1;
				ALTER SEQUENCE {$db->prefix}time_tracker_id_seq OWNED BY {$db->prefix}time_tracker.id;
				ALTER TABLE {$db->prefix}time_tracker ALTER COLUMN id SET DEFAULT nextval('{$db->prefix}time_tracker_id_seq'::regclass);
				ALTER TABLE ONLY {$db->prefix}time_tracker
    				ADD CONSTRAINT {$db->prefix}time_tracker_pkey PRIMARY KEY (id);
				");

			// Using || to concatenate in YTT is not recommeneded because there are
			// database drivers for YTT that do not support the syntax, however
			// they do support CONCAT(item1, item2) which we can replicate in
			// PostgreSQL. PostgreSQL requires the function to be defined for each
			// different argument variation the function can handle.
			$db->ex('CREATE OR REPLACE FUNCTION "concat"(anynonarray, anynonarray) RETURNS text AS \'SELECT CAST($1 AS text) || CAST($2 AS text);\' LANGUAGE \'sql\'');
			$db->ex('CREATE OR REPLACE FUNCTION "concat"(text, anynonarray) RETURNS text AS \'SELECT $1 || CAST($2 AS text);\' LANGUAGE \'sql\'');
			$db->ex('CREATE OR REPLACE FUNCTION "concat"(anynonarray, text) RETURNS text AS \'SELECT CAST($1 AS text) || $2;\' LANGUAGE \'sql\'');
			$db->ex('CREATE OR REPLACE FUNCTION "concat"(text, text) RETURNS text AS \'SELECT $1 || $2;\' LANGUAGE \'sql\'');


		} catch (Exception $e) {
			exitMessage("<b>Error:</b> ". htmlarray($e->getMessage()));
		}
	}
	else #sqlite
	{
		try
		{

			$db->ex(
				"CREATE TABLE {$db->prefix}lists (
 id INTEGER PRIMARY KEY,
 uuid CHAR(36) NOT NULL,
 ow INTEGER NOT NULL default 0,
 name VARCHAR(50) NOT NULL,
 d_created INTEGER UNSIGNED NOT NULL default 0,
 d_edited INTEGER UNSIGNED NOT NULL default 0,
 sorting TINYINT UNSIGNED NOT NULL default 0,
 published TINYINT UNSIGNED NOT NULL default 0,
 taskview INTEGER UNSIGNED NOT NULL default 0
) ");

			$db->ex("CREATE UNIQUE INDEX lists_uuid ON {$db->prefix}lists (uuid)");

			$db->ex(
				"CREATE TABLE {$db->prefix}todolist (
 id INTEGER PRIMARY KEY,
 uuid CHAR(36) NOT NULL,
 list_id INTEGER UNSIGNED NOT NULL default 0,
 d_created INTEGER UNSIGNED NOT NULL default 0,
 d_completed INTEGER UNSIGNED NOT NULL default 0,
 d_edited INTEGER UNSIGNED NOT NULL default 0,
 compl TINYINT UNSIGNED NOT NULL default 0,
 title VARCHAR(250) NOT NULL,
 note TEXT,
 prio TINYINT NOT NULL default 0,
 ow INTEGER NOT NULL default 0,
 tags VARCHAR(600) NOT NULL default '',
 tags_ids VARCHAR(250) NOT NULL default '',
 duedate DATE default NULL,
 duration DOUBLE
) ");
			$db->ex("CREATE INDEX todo_list_id ON {$db->prefix}todolist (list_id)");
			$db->ex("CREATE UNIQUE INDEX todo_uuid ON {$db->prefix}todolist (uuid)");


			$db->ex(
				"CREATE TABLE {$db->prefix}tags (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 name VARCHAR(50) NOT NULL COLLATE NOCASE
) ");
			$db->ex("CREATE UNIQUE INDEX tags_name ON {$db->prefix}tags (name COLLATE NOCASE)");


			$db->ex(
				"CREATE TABLE {$db->prefix}tag2task (
 tag_id INTEGER NOT NULL,
 task_id INTEGER NOT NULL,
 list_id INTEGER NOT NULL
) ");
			$db->ex("CREATE INDEX tag2task_tag_id ON {$db->prefix}tag2task (tag_id)");
			$db->ex("CREATE INDEX tag2task_task_id ON {$db->prefix}tag2task (task_id)");
			$db->ex("CREATE INDEX tag2task_list_id ON {$db->prefix}tag2task (list_id)");	/* for tagcloud */

			$db->ex('CREATE TABLE '.$db->prefix.'users ("id" INTEGER PRIMARY KEY  NOT NULL , "uuid" VARCHAR, "username" VARCHAR, "password" VARCHAR, "email" VARCHAR, "d_created" INTEGER, "role" INTEGER)');

			$db->ex('CREATE TABLE "'.$db->prefix.'notifications" ("id" INTEGER PRIMARY KEY  AUTOINCREMENT  NOT NULL , "user_id" INTEGER, "text" VARCHAR, "created" DATETIME DEFAULT CURRENT_TIMESTAMP, "shown" INTEGER)');
			$db->ex('CREATE  TABLE "'.$db->prefix.'notification_listeners" ("id" INTEGER PRIMARY KEY  AUTOINCREMENT  NOT NULL , "user_id" INTEGER, "type" VARCHAR, "value" INTEGER)');
			$db->ex('CREATE TABLE "'.$db->prefix.'comments" ("id" INTEGER PRIMARY KEY  AUTOINCREMENT  NOT NULL , "task_id" INTEGER, "user_id" INTEGER, "created" DATETIME DEFAULT CURRENT_TIMESTAMP, "comment" TEXT)');
			$db->ex('CREATE TABLE "'.$db->prefix.'time_tracker" ("id" INTEGER PRIMARY KEY  AUTOINCREMENT  NOT NULL , "created" DATETIME, "task_id" INTEGER, "user_id" INTEGER, "minutes" INTEGER)');

		} catch (Exception $e) {
			exitMessage("<b>Error:</b> ". htmlarray($e->getMessage()));
		}
	}

	# create default list	
	$db->ex("INSERT INTO {$db->prefix}lists (uuid,name,d_created) VALUES (?,?,?)", array(generateUUID(), 'Todo', time()));

	// create default user - for multi user support
	$uuid = generateUUID();
	$db->ex("INSERT INTO {$db->prefix}users (id, uuid, username, password, email, d_created, role) VALUES (1, '".$uuid."', 'admin', '".hashPassword('admin',$uuid)."', 'mail@example.com', ".time().", '1')");

}
elseif($ver == YTT_VERSION)
{
	exitMessage("Installed version does not require database update.");
}
else
{
	if(!in_array($ver, array('mtt1.1','mtt1.2','mtt1.3.0','mtt1.3.1','mtt1.4'))) {
		exitMessage("Can not update. Unsupported database version ($ver).");
	}
	if(!isset($_POST['update'])) {
		exitMessage('Update database v'.$ver.'
		<form name="frm"" method="post"><input type="hidden" name="update" value="1"><input type="hidden" name="tz" value="-1"><input type="submit" value=" Update "></form>
		<script type="text/javascript">var tz = -1 * (new Date()).getTimezoneOffset(); document.frm.tz.value = tz;</script>
		');
	}

	# update process
	if($ver == 'mtt1.4')
	{
		update_14_15($db, $dbtype);
	}
	if($ver == 'mtt1.3.1')
	{
		update_131_14($db, $dbtype);
	}
	if($ver == 'mtt1.3.0')
	{
		update_130_131($db, $dbtype);
		update_131_14($db, $dbtype);
	}
	if($ver == 'mtt1.2')
	{
		update_12_13($db, $dbtype);
		update_130_131($db, $dbtype);
		update_131_14($db, $dbtype);
	}
	elseif($ver == 'mtt1.1')
	{
		update_11_12($db, $dbtype);
		update_12_13($db, $dbtype);
		update_130_131($db, $dbtype);
		update_131_14($db, $dbtype);
	}
}
echo "Done<br/><br/><b>Attention!</b> Delete this file for security reasons.";
echo '<br/><br/><a href="index.php">Proceed to a shiny new yourTinyTodo</a>';
echo '<br><br>Read the README file for default username (multi user support)!';
printFooter();


function get_ver($db, $dbtype)
{
	if(!$db || $dbtype == '') return '';
	if(!$db->table_exists($db->prefix.'todolist')) return '';
	$v = 'mtt1.0';
	if(!$db->table_exists($db->prefix.'tags')) return $v;
	$v = 'mtt1.1';
	if($dbtype == 'mysql') {
		if(!has_field_mysql($db, $db->prefix.'todolist', 'duedate')) return $v;
	} elseif($dbtype == 'postgres') {
		if(!has_field_postgres($db, $db->prefix.'todolist', 'duedate')) return $v;
	} else {
		if(!has_field_sqlite($db, $db->prefix.'todolist', 'duedate')) return $v;
	}
	$v = 'mtt1.2';
	if(!$db->table_exists($db->prefix.'lists')) return $v;
	$v = 'mtt1.3.0';
	if($dbtype == 'mysql') {
		if(!has_field_mysql($db, $db->prefix.'todolist', 'd_completed')) return $v;
	} elseif($dbtype == 'postgres') {
		if(!has_field_postgres($db, $db->prefix.'todolist', 'd_completed')) return $v;
	} else {
		if(!has_field_sqlite($db, $db->prefix.'todolist', 'd_completed')) return $v;
	}
	$v = 'mtt1.3.1';
	if($dbtype == 'mysql') {
		if(!has_field_mysql($db, $db->prefix.'todolist', 'd_edited')) return $v;
	} elseif($dbtype == 'postgres') {
		if(!has_field_postgres($db, $db->prefix.'todolist', 'd_edited')) return $v;
	} else {
		if(!has_field_sqlite($db, $db->prefix.'todolist', 'd_edited')) return $v;
	}
	$v = 'mtt1.4';
	if(!$db->table_exists($db->prefix.'users')) return $v;

	$v = 'ytt1.0a';
	return $v;
}

function exitMessage($s)
{
	echo $s;
	printFooter();
	exit;
}

function printFooter()
{
	echo "</body></html>";
}


function has_field_sqlite($db, $table, $field)
{
	$q = $db->dq("PRAGMA table_info(". $db->quote($table). ")");
	while($r = $q->fetch_row()) {
		if($r[1] == $field) return true;
	}
	return false;
}

function has_field_mysql($db, $table, $field)
{
	$q = $db->dq("DESCRIBE $table");
	while($r = $q->fetch_row()) {
		if($r[0] == $field) return true;
	}
	return false;
}

function has_field_postgres($db, $table, $field)
{
	$table = addslashes($table);
	$q = $db->dq("select * from INFORMATION_SCHEMA.COLUMNS where column_name='$field' AND table_name = '$table';");
	if($q->rows() > 0) return false;
	else return true;
}

function testConnect(&$error)
{
	try
	{
		require_once(YTTPATH . 'core/Database.class.php');
		require_once(YTTPATH . 'core/interfaces/databaseresult.interface.php');
		if(Config::get('db') == 'mysql')
		{
			require_once(YTTPATH . 'core/db/class.db.mysql.php');
			$db = new Database_Mysql;
			$db->connect(Config::get('mysql.host'), Config::get('mysql.user'), Config::get('mysql.password'), Config::get('mysql.db'));

		}
		else if(Config::get('db') == 'postgres')
		{
			require_once(YTTPATH . 'core/db/class.db.postgres.php');
			$db = new Database_Postgres;
			$db->connect(Config::get('postgres.host'), Config::get('postgres.user'), Config::get('postgres.password'), Config::get('postgres.db'));
		}
		else
		{
			if(false === $f = fopen(YTTPATH. 'db/todolist.db', 'a+')) throw new Exception("database file is not readable/writable");
			else fclose($f);

			if(!is_writable(YTTPATH. 'db/')) throw new Exception("database directory ('db') is not writable");

			require_once(YTTPATH . 'core/db/class.db.sqlite3.php');
			$db = new Database_Sqlite3;
			$db->connect(YTTPATH. 'db/todolist.db');
		}
	} catch(Exception $e) {
		$error = $e->getMessage();
		return 0;
	}
	return 1;
}

function myExceptionHandler($e)
{
	echo '<br/><b>Fatal Error:</b> \''. $e->getMessage() .'\' in <i>'. $e->getFile() .':'. $e->getLine() . '</i>'.
		"\n<pre>". $e->getTraceAsString() . "</pre>\n";
	exit;
}


### 1.1-1.2 ##########
function update_11_12($db, $dbtype)
{
	if($dbtype == 'mysql') $db->ex("ALTER TABLE todolist ADD duedate DATE default NULL");
	else $db->ex("ALTER TABLE todolist ADD duedate DATE default NULL");

	# Fixing broken tags
	$db->ex("BEGIN");
	$db->ex("DELETE FROM tags");
	$db->ex("DELETE FROM tag2task");
	$q = $db->dq("SELECT id,tags FROM todolist");
	while($r = $q->fetch_assoc())
	{
		if($r['tags'] == '') continue;
		$tag_ids = prepare_tags($r['tags']);
		if($tag_ids) update_task_tags($r['id'], $tag_ids);
	}
	$db->ex("COMMIT");
}

function prepare_tags(&$tags_str)
{
	$tag_ids = array();
	$tag_names = array();
	$tags = explode(',', $tags_str);
	foreach($tags as $v)
	{
		# remove duplicate tags?
		$tag = str_replace(array('"',"'"),array('',''),trim($v));
		if($tag == '') continue;
		list($tag_id,$tag_name) = get_or_create_tag($tag);
		if($tag_id && !in_array($tag_id, $tag_ids)) {
			$tag_ids[] = $tag_id;
			$tag_names[] = $tag_name;
		}
	}
	$tags_str = implode(',', $tag_names);
	return $tag_ids;
}

function get_or_create_tag($name)
{
	global $db;
	$tag = $db->sq("SELECT id,name FROM tags WHERE name=?", $name);
	if($tag) return $tag;

	# need to create tag
	$db->ex("INSERT INTO tags (name) VALUES (?)", $name);
	return array($db->last_insert_id($db->prefix.'tags'), $name);
}

function update_task_tags($id, $tag_ids)
{
	global $db;
	foreach($tag_ids as $v) {
		$db->ex("INSERT INTO tag2task (task_id,tag_id) VALUES ($id,$v)");
	}
	$db->ex("UPDATE tags SET tags_count=tags_count+1 WHERE id IN (". implode(',', $tag_ids). ")");
}

### end 1.1-1.2 #####

### 1.2-1.3 ##########
function update_12_13($db, $dbtype)
{
	# update config
	Config::save();

	# and then db
	$db->ex("BEGIN");
	if($dbtype=='mysql')
	{
		$db->ex(
			"CREATE TABLE lists (
 id INT UNSIGNED NOT NULL auto_increment,
 name VARCHAR(50) NOT NULL default '',
 PRIMARY KEY(id)
) CHARSET=utf8 ");
		$db->ex("ALTER TABLE todolist ADD list_id INT UNSIGNED NOT NULL default 0");
		$db->ex("ALTER TABLE tags ADD list_id INT UNSIGNED NOT NULL default 0");

		$db->ex("ALTER TABLE todolist ADD KEY(list_id)");
		$db->ex("DROP INDEX name ON tags");
		$db->ex("ALTER TABLE tags ADD UNIQUE KEY listid_name (list_id,name)");
	}
	else
	{
		$db->ex(
			"CREATE TABLE lists (
 id INTEGER PRIMARY KEY,
 name VARCHAR(50) NOT NULL
) ");
		$db->ex("ALTER TABLE todolist ADD list_id INTEGER UNSIGNED NOT NULL default 0");
		$db->ex("CREATE INDEX todolist_list_id ON todolist (list_id)");

		$db->ex(
			"CREATE TEMPORARY TABLE tags_backup (
 id INTEGER,
 name VARCHAR(50) NOT NULL,
 tags_count INT default 0
) ");
		$db->ex("INSERT INTO tags_backup SELECT id,name,tags_count FROM tags");
		$db->ex("DROP TABLE tags");
		$db->ex(
			"CREATE TABLE tags (
 id INTEGER PRIMARY KEY,
 name VARCHAR(50) NOT NULL,
 tags_count INT default 0,
 list_id INTEGER UNSIGNED NOT NULL default 0
) ");
		$db->ex("INSERT INTO tags (id,name,tags_count) SELECT id,name,tags_count FROM tags_backup");
		$db->ex("CREATE UNIQUE INDEX tags_listid_name ON tags (list_id,name COLLATE NOCASE) ");
		$db->ex("DROP TABLE tags_backup");
	}
	$db->ex("COMMIT");

	$db->ex("INSERT INTO lists (name,d_created) VALUES (?,?)", array('Todo', time()));
	$db->ex("UPDATE todolist SET list_id=1");

}

### end 1.2-1.3 #####


### 1.3.0 to 1.3.1 ##########
function update_130_131($db, $dbtype)
{
	$tz = null;
	if(isset($_POST['tz'])) {
		$tz = (int)$_POST['tz'];
		if($tz<-720 || $tz>720 || $tz%30!=0) $tz = null;
		else $tz = $tz*60;
	}
	if(is_null($tz)) $tz = (int)date('Z');

#	if($dbtype=='sqlite') {
#		$temp_store_pragma = $db->sq("PRAGMA temp_store");
#		$db->ex("PRAGMA temp_store = MEMORY");
#	}

	$db->ex("BEGIN");
	if($dbtype=='mysql')
	{
		$db->ex("ALTER TABLE lists ADD ow INT NOT NULL default 0");
		$db->ex("ALTER TABLE lists ADD d_created INT UNSIGNED NOT NULL default 0");
		$db->ex("ALTER TABLE lists ADD sorting TINYINT UNSIGNED NOT NULL default 0");
		$db->ex("ALTER TABLE lists ADD published TINYINT UNSIGNED NOT NULL default 0");
		$db->ex("ALTER TABLE lists ADD taskview INT UNSIGNED NOT NULL default 0");

		$db->ex("ALTER TABLE todolist ADD d_created INT UNSIGNED NOT NULL default 0");
		$db->ex("ALTER TABLE todolist ADD d_completed INT UNSIGNED NOT NULL default 0");

		# convert task date...
		$db_session_timezone = $db->sq("SELECT @@session.time_zone");;
		$db->ex("SET time_zone='+0:00'");
		$tz = -1*$tz;
		$db->ex("UPDATE todolist SET d_created = UNIX_TIMESTAMP(d) + TIME_TO_SEC(TIMEDIFF(NOW(),UTC_TIMESTAMP())) ".($tz<0?'-':'+').abs($tz));
		$db->ex("SET time_zone=?", array($db_session_timezone));

		$db->ex("ALTER TABLE todolist DROP d");
	}
	else
	{
		$db->ex("ALTER TABLE lists ADD ow INTEGER NOT NULL default 0");
		$db->ex("ALTER TABLE lists ADD d_created INTEGER UNSIGNED NOT NULL default 0");
		$db->ex("ALTER TABLE lists ADD sorting TINYINT UNSIGNED NOT NULL default 0");
		$db->ex("ALTER TABLE lists ADD published TINYINT UNSIGNED NOT NULL default 0");
		$db->ex("ALTER TABLE lists ADD taskview INTEGER UNSIGNED NOT NULL default 0");

		$db->ex("ALTER TABLE todolist ADD d_created INTEGER UNSIGNED NOT NULL default 0");
		$db->ex("ALTER TABLE todolist ADD d_completed INTEGER UNSIGNED NOT NULL default 0");

		# convert task date to timestamp
		$tz = -1*$tz;
		$db->ex("UPDATE todolist SET d_created=strftime('%s',d) ".($tz<0?'-':'+').abs($tz));

		# drop unnecessary field 'd'
		$db->ex(
			"CREATE TEMPORARY TABLE todolist_backup (
 id INTEGER,
 list_id INTEGER UNSIGNED NOT NULL default 0,
 d_created INTEGER UNSIGNED NOT NULL default 0,
 d_completed INTEGER UNSIGNED NOT NULL default 0,
 compl TINYINT UNSIGNED NOT NULL default 0,
 title VARCHAR(250) NOT NULL,
 note TEXT,
 prio TINYINT NOT NULL default 0,
 ow INT NOT NULL default 0,
 tags VARCHAR(250) NOT NULL default '',
 duedate DATE default NULL
) ");
		$db->ex("INSERT INTO todolist_backup (id,list_id,d_created,d_completed,compl,title,note,prio,ow,tags,duedate) ".
			" SELECT id,list_id,d_created,d_completed,compl,title,note,prio,ow,tags,duedate FROM todolist");
		$db->ex("DROP TABLE todolist");

		$db->ex(
			"CREATE TABLE todolist (
 id INTEGER PRIMARY KEY,
 list_id INTEGER UNSIGNED NOT NULL default 0,
 d_created INTEGER UNSIGNED NOT NULL default 0,
 d_completed INTEGER UNSIGNED NOT NULL default 0,
 compl TINYINT UNSIGNED NOT NULL default 0,
 title VARCHAR(250) NOT NULL,
 note TEXT,
 prio TINYINT NOT NULL default 0,
 ow INT NOT NULL default 0,
 tags VARCHAR(250) NOT NULL default '',
 duedate DATE default NULL
) ");
		$db->ex("CREATE INDEX list_id ON todolist (list_id)");

		$db->ex("INSERT INTO todolist (id,list_id,d_created,d_completed,compl,title,note,prio,ow,tags,duedate) ".
			" SELECT id,list_id,d_created,d_completed,compl,title,note,prio,ow,tags,duedate FROM todolist_backup");
		$db->ex("DROP TABLE todolist_backup");
	}

	$sort = 0;
	if(isset($_COOKIE['sort']) && $_COOKIE['sort'] != ''){
		$sort = (int)$_COOKIE['sort'];
		if($sort < 0 || $sort > 2) $sort = 0;
	}

	if(Config::get('password') != '' && Config::get('allowread')) $published = 1;
	else $published = 0;

	$db->ex("UPDATE lists SET d_created=?, sorting=?, published=?", array(time(), $sort, $published));
	$db->ex("UPDATE todolist SET d_completed=d_created WHERE compl=1");

	$db->ex("COMMIT");

#	if($dbtype=='sqlite') {
#		$db->ex("PRAGMA temp_store = $temp_store_pragma");
#	}
}

### end of 1.3.0 to 1.3.1 ##########

### update v1.3.1 to v1.4 ##########
function update_131_14($db, $dbtype)
{
	$db->ex("BEGIN");
	if($dbtype=='mysql')
	{
		$db->ex("DROP TABLE {$db->prefix}tags");
		$db->ex(
			"CREATE TABLE {$db->prefix}tags (
			 id INT UNSIGNED NOT NULL auto_increment,
			 name VARCHAR(50) NOT NULL,
			 PRIMARY KEY(id),
			 UNIQUE KEY name (name)
			) CHARSET=utf8 ");

		$db->ex("ALTER TABLE {$db->prefix}todolist CHANGE tags tags VARCHAR(600) NOT NULL default ''");
		$db->ex("ALTER TABLE {$db->prefix}todolist ADD tags_ids VARCHAR(250) NOT NULL default ''");
		$db->ex("ALTER TABLE {$db->prefix}todolist ADD uuid CHAR(36) NOT NULL default ''");
		$db->ex("ALTER TABLE {$db->prefix}todolist ADD d_edited INT UNSIGNED NOT NULL default 0");

		$db->ex("ALTER TABLE {$db->prefix}tag2task ADD list_id INT UNSIGNED NOT NULL");
		$db->ex("ALTER TABLE {$db->prefix}tag2task ADD KEY(list_id)");

		$db->ex("ALTER TABLE {$db->prefix}lists ADD uuid CHAR(36) NOT NULL default ''");
		$db->ex("ALTER TABLE {$db->prefix}lists ADD d_edited INT UNSIGNED NOT NULL default 0");

	}
	else #sqlite
	{
		# changes in tags table: fully new
		$db->ex("DROP TABLE {$db->prefix}tags"); //index will be deleted too
		$db->ex(
			"CREATE TABLE {$db->prefix}tags (
				 id INTEGER PRIMARY KEY AUTOINCREMENT,
				 name VARCHAR(50) NOT NULL COLLATE NOCASE
				) ");
		$db->ex("CREATE UNIQUE INDEX tags_name ON {$db->prefix}tags (name COLLATE NOCASE)");

		# changes in todolist table: uuid, d_edited, tags, tags_ids
		$db->ex(
			"CREATE TABLE todolist_new (
				 id INTEGER PRIMARY KEY,
				 uuid CHAR(36) NOT NULL default '',
				 list_id INTEGER UNSIGNED NOT NULL default 0,
				 d_created INTEGER UNSIGNED NOT NULL default 0,
				 d_completed INTEGER UNSIGNED NOT NULL default 0,
				 d_edited INTEGER UNSIGNED NOT NULL default 0,
				 compl TINYINT UNSIGNED NOT NULL default 0,
				 title VARCHAR(250) NOT NULL,
				 note TEXT,
				 prio TINYINT NOT NULL default 0,
				 ow INTEGER NOT NULL default 0,
				 tags VARCHAR(600) NOT NULL default '',
				 tags_ids VARCHAR(250) NOT NULL default '',
				 duedate DATE default NULL
				) ");
		$db->ex("INSERT INTO todolist_new (id,list_id,d_created,d_completed,compl,title,note,prio,ow,tags,duedate)".
			" SELECT id,list_id,d_created,d_completed,compl,title,note,prio,ow,tags,duedate FROM {$db->prefix}todolist");
		$db->ex("DROP TABLE {$db->prefix}todolist");
		$db->ex("ALTER TABLE todolist_new RENAME TO {$db->prefix}todolist");
		$db->ex("CREATE INDEX todo_list_id ON {$db->prefix}todolist (list_id)"); #1st index of 2

		# changes in tag2task table: new column and index, new names of indexes
		$db->ex("ALTER TABLE {$db->prefix}tag2task ADD list_id INTEGER NOT NULL default 0");
		$db->ex("DROP INDEX tag_id");
		$db->ex("DROP INDEX task_id ");
		$db->ex("CREATE INDEX tag2task_tag_id ON {$db->prefix}tag2task (tag_id)");
		$db->ex("CREATE INDEX tag2task_task_id ON {$db->prefix}tag2task (task_id)");
		$db->ex("CREATE INDEX tag2task_list_id ON {$db->prefix}tag2task (list_id)");

		# changes in lists table: uuid, d_edited
		$db->ex("ALTER TABLE {$db->prefix}lists ADD uuid CHAR(36) NOT NULL default ''");
		$db->ex("ALTER TABLE {$db->prefix}lists ADD d_edited INTEGER UNSIGNED NOT NULL default 0");

	}

	# recreate tags
	$db->ex("DELETE FROM {$db->prefix}tag2task");

	$q = $db->dq("SELECT id,list_id,tags FROM {$db->prefix}todolist WHERE tags != ''");
	$ar = array();
	while($r = $q->fetch_assoc()) $ar[] = $r;
	foreach($ar as $r)
	{
		$aTags = v14_prepareTags($r['tags']);
		if($aTags)
		{
			v14_addTaskTags($r['id'], $aTags['ids'], $r['list_id']);
			$db->ex("UPDATE {$db->prefix}todolist SET tags=?,tags_ids=? WHERE id=".$r['id'],
				array(implode(',',$aTags['tags']), implode(',',$aTags['ids'])) );
		}
	}

	# fix bug with empty lists.d_created
	$db->ex("UPDATE {$db->prefix}lists SET d_created=?", time());

	# init d_edited
	$db->ex("UPDATE {$db->prefix}todolist SET d_edited=d_created");
	$db->ex("UPDATE {$db->prefix}todolist SET d_edited=d_completed WHERE d_completed > d_edited");
	$db->ex("UPDATE {$db->prefix}lists SET d_edited=d_created");

	# add UUID
	$q = $db->dq("SELECT id FROM {$db->prefix}todolist");
	$ar = array();
	while($r = $q->fetch_assoc()) $ar[] = $r;
	foreach($ar as $r) {
		$db->ex("UPDATE {$db->prefix}todolist SET uuid=? WHERE id=".$r['id'], array(generateUUID()) );
	}

	$q = $db->dq("SELECT id FROM {$db->prefix}lists");
	$ar = array();
	while($r = $q->fetch_assoc()) $ar[] = $r;
	foreach($ar as $r) {
		$db->ex("UPDATE {$db->prefix}lists SET uuid=? WHERE id=".$r['id'], array(generateUUID()) );
	}

	# create unique indexes for UUID
	if($dbtype=='mysql')
	{
		$db->ex("ALTER TABLE {$db->prefix}lists ADD UNIQUE KEY (uuid)");
		$db->ex("ALTER TABLE {$db->prefix}todolist ADD UNIQUE KEY (uuid)");
	}
	else
	{
		$db->ex("CREATE UNIQUE INDEX lists_uuid ON {$db->prefix}lists (uuid)");
		$db->ex("CREATE UNIQUE INDEX todo_uuid ON {$db->prefix}todolist (uuid)");
	}

	$db->ex("COMMIT");
}

function v14_prepareTags($tagsStr)
{
	$tags = explode(',', $tagsStr);
	if(!$tags) return 0;

	$aTags = array('tags'=>array(), 'ids'=>array());
	foreach($tags as $tag)
	{
		$tag = str_replace(array('"',"'",'<','>','&','/','\\','^'),'',trim($tag));
		if($tag == '') continue;

		$aTag = v14_getOrCreateTag($tag);
		if($aTag && !in_array($aTag['id'], $aTags['ids'])) {
			$aTags['tags'][] = $aTag['name'];
			$aTags['ids'][] = $aTag['id'];
		}
	}
	return $aTags;
}

function v14_getOrCreateTag($name)
{
	global $db;
	$tagId = $db->sq("SELECT id FROM {$db->prefix}tags WHERE name=?", array($name));
	if($tagId) return array('id'=>$tagId, 'name'=>$name);

	$db->ex("INSERT INTO {$db->prefix}tags (name) VALUES (?)", array($name));
	return array('id'=>$db->last_insert_id($db->prefix.'tags'), 'name'=>$name);
}

function v14_addTaskTags($taskId, $tagIds, $listId)
{
	global $db;
	if(!$tagIds) return;
	foreach($tagIds as $tagId)
	{
		$db->ex("INSERT INTO {$db->prefix}tag2task (task_id,tag_id,list_id) VALUES (?,?,?)", array($taskId,$tagId,$listId));
	}
}
### end of 1.4 #####

### update v1.4 to v1.5 ##########
function update_14_15($db, $dbtype)
{
	$db->ex("BEGIN");
	if($dbtype=='mysql')
	{
		$db->ex(
			"CREATE TABLE IF NOT EXISTS {$db->prefix}users (
			  id int(10) unsigned NOT NULL auto_increment,
			  uuid varchar(36) NOT NULL,
			  username varchar(50) NOT NULL,
			  password varchar(32) NOT NULL,
			  email varchar(100) NOT NULL,
			  d_created int(10) unsigned NOT NULL,
			  role enum('1','2','3') NOT NULL default '3',
			  PRIMARY KEY  (id)
			) CHARSET=utf8 ");

		$db->ex(
			"CREATE TABLE IF NOT EXISTS {$db->prefix}notifications (
			  id int(11) NOT NULL auto_increment,
			  user_id int(11) NOT NULL,
			  creator_user_id int(11) NOT NULL,
			  text varchar(255) NOT NULL,
			  created timestamp NOT NULL default CURRENT_TIMESTAMP,
			  shown tinyint(1) NOT NULL default '0',
			  PRIMARY KEY  (id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ; ");


		$db->ex(
			"CREATE TABLE IF NOT EXISTS {$db->prefix}notification_listeners (
			  id int(11) NOT NULL auto_increment,
			  user_id int(11) NOT NULL,
			  type set('task','list','global') character set utf8 NOT NULL,
			  value int(11) default NULL,
			  PRIMARY KEY  (id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ; ");


		$db->ex(
			"CREATE TABLE IF NOT EXISTS {$db->prefix}comments (
			  id int(11) NOT NULL auto_increment,
			  task_id int(11) NOT NULL,
			  user_id int(11) NOT NULL,
			  created timestamp NOT NULL default CURRENT_TIMESTAMP,
			  comment varchar(255) NOT NULL,
			  PRIMARY KEY  (id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ; ");


		$db->ex(
			"CREATE TABLE IF NOT EXISTS {$db->prefix}time_tracker (
			  id int(11) NOT NULL auto_increment,
			  created timestamp NOT NULL default CURRENT_TIMESTAMP,
			  task_id int(11) NOT NULL,
			  user_id int(11) NOT NULL,
			  minutes int(11) NOT NULL,
			  PRIMARY KEY  (id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ; ");

	}
	else if($dbtype == 'postgres')
	{
		$db->ex(
			"CREATE TABLE {$db->prefix}users (
					id integer NOT NULL,
					uuid character varying(36),
					username character varying(50),
					\"password\" character varying(32),
					email character varying(100),
					d_created integer,
					\"role\" integer
				);
				CREATE SEQUENCE {$db->prefix}users_id_seq
					INCREMENT BY 1
					NO MAXVALUE
					NO MINVALUE
					CACHE 1;
				ALTER SEQUENCE {$db->prefix}users_id_seq OWNED BY {$db->prefix}users.id;
				SELECT pg_catalog.setval('{$db->prefix}users_id_seq', 1, true);
				ALTER TABLE {$db->prefix}users ALTER COLUMN id SET DEFAULT nextval('{$db->prefix}users_id_seq'::regclass);
				ALTER TABLE ONLY {$db->prefix}users
    				ADD CONSTRAINT {$db->prefix}users_pkey PRIMARY KEY (id);
				");

		// Using || to concatenate in YTT is not recommeneded because there are
		// database drivers for YTT that do not support the syntax, however
		// they do support CONCAT(item1, item2) which we can replicate in
		// PostgreSQL. PostgreSQL requires the function to be defined for each
		// different argument variation the function can handle.
		$db->ex('CREATE OR REPLACE FUNCTION "concat"(anynonarray, anynonarray) RETURNS text AS \'SELECT CAST($1 AS text) || CAST($2 AS text);\' LANGUAGE \'sql\'');
		$db->ex('CREATE OR REPLACE FUNCTION "concat"(text, anynonarray) RETURNS text AS \'SELECT $1 || CAST($2 AS text);\' LANGUAGE \'sql\'');
		$db->ex('CREATE OR REPLACE FUNCTION "concat"(anynonarray, text) RETURNS text AS \'SELECT CAST($1 AS text) || $2;\' LANGUAGE \'sql\'');
		$db->ex('CREATE OR REPLACE FUNCTION "concat"(text, text) RETURNS text AS \'SELECT $1 || $2;\' LANGUAGE \'sql\'');

		$db->ex(
			"CREATE TABLE IF NOT EXISTS {$db->prefix}notifications (
			  id int(11) NOT NULL auto_increment,
			  user_id int(11) NOT NULL,
			  creator_user_id int(11) NOT NULL,
			  text varchar(255) NOT NULL,
			  created timestamp NOT NULL default CURRENT_TIMESTAMP,
			  shown tinyint(1) NOT NULL default '0',
			  PRIMARY KEY  (id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ; ");


		$db->ex(
			"CREATE TABLE IF NOT EXISTS {$db->prefix}notification_listeners (
			  id int(11) NOT NULL auto_increment,
			  user_id int(11) NOT NULL,
			  type set('task','list','global') character set utf8 NOT NULL,
			  value int(11) default NULL,
			  PRIMARY KEY  (id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ; ");


		$db->ex(
			"CREATE TABLE IF NOT EXISTS {$db->prefix}comments (
			  id int(11) NOT NULL auto_increment,
			  task_id int(11) NOT NULL,
			  user_id int(11) NOT NULL,
			  created timestamp NOT NULL default CURRENT_TIMESTAMP,
			  comment varchar(255) NOT NULL,
			  PRIMARY KEY  (id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ; ");


		$db->ex(
			"CREATE TABLE IF NOT EXISTS {$db->prefix}time_tracker (
			  id int(11) NOT NULL auto_increment,
			  created timestamp NOT NULL default CURRENT_TIMESTAMP,
			  task_id int(11) NOT NULL,
			  user_id int(11) NOT NULL,
			  minutes int(11) NOT NULL,
			  PRIMARY KEY  (id)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ; ");
	}
	else #sqlite
	{
		$db->ex('CREATE TABLE '.$db->prefix.'users ("id" INTEGER PRIMARY KEY  NOT NULL , "uuid" VARCHAR, "username" VARCHAR, "password" VARCHAR, "email" VARCHAR, "d_created" INTEGER, "role" INTEGER)');
		$db->ex('CREATE TABLE "'.$db->prefix.'notifications" ("id" INTEGER PRIMARY KEY  AUTOINCREMENT  NOT NULL , "user_id" INTEGER, "creator_user_id" INTEGER, "text" VARCHAR, "created" DATETIME DEFAULT CURRENT_TIMESTAMP, "shown" INTEGER)');
		$db->ex('CREATE TABLE "'.$db->prefix.'notification_listeners" ("id" INTEGER PRIMARY KEY  AUTOINCREMENT  NOT NULL , "user_id" INTEGER, "type" VARCHAR, "value" INTEGER)');
		$db->ex('CREATE TABLE "'.$db->prefix.'comments" ("id" INTEGER PRIMARY KEY  AUTOINCREMENT  NOT NULL , "task_id" INTEGER, "user_id" INTEGER, "created" DATETIME DEFAULT CURRENT_TIMESTAMP, "comment" TEXT)');
		$db->ex('CREATE TABLE "'.$db->prefix.'time_tracker" ("id" INTEGER PRIMARY KEY  AUTOINCREMENT  NOT NULL , "created" DATETIME, "task_id" INTEGER, "user_id" INTEGER, "minutes" INTEGER)');
	}

	// create default user - for multi user support
	$uuid = generateUUID();
	$db->ex("INSERT INTO {$db->prefix}users (id, uuid, username, password, email, d_created, role) VALUES (1, '".$uuid."', 'admin', '".hashPassword('admin',$uuid)."', 'mail@example.com', ".time().", '1')");

	$db->ex("COMMIT");
}
### end of 1.5 #####