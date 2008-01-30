<?php
$path = APP_PATH . '/libs/devblocks/libs/ZendFramework/Zend/';
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
		$manifest->is_configurable = (string) $plugin->is_configurable;
		$manifest->name = (string) $plugin->name;
        $manifest->file = (string) $plugin->class->file;
        $manifest->class = (string) $plugin->class->name;

        // [TODO] Check that file + class exist
		// [TODO] Clear out any removed plugins/classes/exts?
        
		$db = DevblocksPlatform::getDatabaseService();
		if(is_null($db)) return;
		
		// [JAS]: [TODO] Move to platform DAO
		$db->Replace(
			$prefix.'plugin',
			array(
				'id' => $db->qstr($manifest->id),
				'name' => $db->qstr($manifest->name),
				'description' => $db->qstr($manifest->description),
				'author' => $db->qstr($manifest->author),
				'revision' => $manifest->revision,
				'link' => $db->qstr($manifest->link),
				'is_configurable' => (!empty($manifest->is_configurable) ? 1 : 0),
				'file' => $db->qstr($manifest->file),
				'class' => $db->qstr($manifest->class),
				'dir' => $db->qstr($manifest->dir)
			),
			array('id'),
			false
		);
		
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
		}
		
        // [JAS]: Extension point caching

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
	 * Reads the HTTP Request object.
	 * 
	 * @return DevblocksHttpRequest
	 */
	static function readRequest() {
		$url = DevblocksPlatform::getUrlService();

		$location = self::getWebPath();
		
		$parts = $url->parseURL($location);
		
		// Add any query string arguments (?arg=value&arg=value)
		$query = $_SERVER['QUERY_STRING'];
		$queryArgs = $url->parseQueryString($query);
		
		if(empty($parts)) {
			// Overrides (Form POST, etc.)
			@$uri = DevblocksPlatform::importGPC($_REQUEST['c']); // extension
			if(!empty($uri)) $parts[] = $uri;

			@$listener = DevblocksPlatform::importGPC($_REQUEST['a']); // listener
			if(!empty($listener)) $parts[] = $listener;
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
		        $dir = realpath(DEVBLOCKS_PLUGIN_PATH . $plugin . DIRECTORY_SEPARATOR . 'resources');
		        if(!is_dir($dir)) die(""); // basedir Security
		        $resource = realpath($dir . DIRECTORY_SEPARATOR . $file);
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
	            	case 'js':
	            		header('Content-type: text/javascript;');
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
	            $controllers = DevblocksPlatform::getExtensions('devblocks.controller', true);
	            $router = DevblocksPlatform::getRoutingService();
				/*
				 * [JAS]: Try to find our command in the URI lookup first, and if we
				 * fail then fall back to raw extension ids.
				 */
				if(null == ($controller_id = $router->getRoute($controller_uri))
						|| null == ($controller = $controllers[$controller_id]) ) {
						$controller = $controllers[APP_DEFAULT_CONTROLLER];
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
	}
}

class _DevblocksCacheManager {
    private static $instance = null;
    
    private function __construct() {}

    /**
     * @return Zend_Cache_Core
     */
    public static function getInstance() {
		if(null == self::$instance) {

	        $frontendOptions = array(
			   'lifetime' => null, // forever 
			   'automaticSerialization' => true
			);

			// [TODO] Later this should support multiple servers from config file
		    if(extension_loaded('memcache') && DEVBLOCKS_MEMCACHE_HOST && DEVBLOCKS_MEMCACHE_PORT) {
				$backendOptions = array(
					'servers' => array(array(
					    'host' => DEVBLOCKS_MEMCACHE_HOST,
					    'port' => DEVBLOCKS_MEMCACHE_PORT, 
					    'persistent' => true
					))
				);
				
				self::$instance = Zend_Cache::factory('Core', 'Memcached', $frontendOptions, $backendOptions);
		    }

		    if(null == self::$instance) {
				$backendOptions = array(
				    'cacheDir' => DEVBLOCKS_PATH . 'tmp/'
				);
				
				self::$instance = Zend_Cache::factory('Core', 'File', $frontendOptions, $backendOptions);
		    }
		}
		return self::$instance;
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
            $manifest = DevblocksPlatform::getExtension($listener);
		    $inst = $manifest->createInstance(); /* @var $inst DevblocksEventListenerExtension */
            $inst->handleEvent($event);
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
    
	/**
	 * @private
	 */
	private function __construct() {}
	
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
	function getMailer($smtp_host=null, $smtp_user=null, $smtp_pass=null, $smtp_port=null, $smtp_enc=null) {
		$settings = CerberusSettings::getInstance();

		// [TODO] This shouldn't have Cerberus-specific settings in it.
		if (!isset($smtp_host)) $smtp_host = $settings->get(CerberusSettings::SMTP_HOST,'localhost');
		if (!isset($smtp_user)) $smtp_user = $settings->get(CerberusSettings::SMTP_AUTH_USER,null);
		if (!isset($smtp_pass)) $smtp_pass = $settings->get(CerberusSettings::SMTP_AUTH_PASS,null);
		if (!isset($smtp_port)) $smtp_port = $settings->get(CerberusSettings::SMTP_PORT,'25');
		if (!isset($smtp_enc)) $smtp_enc = $settings->get(CerberusSettings::SMTP_ENCRYPTION_TYPE,'None');
		
		if ($smtp_enc == 'TLS')			$smtp_enc = Swift_Connection_SMTP::ENC_TLS;
		else if ($smtp_enc == 'SSL')	$smtp_enc = Swift_Connection_SMTP::ENC_SSL;
		else							$smtp_enc = Swift_Connection_SMTP::ENC_OFF;
		
		$smtp = new Swift_Connection_SMTP($smtp_host, $smtp_port, $smtp_enc);
		if(!empty($smtp_user) && !empty($smtp_pass)) {
			$smtp->setUsername($smtp_user);
			$smtp->setPassword($smtp_pass);
		}
		$swift =& new Swift($smtp);
		return $swift;
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
			$instance->compile_dir = DEVBLOCKS_PATH . 'tmp/templates_c';
			$instance->cache_dir = DEVBLOCKS_PATH . 'tmp/cache';
			
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
			$ADODB_CACHE_DIR = APP_PATH . "/tmp/cache";
			
			if('' == APP_DB_DRIVER || '' == APP_DB_HOST)
			    return null;
			
			@$instance =& ADONewConnection(APP_DB_DRIVER); /* @var $instance ADOConnection */
			@$instance->Connect(APP_DB_HOST,APP_DB_USER,APP_DB_PASS,APP_DB_DATABASE);
			@$instance->SetFetchMode(ADODB_FETCH_ASSOC);
			@$instance->LogSQL(false);
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
		// [TODO] Ordering?
		$this->containers[] = $container;
	}
	
	// [TODO] This delegate needs to be smart enough to order our patches by dependency
	public function run() {
		$result = TRUE;
		
		if(is_array($this->containers))
		foreach($this->containers as $container) { /* @var $container DevblocksPatchContainerExtension */
			$result = $container->run();
			if(!$result) die("FAILED on " . $container->id);
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
	private $newRegisters = 0;
	
    private function __construct() {
		$cache = DevblocksPlatform::getCacheService();
		if(false !== ($map = $cache->load(self::CACHE_CLASS_MAP))) {
			$this->classMap = $map;
		} else {
			$this->_initPEAR();	
			$this->_initLibs();	
			$this->_initZend();
		}
	}
    
	public function __destruct() {
		// [JAS]: If newly registered this instance, add to cache
		if($this->newRegisters) {
			$cache = _DevblocksCacheManager::getInstance();
			$cache->save($this->classMap, self::CACHE_CLASS_MAP);
		}
	}
	
	/**
	 * @return _DevblocksRoutingManager
	 */
	public static function getInstance() {
		if(null == self::$instance) {
			self::$instance = new _DevblocksClassLoadManager();
		}
		return self::$instance;
	}
	
	public function loadClass($className) {
		if(class_exists($className)) return;

		@$file = $this->classMap[$className];
		
		if(!is_null($file) && file_exists($file)) {
			require_once($file);
		} else {
	       	// [TODO]: Exception, log
	       	// [TODO] It's probably not a good idea to send this much info to the browser
	       	echo sprintf("<b>ERROR: ClassLoader could not find '%s':</b><br>",
	       	    $className
	       	);
//	       	echo "<pre>";
//	       	print_r(debug_backtrace());
//	       	echo "</pre>";
//	       	die;
		}
	}
	
	public function registerClasses($file,$classes=array()) {
		if(is_array($classes))
		foreach($classes as $class) {
			if(!isset($this->classMap[$class]))
				$this->newRegisters++;
			$this->classMap[$class] = $file;
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
			
		$this->registerClasses($path . 'Swift/RecipientList.php',array(
			'Swift_RecipientList',
		));
		
		$this->registerClasses($path . 'Swift/Connection/SMTP.php',array(
			'Swift_Connection_SMTP',
		));
	}
	
	private function _initPEAR() {
	}
	
	private function _initZend() {
		$path = APP_PATH . '/libs/devblocks/libs/ZendFramework/Zend/';
		
		$this->registerClasses(APP_PATH . '/libs/devblocks/libs/ZendFramework/Zend.php', array(
			'Zend',
		));
		
		$this->registerClasses($path . 'Cache.php', array(
			'Zend_Cache',
		));
		
		$this->registerClasses($path . 'Exception.php', array(
			'Zend_Exception',
		));
		
	    $this->registerClasses($path . 'Registry.php', array(
			'Zend_Registry',
		));
		
		$this->registerClasses($path . 'Date.php', array(
			'Zend_Date',
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
		
		$this->registerClasses($path . 'Locale.php', array(
			'Zend_Locale',
		));
		
		$this->registerClasses($path . 'Translate.php', array(
			'Zend_Translate',
		));
		
		$this->registerClasses($path . 'Translate/Adapter/Tmx.php', array(
			'Zend_Translate_Adapter_Tmx',
		));
		
		$this->registerClasses($path . 'Search/Lucene.php', array(
			'Zend_Search_Lucene',
			'Zend_Search_Lucene_Document',
			'Zend_Search_Lucene_Field',
			'Zend_Search_Lucene_Search_QueryHit',
		));
		
		$this->registerClasses($path . 'Search/Lucene/Analysis/Analyzer.php', array(
			'Zend_Search_Lucene_Analysis_Analyzer',
			'Zend_Search_Lucene_Analysis_Analyzer_Common',
			));
		
		$this->registerClasses($path . 'Search/Lucene/Analysis/Analyzer/Common/TextNum.php', array(
			'Zend_Search_Lucene_Analysis_Analyzer_Common_TextNum_CaseInsensitive',
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
		
		$this->registerClasses($path . 'Validate/EmailAddress.php.php', array(
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

class _DevblocksRoutingManager {
    private static $instance = null;
    private $routes = array();
    
    private function __construct() {}
    
	/**
	 * @return _DevblocksRoutingManager
	 */
	public static function getInstance() {
		if(null == self::$instance) {
			self::$instance = new _DevblocksRoutingManager();
		}
		return self::$instance;
	}
	
	function addRoute($route, $controller_id) {
	    $this->routes[$route] = $controller_id;
	}
	
	function getRoutes() {
	    return $this->routes;
	}
	
	function getRoute($route) {
	    return @$this->routes[$route];
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
					($this->_isSSL() ? 'https' : 'http'),
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
	private function _isSSL() {
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
		$url_str='';

		if($request->path[0] != '') {
			$c = array_shift($request->path);
		}
		if(sizeof($request->path) > 0) {
			$url_str = implode('/', $request->path) . '/';
		}
		
		$prefix = '?';
		foreach($request->query as $key=>$val) {
			$url_str .= $prefix . $key . '=' . $val;
			$prefix = '&';
		}
		$url_str = 'c='.$c.'&f='.$url_str;
		return $this->write($url_str, $full);
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

// [JAS]: [TODO] Replace with Zend_Acl
class DevblocksACL {
	// [JAS]: Unsigned 32 bit number, with room to enable all flags
	const BITFLAG_1 = 1;
	const BITFLAG_2 = 2;
	const BITFLAG_3 = 4;
	const BITFLAG_4 = 8;
	const BITFLAG_5 = 16;
	const BITFLAG_6 = 32;
	const BITFLAG_7 = 64;
	const BITFLAG_8 = 128;
	const BITFLAG_9 = 256;
	const BITFLAG_10 = 1024;
	const BITFLAG_11 = 2048;
	const BITFLAG_12 = 4096;
	const BITFLAG_13 = 8192;
	const BITFLAG_14 = 16384;
	const BITFLAG_15 = 32768;
	const BITFLAG_16 = 65536;
	const BITFLAG_17 = 131072;
	const BITFLAG_18 = 262144;
	const BITFLAG_19 = 524288;
	const BITFLAG_20 = 1048576;
	const BITFLAG_21 = 2097152;
	const BITFLAG_22 = 4194304;
	const BITFLAG_23 = 8388608;
	const BITFLAG_24 = 16777216;
	const BITFLAG_25 = 33554432;
	const BITFLAG_26 = 67108864;
	const BITFLAG_27 = 134217728;
	const BITFLAG_28 = 268435456;
	const BITFLAG_29 = 536870912;
	const BITFLAG_30 = 1073741824;

	private function __construct() {}
};

