/**
 * Baja en un clic (List-Unsubscribe)
 *
 * @author    Ecom Experts <ecomyseo@gmail.com>
 * @copyright 2026 Ecom Experts
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License (AFL 3.0)
 */
CREATE TABLE IF NOT EXISTS `PREFIX_ecom_mbc_baja` (
    `id_baja` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(255) NOT NULL,
    `id_shop` INT(11) UNSIGNED NOT NULL DEFAULT 1,
    `metodo` VARCHAR(20) NOT NULL DEFAULT 'oneclick',
    `origen` VARCHAR(64) NOT NULL DEFAULT '',
    `ip` VARCHAR(46) NOT NULL DEFAULT '',
    `agente` VARCHAR(255) NOT NULL DEFAULT '',
    `resultado` VARCHAR(255) NOT NULL DEFAULT '',
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_baja`),
    KEY `email` (`email`),
    KEY `email_shop` (`email`, `id_shop`),
    KEY `date_add` (`date_add`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `PREFIX_ecom_mbc_solicitud` (
    `id_solicitud` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `email_hash` CHAR(64) NOT NULL,
    `ip_hash` CHAR(64) NOT NULL,
    `id_shop` INT(11) UNSIGNED NOT NULL,
    `date_add` DATETIME NOT NULL,
    PRIMARY KEY (`id_solicitud`),
    KEY `email_fecha` (`email_hash`, `date_add`),
    KEY `ip_fecha` (`ip_hash`, `date_add`)
) ENGINE=ENGINE_TYPE DEFAULT CHARSET=utf8mb4;
