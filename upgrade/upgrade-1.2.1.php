<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_2_1($module)
{
    $module->addnewfeatures();
    return true;
}
