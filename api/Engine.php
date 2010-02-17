<?php
require_once(DEVBLOCKS_PATH . 'libs/swift/swift_required.php');

function devblocks_autoload($className) {
	DevblocksPlatform::loadClass($className);
}

spl_autoload_register('devblocks_autoload');

/**
 * Description
 * 
 * @ingroup core
 */
abstract class DevblocksEngine {
	protected static $request = null;
	protected static $response = null;
	
	/**
	 * Reads and caches a single manifest from a given plugin directory.
	 * 
	 * @static 
	 * @private
	 * @param string $dir
	 * @return DevblocksPluginManifest
	 */
	static protected function _readPluginManifest($rel_dir) {
		$manifest_file = APP_PATH . '/' . $rel_dir . '/plugin.xml'; 
		
		if(!file_exists($manifest_file))
			return NULL;
		
		$plugin = simplexml_load_file($manifest_file);
		$prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup
				
		$manifest = new DevblocksPluginManifest();
		$manifest->id = (string) $plugin->id;
		$manifest->dir = $rel_dir;
		$manifest->description = (string) $plugin->description;
		$manifest->author = (string) $plugin->author;
		$manifest->revision = (integer) $plugin->revision;
		$manifest->link = (string) $plugin->link;
		$manifest->name = (string) $plugin->name;

		// [TODO] Clear out any removed plugins/classes/exts?
        
		$db = DevblocksPlatform::getDatabaseService();
		if(is_null($db)) 
			return;
		
		// Templates
		if(isset($plugin->templates)) {
			foreach($plugin->templates as $eTemplates) {
				$template_set = (string) $eTemplates['set'];
				
				if(isset($eTemplates->template))
				foreach($eTemplates->template as $eTemplate) {
					$manifest->templates[] = array(
						'plugin_id' => $manifest->id,
						'set' => $template_set,
						'path' => (string) $eTemplate['path'],
					);
				}
			}
		}
			
		// Manifest
		if($db->GetOne(sprintf("SELECT id FROM ${prefix}plugin WHERE id = %s", $db->qstr($manifest->id)))) { // update
			$db->Execute(sprintf(
				"UPDATE ${prefix}plugin ".
				"SET name=%s,description=%s,author=%s,revision=%s,link=%s,dir=%s,templates_json=%s ".
				"WHERE id=%s",
				$db->qstr($manifest->name),
				$db->qstr($manifest->description),
				$db->qstr($manifest->author),
				$db->qstr($manifest->revision),
				$db->qstr($manifest->link),
				$db->qstr($manifest->dir),
				$db->qstr(json_encode($manifest->templates)),
				$db->qstr($manifest->id)
			));
			
		} else { // insert
			$db->Execute(sprintf(
				"INSERT INTO ${prefix}plugin (id,name,description,author,revision,link,dir,templates_json) ".
				"VALUES (%s,%s,%s,%s,%s,%s,%s,%s)",
				$db->qstr($manifest->id),
				$db->qstr($manifest->name),
				$db->qstr($manifest->description),
				$db->qstr($manifest->author),
				$db->qstr($manifest->revision),
				$db->qstr($manifest->link),
				$db->qstr($manifest->dir),
				$db->qstr(json_encode($manifest->templates))
			));
		}
		
		// Class Loader
		if(isset($plugin->class_loader->file)) {
			foreach($plugin->class_loader->file as $eFile) {
				@$sFilePath = (string) $eFile['path'];
				$manifest->class_loader[$sFilePath] = array();
				
				if(isset($eFile->class))
				foreach($eFile->class as $eClass) {
					@$sClassName = (string) $eClass['name'];
					$manifest->class_loader[$sFilePath][] = $sClassName;
				}
			}
		}
		
		// Routing
		if(isset($plugin->uri_routing->uri)) {
			foreach($plugin->uri_routing->uri as $eUri) {
				@$sUriName = (string) $eUri['name'];
				@$sController = (string) $eUri['controller'];
				$manifest->uri_routing[$sUriName] = $sController;
			}
		}
		
		// ACL
		if(isset($plugin->acl->priv)) {
			foreach($plugin->acl->priv as $ePriv) {
				@$sId = (string) $ePriv['id'];
				@$sLabel = (string) $ePriv['label'];
				
				if(empty($sId) || empty($sLabel))
					continue;
					
				$priv = new DevblocksAclPrivilege();
				$priv->id = $sId;
				$priv->plugin_id = $manifest->id;
				$priv->label = $sLabel;
				
				$manifest->acl_privs[$priv->id] = $priv;
			}
			asort($manifest->acl_privs);
		}
		
		// Event points
		if(isset($plugin->event_points->event)) {
		    foreach($plugin->event_points->event as $eEvent) {
		        $sId = (string) $eEvent['id'];
		        $sName = (string) $eEvent->name;
		        
		        if(empty($sId) || empty($sName))
		            continue;
		        
		        $point = new DevblocksEventPoint();
		        $point->id = $sId;
		        $point->plugin_id = $plugin->id;
		        $point->name = $sName;
		        $point->params = array();
		        
		        if(isset($eEvent->param)) {
		            foreach($eEvent->param as $eParam) {
		                $key = (string) $eParam['key']; 
		                $val = (string) $eParam['value']; 
		                $point->param[$key] = $val;
		            }
		        }
		        
		        $manifest->event_points[] = $point;
		    }
		}
		
		// Extensions
		if(isset($plugin->extensions->extension)) {
		    foreach($plugin->extensions->extension as $eExtension) {
		        $sId = (string) $eExtension->id;
		        $sName = (string) $eExtension->name;
		        
		        if(empty($sId) || empty($sName))
		            continue;
		        
		        $extension = new DevblocksExtensionManifest();
		        
		        $extension->id = $sId;
		        $extension->plugin_id = $manifest->id;
		        $extension->point = (string) $eExtension['point'];
		        $extension->name = $sName;
		        $extension->file = (string) $eExtension->class->file;
		        $extension->class = (string) $eExtension->class->name;
		        
		        if(isset($eExtension->params->param)) {
		            foreach($eExtension->params->param as $eParam) {
				$key = (string) $eParam['key'];
		                if(isset($eParam->value)) {
					// [JSJ]: If there is a child of the param tag named value, then this 
					//        param has multiple values and thus we need to grab them all.
					foreach($eParam->value as $eValue) {
						// [JSJ]: If there is a child named data, then this is a complex structure
						if(isset($eValue->data)) {
							$value = array();
							foreach($eValue->data as $eData) {
								$key2 = (string) $eData['key'];
								if(isset($eData['value'])) {
									$value[$key2] = (string) $eData['value'];
								} else {
									$value[$key2] = (string) $eData;
								}
							}
						}
						else {
							// [JSJ]: Else, just grab the value and use it
							$value = (string) $eValue;
						}
						$extension->params[$key][] = $value;
						unset($value); // Just to be extra safe
					}
				}
				else {
					// [JSJ]: Otherwise, we grab the single value from the params value attribute.
					$extension->params[$key] = (string) $eParam['value'];
				}
		            }
		        }
		        
		        $manifest->extensions[] = $extension;
		    }
		}

		// [JAS]: Extension caching
		$new_extensions = array();
		if(is_array($manifest->extensions))
		foreach($manifest->extensions as $pos => $extension) { /* @var $extension DevblocksExtensionManifest */
			$db->Execute(sprintf(
				"REPLACE INTO ${prefix}extension (id,plugin_id,point,pos,name,file,class,params) ".
				"VALUES (%s,%s,%s,%d,%s,%s,%s,%s)",
				$db->qstr($extension->id),
				$db->qstr($extension->plugin_id),
				$db->qstr($extension->point),
				$pos,
				$db->qstr($extension->name),
				$db->qstr($extension->file),
				$db->qstr($extension->class),
				$db->qstr(serialize($extension->params))
			));
			
			$new_extensions[$extension->id] = true;
		}
		
		/*
		 * Compare our loaded XML manifest to the DB manifest cache and invalidate 
		 * the cache for extensions that are no longer in the XML.
		 */
		$sql = sprintf("SELECT id FROM %sextension WHERE plugin_id = %s",
			$prefix,
			$db->qstr($plugin->id)
		);
		$results = $db->GetArray($sql);

		foreach($results as $row) {
			$plugin_ext_id = $row['id'];
			if(!isset($new_extensions[$plugin_ext_id]))
				DAO_Platform::deleteExtension($plugin_ext_id);
		}
		
        // [JAS]: [TODO] Extension point caching

		// Class loader cache
		$db->Execute(sprintf("DELETE FROM %sclass_loader WHERE plugin_id = %s",$prefix,$db->qstr($plugin->id)));
		if(is_array($manifest->class_loader))
		foreach($manifest->class_loader as $file_path => $classes) {
			if(is_array($classes) && !empty($classes))
			foreach($classes as $class)
			$db->Execute(sprintf(
				"REPLACE INTO ${prefix}class_loader (class,plugin_id,rel_path) ".
				"VALUES (%s,%s,%s)",
				$db->qstr($class),
				$db->qstr($manifest->id),
				$db->qstr($file_path)	
			));			
		}
		
		// URI routing cache
		$db->Execute(sprintf("DELETE FROM %suri_routing WHERE plugin_id = %s",$prefix,$db->qstr($plugin->id)));
		if(is_array($manifest->uri_routing))
		foreach($manifest->uri_routing as $uri => $controller_id) {
			$db->Execute(sprintf(
				"REPLACE INTO ${prefix}uri_routing (uri,plugin_id,controller_id) ".
				"VALUES (%s,%s,%s)",
				$db->qstr($uri),
				$db->qstr($manifest->id),
				$db->qstr($controller_id)	
			));			
		}

		// ACL caching
		$db->Execute(sprintf("DELETE FROM %sacl WHERE plugin_id = %s",$prefix,$db->qstr($plugin->id)));
		if(is_array($manifest->acl_privs))
		foreach($manifest->acl_privs as $priv) { /* @var $priv DevblocksAclPrivilege */
			$db->Execute(sprintf(
				"REPLACE INTO ${prefix}acl (id,plugin_id,label) ".
				"VALUES (%s,%s,%s)",
				$db->qstr($priv->id),
				$db->qstr($priv->plugin_id),
				$db->qstr($priv->label)
			));			
		}
		
        // [JAS]: Event point caching
		if(is_array($manifest->event_points))
		foreach($manifest->event_points as $event) { /* @var $event DevblocksEventPoint */
			$db->Execute(sprintf(
				"REPLACE INTO ${prefix}event_point (id,plugin_id,name,params) ".
				"VALUES (%s,%s,%s,%s)",
				$db->qstr($event->id),
				$db->qstr($event->plugin_id),
				$db->qstr($event->name),
				$db->qstr(serialize($event->params))	
			));
		}
		
		return $manifest;
	}
	
	static function getWebPath() {
		$location = "";
		
		// Read the relative URL into an array
		if(isset($_SERVER['HTTP_X_REWRITE_URL'])) { // IIS Rewrite
			$location = $_SERVER['HTTP_X_REWRITE_URL'];
		} elseif(isset($_SERVER['REQUEST_URI'])) { // Apache
			$location = $_SERVER['REQUEST_URI'];
		} elseif(isset($_SERVER['REDIRECT_URL'])) { // Apache mod_rewrite (breaks on CGI)
			$location = $_SERVER['REDIRECT_URL'];
		} elseif(isset($_SERVER['ORIG_PATH_INFO'])) { // IIS + CGI
			$location = $_SERVER['ORIG_PATH_INFO'];
		}
		
		return $location;
	}
	
