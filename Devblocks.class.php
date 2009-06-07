<?php
include_once(DEVBLOCKS_PATH . "api/Engine.php");
include_once(DEVBLOCKS_PATH . "api/Model.php");
include_once(DEVBLOCKS_PATH . "api/DAO.php");
include_once(DEVBLOCKS_PATH . "api/Extension.php");

//include_once(DEVBLOCKS_PATH . "libs/cloudglue/CloudGlue.php");

define('PLATFORM_BUILD',302);

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
    const CACHE_TAG_TRANSLATIONS = 'devblocks_translations';
    const CACHE_EVENT_POINTS = 'devblocks_event_points';
    const CACHE_EVENTS = 'devblocks_events';
    const CACHE_ACL = 'devblocks_acl';
    
//    static private $pluginDelegate = null;
    static private $extensionDelegate = null;
    
    static private $start_time = 0;
    static private $start_memory = 0;
    static private $start_peak_memory = 0;
    
    static private $locale = 'en_US';
    
    private function __construct() {}

	/**
	 * @param mixed $var
	 * @param string $cast
	 * @param mixed $default
	 * @return mixed
	 */
	static function importGPC($var,$cast=null,$default=null) {
	    if(!is_null($var)) {
	        if(is_string($var)) {
	            $var = get_magic_quotes_gpc() ? stripslashes($var) : $var;
	        } elseif(is_array($var)) {
                foreach($var as $k => $v) {
                    $var[$k] = get_magic_quotes_gpc() ? stripslashes($v) : $v;
                }
	        }
	        
	    } elseif (is_null($var) && !is_null($default)) {
	        $var = $default;
	    }
	    
	    if(!is_null($cast))
	        @settype($var, $cast);

	    return $var;
	}

	/**
	 * Returns a string as a regexp. 
	 * "*bob" returns "/(.*?)bob/".
	 */
	static function parseStringAsRegExp($string) {
		$pattern = str_replace(array('*'),'__any__', $string);
		$pattern = sprintf("/%s/i",str_replace(array('__any__'),'(.*?)', preg_quote($pattern)));
		return $pattern;
	}
	
	static function parseCrlfString($string) {
		$parts = split("[\r\n]", $string);
		
		// Remove any empty tokens
		foreach($parts as $idx => $part) {
			$parts[$idx] = trim($part);
			if(0 == strlen($parts[$idx])) 
				unset($parts[$idx]);
		}
		
		return $parts;
	}
	
	/**
	 * Returns a string as alphanumerics delimited by underscores.
	 * For example: "Devs: 1000 Ways to Improve Sales" becomes 
	 * "devs_1000_ways_to_improve_sales", which is suitable for 
	 * displaying in a URL of a blog, faq, etc.
	 *
	 * @param string $str
	 * @return string
	 */
	static function getStringAsURI($str) {
		$str = strtolower($str);
		
		// turn non [a-z, 0-9, _] into whitespace
		$str = preg_replace("/[^0-9a-z]/",' ',$str);
		
		// condense whitespace to a single underscore
		$str = preg_replace('/\s\s+/', ' ', $str);

		// replace spaces with underscore
		$str = str_replace(' ','_',$str);

		// remove a leading/trailing underscores
		$str = trim($str, '_');
		
		return $str;
	}
	
	/**
	 * Takes a comma-separated value string and returns an array of tokens.
	 * [TODO] Move to a FormHelper service?
	 * 
	 * @param string $string
	 * @return array
	 */
	static function parseCsvString($string) {
		$tokens = explode(',', $string);

		if(!is_array($tokens))
			return array();
		
		foreach($tokens as $k => $v) {
			$tokens[$k] = trim($v);
			if(0==strlen($tokens[$k]))
				unset($tokens[$k]);
		}
		
		return $tokens;
	}
	
	/**
	 * Clears any platform-level plugin caches.
	 * 
	 */
	static function clearCache() {
	    $cache = self::getCacheService(); /* @var $cache Zend_Cache_Core */
	    $cache->remove(self::CACHE_PLUGINS);
	    $cache->remove(self::CACHE_EXTENSIONS);
	    $cache->remove(self::CACHE_POINTS);
	    $cache->remove(self::CACHE_EVENT_POINTS);
	    $cache->remove(self::CACHE_EVENTS);
	    $cache->remove(self::CACHE_TABLES);
	    $cache->remove(self::CACHE_ACL);
	    $cache->remove(_DevblocksClassLoadManager::CACHE_CLASS_MAP);

	    // Clear all locale caches
	    $langs = DAO_Translation::getDefinedLangCodes();
	    if(is_array($langs) && !empty($langs))
	    foreach($langs as $lang_code => $lang_name) {
	    	$cache->remove(self::CACHE_TAG_TRANSLATIONS . '_' . $lang_code);
	    }
	    
	    // Recache plugins
		self::getPluginRegistry();
		self::getExtensionRegistry();
	}

	public static function loadClass($className) {
		$classloader = self::getClassLoaderService();
		return $classloader->loadClass($className);
	}
	
	public static function registerClasses($file,$classes=array()) {
		$classloader = self::getClassLoaderService();
		return $classloader->registerClasses($file,$classes);
	}
	
	public static function getStartTime() {
		return self::$start_time;
	}
	
	public static function getStartMemory() {
		return self::$start_memory;
	}
	
	public static function getStartPeakMemory() {
		return self::$start_peak_memory;
	}
	
	/**
	 * Checks whether the active database has any tables.
	 * 
	 * @return boolean
	 */
	static function isDatabaseEmpty() {
		$tables = self::getDatabaseTables();
	    return empty($tables);
	}
	
	static function getDatabaseTables() {
	    $cache = self::getCacheService();
	    $tables = array();
	    
	    if(null === ($tables = $cache->load(self::CACHE_TABLES))) {
	        $db = self::getDatabaseService(); /* @var $db ADODB_Connection */
	        
	        // Make sure the database connection is valid or error out.
	        if(is_null($db) || !$db->IsConnected())
	        	return array();
	        
	        $tables = $db->MetaTables('TABLE',false);
	        $cache->save($tables, self::CACHE_TABLES);
	    }
	    return $tables;
	}

	/**
	 * Checks to see if the application needs to patch
	 *
	 * @return boolean
	 */
	static function versionConsistencyCheck() {
		$cache = DevblocksPlatform::getCacheService(); /* @var Zend_Cache_Core $cache */ 
		
		if(null === ($build_cache = $cache->load("devblocks_app_build"))
			|| $build_cache != APP_BUILD) {
				
			// If build changed, clear cache regardless of patch status
			// [TODO] We need to find a nicer way to not clear a shared memcached cluster when only one desk needs to
			$cache = DevblocksPlatform::getCacheService(); /* @var $cache Zend_Cache_Core */
			$cache->clean('all');
			
			// Re-read manifests
			DevblocksPlatform::readPlugins();
				
			if(self::_needsToPatch()) {
				return false; // the update script will handle new caches
			} else {
				$cache->save(APP_BUILD, "devblocks_app_build");
				DAO_Translation::reloadPluginStrings(); // reload strings even without DB changes
				return true;
			}
		}
		
		return true;
	}
	
	/**
	 * Enter description here...
	 *
	 * @return boolean
	 */
	static private function _needsToPatch() {
		 $plugins = DevblocksPlatform::getPluginRegistry();
		 $containers = DevblocksPlatform::getExtensions("devblocks.patch.container", true, true);

		 // [JAS]: Devblocks
		 array_unshift($containers, new PlatformPatchContainer());
		 
		 foreach($containers as $container) { /* @var $container DevblocksPatchContainerExtension */
			foreach($container->getPatches() as $patch) { /* @var $patch DevblocksPatch */
				if(!$patch->hasRun()) {
//					echo "Need to run a patch: ",$patch->getPluginId(),$patch->getRevision();
					return true;
				}
			}
		 }
		 
//		 echo "Don't need to run any patches.";
		 return false;
	}
	
	/**
	 * Runs patches for all active plugins
	 *
	 * @return boolean
	 */
	static function runPluginPatches() {
	    // Log out all sessions before patching
		$db = DevblocksPlatform::getDatabaseService();
		$db->Execute(sprintf("DELETE FROM %s_session", APP_DB_PREFIX));
		
		$patchMgr = DevblocksPlatform::getPatchService();
		
//		echo "Patching platform... ";
		
		// [JAS]: Run our overloaded container for the platform
		$patchMgr->registerPatchContainer(new PlatformPatchContainer());
		
		// Clean script
		if(!$patchMgr->run()) {
			return false;
		    
		} else { // success
			// Read in plugin information from the filesystem to the database
			DevblocksPlatform::readPlugins();
			
			$plugins = DevblocksPlatform::getPluginRegistry();
			
//			DevblocksPlatform::clearCache();
			
			// Run enabled plugin patches
			$patches = DevblocksPlatform::getExtensions("devblocks.patch.container",false,true);
			
			if(is_array($patches))
			foreach($patches as $patch_manifest) { /* @var $patch_manifest DevblocksExtensionManifest */ 
				if(null != ($container = $patch_manifest->createInstance())) { /* @var $container DevblocksPatchContainerExtension */
					if(!is_null($container)) {
						$patchMgr->registerPatchContainer($container);
					}
				}
			}
			
//			echo "Patching plugins... ";
			
			if(!$patchMgr->run()) { // fail
				return false;
			}
			
//			echo "done!<br>";

			$cache = self::getCacheService();
			$cache->save(APP_BUILD, "devblocks_app_build");

			return true;
		}
	}
	
	/**
	 * Returns the list of extensions on a given extension point.
	 *
	 * @static
	 * @param string $point
	 * @return DevblocksExtensionManifest[]
	 */
	static function getExtensions($point,$as_instances=false, $ignore_acl=false) {
	    $results = array();
	    $extensions = DevblocksPlatform::getExtensionRegistry($ignore_acl);

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
	 * @param boolean $as_instance
	 * @return DevblocksExtensionManifest
	 */
	static function getExtension($extension_id, $as_instance=false, $ignore_acl=false) {
	    $result = null;
	    $extensions = DevblocksPlatform::getExtensionRegistry($ignore_acl);

	    if(is_array($extensions))
	    foreach($extensions as $extension) { /* @var $extension DevblocksExtensionManifest */
	        if(0 == strcasecmp($extension->id,$extension_id)) {
	            $result = $extension;
	            break;
	        }
	    }

	    if($as_instance && !is_null($result)) {
	    	return $result->createInstance();
	    }
	    
	    return $result;
	}

//	static function getExtensionPoints() {
//	    $cache = self::getCacheService();
//	    if(null !== ($points = $cache->load(self::CACHE_POINTS)))
//	        return $points;
//
//	    $extensions = DevblocksPlatform::getExtensionRegistry();
//	    foreach($extensions as $extension) { /* @var $extension DevblocksExtensionManifest */
//	        $point = $extension->point;
//	        if(!isset($points[$point])) {
//	            $p = new DevblocksExtensionPoint();
//	            $p->id = $point;
//	            $points[$point] = $p;
//	        }
//	        	
//	        $points[$point]->extensions[$extension->id] = $extension;
//	    }
//
//	    $cache->save($points, self::CACHE_POINTS);
//	    return $points;
//	}

	/**
	 * Returns an array of all contributed extension manifests.
	 *
	 * @static 
	 * @return DevblocksExtensionManifest[]
	 */
	static function getExtensionRegistry($ignore_acl=false) {
	    $cache = self::getCacheService();
	    
	    if(null === ($extensions = $cache->load(self::CACHE_EXTENSIONS))) {
		    $db = DevblocksPlatform::getDatabaseService();
		    if(is_null($db)) return;
	
		    $prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup
	
		    $sql = sprintf("SELECT e.id , e.plugin_id, e.point, e.pos, e.name , e.file , e.class, e.params ".
				"FROM %sextension e ".
				"INNER JOIN %splugin p ON (e.plugin_id=p.id) ".
				"WHERE p.enabled = 1 ".
				"ORDER BY e.plugin_id ASC, e.pos ASC",
					$prefix,
					$prefix
				);
			$rs = $db->Execute($sql); /* @var $rs ADORecordSet */
				
			if(is_a($rs,'ADORecordSet'))
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
				$extensions[$extension->id] = $extension;
			    $rs->MoveNext();
			}

			$cache->save($extensions, self::CACHE_EXTENSIONS);
		}
		
		// Check with an extension delegate if we have one
		if(!$ignore_acl && class_exists(self::$extensionDelegate) && method_exists('DevblocksExtensionDelegate','shouldLoadExtension')) {
			if(is_array($extensions))
			foreach($extensions as $id => $extension) {
				// Ask the delegate if we should load the extension
				if(!call_user_func(array(self::$extensionDelegate,'shouldLoadExtension'),$extension))
					unset($extensions[$id]);
			}
		}
		
		return $extensions;
	}

	/**
	 * @return DevblocksEventPoint[]
	 */
	static function getEventPointRegistry() {
	    $cache = self::getCacheService();
	    if(null !== ($events = $cache->load(self::CACHE_EVENT_POINTS)))
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
	
	/**
	 * @return DevblocksAclPrivilege[]
	 */
	static function getAclRegistry() {
	    $cache = self::getCacheService();
	    if(null !== ($acl = $cache->load(self::CACHE_ACL)))
    	    return $acl;

        $acl = array();

	    $db = DevblocksPlatform::getDatabaseService();
	    if(is_null($db)) return;

        //$plugins = self::getPluginRegistry();
	    $prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup

	    $sql = sprintf("SELECT a.id, a.plugin_id, a.label ".
			"FROM %sacl a ".
			"INNER JOIN %splugin p ON (a.plugin_id=p.id) ".
			"WHERE p.enabled = 1 ".
			"ORDER BY a.plugin_id, a.id ASC",
			$prefix,
			$prefix
		);
		$rs = $db->Execute($sql); /* @var $rs ADORecordSet */
		
		if(is_a($rs,'ADORecordSet'))
		while(!$rs->EOF) {
			$priv = new DevblocksAclPrivilege();
			$priv->id = $rs->fields['id'];
			$priv->plugin_id = $rs->fields['plugin_id'];
			$priv->label = $rs->fields['label'];
			
		    $acl[$priv->id] = $priv;
		    
		    $rs->MoveNext();
		}
        
		$cache->save($acl, self::CACHE_ACL);
		return $acl;
	}
	
	static function getEventRegistry() {
	    $cache = self::getCacheService();
	    if(null !== ($events = $cache->load(self::CACHE_EVENTS)))
    	    return $events;
	    
    	$extensions = self::getExtensions('devblocks.listener.event',false,true);
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
	    if(null !== ($plugins = $cache->load(self::CACHE_PLUGINS)))
    	    return $plugins;

	    $db = DevblocksPlatform::getDatabaseService();
	    if(is_null($db)) return;
	    $prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup

	    $sql = sprintf("SELECT * ". // p.id , p.enabled , p.name, p.description, p.author, p.revision, p.link, p.class, p.file, p.dir
			"FROM %splugin p ".
			"ORDER BY p.enabled DESC, p.name ASC ",
			$prefix
		);
		$rs = $db->Execute($sql); /* @var $rs ADORecordSet */
		
		if(is_a($rs,'ADORecordSet'))
		while(!$rs->EOF) {
		    $plugin = new DevblocksPluginManifest();
		    @$plugin->id = $rs->fields['id'];
		    @$plugin->enabled = intval($rs->fields['enabled']);
		    @$plugin->name = $rs->fields['name'];
		    @$plugin->description = $rs->fields['description'];
		    @$plugin->author = $rs->fields['author'];
		    @$plugin->revision = intval($rs->fields['revision']);
		    @$plugin->link = $rs->fields['link'];
		    @$plugin->file = $rs->fields['file'];
		    @$plugin->class = $rs->fields['class'];
		    @$plugin->dir = $rs->fields['dir'];
	
		    if(file_exists(DEVBLOCKS_PLUGIN_PATH . $plugin->dir . DIRECTORY_SEPARATOR . 'plugin.xml')) {
		        $plugins[$plugin->id] = $plugin;
		    }
		    	
		    $rs->MoveNext();
		}

		$sql = sprintf("SELECT p.id, p.name, p.params, p.plugin_id ".
		    "FROM %sevent_point p ",
		    $prefix
		);
		$rs = $db->Execute($sql); /* @var $rs ADORecordSet */

		if(is_a($rs,'ADORecordSet'))
		while(!$rs->EOF) {
		    $point = new DevblocksEventPoint();
		    $point->id = $rs->fields['id'];
		    $point->name = $rs->fields['name'];
		    $point->plugin_id = $rs->fields['plugin_id'];
		    
		    $params = $rs->fields['params'];
		    $point->params = !empty($params) ? unserialize($params) : array();

		    if(isset($plugins[$point->plugin_id])) {
		    	$plugins[$point->plugin_id]->event_points[$point->id] = $point;
		    }
		    
		    $rs->MoveNext();
		}
			
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
	    $dir = DEVBLOCKS_PLUGIN_PATH;
	    $plugins = array();

	    if (is_dir($dir)) {
	        if ($dh = opendir($dir)) {
	            while (($file = readdir($dh)) !== false) {
	                if($file=="." || $file == ".." || 0 == strcasecmp($file,"CVS"))
	                continue;

	                $path = $dir . '/' . $file;
	                if(is_dir($path) && file_exists($path.'/plugin.xml')) {
	                    $manifest = self::_readPluginManifest($file); /* @var $manifest DevblocksPluginManifest */

	                    if(null != $manifest) {
	                        $plugins[] = $manifest;
	                    }
	                }
	            }
	            closedir($dh);
	        }
	    }
	    
		// [TODO] Instance the plugins in dependency order

	    DAO_Platform::cleanupPluginTables();
	    DevblocksPlatform::clearCache();
	    
	    return $plugins;
	}

	/**
	 * @return Zend_Log
	 */
	static function getConsoleLog() {
		return _DevblocksLogManager::getConsoleLog();
	}
	
	/**
	 * @return _DevblocksCacheManager
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
//	static function getCloudGlueService() {
//	    return CloudGlue::getInstance();
//	}

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
	 * @return _DevblocksDateManager
	 */
	static function getDateService($datestamp=null) {
		return _DevblocksDateManager::getInstance();
	}

	static function setLocale($locale) {
		@setlocale(LC_ALL, $locale);
		self::$locale = $locale;
	}
	
	static function getLocale() {
		if(!empty(self::$locale))
			return self::$locale;
			
		return 'en_US';
	}
	
	/**
	 * @return _DevblocksTranslationManager
	 */
	static function getTranslationService() {
		$locale = DevblocksPlatform::getLocale();

	    if(Zend_Registry::isRegistered('Devblocks:getTranslationService:'.$locale)) {
			return Zend_Registry::get('Devblocks:getTranslationService:'.$locale);
		}
						
		$cache = self::getCacheService();
	    
	    if(null === ($map = $cache->load(self::CACHE_TAG_TRANSLATIONS.'_'.$locale))) { /* @var $cache Zend_Cache_Core */
			$map = array();
			$map_en = DAO_Translation::getMapByLang('en_US');
			if(0 != strcasecmp('en_US', $locale))
				$map_loc = DAO_Translation::getMapByLang($locale);
			
			// Loop through the English string objects
			if(is_array($map_en))
			foreach($map_en as $string_id => $obj_string_en) {
				$string = '';
				
				// If we have a locale to check
				if(isset($map_loc) && is_array($map_loc)) {
					@$obj_string_loc = $map_loc[$string_id];
					@$string =
						(!empty($obj_string_loc->string_override))
						? $obj_string_loc->string_override
						: $obj_string_loc->string_default;
				}
				
				// If we didn't hit, load the English default
				if(empty($string))
				@$string = 
					(!empty($obj_string_en->string_override))
					? $obj_string_en->string_override
					: $obj_string_en->string_default;
					
				// If we found any match
				if(!empty($string))
					$map[$string_id] = $string;
			}
			unset($obj_string_en);
			unset($obj_string_loc);
			unset($map_en);
			unset($map_loc);
			
			// Cache with tag (tag allows easy clean for multiple langs at once)
			$cache->save($map,self::CACHE_TAG_TRANSLATIONS.'_'.$locale,array(self::CACHE_TAG_TRANSLATIONS));
	    }
	    
		$translate = _DevblocksTranslationManager::getInstance();
		$translate->addLocale($locale, $map);
		$translate->setLocale($locale);
	    
		Zend_Registry::set('Devblocks:getTranslationService:'.$locale, $translate);

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
		self::$start_time = microtime(true);
		if(function_exists('memory_get_usage') && function_exists('memory_get_peak_usage')) {
			self::$start_memory = memory_get_usage();
			self::$start_peak_memory = memory_get_peak_usage();
		}
		
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

//	static function setPluginDelegate($class) {
//		if(!empty($class) && class_exists($class, true))
//			self::$pluginDelegate = $class;
//	}
	
	static function setExtensionDelegate($class) {
		if(!empty($class) && class_exists($class, true))
			self::$extensionDelegate = $class;
	}
	
	static function redirect(DevblocksHttpIO $httpIO) {
		$url_service = self::getUrlService();
		session_write_close();
		$url = $url_service->writeDevblocksHttpIO($httpIO, true);
		header('Location: '.$url);
		exit;
	}
};

// [TODO] This doesn't belong! (ENGINE)
class PlatformPatchContainer extends DevblocksPatchContainerExtension {
	
	function __construct() {
		parent::__construct(null);
		
		/*
		 * [JAS]: Just add a build number here (from your commit revision) and write a
		 * case in runBuild().  You should comment the milestone next to your build 
		 * number.
		 */

		$file_prefix = dirname(__FILE__) . '/patches/';

		$this->registerPatch(new DevblocksPatch('devblocks.core',1,$file_prefix.'1.0.0.php',''));
		$this->registerPatch(new DevblocksPatch('devblocks.core',253,$file_prefix.'1.0.0_beta.php',''));
		$this->registerPatch(new DevblocksPatch('devblocks.core',290,$file_prefix.'1.1.0.php',''));
	}
};
