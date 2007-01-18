<?php
include_once(dirname(__FILE__) . "/api/Model.php");
include_once(dirname(__FILE__) . "/api/DAO.php");

/*
Cloud
Tag
Content Index (Nickname and Tag<->Content)

*/

class CloudGlue {
	static private $instance = null;

	private function __construct() {}
	
	/**
	 * @return CloudGlue
	 */
	static function getInstance() {
		if(self::$instance == NULL) {
			self::$instance = new CloudGlue();
		}
		
		return self::$instance;
	}
	
	/**
	 * @return CloudGlueTag
	 */
	function getTagById($id) {
		return DAO_CloudGlue::getTag($id);
	}
	
	/**
	 * @return CloudGlueCloud
	 */
	function getCloud($cfg) {
		return new CloudGlueCloud($cfg);
		
//		DAO_CloudGlue::lookupTag('cerberus helpdesk',true);
//		DAO_CloudGlue::lookupTag('portsensor',true);
//		DAO_CloudGlue::lookupTag('jeff',true);
//		DAO_CloudGlue::lookupTag('dan',true);
//		DAO_CloudGlue::lookupTag('installing',true);
//		DAO_CloudGlue::lookupTag('cpanel',true);
//		DAO_CloudGlue::lookupTag('linux',true);
		
//		DAO_CloudGlue::lookupIndex('posts',true);

//		DAO_CloudGlue::applyTags(array('colors','jeff'),16,'posts');
//		DAO_CloudGlue::applyTags(array('portsensor','cerberus helpdesk','clartok','jeff'),25,'posts');
//		DAO_CloudGlue::applyTags(array('portsensor','german','jeff'),20,'posts');
	}
	
	/**
	 * Takes a comma-separated value string and returns an array of tokens.
	 *
	 * @param string $string
	 * @return array
	 */
	static function parseCsvString($string) {
		$tokens = explode(',', $string);

		if(!is_array($tokens))
			return array();
		
		foreach($tokens as $k => $v) {
			$tokens[$k] = trim($v);
		}
		
		return $tokens;
	}
	
};

class CloudGlueCloud {
	public $cfg = null;
	
	/**
	 * @param CloudGlueConfiguration $cfg
	 */
	function __construct($cfg) {
		if(empty($cfg->divName) || empty($cfg->args) || empty($cfg->indexName)) {
			echo "Cloud not configured. (".__CLASS__." : Line ".__LINE__.")";
			return;
		}
		
		$this->cfg = $cfg;
	}
	
	function resetPath() {
		$_SESSION[$this->cfg->divName . '_path'] = array();
	}

	/**
	 * @param CloudGlueTag $tag
	 */
	function addToPath($tag) {
		if(!is_a($tag,'cloudgluetag')) return;
		
		$path = $this->getPath();
		$path[$tag->id] = $tag;
		$_SESSION[$this->cfg->divName . '_path'] = $path;
	}
	
	/**
	 * @return CloudGluePath
	 */
	function getPath() {
		$path =& $_SESSION[$this->cfg->divName . '_path'];
		
		if(empty($path))
			$path = array();

		return $path;
	}
	
	function render() {
		$tpl = DevblocksPlatform::getTemplateService();
		$path = dirname(__FILE__) . "/templates";
		$tpl->assign('path', $path);
		
		// [JAS]: [TODO] Add limit from $cfg
		$tags = DAO_CloudGlue::getCloudTags($this->cfg->indexName,$this->getPath());
		$tpl->assign('tagCloud',$this);
		$tpl->assign('tags', $tags[0]);
		$tpl->assign('weights', $this->scaleWeights($tags[1]));
		
//		print_r($this->getPath());
		
		$tpl->display("file:$path/cloud.tpl.php");
		// draw the cloud from a template
	}
	
	private function scaleWeights($weights) {
		$min = PHP_INT_MAX;
		$max = 0;
		$minWeight = $this->cfg->minWeight;
		$maxWeight = $this->cfg->maxWeight;

		if(!is_array($weights)) $weights = array();
		
		// Define min/max
		foreach($weights as $hits) {
			if($hits < $min) $min = $hits;
			if($hits > $max) $max = $hits;
		}
		
		$scaled = array();
		
		if($min == $max) {
			//avoid divide by zero calculation when all frequencies are the same
			foreach($weights as $tagId=>$hits) {
				$scaled[$tagId] = round(($maxWeight-$minWeight) /2);
			}
		} else {
			foreach($weights as $tagId=>$frequency) {
				//figure out what percent frequency is of the max frequency
				$frequencyPercent = round((($frequency - $min) / ($max - $min)) * 100);
				$scaled[$tagId] = round(($frequencyPercent * ($maxWeight-$minWeight)) / 100) + $minWeight; 
			}
		}

		return $scaled;
	}
	
};


?>