	/**
	 * Return a string as a regular expression, parsing * into a non-greedy 
	 * wildcard, etc.
	 *
	 * @param string $arg
	 * @return string
	 */
	static function strToRegExp($arg, $is_partial=false) {
		$arg = str_replace(array('*'),array('__WILD__'),$arg);
		
		return sprintf("/%s%s%s/i",
			($is_partial ? '' : '^'),
			str_replace(array('__WILD__','/'),array('.*?','\/'),preg_quote($arg)),
			($is_partial ? '' : '$')
		);
	}
	
	/**
	 * Return a string with only its alphanumeric characters
	 *
	 * @param string $arg
	 * @return string
	 */
	static function strAlphaNum($arg) {
		return preg_replace("/[^A-Z0-9\.]/i","", $arg);
	}
	
	/**
	 * Return a string with only its alphanumeric characters or punctuation
	 *
	 * @param string $arg
	 * @return string
	 */
	static function strAlphaNumDash($arg) {
		return preg_replace("/[^A-Z0-9_\-\.]/i","", $arg);
	}
	
	/**
	 * Reads the HTTP Request object.
	 * 
	 * @return DevblocksHttpRequest
	 */
	static function readRequest() {
		$url = DevblocksPlatform::getUrlService();

		$location = self::getWebPath();
		
		$parts = $url->parseURL($location);
		
		// Add any query string arguments (?arg=value&arg=value)
		@$query = $_SERVER['QUERY_STRING'];
		$queryArgs = $url->parseQueryString($query);
		
		if(empty($parts)) {
			// Overrides (Form POST, etc.)
			@$uri = DevblocksPlatform::importGPC($_REQUEST['c']); // extension
			if(!empty($uri)) $parts[] = self::strAlphaNum($uri);

			@$listener = DevblocksPlatform::importGPC($_REQUEST['a']); // listener
			if(!empty($listener)) $parts[] = self::strAlphaNum($listener);
		}
		
		// Controller XSS security (alphanum only)
		if(isset($parts[0])) {
			$parts[0] = self::strAlphaNum($parts[0]);
		}
		
		// Resource / Proxy
	    /*
	     * [TODO] Run this code through another audit.  Is it worth a tiny hit per resource 
	     * to verify the plugin matches exactly in the DB?  If so, make sure we cache the 
	     * resulting file.
	     * 
	     * [TODO] Make this a controller
	     */
	    $path = $parts;
		switch(array_shift($path)) {
		    case "resource":
			    $plugin_id = array_shift($path);
			    if(null == ($plugin = DevblocksPlatform::getPlugin($plugin_id)))
			    	break;
			    
			    $file = implode(DIRECTORY_SEPARATOR, $path); // combine path
		        $dir = APP_PATH . '/' . $plugin->dir . '/' . 'resources';
		        if(!is_dir($dir)) die(""); // basedir Security
		        $resource = $dir . '/' . $file;
		        if(0 != strstr($dir,$resource)) die("");
		        $ext = @array_pop(explode('.', $resource));
		        if(!is_file($resource) || 'php' == $ext) die(""); // extension security

                // Caching
	            if($ext == 'css' || $ext == 'js' || $ext == 'png' || $ext == 'gif' || $ext == 'jpg') {
	                header('Cache-control: max-age=604800', true); // 1 wk // , must-revalidate
	                header('Expires: ' . gmdate('D, d M Y H:i:s',time()+604800) . ' GMT'); // 1 wk
	                header('Content-length: '. filesize($resource));
	            }

	            // [TODO] Get a better mime list together?
	            switch($ext) {
	            	case 'css':
	            		header('Content-type: text/css;');
	            		break;
	            	case 'gif':
	            		header('Content-type: image/gif;');
	            		break;
	            	case 'jpeg':
	            	case 'jpg':
	            		header('Content-type: image/jpeg;');
	            		break;
	            	case 'js':
	            		header('Content-type: text/javascript;');
	            		break;
	            	case 'png':
	            		header('Content-type: image/png;');
	            		break;
	            	case 'xml':
	            		header('Content-type: text/xml;');
	            		break;
	            }
	            
		        echo file_get_contents($resource,false);
				exit;
    	        break;
		        
		    default:
		        break;
		}

		$request = new DevblocksHttpRequest($parts,$queryArgs);
		DevblocksPlatform::setHttpRequest($request);
		
		return $request;
	}
	
	/**
	 * Processes the HTTP request.
	 * 
	 * @param DevblocksHttpRequest $request
	 * @param boolean $is_ajax
	 */
	static function processRequest(DevblocksHttpRequest $request, $is_ajax=false) {
		$path = $request->path;
		
		$controller_uri = array_shift($path);
		
		// [JAS]: Offer the platform a chance to intercept.
		switch($controller_uri) {

			// [JAS]: Plugin-supplied URIs
			default:
				$routing = array();
	            $controllers = DevblocksPlatform::getExtensions('devblocks.controller', false);
				
				// Add any controllers which have definitive routing
				if(is_array($controllers))
				foreach($controllers as $controller_mft) {
					if(isset($controller_mft->params['uri']))
						$routing[$controller_mft->params['uri']] = $controller_mft->id;
				}

				// [TODO] Ask the platform to look at any routing maps (extension manifest) or
				// controller objects
//				print_r($routing);

				// [TODO] Pages like 'tickets' currently work because APP_DEFAULT_CONTROLLER
				// is the ChPageController which looks up those URIs in manifests
	            
				if(empty($controllers))
					die("No controllers are available!");
				
				// Set our controller based on the results
				$controller_mft = (isset($routing[$controller_uri]))
					? $controllers[$routing[$controller_uri]]
					: $controllers[APP_DEFAULT_CONTROLLER];
				
				// Instance our manifest
				if(!empty($controller_mft)) {
					$controller = $controller_mft->createInstance();
				}
				
				if($controller instanceof DevblocksHttpRequestHandler) {
					$controller->handleRequest($request);
					
					// [JAS]: If we didn't write a new response, repeat the request
					if(null == ($response = DevblocksPlatform::getHttpResponse())) {
						$response = new DevblocksHttpResponse($request->path);
						DevblocksPlatform::setHttpResponse($response);
					}
					
					// [JAS]: An Ajax request doesn't need the full Http cycle
					if(!$is_ajax) {
						$controller->writeResponse($response);
					}
					
				} else {
				    header("Status: 404");
                    die(); // [TODO] Improve
				}
					
				break;
		}
		
		return;
	}
};

class _DevblocksPluginSettingsManager {
	private static $_instance = null;
	private $_settings = array();
	
	/**
	 * @return _DevblocksPluginSettingsManager
	 */
	private function __construct() {
	    // Defaults (dynamic)
		$plugin_settings = DAO_DevblocksSetting::getSettings();
		foreach($plugin_settings as $plugin_id => $kv) {
			if(!isset($this->_settings[$plugin_id]))
				$this->_settings[$plugin_id] = array();
				
			if(is_array($kv))
			foreach($kv as $k => $v)
				$this->_settings[$plugin_id][$k] = $v;
		}
	}
	
	/**
	 * @return _DevblocksPluginSettingsManager
	 */
	public static function getInstance() {
		if(self::$_instance==null) {
			self::$_instance = new _DevblocksPluginSettingsManager();	
		}
		
		return self::$_instance;		
	}
	
	public function set($plugin_id,$key,$value) {
		DAO_DevblocksSetting::set($plugin_id,$key,$value);
		
		if(!isset($this->_settings[$plugin_id]))
			$this->_settings[$plugin_id] = array();
		
		$this->_settings[$plugin_id][$key] = $value;
		
	    $cache = DevblocksPlatform::getCacheService();
		$cache->remove(DevblocksPlatform::CACHE_SETTINGS);
		
		return TRUE;
	}
	
	/**
	 * @param string $key
	 * @param string $default
	 * @return mixed
	 */
	public function get($plugin_id,$key,$default=null) {
		if(isset($this->_settings[$plugin_id][$key]))
			return $this->_settings[$plugin_id][$key];
		else 
			return $default;
	}
};

/**
 * Session Management Singleton
 *
 * @static 
 * @ingroup services
 */
class _DevblocksSessionManager {
	var $visit = null;
	
	/**
	 * @private
	 */
	private function _DevblocksSessionManager() {}
	
	/**
	 * Returns an instance of the session manager
	 *
	 * @static
	 * @return _DevblocksSessionManager
	 */
	static function getInstance() {
		static $instance = null;
		if(null == $instance) {
		    $db = DevblocksPlatform::getDatabaseService();
			if(is_null($db) || !$db->isConnected()) { 
				return null;
			}
			
			$prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup
			
			@session_destroy();
			
			$handler = '_DevblocksSessionDatabaseDriver';
			
			session_set_save_handler(
				array($handler, 'open'),
				array($handler, 'close'),
				array($handler, 'read'),
				array($handler, 'write'),
				array($handler, 'destroy'),
				array($handler, 'gc')
			);
			
			session_name(APP_SESSION_NAME);
			session_set_cookie_params(0);
			session_start();
			
			$instance = new _DevblocksSessionManager();
			$instance->visit = isset($_SESSION['db_visit']) ? $_SESSION['db_visit'] : NULL; /* @var $visit DevblocksVisit */
		}
		
		return $instance;
	}
	
	/**
	 * Returns the current session or NULL if no session exists.
	 * 
	 * @return DevblocksVisit
	 */
	function getVisit() {
		return $this->visit;
	}
	
	/**
	 * @param DevblocksVisit $visit
	 */
	function setVisit(DevblocksVisit $visit = null) {
		$this->visit = $visit;
		$_SESSION['db_visit'] = $this->visit;
	}
	
	/**
	 * Kills the current session.
	 *
	 */
	function clear() {
		$this->visit = null;
		unset($_SESSION['db_visit']);
		session_destroy();
	}
	
	function clearAll() {
		self::clear();
		// [TODO] Allow subclasses to be cleared here too
		_DevblocksSessionDatabaseDriver::destroyAll();
	}
};

class _DevblocksSessionDatabaseDriver {
	static function open($save_path, $session_name) {
		return true;
	}
	
	static function close() {
		return true;
	}
	
	static function read($id) {
		$db = DevblocksPlatform::getDatabaseService();
		if(null != ($data = $db->GetOne(sprintf("SELECT session_data FROM devblocks_session WHERE session_key = %s", $db->qstr($id)))))
			return $data;
			
		return false;
	}
	
	static function write($id, $session_data) {
		$db = DevblocksPlatform::getDatabaseService();
		
		if(null != ($data = $db->GetOne(sprintf("SELECT session_key FROM devblocks_session WHERE session_key = %s", $db->qstr($id))))) {
			// Update
			$db->Execute(sprintf("UPDATE devblocks_session SET updated=%d, session_data=%s WHERE session_key=%s",
				time(),
				$db->qstr($session_data),
				$db->qstr($id)
			));
		} else {
			// Insert
			$db->Execute(sprintf("INSERT INTO devblocks_session (session_key, created, updated, session_data) ".
				"VALUES (%s, %d, %d, %s)",
				$db->qstr($id),
				time(),
				time(),
				$db->qstr($session_data)
			));
		}
		return true;
	}
	
