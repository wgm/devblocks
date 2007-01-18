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
	
	function createTagGroup($tagGroupId) {
		return new TagGroup($tagGroupId, true);
	}
	
	public function getTagGroup($tagGroupId) {
		return new TagGroup($tagGroupId);
	}
}

class TagGroup {
	
	private $tags;
	private $cgDao;
	private $tagGroupId;
	private $cloud;
	
	/**
	 * @param tagGroupId The unique string identifier for this tag group
	 * @param doInit Boolean that indicates whether the tag group tables should be initialized
	 */
	public function __construct($tagGroupId, $doInit=false) {
		$this->cgDao = new CloudGlueDao($tagGroupId);
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
	
	public function hasContent($tagId, $contentId) {
		return $this->cgDao->hasContent($tagId, $contentId);
	}
	
	public function getCloud() {
		if($this->cloud == NULL) {
			$this->cloud = new CloudGlueCloud($this->cgDao, $this->tagGroupId);
		}
		return $this->cloud;
	}
}

class CloudGlueCloud {
	
	private $tagGroupId;
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
	 * @param cgDao the DAO for the tag group
	 */
	//public function __construct($tags, $tablename, $col_tag_id, $col_content_id) {
	public function __construct($cgDao, $tagGroupId) {		
		$this->cgDao = $cgDao;
		$this->tagGroupId = $tagGroupId;
		
		$this->weightsCalculated = false;
		$this->minWeight = 14;
		$this->maxWeight = 56;
		$this->tagLimit = -1;
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
			$this->tagWeights[$tagId] = new CloudGlueTagWeight($tagId, round(($this->maxWeight-$this->minWeight) /2));
		}
	}
	
	public function getWeightedTags() {
		if(!$this->weightsCalculated) {
			$this->getContentCounts();
			
			$tempTags = array_keys($this->tagCounts);
			if($this->relatedTags != NULL)
				foreach($this->relatedTags AS $tagId) {
					$tempTags[] = $tagId; 
				}
			
			$this->tags = $this->cgDao->getTags($tempTags);
			
			$this->assignCloudWeights($tagWeights);
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
	
	/**
	 * Returns all tag objects used by this object, including related tags
	 */
	public function getAllTags() {
		return $this->tags;
	}
	
	/**
	 * Returns all tags objects displayed in the cloud
	 * This result will not include tags that were inputted as 'related'
	 */
	public function getCloudTags() {
		$ctags = $this->tags;
		if($this->relatedTags != NULL)
			foreach($this->relatedTags AS $tagId) {
				unset($ctags[$tagId]);
			}
		array_merge($ctags);
		return $ctags;		
	}
	
	public function getTagGroupId() {
		return $this->tagGroupId;
	}
}

class CloudGlueRenderer {
	
	private $cloud;
	private $templateService;
	
	public function CloudGlueRenderer($templateService, $cloud){
		$this->templateService = $templateService;
		$this->cloud = $cloud;
	}
	
	/**
	 * Draws the tag cloud.  Should be called from a template.
	 * @author Jeff Standen
	 */
	public function render() {
		$path = dirname(__FILE__). '/templates/';

		$this->templateService->assign('path', $path);
		$this->templateService->assign('tagCloud', $this->cloud);
		$this->templateService->display('file:' . $path . 'cloud.tpl.php');
		
	}
}
?>