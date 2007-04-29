<?php
class CloudGlueConfiguration {
	public $tagLimit = 30;
	public $minWeight = 16;
	public $maxWeight = 55;
	public $indexName = '';
	public $divName = '';
	public $extension = '';
	public $php_click = '';
	public $js_click = '';
};

class CloudGlueIndex {
	public $id = 0;
	public $name = "";
};

class CloudGlueTag {
	public $id = 0;
	public $name = "";
	
	function __construct($id=null,$name=null) {
		if(empty($id)) return;
		
		$this->id = intval($id);
		$this->name = $name;
	}
};

?>