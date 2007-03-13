<?php
abstract class DevblocksORMHelper {
	/**
	 * @return integer new id
	 */
	static protected function _createId($properties) {
		$sequence = !empty($properties['sequence']) ? $properties['sequence'] : 'generic_seq';
		
		if(empty($properties['table']) || empty($properties['id_column']))
			return FALSE;
		
		$db = DevblocksPlatform::getDatabaseService();
		$id = $db->GenID($sequence);
		
		$sql = sprintf("INSERT INTO %s (%s) VALUES (%d)",
			$properties['table'],
			$properties['id_column'],
			$id
		);
		$rs = $db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		
		return $id;
	}
	
	/**
	 * @param integer $id
	 * @param array $fields
	 */
	static protected function _update($id, $table, $fields) {
		$db = DevblocksPlatform::getDatabaseService();
		$sets = array();
		
		if(!is_array($fields) || empty($fields) || empty($id))
			return;
		
		foreach($fields as $k => $v) {
			$sets[] = sprintf("%s = %s",
				$k,
				$db->qstr($v)
			);
		}
			
		$sql = sprintf("UPDATE %s SET %s WHERE id = %d",
			$table,
			implode(', ', $sets),
			$id
		);
		$db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
	}
	
	/**
	 * [TODO]: Import the searchDAO functionality + combine the extraneous classes
	 */
	static protected function _search() {
		
	}
}

class DAO_Platform {
	
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