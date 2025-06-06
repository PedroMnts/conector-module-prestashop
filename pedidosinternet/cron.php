<?php
require_once(dirname(__FILE__).'/../../config/config.inc.php');

ini_set('log_errors', 1);
ini_set('error_log', '/var/www/vhosts/vinelials.com/httpdocs/modules/pedidosinternet/cron_error.log');

$pedidosinternet = Module::getInstanceByName('pedidosinternet');

// Check token
$token = Tools::getValue('token');
if ($token !== Configuration::get('PEDIDOSINTERNET_CRON_TOKEN')) {
	die('Invalid token');
}

// Your cron task logic here
$pedidosinternet->processCronTask();

clearPrestashopCache();

exit;

/**
 * Función para limpiar/renovar la caché en PrestaShop
 */
function clearPrestashopCache()
{
    Tools::clearSmartyCache();
    Tools::clearXMLCache();

    Cache::clean('*');

    Tools::generateIndex();

    Media::clearCache();

    PrestaShopLogger::addLog('Caché limpiada correctamente después de processCronTask', 1);
}