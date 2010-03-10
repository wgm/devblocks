<?php
class DevblocksStorageEngineDisk extends Extension_DevblocksStorageEngine {
	const ID = 'devblocks.storage.engine.disk'; 
	
	function __construct($manifest) {
		parent::__construct($manifest);
	}
	
	public function setOptions($options) {
		parent::setOptions($options);
		
		// Default
		if(!isset($this->_options['storage_path']))
			$this->_options['storage_path'] = APP_STORAGE_PATH . '/';
	}
	
	function testConfig() {
		$path = APP_STORAGE_PATH . '/';
		if(!is_writeable($path))
			return false;
			
		return true;
	}
	
	function renderConfig(Model_DevblocksStorageProfile $profile) {
		$tpl = DevblocksPlatform::getTemplateService();
		$path = dirname(dirname(__FILE__)) . '/templates';
		
		$tpl->assign('profile', $profile);
		
		$tpl->display("file:{$path}/storage_engine/config/disk.tpl");
	}
	
	function saveConfig(Model_DevblocksStorageProfile $profile) {
//		@$var = DevblocksPlatform::importGPC($_POST['var'],'string','');
		
//		$fields = array(
//			DAO_DevblocksStorageProfile::PARAMS_JSON => json_encode(array(
//				'var' => $var,
//			)),
//		);
		
//		DAO_DevblocksStorageProfile::update($profile->id, $fields);
	}
	
	public function exists($namespace, $key) {
		return file_exists($this->_options['storage_path'] . $this->escapeNamespace($namespace) . '/' . $key);
	}
	
	public function put($namespace, $id, $data) {
		// Get a unique hash path for this namespace+id
		$hash = base_convert(sha1($this->escapeNamespace($namespace).$id),16,32);
		$key_prefix = sprintf("%s/%s",
			substr($hash,0,1),
			substr($hash,1,1)
		);
		$path = sprintf("%s%s/%s",
			$this->_options['storage_path'],
			$this->escapeNamespace($namespace),
			$key_prefix
		);
		
		// Create the hash path if it doesn't exist
		if(!is_dir($path)) {
			if(false === mkdir($path, 0755, true)) {
				return false;
			}
		}
		
		// Write the content
		if(false === file_put_contents($path.'/'.$id, $data))
			return false;

		$key = $key_prefix.'/'.$id;
			
		return $key;
	}

	public function get($namespace, $key) {
		$path = sprintf("%s%s/%s",
			$this->_options['storage_path'],
			$this->escapeNamespace($namespace),
			$key
		);
			
		if(false === ($contents = file_get_contents($path)))
			return false;
			
		return $contents;
	}
	
	public function delete($namespace, $key) {
		$path = sprintf("%s%s/%s",
			$this->_options['storage_path'],
			$this->escapeNamespace($namespace),
			$key
		);
		
		if($this->exists($namespace, $key))
			return @unlink($path);
		
		return true;
	}	
};

class DevblocksStorageEngineDatabase extends Extension_DevblocksStorageEngine {
	const ID = 'devblocks.storage.engine.database';
	
	private $_db = null;
	
	function __construct($manifest) {
		parent::__construct($manifest);
	}
	
	public function setOptions($options) {
		parent::setOptions($options);
		
		// Use the existing local connection by default
		if(empty($this->_options['host'])) {
			$db = DevblocksPlatform::getDatabaseService();
			$this->_db = $db->getConnection();
			return true;
			
		// Use the provided connection details
		} else {
			if(false == ($this->_db = mysql_connect($this->_options['host'], $this->_options['user'], $this->_options['password'])))
				return false;
				
			if(false == mysql_select_db($this->_options['database'], $this->_db))
				return false;
		}
		
		return true;
	}	
	
