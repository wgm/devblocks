<?php
class DAO_Bounty {
	static function createBounty($title,$estimate) {
		if(empty($title)) return null;
		
		$db = CgPlatform::getDatabaseService();
		$id = $db->GenID('bounty_seq');
		
		$sql = sprintf("INSERT INTO bounty (id,title,estimate,votes) ".
			"VALUES (%d,%s,%d,0)",
			$id,
			$db->QMagic($title),
			$estimate
		);
		$db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		
		return $id;
	}
	
	static function getBounty($id) {
		$bounties = DAO_Bounty::getBounties(array($id));
		
		if(isset($bounties[$id]))
			return $bounties[$id];
			
		return null;
	}
	
	static function getBounties($ids=array()) {
		if(!is_array($ids)) $ids = array($ids);
		
		$db = CgPlatform::getDatabaseService();
		$bounties = array();
		
		$sql = "SELECT b.id, b.title, b.estimate, b.votes ".
			"FROM bounty b ".
			(!empty($ids) ? sprintf("WHERE b.id IN (%s) ",implode(',', $ids)) : " ").
			"ORDER BY b.id DESC "
		;
		$rs = $db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		
		while(!$rs->EOF) {
			$id = intval($rs->fields['id']);
			$bounty = new DbBounty();
			$bounty->id = $id;
			$bounty->title = $rs->fields['title'];
			$bounty->estimate = floatval($rs->fields['estimate']);
			$bounty->votes = intval($rs->fields['votes']);			
			$bounties[$id] = $bounty;
			$rs->MoveNext();
		}
		
		return $bounties;
	}
	
	static function updateBounty($id, $fields) {
		
	}
	
	static function deleteBounty($id) {
		$db = CgPlatform::getDatabaseService();
		
		$sql = sprintf("DELETE FROM bounty WHERE id = %d",
			$id
		);
		$db->Execute($sql) or die(__CLASS__ . ':' . $um_db->ErrorMsg()); /* @var $rs ADORecordSet */
	}
	
	static function voteUp($id,$member_id) {
		self::_voteBounty($id,$member_id,1);
	}
	
	static function voteDown($id,$member_id) {
		self::_voteBounty($id,$member_id,-1);	
	}
	
	static private function _voteBounty($id,$member_id,$vote) {
		$db = CgPlatform::getDatabaseService();
		$db->Replace('bounty_vote',array('bounty_id'=>$id,'member_id'=>$member_id,'vote'=>$vote),array('bounty_id','member_id'));

		// [JAS]: Cache bounty vote count in the bounty table
		$count = $db->GetOne(sprintf("SELECT sum(vote) FROM bounty_vote WHERE bounty_id = %d",
			$id
		));
		$db->Execute(sprintf("UPDATE bounty SET votes = %d WHERE id = %d",
			$count,
			$id
		));
	}

	/**
	 * 
	 * @param integer $member_id
	 * @param array $bounty_ids 
	 */
	static function getVoteHistory($member_id,$bounty_ids) {
		if(!is_array($bounty_ids)) $bounty_ids = array($bounty_ids);
		
		$db = CgPlatform::getDatabaseService();
		$votes = array();
		
		$sql = sprintf("SELECT bv.vote, bv.bounty_id ".
			"FROM bounty_vote bv ".
			"WHERE bv.member_id = %d ".
			"AND bv.bounty_id IN (%s) ",
			$member_id,
			implode(',', $bounty_ids)
		);
		$rs = $db->Execute($sql) or die(__CLASS__ . ':' . $um_db->ErrorMsg()); /* @var $rs ADORecordSet */
		
		while(!$rs->EOF) {
			$voted = intval($rs->fields['vote']);
			$bounty_id = intval($rs->fields['bounty_id']);
			$votes[$bounty_id] = $voted;		
			$rs->MoveNext();
		}
		
		return $votes;		
	}
};
?>