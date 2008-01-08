<?php
/***********************************************************************
| Cerberus Helpdesk(tm) developed by WebGroup Media, LLC.
|-----------------------------------------------------------------------
| All source code & content (c) Copyright 2007, WebGroup Media LLC
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
/*
 * IMPORTANT LICENSING NOTE from your friends on the Cerberus Helpdesk Team
 * 
 * Sure, it would be so easy to just cheat and edit this file to use the 
 * software without paying for it.  But we trust you anyway.  In fact, we're 
 * writing this software for you! 
 * 
 * Quality software backed by a dedicated team takes money to develop.  We 
 * don't want to be out of the office bagging groceries when you call up 
 * needing a helping hand.  We'd rather spend our free time coding your 
 * feature requests than mowing the neighbors' lawns for rent money. 
 * 
 * We've never believed in encoding our source code out of paranoia over not 
 * getting paid.  We want you to have the full source code and be able to 
 * make the tweaks your organization requires to get more done -- despite 
 * having less of everything than you might need (time, people, money, 
 * energy).  We shouldn't be your bottleneck.
 * 
 * We've been building our expertise with this project since January 2002.  We 
 * promise spending a couple bucks [Euro, Yuan, Rupees, Galactic Credits] to 
 * let us take over your shared e-mail headache is a worthwhile investment.  
 * It will give you a sense of control over your in-box that you probably 
 * haven't had since spammers found you in a game of "E-mail Address 
 * Battleship".  Miss. Miss. You sunk my in-box!
 * 
 * A legitimate license entitles you to support, access to the developer 
 * mailing list, the ability to participate in betas and the warm fuzzy 
 * feeling of feeding a couple obsessed developers who want to help you get 
 * more done than 'the other guy'.
 *
 * - Jeff Standen, Mike Fogg, Brenan Cavish, Darren Sugita, Dan Hildebrandt
 * 		and Joe Geck.
 *   WEBGROUP MEDIA LLC. - Developers of Cerberus Helpdesk
 */
$db = DevblocksPlatform::getDatabaseService();
$datadict = NewDataDictionary($db); /* @var $datadict ADODB_DataDict */ // ,'mysql' 

$prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup

$tables = $datadict->MetaTables();

// `plugin` ========================
$columns = $datadict->MetaColumns($prefix.'plugin');
$indexes = $datadict->MetaIndexes($prefix.'plugin',false);

if(!isset($columns['FILE'])) {
	$sql = $datadict->AddColumnSQL($prefix.'plugin',"file C(128) DEFAULT '' NOTNULL");
	$datadict->ExecuteSQLArray($sql);
}

if(!isset($columns['CLASS'])) {
	$sql = $datadict->AddColumnSQL($prefix.'plugin',"class C(128) DEFAULT '' NOTNULL");
	$datadict->ExecuteSQLArray($sql);
}

if(!isset($columns['LINK'])) {
	$sql = $datadict->AddColumnSQL($prefix.'plugin',"link C(128) DEFAULT '' NOTNULL");
	$datadict->ExecuteSQLArray($sql);
}

if(!isset($columns['IS_CONFIGURABLE'])) {
	$sql = $datadict->AddColumnSQL($prefix.'plugin',"is_configurable I1 DEFAULT 0 NOTNULL");
	$datadict->ExecuteSQLArray($sql);
}

// `property_store` ========================
$columns = $datadict->MetaColumns($prefix.'property_store');
$indexes = $datadict->MetaIndexes($prefix.'property_store',false);

if(255 == @$columns['VALUE']->max_length) {
	$datadict->ExecuteSQLArray($datadict->RenameColumnSQL($prefix.'property_store', 'value', 'value_old',"value_old C(255) DEFAULT '' NOTNULL"));
	$datadict->ExecuteSQLArray($datadict->AddColumnSQL($prefix.'property_store', "value B"));
	
	$sql = "SELECT extension_id, instance_id, property, value_old FROM ${prefix}property_store ";
	$rs = $db->Execute($sql);
	
	if($rs)
	while(!$rs->EOF) {
		@$db->UpdateBlob(
			$prefix.'property_store',
			'value',
			$rs->fields['value_old'],
			sprintf("extension_id = %s AND instance_id = %s AND property = %s",
				$db->qstr($rs->fields['extension_id']),
				$db->qstr($rs->fields['instance_id']),
				$db->qstr($rs->fields['property'])
			)
		);
		$rs->MoveNext();
	}
	
	if($rs)
		$datadict->ExecuteSQLArray($datadict->DropColumnSQL($prefix.'property_store', 'value_old'));
}

return TRUE;
?>