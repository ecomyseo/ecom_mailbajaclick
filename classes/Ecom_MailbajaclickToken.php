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
 * Firma y verificacion del identificador de baja que viaja en la URL.
 *
 * El identificador es autocontenido: lleva dentro el correo, la tienda y la
 * fecha de emision, todo firmado con HMAC-SHA256 y una clave secreta guardada
 * en la configuracion. Asi no hay que guardar nada en la base de datos para
 * cada correo enviado y el enlace sigue siendo valido meses despues.
 */
class Ecom_MailbajaclickToken
{
    /** Nombre de la opcion donde vive la clave secreta. */
    const CLAVE = 'ECOM_MBC_SECRET';

    /**
     * Devuelve la clave secreta, generandola la primera vez.
     *
     * @return string
     */
    public static function secreto()
    {
        $secreto = Configuration::getGlobalValue(self::CLAVE);
        if (!is_string($secreto) || Tools::strlen($secreto) < 32) {
            $secreto = self::generarSecreto();
            Configuration::updateGlobalValue(self::CLAVE, $secreto);
        }

        return $secreto;
    }

    /**
     * Genera una clave secreta nueva.
     *
     * @return string
     */
    public static function generarSecreto()
    {
        if (function_exists('random_bytes')) {
            try {
                return bin2hex(\random_bytes(32));
            } catch (Exception $e) {
                // Se cae al metodo de respaldo.
            }
        }

        return hash('sha256', uniqid('ecom_mbc', true) . microtime(true) . mt_rand());
    }

    /**
     * Base64 apto para URL (sin +, / ni =).
     *
     * @param string $datos
     *
     * @return string
     */
    protected static function b64($datos)
    {
        return rtrim(strtr(base64_encode($datos), '+/', '-_'), '=');
    }

    /**
     * Deshace el base64 apto para URL.
     *
     * @param string $datos
     *
     * @return string|false
     */
    protected static function unb64($datos)
    {
        $datos = strtr($datos, '-_', '+/');
        $resto = Tools::strlen($datos) % 4;
        if ($resto) {
            $datos .= str_repeat('=', 4 - $resto);
        }

        return base64_decode($datos);
    }

    /**
     * Crea el identificador firmado para un correo.
     *
     * @param string $email
     * @param int    $idShop
     * @param string $origen Plantilla o modulo que envia el correo
     *
     * @return string
     */
    public static function crear($email, $idShop = 0, $origen = '')
    {
        $datos = array(
            'e' => (string) $email,
            's' => (int) $idShop,
            't' => time(),
        );
        if ($origen !== '') {
            $datos['o'] = Tools::substr((string) $origen, 0, 64);
        }

        $json = json_encode($datos);
        if (!is_string($json)) {
            return '';
        }

        $carga = self::b64($json);
        $firma = Tools::substr(hash_hmac('sha256', $carga, self::secreto()), 0, 32);

        return $carga . '.' . $firma;
    }

    /**
     * Comprueba el identificador y devuelve sus datos.
     *
     * @param string $token
     *
     * @return array|false array('email'=>..,'id_shop'=>..,'fecha'=>..,'origen'=>..) o false
     */
    public static function leer($token)
    {
        if (!is_string($token) || Tools::strlen($token) < 10 || strpos($token, '.') === false) {
            return false;
        }

        $partes = explode('.', $token);
        if (count($partes) !== 2) {
            return false;
        }

        list($carga, $firma) = $partes;
        $esperada = Tools::substr(hash_hmac('sha256', $carga, self::secreto()), 0, 32);

        if (!hash_equals($esperada, (string) $firma)) {
            return false;
        }

        $json = self::unb64($carga);
        if (!is_string($json) || $json === '') {
            return false;
        }

        $datos = json_decode($json, true);
        if (!is_array($datos) || empty($datos['e']) || !Validate::isEmail($datos['e'])) {
            return false;
        }

        $dias = (int) Configuration::getGlobalValue('ECOM_MBC_EXPIRE_DAYS');
        if ($dias > 0 && isset($datos['t']) && (time() - (int) $datos['t']) > ($dias * 86400)) {
            return false;
        }

        return array(
            'email' => (string) $datos['e'],
            'id_shop' => isset($datos['s']) ? (int) $datos['s'] : 0,
            'fecha' => isset($datos['t']) ? (int) $datos['t'] : 0,
            'origen' => isset($datos['o']) ? (string) $datos['o'] : '',
        );
    }
}
