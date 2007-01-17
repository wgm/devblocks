<?php
include_once(DEVBLOCKS_PATH . "pear/i18n/I18N_UnicodeString.php");
include_once(APP_PATH . "/languages/".DEVBLOCKS_LANGUAGE."/strings.php");

include_once(DEVBLOCKS_PATH . "api/Engine.php");
include_once(DEVBLOCKS_PATH . "api/DAO.php");
include_once(DEVBLOCKS_PATH . "api/Model.php");
include_once(DEVBLOCKS_PATH . "api/Extension.php");

define('PLATFORM_BUILD',20);

/**
 *  @defgroup core Devblocks Framework Core
 *  Core data structures of the framework
 */

/**
 *  @defgroup plugin Devblocks Framework Plugins
 *  Components for plugin/extensions
 */

/**
 *  @defgroup services Devblocks Framework Services
 *  Services provided by the framework
 */

/**
 * A platform container for plugin/extension registries.
 *
 * @static 
 * @ingroup core
 * @author Jeff Standen <jeff@webgroupmedia.com>
 */
class DevblocksPlatform extends DevblocksEngine {
	/**
	 * @private
	 */
	private function __construct() {}
	
	static function importGPC($var) {
		if(is_string($var))
			return get_magic_quotes_gpc() ? stripslashes($var) : $var;
		
		return $var;
	}
	
	static function clearCache() {
		self::$plugins_cache = array();
		self::$extensions_cache = array();
		self::$points_cache = array();
		self::$mapping_cache = array();
	}
	
	/**
	 * Returns the list of extensions on a given extension point.
	 *
	 * @static
	 * @param string $point
	 * @return DevblocksExtensionManifest[]
	 */
	static function getExtensions($point) {
		$results = array();
		$extensions = DevblocksPlatform::getExtensionRegistry();
		
		if(is_array($extensions))
		foreach($extensions as $extension) { /* @var $extension DevblocksExtensionManifest */
			if(0 == strcasecmp($extension->point,$point)) {
				$results[] = $extension;
			}
		}
		return $results;
	}
	
	/**
	 * Returns the manifest of a given extension ID.
	 *
	 * @static
	 * @param string $extension_id
	 * @return DevblocksExtensionManifest
	 */
	static function getExtension($extension_id) {
		$result = null;
		$extensions = DevblocksPlatform::getExtensionRegistry();
		
		if(is_array($extensions))
		foreach($extensions as $extension) { /* @var $extension DevblocksExtensionManifest */
			if(0 == strcasecmp($extension->id,$extension_id)) {
				$result = $extension;
			}
		}		
		
		return $result;
	}
	
	static function getExtensionPoints() {
		$points =& self::$points_cache;

		if(!empty($points))
			return $points;
		
		$extensions = DevblocksPlatform::getExtensionRegistry();
		foreach($extensions as $extension) { /* @var $extension DevblocksExtensionManifest */
			$point = $extension->point;
			if(!isset($points[$point])) {
				$p = new DevblocksExtensionPoint();
				$p->id = $point;
				$points[$point] = $p;
			}
			
			$points[$point]->extensions[$extension->id] = $extension;
		}
		
		return $points;
	}
	
	/**
	 * Returns an array of all contributed extension manifests.
	 *
	 * @static 
	 * @return DevblocksExtensionManifest[]
	 */
	static function getExtensionRegistry() {
		$extensions =& self::$extensions_cache;
		
		if(!empty($extensions))
			return $extensions;
		
		$db = DevblocksPlatform::getDatabaseService();
		$plugins = DevblocksPlatform::getPluginRegistry();
		
		$sql = sprintf("SELECT e.id , e.plugin_id, e.point, e.pos, e.name , e.file , e.class, e.params ".
			"FROM extension e ".
			"INNER JOIN plugin p ON (e.plugin_id=p.id) ".
			"WHERE p.enabled = 1 ".
			"ORDER BY e.plugin_id ASC, e.pos ASC"
		);
		$rs = $db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		while(!$rs->EOF) {
			$extension = new DevblocksExtensionManifest();
			$extension->id = $rs->fields['id'];
			$extension->plugin_id = $rs->fields['plugin_id'];
			$extension->point = $rs->fields['point'];
			$extension->name = $rs->fields['name'];
			$extension->file = $rs->fields['file'];
			$extension->class = $rs->fields['class'];
			$extension->params = @unserialize($rs->fields['params']);
			
			if(empty($extension->params))
				$extension->params = array();
			
			@$plugin = $plugins[$extension->plugin_id]; /* @var $plugin DevblocksPluginManifest */
			if(!empty($plugin)) {
				$extensions[$extension->id] = $extension;
			}
			
			$rs->MoveNext();
		}
		
		return $extensions;
	}
	