	function testConfig() {
		@$host = DevblocksPlatform::importGPC($_POST['host'],'string','');
		@$user = DevblocksPlatform::importGPC($_POST['user'],'string','');
		@$password = DevblocksPlatform::importGPC($_POST['password'],'string','');
		@$database = DevblocksPlatform::importGPC($_POST['database'],'string','');
		
		if(empty($host)) {
			$host = APP_DB_HOST;
			$user = APP_DB_USER;
			$password = APP_DB_PASS;
			$database = APP_DB_DATABASE;
		}
		
		// Test connection
		if(false == (@$this->_db = mysql_connect($host, $user, $password)))
			return false;
			
		// Test switching DB
		if(false == @mysql_select_db($database, $this->_db))
			return false;
		
		return true;
	}
	
	function renderConfig(Model_DevblocksStorageProfile $profile) {
		$tpl = DevblocksPlatform::getTemplateService();
		$path = dirname(dirname(__FILE__)) . '/templates';
		
		$tpl->assign('profile', $profile);
		
		$tpl->display("file:{$path}/storage_engine/config/database.tpl");
	}
	
	function saveConfig(Model_DevblocksStorageProfile $profile) {
		@$host = DevblocksPlatform::importGPC($_POST['host'],'string','');
		@$user = DevblocksPlatform::importGPC($_POST['user'],'string','');
		@$password = DevblocksPlatform::importGPC($_POST['password'],'string','');
		@$database = DevblocksPlatform::importGPC($_POST['database'],'string','');
		
		$fields = array(
			DAO_DevblocksStorageProfile::PARAMS_JSON => json_encode(array(
				'host' => $host,
				'user' => $user,
				'password' => $password,
				'database' => $database,
			)),
		);
		
		DAO_DevblocksStorageProfile::update($profile->id, $fields);
	}
	
	private function _createTable($namespace) {
		$rs = mysql_query("SHOW TABLES", $this->_db);

		$tables = array();
		while($row = mysql_fetch_row($rs)) {
			$tables[$row[0]] = true;
		}
		
		$namespace = $this->escapeNamespace($namespace);
		
		if(isset($tables['storage_'.$namespace]))
			return true;
		
		$result = mysql_query(sprintf(
			"CREATE TABLE IF NOT EXISTS storage_%s (
				id INT UNSIGNED NOT NULL DEFAULT 0,
				data LONGBLOB,
				PRIMARY KEY (id)
			) ENGINE=MyISAM;",
			$this->escapeNamespace($namespace)
		), $this->_db);
		
		return (false !== $result) ? true : false;
	}
	
	public function exists($namespace, $key) {
		$result = mysql_query(sprintf("SELECT id FROM storage_%s WHERE id=%d",
			$this->escapeNamespace($namespace),
			$key
		), $this->_db);
		
		return (mysql_num_rows($result)) ? true : false;
	}

	private function _put($namespace, $id, $data) {
		$result = mysql_query(sprintf("REPLACE INTO storage_%s (id,data) VALUES (%d,'%s')",
			$this->escapeNamespace($namespace),
			$id,
			mysql_real_escape_string($data, $this->_db)
		), $this->_db);
		
		return (false !== $result) ? $id : false;
	}
	
	public function put($namespace, $id, $data) {
		// Try replacing first since this is the most efficient when things are working right
		$key = $this->_put($namespace, $id, $data);
		
		// If we failed, make sure the table exists
		if(false === $key) {
			if($this->_createTable($namespace)) {
				$key = $this->_put($namespace, $id, $data);
			}
		}
		
		return (false !== $key) ? $key : false;
	}

	public function get($namespace, $key) {
		if(false === ($result = mysql_query(sprintf("SELECT data FROM storage_%s WHERE id=%d",
				$this->escapeNamespace($namespace),
				$key
			), $this->_db)))
			return false;
			
		$row = mysql_fetch_assoc($result);
		return $row['data'];
	}

	public function delete($namespace, $key) {
		$result = mysql_query(sprintf("DELETE FROM storage_%s WHERE id=%d",
			$this->escapeNamespace($namespace),
			$key
		), $this->_db);
		
		return $result ? true : false;
	}	
};

