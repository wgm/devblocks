<?php

abstract class DevblocksEngine {
	protected static $plugins_cache = array();
	protected static $extensions_cache = array();
	protected static $points_cache = array();
	protected static $mapping_cache = array();
	
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
			
		include_once(DEVBLOCKS_PATH . 'domit/xml_domit_include.php');
		$rssRoot =& new DOMIT_Document();
		$success = $rssRoot->loadXML(DEVBLOCKS_PLUGIN_PATH.$dir.'/plugin.xml', false);
		$doc =& $rssRoot->documentElement; /* @var $doc DOMIT_Node */
		
		$eId = $doc->getElementsByPath("id",1);
		$eName = $doc->getElementsByPath("name",1);
		$eAuthor = $doc->getElementsByPath("author",1);
			
		$manifest = new DevblocksPluginManifest();
		$manifest->id = $eId->getText();
		$manifest->dir = $dir;
		$manifest->author = $eAuthor->getText();
		$manifest->name = $eName->getText();
		
		$db = DevblocksPlatform::getDatabaseService();

		$db->Replace(
			'plugin',
			array(
				'id' => $db->qstr($manifest->id),
				'name' => $db->qstr($manifest->name),
				'author' => $db->qstr($manifest->author),
				'dir' => $db->qstr($manifest->dir)
			),
			array('id'),
			false
		);
		
		// [JAS]: URI Mapping
		$db->Execute("DELETE FROM uri WHERE plugin_id = %s",
			$db->qstr($manifest->id)
		);
		
		$eUris =& $doc->getElementsByPath("mapping/uri"); /* @var $eUris DOMIT_NodeList */
		if(is_array($eUris->arNodeList))
		foreach($eUris->arNodeList as $eUri) { /* @var $eUri DOMIT_Node */
			$sUri = $eUri->getAttribute('value');
			$sExtensionId = $eUri->getAttribute('extension_id');
			
			$db->Replace(
				'uri',
				array(
					'uri' => $db->qstr($sUri),
					'plugin_id' => $db->qstr($manifest->id),
					'extension_id' => $db->qstr($sExtensionId)
				),
				array('uri'),
				false
			);
		};
				
		$eExtensions =& $doc->getElementsByPath("extensions/extension"); /* @var $eExtensions DOMIT_NodeList */
		if(is_array($eExtensions->arNodeList))
		foreach($eExtensions->arNodeList as $eExtension) { /* @var $eExtension DOMIT_Node */
			
			$sPoint = $eExtension->getAttribute('point');
			$eId = $eExtension->getElementsByPath('id',1);
			$eName = $eExtension->getElementsByPath('name',1);
			$eClassName = $eExtension->getElementsByPath('class/name',1);
			$eClassFile = $eExtension->getElementsByPath('class/file',1);
			$params = $eExtension->getElementsByPath('params/param');
			$extension = new DevblocksExtensionManifest();

			if(empty($eId) || empty($eName))
				continue;

			$extension->id = $eId->getText();
			$extension->plugin_id = $manifest->id;
			$extension->point = $sPoint;
			$extension->name = $eName->getText();
			$extension->file = $eClassFile->getText();
			$extension->class = $eClassName->getText();
				
			if(null != $params && !empty($params->arNodeList)) {
				foreach($params->arNodeList as $pnode) {
					$sKey = $pnode->getAttribute('key');
					$sValue = $pnode->getAttribute('value');
					$extension->params[$sKey] = $sValue;
				}
			}
				
			$manifest->extensions[] = $extension;
		}
		
