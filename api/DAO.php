<?php
abstract class DevblocksORMHelper {
	/**
	 * @return integer new id
	 */
	// [TODO] Phase this out for create($fields);
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
		    if(is_null($v))
		        $value = 'NULL';
		    else
		        $value = $db->qstr($v);
		    
			$sets[] = sprintf("%s = %s",
				$k,
				$value
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
	static protected function _parseSearchParams($params,$fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$tables = array();
		$wheres = array();
		
		if(is_array($params))
		foreach($params as $param) { /* @var $param DevblocksSearchCriteria */
			if(!($param instanceOf DevblocksSearchCriteria)) continue;
			$where = "";
			
			// [JAS]: Filter allowed columns (ignore invalid/deprecated)
			if(!isset($fields[$param->field]))
				continue;

			$db_field_name = $fields[$param->field]->db_table . '.' . $fields[$param->field]->db_column; 
			
			// [JAS]: Indexes for optimization
			$tables[$fields[$param->field]->db_table] = $fields[$param->field]->db_table;
				
			// [JAS]: Operators
			switch($param->operator) {
				case "=":
					$where = sprintf("%s = %s",
						$db_field_name,
						$db->qstr($param->value)
					);
					break;
					
				case "!=":
					$where = sprintf("%s != %s",
						$db_field_name,
						$db->qstr($param->value)
					);
					break;
				
				case "in":
					if(!is_array($param->value)) break;
					$where = sprintf("%s IN ('%s')",
						$db_field_name,
						implode("','",$param->value)
					);
					break;
					
				case DevblocksSearchCriteria::OPER_LIKE:
//					if(!is_array($param->value)) break;
					$where = sprintf("%s LIKE %s",
						$db_field_name,
						$db->qstr(str_replace('*','%%',$param->value))
					);
					break;
				
				case DevblocksSearchCriteria::OPER_IS_NULL:
					$where = sprintf("%s IS NULL",
						$db_field_name
					);
					break;
				
				case DevblocksSearchCriteria::OPER_GT:
				case DevblocksSearchCriteria::OPER_GTE:
				case DevblocksSearchCriteria::OPER_LT:
				case DevblocksSearchCriteria::OPER_LTE:
					$where = sprintf("%s %s %s",
						$db_field_name,
						$param->operator,
						$db->qstr($param->value)
					);
				    break;
					
				default:
					break;
			}
			
			if(!empty($where)) $wheres[] = $where;
		}
		
		return array($tables, $wheres);
	}
};

class DAO_Platform {
	
	function updatePlugin($id, $fields) {
		$db = DevblocksPlatform::getDatabaseService();
		$prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup
		$sets = array();
		
		if(!is_array($fields) || empty($fields) || empty($id))
			return;
		
		foreach($fields as $k => $v) {
			$sets[] = sprintf("%s = %s",
				$k,
				$db->qstr($v)
			);
		}
			
		$sql = sprintf("UPDATE %splugin SET %s WHERE id = %s",
			$prefix,
			implode(', ', $sets),
			$db->qstr($id)
		);
		$db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
	}

	/**
	 * @param string $plugin_id
	 * @param integer $revision
	 * @return boolean
	 */
	function hasPatchRun($plugin_id,$revision) {
		$db = DevblocksPlatform::getDatabaseService();
		$prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup
		
		$sql = sprintf("SELECT run_date FROM %spatch_history WHERE plugin_id = %s AND revision = %d",
			$prefix,
			$db->qstr($plugin_id),
			$revision
		);
		$rs = $db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		
		if($rs->NumRows()) {
			return true; //$rs->Fields('run_date')
		}
		
		return FALSE;
	}
	
	/**
	 * @param string $plugin_id
	 * @param integer $revision
	 */
	function setPatchRan($plugin_id,$revision) {
		$db = DevblocksPlatform::getDatabaseService();
		$prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup
		
		$db->Replace(
			$prefix.'patch_history',
			array('plugin_id'=>$plugin_id,'revision'=>$revision,'run_date'=>gmmktime()),
			array('plugin_id','revision'),
			true,
			false
		);
	}
	
};
?>
