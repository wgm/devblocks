<?php
class CloudGlueTag {
	public $id = 0;
	public $name = null;
	public function __construct($id, $name) {
		$this->id = $id;
		$this->name = $name;
	}
}

class CloudGlueTagWeight {
	public $id = 0;
	public $weight = 0;
	
	public function __construct($id, $weight) {
		$this->id = $id;
		$this->weight = $weight;
	}
	
}

class CloudGlueTagAssignCount {
	public $tagId = 0;
	public $count = 0;
}

?>