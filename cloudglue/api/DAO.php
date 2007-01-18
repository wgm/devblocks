<?php
/**
 * @author Jeff Standen <jeff@webgroupmedia.com>
 */
class DAO_CloudGlue {
	
	/**
	 * @param array $tags
	 * @param integer $content_id
	 * @param string $index_name
	 */
	static function applyTags($tags,$content_id,$index_name) {

		$db = DevblocksPlatform::getDatabaseService();
		$index_id = self::lookupIndex($index_name);
		$tag_ids = array();
		
		if(empty($index_id) || empty($content_id)) {
			return NULL;
		}
		
		foreach($tags as $tag) {
			$tag_id = self::lookupTag($tag,true);
			$db->Replace(
				'tag_to_content', 
				array('index_id'=>$index_id,'tag_id'=>$tag_id, 'content_id'=>$content_id), 
				array('index_id','tag_id', 'content_id')
			);
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
			for($x=1;$x<=count($including_tags);$x++) {
				$tag =& $including_tags[$x-1]; /* @var $tag CloudGlueTag */
				if(empty($tag->id)) continue;
				
				$exclude_ids[] = $tag->id;
				
				$joins[] = sprintf('INNER JOIN tag_to_content tc%1$d '.
					'ON (tc%1$d.content_id=tc%2$d.content_id AND tc%1$d.tag_id=%3$d) ',
					$x,
					$x-1,
					$tag->id
				);
			}
		}
		
		$sql = sprintf("SELECT count(*) as hits, tc0.tag_id ". 
			"FROM tag_to_content tc0 ".
			(!empty($joins) ? implode(' ', $joins) : "").
			"WHERE tc0.index_id = %d ".
			(!empty($exclude_ids) ? sprintf("AND tc0.tag_id NOT IN (%s) ",implode(',', $exclude_ids)) : "").
			"GROUP BY tc0.tag_id ".
			"ORDER BY hits DESC ".
			"LIMIT 0,30",
			$index_id
		);
//		echo $sql;
		$rs = $db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		
		while(!$rs->EOF) {
			$id = intval($rs->fields['tag_id']);
			$hits[$id] = intval($rs->fields['hits']);
			$rs->MoveNext();
		}
		
		if(empty($hits))
			return array();
			
		return array(DAO_CloudGlue::getTags(array_keys($hits)),$hits);
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
	 * @param array $ids
	 * @return CloudGlueTag[]
	 */
	static function getTags($ids=array()) {
		if(!is_array($ids)) $ids = array($ids);

		$db = DevblocksPlatform::getDatabaseService();
		$tags = array();

		$sql = "SELECT t.id, t.name ".
			"FROM tag t ".
			(!empty($ids) ? sprintf("WHERE t.id IN (%s) ",implode(',', $ids)) : "").
			"ORDER BY t.name "
			;
		$rs = $db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		
		while(!$rs->EOF) {
			$tag = new CloudGlueTag();
			$tag->id = intval($rs->fields['id']);
			$tag->name = $rs->fields['name'];
			$tags[$tag->id] = $tag;
			$rs->MoveNext();
		}
		
		return $tags;
	}
	
	/**
	 * @param string $index_name
	 * @param boolean $create_if_new
	 * @return integer id 
	 */
	static function lookupIndex($index_name,$create_if_new=false) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("SELECT ti.id FROM tag_index ti WHERE ti.name = %s",
			$db->qstr($index_name)
		);
		$id = $db->GetOne($sql);
		
		if(empty($id) && $create_if_new) {
			$id = $db->GenID('generic_seq');
			$sql = sprintf("INSERT INTO tag_index (id,name) VALUES (%d,%s)",
				$id,
				$db->qstr($index_name)
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
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("SELECT t.id FROM tag t WHERE t.name = %s",
			$db->qstr($tag_name)
		);
		$id = $db->GetOne($sql);
		
		if(empty($id) && $create_if_new) {
			$id = $db->GenID('tag_seq');
			$sql = sprintf("INSERT INTO tag (id,name) VALUES (%d,%s)",
				$id,
				$db->qstr($tag_name)
			);
			$db->Execute($sql);
			
		} else if(empty($id)) {
			return NULL;
		}
		
		return $id;
	}
	
};
?>