<?php
abstract class DevblocksApplication {
	
}

/**
 * The superclass of instanced extensions.
 *
 * @abstract 
 * @ingroup plugin
 */
class DevblocksExtension {
	var $manifest = null;
	var $instance_id = 1;
	var $id  = '';
	var $params = array();
	
	/**
	 * Constructor
	 *
	 * @private
	 * @param DevblocksExtensionManifest $manifest
	 * @param int $instance_id
	 * @return DevblocksExtension
	 */
	function DevblocksExtension($manifest,$instance_id=1) { /* @var $manifest DevblocksExtensionManifest */
		$this->manifest = $manifest;
		$this->id = $manifest->id;
		$this->instance_id = $instance_id;
		$this->params = $this->_getParams();
	}
	
	/**
	 * Loads parameters unique to this extension instance.  Returns an 
	 * associative array indexed by parameter key.
	 *
	 * @private
	 * @return array
	 */
	function _getParams() {
//		static $params = null;
		
		if(empty($this->id) || empty($this->instance_id))
			return null;
		
//		if(null != $params)
//			return $params;
		
		$params = $this->manifest->params;
		$db = DevblocksPlatform::getDatabaseService();
		$prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup
		
		$sql = sprintf("SELECT property,value ".
			"FROM %sproperty_store ".
			"WHERE extension_id=%s AND instance_id='%d' ",
			$prefix,
			$db->qstr($this->id),
			$this->instance_id
		);
		$rs = $db->Execute($sql) or die(__CLASS__ . ':' . $db->ErrorMsg()); /* @var $rs ADORecordSet */
		
		while(!$rs->EOF) {
			$params[$rs->fields['property']] = $rs->fields['value'];
			$rs->MoveNext();
		}
		
		return $params;
	}
	
	/**
	 * Persists any changed instanced extension parameters.
	 *
	 * @return void
	 */
	function saveParams() {
		if(empty($this->instance_id) || empty($this->id))
			return FALSE;
		
		$db = DevblocksPlatform::getDatabaseService();
		$prefix = (APP_DB_PREFIX != '') ? APP_DB_PREFIX.'_' : ''; // [TODO] Cleanup
		
		if(is_array($this->params))
		foreach($this->params as $k => $v) {
			$db->Replace(
				$prefix.'property_store',
				array('extension_id'=>$this->id,'instance_id'=>$this->instance_id,'property'=>$db->qstr($k),'value'=>$db->qstr($v)),
				array('extension_id','instance_id','property'),
				true
			);
		}
	}
};

/**
 * 
 */
abstract class DevblocksPatchContainerExtension extends DevblocksExtension {
	private $patches = array();

	function __construct($manifest) {
		$this->DevblocksExtension($manifest, 1);
	}
		
	public function registerPatch(DevblocksPatch $patch) {
		// index by revision
		$rev = $patch->getRevision();
		$this->patches[$rev] = $patch;
		ksort($this->patches);
	}
	
	public function run() {
		if(is_array($this->patches))
		foreach($this->patches as $rev => $patch) { /* @var $patch DevblocksPatch */
			if(!$patch->run())
				return FALSE;
		}
		
		return TRUE;
	}
	
	public function runRevision($rev) {
		return TRUE;
	}
};

interface DevblocksHttpRequestHandler {
	/**
	 * @param DevblocksHttpRequest
	 * @return DevblocksHttpResponse
	 */
	public function handleRequest($request);
	public function writeResponse($response);
}

class DevblocksHttpRequest extends DevblocksHttpIO {
	/**
	 * @param array $path
	 */
	function __construct($path) {
		parent::__construct($path);
	}
}

class DevblocksHttpResponse extends DevblocksHttpIO {
	/**
	 * @param array $path
	 */
	function __construct($path) {
		parent::__construct($path);
	}
}

abstract class DevblocksHttpIO {
	public $path = array();
//	public $query = null;
	
	/**
	 * Enter description here...
	 *
	 * @param array $path
	 */
	function __construct($path) {
		$this->path = $path;
//		$this->query = $query;
	}
}
?>