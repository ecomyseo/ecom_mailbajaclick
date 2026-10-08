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

require_once _PS_MODULE_DIR_ . 'ecom_mailbajaclick/classes/Ecom_MailbajaclickLog.php';
require_once _PS_MODULE_DIR_ . 'ecom_mailbajaclick/classes/Ecom_MailbajaclickToken.php';
require_once _PS_MODULE_DIR_ . 'ecom_mailbajaclick/classes/Ecom_MailbajaclickBaja.php';

/**
 * Atiende la baja del boletin.
 *
 * Tres formas de entrar:
 *  - POST con "List-Unsubscribe=One-Click": es Gmail, Yahoo u Outlook pulsando
 *    el boton de baja. Se procesa y se responde en texto plano, sin pedir
 *    confirmacion (lo exige la RFC 8058) y sin montar la plantilla del tema.
 *  - GET con el codigo firmado: muestra una confirmacion, pero no modifica datos.
 *  - GET sin codigo: formulario para escribir el correo a mano.
 */
class Ecom_MailbajaclickUnsubscribeModuleFrontController extends ModuleFrontController
{
    /** @var bool No hace falta que el visitante este identificado. */
    public $auth = false;

    /** @var bool Se puede ver sin sesion de cliente. */
    public $guestAllowed = true;

    /** @var bool El controlador no forma parte del proceso de compra. */
    public $ssl = true;

    /** @var array Resultado de la baja para la plantilla. */
    protected $estado = array();

    /**
     * Intercepta las peticiones que no deben pasar por el ciclo normal del
     * front (la comprobacion de ping y la baja en un clic).
     *
     * Se hace antes de parent::init() para que ninguna redireccion canonica,
     * ningun modo mantenimiento y ninguna cookie se interpongan: el buzon que
     * hace el POST no sigue redirecciones ni guarda cookies.
     *
     * @return void
     */
    public function init()
    {
        if (Tools::getValue('ping')) {
            $this->responderPing();
        }

        if ($this->esOneClick()) {
            $this->procesarOneClick();
        }
        if (isset($_SERVER['REQUEST_METHOD'])
            && Tools::strtoupper($_SERVER['REQUEST_METHOD']) === 'POST'
            && (string) Tools::getValue('u') !== ''
            && !Tools::isSubmit('ecom_mbc_manual')
            && !Tools::isSubmit('ecom_mbc_confirmar')) {
            $this->responderTexto('Invalid one-click unsubscribe request.', 400);
        }

        $this->ssl = (bool) Configuration::get('PS_SSL_ENABLED');

        parent::init();
    }

    /**
     * Hoja de estilo de la pagina de baja.
     *
     * @return void
     */
    public function setMedia()
    {
        parent::setMedia();

        $this->registerStylesheet(
            'ecom-mailbajaclick-front',
            'modules/' . $this->module->name . '/views/css/front.css',
            array('media' => 'all', 'priority' => 200)
        );
    }

    /**
     * Contenido de la pagina para las visitas normales.
     *
     * @return void
     */
    public function initContent()
    {
        parent::initContent();

        $this->estado = array(
            'hecho' => false,
            'ya_estaba' => false,
            'error' => '',
            'email' => '',
            'formulario' => false,
            'confirmacion' => false,
            'token' => '',
            'csrf' => '',
            'solicitud_enviada' => false,
        );

        $token = (string) Tools::getValue('u');

        if (Tools::isSubmit('ecom_mbc_confirmar')) {
            $this->procesarConfirmacion($token);
        } elseif (Tools::isSubmit('ecom_mbc_manual')) {
            $this->procesarFormulario();
        } elseif ($token !== '') {
            $this->procesarToken($token);
        } else {
            $this->prepararFormulario();
        }

        if ($this->estado['hecho'] && !$this->estado['formulario']) {
            $redirect = trim((string) Configuration::getGlobalValue('ECOM_MBC_REDIRECT'));
            if ($redirect !== '' && Validate::isAbsoluteUrl($redirect)) {
                Tools::redirect($redirect);
            }
        }

        $this->context->smarty->assign(array(
            'mbc' => $this->estado,
            'mbc_shop_name' => Configuration::get('PS_SHOP_NAME'),
            'mbc_accion' => $this->urlPropia(),
        ));

        $this->setTemplate('module:ecom_mailbajaclick/views/templates/front/baja.tpl');
    }

