<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty indent modifier plugin
 *
 * Type:     modifier<br>
 * Name:     explode<br>
 * Purpose:  split string into array
 * @param string
 * @param string
 * @return array
 */
function smarty_modifier_explode($string,$delim)
{
    return explode($delim,$string);
}

?>