		// [JAS]: Extension caching
		if(is_array($manifest->extensions))
		foreach($manifest->extensions as $pos => $extension) { /* @var $extension DevblocksExtensionManifest */
			$db->Replace(
				'extension',
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

		return $manifest;
	}
	
	/**
	 * @return DevblocksHttpRequest
	 */
	static function readRequest() {
		$url = DevblocksPlatform::getUrlService();
		
		if(DEVBLOCKS_REWRITE) {
			$parts = $url->parseURL($_SERVER['REQUEST_URI']);
			$query = $_SERVER['QUERY_STRING'];
			
		} else {
			$argc = $url->parseQueryString($_SERVER['QUERY_STRING']);
			$parts = array_values($argc);
			$query = '';
		}

		if(empty($parts)) {
			// Overrides (Form POST, etc.)
			@$uri = DevblocksPlatform::importGPC($_REQUEST['c']); // extension
			if(!empty($uri)) $parts[] = $uri;

			@$listener = DevblocksPlatform::importGPC($_REQUEST['a']); // listener
			if(!empty($listener)) $parts[] = $listener;
			
			// Use our default URI if we didn't have an override
			if(empty($parts)) $parts[] = APP_DEFAULT_URI;
		}
		
		$request = new DevblocksHttpRequest($parts,$query); 
		DevblocksPlatform::setHttpRequest($request);
		
		return $request;
	}
	
	/**
	 * @param DevblocksHttpRequest $request
	 */
	static function processRequest($request,$is_ajax=false) {
		if(!is_a($request,'DevblocksHttpRequest')) return null;
		
		$path = $request->path;
		$command = array_shift($path);
		
		// [JAS]: Offer the platform a chance to intercept.
		switch($command) {

			// [JAS]: Resource proxy URI
			case 'resource':
				$plugin_id = $path[0];
				$file = $path[1];
				
				$plugin = DevblocksPlatform::getPlugin($plugin_id);
				if(null == $plugin) continue;
				
				// [JAS]: [TODO] Run through an audit to make sure this isn't abusable (../plugin.xml, etc.)
				$dir = DEVBLOCKS_PLUGIN_PATH . $plugin->dir . '/resources/'.$file;
				
				if(file_exists($dir)) {
					echo file_get_contents($dir,false);
				} else {
					echo "Requested resource not found!";
				}
								
				break;
				
			// [JAS]: Plugin-supplied URIs
			default:
				$mapping = DevblocksPlatform::getMappingRegistry();
				
				/*
				 * [JAS]: Try to find our command in the URI lookup first, and if we
				 * fail then fall back to raw extension ids.
				 */
				if(null == ($extension_id = $mapping[$command])) {
					$extension_id = $command;
				}
				
				if(null != ($manifest = DevblocksPlatform::getExtension($extension_id))) {
					$inst = $manifest->createInstance(); /* @var $inst DevblocksHttpRequestHandler */
					
					if($inst instanceof DevblocksHttpRequestHandler) {
						$inst->handleRequest($request);
						
						// [JAS]: If we didn't write a new response, repeat the request
						if(null == ($response = DevblocksPlatform::getHttpResponse())) {
							$response = new DevblocksHttpResponse($request->path);
							DevblocksPlatform::setHttpResponse($response);
						}
						
						// [JAS]: An Ajax request doesn't need the full Http cycle
						if(!$is_ajax) {
							$inst->writeResponse($response);
						}
					}
					
				} else {
					// [JAS]: [TODO] This would be a good point for a 404 URI Platform option		
					echo "No request handler was found for this URI.";
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
		$tpl->caching = 2;
		$tpl->cache_lifetime = 3600*24; // 1 day
		$tpl->display("file:$path/devblocks.tpl.js",APP_BUILD);
		$tpl->caching = 0;
	}
	
	/*
	 * [JAS]: [TODO] This should move into a DatabaseUpdateService area later 
	 * (where plugins can also contribute their patches)
	 */
	static function getDatabaseSchema() {
		$tables = array();
		
		$tables['extension'] = "
			id C(128) DEFAULT '' NOTNULL PRIMARY,
			plugin_id C(128) DEFAULT 0 NOTNULL,
			point C(128) DEFAULT '' NOTNULL,
			pos I2 DEFAULT 0 NOTNULL,
			name C(128) DEFAULT '' NOTNULL,
			file C(128) DEFAULT '' NOTNULL,
			class C(128) DEFAULT '' NOTNULL,
			params B DEFAULT '' NOTNULL
		";
		
		$tables['plugin'] = "
			id C(128) PRIMARY,
			enabled I1 DEFAULT 0 NOTNULL,
			name C(128) DEFAULT '' NOTNULL,
			author C(64) DEFAULT '' NOTNULL,
			dir C(128) DEFAULT '' NOTNULL
		";
		
		$tables['property_store'] = "
			extension_id C(128) DEFAULT '' NOTNULL PRIMARY,
			instance_id I DEFAULT 0 NOTNULL PRIMARY,
			property C(128) DEFAULT '' NOTNULL PRIMARY,
			value C(255) DEFAULT '' NOTNULL
		";
		
		$tables['session'] = "
			sesskey C(64) PRIMARY,
			expiry T,
			expireref C(250),
			created T NOTNULL,
			modified T NOTNULL,
			sessdata B
		";
		
		$tables['login'] = "
			id I PRIMARY,
			login C(32) NOTNULL,
			password C(32) NOTNULL,
			admin I1 DEFAULT 0 NOTNULL
		";
		
		$tables['uri'] = "
			uri C(32) PRIMARY,
			plugin_id C(128) NOTNULL,
			extension_id C(128) NOTNULL
		";

		return $tables;
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
			if(!$db->IsConnected()) return null;
			
			include_once(DEVBLOCKS_PATH . "adodb/session/adodb-session2.php");
			$options = array();
			$options['table'] = 'session';
			ADOdb_Session::config(APP_DB_DRIVER, APP_DB_HOST, APP_DB_USER, APP_DB_PASS, APP_DB_DATABASE, $options);
			ADOdb_session::Persist($connectMode=false);
			ADOdb_session::lifetime($lifetime=86400);
			
			//session_name("cerb4");
			session_set_cookie_params(0);
			session_start();
			$instance = new _DevblocksSessionManager();
			$instance->visit = $_SESSION['um_visit']; /* @var $visit DevblocksSession */
		}
		
		return $instance;
	}
	
	/**
	 * Returns the current session or NULL if no session exists.
	 *
	 * @return DevblocksSession
	 */
	function getVisit() {
		return $this->visit;
	}
	
	/**
	 * Attempts to create a session by login/password.  On success a DevblocksSession 
	 * is returned.  On failure NULL is returned.
	 *
	 * @param string $login
	 * @param string $password
	 * @return DevblocksSession
	 */
	function login($login,$password) {
		$db = DevblocksPlatform::getDatabaseService();
		
		$sql = sprintf("SELECT id,login,admin ".
			"FROM login ".
			"WHERE login = %s ".
			"AND password = MD5(%s)",
				$db->qstr($login),
				$db->qstr($password)
		);
		$rs = $db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		
		if($rs->NumRows()) {
			$visit = new DevblocksSession();
				$visit->id = intval($rs->fields['id']);
				$visit->login = $rs->fields['login'];
				$visit->admin = intval($rs->fields['admin']);
			$this->visit = $visit;
			$_SESSION['um_visit'] = $visit;
			return $this->visit;
		}
		
		$_SESSION['um_visit'] = null;
		return null;
	}
	
	/**
	 * Kills the current session.
	 *
	 */
	function logout() {
		$this->visit = null;
		unset($_SESSION['um_visit']);
	}
}

/**
 * Email Management Singleton
 *
 * @static 
 * @ingroup services
 */
class _DevblocksEmailManager {
	/**
	 * @private
	 */
	private function _DevblocksEmailManager() {}
	
	public function getInstance() {
		static $instance = null;
		if(null == $instance) {
			$instance = new _DevblocksEmailManager();
		}
		return $instance;
	}
	
	static function send($server, $sRCPT, $headers, $body) {
		// mailer setup
		require_once(DEVBLOCKS_PATH . 'pear/Mail.php');
		$mail_params = array();
		$mail_params['host'] = $server;
		$mailer =& Mail::factory("smtp", $mail_params);

		$result = $mailer->send($sRCPT, $headers, $body);
		return $result;
	}
	
	static function getMessages($server, $port, $service, $username, $password) {
		if (!extension_loaded("imap")) die("IMAP Extension not loaded!");
		require_once(DEVBLOCKS_PATH . 'pear/mimeDecode.php');
		
		$mailbox = imap_open("{".$server.":".$port."/service=".$service."/notls}INBOX",
							 !empty($username)?$username:"superuser",
							 !empty($password)?$password:"superuser")
			or die("Failed with error: ".imap_last_error());
		$check = imap_check($mailbox);
		
		$messages = array();
		$params = array();
		$params['include_bodies']	= true;
		$params['decode_bodies']	= true;
		$params['decode_headers']	= true;
		$params['crlf']				= "\r\n";
	
		for ($i=1; $i<=$check->Nmsgs; $i++) {
			$headers = imap_fetchheader($mailbox, $i);
			$body = imap_body($mailbox, $i);
			$params['input'] = $headers . "\r\n\r\n" . $body;
			$structure = Mail_mimeDecode::decode($params);
			$messages[] = $structure;
		}
		
		imap_close($mailbox);
		return $messages;
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
			require(DEVBLOCKS_PATH . 'smarty/Smarty.class.php');
			$instance = new Smarty();
			$instance->template_dir = APP_PATH . '/templates';
			$instance->compile_dir = DEVBLOCKS_PATH . 'templates_c';
			$instance->cache_dir = DEVBLOCKS_PATH . 'cache';
			
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
			include_once(DEVBLOCKS_PATH . "adodb/adodb.inc.php");
			$ADODB_CACHE_DIR = APP_PATH . "/cache";
			@$instance =& ADONewConnection(APP_DB_DRIVER); /* @var $instance ADOConnection */
			@$instance->Connect(APP_DB_HOST,APP_DB_USER,APP_DB_PASS,APP_DB_DATABASE);
			@$instance->SetFetchMode(ADODB_FETCH_ASSOC);
		}
		return $instance;
	}
};

/**
 * Unicode Translation Singleton
 *
 * @ingroup services
 */
class _DevblocksTranslationManager {
	/**
	 * Constructor
	 * 
	 * @private
	 */
	private function _DevblocksTranslationManager() {}
	
