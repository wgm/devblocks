<?php
class DevblocksDAO {
	
	function updatePlugin($id, $fields) {
		$um_db = DevblocksPlatform::getDatabaseService();
		$sets = array();
		
		if(!is_array($fields) || empty($fields) || empty($id))
			return;
		
		foreach($fields as $k => $v) {
			$sets[] = sprintf("%s = %s",
				$k,
				$um_db->qstr($v)
			);
		}
			
		$sql = sprintf("UPDATE plugin SET %s WHERE id = %s",
			implode(', ', $sets),
			$um_db->qstr($id)
		);
		$um_db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $um_db->ErrorMsg()); /* @var $rs ADORecordSet */
	}
};
?>