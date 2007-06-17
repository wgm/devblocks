<?php
include_once(DEVBLOCKS_PATH . "api/Engine.php");
include_once(DEVBLOCKS_PATH . "api/DAO.php");
include_once(DEVBLOCKS_PATH . "api/Model.php");
include_once(DEVBLOCKS_PATH . "api/Extension.php");

include_once(DEVBLOCKS_PATH . "libs/cloudglue/CloudGlue.php");

define('PLATFORM_BUILD',114);

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
 * @ingroup core
 * @author Jeff Standen <jeff@webgroupmedia.com>
 */
class DevblocksPlatform extends DevblocksEngine {
    const CACHE_POINTS = 'devblocks_points';
    const CACHE_PLUGINS = 'devblocks_plugins';
    const CACHE_EXTENSIONS = 'devblocks_extensions';
    const CACHE_TABLES = 'devblocks_tables';
    const CACHE_TRANSLATIONS = 'devblocks_translations';
    const CACHE_EVENT_POINTS = 'devblocks_event_points';
    const CACHE_EVENTS = 'devblocks_events';
    
    private function __construct() {}

	/**
	 * @param mixed $var
	 * @param string $cast
	 * @param mixed $default
	 * @return mixed
	 */
	static function importGPC($var,$cast=null,$default=null) {
	    if(is_string($var))
	    return get_magic_quotes_gpc() ? stripslashes($var) : $var;

	    if(is_null($var) && !is_null($default)) {
	        $var = $default;
	    }
	    	
	    if(!is_null($cast))
	    @settype($var,$cast);

	    return $var;
	}

	/**
	 * Clears any platform-level plugin caches.
	 * 
	 */
	static function clearCache() {
	    $cache = self::getCacheService();
	    $cache->clean();
	}

	public static function loadClass($className) {
		$classloader = self::getClassLoaderService();
		return $classloader->loadClass($className);
	}
	
	public static function registerClasses($file,$classes=array()) {
		$classloader = self::getClassLoaderService();
		return $classloader->registerClasses($file,$classes);
	}
	
	/**
	 * Checks whether the active database has any tables.
	 * 
	 * @return boolean
	 */
	static function isDatabaseEmpty() {
	    $cache = self::getCacheService();
	    
	    if(false === ($tables = $cache->load(self::CACHE_TABLES))) {
	        $db = self::getDatabaseService();
	        if(is_null($db)) return null;
	        $tables = $db->MetaTables('TABLE',false);
	        $cache->save($tables, self::CACHE_TABLES);
	    }
	    return empty($tables);
	}