	/**
	 * Returns an instance of the translation singleton.
	 *
	 * @static 
	 * @return DevblocksTranslationManager
	 */
	static function getInstance() {
		static $instance = null;
		if(null == $instance) {
			$instance = new _DevblocksTranslationManager();
		}
		return $instance;
	}

	/**
	 * Translate an externalized string token into a Unicode string in the 
	 * current language.  The $vars argument provides a list of substitutions 
	 * similar to sprintf().
	 *
	 * @param string $token The externalized string token to replace
	 * @param array $vars A list of substitutions
	 * @return string A string with the Unicode values encoded in UTF-8
	 */
	function say($token,$vars=array()) {
		global $language;
		
		if(!isset($language[$token]))
			return "[#".$token."#]";
		
		if(!empty($vars)) {
			$u = new I18N_UnicodeString(vsprintf($language[$token],$vars),'UTF8');
		} else {
			$u = new I18N_UnicodeString($language[$token],'UTF8');
		}
		return $u->toUtf8String();
	}

};

class _DevblocksUrlManager {
	private function __construct() {}
	
	/**
	 * @return _DevblocksUrlManager
	 */
	public static function getInstance() {
		static $instance = null;
		if(null == $instance) {
			$instance = new _DevblocksUrlManager();
		}
		return $instance;
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
			$argc[$v[0]] = $v[1];
		}
		
