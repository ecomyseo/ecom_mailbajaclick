<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_1($module)
{
    if (Configuration::get('PS_DISABLE_MODULE_OVERRIDES')) {
        return false;
    }
    return $module->installOverrides();
}
