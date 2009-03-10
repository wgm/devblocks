<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */

/**
 * Smarty devblocks_date modifier plugin
 */
function smarty_modifier_devblocks_date($string, $format=null) {
	if(empty($string))
		return '';

	$date = DevblocksPlatform::getDateService();
	return $date->formatTime($format, $string);
}

/* vim: set expandtab: */

?>
