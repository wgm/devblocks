<?php
/***********************************************************************
| Cerberus Helpdesk(tm) developed by WebGroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2010, WebGroup Media LLC
|   unless specifically noted otherwise.
|
| This source code is released under the Cerberus Public License.
| The latest version of this license can be found here:
| http://www.cerberusweb.com/license.php
|
| By using this software, you acknowledge having read this license
| and agree to be bound thereby.
| ______________________________________________________________________
|	http://www.cerberusweb.com	  http://www.webgroupmedia.com/
***********************************************************************/

$db = DevblocksPlatform::getDatabaseService();
$datadict = NewDataDictionary($db); /* @var $datadict ADODB_DataDict */ // ,'mysql' 

$prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup

$tables = $datadict->MetaTables();
$tables = array_flip($tables);

// ============================================================================
// property_store updates

$columns = $datadict->MetaColumns($prefix.'property_store');
$indexes = $datadict->MetaIndexes($prefix.'property_store',false);

// Fix blob encoding
if(isset($columns['VALUE'])) {
	if(0==strcasecmp('longblob',$columns['VALUE']->type)) {
		$sql = sprintf("ALTER TABLE ${prefix}property_store CHANGE COLUMN value value TEXT");
		$db->Execute($sql);
	}
}

// Drop instance ID
if(isset($columns['INSTANCE_ID'])) {
	$sql = $datadict->DropColumnSQL($prefix.'property_store', 'instance_id');
	$datadict->ExecuteSQLArray($sql);	
}

// ============================================================================
// plugin updates

$columns = $datadict->MetaColumns($prefix.'plugin');
$indexes = $datadict->MetaIndexes($prefix.'plugin',false);

// Drop 'file'
if(isset($columns['FILE'])) {
	$sql = $datadict->DropColumnSQL($prefix.'plugin', 'file');
	$datadict->ExecuteSQLArray($sql);	
}

// Drop 'class'
if(isset($columns['CLASS'])) {
	$sql = $datadict->DropColumnSQL($prefix.'plugin', 'class');
	$datadict->ExecuteSQLArray($sql);	
}

// ============================================================================
// devblocks_setting

if(!isset($tables['devblocks_setting'])) {
    $flds = "
    	plugin_id C(255) DEFAULT '' NOTNULL PRIMARY,
		setting C(32) DEFAULT '' NOTNULL PRIMARY,
		value C(255) DEFAULT '' NOTNULL
    ";
    $sql = $datadict->CreateTableSQL('devblocks_setting', $flds);
    $datadict->ExecuteSQLArray($sql);
}

$columns = $datadict->MetaColumns('devblocks_setting');
$indexes = $datadict->MetaIndexes('devblocks_setting',false);

return TRUE;