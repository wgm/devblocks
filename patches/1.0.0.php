<?php
$db = DevblocksPlatform::getDatabaseService();
$datadict = NewDataDictionary($db); /* @var $datadict ADODB_DataDict */ // ,'mysql' 
		
// ***** Platform
$tables = array();
$prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : '';

$tables[$prefix.'event_point'] = "
	id C(128) DEFAULT '' NOTNULL PRIMARY,
	plugin_id C(128) DEFAULT 0 NOTNULL,
	name C(128) DEFAULT '' NOTNULL,
	params B
";

$tables[$prefix.'extension_point'] = "
	id C(128) DEFAULT '' NOTNULL PRIMARY,
	plugin_id C(128) DEFAULT 0 NOTNULL
";

$tables[$prefix.'extension'] = "
	id C(128) DEFAULT '' NOTNULL PRIMARY,
	plugin_id C(128) DEFAULT 0 NOTNULL,
	point C(128) DEFAULT '' NOTNULL,
	pos I2 DEFAULT 0 NOTNULL,
	name C(128) DEFAULT '' NOTNULL,
	file C(128) DEFAULT '' NOTNULL,
	class C(128) DEFAULT '' NOTNULL,
	params B
";

$tables[$prefix.'patch_history'] = "
	plugin_id C(128) DEFAULT '' NOTNULL PRIMARY,
	revision I4 DEFAULT 0 NOTNULL,
	run_date I8 DEFAULT 0 NOTNULL
";

$tables[$prefix.'plugin'] = "
	id C(128) PRIMARY,
	enabled I1 DEFAULT 0 NOTNULL,
	name C(128) DEFAULT '' NOTNULL,
	description C(255) DEFAULT '' NOTNULL,
	author C(64) DEFAULT '' NOTNULL,
	revision I4 DEFAULT 0 NOTNULL,
	dir C(128) DEFAULT '' NOTNULL
";

$tables[$prefix.'property_store'] = "
	extension_id C(128) DEFAULT '' NOTNULL PRIMARY,
	instance_id I DEFAULT 0 NOTNULL PRIMARY,
	property C(128) DEFAULT '' NOTNULL PRIMARY,
	value C(255) DEFAULT '' NOTNULL
";

$tables[$prefix.'session'] = "
	sesskey C(64) PRIMARY,
	expiry T,
	expireref C(250),
	created T NOTNULL,
	modified T NOTNULL,
	sessdata B
";
		
//		$tables[$prefix.'uri'] = "
//			uri C(32) PRIMARY,
//			plugin_id C(128) NOTNULL,
//			extension_id C(128) NOTNULL
//		";

$currentTables = $db->MetaTables('TABLE', false);

if(is_array($tables))
foreach($tables as $table => $flds) {
	if(false === array_search($table,$currentTables)) {
		$sql = $datadict->CreateTableSQL($table,$flds);
		// [TODO] Buffer up success and fail messages?  Patcher!
		if(!$datadict->ExecuteSQLArray($sql,false)) {
			echo '[' . $table . '] ' . $db->ErrorMsg();
			exit;
			return FALSE;
		}
	}
}

return TRUE;
?>