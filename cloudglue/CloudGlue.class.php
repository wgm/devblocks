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
	
	function createCloud($tagIdsToRelate=NULL,$maxFreq=NULL) {
		return new CloudGlueCloud($tagIdsToRelate, $maxFreq);
	}
}

class CloudGlueCloud {
	
	private $tags;
	private $tagCounts;
	
	private $MIN_FONT = 11;
	private $MAX_FONT = 56;
	
	private $maxFrequency;
	private $minFrequency;
	
	/**
	 * @param relatedToTagId An optional array of tags to get a related tag cloud for. Default gets cloud of all tags.
	 * @param maxFreq The max frequency can be specified, otherwise it is determined based on the frequencies it finds when it get tag counts
	 */
	public function __construct($relatedToTagId=NULL, $maxFreq=NULL) {
		$this->tags = DAO_Tag::getTags();
		
		$this->maxFrequency = $maxFreq;
		
		if($relatedToTagId === NULL) {
			$this->tagCounts = DAO_Tag::getTagContentCounts();
		}
		else {
			$this->tagCounts = DAO_Tag::getRelatedTagContentCounts($relatedToTagId);
		}
		
		//print_r($this->tagCounts);exit();
		$this->assignCloudWeights();
		
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
				$weight = round(($frequencyPercent * ($this->MAX_FONT-$this->MIN_FONT)) / 100) + 11;
				
				$this->tags[$tagId]->weight = $weight;
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
			$this->tags[$tagId]->weight = round($this->MAX_FONT-$this->MIN_FONT /2);
		}
	}
	
	public function getWeightedTags() {
		return $this->tags;
	}
	
	public function getMaxFrequency() {
		return $this->maxFrequency;
	}
	
	public function getMinFrequency() {
		return $this->minFrequency;
	}
}
?>