    /**
     * Migas de pan de la pagina.
     *
     * @return array
     */
    public function getBreadcrumbLinks()
    {
        $migas = parent::getBreadcrumbLinks();

        $migas['links'][] = array(
            'title' => $this->trans('Unsubscribe', array(), 'Modules.Ecommailbajaclick.Shop'),
            'url' => $this->urlPropia(),
        );

        return $migas;
    }

    /* ------------------------------------------------------------------ */
    /* Baja en un clic                                                     */
    /* ------------------------------------------------------------------ */

    /**
     * Indica si la peticion es la baja en un clic de un buzon.
     *
     * @return bool
     */
    protected function esOneClick()
    {
        if (!isset($_SERVER['REQUEST_METHOD']) || Tools::strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST') {
            return false;
        }

        if (Tools::isSubmit('ecom_mbc_manual')) {
            return false;
        }

        if ((string) Tools::getValue('u') === '') {
            return false;
        }

        $tipo = isset($_SERVER['CONTENT_TYPE']) ? Tools::strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'])[0])) : '';
        if ($tipo !== 'application/x-www-form-urlencoded') {
            return false;
        }

        return isset($_POST['List-Unsubscribe'])
            && is_string($_POST['List-Unsubscribe'])
            && hash_equals('One-Click', $_POST['List-Unsubscribe']);
    }

    /**
     * Procesa la baja en un clic y termina la peticion.
     *
     * @return void
     */
    protected function procesarOneClick()
    {
        $token = (string) Tools::getValue('u');
        $datos = Ecom_MailbajaclickToken::leer($token);

        if ($datos === false || !$this->tokenPerteneceATienda($datos)) {
            Ecom_MailbajaclickLog::add('Código no válido en la baja en un clic', 'oneclick', array(
                'ip' => Ecom_MailbajaclickBaja::ip(),
            ));
            $this->responderTexto('Invalid or expired unsubscribe code.', 400);
        }

        $resultado = Ecom_MailbajaclickBaja::ejecutar(
            $datos['email'],
            (int) $datos['id_shop'],
            'oneclick',
            $datos['origen']
        );

        if (!$resultado['ok']) {
            $this->responderTexto('Unable to process unsubscribe request.', 500);
        }
        $this->responderTexto('Unsubscribed', 200);
    }

    /**
     * Responde a la comprobacion de estado del back-office.
     *
     * @return void
     */
    protected function responderPing()
    {
        $this->cabecerasSinCache();
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode(array(
            'ecom_mailbajaclick' => 'ok',
            'oneclick' => true,
            'time' => date('c'),
        ));
        exit;
    }

    /**
     * Responde en texto plano y termina.
     *
     * @param string $texto
     * @param int    $codigo
     *
     * @return void
     */
    protected function responderTexto($texto, $codigo = 200)
    {
        $this->cabecerasSinCache();
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code((int) $codigo);

        echo $texto;
        exit;
    }