	/**
	 * Returns an array of all contributed plugin manifests.
	 *
	 * @static
	 * @return DevblocksPluginManifest[]
	 */
	static function getPluginRegistry() {
		$plugins =& self::$plugins_cache;
		
		if(!empty($plugins))
			return $plugins;
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("SELECT p.id , p.enabled , p.name, p.author, p.dir ".
			"FROM plugin p"
		);
		$rs = $db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		while(!$rs->EOF) {
			$plugin = new DevblocksPluginManifest();
			$plugin->id = $rs->fields['id'];
			$plugin->enabled = intval($rs->fields['enabled']);
			$plugin->name = $rs->fields['name'];
			$plugin->author = $rs->fields['author'];
			$plugin->dir = $rs->fields['dir'];
			
			if(file_exists(DEVBLOCKS_PLUGIN_PATH . $plugin->dir)) {
				$plugins[$plugin->id] = $plugin;
			}
			
			$rs->MoveNext();
		}
		
		$extensions = DevblocksPlatform::getExtensionRegistry();
		foreach($extensions as $extension_id => $extension) { /* @var $extension DevblocksExtensionManifest */
			$plugin_id = $extension->plugin_id;
			if(isset($plugin_id)) {
				$plugins[$plugin_id]->extensions[$extension_id] = $extension;
			}
		}
		
		return $plugins;
	}
	
	/**
	 * Enter description here...
	 *
	 * @param string $id
	 * @return DevblocksPluginManifest
	 */
	static function getPlugin($id) {
		$plugins = DevblocksPlatform::getPluginRegistry();
		
		if(isset($plugins[$id]))
			return $plugins[$id];
			
		return null;
	}
	
	static function getMappingRegistry() {
		$maps =& self::$mapping_cache;
		
		if(!empty($maps))
			return $maps;
		
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("SELECT uri,extension_id ".
			"FROM uri"
		);
		$rs = $db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		while(!$rs->EOF) {
			$uri = $rs->fields['uri'];
			$extension_id = $rs->fields['extension_id'];
			$maps[$uri] = $extension_id;
			
			$rs->MoveNext();
		}
		
		return $maps;
	}
	
	/**
	 * Reads and caches manifests from the plugin directory.
	 *
	 * @static 
	 * @return DevblocksPluginManifest[]
	 */
	static function readPlugins() {
		$dir = DEVBLOCKS_PLUGIN_PATH;
		$plugins = array();
		
		if (is_dir($dir)) {
		    if ($dh = opendir($dir)) {
		        while (($file = readdir($dh)) !== false) {
		        	if($file=="." || $file == ".." || 0 == strcasecmp($file,"CVS"))
		        		continue;
		        		
		        	$path = $dir . '/' . $file;
		        	if(is_dir($path) && file_exists($path.'/plugin.xml')) {
		        		$manifest = self::_readPluginManifest($file);
		        		if(null != $manifest) {
//							print_r($manifest);
							$plugins[] = $manifest;
		        		}
		        	}
		        }
		        closedir($dh);
		    }
		}
		
		return $plugins; // [TODO] Move this to the DB
	}
	
	/**
	 * Enter description here...
	 *
	 * @return ADOConnection
	 */
	static function getDatabaseService() {
		return _DevblocksDatabaseManager::getInstance();
	}
	
	/**
	 * Enter description here...
	 *
	 * @return CloudGlue
	 */
	static function getCloudGlueService() {
		include_once(DEVBLOCKS_PATH . "cloudglue/CloudGlue.class.php");
		include_once(DEVBLOCKS_PATH . "cloudglue/CloudGlueDAO.class.php");
		include_once(DEVBLOCKS_PATH . "cloudglue/CloudGlueVO.class.php");
		return CloudGlue::getInstance();
	}
	
	/**
	 * @return _DevblocksUrlManager
	 */
	static function getUrlService() {
		return _DevblocksUrlManager::getInstance();
	}
	
	/**
	 * @return _DevblocksEmailManager
	 */
	static function getMailService() {
		return _DevblocksEmailManager::getInstance();
	}
	
	/**
	 * @return _DevblocksSessionManager
	 */
	static function getSessionService() {
		return _DevblocksSessionManager::getInstance();
	}
	
	/**
	 * @return Smarty
	 */
	static function getTemplateService() {
		return _DevblocksTemplateManager::getInstance();
	}
	
	/**
	 * @return _DevblocksTranslationManager
	 */
	static function getTranslationService() {
		return _DevblocksTranslationManager::getInstance();
	}
	
	/**
	 * Enter description here...
	 *
	 * @return DevblocksHttpRequest
	 */
	static function getHttpRequest() {
		return self::$request;
	}
	
	/**
	 * @param DevblocksHttpRequest $request
	 */
	static function setHttpRequest($request) {
		if(!is_a($request,'DevblocksHttpRequest')) return null;
		self::$request = $request;
	}
	
	/**
	 * Enter description here...
	 *
	 * @return DevblocksHttpRequest
	 */
	static function getHttpResponse() {
		return self::$response;
	}
	
	/**
	 * @param DevblocksHttpResponse $response
	 */
	static function setHttpResponse($response) {
		if(!is_a($response,'DevblocksHttpResponse')) return null;
		self::$response = $response;
	}
	
	/**
	 * Initializes the plugin platform (paths, etc).
	 *
	 * @static 
	 * @return void
	 */
	static function init() {
		// [JAS]: [TODO] Do we need an explicit init() call?
		// [JAS] [MDF]: Automatically determine the relative webpath to Devblocks files
		if(!defined('DEVBLOCKS_WEBPATH')) {
			$php_self = $_SERVER["PHP_SELF"];
			$pos = strrpos($php_self,'/');
			$php_self = substr($php_self,0,$pos) . '/';
			@define('DEVBLOCKS_WEBPATH',$php_self);
		}
	}
	
};

?>
