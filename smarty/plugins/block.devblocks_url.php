<?php
function smarty_block_devblocks_url($params, $content, &$smarty) {
	$rewrite = true;

	@$c = $params['c'];
	@$a = $params['a'];

	$url = URL::getInstance();
	$contents = $url->write($c,$a,$content);
	
    if (!empty($params['assign'])) {
        $smarty->assign($params['assign'], $contents);
    } else {
        return $contents;
    }
}
?>