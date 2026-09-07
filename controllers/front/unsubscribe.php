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
 *  - GET con el codigo firmado: es la persona pulsando el enlace del pie. Se
 *    da de baja y se le ensena la pagina de confirmacion.
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
        );

        $token = (string) Tools::getValue('u');

        if (Tools::isSubmit('ecom_mbc_manual')) {
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

        return (string) Tools::getValue('u') !== '';
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

        if ($datos === false) {
            Ecom_MailbajaclickLog::add('Código no válido en la baja en un clic', 'oneclick', array(
                'ip' => Ecom_MailbajaclickBaja::ip(),
            ));
            $this->responderTexto('Invalid or expired unsubscribe code.', 400);
        }

        Ecom_MailbajaclickBaja::ejecutar(
            $datos['email'],
            (int) $datos['id_shop'],
            'oneclick',
            $datos['origen']
        );

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
    }

    /* ------------------------------------------------------------------ */
    /* Baja desde el navegador                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Da de baja a partir del codigo firmado del enlace.
     *
     * @param string $token
     *
     * @return void
     */
    protected function procesarToken($token)
    {
        $datos = Ecom_MailbajaclickToken::leer($token);

        if ($datos === false) {
            Ecom_MailbajaclickLog::add('Código no válido en el enlace', 'enlace');
            $this->estado['error'] = $this->trans(
                'This unsubscribe link is not valid or has expired.',
                array(),
                'Modules.Ecommailbajaclick.Shop'
            );
            $this->prepararFormulario();

            return;
        }

        $resultado = Ecom_MailbajaclickBaja::ejecutar(
            $datos['email'],
            (int) $datos['id_shop'],
            'enlace',
            $datos['origen']
        );

        $this->estado['hecho'] = (bool) $resultado['ok'];
        $this->estado['ya_estaba'] = (bool) $resultado['ya_estaba'];
        $this->estado['email'] = $datos['email'];
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

        $resultado = Ecom_MailbajaclickBaja::ejecutar(
            $email,
            (int) $this->context->shop->id,
            'formulario',
            ''
        );

        $this->estado['hecho'] = (bool) $resultado['ok'];
        $this->estado['ya_estaba'] = (bool) $resultado['ya_estaba'];
        $this->estado['email'] = $email;
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
}
