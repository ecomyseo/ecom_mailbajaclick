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
 * Anade a los correos las cabeceras List-Unsubscribe y List-Unsubscribe-Post
 * que exigen Gmail, Yahoo y Outlook al correo comercial (RFC 8058), y atiende
 * la baja en un clic desde un controlador propio del front.
 */
class Ecom_Mailbajaclick extends Module
{
    /** Nombre del controlador del front que atiende la baja. */
    const CONTROLADOR = 'unsubscribe';

    /** Modo de filtrado: solo las plantillas indicadas. */
    const MODO_INCLUIR = 1;

    /** Modo de filtrado: todas menos las indicadas. */
    const MODO_EXCLUIR = 2;

    /** Modo de filtrado: todas las plantillas. */
    const MODO_TODAS = 3;

    /**
     * Datos del correo que se esta enviando ahora mismo.
     *
     * Se rellenan en actionEmailSendBefore (el unico hook que recibe la
     * plantilla y el destinatario) y se consumen en
     * actionMailAlterMessageBeforeSend y actionEmailAddAfterContent.
     *
     * @var array
     */
    protected static $correoActual = array();

    /** @var array Opciones del modulo con su valor por defecto. */
    protected static $opciones = array(
        'ECOM_MBC_ACTIVE' => 1,
        'ECOM_MBC_MODE' => self::MODO_INCLUIR,
        'ECOM_MBC_TPL_INCLUDE' => "newsletter\nnewsletter_conf\nnewsletter_verif\n*newsletter*\n*boletin*\n*promo*\n*campaign*\n*mailing*\n*marketing*",
        'ECOM_MBC_TPL_EXCLUDE' => "order_conf\norder_changed\norder_canceled\norder_return_state\nnew_order\npayment\npayment_error\nbankwire\ncheque\ncredit_slip\ndownload_product\nshipped\npreparation\nrefund\naccount\npassword\npassword_query\nguest_to_customer\ncontact\ncontact_form\nreply_msg\ntest\nemployee_password\nbackoffice_order",
        'ECOM_MBC_ADD_MAILTO' => 0,
        'ECOM_MBC_MAILTO' => '',
        'ECOM_MBC_FOOTER' => 1,
        'ECOM_MBC_FORCE_HTTPS' => 1,
        'ECOM_MBC_EXPIRE_DAYS' => 0,
        'ECOM_MBC_UNSUB_CUSTOMER' => 1,
        'ECOM_MBC_UNSUB_SUBSCRIPTION' => 1,
        'ECOM_MBC_ALL_SHOPS' => 0,
        'ECOM_MBC_FORM' => 1,
        'ECOM_MBC_REDIRECT' => '',
        'ECOM_MBC_ROUTE' => 'baja-boletin',
        'ECOM_MBC_PRETTY' => 1,
        'ECOM_MBC_TRUST_PROXY' => 0,
        'ECOM_MBC_DEBUG' => 0,
        'ECOM_MBC_UPDATE_CHECK' => 0,
    );

    /**
     * Constructor del modulo.
     */
    public function __construct()
    {
        $this->name = 'ecom_mailbajaclick';
        $this->tab = 'emailing';
        $this->version = '1.2.1';
        $this->author = 'Ecom Experts';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->module_key = '';

        $this->ps_versions_compliancy = array('min' => '1.7.8.0', 'max' => '9.2.99');

        parent::__construct();

        $this->displayName = $this->trans('Unsubscribe in one click', array(), 'Modules.Ecommailbajaclick.Admin');
        $this->description = $this->trans(
            'Adds the List-Unsubscribe and List-Unsubscribe-Post headers that Gmail, Yahoo and Outlook require on commercial email, and handles the one-click unsubscribe.',
            array(),
            'Modules.Ecommailbajaclick.Admin'
        );
        $this->confirmUninstall = $this->trans(
            'Are you sure? The unsubscribe log will be deleted and your emails will stop carrying the unsubscribe headers.',
            array(),
            'Modules.Ecommailbajaclick.Admin'
        );
    }

