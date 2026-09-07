<?php
/**
 * Baja en un clic (List-Unsubscribe)
 *
 * @author    Ecom Experts <ecomyseo@gmail.com>
 * @copyright 2026 Ecom Experts
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Ejecuta y registra la baja de un correo.
 *
 * Actua sobre la casilla "newsletter" de la ficha de cliente y sobre la tabla
 * del modulo nativo de suscripcion (ps_emailsubscription), y deja constancia en
 * la tabla propia del modulo. Ademas dispara el hook
 * actionEcomMailbajaclickUnsubscribe para que cualquier otro modulo (un
 * conector de Mailchimp, Brevo, etc.) pueda propagar la baja a su plataforma.
 */
class Ecom_MailbajaclickBaja
{
    /** Nombre de la tabla del registro de bajas, sin prefijo. */
    const TABLA = 'ecom_mbc_baja';

    /** @var array Guarda las bajas ya procesadas en esta peticion. */
    protected static $procesadas = array();

    /**
     * Da de baja un correo.
     *
     * @param string $email
     * @param int    $idShop 0 = tienda del contexto
     * @param string $metodo oneclick|enlace|formulario|manual
     * @param string $origen Plantilla de correo o texto libre
     *
     * @return array array('ok'=>bool,'ya_estaba'=>bool,'clientes'=>int,'suscripciones'=>int,'mensaje'=>string)
     */
    public static function ejecutar($email, $idShop = 0, $metodo = 'oneclick', $origen = '')
    {
        $resultado = array(
            'ok' => false,
            'ya_estaba' => false,
            'clientes' => 0,
            'suscripciones' => 0,
            'mensaje' => '',
        );

        $email = trim((string) $email);
        if (!Validate::isEmail($email)) {
            $resultado['mensaje'] = 'Correo no válido';
            Ecom_MailbajaclickLog::add('Correo no válido en la baja', 'baja', array('email' => $email));

            return $resultado;
        }

        if (!$idShop) {
            $idShop = (int) Context::getContext()->shop->id;
        }

        // Guardia en memoria: dos llamadas en la misma peticion no duplican el registro.
        $llave = Tools::strtolower($email) . '|' . (int) $idShop;
        if (isset(self::$procesadas[$llave])) {
            return self::$procesadas[$llave];
        }

        $todasTiendas = (bool) Configuration::getGlobalValue('ECOM_MBC_ALL_SHOPS');
        $idsShop = $todasTiendas ? self::tiendasActivas() : array((int) $idShop);

        $yaEstaba = !self::estaSuscrito($email, $idsShop);

        if (Configuration::getGlobalValue('ECOM_MBC_UNSUB_CUSTOMER')) {
            $resultado['clientes'] = self::bajaClientes($email, $idsShop);
        }
        if (Configuration::getGlobalValue('ECOM_MBC_UNSUB_SUBSCRIPTION')) {
            $resultado['suscripciones'] = self::bajaSuscripciones($email, $idsShop);
        }

        $resultado['ok'] = true;
        $resultado['ya_estaba'] = $yaEstaba;

        self::registrar($email, (int) $idShop, $metodo, $origen, $resultado);

        Ecom_MailbajaclickLog::add('Baja ejecutada', 'baja', array(
            'email' => $email,
            'id_shop' => (int) $idShop,
            'metodo' => $metodo,
            'origen' => $origen,
            'clientes' => $resultado['clientes'],
            'suscripciones' => $resultado['suscripciones'],
            'ya_estaba' => $yaEstaba,
        ));

        try {
            Hook::exec('actionEcomMailbajaclickUnsubscribe', array(
                'email' => $email,
                'id_shop' => (int) $idShop,
                'metodo' => $metodo,
                'origen' => $origen,
                'resultado' => $resultado,
            ));
        } catch (Exception $e) {
            Ecom_MailbajaclickLog::add('Fallo al lanzar el hook de baja: ' . $e->getMessage(), 'baja');
        }

        self::$procesadas[$llave] = $resultado;

        return $resultado;
    }

