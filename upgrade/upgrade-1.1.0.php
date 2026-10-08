<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_0($module)
{
    $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'ecom_mbc_solicitud` (
        `id_solicitud` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
        `email_hash` CHAR(64) NOT NULL,
        `ip_hash` CHAR(64) NOT NULL,
        `id_shop` INT(11) UNSIGNED NOT NULL,
        `date_add` DATETIME NOT NULL,
        PRIMARY KEY (`id_solicitud`),
        KEY `email_fecha` (`email_hash`, `date_add`),
        KEY `ip_fecha` (`ip_hash`, `date_add`)
    ) ENGINE=' . (defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'InnoDB') . ' DEFAULT CHARSET=utf8mb4';

    return Db::getInstance()->execute($sql)
        && Configuration::updateGlobalValue('ECOM_MBC_UPDATE_CHECK', 0);
}
