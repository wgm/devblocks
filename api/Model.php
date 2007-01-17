<?php
class DevblocksExtensionPoint {
	var $id = '';
	var $extensions = array();
}

/**
 * Manifest information for plugin.
 * @ingroup plugin
 */
class DevblocksPluginManifest {
	var $id = '';
	var $enabled = 0;
	var $name = '';
	var $author = '';
	var $dir = '';
	var $extensions = array();
	
	function setEnabled($bool) {
		$this->enabled = ($bool) ? 1 : 0;
		
		// [JAS]: Persist to DB
		$fields = array(
			'enabled' => $this->enabled
		);
		DevblocksDAO::updatePlugin($this->id,$fields);
	}
};

/**
 * Manifest information for a plugin's extension.
 * @ingroup plugin
 */
class DevblocksExtensionManifest {
	var $id = '';
	var $plugin_id ='';
	var $point = '';
	var $name = '';
	var $file = '';
	var $class = '';
	var $params = array();

	function DevblocksExtensionManifest() {}
	
	/**
	 * Creates and loads a usable extension from a manifest record.  The object returned 
	 * will be of type $class defined by the manifest.  $instance_id is passed as an 
	 * argument to uniquely identify multiple instances of an extension.
	 *
	 * @param integer $instance_id
	 * @return object
	 */
	function createInstance($instance_id=1) {
		if(empty($this->id) || empty($this->plugin_id)) // empty($instance_id) || 
			return null;

//		$plugins = DevblocksPlatform::getPluginRegistry();
//		
//		if(!isset($plugins[$this->plugin_id]))
//			return null;
//		
//		$plugin = $plugins[$this->plugin_id]; /* @var $plugin DevblocksPluginManifest */

		$plugin = DevblocksPlatform::getPlugin($this->plugin_id);
			
		$class_file = DEVBLOCKS_PLUGIN_PATH . $plugin->dir . '/' . $this->file;
		$class_name = $this->class;

		if(!file_exists($class_file))
			return null;
			
		include_once($class_file);
		if(!class_exists($class_name)) {
			return null;
		}
			
		$instance = new $class_name($this,$instance_id);
		return $instance;
	}
};

/**
 * A single session instance
 *
 * @ingroup core
 */
class DevblocksSession {
	var $id = 0;
	var $login = '';
	var $admin = 0;
	
	/**
	 * Returns TRUE if the current session has administrative privileges, or FALSE otherwise.
	 *
	 * @return boolean
	 */
	function isAdmin() {
		return $this->admin != 0;
	}
};

?>