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
	
	try {
		$date = new Zend_Date($string);
	} catch (Zend_Date_Exception $zde) {
		$date = new Zend_Date(Zend_Date::now());
	}
	
	if(empty($format)) {
		return $date->get(Zend_Date::RFC_2822);
	} else {
		return $date->toString($format);	
	}
}

/* vim: set expandtab: */

?>
