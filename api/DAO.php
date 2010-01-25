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
		$rs = $db->Execute($sql); /* @var $rs ADORecordSet */
		
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
		$db->Execute($sql); /* @var $rs ADORecordSet */
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
		$db->Execute($sql); /* @var $rs ADORecordSet */
	}
	
	/**
	 * [TODO]: Import the searchDAO functionality + combine the extraneous classes
	 */
	static protected function _parseSearchParams($params,$columns=array(),$fields,$sortBy='') {
		$db = DevblocksPlatform::getDatabaseService();
		
		$tables = array();
		$wheres = array();
		$selects = array();
		
		// Sort By
		if(!empty($sortBy) && isset($fields[$sortBy]))
			$tables[$fields[$sortBy]->db_table] = $fields[$sortBy]->db_table;
		
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
				$where = self::_parseNestedSearchParams($param, $tables, $fields);
				
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
	
	static private function _parseNestedSearchParams($param,&$tables,$fields) {
		$outer_wheres = array();
		$group_wheres = array();
		@$group_oper = strtoupper(array_shift($param));
		$where = '';
		
		switch($group_oper) {
			case DevblocksSearchCriteria::GROUP_OR:
			case DevblocksSearchCriteria::GROUP_AND:
				foreach($param as $p) { /* @var $$p DevblocksSearchCriteria */
					if(is_array($p)) {
						$outer_wheres[] = self::_parseNestedSearchParams($p, $tables, $fields);
						
					} else {
						// [JAS]: Filter allowed columns (ignore invalid/deprecated)
						if(!isset($fields[$p->field]))
							continue;
						
						// [JAS]: Indexes for optimization
						$tables[$fields[$p->field]->db_table] = $fields[$p->field]->db_table;
						$group_wheres[] = $p->getWhereSQL($fields);
						
						$where = sprintf("(%s)",
							implode(" $group_oper ", $group_wheres)
						);
					}
				}
				
				break;
		}
		
		if(!empty($outer_wheres)) {
			return sprintf("(%s)",
				implode(" $group_oper ", $outer_wheres)
			);
			
		} else {
			return $where;
			
		}
		
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
		$rs = $db->Execute($sql); /* @var $rs ADORecordSet */

		$plugins = DevblocksPlatform::getPluginRegistry();
		
		// [JAS]: Remove any plugins that are no longer in the filesystem
		if(is_a($rs,'ADORecordSet'))
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
		$db->Execute($sql); /* @var $rs ADORecordSet */
	}
	
	static function deleteExtension($extension_id) {
		$db = DevblocksPlatform::getDatabaseService();
		$prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup
		
		// Nuke cached extension manifest
		$sql = sprintf("DELETE FROM %sextension WHERE id = %s",
			$prefix,
			$db->qstr($extension_id)
		);
		$db->Execute($sql);
		
		// Nuke cached extension properties
		$sql = sprintf("DELETE FROM %sproperty_store WHERE extension_id = %s",
			$prefix,
			$db->qstr($extension_id)
		);
		$db->Execute($sql);
	}

	/**
	 * @param string $plugin_id
	 * @param integer $revision
	 * @return boolean
	 */
	static function hasPatchRun($plugin_id,$revision) {
		$tables = DevblocksPlatform::getDatabaseTables();
		if(empty($tables))
			return false;
		
		$db = DevblocksPlatform::getDatabaseService();
		$prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup
		
		// [JAS]: [TODO] Does the GTE below do what we need with the primary key mucking up redundant patches?
		$sql = sprintf("SELECT run_date FROM %spatch_history WHERE plugin_id = %s AND revision >= %d",
			$prefix,
			$db->qstr($plugin_id),
			$revision
		);
		$rs = $db->Execute($sql); /* @var $rs ADORecordSet */
		
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
	
	static function getClassLoaderMap() {
		if(null == ($db = DevblocksPlatform::getDatabaseService()) || !$db->IsConnected())
			return array();

		$plugins = DevblocksPlatform::getPluginRegistry();
			
		$prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup		
		$class_loader_map = array();
		
		$sql = sprintf("SELECT class, plugin_id, rel_path FROM %sclass_loader ORDER BY plugin_id", $prefix);
		$rs = $db->Execute($sql); /* @var $rs ADORecordSet */ //

		if(is_a($rs,'ADORecordSet'))
		while(!$rs->EOF) {
			@$class = $rs->fields['class'];
			@$plugin_id = $rs->fields['plugin_id'];
			@$rel_path = $rs->fields['rel_path'];
			
			// Make sure the plugin is valid
			if(isset($plugins[$plugin_id])) {
				// Build an absolute path
				$path = APP_PATH . DIRECTORY_SEPARATOR . $plugins[$plugin_id]->dir . DIRECTORY_SEPARATOR . $rel_path;
				
				// Init the array
				if(!isset($class_loader_map[$path]))
					$class_loader_map[$path] = array();
				
				$class_loader_map[$path][] = $class;
			}
			
			$rs->MoveNext();
		}
		
		return $class_loader_map;
	}
	
	static function getUriRoutingMap() {
		$db = DevblocksPlatform::getDatabaseService();
		$prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup		
		
		$uri_routing_map = array();
	
		$sql = sprintf("SELECT uri, plugin_id, controller_id FROM %suri_routing ORDER BY plugin_id", $prefix);
		$rs = $db->Execute($sql); /* @var $rs ADORecordSet */ //or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg())

		if(is_a($rs,'ADORecordSet'))
		while(!$rs->EOF) {
			@$uri = $rs->fields['uri'];
			@$plugin_id = $rs->fields['plugin_id'];
			@$controller_id = $rs->fields['controller_id'];
			
			$uri_routing_map[$uri] = $controller_id;
			
			$rs->MoveNext();
		}
	
		return $uri_routing_map;
	}
};

class DAO_DevblocksSetting extends DevblocksORMHelper {
	static function set($plugin_id, $key, $value) {
		$db = DevblocksPlatform::getDatabaseService();
		$db->Replace(
			'devblocks_setting',
			array(
				'plugin_id'=>$db->qstr($plugin_id),
				'setting'=>$db->qstr($key),
				'value'=>$db->qstr($value)
			),
			array('plugin_id','setting'),
			false
		);
		
//		$cache = DevblocksPlatform::getCacheService();
//		$cache->remove(DevblocksPlatform::CACHE_SETTINGS);
	}
	
	static function get($plugin_id, $key) {
		$db = DevblocksPlatform::getDatabaseService();
		$sql = sprintf("SELECT value FROM devblocks_setting WHERE plugin_id = %s AND setting = %s",
			$db->qstr($plugin_id),
			$db->qstr($key)
		);
		$value = $db->GetOne($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		
		return $value;
	}
	
	static function getSettings($plugin_id=null) {
	    $cache = DevblocksPlatform::getCacheService();
	    if(null === ($plugin_settings = $cache->load(DevblocksPlatform::CACHE_SETTINGS))) {
			$db = DevblocksPlatform::getDatabaseService();
			$plugin_settings = array();
			
			$sql = sprintf("SELECT plugin_id,setting,value FROM devblocks_setting");
			$rs = $db->Execute($sql); /* @var $rs ADORecordSet */
			
			if(is_a($rs,'ADORecordSet'))
			while(!$rs->EOF) {
				$plugin_id = $rs->Fields('plugin_id');
				$k = $rs->Fields('setting');
				$v = $rs->Fields('value');
				
				if(!isset($plugin_settings[$plugin_id]))
					$plugin_settings[$plugin_id] = array();
				
				$plugin_settings[$plugin_id][$k] = $v;
				$rs->MoveNext();
			}
			
			if(!empty($plugin_settings))
				$cache->save($plugin_settings, DevblocksPlatform::CACHE_SETTINGS);
	    }
	    
		return $plugin_settings;
	}
};

class DAO_DevblocksTemplate extends DevblocksORMHelper {
	const ID = 'id';
	const PLUGIN_ID = 'plugin_id';
	const PATH = 'path';
	const TAG = 'tag';
	const LAST_UPDATED = 'last_updated';
	const CONTENT = 'content';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$id = $db->GenID('generic_seq');
		
		$sql = sprintf("INSERT INTO devblocks_template (id) ".
			"VALUES (%d)",
			$id
		);
		$db->Execute($sql);
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields) {
		parent::_update($ids, 'devblocks_template', $fields);
	}
	
	static function updateWhere($fields, $where) {
		parent::_updateWhere('devblocks_template', $fields, $where);
	}
	
	/**
	 * @param string $where
	 * @return Model_DevblocksTemplate[]
	 */
	static function getWhere($where=null) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "SELECT id, plugin_id, path, tag, last_updated, content ".
			"FROM devblocks_template ".
			(!empty($where) ? sprintf("WHERE %s ",$where) : "").
			"ORDER BY id asc";
		$rs = $db->Execute($sql);
		
		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_DevblocksTemplate	 */
	static function get($id) {
		$objects = self::getWhere(sprintf("%s = %d",
			self::ID,
			$id
		));
		
		if(isset($objects[$id]))
			return $objects[$id];
		
		return null;
	}
	
	/**
	 * @param ADORecordSet $rs
	 * @return Model_DevblocksTemplate[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		while(!$rs->EOF) {
			$object = new Model_DevblocksTemplate();
			$object->id = $rs->fields['id'];
			$object->plugin_id = $rs->fields['plugin_id'];
			$object->path = $rs->fields['path'];
			$object->tag = $rs->fields['tag'];
			$object->last_updated = $rs->fields['last_updated'];
			$object->content = $rs->fields['content'];
			$objects[$object->id] = $object;
			$rs->MoveNext();
		}
		
		return $objects;
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;

		$ids_list = implode(',', $ids);

		$db->Execute(sprintf("DELETE FROM devblocks_template WHERE id IN (%s)", $ids_list));
		
		return true;
	}
	
    /**
     * Enter description here...
     *
     * @param array $columns
     * @param DevblocksSearchCriteria[] $params
     * @param integer $limit
     * @param integer $page
     * @param string $sortBy
     * @param boolean $sortAsc
     * @param boolean $withCounts
     * @return array
     */
    static function search($columns, $params, $limit=10, $page=0, $sortBy=null, $sortAsc=null, $withCounts=true) {
		$db = DevblocksPlatform::getDatabaseService();
		$fields = SearchFields_DevblocksTemplate::getFields();
		
		// Sanitize
		if(!isset($fields[$sortBy]))
			$sortBy=null;

        list($tables,$wheres) = parent::_parseSearchParams($params, $columns, $fields, $sortBy);
		$start = ($page * $limit); // [JAS]: 1-based
		$total = -1;
		
		$select_sql = sprintf("SELECT ".
			"devblocks_template.id as %s, ".
			"devblocks_template.plugin_id as %s, ".
			"devblocks_template.path as %s, ".
			"devblocks_template.tag as %s, ".
			"devblocks_template.last_updated as %s ",
//			"devblocks_template.content as %s ",
				SearchFields_DevblocksTemplate::ID,
				SearchFields_DevblocksTemplate::PLUGIN_ID,
				SearchFields_DevblocksTemplate::PATH,
				SearchFields_DevblocksTemplate::TAG,
				SearchFields_DevblocksTemplate::LAST_UPDATED
//				SearchFields_DevblocksTemplate::CONTENT
			);
			
		$join_sql = "FROM devblocks_template ";
		
		// Custom field joins
		//list($select_sql, $join_sql, $has_multiple_values) = self::_appendSelectJoinSqlForCustomFieldTables(
		//	$tables,
		//	$params,
		//	'devblocks_template.id',
		//	$select_sql,
		//	$join_sql
		//);
				
		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "");
			
		$sort_sql = (!empty($sortBy)) ? sprintf("ORDER BY %s %s ",$sortBy,($sortAsc || is_null($sortAsc))?"ASC":"DESC") : " ";
			
		$sql = 
			$select_sql.
			$join_sql.
			$where_sql.
			($has_multiple_values ? 'GROUP BY devblocks_template.id ' : '').
			$sort_sql;
			
		// [TODO] Could push the select logic down a level too
		if($limit > 0) {
    		$rs = $db->SelectLimit($sql,$limit,$start) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		} else {
		    $rs = $db->Execute($sql) or die(__CLASS__ . '('.__LINE__.')'. ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
            $total = $rs->RecordCount();
		}
		
		$results = array();
		
		if(is_a($rs,'ADORecordSet'))
		while(!$rs->EOF) {
			$result = array();
			foreach($rs->fields as $f => $v) {
				$result[$f] = $v;
			}
			$object_id = intval($rs->fields[SearchFields_DevblocksTemplate::ID]);
			$results[$object_id] = $result;
			$rs->MoveNext();
		}

		// [JAS]: Count all
		if($withCounts) {
			$count_sql = 
				($has_multiple_values ? "SELECT COUNT(DISTINCT devblocks_template.id) " : "SELECT COUNT(devblocks_template.id) ").
				$join_sql.
				$where_sql;
			$total = $db->GetOne($count_sql);
		}
		
		return array($results,$total);
	}
	
	static function importXmlFile($filename, $tag) {
		$db = DevblocksPlatform::getDatabaseService();
		
		if(!file_exists($filename) && empty($tag))
			return;
		
		if(false == (@$xml = simplexml_load_file($filename))) /* @var $xml SimpleXMLElement */
			return;

		// Loop through all the template elements and insert/update for this tag
		foreach($xml->templates->template as $eTemplate) { /* @var $eTemplate SimpleXMLElement */
			$plugin_id = (string) $eTemplate['plugin_id'];
			$path = (string) $eTemplate['path'];
			$content = (string) $eTemplate[0];

			// Pull the template if it exists already
			@$template = array_shift(self::getWhere(sprintf("%s = %s AND %s = %s AND %s = %s",
				self::PLUGIN_ID,
				$db->qstr($plugin_id),
				self::PATH,
				$db->qstr($path),
				self::TAG,
				$db->qstr($tag)
			)));

			// Common fields
			$fields = array(
				self::CONTENT => $content,
				self::LAST_UPDATED => time(),
			);
			
			// Create or update
			if(empty($template)) { // new
				$fields[self::PLUGIN_ID] = $plugin_id;
				$fields[self::PATH] = $path;
				$fields[self::TAG] = $tag;
				self::create($fields);
				
			} else { // update
				self::update($template->id, $fields);
				
			}
		}
			
		unset($xml);
	}	

};

class SearchFields_DevblocksTemplate implements IDevblocksSearchFields {
	const ID = 'd_id';
	const PLUGIN_ID = 'd_plugin_id';
	const PATH = 'd_path';
	const TAG = 'd_tag';
	const LAST_UPDATED = 'd_last_updated';
//	const CONTENT = 'd_content';
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		
		$columns = array(
			self::ID => new DevblocksSearchField(self::ID, 'devblocks_template', 'id', null, $translate->_('common.id')),
			self::PLUGIN_ID => new DevblocksSearchField(self::PLUGIN_ID, 'devblocks_template', 'plugin_id', null, $translate->_('plugin_id')),
			self::PATH => new DevblocksSearchField(self::PATH, 'devblocks_template', 'path', null, $translate->_('path')),
			self::TAG => new DevblocksSearchField(self::TAG, 'devblocks_template', 'tag', null, $translate->_('tag')),
			self::LAST_UPDATED => new DevblocksSearchField(self::LAST_UPDATED, 'devblocks_template', 'last_updated', null, $translate->_('last_updated')),
//			self::CONTENT => new DevblocksSearchField(self::CONTENT, 'devblocks_template', 'content', null, $translate->_('content')),
		);
		
		// Custom Fields
		//$fields = DAO_CustomField::getBySource(PsCustomFieldSource_XXX::ID);

		//if(is_array($fields))
		//foreach($fields as $field_id => $field) {
		//	$key = 'cf_'.$field_id;
		//	$columns[$key] = new DevblocksSearchField($key,$key,'field_value',null,$field->name);
		//}
		
		// Sort by label (translation-conscious)
		uasort($columns, create_function('$a, $b', "return strcasecmp(\$a->db_label,\$b->db_label);\n"));

		return $columns;		
	}
};

