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
	static protected function _update($ids=array(), $table, $fields) {
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
			
		$sql = sprintf("UPDATE %s SET %s WHERE id IN (%s)",
			$table,
			implode(', ', $sets),
			implode(',', $ids)
		);
		$db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
	}
	
	static protected function _escapeSearchParam(DevblocksSearchCriteria $param, $fields) {
	    $db = DevblocksPlatform::getDatabaseService();
	    $field = $fields[$param->field];
	    $where_value = null;
/*	    
   	 *	C for character < 250 chars
	 *	X for teXt (>= 250 chars)
	 *	B for Binary
	 * 	N for numeric or floating point
	 *	D for date
	 *	T for timestamp
	 * 	L for logical/Boolean
	 *	I for integer
	 *	R for autoincrement counter/integer
*/	    
//	    $datadict = new ADODB_DataDict($db);
//	    $datadict->MetaType()

        if($field) {
            switch(strtoupper($field->db_type)) {
                case 'B':
                case 'X':
                    if($db->blobEncodeType) {
	                    if(!is_array($param->value)) {
	                        $where_value = "'" . $db->BlobEncode($param->value) . "'";
	                    } else {
	                        $where_value = array();
	                        foreach($param->value as $v) {
	                            $where_value[] = "'" . $db->BlobEncode($v) . "'";
	                        }
	                    }
                    } else {
	                    if(!is_array($param->value)) {
	                        $where_value = $db->qstr($param->value);
	                    } else {
	                        $where_value = array();
	                        foreach($param->value as $v) {
	                            $where_value[] = $db->qstr($v);
	                        }
	                    }
                    }
                    break;
                    
                case 'I':
                case 'N':
                case 'L':
                case 'R':
                    if(!is_array($param->value)) {
                        $where_value = $param->value;
                    } else {
                        $where_value = array();
                        foreach($param->value as $v) {
                            $where_value[] = $v;
                        }
                    }
                    break;
                
                case 'C':
                default:
                    if(!is_array($param->value)) {
                        $where_value = $db->qstr($param->value);
                    } else {
                        $where_value = array();
                        foreach($param->value as $v) {
                            $where_value[] = $db->qstr($v);
                        }
                    }
                    break; 
            }
        }
        
        return $where_value;
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
			if(!$param instanceOf DevblocksSearchCriteria) 
				continue;
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
						self::_escapeSearchParam($param, $fields)
					);
					break;
					
				case "!=":
					$where = sprintf("%s != %s",
						$db_field_name,
						self::_escapeSearchParam($param, $fields)
					);
					break;
				
				case "in":
					if(!is_array($param->value)) break;
					$where = sprintf("%s IN ('%s')",
						$db_field_name,
						implode("','",$param->value) // [TODO] Needs BlobEncode compat
					);
					break;
					
				case DevblocksSearchCriteria::OPER_LIKE:
//					if(!is_array($param->value)) break;
					$where = sprintf("%s LIKE %s",
						$db_field_name,
						str_replace('*','%%',self::_escapeSearchParam($param, $fields))
					);
					break;
				
				case DevblocksSearchCriteria::OPER_NOT_LIKE:
//					if(!is_array($param->value)) break;
					$where = sprintf("%s NOT LIKE %s",
						$db_field_name,
						str_replace('*','%%',self::_escapeSearchParam($param, $fields))
					);
					break;
				
				case DevblocksSearchCriteria::OPER_IS_NULL:
					$where = sprintf("%s IS NULL",
						$db_field_name
					);
					break;
				
				/*
				 * [TODO] Someday we may want to call this OPER_DATE_BETWEEN so it doesn't interfere 
				 * with the operator in other uses
				 */
				case DevblocksSearchCriteria::OPER_BETWEEN:
					if(!is_array($param->value) && 2 != count($param->value))
						break;
						
					$where = sprintf("%s BETWEEN %s and %s",
						$db_field_name,
						(!is_numeric($param->value[0]) ? strtotime($param->value[0]) : $param->value[0]),
						(!is_numeric($param->value[1]) ? strtotime($param->value[1]) : $param->value[1])
					);
					break;
				
				case DevblocksSearchCriteria::OPER_GT:
				case DevblocksSearchCriteria::OPER_GTE:
				case DevblocksSearchCriteria::OPER_LT:
				case DevblocksSearchCriteria::OPER_LTE:
					$where = sprintf("%s %s %s",
						$db_field_name,
						$param->operator,
						self::_escapeSearchParam($param, $fields)
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
    static function cleanupPluginTables() {
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
	static function setPatchRan($plugin_id,$revision) {
		$db = DevblocksPlatform::getDatabaseService();
		$prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup
		
		$db->Replace(
			$prefix.'patch_history',
			array('plugin_id'=>$plugin_id,'revision'=>$revision,'run_date'=>time()),
			array('plugin_id'),
			true,
			false
		);
	}
	
};