    /**
     * Amplia la visibilidad de trans() para poder llamarlo desde los
     * controladores y las clases del modulo.
     *
     * Module::trans() es protected; llamarlo desde fuera provoca un fatal y
     * deja la pantalla en blanco con un 500.
     *
     * @param string      $id
     * @param array       $parameters
     * @param string|null $domain
     * @param string|null $locale
     *
     * @return string
     */
    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        return parent::trans($id, $parameters, $domain, $locale);
    }

    /**
     * El modulo usa el sistema nuevo de traducciones (ficheros .xlf).
     *
     * @return bool
     */
    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    /**
     * Instalacion.
     *
     * @return bool
     */
    public function install()
    {
        if (Configuration::get('PS_DISABLE_MODULE_OVERRIDES')) {
            $this->_errors[] = $this->trans(
                'Overrides must be enabled because the PrestaShop test email bypasses all mail hooks.',
                array(),
                'Modules.Ecommailbajaclick.Admin'
            );
            return false;
        }
        if (!parent::install()) {
            return false;
        }

        if (!$this->instalarTabla()) {
            return false;
        }

        foreach (self::$opciones as $clave => $valor) {
            if (Configuration::getGlobalValue($clave) === false) {
                Configuration::updateGlobalValue($clave, $valor);
            }
        }

        Ecom_MailbajaclickToken::secreto();
        $this->guardarTextoPie($this->textoPiePorDefecto());
        $this->sincronizarPlantillasDetectadas();

        foreach ($this->hooksNecesarios() as $hook) {
            $this->registerHook($hook);
        }

        Configuration::updateValue('GMARTOS_LAST_PING_' . $this->name, 0);
        if (Configuration::getGlobalValue('ECOM_MBC_UPDATE_CHECK')) {
            $this->checkGmartosUpdate();
        }

        return true;
    }

    /**
     * Registra la instalacion en modules.gmartos.es y avisa si hay una version
     * mas nueva.
     *
     * Un solo aviso por semana, con un tope de 3 segundos: si el servidor no
     * contesta, se abandona en silencio y la pantalla del modulo no se
     * ralentiza. Se hace con cURL nativo, sin Guzzle ni ninguna otra
     * dependencia de Composer.
     *
     * @return bool true si hay una version mas nueva
     */
    protected function checkGmartosUpdate()
    {
        $ultimo = (int) Configuration::get('GMARTOS_LAST_PING_' . $this->name);
        if ((time() - $ultimo) < 604800) {
            return (bool) Configuration::get('GMARTOS_UPDATE_AVAILABLE_' . $this->name);
        }

        if (!function_exists('curl_init')) {
            return false;
        }

        $datos = array(
            'action' => 'register',
            'module_name' => $this->name,
            'version' => $this->version,
            'url' => Tools::getShopDomainSsl(true),
            'email' => Configuration::get('PS_SHOP_EMAIL'),
        );

        try {
            $ch = \curl_init();
            \curl_setopt($ch, CURLOPT_URL, 'https://modules.gmartos.es/api.php');
            \curl_setopt($ch, CURLOPT_POST, true);
            \curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($datos));
            \curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
            \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            \curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            \curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            \curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $cuerpo = \curl_exec($ch);
            $codigo = (int) \curl_getinfo($ch, CURLINFO_HTTP_CODE);
            \curl_close($ch);
        } catch (Exception $e) {
            return false;
        }

        if ($codigo !== 200 || !is_string($cuerpo) || $cuerpo === '') {
            return false;
        }

        $json = json_decode($cuerpo, true);
        if (!is_array($json) || empty($json['success'])) {
            return false;
        }

        Configuration::updateValue('GMARTOS_LAST_PING_' . $this->name, time());

        if (!empty($json['update_available'])) {
            Configuration::updateValue('GMARTOS_UPDATE_AVAILABLE_' . $this->name, true);
            Configuration::updateValue(
                'GMARTOS_LATEST_VERSION_' . $this->name,
                isset($json['latest_version']) ? (string) $json['latest_version'] : ''
            );

            return true;
        }

        Configuration::updateValue('GMARTOS_UPDATE_AVAILABLE_' . $this->name, false);

        return false;
    }

    /**
     * Desinstalacion.
     *
     * @return bool
     */
    public function uninstall()
    {
        // Las tablas de supresión se conservan para no volver a escribir a personas dadas de baja.
        foreach (array_keys(self::$opciones) as $clave) {
            Configuration::deleteByName($clave);
        }
        Configuration::deleteByName('ECOM_MBC_SECRET');
        Configuration::deleteByName('ECOM_MBC_FOOTER_TEXT');
        Configuration::deleteByName('GMARTOS_LAST_PING_' . $this->name);
        Configuration::deleteByName('GMARTOS_UPDATE_AVAILABLE_' . $this->name);
        Configuration::deleteByName('GMARTOS_LATEST_VERSION_' . $this->name);

        return parent::uninstall();
    }

    /**
     * Hooks que necesita el modulo.
     *
     * @return array
     */
    protected function hooksNecesarios()
    {
        return array(
            'actionEmailSendBefore',
            'actionMailAlterMessageBeforeSend',
            'actionEmailAddAfterContent',
            'moduleRoutes',
        );
    }

    /**
     * Crea la tabla del registro de bajas.
     *
     * @return bool
     */
    protected function instalarTabla()
    {
        $motor = defined('_MYSQL_ENGINE_') ? _MYSQL_ENGINE_ : 'InnoDB';
        $fichero = _PS_MODULE_DIR_ . $this->name . '/sql/install.sql';

        $sql = '';
        if (is_file($fichero)) {
            $sql = (string) Tools::file_get_contents($fichero);
        }

        if (trim($sql) === '') {
            return false;
        }

        $sql = str_replace(array('PREFIX_', 'ENGINE_TYPE'), array(_DB_PREFIX_, $motor), $sql);

        try {
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $sentencia) {
                if (stripos($sentencia, 'CREATE') === false && stripos($sentencia, 'ALTER') === false) {
                    continue;
                }
                if (!Db::getInstance()->execute($sentencia)) {
                    return false;
                }
            }

            return true;
        } catch (Exception $e) {
            Ecom_MailbajaclickLog::add('Fallo al crear la tabla: ' . $e->getMessage(), 'install');

            return false;
        }
    }

    /**
     * Migraciones y altas de hooks que se aplican al entrar en la configuracion.
     *
     * Evita los ficheros de upgrade: al abrir la pantalla del modulo se pone al
     * dia la tabla, las opciones nuevas y los hooks que falten.
     *
     * @return void
     */
    public function addnewfeatures()
    {
        try {
            $this->instalarTabla();

            foreach (self::$opciones as $clave => $valor) {
                if (Configuration::getGlobalValue($clave) === false) {
                    Configuration::updateGlobalValue($clave, $valor);
                }
            }

            if (Configuration::get('ECOM_MBC_FOOTER_TEXT') === false) {
                $this->guardarTextoPie($this->textoPiePorDefecto());
            }

            Ecom_MailbajaclickToken::secreto();
            $this->sincronizarPlantillasDetectadas();

            foreach ($this->hooksNecesarios() as $hook) {
                if (!$this->isRegisteredInHook($hook)) {
                    $this->registerHook($hook);
                    Ecom_MailbajaclickLog::add('Hook registrado a posteriori: ' . $hook, 'addnewfeatures');
                }
            }
        } catch (Exception $e) {
            Ecom_MailbajaclickLog::add('Fallo en addnewfeatures: ' . $e->getMessage(), 'addnewfeatures');
        }
    }

    /* ------------------------------------------------------------------ */
    /* Hooks del correo                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Guarda la plantilla y el destinatario del correo que se va a enviar.
     *
     * Es el unico hook de Mail::send() que recibe esos datos; los siguientes
     * solo ven el objeto del mensaje.
     *
     * @param array $params
     *
     * @return bool Siempre true: devolver false cancelaria el envio
     */
    public function hookActionEmailSendBefore($params)
    {
        self::$correoActual = array(
            'template' => isset($params['template']) ? (string) $params['template'] : '',
            'to' => isset($params['to']) ? $params['to'] : '',
            'bcc' => isset($params['bcc']) ? $params['bcc'] : '',
            'id_shop' => isset($params['idShop']) ? (int) $params['idShop'] : 0,
            'id_lang' => isset($params['idLang']) ? (int) $params['idLang'] : 0,
        );

        $email = $this->destinatarioDelContexto();
        if ($email !== ''
            && $this->plantillaAplica(self::$correoActual['template'])
            && Ecom_MailbajaclickBaja::estaSuprimido($email, $this->idShopActual())) {
            Ecom_MailbajaclickLog::add('Envío comercial bloqueado por la lista de supresión', 'envio', array(
                'plantilla' => self::$correoActual['template'],
            ));
            self::$correoActual = array();
            return false;
        }

        return true;
    }

    /**
     * Anade las cabeceras List-Unsubscribe y List-Unsubscribe-Post.
     *
     * En PrestaShop 8 el mensaje es un Swift_Message y en PrestaShop 9 un
     * Symfony\Component\Mime\Email; los dos exponen
     * getHeaders()->addTextHeader(), asi que el mismo codigo vale para ambos.
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionMailAlterMessageBeforeSend($params)
    {
        if (!Configuration::getGlobalValue('ECOM_MBC_ACTIVE')) {
            return;
        }

        if (!isset($params['message']) || !is_object($params['message'])) {
            return;
        }

        $mensaje = $params['message'];
        $plantilla = isset(self::$correoActual['template']) ? self::$correoActual['template'] : '';

        if (!$this->plantillaAplica($plantilla)) {
            Ecom_MailbajaclickLog::add('Plantilla descartada por el filtro', 'cabecera', array('plantilla' => $plantilla));

            return;
        }

        $email = $this->destinatario($mensaje);
        if ($email === '') {
            Ecom_MailbajaclickLog::add('Sin destinatario utilizable', 'cabecera', array('plantilla' => $plantilla));

            return;
        }

        $idShop = $this->idShopActual();
        $url = $this->urlBaja($email, $idShop, $plantilla);
        if ($url === '') {
            Ecom_MailbajaclickLog::add('No se pudo construir la URL de baja', 'cabecera', array('email' => $email));

            return;
        }

        $valores = array('<' . $url . '>');

        $mailto = trim((string) Configuration::getGlobalValue('ECOM_MBC_MAILTO'));
        if (Configuration::getGlobalValue('ECOM_MBC_ADD_MAILTO') && Validate::isEmail($mailto)) {
            $valores[] = '<mailto:' . $mailto . '?subject=' . rawurlencode('unsubscribe ' . $email) . '>';
        }

        try {
            $cabeceras = $mensaje->getHeaders();
            if (!is_object($cabeceras)) {
                return;
            }

            if ((method_exists($cabeceras, 'has') && ($cabeceras->has('List-Unsubscribe') || $cabeceras->has('List-Unsubscribe-Post')))
                || (method_exists($cabeceras, 'hasHeader') && ($cabeceras->hasHeader('List-Unsubscribe') || $cabeceras->hasHeader('List-Unsubscribe-Post')))) {
                self::$correoActual = array();
                return;
            }

            $cabeceras->addTextHeader('List-Unsubscribe', implode(', ', $valores));

            // List-Unsubscribe-Post solo es valido sobre HTTPS (RFC 8058).
            if (Tools::substr($url, 0, 8) === 'https://') {
                $cabeceras->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            }

            Ecom_MailbajaclickLog::add('Cabeceras añadidas', 'cabecera', array(
                'plantilla' => $plantilla,
                'oneclick' => Tools::substr($url, 0, 8) === 'https://',
            ));
        } catch (Exception $e) {
            Ecom_MailbajaclickLog::add('Fallo al añadir las cabeceras: ' . $e->getMessage(), 'cabecera');
        }
        self::$correoActual = array();
    }

    /**
     * Anade al pie del correo un enlace de baja visible.
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionEmailAddAfterContent($params)
    {
        if (!Configuration::getGlobalValue('ECOM_MBC_ACTIVE') || !Configuration::getGlobalValue('ECOM_MBC_FOOTER')) {
            return;
        }

        $plantilla = isset($params['template']) ? (string) $params['template'] : '';
        if (!$this->plantillaAplica($plantilla)) {
            return;
        }

        $email = $this->destinatarioDelContexto();
        if ($email === '') {
            return;
        }

        $idLang = isset($params['id_lang']) ? (int) $params['id_lang'] : (int) Context::getContext()->language->id;
        $url = $this->urlBaja($email, $this->idShopActual(), $plantilla);
        if ($url === '') {
            return;
        }

        $texto = $this->textoPie($idLang);
        $etiqueta = $this->trans('unsubscribe here', array(), 'Modules.Ecommailbajaclick.Shop', $this->localeDe($idLang));

        $enlace = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" style="color:#777;text-decoration:underline;">'
            . htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8') . '</a>';

        $html = '<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:0;padding:0;">'
            . '<tr><td align="center" style="font-family:Arial,Helvetica,sans-serif;font-size:11px;color:#777;padding:14px 10px;line-height:16px;">'
            . str_replace('%s', $enlace, htmlspecialchars($texto, ENT_QUOTES, 'UTF-8'))
            . '</td></tr></table>';

        $txt = str_replace('%s', $url, $texto);

        if (isset($params['template_html'])) {
            $params['template_html'] = $this->insertarAntesDeBody((string) $params['template_html'], $html);
        }
        if (isset($params['template_txt'])) {
            $params['template_txt'] = (string) $params['template_txt'] . "\n\n" . $txt . "\n";
        }
    }

    /**
     * Ruta amigable del controlador de baja.
     *
     * @param array $params
     *
     * @return array
     */
    public function hookModuleRoutes($params)
    {
        if (!Configuration::getGlobalValue('ECOM_MBC_PRETTY')) {
            return array();
        }

        $ruta = trim((string) Configuration::getGlobalValue('ECOM_MBC_ROUTE'));
        $ruta = trim(preg_replace('/[^a-z0-9\-_\/]/i', '', $ruta), '/');
        if ($ruta === '') {
            $ruta = 'baja-boletin';
        }

        return array(
            'module-ecom_mailbajaclick-unsubscribe' => array(
                'controller' => self::CONTROLADOR,
                'rule' => $ruta,
                'keywords' => array(),
                'params' => array(
                    'fc' => 'module',
                    'module' => 'ecom_mailbajaclick',
                    'controller' => self::CONTROLADOR,
                ),
            ),
        );
    }

    /**
     * Hook propio que se dispara al dar de baja un correo.
     *
     * Se declara para que aparezca en la lista de hooks del back-office; el
     * modulo no hace nada con el, lo lanza para que otros lo aprovechen.
     *
     * @param array $params
     *
     * @return void
     */
    public function hookActionEcomMailbajaclickUnsubscribe(array $params)
    {
    }

    /* ------------------------------------------------------------------ */
    /* Utilidades del correo                                               */
    /* ------------------------------------------------------------------ */

    /**
     * Decide si una plantilla debe llevar las cabeceras de baja.
     *
     * @param string $plantilla
     *
     * @return bool
     */
    public function plantillaAplica($plantilla)
    {
        $modo = (int) Configuration::getGlobalValue('ECOM_MBC_MODE');
        $plantilla = Tools::strtolower(trim((string) $plantilla));

        // La prueba de correo de PrestaShop siempre debe llevar las cabeceras.
        if ($plantilla === 'test') {
            return true;
        }

        if ($modo === self::MODO_TODAS) {
            return true;
        }

        if ($modo === self::MODO_EXCLUIR) {
            return !$this->coincide($plantilla, Configuration::getGlobalValue('ECOM_MBC_TPL_EXCLUDE'));
        }

        return $this->coincide($plantilla, Configuration::getGlobalValue('ECOM_MBC_TPL_INCLUDE'));
    }

    public function anadirCabecerasPrueba($mensaje, $email)
    {
        if (!is_object($mensaje) || !Validate::isEmail($email) || !method_exists($mensaje, 'getHeaders')) {
            return false;
        }
        $url = $this->urlBaja($email, (int) Context::getContext()->shop->id, 'test');
        if ($url === '') {
            return false;
        }
        $cabeceras = $mensaje->getHeaders();
        $cabeceras->addTextHeader('List-Unsubscribe', '<' . $url . '>');
        if (Tools::substr($url, 0, 8) === 'https://') {
            $cabeceras->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        }
        return true;
    }

    /**
     * Comprueba una plantilla contra una lista con comodines.
     *
     * @param string $plantilla
     * @param string $lista Una expresion por linea; se admite el comodin *
     *
     * @return bool
     */
    protected function coincide($plantilla, $lista)
    {
        if ($plantilla === '') {
            return false;
        }

        $lineas = preg_split('/[\r\n,;]+/', (string) $lista);
        foreach (is_array($lineas) ? $lineas : array() as $linea) {
            $linea = Tools::strtolower(trim($linea));
            if ($linea === '') {
                continue;
            }

            if (strpos($linea, '*') === false) {
                if ($linea === $plantilla) {
                    return true;
                }
                continue;
            }

            $patron = '/^' . str_replace('\*', '.*', preg_quote($linea, '/')) . '$/';
            if (preg_match($patron, $plantilla)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Saca el destinatario del mensaje o del contexto del envio.
     *
     * @param object $mensaje Swift_Message (PS8) o Symfony Mime\Email (PS9)
     *
     * @return string
     */
    protected function destinatario($mensaje)
    {
        $email = $this->destinatarioDelContexto();
        if ($email === '') {
            return '';
        }

        try {
            if (!method_exists($mensaje, 'getTo')) {
                return '';
            }

            $total = $this->contarDestinatarios($mensaje->getTo());
            if (method_exists($mensaje, 'getCc')) {
                $total += $this->contarDestinatarios($mensaje->getCc());
            }
            if (method_exists($mensaje, 'getBcc')) {
                $total += $this->contarDestinatarios($mensaje->getBcc());
            }
            return $total === 1 ? $email : '';
        } catch (Exception $e) {
            Ecom_MailbajaclickLog::add('Fallo al leer el destinatario: ' . $e->getMessage(), 'cabecera');
        }

        return '';
    }

    protected function contarDestinatarios($destinos)
    {
        return is_array($destinos) ? count($destinos) : ($destinos ? 1 : 0);
    }

    /**
     * Destinatario capturado en actionEmailSendBefore.
     *
     * Solo se devuelve cuando hay UNO: con varios destinatarios la cabecera de
     * baja apuntaria al correo equivocado.
     *
     * @return string
     */
    protected function destinatarioDelContexto()
    {
        if (empty(self::$correoActual['to'])) {
            return '';
        }

        $to = self::$correoActual['to'];

        if (is_array($to)) {
            if (count($to) !== 1) {
                return '';
            }
            $to = reset($to);
        }

        $to = trim((string) $to);

        return Validate::isEmail($to) ? $to : '';
    }

    /**
     * Identificador de la tienda del envio actual.
     *
     * @return int
     */
    protected function idShopActual()
    {
        if (!empty(self::$correoActual['id_shop'])) {
            return (int) self::$correoActual['id_shop'];
        }

        return (int) Context::getContext()->shop->id;
    }

    /**
     * Construye la URL de baja para un correo.
     *
     * @param string $email
     * @param int    $idShop
     * @param string $origen
     *
     * @return string Cadena vacia si no se ha podido construir
     */
    public function urlBaja($email, $idShop = 0, $origen = '')
    {
        $token = Ecom_MailbajaclickToken::crear($email, (int) $idShop, $origen);
        if ($token === '') {
            return '';
        }

        return $this->urlControlador((int) $idShop, array('u' => $token));
    }

    /**
     * URL absoluta del controlador de baja.
     *
     * @param int   $idShop
     * @param array $parametros
     *
     * @return string
     */
    public function urlControlador($idShop = 0, array $parametros = array())
    {
        $https = (bool) Configuration::getGlobalValue('ECOM_MBC_FORCE_HTTPS');
        $url = '';

        try {
            $link = Context::getContext()->link;
            if (is_object($link)) {
                $url = (string) $link->getModuleLink(
                    $this->name,
                    self::CONTROLADOR,
                    $parametros,
                    $https ? true : null,
                    null,
                    $idShop ? (int) $idShop : null
                );
            }
        } catch (Exception $e) {
            $url = '';
        }

        if ($url === '' || strpos($url, 'http') !== 0) {
            $url = $this->urlManual((int) $idShop, $parametros);
        }

        if ($https && Tools::substr($url, 0, 7) === 'http://') {
            $url = 'https://' . Tools::substr($url, 7);
        }

        return $url;
    }

    /**
     * URL del controlador construida a mano.
     *
     * Sirve de respaldo cuando el objeto Link no esta disponible (envios desde
     * una tarea programada o desde la linea de ordenes).
     *
     * @param int   $idShop
     * @param array $parametros
     *
     * @return string
     */
    protected function urlManual($idShop, array $parametros)
    {
        try {
            $shop = $idShop ? new Shop((int) $idShop) : Context::getContext()->shop;
            if (!Validate::isLoadedObject($shop)) {
                $shop = Context::getContext()->shop;
            }

            $dominio = Configuration::getGlobalValue('ECOM_MBC_FORCE_HTTPS') && $shop->domain_ssl
                ? $shop->domain_ssl
                : $shop->domain;

            $base = (Configuration::getGlobalValue('ECOM_MBC_FORCE_HTTPS') ? 'https://' : 'http://')
                . $dominio . $shop->getBaseURI();
        } catch (Exception $e) {
            return '';
        }

        $consulta = array(
            'fc' => 'module',
            'module' => $this->name,
            'controller' => self::CONTROLADOR,
        );
        $consulta = array_merge($consulta, $parametros);

        return $base . 'index.php?' . http_build_query($consulta);
    }

    /**
     * Inserta un bloque HTML justo antes de la etiqueta de cierre del cuerpo.
     *
     * @param string $html
     * @param string $bloque
     *
     * @return string
     */
    protected function insertarAntesDeBody($html, $bloque)
    {
        $posicion = stripos($html, '</body>');
        if ($posicion === false) {
            return $html . $bloque;
        }

        return Tools::substr($html, 0, $posicion) . $bloque . Tools::substr($html, $posicion);
    }

    /* ------------------------------------------------------------------ */
    /* Texto del pie                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Texto por defecto del pie, idioma a idioma.
     *
     * @return array
     */
    protected function textoPiePorDefecto()
    {
        $valores = array();
        foreach (Language::getLanguages(false) as $idioma) {
            $valores[(int) $idioma['id_lang']] = $this->trans(
                'If you no longer want to receive emails like this one, %s.',
                array(),
                'Modules.Ecommailbajaclick.Shop',
                $this->localeDe((int) $idioma['id_lang'])
            );
        }

        return $valores;
    }

    /**
     * Guarda el texto del pie por idioma.
     *
     * @param array $valores
     *
     * @return void
     */
    protected function guardarTextoPie(array $valores)
    {
        Configuration::updateValue('ECOM_MBC_FOOTER_TEXT', $valores, true);
    }

    /**
     * Texto del pie para un idioma.
     *
     * @param int $idLang
     *
     * @return string
     */
    protected function textoPie($idLang)
    {
        $texto = Configuration::get('ECOM_MBC_FOOTER_TEXT', (int) $idLang);
        if (!is_string($texto) || trim($texto) === '') {
            $texto = $this->trans(
                'If you no longer want to receive emails like this one, %s.',
                array(),
                'Modules.Ecommailbajaclick.Shop',
                $this->localeDe((int) $idLang)
            );
        }

        if (strpos($texto, '%s') === false) {
            $texto .= ' %s';
        }

        return $texto;
    }

    /**
     * Codigo de idioma (locale) a partir de su identificador.
     *
     * @param int $idLang
     *
     * @return string|null
     */
    protected function localeDe($idLang)
    {
        if (!$idLang) {
            return null;
        }

        try {
            $idioma = new Language((int) $idLang);
            if (Validate::isLoadedObject($idioma) && !empty($idioma->locale)) {
                return $idioma->locale;
            }
        } catch (Exception $e) {
            return null;
        }

        return null;
    }

    /* ------------------------------------------------------------------ */
    /* Pantalla de configuracion                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Pantalla de configuracion del modulo.
     *
     * @return string
     */
    public function getContent()
    {
        $this->addnewfeatures();

        $salida = '';

        if (Configuration::getGlobalValue('ECOM_MBC_UPDATE_CHECK') && $this->checkGmartosUpdate()) {
            $this->context->smarty->assign(array(
                'gmartos_latest_version' => Configuration::get('GMARTOS_LATEST_VERSION_' . $this->name),
            ));
            $salida .= $this->display(__FILE__, 'views/templates/admin/gmartos_update.tpl');
        }

        $salida .= $this->procesarAcciones();

        $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
        $this->context->controller->addJS($this->_path . 'views/js/admin.js');

        return $salida . $this->renderCabecera() . $this->renderForm() . $this->renderBajas();
    }

    /**
     * Atiende los botones de la pantalla de configuracion.
     *
     * @return string HTML con los avisos de resultado
     */
    protected function procesarAcciones()
    {
        $salida = '';

        if (Tools::isSubmit('submitEcomMbcExport')) {
            $this->exportarCsv();
        }

        if (Tools::isSubmit('submitEcomMbc')) {
            $salida .= $this->guardarConfiguracion();
        }

        if (Tools::isSubmit('submitEcomMbcRegen')) {
            Configuration::updateGlobalValue('ECOM_MBC_SECRET', Ecom_MailbajaclickToken::generarSecreto());
            $salida .= $this->displayWarning($this->trans('New secret key generated. The unsubscribe links sent in previous emails are no longer valid.', array(), 'Modules.Ecommailbajaclick.Admin'));
        }

        if (Tools::isSubmit('submitEcomMbcClearLog')) {
            $borrados = Ecom_MailbajaclickLog::vaciar();
            $salida .= $this->displayConfirmation(sprintf($this->trans('Log files deleted: %d', array(), 'Modules.Ecommailbajaclick.Admin'), $borrados));
        }

        return $salida;
    }

    /**
     * Guarda la configuracion enviada por el formulario.
     *
     * @return string
     */
    protected function guardarConfiguracion()
    {
        $errores = array();

        $mailto = trim((string) Tools::getValue('ECOM_MBC_MAILTO'));
        if ($mailto !== '' && !Validate::isEmail($mailto)) {
            $errores[] = $this->trans('The unsubscribe email address is not valid.', array(), 'Modules.Ecommailbajaclick.Admin');
        }

        $redirect = trim((string) Tools::getValue('ECOM_MBC_REDIRECT'));
        if ($redirect !== '' && !Validate::isAbsoluteUrl($redirect)) {
            $errores[] = $this->trans('The redirect address must be a full URL, starting with http:// or https://.', array(), 'Modules.Ecommailbajaclick.Admin');
        }

        if (!empty($errores)) {
            return $this->displayError(implode('<br>', $errores));
        }

        $booleanos = array(
            'ECOM_MBC_ACTIVE', 'ECOM_MBC_ADD_MAILTO', 'ECOM_MBC_FOOTER', 'ECOM_MBC_FORCE_HTTPS',
            'ECOM_MBC_UNSUB_CUSTOMER', 'ECOM_MBC_UNSUB_SUBSCRIPTION', 'ECOM_MBC_ALL_SHOPS',
            'ECOM_MBC_FORM', 'ECOM_MBC_PRETTY', 'ECOM_MBC_TRUST_PROXY', 'ECOM_MBC_DEBUG',
            'ECOM_MBC_UPDATE_CHECK',
        );
        foreach ($booleanos as $clave) {
            Configuration::updateGlobalValue($clave, (int) Tools::getValue($clave) ? 1 : 0);
        }

        Configuration::updateGlobalValue('ECOM_MBC_MODE', self::MODO_INCLUIR);
        Configuration::updateGlobalValue('ECOM_MBC_EXPIRE_DAYS', max(0, (int) Tools::getValue('ECOM_MBC_EXPIRE_DAYS')));
        Configuration::updateGlobalValue('ECOM_MBC_MAILTO', $mailto);
        Configuration::updateGlobalValue('ECOM_MBC_REDIRECT', $redirect);

        $ruta = trim(preg_replace('/[^a-z0-9\-_\/]/i', '', (string) Tools::getValue('ECOM_MBC_ROUTE')), '/');
        Configuration::updateGlobalValue('ECOM_MBC_ROUTE', $ruta !== '' ? $ruta : 'baja-boletin');

        $detectadas = $this->nombresPlantillasDetectadas();
        $seleccionadas = $this->normalizarListaPlantillas((string) Tools::getValue('ECOM_MBC_TPL_INCLUDE'));
        if (!in_array('test', $seleccionadas, true)) {
            $seleccionadas[] = 'test';
        }
        $permitidas = array_merge($detectadas, array('test'));
        $seleccionadas = array_values(array_intersect($seleccionadas, $permitidas));
        $excluidas = array_values(array_diff($detectadas, $seleccionadas));
        sort($seleccionadas);
        sort($excluidas);
        Configuration::updateGlobalValue('ECOM_MBC_TPL_INCLUDE', implode(PHP_EOL, $seleccionadas));
        Configuration::updateGlobalValue('ECOM_MBC_TPL_EXCLUDE', implode(PHP_EOL, $excluidas));

        $pie = array();
        foreach (Language::getLanguages(false) as $idioma) {
            $id = (int) $idioma['id_lang'];
            $pie[$id] = (string) Tools::getValue('ECOM_MBC_FOOTER_TEXT_' . $id);
        }
        $this->guardarTextoPie($pie);

        Ecom_MailbajaclickLog::setActivo((bool) Configuration::getGlobalValue('ECOM_MBC_DEBUG'));
        Ecom_MailbajaclickLog::add('Configuración guardada', 'admin');

        Tools::clearSmartyCache();

        return $this->displayConfirmation($this->trans('Settings saved.', array(), 'Modules.Ecommailbajaclick.Admin'));
    }

    /**
     * Cabecera de la pantalla: avisos cortos, botones y ayuda plegada.
     *
     * @return string
     */
    protected function renderCabecera()
    {
        $diagnostico = $this->diagnostico();

        $this->context->smarty->assign(array(
            'mbc_diagnostico' => $diagnostico,
            'mbc_url_baja' => $this->urlControlador(0, array('u' => 'EJEMPLO')),
            'mbc_url_prueba' => $this->urlControlador(0, array('ping' => 1)),
            'mbc_token_demo' => Ecom_MailbajaclickToken::crear(
                (string) Configuration::get('PS_SHOP_EMAIL'),
                (int) $this->context->shop->id,
                'prueba'
            ),
            'mbc_multitienda' => Shop::isFeatureActive(),
            'mbc_debug' => (bool) Configuration::getGlobalValue('ECOM_MBC_DEBUG'),
            'mbc_log' => Ecom_MailbajaclickLog::ultimas(120),
            'mbc_form_url' => $this->urlFormularioAdmin(),
            'mbc_modulo_path' => $this->_path,
        ));

        return $this->display(__FILE__, 'views/templates/admin/cabecera.tpl');
    }

    /**
     * Autocomprobacion: interroga al sistema ya montado.
     *
     * Comprueba lo que un repaso del codigo no ve: que los hooks estan
     * enganchados de verdad, que la tabla existe, que la URL de baja responde y
     * que va por HTTPS (sin HTTPS no hay baja en un clic).
     *
     * @return array
     */
    public function diagnostico()
    {
        $filas = array();

        $filas[] = array(
            'clave' => 'activo',
            'texto' => $this->trans('Headers enabled', array(), 'Modules.Ecommailbajaclick.Admin'),
            'ok' => (bool) Configuration::getGlobalValue('ECOM_MBC_ACTIVE'),
            'detalle' => Configuration::getGlobalValue('ECOM_MBC_ACTIVE')
                ? $this->trans('The module is adding the headers.', array(), 'Modules.Ecommailbajaclick.Admin')
                : $this->trans('Turn on "Add the unsubscribe headers" in the General tab.', array(), 'Modules.Ecommailbajaclick.Admin'),
        );

        $faltan = array();
        foreach ($this->hooksNecesarios() as $hook) {
            if (!$this->isRegisteredInHook($hook)) {
                $faltan[] = $hook;
            }
        }
        $filas[] = array(
            'clave' => 'hooks',
            'texto' => $this->trans('Hooks connected', array(), 'Modules.Ecommailbajaclick.Admin'),
            'ok' => empty($faltan),
            'detalle' => empty($faltan)
                ? $this->trans('All the required hooks are registered.', array(), 'Modules.Ecommailbajaclick.Admin')
                : sprintf($this->trans('Missing hooks: %s', array(), 'Modules.Ecommailbajaclick.Admin'), implode(', ', $faltan)),
        );

        $tabla = Ecom_MailbajaclickBaja::existeTabla(_DB_PREFIX_ . Ecom_MailbajaclickBaja::TABLA);
        $filas[] = array(
            'clave' => 'tabla',
            'texto' => $this->trans('Unsubscribe log table', array(), 'Modules.Ecommailbajaclick.Admin'),
            'ok' => $tabla,
            'detalle' => $tabla ? _DB_PREFIX_ . Ecom_MailbajaclickBaja::TABLA : $this->trans('The table is missing.', array(), 'Modules.Ecommailbajaclick.Admin'),
        );

        $secreto = (string) Configuration::getGlobalValue('ECOM_MBC_SECRET');
        $filas[] = array(
            'clave' => 'secreto',
            'texto' => $this->trans('Signing key', array(), 'Modules.Ecommailbajaclick.Admin'),
            'ok' => Tools::strlen($secreto) >= 32,
            'detalle' => Tools::strlen($secreto) >= 32
                ? $this->trans('The unsubscribe links are signed.', array(), 'Modules.Ecommailbajaclick.Admin')
                : $this->trans('No signing key. Save the settings to generate one.', array(), 'Modules.Ecommailbajaclick.Admin'),
        );

        $url = $this->urlControlador(0, array('ping' => 1));
        $https = Tools::substr($url, 0, 8) === 'https://';
        $filas[] = array(
            'clave' => 'https',
            'texto' => $this->trans('Unsubscribe URL over HTTPS', array(), 'Modules.Ecommailbajaclick.Admin'),
            'ok' => $https,
            'detalle' => $https
                ? $url
                : $this->trans('Gmail only accepts one-click unsubscribe over HTTPS. Enable SSL in the shop or turn on "Force HTTPS".', array(), 'Modules.Ecommailbajaclick.Admin'),
        );

        $respuesta = $this->comprobarUrl($url);
        $filas[] = array(
            'clave' => 'alcanzable',
            'texto' => $this->trans('The unsubscribe URL answers', array(), 'Modules.Ecommailbajaclick.Admin'),
            'ok' => $respuesta['ok'],
            'detalle' => $respuesta['detalle'],
        );

        return $filas;
    }

    /**
     * Llama a la URL de baja y comprueba que responde.
     *
     * @param string $url
     *
     * @return array array('ok'=>bool,'detalle'=>string)
     */
    protected function comprobarUrl($url)
    {
        if ($url === '' || !function_exists('curl_init')) {
            return array('ok' => false, 'detalle' => $this->trans('cURL is not available; check the URL by hand.', array(), 'Modules.Ecommailbajaclick.Admin'));
        }

        $ch = \curl_init();
        \curl_setopt($ch, CURLOPT_URL, $url);
        \curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        \curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        \curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        \curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        \curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        \curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        $cuerpo = \curl_exec($ch);
        $codigo = (int) \curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = \curl_error($ch);
        \curl_close($ch);

        if ($cuerpo === false || $error !== '') {
            return array('ok' => false, 'detalle' => sprintf($this->trans('Could not reach the URL: %s', array(), 'Modules.Ecommailbajaclick.Admin'), $error));
        }

        $datos = json_decode((string) $cuerpo, true);
        if ($codigo === 200 && is_array($datos) && isset($datos['ecom_mailbajaclick'])) {
            return array('ok' => true, 'detalle' => sprintf($this->trans('Answers correctly (HTTP %d).', array(), 'Modules.Ecommailbajaclick.Admin'), $codigo));
        }

        return array(
            'ok' => false,
            'detalle' => sprintf($this->trans('Unexpected answer (HTTP %d). Check that the front controller is reachable.', array(), 'Modules.Ecommailbajaclick.Admin'), $codigo),
        );
    }

    /**
     * URL de la pantalla del modulo en el back-office.
     *
     * @return string
     */
    protected function urlFormularioAdmin()
    {
        return $this->context->link->getAdminLink('AdminModules', true)
            . '&configure=' . $this->name
            . '&tab_module=' . $this->tab
            . '&module_name=' . $this->name;
    }

    /**
     * Formulario unico de configuracion, con pestanas.
     *
     * @return string
     */
    protected function renderForm()
    {
        $idiomas = $this->context->controller->getLanguages();
        $idLangDefecto = (int) Configuration::get('PS_LANG_DEFAULT');

        $inputs = array();

        /* ---- Pestana general ---- */
        $inputs[] = array(
            'type' => 'switch',
            'label' => $this->trans('Add the unsubscribe headers', array(), 'Modules.Ecommailbajaclick.Admin'),
            'name' => 'ECOM_MBC_ACTIVE',
            'tab' => 'mbcgeneral',
            'desc' => $this->trans('Master switch of the module.', array(), 'Modules.Ecommailbajaclick.Admin'),
            'is_bool' => true,
            'values' => $this->valoresSwitch('ECOM_MBC_ACTIVE'),
        );
        $inputs[] = array(
            'type' => 'switch',
            'label' => $this->trans('Check for module updates', array(), 'Modules.Ecommailbajaclick.Admin'),
            'name' => 'ECOM_MBC_UPDATE_CHECK',
            'tab' => 'mbcgeneral',
            'desc' => $this->trans('If enabled, the shop domain, contact email and module version are sent weekly to modules.gmartos.es.', array(), 'Modules.Ecommailbajaclick.Admin'),
            'is_bool' => true,
            'values' => $this->valoresSwitch('ECOM_MBC_UPDATE_CHECK'),
        );
        $inputs[] = array(
            'type' => 'switch',
            'label' => $this->trans('Force HTTPS in the unsubscribe link', array(), 'Modules.Ecommailbajaclick.Admin'),
            'name' => 'ECOM_MBC_FORCE_HTTPS',
            'tab' => 'mbcgeneral',
            'desc' => $this->trans('Gmail only accepts one-click unsubscribe over HTTPS.', array(), 'Modules.Ecommailbajaclick.Admin'),
            'is_bool' => true,
            'values' => $this->valoresSwitch('ECOM_MBC_FORCE_HTTPS'),
        );
        $inputs[] = array(
            'type' => 'switch',
            'label' => $this->trans('Also offer unsubscribe by email (mailto)', array(), 'Modules.Ecommailbajaclick.Admin'),
            'name' => 'ECOM_MBC_ADD_MAILTO',
            'tab' => 'mbcgeneral',
            'desc' => $this->trans('Adds a second address to the header for clients that do not support the web link.', array(), 'Modules.Ecommailbajaclick.Admin'),
            'is_bool' => true,
            'values' => $this->valoresSwitch('ECOM_MBC_ADD_MAILTO'),
        );
        $inputs[] = array(
            'type' => 'text',
            'label' => $this->trans('Unsubscribe email address', array(), 'Modules.Ecommailbajaclick.Admin'),
            'name' => 'ECOM_MBC_MAILTO',
            'tab' => 'mbcgeneral',
            'desc' => $this->trans('A mailbox you actually read, for example bajas@yourshop.com.', array(), 'Modules.Ecommailbajaclick.Admin'),
        );
        $inputs[] = array(
            'type' => 'switch',
            'label' => $this->trans('Add a visible unsubscribe link at the bottom of the email', array(), 'Modules.Ecommailbajaclick.Admin'),
            'name' => 'ECOM_MBC_FOOTER',
            'tab' => 'mbcgeneral',
            'desc' => $this->trans('Required by law in commercial email in most countries.', array(), 'Modules.Ecommailbajaclick.Admin'),
            'is_bool' => true,
            'values' => $this->valoresSwitch('ECOM_MBC_FOOTER'),
        );
        $inputs[] = array(
            'type' => 'text',
            'label' => $this->trans('Footer text', array(), 'Modules.Ecommailbajaclick.Admin'),
            'name' => 'ECOM_MBC_FOOTER_TEXT',
            'tab' => 'mbcgeneral',
            'lang' => true,
            'desc' => $this->trans('Use %s where the link should appear.', array(), 'Modules.Ecommailbajaclick.Admin'),
        );

        /* ---- Pestana de plantillas ---- */
        $inputs[] = array(
            'type' => 'hidden',
            'label' => $this->trans('Which emails carry the headers', array(), 'Modules.Ecommailbajaclick.Admin'),
            'name' => 'ECOM_MBC_MODE',
            'value' => self::MODO_INCLUIR,
            'tab' => 'mbcplantillas',
            'desc' => $this->trans('Transactional email (orders, invoices, passwords) must not carry them.', array(), 'Modules.Ecommailbajaclick.Admin'),
            'values' => array(
                array(
                    'id' => 'mbc_modo_incluir',
                    'value' => self::MODO_INCLUIR,
                    'label' => $this->trans('Only the templates listed below', array(), 'Modules.Ecommailbajaclick.Admin'),
                ),
                array(
                    'id' => 'mbc_modo_excluir',
                    'value' => self::MODO_EXCLUIR,
                    'label' => $this->trans('Every email except the templates listed below', array(), 'Modules.Ecommailbajaclick.Admin'),
                ),
                array(
                    'id' => 'mbc_modo_todas',
                    'value' => self::MODO_TODAS,
                    'label' => $this->trans('Every email', array(), 'Modules.Ecommailbajaclick.Admin'),
                ),
            ),
        );
        $inputs[] = array(
            'type' => 'hidden',
            'label' => $this->trans('Templates that DO carry the headers', array(), 'Modules.Ecommailbajaclick.Admin'),
            'name' => 'ECOM_MBC_TPL_INCLUDE',
            'tab' => 'mbcplantillas',
            'rows' => 8,
            'cols' => 60,
            'desc' => $this->trans('One per line. The * wildcard is allowed.', array(), 'Modules.Ecommailbajaclick.Admin'),
        );
        $inputs[] = array(
            'type' => 'hidden',
            'label' => $this->trans('Templates that do NOT carry the headers', array(), 'Modules.Ecommailbajaclick.Admin'),
            'name' => 'ECOM_MBC_TPL_EXCLUDE',
            'tab' => 'mbcplantillas',
            'rows' => 8,
            'cols' => 60,
            'desc' => $this->trans('Used only in the "Every email except" mode.', array(), 'Modules.Ecommailbajaclick.Admin'),
        );
        $inputs[] = array(
            'type' => 'html',
            'name' => 'mbc_plantillas_detectadas',
            'tab' => 'mbcplantillas',
            'html_content' => $this->renderPlantillasDetectadas(),
        );

        /* ---- Pestana de la baja ---- */
        $inputs[] = array(
            'type' => 'switch',
            'label' => $this->trans('Uncheck the newsletter box on the customer record', array(), 'Modules.Ecommailbajaclick.Admin'),
            'name' => 'ECOM_MBC_UNSUB_CUSTOMER',
            'tab' => 'mbcbaja',
            'is_bool' => true,
            'values' => $this->valoresSwitch('ECOM_MBC_UNSUB_CUSTOMER'),
        );
        $inputs[] = array(
            'type' => 'switch',
            'label' => $this->trans('Deactivate the subscription of guests (ps_emailsubscription)', array(), 'Modules.Ecommailbajaclick.Admin'),
            'name' => 'ECOM_MBC_UNSUB_SUBSCRIPTION',
            'tab' => 'mbcbaja',
            'is_bool' => true,
            'values' => $this->valoresSwitch('ECOM_MBC_UNSUB_SUBSCRIPTION'),
        );
        $inputs[] = array(
            'type' => 'switch',
            'label' => $this->trans('Unsubscribe from every shop at once', array(), 'Modules.Ecommailbajaclick.Admin'),
            'name' => 'ECOM_MBC_ALL_SHOPS',
            'tab' => 'mbcbaja',
            'desc' => $this->trans('Only relevant on a multistore installation.', array(), 'Modules.Ecommailbajaclick.Admin'),
            'is_bool' => true,
            'values' => $this->valoresSwitch('ECOM_MBC_ALL_SHOPS'),
        );
        $inputs[] = array(
            'type' => 'switch',
            'label' => $this->trans('Show a manual unsubscribe form', array(), 'Modules.Ecommailbajaclick.Admin'),
            'name' => 'ECOM_MBC_FORM',
            'tab' => 'mbcbaja',
            'desc' => $this->trans('The page asks for the email address when the link carries no valid code.', array(), 'Modules.Ecommailbajaclick.Admin'),
            'is_bool' => true,
            'values' => $this->valoresSwitch('ECOM_MBC_FORM'),
        );
        $inputs[] = array(
            'type' => 'text',
            'label' => $this->trans('Redirect after unsubscribing', array(), 'Modules.Ecommailbajaclick.Admin'),
            'name' => 'ECOM_MBC_REDIRECT',
            'tab' => 'mbcbaja',
            'desc' => $this->trans('Full URL of your own page. Leave it empty to use the page built into the module.', array(), 'Modules.Ecommailbajaclick.Admin'),
        );

        /* ---- Pestana avanzada ---- */
        $inputs[] = array(
            'type' => 'switch',
            'label' => $this->trans('Friendly URL', array(), 'Modules.Ecommailbajaclick.Admin'),
            'name' => 'ECOM_MBC_PRETTY',
            'tab' => 'mbcavanzado',
            'is_bool' => true,
            'values' => $this->valoresSwitch('ECOM_MBC_PRETTY'),
        );
        $inputs[] = array(
            'type' => 'text',
            'label' => $this->trans('Friendly URL path', array(), 'Modules.Ecommailbajaclick.Admin'),
            'name' => 'ECOM_MBC_ROUTE',
            'tab' => 'mbcavanzado',
            'desc' => $this->trans('Only letters, digits, hyphens and slashes.', array(), 'Modules.Ecommailbajaclick.Admin'),
        );
        $inputs[] = array(
            'type' => 'text',
            'label' => $this->trans('Days the unsubscribe link stays valid', array(), 'Modules.Ecommailbajaclick.Admin'),
            'name' => 'ECOM_MBC_EXPIRE_DAYS',
            'tab' => 'mbcavanzado',
            'class' => 'fixed-width-sm',
            'desc' => $this->trans('0 means it never expires, which is the recommended setting.', array(), 'Modules.Ecommailbajaclick.Admin'),
        );
        $inputs[] = array(
            'type' => 'switch',
            'label' => $this->trans('Trust the proxy headers for the IP address', array(), 'Modules.Ecommailbajaclick.Admin'),
            'name' => 'ECOM_MBC_TRUST_PROXY',
            'tab' => 'mbcavanzado',
            'desc' => $this->trans('Turn it on only if there is a proxy or a CDN in front of the shop.', array(), 'Modules.Ecommailbajaclick.Admin'),
            'is_bool' => true,
            'values' => $this->valoresSwitch('ECOM_MBC_TRUST_PROXY'),
        );
        $inputs[] = array(
            'type' => 'switch',
            'label' => $this->trans('Debug log', array(), 'Modules.Ecommailbajaclick.Admin'),
            'name' => 'ECOM_MBC_DEBUG',
            'tab' => 'mbcavanzado',
            'desc' => $this->trans('Writes to modules/ecom_mailbajaclick/logs/.', array(), 'Modules.Ecommailbajaclick.Admin'),
            'is_bool' => true,
            'values' => $this->valoresSwitch('ECOM_MBC_DEBUG'),
        );

        $fieldsForm = array();
        $fieldsForm[0]['form'] = array(
            'tinymce' => false,
            'legend' => array(
                'title' => $this->trans('Unsubscribe in one click', array(), 'Modules.Ecommailbajaclick.Admin'),
                'icon' => 'icon-envelope',
            ),
            'tabs' => array(
                'mbcgeneral' => $this->trans('General', array(), 'Modules.Ecommailbajaclick.Admin'),
                'mbcplantillas' => $this->trans('Templates', array(), 'Modules.Ecommailbajaclick.Admin'),
                'mbcbaja' => $this->trans('Unsubscribe', array(), 'Modules.Ecommailbajaclick.Admin'),
                'mbcavanzado' => $this->trans('Advanced', array(), 'Modules.Ecommailbajaclick.Admin'),
            ),
            'input' => $inputs,
            'submit' => array(
                'title' => $this->trans('Save', array(), 'Modules.Ecommailbajaclick.Admin'),
                'name' => 'submitEcomMbc',
            ),
        );

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $idLangDefecto;
        $helper->allow_employee_form_lang = $idLangDefecto;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitEcomMbc';
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->languages = $idiomas;
        $helper->tpl_vars = array(
            'fields_value' => $this->valoresFormulario($idiomas),
            'languages' => $idiomas,
            'id_language' => $idLangDefecto,
        );

        return $helper->generateForm($fieldsForm);
    }

    /**
     * Valores de los interruptores del formulario.
     *
     * @param string $nombre
     *
     * @return array
     */
    protected function valoresSwitch($nombre)
    {
        return array(
            array('id' => $nombre . '_on', 'value' => 1, 'label' => $this->trans('Yes', array(), 'Modules.Ecommailbajaclick.Admin')),
            array('id' => $nombre . '_off', 'value' => 0, 'label' => $this->trans('No', array(), 'Modules.Ecommailbajaclick.Admin')),
        );
    }

    /**
     * Valores actuales de todos los campos del formulario.
     *
     * Cada campo declarado en renderForm() tiene que aparecer aqui o Smarty
     * lanza "Undefined array key" y la pantalla se cae con un 500.
     *
     * @param array $idiomas
     *
     * @return array
     */
    protected function valoresFormulario(array $idiomas)
    {
        $valores = array();

        foreach (array_keys(self::$opciones) as $clave) {
            $valores[$clave] = Tools::getValue($clave, Configuration::getGlobalValue($clave));
        }

        $valores['ECOM_MBC_FOOTER_TEXT'] = array();
        foreach ($idiomas as $idioma) {
            $id = (int) $idioma['id_lang'];
            $valores['ECOM_MBC_FOOTER_TEXT'][$id] = Tools::getValue(
                'ECOM_MBC_FOOTER_TEXT_' . $id,
                Configuration::get('ECOM_MBC_FOOTER_TEXT', $id)
            );
        }

        $valores['mbc_plantillas_detectadas'] = '';

        return $valores;
    }

    /**
     * Lista de plantillas de correo encontradas en la tienda.
     *
     * @return string HTML
     */
    protected function renderPlantillasDetectadas()
    {
        $this->sincronizarPlantillasDetectadas();
        $activas = $this->normalizarListaPlantillas((string) Configuration::getGlobalValue('ECOM_MBC_TPL_INCLUDE'));
        $filas = array();
        foreach ($this->plantillasDetectadas() as $grupo => $plantillas) {
            if ($grupo === 'core') {
                foreach ($plantillas as $nombre) {
                    $filas[$nombre] = array(
                        'nombre' => $nombre,
                        'origen' => $this->trans('PrestaShop or active theme', array(), 'Modules.Ecommailbajaclick.Admin'),
                        'activa' => in_array($nombre, $activas, true),
                    );
                }
                continue;
            }
            foreach ($plantillas as $fila) {
                $nombre = $fila['plantilla'];
                if (!isset($filas[$nombre])) {
                    $filas[$nombre] = array(
                        'nombre' => $nombre,
                        'origen' => $fila['modulo'],
                        'activa' => in_array($nombre, $activas, true),
                    );
                } elseif (strpos($filas[$nombre]['origen'], $fila['modulo']) === false) {
                    $filas[$nombre]['origen'] .= ', ' . $fila['modulo'];
                }
            }
        }
        ksort($filas);
        $this->context->smarty->assign(array(
            'mbc_selector_plantillas' => array_values($filas),
            'mbc_selector_valor' => implode(PHP_EOL, $activas),
        ));

        return $this->display(__FILE__, 'views/templates/admin/plantillas.tpl');
    }

    protected function sincronizarPlantillasDetectadas()
    {
        $detectadas = $this->nombresPlantillasDetectadas();
        if (empty($detectadas)) {
            return;
        }
        $activas = $this->normalizarListaPlantillas((string) Configuration::getGlobalValue('ECOM_MBC_TPL_INCLUDE'));
        $inactivas = $this->normalizarListaPlantillas((string) Configuration::getGlobalValue('ECOM_MBC_TPL_EXCLUDE'));
        foreach ($detectadas as $nombre) {
            if (in_array($nombre, $activas, true) || in_array($nombre, $inactivas, true)) {
                continue;
            }
            if ($this->esPlantillaTransaccional($nombre)) {
                $inactivas[] = $nombre;
            } else {
                $activas[] = $nombre;
            }
        }
        if (!in_array('test', $activas, true)) {
            $activas[] = 'test';
        }
        $inactivas = array_values(array_diff($inactivas, $activas));
        sort($activas);
        sort($inactivas);
        Configuration::updateGlobalValue('ECOM_MBC_MODE', self::MODO_INCLUIR);
        Configuration::updateGlobalValue('ECOM_MBC_TPL_INCLUDE', implode(PHP_EOL, $activas));
        Configuration::updateGlobalValue('ECOM_MBC_TPL_EXCLUDE', implode(PHP_EOL, $inactivas));
    }

    protected function nombresPlantillasDetectadas()
    {
        $nombres = array();
        $detectadas = $this->plantillasDetectadas();
        foreach ($detectadas['core'] as $nombre) {
            $nombres[] = Tools::strtolower(trim((string) $nombre));
        }
        foreach ($detectadas['modulos'] as $fila) {
            $nombres[] = Tools::strtolower(trim((string) $fila['plantilla']));
        }
        $nombres = array_values(array_unique(array_filter($nombres)));
        sort($nombres);
        return $nombres;
    }

    protected function normalizarListaPlantillas($lista)
    {
        $resultado = array();
        foreach (preg_split('/[\r\n,;]+/', (string) $lista) as $nombre) {
            $nombre = Tools::strtolower(trim($nombre));
            if ($nombre !== '' && !in_array($nombre, $resultado, true)) {
                $resultado[] = $nombre;
            }
        }
        return $resultado;
    }

    protected function esPlantillaTransaccional($nombre)
    {
        $patrones = array(
            'order', 'pedido', 'payment', 'pago', 'invoice', 'factura', 'credit_slip',
            'refund', 'reembolso', 'shipped', 'shipping', 'envio', 'enviado', 'carrier',
            'transport', 'delivery', 'preparation', 'bankwire', 'cheque', 'cashondelivery',
            'password', 'contrasena', 'account', 'customer_qty', 'guest_to_customer',
            'contact', 'reply', 'support', 'employee', 'return', 'devolucion', 'download',
            'voucher', 'stock', 'supplier', 'merchant', 'backoffice', 'log_alert',
        );
        $normalizado = Tools::strtolower((string) $nombre);
        foreach ($patrones as $patron) {
            if (strpos($normalizado, $patron) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Busca las plantillas .html del nucleo, del tema y de los modulos.
     *
     * @return array array('core'=>array(),'modulos'=>array())
     */
    public function plantillasDetectadas()
    {
        $encontradas = array('core' => array(), 'modulos' => array());

        try {
            $iso = 'en';
            $idioma = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
            if (Validate::isLoadedObject($idioma)) {
                $iso = $idioma->iso_code;
            }

            $rutas = array(_PS_MAIL_DIR_ . '*/');
            if (defined('_PS_ALL_THEMES_DIR_') && is_object($this->context->shop)) {
                $rutas[] = _PS_ALL_THEMES_DIR_ . $this->context->shop->theme_name . '/mails/*/';
            }

            foreach ($rutas as $patronRuta) {
                foreach (array('html', 'txt') as $extension) {
                    $ficheros = glob($patronRuta . '*.' . $extension);
                    foreach (is_array($ficheros) ? $ficheros : array() as $fichero) {
                        $nombre = basename($fichero, '.' . $extension);
                        if (!in_array($nombre, $encontradas['core'], true)) {
                            $encontradas['core'][] = $nombre;
                        }
                    }
                }
            }

            foreach (array('html', 'txt') as $extension) {
                $ficheros = glob(_PS_MODULE_DIR_ . '*/mails/*/*.' . $extension);
                foreach (is_array($ficheros) ? $ficheros : array() as $fichero) {
                    $modulo = basename(dirname(dirname(dirname($fichero))));
                    $nombre = basename($fichero, '.' . $extension);
                    $llave = $modulo . '|' . $nombre;
                    $encontradas['modulos'][$llave] = array('modulo' => $modulo, 'plantilla' => $nombre);
                }
            }
            $encontradas['modulos'] = array_values($encontradas['modulos']);
        } catch (Exception $e) {
            Ecom_MailbajaclickLog::add('Fallo al detectar plantillas: ' . $e->getMessage(), 'admin');
        }

        sort($encontradas['core']);

        return $encontradas;
    }

    /**
     * Panel con las ultimas bajas registradas.
     *
     * @return string
     */
    protected function renderBajas()
    {
        $buscar = trim((string) Tools::getValue('mbc_buscar'));
        $pagina = max(1, (int) Tools::getValue('mbc_pagina'));
        $porPagina = 25;
        $total = Ecom_MailbajaclickBaja::total($buscar);

        // El numero de paginas se calcula aqui: usar |ceil como modificador de
        // Smarty esta obsoleto y el back-office lo saca en un modal de error.
        $paginas = (int) ceil($total / $porPagina);

        $this->context->smarty->assign(array(
            'mbc_bajas' => Ecom_MailbajaclickBaja::listado($porPagina, ($pagina - 1) * $porPagina, $buscar),
            'mbc_total' => $total,
            'mbc_paginas' => $paginas,
            'mbc_pagina' => $pagina,
            'mbc_por_pagina' => $porPagina,
            'mbc_buscar' => $buscar,
            'mbc_form_url' => $this->urlFormularioAdmin(),
        ));

        return $this->display(__FILE__, 'views/templates/admin/bajas.tpl');
    }

    /**
     * Descarga el registro de bajas en CSV.
     *
     * @return void
     */
    protected function exportarCsv()
    {
        $filas = Ecom_MailbajaclickBaja::listado(50000, 0, trim((string) Tools::getValue('mbc_buscar')));

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="bajas-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        $salida = fopen('php://output', 'w');
        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, array('id', 'email', 'id_shop', 'metodo', 'origen', 'ip', 'resultado', 'fecha'), ';');
        foreach ($filas as $fila) {
            fputcsv($salida, array(
                $fila['id_baja'],
                $fila['email'],
                $fila['id_shop'],
                $fila['metodo'],
                $fila['origen'],
                $fila['ip'],
                $fila['resultado'],
                $fila['date_add'],
            ), ';');
        }
        fclose($salida);
        exit;
    }
}
