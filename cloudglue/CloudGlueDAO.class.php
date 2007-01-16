<?php

class CloudGlueDao {
	
	private $tablename;
	private $col_tag_id;
	private $col_content_id;
	
	public function __construct($tablename, $col_tag_id, $col_content_id) {
		$this->tablename = $tablename;//tag_content
		$this->col_tag_id = $col_tag_id;//tag_id
		$this->col_content_id = $col_content_id;//content_id
	}
	
	public function getTagContentCounts($limit=-1) {
		$counts = array();
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("select tc.%s, count(*) tag_count ".
			"FROM %s tc ".
			" GROUP by tc.%s", $this->col_tag_id, $this->tablename, $this->col_tag_id);
		;
		//echo $sql;
		if($limit == -1) {
			$rs = $db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		}
		else {
			$rs = $db->SelectLimit($sql, $limit);
		}		
		while(!$rs->EOF) {
			$tagCount = new CloudGlueTagContentCounts();
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
		$from[] = sprintf("%s tc0 ", $this->tablename);
		$group[] = sprintf("tc0.%s ", $this->col_tag_id);
		$where[] = ' 1=1 ';
		$order[] = "tag_count DESC";
		
		for($i=1; $i<=sizeof($tagIds); $i++) {
			if($i==sizeof($tagIds)) {
				$select[] = sprintf(', tc%d.%s tag_id ', $i, $this->col_tag_id); 
			}
			
			$from[] = sprintf('INNER JOIN %s tc%d ON tc%d.%s = tc%d.%s ',
					$this->tablename,
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
			$tagCount = new CloudGlueTagContentCounts();
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
	
}
?>