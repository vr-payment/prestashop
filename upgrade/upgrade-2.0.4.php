<?php

if (!defined('_PS_VERSION_')) {
	exit;
}

function upgrade_module_2_0_4($module)
{
	try {
		require_once _PS_MODULE_DIR_ . 'vrpayment/inc/Webhooksignature.php';

		$moduleInstance = $module;
		if (!($moduleInstance instanceof VRPayment) || !$moduleInstance->id) {
			$moduleInstance = \Module::getInstanceByName('vrpayment');
		}

		if ($moduleInstance && !$moduleInstance->id) {
			$moduleInstance->id = (int) \Module::getModuleIdByName('vrpayment');
		}

		if (!$moduleInstance || !$moduleInstance->id) {
			throw new \Exception('Unable to load module with a valid id for hook registration.');
		}

		$result = $moduleInstance->registerHook('actionValidateStepComplete') && $moduleInstance->registerHook('actionObjectAddressAddAfter');

		// Run webhook listeners signature update
		Webhooksignature::run();

		if (!$result) {
			throw new \Exception('Hook registration failed.');
		}

		return true;

	} catch (\Exception $e) {
		error_log("Upgrade 2.0.4 failed: " . $e->getMessage());
		return false;
	}
}