		return $argc;
	}
	
	function parseURL($url) {
		// [JAS]: Use the index.php page as a reference to deconstruct the URI
		$pos = stripos($_SERVER['PHP_SELF'],'index.php',0);
		if($pos === FALSE) return null;
		
		// [JAS]: Extract the basedir of the path
		$basedir = substr($url,0,$pos);
		
		// [JAS]: Remove query string
		$pos = stripos($url,'?',0);
		if($pos !== FALSE) {
			$url = substr($url,0,$pos);
		}
		
		$request = substr($url,strlen($basedir));
		if(empty($request)) return array();
		
		$parts = split('/', $request);
		
		return $parts;
	}
	
	function write($sQuery='') {
		$url = DevblocksPlatform::getUrlService();
		$args = $url->parseQueryString($sQuery);
		$c = @$args['c'];
		
		// [JAS]: Internal non-component URL (images/css/js/etc)
		if(empty($c)) {
			$contents = sprintf("%s%s",
				DEVBLOCKS_WEBPATH,
				$sQuery
			);
			
		// [JAS]: Component URL
		} else {
			if(DEVBLOCKS_REWRITE) {
				$contents = sprintf("%s%s",
					DEVBLOCKS_WEBPATH,
					(!empty($args) ? implode('/',array_values($args)) : '')
				);
				
			} else {
				$contents = sprintf("%sindex.php?",
					DEVBLOCKS_WEBPATH,
					(!empty($args) ? $args : '')
				);
			}
		}
		
		return $contents;
	}
};

?>