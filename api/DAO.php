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
	static protected function _update($ids=array(), $table, $fields, $idcol='id') {
	    if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		$sets = array();
		
		if(!is_array($fields) || empty($fields) || empty($ids))
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
			
		$sql = sprintf("UPDATE %s SET %s WHERE %s IN (%s)",
			$table,
			implode(', ', $sets),
			$idcol,
			implode(',', $ids)
		);
		$db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
	}
	
	static protected function _updateWhere($table, $fields, $where) {
		$db = DevblocksPlatform::getDatabaseService();
		$sets = array();
		
		if(!is_array($fields) || empty($fields) || empty($where))
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
			
		$sql = sprintf("UPDATE %s SET %s WHERE %s",
			$table,
			implode(', ', $sets),
			$where
		);
		$db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
	}
	
	/**
	 * [TODO]: Import the searchDAO functionality + combine the extraneous classes
	 */
	static protected function _parseSearchParams($params,$columns=array(),$fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$tables = array();
		$wheres = array();
		$selects = array();
		
		// Columns
		if(is_array($columns))
		foreach($columns as $column) {
			$tables[$fields[$column]->db_table] = $fields[$column]->db_table;
			$selects[] = sprintf("%s.%s AS %s",
				$fields[$column]->db_table,
				$fields[$column]->db_column,
				$column
			);
		}
		
		// Params
		if(is_array($params))
		foreach($params as $param) {
			
			// Is this a criteria group (OR, AND)?
			if(is_array($param)) {
				$group_wheres = array();
				@$group_oper = strtoupper(array_shift($param));
				
				switch($group_oper) {
					case DevblocksSearchCriteria::GROUP_OR:
					case DevblocksSearchCriteria::GROUP_AND:
						foreach($param as $p) { /* @var $$p DevblocksSearchCriteria */
							// [JAS]: Filter allowed columns (ignore invalid/deprecated)
							if(!isset($fields[$p->field]))
								continue;
							
							// [JAS]: Indexes for optimization
							$tables[$fields[$p->field]->db_table] = $fields[$p->field]->db_table;
							$group_wheres[] = $p->getWhereSQL($fields);
						}
						
						$where = sprintf("(%s)",
							implode(" $group_oper ", $group_wheres)
						);
						break;
				}
				
			// Is this a single parameter?
			} elseif($param instanceOf DevblocksSearchCriteria) { /* @var $param DevblocksSearchCriteria */
				// [JAS]: Filter allowed columns (ignore invalid/deprecated)
				if(!isset($fields[$param->field]))
					continue;
				
				// [JAS]: Indexes for optimization
				$tables[$fields[$param->field]->db_table] = $fields[$param->field]->db_table;
				$where = $param->getWhereSQL($fields);
			}
			
			if(!empty($where)) $wheres[] = $where;
		}
		
		return array($tables, $wheres, $selects);
	}
};

class DAO_Platform {
    static function cleanupPluginTables() {
    	DevblocksPlatform::clearCache();
    	
		$db = DevblocksPlatform::getDatabaseService();
		$prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup

        $sql = sprintf("SELECT id FROM %splugin ",
            $prefix
        );
		$rs = $db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */

		$plugins = DevblocksPlatform::getPluginRegistry();
		
		// [JAS]: Remove any plugins that are no longer in the filesystem
		while(!$rs->EOF) {
		    $plugin_id = $rs->fields['id'];
		    if(!isset($plugins[$plugin_id])) {
		        $db->Execute(sprintf("DELETE FROM %splugin WHERE id = %s",
		            $prefix,
		            $db->qstr($plugin_id)
		        ));
		        $db->Execute(sprintf("DELETE FROM %sextension WHERE id = %s",
		            $prefix,
		            $db->qstr($plugin_id)
		        ));
		        $db->Execute(sprintf("DELETE FROM %sproperty_store WHERE id = %s",
		            $prefix,
		            $db->qstr($plugin_id)
		        ));
		    }
		    $rs->MoveNext();
		}
    }
    
	static function updatePlugin($id, $fields) {
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
	static function hasPatchRun($plugin_id,$revision) {
		$db = DevblocksPlatform::getDatabaseService();
		$prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup
		
		// [JAS]: [TODO] Does the GTE below do what we need with the primary key mucking up redundant patches?
		$sql = sprintf("SELECT run_date FROM %spatch_history WHERE plugin_id = %s AND revision >= %d",
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
	static function setPatchRan($plugin_id,$revision) {
		$db = DevblocksPlatform::getDatabaseService();
		$prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup
		
		$db->Replace(
			$prefix.'patch_history',
			array('plugin_id'=>$db->qstr($plugin_id),'revision'=>$revision,'run_date'=>time()),
			array('plugin_id'),
			false,
			false
		);
	}
	
};

