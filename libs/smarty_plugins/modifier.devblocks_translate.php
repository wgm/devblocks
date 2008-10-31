<?php
/**
 * Devblocks Translate modifier plugin
 */
function smarty_modifier_devblocks_translate($string) {
	$translate = DevblocksPlatform::getTranslationService();
	
	// Variable number of arguments
	$args = func_get_args();
	array_shift($args); // pop off $string
	
	$translated = $translate->_($string);
	$translated = @vsprintf($translated,$args);
	return $translated;
}

?>
