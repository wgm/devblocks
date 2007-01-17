<?php
class CloudGlue {
	
	private $clouds;
	private $cgDao;
	
	private static $instance = null;
	
	private function __construct() {}
	
	static function getInstance() {
		if(self::$instance==null) {
			self::$instance = new CloudGlue();
		}
		
		return self::$instance;
	}
	
	function createTagGroup($cloudId) {
		return new TagGroup($cloudId, true);
	}
	
	public function getTagGroup($cloudId) {
		return new TagGroup($cloudId);
	}
}

class TagGroup {
	
	private $tags;
	private $cgDao;
	private $cloud;
	
	/**
	 * @param cloudId The unique string identifier for this tag group
	 * @param doInit Boolean that indicates whether the tag group tables should be initialized
	 */
	public function __construct($cloudId, $doInit=false) {
		$this->cgDao = new CloudGlueDao($cloudId);
		if($doInit) {
			$this->initTables();
		}
	}
	
	public function initTables() {
		$this->cgDao->initCloudGlueTables();	
	}
	
	public function createTag($name) {
		$this->cgDao->createTag($name);
	}
	
	public function assignTag($tagId, $contentId) {
		$this->cgDao->assignTag($tagId, $contentId);
	}
	
	public function getTags($tagIds=array()) {
		$this->tags = $this->cgDao->getTags($tagIds);
		return $this->tags;
	}
	
	public function isTagContentExist($tagId, $contentId) {
		return $this->cgDao->isTagContentExist($tagId, $contentId);
	}
	
	public function getCloud() {
		if($this->cloud == NULL) {
			if($this->tags == NULL) {
				$this->tags = $this->getTags();
			}
			$this->cloud = new CloudGlueCloud($this->tags, $this->cgDao);
		}
		return $this->cloud;
	}
}

class CloudGlueCloud {
	
	private $cgDao;
	
	private $relatedTags;
	
	private $tags;
	private $tagCounts;
	private $tagWeights;
	
	private $tagLimit;
	
	private $minWeight;
	private $maxWeight;
	
	private $maxFrequency;
	private $minFrequency;
	
	private $weightsCalculated;//boolean tells whether assignCloudWeights() has been called yet
	
	/**
	 * @param tags An array of tags
	 * @param cgDao the DAO for the tag group
	 */
	//public function __construct($tags, $tablename, $col_tag_id, $col_content_id) {
	public function __construct($tags, $cgDao) {		
		$this->cgDao = $cgDao;
		
		$this->weightsCalculated = false;
		$this->minWeight = 14;
		$this->maxWeight = 56;
		$this->tagLimit = -1;
		$this->tags = $tags;
		
	}
	
	/**
	 * Draws the tag cloud.  Should be called from a template.
	 * @author Jeff Standen
	 */
	public function render() {
		$path = dirname(__FILE__). '/templates/';
		
		$tpl = DevblocksPlatform::getTemplateService();
		$tpl->assign('path', $path);
		
		$cloudglue = DevblocksPlatform::getCloudGlueService();
		$tagGroup = $cloudglue->getTagGroup("mygroup");
		$tags = $tagGroup->getTags();
		$tpl->assign('tags', $tags);
		
		$tpl->assign('tagCloud', $this);
		
		$tpl->display('file:' . $path . 'cloud.tpl.php');
	}
	
	public function setRelatedTags($tagIds) {
		$this->relatedTags = $tagIds;
	}
	
	/**
	 * Allows the max frequency to be set, so when weights are calculated,
	 * it will use an alternative max than what it actually finds in the data
	 * (useful if we want it to use a maximum from a previous execution thus yielding smaller weights)
	 */
	public function setMaxFrequency($maxFrequency) {
		$this->maxFrequency = $maxFrequency;
	}
	
	public function setMaxWeight($maxWeight) {
		$this->maxWeight = $maxWeight;
	}
	
	public function setMinWeight($minWeight) {
		$this->minWeight = $minWeight;
	}
	
	/**
	 * Set the maximum number of tags that will be returned
	 */
	public function setTagLimit($limit) {
		$this->tagLimit = $limit;
	}
	
	private function getContentCounts() {
		if($this->relatedTags === NULL) {
			$this->tagCounts = $this->cgDao->getTagContentCounts($this->tagLimit);
		}
		else {
			$this->tagCounts = $this->cgDao->getRelatedTagContentCounts($this->relatedTags, $this->tagLimit);
		}
	}
	
	/**
	 * sets the weight instance variable for each tag in $this->tagWeights
	 */
	private function assignCloudWeights() {
		
		//if($this->maxFrequency == NULL || $this->minFrequency == NULL) {
		$this->findMinMaxFrequencies();
		//}
		$min = $this->minFrequency;
		$max = $this->maxFrequency;
		
		//echo "$max $min <br>";

		if($min == $max) {
			//avoid divide by zero calculation when all frequencies are the same
			$this->setWeightsEqual();
		}
		else {
			foreach($this->tagCounts as $tagId=>$countObj) {
				$frequency = $countObj->count;
				
				//figure out what percent frequency is of the max frequency
				//echo sprintf("round(((%d - %d) / (%d - %d)) * 100)<br>", $frequency, $min, $max, $min);
				$frequencyPercent = round((($frequency - $min) / ($max - $min)) * 100);
				//echo "frequency% ::".$frequencyPercent.'<br>';
				$weight = round(($frequencyPercent * ($this->maxWeight-$this->minWeight)) / 100) + $this->minWeight;
				//echo "$tagId, $weight<br>";
				$this->tagWeights[$tagId] = new CloudGlueTagWeight($tagId, $weight);
			}
		}
		
	}
	
	/**
	 * sets the maximum and minimum frequencies found in the TagCounts object
	 */
	private function findMinMaxFrequencies() {
		$max = 0;
		$min = PHP_INT_MAX;
		foreach($this->tagCounts as $tagId=>$countObj) {
			$frequency = $countObj->count;
			//echo 'freq: '.$frequency.'<br>';			
			if($frequency > $max) {
				$max = $frequency;
			}
			if($frequency < $min) {
				$min = $frequency;
			}
		}
		if($this->maxFrequency == NULL) {
			$this->maxFrequency = $max;
		}
		$this->minFrequency = $min;
	}
	
	/**
	 * Sets all weights to the same mid range value.
	 * This is used when our normal calculation would result in a divide by zero (i.e. when max frequency = min frequency)
	 */
	private function setWeightsEqual() {
		foreach($this->tagCounts as $tagId=>$countObj) {
			$this->tags[$tagId]->weight = round($this->maxWeight-$this->minWeight /2);
		}
	}
	
	public function getWeightedTags() {
		if(!$this->weightsCalculated) {
			$this->getContentCounts();
			$this->assignCloudWeights();
			$this->weightsCalculated = true;
		}
		
		return $this->tagWeights;
	}
	
	public function getRelatedTags() {
		return $this->relatedTags;
	}
	
	public function getMaxFrequency() {
		return $this->maxFrequency;
	}
	
	public function getMinFrequency() {
		return $this->minFrequency;
	}
}
?>