<?php
$path = APP_PATH . '/libs/devblocks/libs/zend_framework/Zend/';
require_once($path.'Cache.php');

function __autoload($className) {
	DevblocksPlatform::loadClass($className);
}

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
	 * @param string $file
	 * @return DevblocksPluginManifest
	 */
	static protected function _readPluginManifest($dir) {
		if(!file_exists(DEVBLOCKS_PLUGIN_PATH.$dir.'/plugin.xml'))
			return NULL;
		
		$plugin = simplexml_load_file(DEVBLOCKS_PLUGIN_PATH.$dir.'/plugin.xml');
		$prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup
				
		$manifest = new DevblocksPluginManifest();
		$manifest->id = (string) $plugin->id;
		$manifest->dir = $dir;
		$manifest->description = (string) $plugin->description;
		$manifest->author = (string) $plugin->author;
		$manifest->revision = (integer) $plugin->revision;
		$manifest->link = (string) $plugin->link;
		$manifest->name = (string) $plugin->name;
        $manifest->file = (string) $plugin->class->file;
        $manifest->class = (string) $plugin->class->name;

        // [TODO] Check that file + class exist
		// [TODO] Clear out any removed plugins/classes/exts?
        
		$db = DevblocksPlatform::getDatabaseService();
		if(is_null($db)) return;
		
		// Manifest
		$db->Replace(
			$prefix.'plugin',
			array(
				'id' => $db->qstr($manifest->id),
				'name' => $db->qstr($manifest->name),
				'description' => $db->qstr($manifest->description),
				'author' => $db->qstr($manifest->author),
				'revision' => $manifest->revision,
				'link' => $db->qstr($manifest->link),
				'file' => $db->qstr($manifest->file),
				'class' => $db->qstr($manifest->class),
				'dir' => $db->qstr($manifest->dir)
			),
			array('id'),
			false
		);
		
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
		    // [JAS]: [TODO] Move to platform DAO
			$db->Replace(
				$prefix.'extension',
				array(
					'id' => $db->qstr($extension->id),
					'plugin_id' => $db->qstr($extension->plugin_id),
					'point' => $db->qstr($extension->point),
					'pos' => $pos,
					'name' => $db->qstr($extension->name),
					'file' => $db->qstr($extension->file),
					'class' => $db->qstr($extension->class),
					'params' => $db->qstr(serialize($extension->params))
				),
				array('id'),
				false
			);
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
		$rs_plugin_extensions = $db->Execute($sql);

		while(!$rs_plugin_extensions->EOF) {
			$plugin_ext_id = $rs_plugin_extensions->fields['id'];
			if(!isset($new_extensions[$plugin_ext_id]))
				DAO_Platform::deleteExtension($plugin_ext_id);
			$rs_plugin_extensions->MoveNext(); 
		}
		
        // [JAS]: [TODO] Extension point caching

		// Class loader cache
		$db->Execute(sprintf("DELETE FROM %sclass_loader WHERE plugin_id = %s",$prefix,$db->qstr($plugin->id)));
		if(is_array($manifest->class_loader))
		foreach($manifest->class_loader as $file_path => $classes) {
			if(is_array($classes) && !empty($classes))
			foreach($classes as $class)
			$db->Replace(
				$prefix.'class_loader',
				array(
					'class' => $db->qstr($class),
					'plugin_id' => $db->qstr($manifest->id),
					'rel_path' => $db->qstr($file_path),
				),
				array('class'),
				false
			);
		}
		
		// URI routing cache
		$db->Execute(sprintf("DELETE FROM %suri_routing WHERE plugin_id = %s",$prefix,$db->qstr($plugin->id)));
		if(is_array($manifest->uri_routing))
		foreach($manifest->uri_routing as $uri => $controller_id) {
			$db->Replace(
				$prefix.'uri_routing',
				array(
					'uri' => $db->qstr($uri),
					'plugin_id' => $db->qstr($manifest->id),
					'controller_id' => $db->qstr($controller_id),
				),
				array('uri'),
				false
			);
		}

		// ACL caching
		$db->Execute(sprintf("DELETE FROM %sacl WHERE plugin_id = %s",$prefix,$db->qstr($plugin->id)));
		if(is_array($manifest->acl_privs))
		foreach($manifest->acl_privs as $priv) { /* @var $priv DevblocksAclPrivilege */
			$db->Replace(
				$prefix.'acl',
				array(
					'id' => $db->qstr($priv->id),
					'plugin_id' => $db->qstr($priv->plugin_id),
					'label' => $db->qstr($priv->label),
				),
				array('id'),
				false
			);
		}
		
        // [JAS]: Event point caching
		if(is_array($manifest->event_points))
		foreach($manifest->event_points as $event) { /* @var $event DevblocksEventPoint */
			$db->Replace(
				$prefix.'event_point',
				array(
					'id' => $db->qstr($event->id),
					'plugin_id' => $db->qstr($event->plugin_id),
					'name' => $db->qstr($event->name),
					'params' => $db->qstr(serialize($event->params))
				),
				array('id'),
				false
			);
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
	static function strToRegExp($arg) {
		$arg = str_replace(array('*'),array('__WILD__'),$arg);
		
		return sprintf("/^%s$/i",
			str_replace(array('__WILD__','/'),array('.*?','\/'),preg_quote($arg))
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
			    // [TODO] Set the mime-type/filename in response headers
			    $plugin = array_shift($path);
			    $file = implode(DIRECTORY_SEPARATOR, $path); // combine path
		        $dir = DEVBLOCKS_PLUGIN_PATH . $plugin . DIRECTORY_SEPARATOR . 'resources';
		        if(!is_dir($dir)) die(""); // basedir Security
		        $resource = $dir . DIRECTORY_SEPARATOR . $file;
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

	/**
	 * Prints out the Platform Javascript Library for use by Application.
	 * This library provides the ability to rewrite URLs in Javascript for 
	 * Ajax functionality, etc.
	 * 
	 * @example
	 * <script language="javascript" type="text/javascript">{php}DevblocksPlatform::printJavascriptLibrary();{/php}</script>
	 */
	static function printJavascriptLibrary() {
		$tpl = DevblocksPlatform::getTemplateService();
		$path = dirname(__FILE__);
		$tpl->caching = 0;
		$tpl->display("file:$path/devblocks.tpl.js");
	}
}

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
		    
			if(is_null($db) || !$db->IsConnected()) { return null; }
			
			$prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup
			
			@session_destroy();
			
			include_once(DEVBLOCKS_PATH . "libs/adodb5/session/adodb-session2.php");
			$options = array();
			$options['table'] = $prefix.'session';
			ADOdb_Session::config(APP_DB_DRIVER, APP_DB_HOST, APP_DB_USER, APP_DB_PASS, APP_DB_DATABASE, $options);
			ADOdb_session::Persist($connectMode=false);
			ADOdb_session::lifetime($lifetime=86400);

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
}

/**
 * This class wraps Zend_Cache and implements a more intelligent 
 * cache manager that won't try to load the same cache twice 
 * during the same request.
 *
 */
class _DevblocksCacheManager {
    private static $instance = null;
    private static $_zend_cache = null;
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
			
	        $frontendOptions = array(
	           'cache_id_prefix' => (defined('DEVBLOCKS_CACHE_PREFIX') && DEVBLOCKS_CACHE_PREFIX) ? DEVBLOCKS_CACHE_PREFIX : null,
			   'lifetime' => 21600, // 6 hours 
	           'write_control' => false,
			   'automatic_serialization' => true,
			);

			// Shared-memory cache
		    if(extension_loaded('memcache') && defined('DEVBLOCKS_MEMCACHED_SERVERS') && DEVBLOCKS_MEMCACHED_SERVERS) {
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
		    			'persistent'=>true
		    		);
		    	}
		    	
				$backendOptions = array(
					'servers' => $servers
				);
						
				self::$_zend_cache = Zend_Cache::factory('Core', 'Memcached', $frontendOptions, $backendOptions);
		    }

		    // Disk-based cache (default)
		    if(null == self::$_zend_cache) {
				$backendOptions = array(
				    'cache_dir' => APP_TEMP_PATH
				);
				
				self::$_zend_cache = Zend_Cache::factory('Core', 'File', $frontendOptions, $backendOptions);
		    }
		}
		return self::$instance;
    }
    
	public function save($data, $key, $tags=array(), $lifetime=false) {
		// Monitor short-term cache memory usage
		@$this->_statistics[$key] = intval($this->_statistics[$key]);
		$this->_io_writes++;
//		echo "Memory usage: ",memory_get_usage(true),"<BR>";
		self::$_zend_cache->save($data, $key, $tags, $lifetime);
		$this->_registry[$key] = $data;
	}
	
	public function load($key, $nocache=false) {
//		echo "Memory usage: ",memory_get_usage(true),"<BR>";
//		print_r(array_keys($this->_registry));
//		echo "<HR>";
		
		// Retrieving the long-term cache
		if($nocache || !isset($this->_registry[$key])) {
//			echo "Hit long-term cache for $key<br>";
			if(false === ($this->_registry[$key] = self::$_zend_cache->load($key)))
				return NULL;
			
			@$this->_statistics[$key] = intval($this->_statistics[$key]) + 1;
			$this->_io_reads_long++;
			return $this->_registry[$key];
		}
		
		// Retrieving the short-term cache
		if(isset($this->_registry[$key])) {
//			echo "Hit short-term cache for $key<br>";
			@$this->_statistics[$key] = intval($this->_statistics[$key]) + 1;
			$this->_io_reads_short++;
			return $this->_registry[$key];
		}
		
		return NULL;
	}
	
	public function remove($key) {
		unset($this->_registry[$key]);
		unset($this->_statistics[$key]);
		self::$_zend_cache->remove($key);
	}
	
	public function clean($mode=null) {
		$this->_registry = array();
		$this->_statistics = array();
		
		if(!empty($mode)) {
			self::$_zend_cache->clean($mode);
		} else { 
			self::$_zend_cache->clean();
		}
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
		return new Swift_Message();
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
					$smtp_enc = Swift_Connection_SMTP::ENC_TLS;
					break;
					
				case 'SSL':
					$smtp_enc = Swift_Connection_SMTP::ENC_SSL;
					break;
					
				default:
					$smtp_enc = Swift_Connection_SMTP::ENC_OFF;
					break;
			}
			
			$smtp = new Swift_Connection_SMTP($smtp_host, $smtp_port, $smtp_enc);
			$smtp->setTimeout($smtp_timeout);
			
			if(!empty($smtp_user) && !empty($smtp_pass)) {
				$smtp->setUsername($smtp_user);
				$smtp->setPassword($smtp_pass);
			}
			
			$swift =& new Swift($smtp);
			$swift->attachPlugin(new Swift_Plugin_AntiFlood($smtp_max_sends,1), "anti-flood");
			
			$this->mailers[$hash] =& $swift;
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
			require(DEVBLOCKS_PATH . 'libs/smarty/Smarty.class.php');
			$instance = new Smarty();
			$instance->template_dir = APP_PATH . '/templates'; // [TODO] Themes
			$instance->compile_dir = APP_TEMP_PATH . '/templates_c';
			$instance->cache_dir = APP_TEMP_PATH . '/cache';
			$instance->plugins_dir = DEVBLOCKS_PATH . 'libs/smarty_plugins';
			
			//$smarty->config_dir = DEVBLOCKS_PATH. 'configs';
			$instance->caching = 0;
			$instance->cache_lifetime = 0;
		}
		return $instance;
	}
};

