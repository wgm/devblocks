<?php
class DbProjectsModule extends DevblocksModuleExtension {
	function __construct($manifest) {
		parent::__construct($manifest,1);
	}

	function render() {
		$tpl = CgPlatform::getTemplateService();
		$tpl->assign('path', dirname(__FILE__) . '/templates/');
		
		$tpl->cache_lifetime = "0";
		$tpl->display('file:' . dirname(__FILE__) . '/templates/modules/projects/index.tpl.php');
	}
}

class DbBountiesModule extends DevblocksModuleExtension {
	function __construct($manifest) {
		parent::__construct($manifest,1);
	}

	function render() {
		$tpl = CgPlatform::getTemplateService();
		$tpl->assign('path', dirname(__FILE__) . '/templates/');
		
		$bounties = DAO_Bounty::getBounties();
		$tpl->assign('bounties', $bounties);
		
		$votes = DAO_Bounty::getVoteHistory(1, array_keys($bounties));
		$tpl->assign('votes', $votes);
		
		$tpl->cache_lifetime = "0";
		$tpl->display('file:' . dirname(__FILE__) . '/templates/modules/bounties/index.tpl.php');
	}
	
	function voteUp() {
		@$id = $_REQUEST['id'];
		$member_id = 1; // [TODO] Pull from session later
		
		DAO_Bounty::voteUp($id,$member_id);
		
		echo ' ';
	}
	
	function voteDown() {
		@$id = $_REQUEST['id'];
		$member_id = 1; // [TODO] Pull from session later
		
		DAO_Bounty::voteDown($id,$member_id);
		
		echo ' ';
	}
	
	function refreshVotes() {
		@$id = $_REQUEST['id'];
		
		$tpl = CgPlatform::getTemplateService();
		$tpl->assign('path', dirname(__FILE__) . '/templates/');
		
		$bounty = DAO_Bounty::getBounty($id);
		$tpl->assign('bounty', $bounty);
		
		$votes = DAO_Bounty::getVoteHistory(1, array($id));
		$tpl->assign('voted',@$votes[$id]);
		
		$tpl->cache_lifetime = "0";
		$tpl->display('file:' . dirname(__FILE__) . '/templates/modules/bounties/votes.tpl.php');
	}
}

class DbRoadmapModule extends DevblocksModuleExtension {
	function __construct($manifest) {
		parent::__construct($manifest,1);
	}

	function render() {
		$tpl = CgPlatform::getTemplateService();
		$tpl->assign('path', dirname(__FILE__) . '/templates/');
		
		$tpl->cache_lifetime = "0";
		$tpl->display('file:' . dirname(__FILE__) . '/templates/modules/roadmap/index.tpl.php');
	}
}

class DbConfigurationModule extends DevblocksModuleExtension {
	function __construct($manifest) {
		parent::__construct($manifest,1);
	}

	function render() {
		$tpl = CgPlatform::getTemplateService();
		$tpl->assign('path', dirname(__FILE__) . '/templates/');
		
		$tpl->cache_lifetime = "0";
		$tpl->display('file:' . dirname(__FILE__) . '/templates/modules/config/index.tpl.php');
	}
	
	function saveBounty() {
		@$id = $_REQUEST['id'];
		@$name = $_REQUEST['name'];
		@$estimate = $_REQUEST['estimate'];
		
		$newId = DAO_Bounty::createBounty($name, $estimate);
		
		DevblocksApplication::setActiveModule($this->id);
	}
	
	function massBountyEntry() {
		@$entry = $_REQUEST['entry'];
		
		$lines = split("[\r\n]", $entry);
		if(is_array($lines))
		foreach($lines as $line) {
			if(empty($line)) continue;
			DAO_Bounty::createBounty($line,0);
		}
		
		/*
		 * [JAS]: [TODO] This should probably send to another screen to let the
		 * developer elaborate on each quick-created bounty without having to 
		 * go search for them.
		 */
	
		DevblocksApplication::setActiveModule($this->id);
	}
}
?>