	/**
	 * Returns the list of extensions on a given extension point.
	 *
	 * @static
	 * @param string $point
	 * @return DevblocksExtensionManifest[]
	 */
	static function getExtensions($point,$as_instances=false) {
	    $results = array();
	    $extensions = DevblocksPlatform::getExtensionRegistry();

	    if(is_array($extensions))
	    foreach($extensions as $extension) { /* @var $extension DevblocksExtensionManifest */
	        if(0 == strcasecmp($extension->point,$point)) {
	            $results[$extension->id] = ($as_instances) ? $extension->createInstance() : $extension;
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
	    $cache = self::getCacheService();
	    if(false !== ($points = $cache->load(self::CACHE_POINTS)))
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

	    $cache->save($points, self::CACHE_POINTS);
	    return $points;
	}

	/**
	 * Returns an array of all contributed extension manifests.
	 *
	 * @static 
	 * @return DevblocksExtensionManifest[]
	 */
	static function getExtensionRegistry() {
	    $cache = self::getCacheService();
	    if(false !== ($extensions = $cache->load(self::CACHE_EXTENSIONS)))
    	    return $extensions;

	    $db = DevblocksPlatform::getDatabaseService();
	    if(is_null($db)) return;

//	    $plugins = DevblocksPlatform::getPluginRegistry();
	    $prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup

	    $sql = sprintf("SELECT e.id , e.plugin_id, e.point, e.pos, e.name , e.file , e.class, e.params ".
			"FROM %sextension e ".
			"INNER JOIN %splugin p ON (e.plugin_id=p.id) ".
			"WHERE p.enabled = 1 ".
			"ORDER BY e.plugin_id ASC, e.pos ASC",
			$prefix,
			$prefix
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
		
//			    @$plugin = $plugins[$extension->plugin_id]; /* @var $plugin DevblocksPluginManifest */
//			    if(!empty($plugin)) {
			        $extensions[$extension->id] = $extension;
//			    }
			    	
			    $rs->MoveNext();
			}

			$cache->save($extensions, self::CACHE_EXTENSIONS);
			return $extensions;
	}

	/**
	 * @return DevblocksEventPoint[]
	 */
	static function getEventPointRegistry() {
	    $cache = self::getCacheService();
	    if(false !== ($events = $cache->load(self::CACHE_EVENT_POINTS)))
    	    return $events;

        $events = array();
        $plugins = self::getPluginRegistry();
    	 
		// [JAS]: Event point hashing/caching
		if(is_array($plugins))
		foreach($plugins as $plugin) { /* @var $plugin DevblocksPluginManifest */
            $events = array_merge($events,$plugin->event_points);
		}
    	
		$cache->save($events, self::CACHE_EVENT_POINTS);
		return $events;
	}
	
	static function getEventRegistry() {
	    $cache = self::getCacheService();
	    if(false !== ($events = $cache->load(self::CACHE_EVENTS)))
    	    return $events;
	    
    	$extensions = self::getExtensions('devblocks.listener.event');
    	$events = array('*');
    	 
		// [JAS]: Event point hashing/caching
		if(is_array($extensions))
		foreach($extensions as $extension) { /* @var $extension DevblocksExtensionManifest */
            @$evts = $extension->params['events'][0];
            
            // Global listeners (every point)
            if(empty($evts) && !is_array($evts)) {
                $events['*'][] = $extension->id;
                continue;
            }
            
            if(is_array($evts))
            foreach(array_keys($evts) as $evt) {
                $events[$evt][] = $extension->id; 
            }
		}
    	
		$cache->save($events, self::CACHE_EVENTS);
		return $events;
	}
	
	/**
	 * Returns an array of all contributed plugin manifests.
	 *
	 * @static
	 * @return DevblocksPluginManifest[]
	 */
	static function getPluginRegistry() {
	    $cache = self::getCacheService();
	    if(false !== ($plugins = $cache->load(self::CACHE_PLUGINS)))
    	    return $plugins;

	    $db = DevblocksPlatform::getDatabaseService();
	    if(is_null($db)) return;
	    $prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup

	    $sql = sprintf("SELECT p.id , p.enabled , p.name, p.description, p.author, p.revision, p.dir ".
			"FROM %splugin p ".
			"ORDER BY p.enabled DESC, p.name ASC ",
			$prefix
		);
		$rs = $db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		while(!$rs->EOF) {
		    $plugin = new DevblocksPluginManifest();
		    $plugin->id = $rs->fields['id'];
		    $plugin->enabled = intval($rs->fields['enabled']);
		    $plugin->name = $rs->fields['name'];
		    $plugin->description = $rs->fields['description'];
		    $plugin->author = $rs->fields['author'];
		    $plugin->revision = intval($rs->fields['revision']);
		    $plugin->dir = $rs->fields['dir'];
	
		    if(file_exists(DEVBLOCKS_PLUGIN_PATH . $plugin->dir)) {
		        $plugins[$plugin->id] = $plugin;
		    }
		    	
		    $rs->MoveNext();
		}

		$sql = sprintf("SELECT p.id, p.name, p.params, p.plugin_id ".
		    "FROM %sevent_point p ",
		    $prefix
		);
		$rs = $db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg() . var_dump(debug_backtrace())); /* @var $rs ADORecordSet */

		while(!$rs->EOF) {
		    $point = new DevblocksEventPoint();
		    $point->id = $rs->fields['id'];
		    $point->name = $rs->fields['name'];
		    $point->plugin_id = $rs->fields['plugin_id'];
		    
		    $params = $rs->fields['params'];
		    $point->params = !empty($params) ? unserialize($params) : array();

		    if(!isset($plugins[$point->plugin_id]))
		        continue;
		    
		    $plugins[$point->plugin_id]->event_points[$point->id] = $point;
		    $rs->MoveNext();
		}
			
//			$extensions = DevblocksPlatform::getExtensionRegistry();
//			foreach($extensions as $extension_id => $extension) { /* @var $extension DevblocksExtensionManifest */
//			    $plugin_id = $extension->plugin_id;
//			    if(isset($plugin_id)) {
//			        $plugins[$plugin_id]->extensions[$extension_id] = $extension;
//			    }
//			}

			$cache->save($plugins, self::CACHE_PLUGINS);
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

	/**
	 * Reads and caches manifests from the plugin directory.
	 *
	 * @static 
	 * @return DevblocksPluginManifest[]
	 */
	static function readPlugins() {
	    DevblocksPlatform::clearCache();
	    
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
	                        $plugins[] = $manifest;
	                    }
	                }
	            }
	            closedir($dh);
	        }
	    }

	    DAO_Platform::cleanupPluginTables();
	    DevblocksPlatform::clearCache();
	    
	    return $plugins; // [TODO] Move this to the DB
	}

	/**
	 * @return Zend_Cache_Core
	 */
	static function getCacheService() {
	    return _DevblocksCacheManager::getInstance();
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
	 * @return _DevblocksPatchManager
	 */
	static function getPatchService() {
	    return _DevblocksPatchManager::getInstance();
	}

	/**
	 * Enter description here...
	 *
	 * @return CloudGlue
	 */
	static function getCloudGlueService() {
	    return CloudGlue::getInstance();
	}

	/**
	 * @return _DevblocksRoutingManager
	 */
    static function getRoutingService() {
        return _DevblocksRoutingManager::getInstance();
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
	 * @return _DevblocksEventManager
	 */
	static function getEventService() {
	    return _DevblocksEventManager::getInstance();
	}
	
	/**
	 * @return DevblocksProxy
	 */
	static function getProxyService() {
	    return DevblocksProxy::getProxy();
	}
	
	/**
	 * @return _DevblocksClassLoadManager
	 */
	static function getClassLoaderService() {
		return _DevblocksClassLoadManager::getInstance();
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
	 * @return Zend_Date
	 */
	static function getDateService($date=null) {
	    $locale = DevblocksPlatform::getLocaleService();
	    $date = new Zend_Date($date);
	    $date->setLocale($locale);
	    return $date;
	}

	/**
	 * @return Zend_Locale
	 */
	static function getLocaleService() {
	    if(!Zend_Registry::isRegistered('locale')) {
	        $locale = new Zend_Locale('en_US');
	        Zend_Registry::set('locale', $locale);
	    } else {
	        $locale = Zend_Registry::get('locale');
	    }

	    return $locale;
	}

	/**
	 * @return Zend_Translate
	 */
	static function getTranslationService() {
	    $cache = self::getCacheService();
	    if(false === ($translate = $cache->load(self::CACHE_TRANSLATIONS))) {
	        $locale = DevblocksPlatform::getLocaleService();
	        $translate = new Zend_Translate('tmx', DEVBLOCKS_PATH . 'resources/strings.xml', $locale);
		
	        // [JAS]: Read in translations from the extension point
	        if(!self::isDatabaseEmpty()) {
	            $translations = DevblocksPlatform::getExtensions("devblocks.i18n.strings");
	            
		        if(is_array($translations))
		        foreach($translations as $translationManifest) { /* @var $translationManifest DevblocksExtensionManifest */
		            $translation = $translationManifest->createInstance(); /* @var $translation DevblocksTranslationsExtension */
		            $file = $translation->getTmxFile();
	
		            if(@is_readable($file))
		            $translate->addTranslation($file, $locale);
		        }
		        
       	        $cache->save($translate,self::CACHE_TRANSLATIONS);
	        }
	    }

	    return $translate;
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
	static function setHttpRequest(DevblocksHttpRequest $request) {
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
	static function setHttpResponse(DevblocksHttpResponse $response) {
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
	        
		    @$proxyhost = $_SERVER['HTTP_DEVBLOCKSPROXYHOST'];
		    @$proxybase = $_SERVER['HTTP_DEVBLOCKSPROXYBASE'];
        
            if(!empty($proxybase)) {
                $php_self = $proxybase . '/';
            } elseif(DEVBLOCKS_REWRITE) {
	            $pos = strrpos($php_self,'/');
	            $php_self = substr($php_self,0,$pos) . '/';
	        } else {
	            $pos = strrpos($php_self,'index.php');
	            if(false === $pos) $pos = strrpos($php_self,'ajax.php');
	            $php_self = substr($php_self,0,$pos);
	        }
	        
	        @define('DEVBLOCKS_WEBPATH',$php_self);
	    }
	}

};