	static function destroy($id) {
		$db = DevblocksPlatform::getDatabaseService();
		$db->Execute(sprintf("DELETE FROM devblocks_session WHERE session_key = %s", $db->qstr($id)));
		return true;
	}
	
	static function gc($maxlifetime) {
		$db = DevblocksPlatform::getDatabaseService();
		$db->Execute(sprintf("DELETE FROM devblocks_session WHERE updated + %d < %d", $maxlifetime, time()));
		return true;
	}
	
	static function destroyAll() {
		$db = DevblocksPlatform::getDatabaseService();
		$db->Execute("DELETE FROM devblocks_session");
	}
};

class _DevblocksCacheManager {
    private static $instance = null;
    private static $_cacher = null;
	private $_registry = array();
	private $_statistics = array();
	private $_io_reads_long = 0;
	private $_io_reads_short = 0;
	private $_io_writes = 0;
    
    private function __construct() {}

    /**
     * @return _DevblocksCacheManager
     */
    public static function getInstance() {
		if(null == self::$instance) {
			self::$instance = new _DevblocksCacheManager();
			
			$options = array(
				'key_prefix' => ((defined('DEVBLOCKS_CACHE_PREFIX') && DEVBLOCKS_CACHE_PREFIX) ? DEVBLOCKS_CACHE_PREFIX : null), 
			);
			
			// Shared-memory cache
		    if((extension_loaded('memcache') || extension_loaded('memcached')) 
		    	&& defined('DEVBLOCKS_MEMCACHED_SERVERS') && DEVBLOCKS_MEMCACHED_SERVERS) {
		    	$pairs = DevblocksPlatform::parseCsvString(DEVBLOCKS_MEMCACHED_SERVERS);
		    	$servers = array();
		    	
		    	if(is_array($pairs) && !empty($pairs))
		    	foreach($pairs as $server) {
		    		list($host,$port) = explode(':',$server);
		    		
		    		if(empty($host) || empty($port))
		    			continue;
		    			
		    		$servers[] = array(
		    			'host'=>$host,
		    			'port'=>$port,
//		    			'persistent'=>true
		    		);
		    	}
		    	
				$options['servers'] = $servers;
				
				self::$_cacher = new _DevblocksCacheManagerMemcached($options);
		    }

		    // Disk-based cache (default)
		    if(null == self::$_cacher) {
		    	$options['cache_dir'] = APP_TEMP_PATH; 
				
				self::$_cacher = new _DevblocksCacheManagerDisk($options);
		    }
		}
		
		return self::$instance;
    }
    
	public function save($data, $key, $tags=array(), $lifetime=0) {
		// Monitor short-term cache memory usage
		@$this->_statistics[$key] = intval($this->_statistics[$key]);
		$this->_io_writes++;
		self::$_cacher->save($data, $key, $tags, $lifetime);
		$this->_registry[$key] = $data;
	}
	
	public function load($key, $nocache=false) {
		// Retrieving the long-term cache
		if($nocache || !isset($this->_registry[$key])) {
			if(false === ($this->_registry[$key] = self::$_cacher->load($key)))
				return NULL;
			
			@$this->_statistics[$key] = intval($this->_statistics[$key]) + 1;
			$this->_io_reads_long++;
			return $this->_registry[$key];
		}
		
		// Retrieving the short-term cache
		if(isset($this->_registry[$key])) {
			@$this->_statistics[$key] = intval($this->_statistics[$key]) + 1;
			$this->_io_reads_short++;
			return $this->_registry[$key];
		}
		
		return NULL;
	}
	
	public function remove($key) {
		unset($this->_registry[$key]);
		unset($this->_statistics[$key]);
		self::$_cacher->remove($key);
	}
	
	public function clean() { // $mode=null
		$this->_registry = array();
		$this->_statistics = array();
		
		self::$_cacher->clean();
	}
	
	public function printStatistics() {
		arsort($this->_statistics);
		print_r($this->_statistics);
		echo "<BR>";
		echo "Reads (short): ",$this->_io_reads_short,"<BR>";
		echo "Reads (long): ",$this->_io_reads_long,"<BR>";
		echo "Writes: ",$this->_io_writes,"<BR>";
	}
};

abstract class _DevblocksCacheManagerAbstract {
	protected $_options;
	protected $_prefix = 'devblocks_cache---';
	
	function __construct($options) {
		if(is_array($options))
			$this->_options = $options;
		
		// Key prefix
		if(!isset($this->_options['key_prefix']))
			$this->_options['key_prefix'] = '';
	}
	
	function save($data, $key, $tags=array(), $lifetime=0) {}
	function load($key) {}
	function remove($key) {}
	function clean() {} // $mode=null
};

class _DevblocksCacheManagerMemcached extends _DevblocksCacheManagerAbstract {
	private $_driver;
	
	function __construct($options) {
		parent::__construct($options);
		
		if(extension_loaded('memcached'))
			$this->_driver = new Memcached();
		elseif(extension_loaded('memcache'))
			$this->_driver = new Memcache();
		else
			die("PECL/Memcache or PECL/Memcached is not loaded.");
			
		// Check servers option
		if(!isset($this->_options['servers']) || !is_array($this->_options['servers']))
			die("_DevblocksCacheManagerMemcached requires the 'servers' option.");
			
		if(is_array($this->_options['servers']))
		foreach($this->_options['servers'] as $params) {
			$this->_driver->addServer($params['host'], $params['port']);
		}
	}
	
	function save($data, $key, $tags=array(), $lifetime=0) {
		$key = $this->_options['key_prefix'] . $key;
		return $this->_driver->set($key, $data, 0, $lifetime);
	}
	
	function load($key) {
		$key = $this->_options['key_prefix'] . $key;
		return $this->_driver->get($key);
	}
	
	function remove($key) {
		$key = $this->_options['key_prefix'] . $key;
		$this->_driver->delete($key);
	}
	
	function clean() {
		$this->_driver->flush();
	}
};

class _DevblocksCacheManagerDisk extends _DevblocksCacheManagerAbstract {
	function __construct($options) {
		parent::__construct($options);

		$path = $this->_getPath();
		
		if(null == $path)
			die("_DevblocksCacheManagerDisk requires the 'cache_dir' option.");

		// Ensure we have a trailing slash
		$this->_options['cache_dir'] = rtrim($path,"\\/") . DIRECTORY_SEPARATOR;
			
		if(!is_writeable($path))
			die("_DevblocksCacheManagerDisk requires write access to the 'path' directory ($path)");
	}
	
	private function _getPath() {
		return $this->_options['cache_dir'];
	}
	
	private function _getFilename($key) {
		$safe_key = preg_replace("/[^A-Za-z0-9_\-]/",'_', $key);
		return $this->_prefix . $safe_key;
	}
	
	function load($key) {
		$key = $this->_options['key_prefix'] . $key;
		return @unserialize(file_get_contents($this->_getPath() . $this->_getFilename($key)));
	}
	
	function save($data, $key, $tags=array(), $lifetime=0) {
		$key = $this->_options['key_prefix'] . $key;
		return file_put_contents($this->_getPath() . $this->_getFilename($key), serialize($data));
	}
	
	function remove($key) {
		$key = $this->_options['key_prefix'] . $key;
		$file = $this->_getPath() . $this->_getFilename($key);
		if(file_exists($file))
			unlink($file);
	}
	
	function clean() {
		$path = $this->_getPath();
		
		$files = scandir($path);
		unset($files['.']);
		unset($files['..']);
		
		if(is_array($files))
		foreach($files as $file) {
			if(0==strcmp('devblocks_cache',substr($file,0,15))) {
				unlink($path . $file);
			}
		}
		
	}	
};

class _DevblocksEventManager {
    private static $instance = null;
    
    private function __construct() {}

    /**
     * @return _DevblocksEventManager
     */
	public static function getInstance() {
		if(null == self::$instance) {
			self::$instance = new _DevblocksEventManager();
		}
		return self::$instance;
	}
	
	function trigger(Model_DevblocksEvent $event) {
	    /*
	     * [TODO] Look at the hash and spawn our listeners for this particular point
	     */
		$events = DevblocksPlatform::getEventRegistry();

		if(null == ($listeners = @$events[$event->id])) {
		    $listeners = array();
		}

		// [TODO] Make sure we can't get a double listener
	    if(is_array($events['*']))
	    foreach($events['*'] as $evt) {
	        $listeners[] = $evt;
	    }
		
		if(is_array($listeners) && !empty($listeners))
		foreach($listeners as $listener) { /* @var $listener DevblocksExtensionManifest */
			// Extensions can be invoked on these plugins even by workers who cannot see them
            if(null != ($manifest = DevblocksPlatform::getExtension($listener,false,true))) {
            	if(method_exists($manifest, 'createInstance')) {
		    		$inst = $manifest->createInstance(); /* @var $inst DevblocksEventListenerExtension */
		    		if($inst instanceof DevblocksEventListenerExtension)
            			$inst->handleEvent($event);
            	}
            }
		}
		
	}
};

/**
 * Email Management Singleton
 *
 * @static 
 * @ingroup services
 */
class _DevblocksEmailManager {
    private static $instance = null;
    
    private $mailers = array();
    
	/**
	 * @private
	 */
	private function __construct() {
		
	}
	
	/**
	 * Enter description here...
	 *
	 * @return _DevblocksEmailManager
	 */
	public static function getInstance() {
		if(null == self::$instance) {
			self::$instance = new _DevblocksEmailManager();
		}
		return self::$instance;
	}
	
	/**
	 * Enter description here...
	 *
	 * @return Swift_Message
	 */
	function createMessage() {
		return Swift_Message::newInstance();
	}
	
	/**
	 * @return Swift
	 */
	function getMailer($options) {

		// Options
		$smtp_host = isset($options['host']) ? $options['host'] : '127.0.0.1'; 
		$smtp_port = isset($options['port']) ? $options['port'] : '25'; 
		$smtp_user = isset($options['auth_user']) ? $options['auth_user'] : null; 
		$smtp_pass = isset($options['auth_pass']) ? $options['auth_pass'] : null; 
		$smtp_enc = isset($options['enc']) ? $options['enc'] : 'None'; 
		$smtp_max_sends = isset($options['max_sends']) ? intval($options['max_sends']) : 20; 
		$smtp_timeout = isset($options['timeout']) ? intval($options['timeout']) : 30; 
		
		/*
		 * [JAS]: We'll cache connection info hashed by params and hold a persistent 
		 * connection for the request cycle.  If we ask for the same params again 
		 * we'll get the existing connection if it exists.
		 */
		$hash = md5(sprintf("%s %s %s %s %s %d %d",
			$smtp_host,
			$smtp_user,
			$smtp_pass,
			$smtp_port,
			$smtp_enc,
			$smtp_max_sends,
			$smtp_timeout
		));
		
		if(!isset($this->mailers[$hash])) {
			// Encryption
			switch($smtp_enc) {
				case 'TLS':
					$smtp_enc = 'tls';
					break;
					
				case 'SSL':
					$smtp_enc = 'ssl';
					break;
					
				default:
					$smtp_enc = null;
					break;
			}
			
			$smtp = Swift_SmtpTransport::newInstance($smtp_host, $smtp_port, $smtp_enc);
			$smtp->setTimeout($smtp_timeout);
			
			if(!empty($smtp_user) && !empty($smtp_pass)) {
				$smtp->setUsername($smtp_user);
				$smtp->setPassword($smtp_pass);
			}
			
			$mailer = Swift_Mailer::newInstance($smtp);
			$mailer->registerPlugin(new Swift_Plugins_AntiFloodPlugin($smtp_max_sends,1));
			
			$this->mailers[$hash] =& $mailer;
		}

		return $this->mailers[$hash];
	}
	
