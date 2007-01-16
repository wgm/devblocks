<?php
class CloudGlueTag {
	public $id = 0;
	public $name = null;
	public $weight = 0;
	
	public function __construct($id, $weight) {
		$this->id = $id;
		$this->weight = $weight;
	}
	
}

class CloudGlueTagContentCounts {
	public $tagId = 0;
	public $count = 0;
}

?>