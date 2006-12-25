<?php
require(getcwd() . '/framework.config.php');
require(CG_PATH . '/libs/cloudglue/CloudGlue.class.php');
require(CG_PATH . '/api/DevblocksApplication.php');

CgPlatform::init();

$smarty = CgPlatform::getTemplateService();
$session = CgPlatform::getSessionService();
$translate = CgPlatform::getTranslationService();

//$plugins = CgPlatform::readPlugins();

// [JAS]: Handle component actions
@$c = (isset($_REQUEST['c']) ? $_REQUEST['c'] : null);
@$a = (isset($_REQUEST['a']) ? $_REQUEST['a'] : null);

if(null == $session)
	die("No database connection. Check settings.");

$visit = $session->getVisit();

if(!empty($c) && !empty($a)) {
	// [JAS]: [TODO] Split $c and look for an ID and an instance
	$mfTarget = CgPlatform::getExtension($c);
	$target = $mfTarget->createInstance();

	// [JAS]: Security check
//	if(empty($visit)) {
//		if (0 != strcasecmp($c,"core.module.signin") && !is_a($target, 'cerberusloginmoduleextension')) {
//			// [JAS]: [TODO] This should probably be a meta redirect for IIS.
//			header("Location: index.php?c=core.module.signin&a=show");
//			exit;
//		}
//	}
	
	if(method_exists($target,$a)) {
		call_user_method($a,$target); // [JAS]: [TODO] Action Args
	}
}

$modules = CgPlatform::getExtensions("com.devblocks.module");
$smarty->assign('modules', $modules);

$activeModuleId = DevblocksApplication::getActiveModule();

if(empty($activeModuleId)) {
	$activeModuleId = 'core.module.projects';
	DevblocksApplication::setActiveModule($activeModuleId);
}

$activeModuleManifest = CgPlatform::getExtension($activeModuleId);
$activeModule = $activeModuleManifest->createInstance(1);
$smarty->assign('activeModule', $activeModule);

$smarty->assign('session', $_SESSION);
$smarty->assign('visit', $session->getVisit());
$smarty->assign('translate', $translate);
$smarty->assign('c', $c);

$smarty->caching = 0;
$smarty->display('border.php');
?>