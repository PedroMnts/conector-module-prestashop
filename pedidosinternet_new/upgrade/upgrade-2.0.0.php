<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_2_0_0($module) {
    // Registrar los nuevos hooks
    if (!$module->registerHook('actionObjectAddressAddAfter') || 
        !$module->registerHook('actionObjectAddressDeleteAfter')) {
        return false;
    }
    
    return true;
}