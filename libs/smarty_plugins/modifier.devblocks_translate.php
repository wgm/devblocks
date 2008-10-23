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
	
	$hits = 0;
	$pos = 0;
	$find = '%s';
	
	if(extension_loaded('mbstring') && function_exists('mb_strpos')) {
		while(false !== ($pos = mb_strpos($translated,$find,$pos,LANG_CHARSET_CODE))) {
			$hits++;
			$pos += mb_strlen($find); // skip the matched %s
		}
		
	} else {
		while(false !== ($pos = stripos($translated,$find,$pos))) {
			$hits++;
			$pos += strlen($find); // skip the matched %s
		}
		
	}

	// If we have enough arguments, go to town
	if($hits == count($args))
		$translated = @vsprintf($translated,$args);
		
	// Return what we can (sans replacements0
	return $translated;
}

?>
