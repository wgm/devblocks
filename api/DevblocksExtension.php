<?php
abstract class DevblocksModuleExtension extends CgExtension {
	function __construct($manifest) {
		parent::CgExtension($manifest,1);
	}
	
	/**
	 * Enter description here...
	 *
	 * @return boolean
	 */
	function isVisible() {
		return true;
	}
	
	function click() {
		DevblocksApplication::setActiveModule($this->id);
	}
	
	/**
	 * Enter description here...
	 *
	 */
	function render() {
		
	}
}

?>