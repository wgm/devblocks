<?php
/**
 * @author Jeff Standen <jeff@webgroupmedia.com>
 */
class DAO_CloudGlue {
	
	/**
	 * @param array $tags
	 * @param integer $content_id
	 * @param string $index_name
	 * @param boolean $replace Replace previously applied tags on content/index pair.
	 */
	static function applyTags($tags,$content_id,$index_name,$replace=false) {

		$db = DevblocksPlatform::getDatabaseService();
		$index_id = self::lookupIndex($index_name,true);
		$tag_ids = array();
		
		if(empty($index_id) || empty($content_id)) {
			return NULL;
		}

		if($replace) {
			$sql = sprintf("DELETE FROM tag_to_content WHERE content_id = %d AND index_id = %d",
				$content_id,
				$index_id
			);
			$db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */	
		}
		
		if(is_array($tags) && !empty($tags))
		foreach($tags as $v) {
			$tag = self::lookupTag($v,true);
			if($tag instanceOf CloudGlueTag) {
			    $db->Replace(
			        'tag_to_content', 
			        array('index_id'=>$index_id,'tag_id'=>$tag->id, 'content_id'=>$content_id), 
			        array('index_id','tag_id', 'content_id')
			        );
			}
		}
		
	}

	/**
	 * @return array
	 */
	static function getCloudTags($index_name,$including_tags=array()) {
		$db = DevblocksPlatform::getDatabaseService();
		$hits = array();
		
		// [JAS]: Make sure our index exists
		$index_id = DAO_CloudGlue::lookupIndex($index_name);
		if(empty($index_id)) return array();
		
		// [JAS]: If we're requiring other tags, join them
		$joins = array();
		$exclude_ids = array();
		if(!empty($including_tags)) {
			$pos = 1;
			foreach($including_tags as $tag) { /* @var $tag CloudGlueTag */
				if(empty($tag->id)) continue;
				
				$exclude_ids[] = $tag->id;
				
				$joins[] = sprintf('INNER JOIN tag_to_content tc%1$d '.
					'ON (tc%1$d.content_id=tc%2$d.content_id AND tc%1$d.tag_id=%3$d) ',
					$pos,
					$pos-1,
					$tag->id
				);
				$pos++;
			}
		}
		
		$sql = sprintf("SELECT count(*) as hits, tc0.tag_id ". 
			"FROM tag_to_content tc0 ".
			(!empty($joins) ? implode(' ', $joins) : "").
			"WHERE tc0.index_id = %d ".
			(!empty($exclude_ids) ? sprintf("AND tc0.tag_id NOT IN (%s) ",implode(',', $exclude_ids)) : "").
			"GROUP BY tc0.tag_id ".
			"ORDER BY hits DESC ",
			$index_id
		);
//		echo $sql;
		$rs = $db->SelectLimit($sql,30,0) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		
		if(is_a($rs,'ADORecordSet'))
		while(!$rs->EOF) {
			$id = intval($rs->fields['tag_id']);
			$hits[$id] = intval($rs->fields['hits']);
			$rs->MoveNext();
		}
		
		if(empty($hits))
			return array();
			
		return array(DAO_CloudGlue::getTags(array_keys($hits)),$hits);
	}
	
	static function deleteContentIds($index_name,$content_ids) {
		$db = DevblocksPlatform::getDatabaseService();
		if(!is_array($content_ids)) $content_ids = array($content_ids);

		$index_id = DAO_CloudGlue::lookupIndex($index_name);
		if(empty($index_id)) return null;
		
		$sql = sprintf("DELETE FROM tag_to_content WHERE index_id = %d AND content_id IN (%s)",
			$index_id,
			implode(',', $content_ids)
		);
		$db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
	}
	
	/**
	 * @return array
	 */
	static function getTagContentIds($index_name,$including_tags=array()) {
		$db = DevblocksPlatform::getDatabaseService();
		$hits = array();
		
		// [JAS]: Make sure our index exists
		$index_id = DAO_CloudGlue::lookupIndex($index_name);
		if(empty($index_id)) return array();
		
		// [JAS]: If we're requiring other tags, join them
		$joins = array();
		$exclude_ids = array();
		if(!empty($including_tags)) {
			$pos = 1;
			foreach($including_tags as $tag) { /* @var $tag CloudGlueTag */
				if(empty($tag->id)) continue;
				
				$exclude_ids[] = $tag->id;
				
				$joins[] = sprintf('INNER JOIN tag_to_content tc%1$d '.
					'ON (tc%1$d.content_id=tc%2$d.content_id AND tc%1$d.tag_id=%3$d) ',
					$pos,
					$pos-1,
					$tag->id
				);
				$pos++;
			}
		}
		
		$sql = sprintf("SELECT count(*) as hits, tc0.content_id ". 
			"FROM tag_to_content tc0 ".
			(!empty($joins) ? implode(' ', $joins) : "").
			"WHERE tc0.index_id = %d ".
//			(!empty($exclude_ids) ? sprintf("AND tc0.tag_id NOT IN (%s) ",implode(',', $exclude_ids)) : "").
			"GROUP BY tc0.content_id ".
			"ORDER BY hits DESC ",
			$index_id
		);
//		echo $sql;
		$rs = $db->SelectLimit($sql,30,0) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		
		if(is_a($rs,'ADORecordSet'))
		while(!$rs->EOF) {
			$id = intval($rs->fields['content_id']);
			$hits[$id] = intval($rs->fields['hits']);
			$rs->MoveNext();
		}
		
		if(empty($hits))
			return array();
			
		return(array_keys($hits));
//		return array(DAO_CloudGlue::getTags(array_keys($hits)),$hits);
	}
	