    /**
     * Vuelve a suscribir un correo (se usa desde el back-office).
     *
     * @param string $email
     * @param int    $idShop
     *
     * @return bool
     */
    public static function reactivar($email, $idShop = 0)
    {
        $email = trim((string) $email);
        if (!Validate::isEmail($email)) {
            return false;
        }
        if (!$idShop) {
            $idShop = (int) Context::getContext()->shop->id;
        }

        $idsShop = Configuration::getGlobalValue('ECOM_MBC_ALL_SHOPS') ? self::tiendasActivas() : array((int) $idShop);
        $filtro = self::filtroTiendas($idsShop, 'id_shop');

        try {
            Db::getInstance()->execute(
                'UPDATE `' . _DB_PREFIX_ . 'customer` SET `newsletter` = 1
                 WHERE `email` = \'' . pSQL($email) . '\' AND `deleted` = 0' . $filtro
            );
            if (self::existeTabla(_DB_PREFIX_ . 'emailsubscription')) {
                Db::getInstance()->execute(
                    'UPDATE `' . _DB_PREFIX_ . 'emailsubscription` SET `active` = 1
                     WHERE `email` = \'' . pSQL($email) . '\'' . $filtro
                );
            }
        } catch (Exception $e) {
            Ecom_MailbajaclickLog::add('Fallo al reactivar: ' . $e->getMessage(), 'baja');

            return false;
        }

        Ecom_MailbajaclickLog::add('Correo reactivado desde el back-office', 'baja', array('email' => $email));

        return true;
    }