	function testImap($server, $port, $service, $username, $password) {
		if (!extension_loaded("imap")) die("IMAP Extension not loaded!");
		
        switch($service) {
            default:
            case 'pop3': // 110
                $connect = sprintf("{%s:%d/pop3/notls}INBOX",
                $server,
                $port
                );
                break;
                 
            case 'pop3-ssl': // 995
                $connect = sprintf("{%s:%d/pop3/ssl/novalidate-cert}INBOX",
                $server,
                $port
                );
                break;
                 
            case 'imap': // 143
                $connect = sprintf("{%s:%d/notls}INBOX",
                $server,
                $port
                );
                break;
                
            case 'imap-ssl': // 993
                $connect = sprintf("{%s:%d/imap/ssl/novalidate-cert}INBOX",
                $server,
                $port
                );
                break;
        }
		
		@$mailbox = imap_open(
			$connect,
			!empty($username)?$username:"superuser",
			!empty($password)?$password:"superuser"
		);

		if($mailbox === FALSE)
			return FALSE;
		
		@imap_close($mailbox);
			
		return TRUE;
	}
	
	/**
	 * @return array
	 */
	function getErrors() {
		return imap_errors();
	}
	
}

class _DevblocksDateManager {
	private function __construct() {}
	
	/**
	 * 
	 * @return _DevblocksDateManager
	 */
	static function getInstance() {
		static $instance = null;
		
		if(null == $instance) {
			$instance = new _DevblocksDateManager();
		}
		
		return $instance;
	}
	
	public function formatTime($format, $timestamp) {
		try {
			if(is_numeric($timestamp))
				$timestamp = intval($timestamp);
			else
				$timestamp = strtotime($time);
		} catch (Exception $e) {
			$timestamp = time();
		}
		
		if(empty($format)) {
			return strftime('%a, %d %b %Y %H:%M:%S %z', $timestamp);
		} else {
			return strftime($format, $timestamp);
		}
	}
	
	public function getTimezones() {
		return array(
			'Africa/Abidjan',
			'Africa/Accra',
			'Africa/Addis_Ababa',
			'Africa/Algiers',
			'Africa/Asmera',
			'Africa/Bamako',
			'Africa/Bangui',
			'Africa/Banjul',
			'Africa/Bissau',
			'Africa/Blantyre',
			'Africa/Brazzaville',
			'Africa/Bujumbura',
			'Africa/Cairo',
			'Africa/Casablanca',
			'Africa/Ceuta',
			'Africa/Conakry',
			'Africa/Dakar',
			'Africa/Dar_es_Salaam',
			'Africa/Djibouti',
			'Africa/Douala',
			'Africa/El_Aaiun',
			'Africa/Freetown',
			'Africa/Gaborone',
			'Africa/Harare',
			'Africa/Johannesburg',
			'Africa/Kampala',
			'Africa/Khartoum',
			'Africa/Kigali',
			'Africa/Kinshasa',
			'Africa/Lagos',
			'Africa/Libreville',
			'Africa/Lome',
			'Africa/Luanda',
			'Africa/Lubumbashi',
			'Africa/Lusaka',
			'Africa/Malabo',
			'Africa/Maputo',
			'Africa/Maseru',
			'Africa/Mbabane',
			'Africa/Mogadishu',
			'Africa/Monrovia',
			'Africa/Nairobi',
			'Africa/Ndjamena',
			'Africa/Niamey',
			'Africa/Nouakchott',
			'Africa/Ouagadougou',
			'Africa/Porto-Novo',
			'Africa/Sao_Tome',
			'Africa/Timbuktu',
			'Africa/Tripoli',
			'Africa/Tunis',
			'Africa/Windhoek',
			'America/Adak',
			'America/Anchorage',
			'America/Anguilla',
			'America/Antigua',
			'America/Araguaina',
			'America/Aruba',
			'America/Asuncion',
			'America/Barbados',
			'America/Belem',
			'America/Belize',
			'America/Bogota',
			'America/Boise',
			'America/Buenos_Aires',
			'America/Cancun',
			'America/Caracas',
			'America/Catamarca',
			'America/Cayenne',
			'America/Cayman',
			'America/Chicago',
			'America/Chihuahua',
			'America/Cordoba',
			'America/Costa_Rica',
			'America/Cuiaba',
			'America/Curacao',
			'America/Dawson',
			'America/Dawson_Creek',
			'America/Denver',
			'America/Detroit',
			'America/Dominica',
			'America/Edmonton',
			'America/El_Salvador',
			'America/Ensenada',
			'America/Fortaleza',
			'America/Glace_Bay',
			'America/Godthab',
			'America/Goose_Bay',
			'America/Grand_Turk',
			'America/Grenada',
			'America/Guadeloupe',
			'America/Guatemala',
			'America/Guayaquil',
			'America/Guyana',
			'America/Halifax',
			'America/Havana',
			'America/Indiana/Knox',
			'America/Indiana/Marengo',
			'America/Indiana/Vevay',
			'America/Indianapolis',
			'America/Inuvik',
			'America/Iqaluit',
			'America/Jamaica',
			'America/Jujuy',
			'America/Juneau',
			'America/La_Paz',
			'America/Lima',
			'America/Los_Angeles',
			'America/Louisville',
			'America/Maceio',
			'America/Managua',
			'America/Manaus',
			'America/Martinique',
			'America/Mazatlan',
			'America/Mendoza',
			'America/Menominee',
			'America/Mexico_City',
			'America/Miquelon',
			'America/Montevideo',
			'America/Montreal',
			'America/Montserrat',
			'America/Nassau',
			'America/New_York',
			'America/Nipigon',
			'America/Nome',
			'America/Noronha',
			'America/Panama',
			'America/Pangnirtung',
			'America/Paramaribo',
			'America/Phoenix',
			'America/Port-au-Prince',
			'America/Port_of_Spain',
			'America/Porto_Acre',
			'America/Porto_Velho',
			'America/Puerto_Rico',
			'America/Rainy_River',
			'America/Rankin_Inlet',
			'America/Regina',
			'America/Rosario',
			'America/Santiago',
			'America/Santo_Domingo',
			'America/Sao_Paulo',
			'America/Scoresbysund',
			'America/Shiprock',
			'America/St_Johns',
			'America/St_Kitts',
			'America/St_Lucia',
			'America/St_Thomas',
			'America/St_Vincent',
			'America/Swift_Current',
			'America/Tegucigalpa',
			'America/Thule',
			'America/Thunder_Bay',
			'America/Tijuana',
			'America/Tortola',
			'America/Vancouver',
			'America/Whitehorse',
			'America/Winnipeg',
			'America/Yakutat',
			'America/Yellowknife',
			'Antarctica/Casey',
			'Antarctica/Davis',
			'Antarctica/DumontDUrville',
			'Antarctica/Mawson',
			'Antarctica/McMurdo',
			'Antarctica/Palmer',
			'Antarctica/South_Pole',
			'Arctic/Longyearbyen',
			'Asia/Aden',
			'Asia/Almaty',
			'Asia/Amman',
			'Asia/Anadyr',
			'Asia/Aqtau',
			'Asia/Aqtobe',
			'Asia/Ashkhabad',
			'Asia/Baghdad',
			'Asia/Bahrain',
			'Asia/Baku',
			'Asia/Bangkok',
			'Asia/Beirut',
			'Asia/Bishkek',
			'Asia/Brunei',
			'Asia/Calcutta',
			'Asia/Chungking',
			'Asia/Colombo',
			'Asia/Dacca',
			'Asia/Damascus',
			'Asia/Dubai',
			'Asia/Dushanbe',
			'Asia/Gaza',
			'Asia/Harbin',
			'Asia/Hong_Kong',
			'Asia/Irkutsk',
			'Asia/Jakarta',
			'Asia/Jayapura',
			'Asia/Jerusalem',
			'Asia/Kabul',
			'Asia/Kamchatka',
			'Asia/Karachi',
			'Asia/Kashgar',
			'Asia/Katmandu',
			'Asia/Krasnoyarsk',
			'Asia/Kuala_Lumpur',
			'Asia/Kuching',
			'Asia/Kuwait',
			'Asia/Macao',
			'Asia/Magadan',
			'Asia/Manila',
			'Asia/Muscat',
			'Asia/Nicosia',
			'Asia/Novosibirsk',
			'Asia/Omsk',
			'Asia/Phnom_Penh',
			'Asia/Pyongyang',
			'Asia/Qatar',
			'Asia/Rangoon',
			'Asia/Riyadh',
			'Asia/Saigon',
			'Asia/Samarkand',
			'Asia/Seoul',
			'Asia/Shanghai',
			'Asia/Singapore',
			'Asia/Taipei',
			'Asia/Tashkent',
			'Asia/Tbilisi',
			'Asia/Tehran',
			'Asia/Thimbu',
			'Asia/Tokyo',
			'Asia/Ujung_Pandang',
			'Asia/Ulan_Bator',
			'Asia/Urumqi',
			'Asia/Vientiane',
			'Asia/Vladivostok',
			'Asia/Yakutsk',
			'Asia/Yekaterinburg',
			'Asia/Yerevan',
			'Atlantic/Azores',
			'Atlantic/Bermuda',
			'Atlantic/Canary',
			'Atlantic/Cape_Verde',
			'Atlantic/Faeroe',
			'Atlantic/Jan_Mayen',
			'Atlantic/Madeira',
			'Atlantic/Reykjavik',
			'Atlantic/South_Georgia',
			'Atlantic/St_Helena',
			'Atlantic/Stanley',
			'Australia/Adelaide',
			'Australia/Brisbane',
			'Australia/Broken_Hill',
			'Australia/Darwin',
			'Australia/Hobart',
			'Australia/Lindeman',
			'Australia/Lord_Howe',
			'Australia/Melbourne',
			'Australia/Perth',
			'Australia/Sydney',
			'Europe/Amsterdam',
			'Europe/Andorra',
			'Europe/Athens',
			'Europe/Belfast',
			'Europe/Belgrade',
			'Europe/Berlin',
			'Europe/Bratislava',
			'Europe/Brussels',
			'Europe/Bucharest',
			'Europe/Budapest',
			'Europe/Chisinau',
			'Europe/Copenhagen',
			'Europe/Dublin',
			'Europe/Gibraltar',
			'Europe/Helsinki',
			'Europe/Istanbul',
			'Europe/Kaliningrad',
			'Europe/Kiev',
			'Europe/Lisbon',
			'Europe/Ljubljana',
			'Europe/London',
			'Europe/Luxembourg',
			'Europe/Madrid',
			'Europe/Malta',
			'Europe/Minsk',
			'Europe/Monaco',
			'Europe/Moscow',
			'Europe/Oslo',
			'Europe/Paris',
			'Europe/Prague',
			'Europe/Riga',
			'Europe/Rome',
			'Europe/Samara',
			'Europe/San_Marino',
			'Europe/Sarajevo',
			'Europe/Simferopol',
			'Europe/Skopje',
			'Europe/Sofia',
			'Europe/Stockholm',
			'Europe/Tallinn',
			'Europe/Tirane',
			'Europe/Vaduz',
			'Europe/Vatican',
			'Europe/Vienna',
			'Europe/Vilnius',
			'Europe/Warsaw',
			'Europe/Zagreb',
			'Europe/Zurich',
			'Indian/Antananarivo',
			'Indian/Chagos',
			'Indian/Christmas',
			'Indian/Cocos',
			'Indian/Comoro',
			'Indian/Kerguelen',
			'Indian/Mahe',
			'Indian/Maldives',
			'Indian/Mauritius',
			'Indian/Mayotte',
			'Indian/Reunion',
			'Pacific/Apia',
			'Pacific/Auckland',
			'Pacific/Chatham',
			'Pacific/Easter',
			'Pacific/Efate',
			'Pacific/Enderbury',
			'Pacific/Fakaofo',
			'Pacific/Fiji',
			'Pacific/Funafuti',
			'Pacific/Galapagos',
			'Pacific/Gambier',
			'Pacific/Guadalcanal',
			'Pacific/Guam',
			'Pacific/Honolulu',
			'Pacific/Johnston',
			'Pacific/Kiritimati',
			'Pacific/Kosrae',
			'Pacific/Kwajalein',
			'Pacific/Majuro',
			'Pacific/Marquesas',
			'Pacific/Midway',
			'Pacific/Nauru',
			'Pacific/Niue',
			'Pacific/Norfolk',
			'Pacific/Noumea',
			'Pacific/Pago_Pago',
			'Pacific/Palau',
			'Pacific/Pitcairn',
			'Pacific/Ponape',
			'Pacific/Port_Moresby',
			'Pacific/Rarotonga',
			'Pacific/Saipan',
			'Pacific/Tahiti',
			'Pacific/Tarawa',
			'Pacific/Tongatapu',
			'Pacific/Truk',
			'Pacific/Wake',
			'Pacific/Wallis',
			'Pacific/Yap',
		);
	}
}

