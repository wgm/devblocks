<?php
/***********************************************************************
| Cerberus Helpdesk(tm) developed by WebGroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2009, WebGroup Media LLC
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
// Drop deprecated acl.is_default

$columns = $datadict->MetaColumns($prefix.'acl');
$indexes = $datadict->MetaIndexes($prefix.'acl',false);

if(!isset($columns['IS_DEFAULT'])) {
	$sql = $datadict->DropColumnSQL($prefix.'acl','is_default');
	$datadict->ExecuteSQLArray($sql);
}

// ============================================================================
// Classloading cache from plugin manifests

if(!isset($tables[$prefix.'class_loader'])) {
	$flds ="
		class C(255) DEFAULT '' NOTNULL PRIMARY,
		plugin_id C(255) DEFAULT '' NOTNULL,
		rel_path C(255) DEFAULT '' NOTNULL
	";
	
	$sql = $datadict->CreateTableSQL($prefix.'class_loader', $flds);
	$datadict->ExecuteSQLArray($sql);
}

$columns = $datadict->MetaColumns($prefix.'class_loader');
$indexes = $datadict->MetaIndexes($prefix.'class_loader',false);

// ============================================================================
// Front controller cache from plugin manifests

if(!isset($tables[$prefix.'uri_routing'])) {
	$flds ="
		uri C(255) DEFAULT '' NOTNULL PRIMARY,
		plugin_id C(255) DEFAULT '' NOTNULL,
		controller_id C(255) DEFAULT '' NOTNULL
	";
	
	$sql = $datadict->CreateTableSQL($prefix.'uri_routing', $flds);
	$datadict->ExecuteSQLArray($sql);
}

$columns = $datadict->MetaColumns($prefix.'uri_routing');
$indexes = $datadict->MetaIndexes($prefix.'uri_routing',false);


return TRUE;