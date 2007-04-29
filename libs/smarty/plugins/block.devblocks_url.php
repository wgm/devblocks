<?php
function smarty_block_devblocks_url($params, $content, &$smarty) {
	$url = DevblocksPlatform::getUrlService();
	$contents = $url->write($content);
	
    if (!empty($params['assign'])) {
        $smarty->assign($params['assign'], $contents);
    } else {
        return $contents;
    }
}
?>