class _DevblocksTranslationManager {
	private $_locales = array();
	private $_locale = 'en_US';
	
	private function __construct() {}
	
	static function getInstance() {
		static $instance = null;
		
		if(null == $instance) {
			$instance = new _DevblocksTranslationManager();
		}
		
		return $instance;
	}
	
	public function addLocale($locale, $strings) {
		$this->_locales[$locale] = $strings;
	}
	
	public function setLocale($locale) {
		if(isset($this->_locales[$locale]))
			$this->_locale = $locale;
	}
	
	public function _($token) {
		if(isset($this->_locales[$this->_locale][$token]))
			return $this->_locales[$this->_locale][$token];
		
		// [JAS] Make it easy to find things that don't translate
		//return '$'.$token.'('.$this->_locale.')';
		
		return $token;
	}
	
	public function getLocaleCodes() {
		return array(
			'af_ZA',
			'am_ET',
			'be_BY',
			'bg_BG',
			'ca_ES',
			'cs_CZ',
			'da_DK',
			'de_AT',
			'de_CH',
			'de_DE',
			'el_GR',
			'en_AU',
			'en_CA',
			'en_GB',
			'en_IE',
			'en_NZ',
			'en_US',
			'es_ES',
			'es_MX',
			'et_EE',
			'eu_ES',
			'fi_FI',
			'fr_BE',
			'fr_CA',
			'fr_CH',
			'fr_FR',
			'he_IL',
			'hr_HR',
			'hu_HU',
			'hy_AM',
			'is_IS',
			'it_CH',
			'it_IT',
			'ja_JP',
			'kk_KZ',
			'ko_KR',
			'lt_LT',
			'nl_BE',
			'nl_NL',
			'no_NO',
			'pl_PL',
			'pt_BR',
			'pt_PT',
			'ro_RO',
			'ru_RU',
			'sk_SK',
			'sl_SI',
			'sr_RS',
			'sv_SE',
			'tr_TR',
			'uk_UA',
			'zh_CN',
			'zh_HK',
			'zh_TW',
		);
	}
	
	function getLocaleStrings() {
		$codes = $this->getLocaleCodes();
		$langs = $this->getLanguageCodes();
		$countries = $this->getCountryCodes();
		
		$lang_codes = array();
		
		if(is_array($codes))
		foreach($codes as $code) {
			$data = explode('_', $code);
			@$lang = $langs[strtolower($data[0])];
			@$terr = $countries[strtoupper($data[1])];

			$lang_codes[$code] = (!empty($lang) && !empty($terr))
				? ($lang . ' (' . $terr . ')')
				: $code;
		}
		
		asort($lang_codes);
		
		unset($codes);
		unset($langs);
		unset($countries);
		
		return $lang_codes;
	}
	
	function getLanguageCodes() {
		return array(
			'aa' => "Afar",
			'ab' => "Abkhazian",
			'ae' => "Avestan",
			'af' => "Afrikaans",
			'am' => "Amharic",
			'an' => "Aragonese",
			'ar' => "Arabic",
			'as' => "Assamese",
			'ay' => "Aymara",
			'az' => "Azerbaijani",
			'ba' => "Bashkir",
			'be' => "Belarusian",
			'bg' => "Bulgarian",
			'bh' => "Bihari",
			'bi' => "Bislama",
			'bn' => "Bengali",
			'bo' => "Tibetan",
			'br' => "Breton",
			'bs' => "Bosnian",
			'ca' => "Catalan",
			'ce' => "Chechen",
			'ch' => "Chamorro",
			'co' => "Corsican",
			'cs' => "Czech",
			'cu' => "Church Slavic; Slavonic; Old Bulgarian",
			'cv' => "Chuvash",
			'cy' => "Welsh",
			'da' => "Danish",
			'de' => "German",
			'dv' => "Divehi; Dhivehi; Maldivian",
			'dz' => "Dzongkha",
			'el' => "Greek, Modern",
			'en' => "English",
			'eo' => "Esperanto",
			'es' => "Spanish; Castilian",
			'et' => "Estonian",
			'eu' => "Basque",
			'fa' => "Persian",
			'fi' => "Finnish",
			'fj' => "Fijian",
			'fo' => "Faroese",
			'fr' => "French",
			'fy' => "Western Frisian",
			'ga' => "Irish",
			'gd' => "Gaelic; Scottish Gaelic",
			'gl' => "Galician",
			'gn' => "Guarani",
			'gu' => "Gujarati",
			'gv' => "Manx",
			'ha' => "Hausa",
			'he' => "Hebrew",
			'hi' => "Hindi",
			'ho' => "Hiri Motu",
			'hr' => "Croatian",
			'ht' => "Haitian; Haitian Creole ",
			'hu' => "Hungarian",
			'hy' => "Armenian",
			'hz' => "Herero",
			'ia' => "Interlingua",
			'id' => "Indonesian",
			'ie' => "Interlingue",
			'ii' => "Sichuan Yi",
			'ik' => "Inupiaq",
			'io' => "Ido",
			'is' => "Icelandic",
			'it' => "Italian",
			'iu' => "Inuktitut",
			'ja' => "Japanese",
			'jv' => "Javanese",
			'ka' => "Georgian",
			'ki' => "Kikuyu; Gikuyu",
			'kj' => "Kuanyama; Kwanyama",
			'kk' => "Kazakh",
			'kl' => "Kalaallisut",
			'km' => "Khmer",
			'kn' => "Kannada",
			'ko' => "Korean",
			'ks' => "Kashmiri",
			'ku' => "Kurdish",
			'kv' => "Komi",
			'kw' => "Cornish",
			'ky' => "Kirghiz",
			'la' => "Latin",
			'lb' => "Luxembourgish; Letzeburgesch",
			'li' => "Limburgan; Limburger; Limburgish",
			'ln' => "Lingala",
			'lo' => "Lao",
			'lt' => "Lithuanian",
			'lv' => "Latvian",
			'mg' => "Malagasy",
			'mh' => "Marshallese",
			'mi' => "Maori",
			'mk' => "Macedonian",
			'ml' => "Malayalam",
			'mn' => "Mongolian",
			'mo' => "Moldavian",
			'mr' => "Marathi",
			'ms' => "Malay",
			'mt' => "Maltese",
			'my' => "Burmese",
			'na' => "Nauru",
			'nb' => "Norwegian Bokmal",
			'nd' => "Ndebele, North",
			'ne' => "Nepali",
			'ng' => "Ndonga",
			'nl' => "Dutch",
			'nn' => "Norwegian Nynorsk",
			'no' => "Norwegian",
			'nr' => "Ndebele, South",
			'nv' => "Navaho, Navajo",
			'ny' => "Nyanja; Chichewa; Chewa",
			'oc' => "Occitan; Provencal",
			'om' => "Oromo",
			'or' => "Oriya",
			'os' => "Ossetian; Ossetic",
			'pa' => "Panjabi",
			'pi' => "Pali",
			'pl' => "Polish",
			'ps' => "Pushto",
			'pt' => "Portuguese",
			'qu' => "Quechua",
			'rm' => "Raeto-Romance",
			'rn' => "Rundi",
			'ro' => "Romanian",
			'ru' => "Russian",
			'rw' => "Kinyarwanda",
			'sa' => "Sanskrit",
			'sc' => "Sardinian",
			'sd' => "Sindhi",
			'se' => "Northern Sami",
			'sg' => "Sango",
			'si' => "Sinhala; Sinhalese",
			'sk' => "Slovak",
			'sl' => "Slovenian",
			'sm' => "Samoan",
			'sn' => "Shona",
			'so' => "Somali",
			'sq' => "Albanian",
			'sr' => "Serbian",
			'ss' => "Swati",
			'st' => "Sotho, Southern",
			'su' => "Sundanese",
			'sv' => "Swedish",
			'sw' => "Swahili",
			'ta' => "Tamil",
			'te' => "Telugu",
			'tg' => "Tajik",
			'th' => "Thai",
			'ti' => "Tigrinya",
			'tk' => "Turkmen",
			'tl' => "Tagalog",
			'tn' => "Tswana",
			'to' => "Tonga",
			'tr' => "Turkish",
			'ts' => "Tsonga",
			'tt' => "Tatar",
			'tw' => "Twi",
			'ty' => "Tahitian",
			'ug' => "Uighur",
			'uk' => "Ukrainian",
			'ur' => "Urdu",
			'uz' => "Uzbek",
			'vi' => "Vietnamese",
			'vo' => "Volapuk",
			'wa' => "Walloon",
			'wo' => "Wolof",
			'xh' => "Xhosa",
			'yi' => "Yiddish",
			'yo' => "Yoruba",
			'za' => "Zhuang; Chuang",
			'zh' => "Chinese",
			'zu' => "Zulu",
		);
	}
	
