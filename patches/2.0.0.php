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

return TRUE;