<?php
define('CG_PATH',realpath(dirname(__FILE__)));

define('UM_PLUGIN_PATH',CG_PATH.'/plugins/');
//define('UM_ATTACHMENT_SAVE_PATH',CG_PATH.'/tmp/');
//define('UM_ATTACHMENT_ACCESS_PATH','http://localhost/cerb4/tmp/');
define('UM_DIRECTORY',basename(realpath(dirname(__FILE__))));
//define('UM_WEBPATH',''); // uncomment to override

define('UM_LANGUAGE','en');
define('UM_THEME','default');

define('UM_DEBUG','true');

define('UM_DB_DRIVER','mysql');
define('UM_DB_HOST','localhost');
define('UM_DB_DATABASE','devblocks');
define('UM_DB_USER','devblocks');
define('UM_DB_PASS','devblocks');

?>