	function getCountryCodes() {
		return array(
			'AD' => "Andorra",
			'AE' => "United Arab Emirates",
			'AF' => "Afghanistan",
			'AG' => "Antigua and Barbuda",
			'AI' => "Anguilla",
			'AL' => "Albania",
			'AM' => "Armenia",
			'AN' => "Netherlands Antilles",
			'AO' => "Angola",
			'AQ' => "Antarctica",
			'AR' => "Argentina",
			'AS' => "American Samoa",
			'AT' => "Austria",
			'AU' => "Australia",
			'AW' => "Aruba",
			'AX' => "Aland Islands",
			'AZ' => "Azerbaijan",
			'BA' => "Bosnia and Herzegovina",
			'BB' => "Barbados",
			'BD' => "Bangladesh",
			'BE' => "Belgium",
			'BF' => "Burkina Faso",
			'BG' => "Bulgaria",
			'BH' => "Bahrain",
			'BI' => "Burundi",
			'BJ' => "Benin",
			'BL' => "Saint Barthélemy",
			'BM' => "Bermuda",
			'BN' => "Brunei Darussalam",
			'BO' => "Bolivia",
			'BR' => "Brazil",
			'BS' => "Bahamas",
			'BT' => "Bhutan",
			'BV' => "Bouvet Island",
			'BW' => "Botswana",
			'BY' => "Belarus",
			'BZ' => "Belize",
			'CA' => "Canada",
			'CC' => "Cocos (Keeling) Islands",
			'CD' => "Congo, the Democratic Republic of the",
			'CF' => "Central African Republic",
			'CG' => "Congo",
			'CH' => "Switzerland",
			'CI' => "Cote d'Ivoire Côte d'Ivoire",
			'CK' => "Cook Islands",
			'CL' => "Chile",
			'CM' => "Cameroon",
			'CN' => "China",
			'CO' => "Colombia",
			'CR' => "Costa Rica",
			'CU' => "Cuba",
			'CV' => "Cape Verde",
			'CX' => "Christmas Island",
			'CY' => "Cyprus",
			'CZ' => "Czech Republic",
			'DE' => "Germany",
			'DJ' => "Djibouti",
			'DK' => "Denmark",
			'DM' => "Dominica",
			'DO' => "Dominican Republic",
			'DZ' => "Algeria",
			'EC' => "Ecuador",
			'EE' => "Estonia",
			'EG' => "Egypt",
			'EH' => "Western Sahara",
			'ER' => "Eritrea",
			'ES' => "Spain",
			'ET' => "Ethiopia",
			'FI' => "Finland",
			'FJ' => "Fiji",
			'FK' => "Falkland Islands (Malvinas)",
			'FM' => "Micronesia, Federated States of",
			'FO' => "Faroe Islands",
			'FR' => "France",
			'GA' => "Gabon",
			'GB' => "United Kingdom",
			'GD' => "Grenada",
			'GE' => "Georgia",
			'GF' => "French Guiana",
			'GG' => "Guernsey",
			'GH' => "Ghana",
			'GI' => "Gibraltar",
			'GL' => "Greenland",
			'GM' => "Gambia",
			'GN' => "Guinea",
			'GP' => "Guadeloupe",
			'GQ' => "Equatorial Guinea",
			'GR' => "Greece",
			'GS' => "South Georgia and the South Sandwich Islands",
			'GT' => "Guatemala",
			'GU' => "Guam",
			'GW' => "Guinea-Bissau",
			'GY' => "Guyana",
			'HK' => "Hong Kong",
			'HM' => "Heard Island and McDonald Islands",
			'HN' => "Honduras",
			'HR' => "Croatia",
			'HT' => "Haiti",
			'HU' => "Hungary",
			'ID' => "Indonesia",
			'IE' => "Ireland",
			'IL' => "Israel",
			'IM' => "Isle of Man",
			'IN' => "India",
			'IO' => "British Indian Ocean Territory",
			'IQ' => "Iraq",
			'IR' => "Iran, Islamic Republic of",
			'IS' => "Iceland",
			'IT' => "Italy",
			'JE' => "Jersey",
			'JM' => "Jamaica",
			'JO' => "Jordan",
			'JP' => "Japan",
			'KE' => "Kenya",
			'KG' => "Kyrgyzstan",
			'KH' => "Cambodia",
			'KI' => "Kiribati",
			'KM' => "Comoros",
			'KN' => "Saint Kitts and Nevis",
			'KP' => "Korea, Democratic People's Republic of",
			'KR' => "Korea, Republic of",
			'KW' => "Kuwait",
			'KY' => "Cayman Islands",
			'KZ' => "Kazakhstan",
			'LA' => "Lao People's Democratic Republic",
			'LB' => "Lebanon",
			'LC' => "Saint Lucia",
			'LI' => "Liechtenstein",
			'LK' => "Sri Lanka",
			'LR' => "Liberia",
			'LS' => "Lesotho",
			'LT' => "Lithuania",
			'LU' => "Luxembourg",
			'LV' => "Latvia",
			'LY' => "Libyan Arab Jamahiriya",
			'MA' => "Morocco",
			'MC' => "Monaco",
			'MD' => "Moldova, Republic of",
			'ME' => "Montenegro",
			'MF' => "Saint Martin (French part)",
			'MG' => "Madagascar",
			'MH' => "Marshall Islands",
			'MK' => "Macedonia, the former Yugoslav Republic of",
			'ML' => "Mali",
			'MM' => "Myanmar",
			'MN' => "Mongolia",
			'MO' => "Macao",
			'MP' => "Northern Mariana Islands",
			'MQ' => "Martinique",
			'MR' => "Mauritania",
			'MS' => "Montserrat",
			'MT' => "Malta",
			'MU' => "Mauritius",
			'MV' => "Maldives",
			'MW' => "Malawi",
			'MX' => "Mexico",
			'MY' => "Malaysia",
			'MZ' => "Mozambique",
			'NA' => "Namibia",
			'NC' => "New Caledonia",
			'NE' => "Niger",
			'NF' => "Norfolk Island",
			'NG' => "Nigeria",
			'NI' => "Nicaragua",
			'NL' => "Netherlands",
			'NO' => "Norway",
			'NP' => "Nepal",
			'NR' => "Nauru",
			'NU' => "Niue",
			'NZ' => "New Zealand",
			'OM' => "Oman",
			'PA' => "Panama",
			'PE' => "Peru",
			'PF' => "French Polynesia",
			'PG' => "Papua New Guinea",
			'PH' => "Philippines",
			'PK' => "Pakistan",
			'PL' => "Poland",
			'PM' => "Saint Pierre and Miquelon",
			'PN' => "Pitcairn",
			'PR' => "Puerto Rico",
			'PS' => "Palestinian Territory, Occupied",
			'PT' => "Portugal",
			'PW' => "Palau",
			'PY' => "Paraguay",
			'QA' => "Qatar",
			'RE' => "Reunion Réunion",
			'RO' => "Romania",
			'RS' => "Serbia",
			'RU' => "Russian Federation",
			'RW' => "Rwanda",
			'SA' => "Saudi Arabia",
			'SB' => "Solomon Islands",
			'SC' => "Seychelles",
			'SD' => "Sudan",
			'SE' => "Sweden",
			'SG' => "Singapore",
			'SH' => "Saint Helena",
			'SI' => "Slovenia",
			'SJ' => "Svalbard and Jan Mayen",
			'SK' => "Slovakia",
			'SL' => "Sierra Leone",
			'SM' => "San Marino",
			'SN' => "Senegal",
			'SO' => "Somalia",
			'SR' => "Suriname",
			'ST' => "Sao Tome and Principe",
			'SV' => "El Salvador",
			'SY' => "Syrian Arab Republic",
			'SZ' => "Swaziland",
			'TC' => "Turks and Caicos Islands",
			'TD' => "Chad",
			'TF' => "French Southern Territories",
			'TG' => "Togo",
			'TH' => "Thailand",
			'TJ' => "Tajikistan",
			'TK' => "Tokelau",
			'TL' => "Timor-Leste",
			'TM' => "Turkmenistan",
			'TN' => "Tunisia",
			'TO' => "Tonga",
			'TR' => "Turkey",
			'TT' => "Trinidad and Tobago",
			'TV' => "Tuvalu",
			'TW' => "Taiwan, Province of China",
			'TZ' => "Tanzania, United Republic of",
			'UA' => "Ukraine",
			'UG' => "Uganda",
			'UM' => "United States Minor Outlying Islands",
			'US' => "United States",
			'UY' => "Uruguay",
			'UZ' => "Uzbekistan",
			'VA' => "Holy See (Vatican City State)",
			'VC' => "Saint Vincent and the Grenadines",
			'VE' => "Venezuela",
			'VG' => "Virgin Islands, British",
			'VI' => "Virgin Islands, U.S.",
			'VN' => "Viet Nam",
			'VU' => "Vanuatu",
			'WF' => "Wallis and Futuna",
			'WS' => "Samoa",
			'YE' => "Yemen",
			'YT' => "Mayotte",
			'ZA' => "South Africa",
			'ZM' => "Zambia",
			'ZW' => "Zimbabwe",
		);
	}
}

/**
 * Smarty Template Manager Singleton
 *
 * @ingroup services
 */
class _DevblocksTemplateManager {
	/**
	 * Constructor
	 * 
	 * @private
	 */
	private function _DevblocksTemplateManager() {}
	/**
	 * Returns an instance of the Smarty Template Engine
	 * 
	 * @static 
	 * @return Smarty
	 */
	static function getInstance() {
		static $instance = null;
		if(null == $instance) {
			define('SMARTY_RESOURCE_CHAR_SET', LANG_CHARSET_CODE);
			require(DEVBLOCKS_PATH . 'libs/smarty/Smarty.class.php');

			$instance = new Smarty();
			
			$instance->template_dir = APP_PATH . '/templates';
			$instance->compile_dir = APP_TEMP_PATH . '/templates_c';
			$instance->cache_dir = APP_TEMP_PATH . '/cache';

			$instance->caching = 0;
			$instance->cache_lifetime = 0;
			$instance->compile_check = (defined('DEVELOPMENT_MODE') && DEVELOPMENT_MODE) ? true : false;
			
			// Devblocks plugins
			$instance->register_block('devblocks_url', array('_DevblocksTemplateManager', 'block_devblocks_url'));
			$instance->register_modifier('devblocks_date', array('_DevblocksTemplateManager', 'modifier_devblocks_date'));
			$instance->register_modifier('devblocks_prettytime', array('_DevblocksTemplateManager', 'modifier_devblocks_prettytime'));
			$instance->register_modifier('devblocks_translate', array('_DevblocksTemplateManager', 'modifier_devblocks_translate'));
			$instance->register_resource('devblocks', array(
				array('_DevblocksSmartyTemplateResource', 'get_template'),
				array('_DevblocksSmartyTemplateResource', 'get_timestamp'),
				array('_DevblocksSmartyTemplateResource', 'get_secure'),
				array('_DevblocksSmartyTemplateResource', 'get_trusted'),
			));
		}
		return $instance;
	}

	static function modifier_devblocks_translate($string) {
		$translate = DevblocksPlatform::getTranslationService();
		
		// Variable number of arguments
		$args = func_get_args();
		array_shift($args); // pop off $string
		
		$translated = $translate->_($string);
		$translated = @vsprintf($translated,$args);
		return $translated;
	}
	
	static function block_devblocks_url($params, $content, $smarty, $repeat, $smarty_tpl) {
		$url = DevblocksPlatform::getUrlService();
		
		$contents = $url->write($content, !empty($params['full']) ? true : false);
		
	    if (!empty($params['assign'])) {
	        $smarty->assign($params['assign'], $contents);
	    } else {
	        return $contents;
	    }
	}
	