class DevblocksStorageEngineS3 extends Extension_DevblocksStorageEngine {
	const ID = 'devblocks.storage.engine.s3';
	
	private $_s3 = null;
	private $_buckets = array();
	
	function __construct($manifest) {
		parent::__construct($manifest);
	}
	
	public function setOptions($options) {
		parent::setOptions($options);
		
		// [TODO] Fail, this info is required.
		if(!isset($this->_options['access_key']))
			$this->_options['access_key'] = '';
		if(!isset($this->_options['secret_key']))
			$this->_options['secret_key'] = '';
		
		$this->_s3 = new S3($this->_options['access_key'], $this->_options['secret_key']);
			
		if(false !== ($results = $this->_s3->listBuckets())) {
			foreach($results as $result) {
				$this->_buckets[$result] = array(); 
			}
		}
	}	
	
	function testConfig() {
		// [TODO] Test S3 connection info
		@$access_key = DevblocksPlatform::importGPC($_POST['access_key'],'string','');
		@$secret_key = DevblocksPlatform::importGPC($_POST['secret_key'],'string','');
		
		try {
			$s3 = new S3($access_key, $secret_key);
			if(@!$s3->listBuckets())
				return false;	
		} catch(Exception $e) {
			return false;
		}
		
		return true;
	}
	
	function renderConfig(Model_DevblocksStorageProfile $profile) {
		$tpl = DevblocksPlatform::getTemplateService();
		$path = dirname(dirname(__FILE__)) . '/templates';
		
		$tpl->assign('profile', $profile);
		
		$tpl->display("file:{$path}/storage_engine/config/s3.tpl");
	}
	
	function saveConfig(Model_DevblocksStorageProfile $profile) {
		@$access_key = DevblocksPlatform::importGPC($_POST['access_key'],'string','');
		@$secret_key = DevblocksPlatform::importGPC($_POST['secret_key'],'string','');
		@$bucket_prefix = DevblocksPlatform::importGPC($_POST['bucket_prefix'],'string','');
		
		$fields = array(
			DAO_DevblocksStorageProfile::PARAMS_JSON => json_encode(array(
				'access_key' => $access_key,
				'secret_key' => $secret_key,
				'bucket_prefix' => $bucket_prefix,
			)),
		);
		
		DAO_DevblocksStorageProfile::update($profile->id, $fields);
	}
	
	public function exists($namespace, $key) {
		$bucket = 'storage_'.$this->escapeNamespace($namespace);
		return false !== ($info = $this->_s3->getObjectInfo($bucket, $key));
	}
	
	public function put($namespace, $id, $data) {
		$bucket = 'storage_'.$this->escapeNamespace($namespace);
		
		if(!isset($this->_buckets[$bucket])) {
			if(false !== $this->_s3->putBucket($bucket, S3::ACL_PRIVATE)) {
				$this->_buckets[$bucket] = array();
			}
		}
		
		if(!isset($this->_buckets[$bucket]))
			return false;
		
		// Get a unique hash path for this namespace+id
		$hash = base_convert(sha1($this->escapeNamespace($namespace).$id), 16, 32);
		$path = sprintf("%s/%s/%d",
			substr($hash,0,1),
			substr($hash,1,1),
			$id
		);
		
		// Write the content
		if(false === $this->_s3->putObject($data, $bucket, $path, S3::ACL_PRIVATE)) {
			return false;
		}
		
		return $path;
	}

	public function get($namespace, $key) {
		$bucket = 'storage_'.$this->escapeNamespace($namespace);
		
		if(!isset($this->_buckets[$bucket])) {
			return false;
		}
		
		if(false === ($object = $this->_s3->getObject($bucket, $key)))
			return false;
			
		return $object->body;
	}
	
	public function delete($namespace, $key) {
		$bucket = 'storage_'.$this->escapeNamespace($namespace);

		return $this->_s3->deleteObject($bucket, $key);
	}	
};
