<?php
function smarty_modifier_devblocks_prettytime($string, $format=null) {
	if(empty($string) || !is_numeric($string))
		return '';
	
	$diffsecs = time() - intval($string);
	$whole = '';		
	
	// The past
	if($diffsecs >= 0) {
		if($diffsecs >= 86400) { // days
			$whole = floor($diffsecs/86400).'d ago';
		} elseif($diffsecs >= 3600) { // hours
			$whole = floor($diffsecs/3600).'h ago';
		} elseif($diffsecs >= 60) { // mins
			$whole = floor($diffsecs/60).'m ago';
		} elseif($diffsecs >= 0) { // secs
			$whole = $diffsecs.'s ago';
		}
	} else { // The future
		if($diffsecs <= -86400) { // days
			$whole = floor($diffsecs/-86400).'d';
		} elseif($diffsecs <= -3600) { // hours
			$whole = floor($diffsecs/-3600).'h';
		} elseif($diffsecs <= -60) { // mins
			$whole = floor($diffsecs/-60).'m';
		} elseif($diffsecs <= 0) { // secs
			$whole = $diffsecs.'s';
		}
	}
	
	echo $whole;
};
?>