<?php
class CloudGlue {
	private static $instance = null;
	
	private function __construct() {}
	
	static function getInstance() {
		if(self::$instance==null) {
			self::$instance = new CloudGlue();
		}
		
		return self::$instance;
	}
	
	function createCloud($tags, $tablename, $col_tag_id, $col_content_id) {
		return new CloudGlueCloud($tags, $tablename, $col_tag_id, $col_content_id);
	}
}

class CloudGlueCloud {
	
	private $cgDao;
	
	private $tagsToRelateTo;
	
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
	 * @param relatedToTagId An optional array of tags to get a related tag cloud for. Default gets cloud of all tags.
	 * @param maxFreq The max frequency can be specified, otherwise it is determined based on the frequencies it finds when it get tag counts
	 */
	public function __construct($tags, $tablename, $col_tag_id, $col_content_id) {
		$this->cgDao = new CloudGlueDao($tablename, $col_tag_id, $col_content_id);
		
		$this->weightsCalculated = false;
		$this->minWeight = 14;
		$this->maxWeight = 56;
		$this->tagLimit = -1;
		$this->tags = $tags;
		
	}
	
	public function setTagsToRelateTo($tagIds) {
		$this->tagsToRelateTo = $tagIds;
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
		if($this->tagsToRelateTo === NULL) {
			$this->tagCounts = $this->cgDao->getTagContentCounts($this->tagLimit);
		}
		else {
			$this->tagCounts = $this->cgDao->getRelatedTagContentCounts($this->tagsToRelateTo, $this->tagLimit);
		}
	}
	
	/**
	 * sets the weight instance variable for each tag in $this->tags
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
				$this->tagWeights[$tagId] = new CloudGlueTag($tagId, $weight);
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
	
	public function getMaxFrequency() {
		return $this->maxFrequency;
	}
	
	public function getMinFrequency() {
		return $this->minFrequency;
	}
}
?>