/**
 * ADODB Database Singleton
 *
 * @ingroup services
 */
class _DevblocksDatabaseManager {
	
	/**
	 * Constructor 
	 * 
	 * @private
	 */
	private function _DevblocksDatabaseManager() {}
	
	/**
	 * Returns an ADODB database resource
	 *
	 * @static 
	 * @return ADOConnection
	 */
	static function getInstance() {
		static $instance = null;
		
		if(null == $instance) {
			include_once(DEVBLOCKS_PATH . "libs/adodb5/adodb.inc.php");
			$ADODB_CACHE_DIR = APP_TEMP_PATH . "/cache";
			
			if('' == APP_DB_DRIVER || '' == APP_DB_HOST)
			    return null;
			
			@$instance =& ADONewConnection(APP_DB_DRIVER); /* @var $instance ADOConnection */
			
			// Make the connection (or persist it)
			if(defined('APP_DB_PCONNECT') && APP_DB_PCONNECT) {
				@$instance->PConnect(APP_DB_HOST,APP_DB_USER,APP_DB_PASS,APP_DB_DATABASE);
			} else { 
				@$instance->Connect(APP_DB_HOST,APP_DB_USER,APP_DB_PASS,APP_DB_DATABASE);
			}

			if(null == $instance || !$instance->IsConnected())
				die("[Error]: There is no connection to the database.  Check your connection details.");
			
			@$instance->SetFetchMode(ADODB_FETCH_ASSOC);
			//$instance->LogSQL(false);
			
			// Encoding
			$instance->Execute('SET NAMES ' . DB_CHARSET_CODE);
		}
		return $instance;
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
			$this->_initLibs();	
			$this->_initZend();
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
	
	private function _initLibs() {
		$path = DEVBLOCKS_PATH . 'libs/swift/';
		
		$this->registerClasses($path . 'Swift.php',array(
			'Swift',
			'Swift_Message_Part',
			'Swift_Message_Attachment',
			'Swift_File',
			'Swift_Address'
		));
			
		$this->registerClasses($path . 'Swift/LogContainer.php',array(
			'Swift_LogContainer',
		));
		
		$this->registerClasses($path . 'Swift/Log/DefaultLog.php',array(
			'Swift_Log_DefaultLog',
		));
		
		$this->registerClasses($path . 'Swift/RecipientList.php',array(
			'Swift_RecipientList',
		));
		
		$this->registerClasses($path . 'Swift/Connection/SMTP.php',array(
			'Swift_Connection_SMTP',
		));
		
		$this->registerClasses($path . 'Swift/AddressContainer.php',array(
			'Swift_AddressContainer',
		));
		
		$this->registerClasses($path . 'Swift/Plugin/AntiFlood.php',array(
			'Swift_Plugin_AntiFlood',
		));
		
		$this->registerClasses($path . 'Swift/Message/Headers.php',array(
			'Swift_Message_Headers',
		));		
	}
	
	private function _initZend() {
		$path = APP_PATH . '/libs/devblocks/libs/zend_framework/Zend/';
		
		$this->registerClasses($path . 'Cache.php', array(
			'Zend_Cache',
		));
		
		$this->registerClasses($path . 'Exception.php', array(
			'Zend_Exception',
		));
		
	    $this->registerClasses($path . 'Registry.php', array(
			'Zend_Registry',
		));
		
		$this->registerClasses($path . 'Feed/Exception.php', array(
			'Zend_Feed_Exception',
		));
		
		$this->registerClasses($path . 'Feed.php', array(
			'Zend_Feed',
		));
		
		$this->registerClasses($path . 'Feed/Atom.php', array(
			'Zend_Feed_Atom',
		));
		
		$this->registerClasses($path . 'Feed/Builder.php', array(
			'Zend_Feed_Builder',
		));
		
		$this->registerClasses($path . 'Feed/Rss.php', array(
			'Zend_Feed_Rss',
		));
		
		$this->registerClasses($path . 'Json.php', array(
			'Zend_Json',
		));
		
		$this->registerClasses($path . 'Log.php', array(
			'Zend_Log',
		));
		
		$this->registerClasses($path . 'Log/Writer/Stream.php', array(
			'Zend_Log_Writer_Stream',
		));
		
		$this->registerClasses($path . 'Mail.php', array(
			'Zend_Mail',
		));
		
		$this->registerClasses($path . 'Mail/Storage/Pop3.php', array(
			'Zend_Mail_Storage_Pop3',
		));
		
		$this->registerClasses($path . 'Mime.php', array(
			'Zend_Mime',
		));
		
		$this->registerClasses($path . 'Validate/EmailAddress.php', array(
			'Zend_Validate_EmailAddress',
		));
		
		$this->registerClasses($path . 'Mail/Transport/Smtp.php', array(
			'Zend_Mail_Transport_Smtp',
		));
		
		$this->registerClasses($path . 'Mail/Transport/Sendmail.php', array(
			'Zend_Mail_Transport_Sendmail',
		));
	}
};

class _DevblocksLogManager {
	static $consoleLogger = null;
	