    /**
     * Cabeceras que impiden que la respuesta se quede en cache.
     *
     * @return void
     */
    protected function cabecerasSinCache()
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
    }

    /* ------------------------------------------------------------------ */
    /* Baja desde el navegador                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Comprueba el código y prepara la confirmación. GET nunca modifica datos.
     *
     * @param string $token
     *
     * @return void
     */
    protected function procesarToken($token)
    {
        $datos = Ecom_MailbajaclickToken::leer($token);

        if ($datos === false || !$this->tokenPerteneceATienda($datos)) {
            Ecom_MailbajaclickLog::add('Código no válido en el enlace', 'enlace');
            $this->estado['error'] = $this->trans(
                'This unsubscribe link is not valid or has expired.',
                array(),
                'Modules.Ecommailbajaclick.Shop'
            );
            $this->prepararFormulario();

            return;
        }

        $this->estado['confirmacion'] = true;
        $this->estado['token'] = $token;
        $this->estado['csrf'] = Ecom_MailbajaclickToken::csrf($token);
        $this->estado['email'] = $this->ocultarEmail($datos['email']);
    }

    protected function procesarConfirmacion($token)
    {
        $datos = Ecom_MailbajaclickToken::leer($token);
        $csrf = (string) Tools::getValue('ecom_mbc_csrf');
        if ($datos === false || !$this->tokenPerteneceATienda($datos) || !Ecom_MailbajaclickToken::validarCsrf($token, $csrf)) {
            $this->estado['error'] = $this->trans('This unsubscribe link is not valid or has expired.', array(), 'Modules.Ecommailbajaclick.Shop');
            return;
        }

        $resultado = Ecom_MailbajaclickBaja::ejecutar($datos['email'], (int) $datos['id_shop'], 'confirmacion', $datos['origen']);
        $this->estado['hecho'] = (bool) $resultado['ok'];
        $this->estado['ya_estaba'] = (bool) $resultado['ya_estaba'];
        $this->estado['email'] = $this->ocultarEmail($datos['email']);
        if (!$resultado['ok']) {
            $this->estado['error'] = $this->trans('We could not process your request. Please try again later.', array(), 'Modules.Ecommailbajaclick.Shop');
        }
    }

    /**
     * Da de baja a partir del correo escrito en el formulario.
     *
     * @return void
     */
    protected function procesarFormulario()
    {
        if (!Configuration::getGlobalValue('ECOM_MBC_FORM')) {
            $this->estado['error'] = $this->trans(
                'This unsubscribe link is not valid or has expired.',
                array(),
                'Modules.Ecommailbajaclick.Shop'
            );

            return;
        }

        // Campo trampa: si viene relleno es un robot.
        if (trim((string) Tools::getValue('ecom_mbc_web')) !== '') {
            Ecom_MailbajaclickLog::add('Formulario descartado por el campo trampa', 'formulario');
            $this->estado['hecho'] = true;
            $this->estado['email'] = '';

            return;
        }

        $email = trim((string) Tools::getValue('ecom_mbc_correo'));
        if (!Validate::isEmail($email)) {
            $this->estado['error'] = $this->trans(
                'Please write a valid email address.',
                array(),
                'Modules.Ecommailbajaclick.Shop'
            );
            $this->prepararFormulario();

            return;
        }

        if (!Ecom_MailbajaclickBaja::permitirSolicitud($email, Ecom_MailbajaclickBaja::ip())) {
            $this->estado['solicitud_enviada'] = true;
            return;
        }

        $idShop = (int) $this->context->shop->id;
        if (Ecom_MailbajaclickBaja::estaSuscrito($email, array($idShop))) {
            $token = Ecom_MailbajaclickToken::crear($email, $idShop, 'formulario');
            $url = $this->context->link->getModuleLink('ecom_mailbajaclick', 'unsubscribe', array('u' => $token), true);
            Mail::Send(
                (int) $this->context->language->id,
                'ecom_mbc_confirmacion',
                $this->trans('Confirm your unsubscribe request', array(), 'Modules.Ecommailbajaclick.Shop'),
                array('{confirmation_url}' => $url, '{shop_name}' => Configuration::get('PS_SHOP_NAME')),
                $email,
                null,
                null,
                null,
                null,
                null,
                _PS_MODULE_DIR_ . $this->module->name . '/mails/',
                false,
                $idShop
            );
        }
        Ecom_MailbajaclickBaja::registrarSolicitud($email, Ecom_MailbajaclickBaja::ip(), $idShop);
        $this->estado['solicitud_enviada'] = true;
    }

    /**
     * Prepara la pantalla del formulario manual.
     *
     * @return void
     */
    protected function prepararFormulario()
    {
        $this->estado['formulario'] = (bool) Configuration::getGlobalValue('ECOM_MBC_FORM');

        if (!$this->estado['formulario'] && $this->estado['error'] === '') {
            $this->estado['error'] = $this->trans(
                'This unsubscribe link is not valid or has expired.',
                array(),
                'Modules.Ecommailbajaclick.Shop'
            );
        }
    }

    /**
     * URL de esta misma pagina, sin parametros.
     *
     * @return string
     */
    protected function urlPropia()
    {
        return $this->context->link->getModuleLink('ecom_mailbajaclick', 'unsubscribe', array(), true);
    }

    protected function ocultarEmail($email)
    {
        $partes = explode('@', (string) $email, 2);
        if (count($partes) !== 2) {
            return '';
        }
        return Tools::substr($partes[0], 0, 1) . '***@' . $partes[1];
    }

    protected function tokenPerteneceATienda(array $datos)
    {
        return isset($this->context->shop)
            && (int) $this->context->shop->id === (int) $datos['id_shop'];
    }
}