	/**
	 * @param integer $id
	 * @return CloudGlueTag
	 */
	static function getTag($id) {
		if(empty($id)) return NULL;
		
		$tags = DAO_CloudGlue::getTags(array($id));
		
		if(isset($tags[$id]))
			return $tags[$id];
			
		return NULL;
	}
	
	/**
	 * @param array $content_ids
	 * @param string $index_name
	 * @return CloudGlueTag[]
	 */
	static function getTagsOnContents($content_ids,$index_name) {
		if(!is_array($content_ids)) $content_ids = array($content_ids);
		$db = DevblocksPlatform::getDatabaseService();
		$ids = array();
		
		$index_id = DAO_CloudGlue::lookupIndex($index_name);
		if(empty($index_id)) return array();
		
		$sql = sprintf("SELECT tc.tag_id, tc.content_id ".
			"FROM tag_to_content tc ".
			"WHERE tc.content_id IN (%s) ".
			"AND tc.index_id = %d ",
			implode(',',$content_ids),
			$index_id
		);
		$rs = $db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		
		if(is_a($rs,'ADORecordSet'))
		while(!$rs->EOF) {
			$content_id = intval($rs->fields['content_id']);
			$tag_id = intval($rs->fields['tag_id']);
			if(!isset($ids[$content_id])) $ids[$content_id] = array();
			$ids[$content_id][] = $tag_id;
			$rs->MoveNext();
		}

		if(empty($ids))
			return array();
		
		$content_tags = array();
		foreach($ids as $content_id => $tags) {
			$content_tags[$content_id] = DAO_CloudGlue::getTags($tags);
		}
			
		return $content_tags;
	}
	
	/**
	 * @param array $ids
	 * @return CloudGlueTag[]
	 */
	static function getTags($ids=array()) {
		if(!is_array($ids)) $ids = array($ids);

		$db = DevblocksPlatform::getDatabaseService();

		$sql = "SELECT t.id, t.name ".
			"FROM tag t ".
			(!empty($ids) ? sprintf("WHERE t.id IN (%s) ",implode(',', $ids)) : "").
			"ORDER BY t.name "
			;
		$rs = $db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */

		return self::_getObjectsFromResultSet($rs);
	}
	
	static function getTagsWhere($where = null) {
		$db = DevblocksPlatform::getDatabaseService();

		$sql = "SELECT t.id, t.name ".
			"FROM tag t ".
			(!empty($where) ? sprintf("WHERE %s ",$where) : " ").
			"ORDER BY t.name "
			;
		$rs = $db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		
		return self::_getObjectsFromResultSet($rs);
	}
	
	private static function _getObjectsFromResultSet(ADORecordSet $rs) {
		$objects = array();
		
		if(is_a($rs,'ADORecordSet'))
		while(!$rs->EOF) {
			$tag = new CloudGlueTag();
			$tag->id = intval($rs->fields['id']);
			$tag->name = $rs->fields['name'];
			$objects[$tag->id] = $tag;
			$rs->MoveNext();
		}
		
		return $objects;
	}
	
	/**
	 * @param string $index_name
	 * @param boolean $create_if_new
	 * @return integer id 
	 */
	static function lookupIndex($index_name,$create_if_new=false) {
		if(empty($index_name)) return NULL;
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("SELECT ti.id FROM tag_index ti WHERE ti.name = %s",
			$db->qstr(strtolower($index_name))
		);
		$id = $db->GetOne($sql);
		
		if(empty($id) && $create_if_new) {
			$id = $db->GenID('generic_seq');
			$sql = sprintf("INSERT INTO tag_index (id,name) VALUES (%d,%s)",
				$id,
				$db->qstr(strtolower($index_name))
			);
			$db->Execute($sql);
			
		} else if(empty($id)) {
			return NULL;
		}
		
		return $id;
	}
	
	/**
	 * @param string $tag_name
	 * @param boolean $create_if_new
	 * @return integer id 
	 */
	static function lookupTag($tag_name,$create_if_new=false) {
		if(empty($tag_name)) return NULL;
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("SELECT t.id FROM tag t WHERE t.name = %s",
			$db->qstr(strtolower($tag_name))
		);
		$id = $db->GetOne($sql);
		
		if(empty($id) && $create_if_new) {
			$id = $db->GenID('tag_seq');
			$sql = sprintf("INSERT INTO tag (id,name) VALUES (%d,%s)",
				$id,
				$db->qstr(strtolower($tag_name))
			);
			$db->Execute($sql);
			
		} else if(empty($id)) {
			return NULL;
		}
		
		return self::getTag($id);
	}
	
};
?>