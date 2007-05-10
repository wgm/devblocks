<?php
@ini_set('session.gc_maxlifetime','86400');
@ini_set('session.save_path',DEVBLOCKS_PATH . 'tmp/');
// [TODO]: We need a way to get around this requirement for libs
@set_include_path(
	DEVBLOCKS_PATH . 'libs' . PATH_SEPARATOR .
	DEVBLOCKS_PATH . 'libs/ZendFramework' . PATH_SEPARATOR .  
	get_include_path());
@ini_set('magic_quotes_gpc',0);
@set_magic_quotes_runtime(0);

if(!defined('APP_DB_DRIVER'))
define('APP_DB_DRIVER','mysql');

if(!defined('APP_DB_HOST'))
define('APP_DB_HOST','localhost');

if(!defined('APP_DB_DATABASE'))
define('APP_DB_DATABASE','');

if(!defined('APP_DB_USER'))
define('APP_DB_USER','');

if(!defined('APP_DB_PASS'))
define('APP_DB_PASS','');

if(!defined('APP_DB_PREFIX'))
define('APP_DB_PREFIX','');

if(!defined('DEVBLOCKS_LANGUAGE'))
define('DEVBLOCKS_LANGUAGE','en');

if(!defined('DEVBLOCKS_THEME'))
define('DEVBLOCKS_THEME','default');

if(!defined('DEVBLOCKS_REWRITE'))
define('DEVBLOCKS_REWRITE',false);

if(!defined('DEVBLOCKS_DEBUG'))
define('DEVBLOCKS_DEBUG',false);

if(!defined('DEVBLOCKS_MEMCACHE_HOST'))
define('DEVBLOCKS_MEMCACHE_HOST','');

if(!defined('DEVBLOCKS_MEMCACHE_PORT'))
define('DEVBLOCKS_MEMCACHE_PORT','11211');

if(!defined('APP_DEFAULT_CONTROLLER'))
define('APP_DEFAULT_CONTROLLER',''); // 404?

if(!defined('APP_PATH'))
define('APP_PATH',realpath(dirname(__FILE__)));

if(!defined('DEVBLOCKS_PATH'))
define('DEVBLOCKS_PATH',APP_PATH . '/libs/devblocks/');

if(!defined('DEVBLOCKS_PLUGIN_PATH'))
define('DEVBLOCKS_PLUGIN_PATH',APP_PATH.DIRECTORY_SEPARATOR.'plugins'.DIRECTORY_SEPARATOR);

//define('DEVBLOCKS_ATTACHMENT_SAVE_PATH',DEVBLOCKS_PATH.'tmp/');
//define('DEVBLOCKS_ATTACHMENT_ACCESS_PATH','http://localhost/cerb4/devblocks/tmp/');
//define('DEVBLOCKS_WEBPATH',''); // uncomment to override

if(!defined('LANG_CHARSET_MAIL_CONTENT_TYPE'))
define('LANG_CHARSET_MAIL_CONTENT_TYPE','text/plain');

if(!defined('LANG_CHARSET_CODE'))
define('LANG_CHARSET_CODE','iso-8859-1');

if(!defined('APP_SESSION_NAME'))
define('APP_SESSION_NAME', 'Devblocks');

