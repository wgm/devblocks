<?php

class CloudGlueDao {
	
	private $tagToContentTableName;//(tagToContentTableName)
	private $tagTableName;
	
	private $col_tag_id;
	private $col_content_id;
	private $col_tag_name;
	
	private static $DEFAULT_TAG_ID_COL = 'tag_id';
	private static $DEFAULT_TAG_NAME_COL = 'name';
	private static $DEFAULT_CONTENT_COL = 'content_id';
	
	public function __construct($cloudId) {
		$tagTableName = 'cg_tag_'.$cloudId;
		$tagToContentTableName = 'cg_tag_assignment_'.$cloudId;		

		$this->tagToContentTableName = $tagToContentTableName;
		$this->tagTableName = $tagTableName;
		$this->col_tag_id = self::$DEFAULT_TAG_ID_COL;
		$this->col_tag_name = self::$DEFAULT_TAG_NAME_COL;
		$this->col_content_id = self::$DEFAULT_CONTENT_COL;		
	}
	
	public function initCloudGlueTables() {
		$um_db = DevblocksPlatform::getDatabaseService();
		
		$datadict = NewDataDictionary($um_db); /* @var $datadict ADODB_DataDict */ // ,'mysql' 
		
		//$tables = DevblocksPlatform::getDatabaseSchema();
		
		$tables[$this->tagTableName] = sprintf("
			id I PRIMARY,
			%s C(255) NOTNULL
		", $this->col_tag_name);

		$tables[$this->tagToContentTableName] = sprintf("
			id I PRIMARY,
			%s I NOTNULL,
			%s I NOTNULL
		", $this->col_tag_id, $this->col_content_id);
		
		foreach($tables as $table => $flds) {
			$sql = $datadict->ChangeTableSQL($table,$flds);
			$datadict->ExecuteSQLArray($sql,false);
		}		
		
	}
	
	public function getTagContentCounts($limit=-1) {
		$counts = array();
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("select tc.%s, count(*) tag_count ".
			"FROM %s tc ".
			" GROUP by tc.%s", $this->col_tag_id, $this->tagToContentTableName, $this->col_tag_id);
		;

		if($limit == -1) {
			$rs = $db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		}
		else {
			$rs = $db->SelectLimit($sql, $limit);
		}		
		while(!$rs->EOF) {
			$tagCount = new CloudGlueTagAssignCount();
			$tagCount->tagId = intval($rs->fields['tag_id']);
			$tagCount->count = $rs->fields['tag_count'];
			$counts[$tagCount->tagId] = $tagCount;
			$rs->MoveNext();
		}
		
		return $counts;
	}	

	/**
	 * 
	 */
	public function getRelatedTagContentCounts($tagIds, $limit=-1) {
		if(!is_array($tagIds)) $ids = array($tagIds);

		$counts = array();
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$select[] = sprintf("tc0.%s tid0 ", $this->col_tag_id);
		$from[] = sprintf("%s tc0 ", $this->tagToContentTableName);
		$group[] = sprintf("tc0.%s ", $this->col_tag_id);
		$where[] = ' 1=1 ';
		$order[] = "tag_count DESC";
		
		for($i=1; $i<=sizeof($tagIds); $i++) {
			if($i==sizeof($tagIds)) {
				$select[] = sprintf(', tc%d.%s tag_id ', $i, $this->col_tag_id); 
			}
			
			$from[] = sprintf('INNER JOIN %s tc%d ON tc%d.%s = tc%d.%s ',
					$this->tagToContentTableName,
					$i,
					($i-1),
					$this->col_content_id,
					$i,
					$this->col_content_id);
			
			$where[] = sprintf('AND tc%d.%s = %d ', $i-1, $this->col_tag_id, $tagIds[$i-1]);
			$where[] = sprintf('AND tc%d.%s <> %d ', sizeof($tagIds), $this->col_tag_id, $tagIds[$i-1]);
			
			$group[] = sprintf(', tc%d.%s ', $i, $this->col_tag_id);
		}
		$select[] = ', count(*) tag_count ';
		
		$sql = sprintf("SELECT %s FROM %s WHERE %s GROUP BY %s ORDER BY %s", 
			implode(' ', $select), 
			implode(' ', $from), 
			implode(' ', $where),
			implode(' ', $group),
			implode(' ', $order));
		//echo $sql;
		if($limit == -1) {
			$rs = $db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		}
		else {
			$rs = $db->SelectLimit($sql, $limit);
		}
		
		while(!$rs->EOF) {
			$tagCount = new CloudGlueTagAssignCount();
			$tagCount->tagId = intval($rs->fields['tag_id']);
			$tagCount->count = $rs->fields['tag_count'];
			$counts[$tagCount->tagId] = $tagCount;
			$rs->MoveNext();
		}
		
		//usort($result_data, array($this, "alphabatizeName"));
		
		return $counts;
	}
	
//	private function alphabatizeName($a, $b) {
//		return strcmp($a->tagId, $b);
//	}
	
	public function getTags($ids=array()) {
		if(!is_array($ids)) $ids = array($ids);

		$tags = array();
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = "SELECT t.id,t.name ".
			sprintf("FROM %s t ", $this->tagTableName).
			((!empty($ids) ? sprintf("WHERE t.id IN (%s)",implode(',', $ids)) : " ").
			"ORDER BY t.name "
		);
		$rs = $db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		
		while(!$rs->EOF) {
			$tag = new CloudGlueTag(intval($rs->fields['id']), $rs->fields[$this->col_tag_name]);
			$tags[$tag->id] = $tag;
			$rs->MoveNext();
		}
		
		return $tags;
	}

	/**
	 * @return integer $id
	 */
	public function createTag($name) {
		$db = DevblocksPlatform::getDatabaseService();

		if(empty($name)) return null;
		$id = $db->GenID($this->tagTableName . '_seq');
		//echo $this->tagTableName;
		$sql = sprintf("INSERT INTO %s (id,%s) ", $this->tagTableName, $this->col_tag_name).
			sprintf("VALUES (%d,%s)",
				$id,
				$db->qstr($name)
			)
		;
		
		//echo $sql . '<br>';
		
		$db->Execute($sql) or die(__CLASS__ . ':' . __FUNCTION__ . ':'. $db->ErrorMsg()); /* @var $rs ADORecordSet */

		return $id;
	}

	public function assignTag($tagId, $contentId) {
		//echo "called assign with ". $tagId . '-'.$contentId . '<br>';
		$db = DevblocksPlatform::getDatabaseService();
		if(!is_numeric($tagId) || !is_numeric($contentId)) return null;
		
		$id = $db->GenID($this->tagToContentTableName . '_seq');
		
		$sql = sprintf("INSERT INTO %s (id,%s,%s) ", 
				$this->tagToContentTableName,
				$this->col_tag_id,
				$this->col_content_id).
				sprintf("VALUES (%d,%d,%d)",
					$id,
					$tagId,
					$contentId
				)
		;
		
		//echo $sql . '<br>';
		
		$db->Execute($sql) or die(__CLASS__ . ':' . __FUNCTION__ . ':'. $db->ErrorMsg()); /* @var $rs ADORecordSet */

		return $id;
	}
	
	public function hasContent($tagId, $contentId) {
		$db = DevblocksPlatform::getDatabaseService();

		if(empty($tagId) || empty($contentId)) return null;
		
		$sql = sprintf("SELECT id FROM %s WHERE %s = %d AND %s = %d",
		$this->tagToContentTableName,
		$this->col_tag_id,
		$tagId,
		$this->col_content_id,
		$contentId);
		
		//echo $sql . '<br>';
		
		$rs = $db->Execute($sql) or die(__CLASS__ . ':' . __FUNCTION__ . ':'. $db->ErrorMsg()); /* @var $rs ADORecordSet */
		//echo 'NUMROWS='.$rs->RecordCount() . '<br>';
		return $rs->NumRows() > 0;
	}
	
}
?>