	static function modifier_devblocks_date($string, $format=null) {
		if(empty($string))
			return '';
	
		$date = DevblocksPlatform::getDateService();
		return $date->formatTime($format, $string);
	}
	
	static function modifier_devblocks_prettytime($string, $format=null) {
		if(empty($string) || !is_numeric($string))
			return '';
		
		$diffsecs = time() - intval($string);
		$whole = '';		
		
		// The past
		if($diffsecs >= 0) {
			if($diffsecs >= 86400) { // days
				$whole = floor($diffsecs/86400).'d ago';
			} elseif($diffsecs >= 3600) { // hours
				$whole = floor($diffsecs/3600).'h ago';
			} elseif($diffsecs >= 60) { // mins
				$whole = floor($diffsecs/60).'m ago';
			} elseif($diffsecs >= 0) { // secs
				$whole = $diffsecs.'s ago';
			}
		} else { // The future
			if($diffsecs <= -86400) { // days
				$whole = floor($diffsecs/-86400).'d';
			} elseif($diffsecs <= -3600) { // hours
				$whole = floor($diffsecs/-3600).'h';
			} elseif($diffsecs <= -60) { // mins
				$whole = floor($diffsecs/-60).'m';
			} elseif($diffsecs <= 0) { // secs
				$whole = $diffsecs.'s';
			}
		}
		
		echo $whole;
	}	
};

class _DevblocksSmartyTemplateResource {
	static function get_template($tpl_name, &$tpl_source, $smarty_obj) {
		list($plugin_id, $tpl_path, $tag) = explode(':',$tpl_name,3);
		
		if(empty($plugin_id) || empty($tpl_path))
			return false;
			
		$plugins = DevblocksPlatform::getPluginRegistry();
		$db = DevblocksPlatform::getDatabaseService();
			
		if(null == ($plugin = @$plugins[$plugin_id])) /* @var $plugin DevblocksPluginManifest */
			return false;
			
		// Check if template is overloaded in DB/cache
		$matches = DAO_DevblocksTemplate::getWhere(sprintf("plugin_id = %s AND path = %s %s",
			$db->qstr($plugin_id),
			$db->qstr($tpl_path),
			(!empty($tag) ? sprintf("AND tag = %s ",$db->qstr($tag)) : "")
		));
			
		if(!empty($matches)) {
			$match = array_shift($matches); /* @var $match Model_DevblocksTemplate */
			$tpl_source = $match->content;
			return true;
		}
		
		// If not in DB, check plugin's relative path on disk
		$path = APP_PATH . '/' . $plugin->dir . '/templates/' . $tpl_path;
		
		if(!file_exists($path))
			return false;
		
		$tpl_source = file_get_contents($path);
		return true;			
	}
	
	static function get_timestamp($tpl_name, &$tpl_timestamp, $smarty_obj) { /* @var $smarty_obj Smarty */
		list($plugin_id, $tpl_path, $tag) = explode(':',$tpl_name,3);
		
		if(empty($plugin_id) || empty($tpl_path))
			return false;
		
//		echo $tpl_name,"<BR>";
			
		$plugins = DevblocksPlatform::getPluginRegistry();
		$db = DevblocksPlatform::getDatabaseService();
			
		if(null == ($plugin = @$plugins[$plugin_id])) /* @var $plugin DevblocksPluginManifest */
			return false;
			
		// Check if template is overloaded in DB/cache
		$matches = DAO_DevblocksTemplate::getWhere(sprintf("plugin_id = %s AND path = %s %s",
			$db->qstr($plugin_id),
			$db->qstr($tpl_path),
			(!empty($tag) ? sprintf("AND tag = %s ",$db->qstr($tag)) : "")
		));

		if(!empty($matches)) {
			$match = array_shift($matches); /* @var $match Model_DevblocksTemplate */
//			echo time(),"==(DB)",$match->last_updated,"<BR>";
			$tpl_timestamp = $match->last_updated;
			return true; 
		}
			
		// If not in DB, check plugin's relative path on disk
		$path = APP_PATH . '/' . $plugin->dir . '/templates/' . $tpl_path;
		
		if(!file_exists($path))
			return false;
		
		$stat = stat($path);
		$tpl_timestamp = $stat['mtime'];
//		echo time(),"==(DISK)",$stat['mtime'],"<BR>";
		return true;
	}
	
	static function get_secure($tpl_name, &$smarty_obj) {
		return false;
	}
	
	static function get_trusted($tpl_name, &$smarty_obj) {
		// not used
	}
};

class _DevblocksTemplateBuilder {
	private $_tpl = null;
	private $_errors = array();
	
	private function _DevblocksTemplateBuilder() {
		$this->_tpl = DevblocksPlatform::getTemplateService();
	}
	
	/**
	 * 
	 * @return _DevblocksTemplateBuilder
	 */
	static function getInstance() {
		static $instance = null;
		if(null == $instance) {
			$instance = new _DevblocksTemplateBuilder();
		}
		return $instance;
	}

	public function getErrors() {
		return $this->_errors;
	}
	
	private function _setUp() {
		$this->_errors = array();
		$this->_tpl->force_compile = true;
		$this->_tpl->security = true;
		$this->_tpl->security_settings = array(
			'PHP_TAGS' => false,
			'INCLUDE_ANY' => true, 
		);
	}
	
	private function _tearDown() {
		$this->_tpl->force_compile = false;
		$this->_tpl->security = false;
	}
	
	/**
	 * 
	 * @param Smarty $tpl
	 * @param string $template
	 * @return string
	 */
	function build($template) {
		$this->_setUp();
		try {
			$out = $this->_tpl->fetch('string:'.$template);
		} catch(Exception $e) {
			$this->_errors[] = $e->getMessage();
		}
		$this->_tearDown();

		if(!empty($this->_errors))
			return false;
		
		return $out;
	} 
};

class _DevblocksDatabaseManager {
	private $_db = null;
	static $instance = null;
	
	private function _DevblocksDatabaseManager() {}
	
	static function getInstance() {
		if(null == self::$instance) {
			// Bail out early for pre-install
			if('' == APP_DB_DRIVER || '' == APP_DB_HOST)
			    return null;
			
			self::$instance = new _DevblocksDatabaseManager();
		}
		
		return self::$instance;
	}
	
	function __construct() {
		$persistent = (defined('APP_DB_PCONNECT') && APP_DB_PCONNECT) ? true : false;
		$this->Connect(APP_DB_HOST, APP_DB_USER, APP_DB_PASS, APP_DB_DATABASE, $persistent);
	}
	
	function Connect($host, $user, $pass, $database, $persistent=false) {
		if($persistent) {
			if(false === (@$this->_db = mysql_pconnect($host, $user, $pass)))
				return false;
		} else {
			if(false === (@$this->_db = mysql_connect($host, $user, $pass)))
				return false;
		}

		if(false === mysql_select_db($database, $this->_db)) {
			return false;
		}
		
		// Encoding
		//mysql_set_charset(DB_CHARSET_CODE, $this->_db); 
		$this->Execute('SET NAMES ' . DB_CHARSET_CODE);
		
		return true;
	}
	
	function isConnected() {
		return mysql_ping($this->_db);
	}
	
	function metaTables() {
		$tables = array();
		
		$sql = "SHOW TABLES";
		$rs = $this->GetArray($sql);
		
		foreach($rs as $row) {
			$table = array_shift($row);
			$tables[$table] = $table;
		}
		
		return $tables;
	}
	
	function metaTable($table_name) {
		$columns = array();
		$indexes = array();
		
		$sql = sprintf("SHOW COLUMNS FROM %s", $table_name);
		$rs = $this->GetArray($sql);
		
		foreach($rs as $row) {
			$field = $row['Field'];
			
			$columns[$field] = array(
				'field' => $field,
				'type' => $row['Type'],
				'null' => $row['Null'],
				'key' => $row['Key'],
				'default' => $row['Default'],
				'extra' => $row['Extra'],
			);
		}
		
		$sql = sprintf("SHOW INDEXES FROM %s", $table_name);
		$rs = $this->GetArray($sql);

		foreach($rs as $row) {
			$key_name = $row['Key_name'];
			$column_name = $row['Column_name'];

			if(!isset($indexes[$key_name]))
				$indexes[$key_name] = array(
					'columns' => array(),
				);
			
			$indexes[$key_name]['columns'][$column_name] = array(
				'column_name' => $column_name,
				'cardinality' => $row['Cardinality'],
				'index_type' => $row['Index_type'],
			);
		}
		
		return array(
			$columns,
			$indexes
		);
	}
	
	function Execute($sql) {
		if(false === ($rs = mysql_query($sql, $this->_db))) {
			error_log(sprintf("[%d] %s ::SQL:: %s", 
				mysql_errno(),
				mysql_error(),
				$sql
			));
			return false;
		}
			
		return $rs;
	}
	
	function SelectLimit($sql, $limit, $start=0) {
		$limit = intval($limit);
		$start = intval($start);
		
		if($limit > 0)
			return $this->Execute($sql . sprintf(" LIMIT %d,%d", $start, $limit));
		else
			return $this->Execute($sql);
	}
	
	function qstr($string) {
		return "'".mysql_real_escape_string($string, $this->_db)."'";
	}
	
	function GetArray($sql) {
		$results = array();
		
		if(false !== ($rs = $this->Execute($sql))) {
			while($row = mysql_fetch_assoc($rs)) {
				$results[] = $row;
			}
			mysql_free_result($rs);
		}
		
		return $results;
	}
	
	function GetRow($sql) {
		if($rs = $this->Execute($sql)) {
			$row = mysql_fetch_assoc($rs);
			mysql_free_result($rs);
			return $row;
		}
		return false;
	}

	function GetOne($sql) {
		if(false !== ($rs = $this->Execute($sql))) {
			$row = mysql_fetch_row($rs);
			mysql_free_result($rs);
			return $row[0];
		}
		
		return false;
	}
	
	function GenID($seq) {
		// Attempt to update and see if we fail, if so we need to create table
		if(false === ($rs = $this->Execute("UPDATE ${seq} SET id=LAST_INSERT_ID(id+1)"))) {
			// Create the table
			$sql = "
				CREATE TABLE IF NOT EXISTS ${seq} (
					id INT UNSIGNED NOT NULL
				) ENGINE=MyISAM;
			";
			$this->Execute($sql);
			$this->Execute(sprintf("INSERT INTO ${seq} (id) VALUES (1)"));
			return 1;
		}
		
		return mysql_insert_id($this->_db);
	}
	
	function ErrorMsg() {
		return mysql_error($this->_db);
	}
};

class _DevblocksPatchManager {
	private static $instance = null; 
	private $containers = array(); // DevblocksPatchContainerExtension[]
	private $errors = array();

	private function __construct() {}
	
	public static function getInstance() {
		if(null == self::$instance) {
			self::$instance = new _DevblocksPatchManager();
		}
		return self::$instance;
	}
	
	public function registerPatchContainer(DevblocksPatchContainerExtension $container) {
		$this->containers[] = $container;
	}
	