class DAO_Translation extends DevblocksORMHelper {
	const ID = 'id';
	const STRING_ID = 'string_id';
	const LANG_CODE = 'lang_code';
	const STRING_DEFAULT = 'string_default';
	const STRING_OVERRIDE = 'string_override';

	static function create($fields) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$id = $db->GenID('generic_seq');
		
		$sql = sprintf("INSERT INTO translation (id) ".
			"VALUES (%d)",
			$id
		);
		$db->Execute($sql);
		
		self::update($id, $fields);
		
		return $id;
	}
	
	static function update($ids, $fields) {
		parent::_update($ids, 'translation', $fields);
	}
	
	/**
	 * @param string $where
	 * @return Model_TranslationDefault[]
	 */
	static function getWhere($where=null) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "SELECT id, string_id, lang_code, string_default, string_override ".
			"FROM translation ".
			(!empty($where) ? sprintf("WHERE %s ",$where) : "").
			"ORDER BY string_id ASC, lang_code ASC";
		$rs = $db->Execute($sql);
		
		return self::_getObjectsFromResult($rs);
	}

	/**
	 * @param integer $id
	 * @return Model_TranslationDefault	 */
	static function get($id) {
		$objects = self::getWhere(sprintf("%s = %d",
			self::ID,
			$id
		));
		
		if(isset($objects[$id]))
			return $objects[$id];
		
		return null;
	}
	
	static function importTmxFile($filename) {
		$db = DevblocksPlatform::getDatabaseService();
		
		if(!file_exists($filename))
			return;
		
		/*
		 * [JAS] [TODO] This could be inefficient when reading a lot 
		 * of TMX sources, but it could also be inefficient always
		 * keeping it in memory after using it once.  I'm going to err
		 * on the side of a little extra DB work for the few times it's 
		 * called.
		 */
		
		$hash = array();
		foreach(DAO_Translation::getWhere() as $s) { /* @var $s Model_TranslationDefault */
			$hash[$s->lang_code.'_'.$s->string_id] = $s;
		}
		
		if(false == (@$xml = simplexml_load_file($filename))) /* @var $xml SimpleXMLElement */
			return;
			
		$namespaces = $xml->getNamespaces(true);
		
		foreach($xml->body->tu as $tu) { /* @var $tu SimpleXMLElement */
			$msgid = strtolower((string) $tu['tuid']);
			foreach($tu->tuv as $tuv) { /* @var $tuv SimpleXMLElement */
				$attribs = $tuv->attributes($namespaces['xml']); 
				$lang = (string) $attribs['lang'];
				$string = (string) $tuv->seg[0]; // [TODO] Handle multiple segs?
				
				@$hash_obj = $hash[$lang.'_'.$msgid]; /* @var $hash_obj Model_Translation */
				
				// If not found in the DB
				if(empty($hash_obj)) {
					$fields = array(
						DAO_Translation::STRING_ID => $msgid,
						DAO_Translation::LANG_CODE => $lang,
						DAO_Translation::STRING_DEFAULT => $string,
					);
					$id = DAO_Translation::create($fields);

					// Add to our hash to prevent dupes
					$new = new Model_Translation();
						$new->id = $id;
						$new->string_id = $msgid;
						$new->lang_code = $lang;
						$new->string_default = $string;
						$new->string_override = '';
					$hash[$lang.'_'.$msgid] = $new;
					
				// If exists in DB and the string has changed
				} elseif (!empty($hash_obj) && 0 != strcasecmp($string, $hash_obj->string_default)) {
					$fields = array(
						DAO_Translation::STRING_DEFAULT => $string,
					);
					DAO_Translation::update($hash_obj->id, $fields);
				}
			}
		}
	
		unset($xml);
	}
	
	static function reloadPluginStrings() {
		$translations = DevblocksPlatform::getExtensions("devblocks.i18n.strings", false, true);

		if(is_array($translations))
		foreach($translations as $translationManifest) { /* @var $translationManifest DevblocksExtensionManifest */
			if(null != ($translation = $translationManifest->createInstance())) { /* @var $translation DevblocksTranslationsExtension */
				$filename = $translation->getTmxFile();
				self::importTmxFile($filename);
			}
		}
	}
	
	static function getDefinedLangCodes() {
		$db = DevblocksPlatform::getDatabaseService();
		$translate = DevblocksPlatform::getTranslationService();
		
		$lang_codes = array();
		
		// Look up distinct land codes from existing translations
		$sql = sprintf("SELECT DISTINCT lang_code FROM translation ORDER BY lang_code ASC");
		$rs = $db->Execute($sql); /* @var $rs ADORecordSet */
		
		// Languages
		$langs = $translate->getLanguageCodes();

		// Countries
		$countries = $translate->getCountryCodes();
		
		if(is_a($rs,'ADORecordSet'))
		while(!$rs->EOF) {
			$code = $rs->fields['lang_code'];
			$data = explode('_', $code);
			@$lang = $langs[strtolower($data[0])];
			@$terr = $countries[strtoupper($data[1])];

			$lang_codes[$code] = (!empty($lang) && !empty($terr))
				? ($lang . ' (' . $terr . ')')
				: $code;

			$rs->MoveNext();
		}
		
		return $lang_codes;
	}
	
	static function getByLang($lang='en_US') {
		$db = DevblocksPlatform::getDatabaseService();
		
		return self::getWhere(sprintf("%s = %s",
			self::LANG_CODE,
			$db->qstr($lang)
		));
	}
	
	static function getMapByLang($lang='en_US') {
		$strings = self::getByLang($lang);
		$map = array();
		
		if(is_array($strings))
		foreach($strings as $string) { /* @var $string Model_Translation */
			if(is_a($string, 'Model_Translation'))
				$map[$string->string_id] = $string;
		}
		
		return $map;
	}
	
	// [TODO] Allow null 2nd arg for all instances of a given string?
	static function getString($string_id, $lang='en_US') {
		$db = DevblocksPlatform::getDatabaseService();
		
		$objects = self::getWhere(sprintf("%s = %s AND %s = %s",
			self::STRING_ID,
			$db->qstr($string_id),
			self::LANG_CODE,
			$db->qstr($lang)
		));

		if(!empty($objects) && is_array($objects))
			return array_shift($objects);
		
		return null;
	}
	
	/**
	 * @param ADORecordSet $rs
	 * @return Model_TranslationDefault[]
	 */
	static private function _getObjectsFromResult($rs) {
		$objects = array();
		
		while(is_a($rs,'ADORecordSet') && !$rs->EOF) {
			$object = new Model_Translation();
			$object->id = $rs->fields['id'];
			$object->string_id = $rs->fields['string_id'];
			$object->lang_code = $rs->fields['lang_code'];
			$object->string_default = $rs->fields['string_default'];
			$object->string_override = $rs->fields['string_override'];
			$objects[$object->id] = $object;
			$rs->MoveNext();
		}
		
		return $objects;
	}
	
	static function delete($ids) {
		if(!is_array($ids)) $ids = array($ids);
		$db = DevblocksPlatform::getDatabaseService();
		
		if(empty($ids))
			return;
		
		$ids_list = implode(',', $ids);
		
		$db->Execute(sprintf("DELETE FROM translation WHERE id IN (%s)", $ids_list));
		
		return true;
	}
	
	static function deleteByLangCodes($codes) {
		if(!is_array($codes)) $codes = array($codes);
		$db = DevblocksPlatform::getDatabaseService();
		
		$codes_list = implode("','", $codes);
		
		$db->Execute(sprintf("DELETE FROM translation WHERE lang_code IN ('%s') AND lang_code != 'en_US'", $codes_list));
		
		return true;
	}
	
    /**
     * Enter description here...
     *
     * @param DevblocksSearchCriteria[] $params
     * @param integer $limit
     * @param integer $page
     * @param string $sortBy
     * @param boolean $sortAsc
     * @param boolean $withCounts
     * @return array
     */
    static function search($params, $limit=10, $page=0, $sortBy=null, $sortAsc=null, $withCounts=true) {
		$db = DevblocksPlatform::getDatabaseService();
		$fields = SearchFields_Translation::getFields(); 
		
		// Sanitize
		if(!isset($fields[$sortBy]))
			$sortBy=null;

        list($tables,$wheres) = parent::_parseSearchParams($params, array(),$fields,$sortBy);
		$start = ($page * $limit); // [JAS]: 1-based [TODO] clean up + document
		
		$select_sql = sprintf("SELECT ".
			"tl.id as %s, ".
			"tl.string_id as %s, ".
			"tl.lang_code as %s, ".
			"tl.string_default as %s, ".
			"tl.string_override as %s ",
//			"o.name as %s ".
			    SearchFields_Translation::ID,
			    SearchFields_Translation::STRING_ID,
			    SearchFields_Translation::LANG_CODE,
			    SearchFields_Translation::STRING_DEFAULT,
			    SearchFields_Translation::STRING_OVERRIDE
			 );
		
		$join_sql = 
			"FROM translation tl ";
//			"LEFT JOIN contact_org o ON (o.id=a.contact_org_id) "

			// [JAS]: Dynamic table joins
//			(isset($tables['o']) ? "LEFT JOIN contact_org o ON (o.id=a.contact_org_id)" : " ").
//			(isset($tables['mc']) ? "INNER JOIN message_content mc ON (mc.message_id=m.id)" : " ").

		$where_sql = "".
			(!empty($wheres) ? sprintf("WHERE %s ",implode(' AND ',$wheres)) : "");
			
		$sql = $select_sql . $join_sql . $where_sql .  
			(!empty($sortBy) ? sprintf("ORDER BY %s %s",$sortBy,($sortAsc || is_null($sortAsc))?"ASC":"DESC") : "");
		
		$rs = $db->SelectLimit($sql,$limit,$start); /* @var $rs ADORecordSet */
		
		$results = array();
		
		if(is_a($rs,'ADORecordSet'))
		while(!$rs->EOF) {
			$result = array();
			foreach($rs->fields as $f => $v) {
				$result[$f] = $v;
			}
			$id = intval($rs->fields[SearchFields_Translation::ID]);
			$results[$id] = $result;
			$rs->MoveNext();
		}

		// [JAS]: Count all
		$total = -1;
		if($withCounts) {
			$count_sql = "SELECT count(*) " . $join_sql . $where_sql;
			$total = $db->GetOne($count_sql);
		}
		
		return array($results,$total);
    }	

};

class SearchFields_Translation implements IDevblocksSearchFields {
	// Translate
	const ID = 'tl_id';
	const STRING_ID = 'tl_string_id';
	const LANG_CODE = 'tl_lang_code';
	const STRING_DEFAULT = 'tl_string_default';
	const STRING_OVERRIDE = 'tl_string_override';
	
	/**
	 * @return DevblocksSearchField[]
	 */
	static function getFields() {
		$translate = DevblocksPlatform::getTranslationService();
		return array(
			self::ID => new DevblocksSearchField(self::ID, 'tl', 'id', null, $translate->_('translate.id')),
			self::STRING_ID => new DevblocksSearchField(self::STRING_ID, 'tl', 'string_id', null, $translate->_('translate.string_id')),
			self::LANG_CODE => new DevblocksSearchField(self::LANG_CODE, 'tl', 'lang_code', null, $translate->_('translate.lang_code')),
			self::STRING_DEFAULT => new DevblocksSearchField(self::STRING_DEFAULT, 'tl', 'string_default', null, $translate->_('translate.string_default')),
			self::STRING_OVERRIDE => new DevblocksSearchField(self::STRING_OVERRIDE, 'tl', 'string_override', null, $translate->_('translate.string_override')),
		);
	}
};
