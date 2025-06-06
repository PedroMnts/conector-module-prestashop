<?php
/**
* 2007-2022 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2022 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/
$sql = array();

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pedidosinternet_logs` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `fecha_inicio` datetime NOT NULL ,
    `fecha_fin` datetime,
    `direccion` varchar(11) NOT NULL,
    `url` varchar(100) NOT NULL,
    `contenido` longtext,    
    `respuesta` longtext,    
    PRIMARY KEY  (`id`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pedidosinternet_configuracion` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `ultimos_cambios` datetime ,
    `usuario_api` varchar(50) NOT NULL ,
    `password_api` varchar(50) NOT NULL ,    
    `url` varchar(100) NOT NULL ,
    `url_append` varchar(100),
    `client_id` varchar(100),
    `client_secret` varchar(100),
    `scope` varchar(100),
    `access_token` text(10000) ,
    `refresh_token` text(10000) ,
    `expiracion_token` datetime ,
    PRIMARY KEY  (`id`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

$sql[] = <<< EOF
INSERT INTO `ps_pedidosinternet_configuracion` (`usuario_api`, `password_api`, `url`, `url_append`,`client_id`, `client_secret`, `scope`)
VALUES (
        'dperea@dobuss.es',
        'YD@k96Q2A0',
        'https://webapi.basterra.pedidosinternet.com:7443/',
        'pruebaswebapi',
        'distribb2b',
        ',hct3NbeMHJ',
        'openid offline_access profile roles SSOScope'
        )
EOF;

$columns = Db::getInstance()->executeS("SHOW COLUMNS FROM ". pSQL(_DB_PREFIX_) . "customer WHERE field LIKE 'api_id';");

if (!is_array($columns) || count($columns) == 0) {
    $sql[] = 'ALTER TABLE `' . pSQL(_DB_PREFIX_) . 'customer` ADD `api_id` INT NULL';
}

$columns = Db::getInstance()->executeS("SHOW COLUMNS FROM ". pSQL(_DB_PREFIX_) . "category WHERE field LIKE 'api_id';");

if (!is_array($columns) || count($columns) == 0) {
    $sql[] = 'ALTER TABLE `' . pSQL(_DB_PREFIX_) . 'category` ADD `api_id` varchar(50) NULL';
}

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
