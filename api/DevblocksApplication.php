<?php
require(CG_PATH . '/api/DevblocksDAO.php');
require(CG_PATH . '/api/DevblocksModel.php');
require(CG_PATH . '/api/DevblocksExtension.php');

class DevblocksApplication {
	static private $module = '';
	
	static function getActiveModule() {
		return self::$module;
	}
	
	static function setActiveModule($module_id) {
		self::$module = $module_id;
	}
	
};

?>