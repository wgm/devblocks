<?php
require(getcwd() . '/framework.config.php');
require(CG_PATH . '/libs/cloudglue/CloudGlue.class.php');

$um_db = CgPlatform::getDatabaseService();

$datadict = NewDataDictionary($um_db); /* @var $datadict ADODB_DataDict */ // ,'mysql' 

$tables = array();

// ***** Platform

$tables['extension'] = "
	id C(128) DEFAULT '' NOTNULL PRIMARY,
	plugin_id I DEFAULT 0 NOTNULL,
	point C(128) DEFAULT '' NOTNULL,
	pos I2 DEFAULT 0 NOTNULL,
	name C(128) DEFAULT '' NOTNULL,
	file C(128) DEFAULT '' NOTNULL,
	class C(128) DEFAULT '' NOTNULL,
	params B DEFAULT '' NOTNULL
";

$tables['plugin'] = "
	id I PRIMARY,
	enabled I1 DEFAULT 0 NOTNULL,
	name C(128) DEFAULT '' NOTNULL,
	author C(64) DEFAULT '' NOTNULL,
	dir C(128) DEFAULT '' NOTNULL
";

$tables['property_store'] = "
	extension_id C(128) DEFAULT '' NOTNULL PRIMARY,
	instance_id I DEFAULT 0 NOTNULL PRIMARY,
	property C(128) DEFAULT '' NOTNULL PRIMARY,
	value C(255) DEFAULT '' NOTNULL
";

$tables['session'] = "
	sesskey C(64) PRIMARY,
	expiry T,
	expireref C(250),
	created T NOTNULL,
	modified T NOTNULL,
	sessdata B
";

$tables['login'] = "
	id I PRIMARY,
	login C(32) NOTNULL,
	password C(32) NOTNULL,
	admin I1 DEFAULT 0 NOTNULL
";

// ***** Application

$tables['bounty'] = "
	id I4 PRIMARY,
	title C(128) NOTNULL,
	estimate F DEFAULT 0.0 NOTNULL,
	priority I1 DEFAULT 0,
	votes I4 DEFAULT 0
";

$tables['bounty_vote'] = "
	bounty_id I4 PRIMARY,
	member_id I4 PRIMARY,
	vote I1 DEFAULT 0
";

// [TODO] Bounty Content (blob body)
// [TODO] Bounty Transactions (member, # blocks, and what spent on)
// [TODO] Members

// *****

foreach($tables as $table => $flds) {
	$sql = $datadict->ChangeTableSQL($table,$flds);
	print_r($sql);
	$datadict->ExecuteSQLArray($sql,false);
	echo "<HR>";
}

$plugins = CgPlatform::readPlugins();
?>