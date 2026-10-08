<?php
$root = dirname(__DIR__);
$controller = file_get_contents($root . '/controllers/front/unsubscribe.php');
$module = file_get_contents($root . '/ecom_mailbajaclick.php');
$token = file_get_contents($root . '/classes/Ecom_MailbajaclickToken.php');
$baja = file_get_contents($root . '/classes/Ecom_MailbajaclickBaja.php');
$override = file_get_contents($root . '/override/classes/Mail.php');

$checks = array(
    'RFC 8058' => strpos($controller, 'List-Unsubscribe') !== false,
    'Confirmación POST' => strpos($controller, 'procesarConfirmacion') !== false,
    'CSRF firmado' => strpos($controller, 'validarCsrf') !== false,
    'Longitud máxima' => strpos($token, '> 1024') !== false,
    'Fecha futura' => strpos($token, 'time() + 300') !== false,
    'Filtro cerrado' => strpos($baja, 'AND 1 = 0') !== false,
    'Transacción' => strpos($baja, 'START TRANSACTION') !== false,
    'Lista de supresión' => strpos($module, 'estaSuprimido') !== false,
    'Cabeceras existentes' => strpos($module, 'hasHeader') !== false,
    'Compatibilidad 9.2' => strpos($module, '9.2.99') !== false,
    'Plantilla test forzada' => strpos($module, 'plantilla ===') !== false,
    'Prueba directa cubierta' => strpos($override, 'sendMailTest') !== false,
    'Cabeceras antes de DKIM' => strpos($override, 'forzarCabeceras') < strpos($override, 'DkimSigner'),
    'Selector doble' => strpos($module, 'sincronizarPlantillasDetectadas') !== false,
    'Transaccionales excluidas' => strpos($module, 'esPlantillaTransaccional') !== false,
    'Nuevas activas' => strpos($module, '$activas[] = $nombre') !== false,
    'Separadores PHP válidos' => strpos($module, 'implode(\\n') === false,
);

$fallos = 0;
foreach ($checks as $nombre => $ok) {
    echo ($ok ? 'OK   ' : 'FALLO') . ' ' . $nombre . PHP_EOL;
    if (!$ok) {
        ++$fallos;
    }
}
exit($fallos ? 1 : 0);