    /**
     * Indica si el correo sigue suscrito en alguna de las tiendas indicadas.
     *
     * @param string $email
     * @param array  $idsShop
     *
     * @return bool
     */
    public static function estaSuscrito($email, array $idsShop)
    {
        $filtro = self::filtroTiendas($idsShop, 'id_shop');

        try {
            $clientes = (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'customer`
                 WHERE `email` = \'' . pSQL($email) . '\' AND `newsletter` = 1 AND `deleted` = 0' . $filtro
            );
            if ($clientes > 0) {
                return true;
            }

            if (self::existeTabla(_DB_PREFIX_ . 'emailsubscription')) {
                $suscritos = (int) Db::getInstance()->getValue(
                    'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'emailsubscription`
                     WHERE `email` = \'' . pSQL($email) . '\' AND `active` = 1' . $filtro
                );
                if ($suscritos > 0) {
                    return true;
                }
            }
        } catch (Exception $e) {
            Ecom_MailbajaclickLog::add('Fallo al comprobar la suscripcion: ' . $e->getMessage(), 'baja');
        }

        return false;
    }

    /**
     * Desactiva la casilla de boletin en las fichas de cliente.
     *
     * @param string $email
     * @param array  $idsShop
     *
     * @return int Filas afectadas
     */
    protected static function bajaClientes($email, array $idsShop)
    {
        $filtro = self::filtroTiendas($idsShop, 'id_shop');

        try {
            Db::getInstance()->execute(
                'UPDATE `' . _DB_PREFIX_ . 'customer` SET `newsletter` = 0
                 WHERE `email` = \'' . pSQL($email) . '\' AND `newsletter` = 1 AND `deleted` = 0' . $filtro
            );

            return (int) Db::getInstance()->Affected_Rows();
        } catch (Exception $e) {
            Ecom_MailbajaclickLog::add('Fallo al dar de baja al cliente: ' . $e->getMessage(), 'baja');
        }

        return 0;
    }

    /**
     * Desactiva la suscripcion en la tabla del modulo nativo de boletin.
     *
     * @param string $email
     * @param array  $idsShop
     *
     * @return int Filas afectadas
     */
    protected static function bajaSuscripciones($email, array $idsShop)
    {
        if (!self::existeTabla(_DB_PREFIX_ . 'emailsubscription')) {
            return 0;
        }

        $filtro = self::filtroTiendas($idsShop, 'id_shop');

        try {
            Db::getInstance()->execute(
                'UPDATE `' . _DB_PREFIX_ . 'emailsubscription` SET `active` = 0
                 WHERE `email` = \'' . pSQL($email) . '\' AND `active` = 1' . $filtro
            );

            return (int) Db::getInstance()->Affected_Rows();
        } catch (Exception $e) {
            Ecom_MailbajaclickLog::add('Fallo al dar de baja la suscripcion: ' . $e->getMessage(), 'baja');
        }

        return 0;
    }

    /**
     * Guarda la baja en la tabla del modulo.
     *
     * @param string $email
     * @param int    $idShop
     * @param string $metodo
     * @param string $origen
     * @param array  $resultado
     *
     * @return void
     */
    protected static function registrar($email, $idShop, $metodo, $origen, array $resultado)
    {
        try {
            Db::getInstance()->insert(self::TABLA, array(
                'email' => pSQL($email),
                'id_shop' => (int) $idShop,
                'metodo' => pSQL(Tools::substr((string) $metodo, 0, 20)),
                'origen' => pSQL(Tools::substr((string) $origen, 0, 64)),
                'ip' => pSQL(Tools::substr((string) self::ip(), 0, 46)),
                'agente' => pSQL(Tools::substr((string) (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''), 0, 255)),
                'resultado' => pSQL('clientes=' . (int) $resultado['clientes'] . ';suscripciones=' . (int) $resultado['suscripciones'] . ';ya_estaba=' . ($resultado['ya_estaba'] ? '1' : '0')),
                'date_add' => date('Y-m-d H:i:s'),
            ), false, true, Db::INSERT_IGNORE);
        } catch (Exception $e) {
            Ecom_MailbajaclickLog::add('Fallo al registrar la baja: ' . $e->getMessage(), 'baja');
        }
    }

    /**
     * Ultimas bajas registradas.
     *
     * @param int    $limite
     * @param int    $desde
     * @param string $buscar
     *
     * @return array
     */
    public static function listado($limite = 50, $desde = 0, $buscar = '')
    {
        $where = '';
        if ($buscar !== '') {
            $where = ' WHERE `email` LIKE \'%' . pSQL($buscar) . '%\'';
        }

        try {
            $filas = Db::getInstance()->executeS(
                'SELECT * FROM `' . _DB_PREFIX_ . self::TABLA . '`' . $where . '
                 ORDER BY `id_baja` DESC LIMIT ' . (int) $desde . ', ' . (int) $limite
            );
        } catch (Exception $e) {
            Ecom_MailbajaclickLog::add('Fallo al leer el listado: ' . $e->getMessage(), 'baja');

            return array();
        }

        return is_array($filas) ? $filas : array();
    }

    /**
     * Numero total de bajas registradas.
     *
     * @param string $buscar
     *
     * @return int
     */
    public static function total($buscar = '')
    {
        $where = '';
        if ($buscar !== '') {
            $where = ' WHERE `email` LIKE \'%' . pSQL($buscar) . '%\'';
        }

        try {
            return (int) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . self::TABLA . '`' . $where
            );
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Identificadores de las tiendas activas.
     *
     * @return array
     */
    public static function tiendasActivas()
    {
        $ids = array();
        try {
            $tiendas = Shop::getShops(true, null, true);
            foreach (is_array($tiendas) ? $tiendas : array() as $id) {
                $ids[] = (int) $id;
            }
        } catch (Exception $e) {
            $ids = array();
        }

        if (empty($ids)) {
            $ids = array((int) Context::getContext()->shop->id);
        }

        return $ids;
    }

    /**
     * Trozo de SQL que limita por tienda, o cadena vacia si no hay que limitar.
     *
     * @param array  $idsShop
     * @param string $columna
     *
     * @return string
     */
    protected static function filtroTiendas(array $idsShop, $columna)
    {
        $limpias = array();
        foreach ($idsShop as $id) {
            if ((int) $id > 0) {
                $limpias[] = (int) $id;
            }
        }

        if (empty($limpias)) {
            return '';
        }

        return ' AND `' . bqSQL($columna) . '` IN (' . implode(',', $limpias) . ')';
    }

    /**
     * Comprueba que una tabla existe.
     *
     * @param string $tabla Nombre completo con prefijo
     *
     * @return bool
     */
    public static function existeTabla($tabla)
    {
        static $cache = array();

        if (isset($cache[$tabla])) {
            return $cache[$tabla];
        }

        try {
            $existe = (bool) Db::getInstance()->getValue(
                'SELECT COUNT(*) FROM `information_schema`.`TABLES`
                 WHERE `TABLE_SCHEMA` = \'' . pSQL(_DB_NAME_) . '\'
                 AND `TABLE_NAME` = \'' . pSQL($tabla) . '\''
            );
        } catch (Exception $e) {
            $existe = false;
        }

        $cache[$tabla] = $existe;

        return $existe;
    }

    /**
     * Direccion IP de quien hace la peticion.
     *
     * Solo se hace caso a las cabeceras de proxy si el comercio lo ha
     * confirmado en la configuracion: cualquiera puede falsificarlas.
     *
     * @return string
     */
    public static function ip()
    {
        if (Configuration::getGlobalValue('ECOM_MBC_TRUST_PROXY')) {
            foreach (array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP') as $cabecera) {
                if (!empty($_SERVER[$cabecera])) {
                    $partes = explode(',', (string) $_SERVER[$cabecera]);

                    return trim($partes[0]);
                }
            }
        }

        return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    }
}