	static function getConsoleLog() {
		if(null == self::$consoleLogger) {
			$writer = new Zend_Log_Writer_Stream('php://output');
			$writer->setFormatter(new Zend_Log_Formatter_Simple('[%priorityName%]: %message%<BR>' . PHP_EOL));
			self::$consoleLogger = new Zend_Log($writer);
			
			// Allow query string overloading Devblocks-wide
			@$log_level = DevblocksPlatform::importGPC($_REQUEST['loglevel'],'integer',0);
			self::$consoleLogger->addFilter(new Zend_Log_Filter_Priority($log_level));
		}
		
		return self::$consoleLogger;
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
		
		$parts = split('/', $request);

		if(trim($parts[count($parts)-1]) == '') {
			unset($parts[count($parts)-1]);
		}
		
		return $parts;
	}
	
	function write($sQuery='',$full=false) {
		$url = DevblocksPlatform::getUrlService();
		$args = $url->parseQueryString($sQuery);
		$c = @$args['c'];
		
	    @$proxyssl = $_SERVER['HTTP_DEVBLOCKSPROXYSSL'];
	    @$proxyhost = $_SERVER['HTTP_DEVBLOCKSPROXYHOST'];
	    @$proxybase = $_SERVER['HTTP_DEVBLOCKSPROXYBASE'];

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
					DEVBLOCKS_WEBPATH
				);
			} else {
				$prefix = DEVBLOCKS_WEBPATH;
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