	public function run() {
		$result = TRUE;

		// If this is the core container, make sure it runs first
		// [TODO] plugin dependency order (core on top)
		if(is_array($this->containers)) {
			// Order by dependency
			foreach($this->containers as $idx => $container) { /* @var $container DevblocksPatchContainerExtension */
				if(isset($container->manifest) && 0 == strcasecmp('core.patches', $container->manifest->id)) { // [TODO] Don't hardcode
					unset($this->containers[$idx]);
					array_unshift($this->containers, $container);
				}
			}
			
			foreach($this->containers as $container) { /* @var $container DevblocksPatchContainerExtension */
				$result = $container->run();
				if(!$result) die("FAILED on " . $container->id);
			}
		}
		
		$this->clear();
		
		return TRUE;
	}
	
	// [TODO] Populate
	public function getErrors() {
		return $this->errors;
	}
	
	public function clear() {
		// [TODO] We probably need a mechanism to clear errors also.
		$this->containers = array();
	}

};

class _DevblocksClassLoadManager {
	const CACHE_CLASS_MAP = 'devblocks_classloader_map';
	
    private static $instance = null;
	private $classMap = array();
	
    private function __construct() {
		$cache = DevblocksPlatform::getCacheService();
		if(null !== ($map = $cache->load(self::CACHE_CLASS_MAP))) {
			$this->classMap = $map;
		} else {
			$this->_initPlugins();
			$cache->save($this->classMap, self::CACHE_CLASS_MAP);
		}
	}
    
	/**
	 * @return _DevblocksClassLoadManager
	 */
	public static function getInstance() {
		if(null == self::$instance) {
			self::$instance = new _DevblocksClassLoadManager();
		}
		return self::$instance;
	}
	
	public function destroy() {
		self::$instance = null;
	}
	
	public function loadClass($className) {
		if(class_exists($className))
			return;

		@$file = $this->classMap[$className];

		if(!is_null($file) && file_exists($file)) {
			require_once($file);
		} else {
			// Not found
		}
	}
	
	public function registerClasses($file,$classes=array()) {
		if(is_array($classes))
		foreach($classes as $class) {
			$this->classMap[$class] = $file;
		}
	}
	
	private function _initPlugins() {
		// Load all the exported classes defined by plugin manifests		
		$class_map = DAO_Platform::getClassLoaderMap();
		if(is_array($class_map) && !empty($class_map))
		foreach($class_map as $path => $classes) {
			$this->registerClasses($path, $classes);
		}
	}
};

class _DevblocksLogManager {
	static $_instance = null;
	
    // Used the ZF classifications
	private static $_log_levels = array(
		'emerg' => 0,		// Emergency: system is unusable
		'emergency' => 0,	
		'alert' => 1,		// Alert: action must be taken immediately
		'crit' => 2,		// Critical: critical conditions
		'critical' => 2,	
		'err' => 3,			// Error: error conditions
		'error' => 3,		
		'warn' => 4,		// Warning: warning conditions
		'warning' => 4,		
		'notice' => 5,		// Notice: normal but significant condition
		'info' => 6,		// Informational: informational messages
		'debug' => 7,		// Debug: debug messages
	);

	private $_log_level = 0;
	private $_fp = null;
	
	static function getConsoleLog() {
		if(null == self::$_instance) {
			self::$_instance = new _DevblocksLogManager();
		}
		
		return self::$_instance;
	}
	
	private function __construct() {
		// Allow query string overloading Devblocks-wide
		@$log_level = DevblocksPlatform::importGPC($_REQUEST['loglevel'],'integer', 0);
		$this->_log_level = intval($log_level);
		
		// Open file pointer
		$this->_fp = fopen('php://output', 'w+');
	}
	
	public function __destruct() {
		@fclose($this->_fp);	
	}	
	
	public function __call($name, $args) {
		if(isset(self::$_log_levels[$name])) {
			if(self::$_log_levels[$name] <= $this->_log_level) {
				$out = sprintf("[%s] %s<BR>\n",
					strtoupper($name),
					$args[0]
				);
				fputs($this->_fp, $out);
			}
		}
	}
};

class _DevblocksUrlManager {
    private static $instance = null;
        
   	private function __construct() {}
	
	/**
	 * @return _DevblocksUrlManager
	 */
	public static function getInstance() {
		if(null == self::$instance) {
			self::$instance = new _DevblocksUrlManager();
		}
		return self::$instance;
	}
	
	function parseQueryString($args) {
		$argc = array();
		if(empty($args)) return $argc;
		
		$query = explode('&', $args);
		if(is_array($query))
		foreach($query as $q) {
			if(empty($q)) continue;
			$v = explode('=',$q);
			if(empty($v)) continue;
			@$argc[strtolower($v[0])] = $v[1];
		}
		
		return $argc;
	}
	
	function parseURL($url) {
		// [JAS]: Use the index.php page as a reference to deconstruct the URI
		$pos = stripos($_SERVER['PHP_SELF'],'index.php',0);
		if($pos === FALSE) return array();

		// Decode proxy requests
		if(isset($_SERVER['HTTP_DEVBLOCKSPROXYHOST'])) {
			$url = urldecode($url);
		}
		
		// [JAS]: Extract the basedir of the path
		$basedir = substr($url,0,$pos);

		// [JAS]: Remove query string
		$pos = stripos($url,'?',0);
		if($pos !== FALSE) {
			$url = substr($url,0,$pos);
		}
		
		$len = strlen($basedir);
		if(!DEVBLOCKS_REWRITE) $len += strlen("index.php/");
		
		$request = substr($url, $len);
		
		if(empty($request)) return array();
		
		$parts = explode('/', $request);

		if(trim($parts[count($parts)-1]) == '') {
			unset($parts[count($parts)-1]);
		}
		
		return $parts;
	}
	
	function write($sQuery='',$full=false,$check_proxy=true) {
		$args = $this->parseQueryString($sQuery);
		$c = @$args['c'];
		
		// Allow proxy override
		if($check_proxy) {
    		@$proxyssl = $_SERVER['HTTP_DEVBLOCKSPROXYSSL'];
    		@$proxyhost = $_SERVER['HTTP_DEVBLOCKSPROXYHOST'];
    		@$proxybase = $_SERVER['HTTP_DEVBLOCKSPROXYBASE'];
		}

		// Proxy (Community Tool)
		if(!empty($proxyhost)) {
			if($full) {
				$prefix = sprintf("%s://%s%s/",
					(!empty($proxyssl) ? 'https' : 'http'),
					$proxyhost,
					$proxybase
				);
			} else {
				$prefix = $proxybase.'/';
			}
		
			// Index page
			if(empty($sQuery)) {
			    return sprintf("%s",
			        $prefix
			    );
			}
			
			// [JAS]: Internal non-component URL (images/css/js/etc)
			if(empty($c)) {
				$contents = sprintf("%s%s",
					$prefix,
					$sQuery
				);
		    
			// [JAS]: Component URL
			} else {
				$contents = sprintf("%s%s",
					$prefix,
					(!empty($args) ? implode('/',array_values($args)) : '')
				);
			}
			
		// Devblocks App
		} else {
			if($full) {
				$prefix = sprintf("%s://%s%s",
					($this->isSSL() ? 'https' : 'http'),
					$_SERVER['HTTP_HOST'],
					DEVBLOCKS_APP_WEBPATH
				);
			} else {
				$prefix = DEVBLOCKS_APP_WEBPATH;
			}

			// Index page
			if(empty($sQuery)) {
			    return sprintf("%s%s",
			        $prefix,
			        (DEVBLOCKS_REWRITE) ? '' : 'index.php/'
			    );
			}
			
			// [JAS]: Internal non-component URL (images/css/js/etc)
			if(empty($c)) {
				$contents = sprintf("%s%s",
					$prefix,
					$sQuery
				);
		    
				// [JAS]: Component URL
			} else {
				if(DEVBLOCKS_REWRITE) {
					$contents = sprintf("%s%s",
						$prefix,
						(!empty($args) ? implode('/',array_values($args)) : '')
					);
					
				} else {
					$contents = sprintf("%sindex.php/%s",
						$prefix,
						(!empty($args) ? implode('/',array_values($args)) : '')
	//					(!empty($args) ? $sQuery : '')
					);
				}
			}
		}
		
		return $contents;
	}
	
	/**
	 * Enter description here...
	 *
	 * @return boolean
	 */
	public function isSSL() {
		if(@$_SERVER["HTTPS"] == "on"){
			return true;
		} elseif (@$_SERVER["HTTPS"] == 1){
			return true;
		} elseif (@$_SERVER['SERVER_PORT'] == 443) {
			return true;
		} else {
			return false;
		}
	}
	
	/**
	 * Useful for converting DevblocksRequest and DevblocksResponse objects to a URL
	 */
	function writeDevblocksHttpIO($request, $full=false) {
		$url_parts = '';
		
		if(is_array($request->path) && count($request->path) > 0)
			$url_parts = 'c=' . array_shift($request->path);
		
		if(!empty($request->path))
			$url_parts .= '&f=' . implode('/', $request->path);
		
		// Build the URL		
		$url = $this->write($url_parts, $full);
		
		$query = '';
		foreach($request->query as $key=>$val) {
			$query .= 
				(empty($query)?'':'&') . // arg1=val1&arg2=val2 
				$key . 
				'=' . 
				$val
			;
		}
		
		if(!empty($query))
			$url .= '?' . $query;

		return $url;
	}
};

// [TODO] Rename URLPing or some such nonsense, these don't proxy completely
class DevblocksProxy {
    /**
     * @return DevblocksProxy
     */
    static function getProxy() {
        $proxy = null;

		// Determine if CURL or FSOCK is available
		if(function_exists('curl_exec')) {
	    	$proxy = new DevblocksProxy_Curl();
		} elseif(function_exists('fsockopen')) {
    		$proxy = new DevblocksProxy_Socket();
		}

        return $proxy;
    }
    
    function proxy($remote_host, $remote_uri) {
        $this->_get($remote_host, $remote_uri);
    }

    function _get($remote_host, $remote_uri) {
        die("Subclass abstract " . __CLASS__ . "...");
    }

};

class DevblocksProxy_Socket extends DevblocksProxy {
    function _get($remote_host, $remote_uri) {
        $fp = fsockopen($remote_host, 80, $errno, $errstr, 10);
        if ($fp) {
            $out = "GET " . $remote_uri . " HTTP/1.1\r\n";
            $out .= "Host: $remote_host\r\n";
            $out .= 'Via: 1.1 ' . $_SERVER['HTTP_HOST'] . "\r\n";
            $out .= "Connection: Close\r\n\r\n";

            $this->_send($fp, $out);
        }
    }

    function _send($fp, $out) {
	    fwrite($fp, $out);
	    
	    while(!feof($fp)) {
	        fgets($fp,4096);
	    }

	    fclose($fp);
	    return;
    }
};

class DevblocksProxy_Curl extends DevblocksProxy {
    function _get($remote_host, $remote_uri) {
        $url = 'http://' . $remote_host . $remote_uri;
        $header = array();
        $header[] = 'Via: 1.1 ' . $_SERVER['HTTP_HOST'];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_HEADER, 0);
//        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);
//        curl_setopt($ch, CURLOPT_NOSIGNAL, 1);
//        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
//        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500);
        curl_exec($ch);
        curl_close($ch);
    }
};

interface DevblocksExtensionDelegate {
	static function shouldLoadExtension(DevblocksExtensionManifest $extension_manifest);
};
