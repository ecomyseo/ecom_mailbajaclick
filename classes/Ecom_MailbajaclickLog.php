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
 * Registro de depuracion del modulo.
 *
 * Solo escribe si la opcion ECOM_MBC_DEBUG esta activada. Los ficheros viven
 * dentro de la carpeta del modulo (nunca en /var/logs ni en /tmp) y se rotan
 * por dia con un tope de tamano.
 */
class Ecom_MailbajaclickLog
{
    /** Tope de tamano de cada fichero de registro. */
    const MAX_BYTES = 2097152;

    /** Dias que se conservan los ficheros de registro. */
    const MAX_DIAS = 15;

    /** @var bool|null Cache de la opcion de depuracion para la peticion actual. */
    protected static $activo = null;

    /**
     * Indica si la depuracion esta activada.
     *
     * @return bool
     */
    public static function activo()
    {
        if (self::$activo === null) {
            self::$activo = (bool) Configuration::getGlobalValue('ECOM_MBC_DEBUG');
        }

        return self::$activo;
    }

    /**
     * Fuerza el estado de la depuracion (se usa al guardar la configuracion).
     *
     * @param bool $valor
     *
     * @return void
     */
    public static function setActivo($valor)
    {
        self::$activo = (bool) $valor;
    }

    /**
     * Carpeta donde se guardan los registros.
     *
     * @return string
     */
    public static function dir()
    {
        return _PS_MODULE_DIR_ . 'ecom_mailbajaclick/logs/';
    }

    /**
     * Escribe una linea en el registro del dia.
     *
     * @param string $mensaje
     * @param string $contexto Etiqueta corta que identifica el punto del codigo
     * @param array  $datos    Datos adicionales que se serializan en JSON
     *
     * @return void
     */
    public static function add($mensaje, $contexto = 'general', array $datos = array())
    {
        if (!self::activo()) {
            return;
        }

        try {
            $dir = self::dir();
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            if (!is_dir($dir) || !is_writable($dir)) {
                return;
            }

            $fichero = $dir . 'ecom_mailbajaclick-' . date('Y-m-d') . '.log';

            if (is_file($fichero) && filesize($fichero) > self::MAX_BYTES) {
                @rename($fichero, $fichero . '.' . time() . '.old');
            }

            $linea = '[' . date('Y-m-d H:i:s') . '] [' . $contexto . '] ' . $mensaje;
            if (!empty($datos)) {
                $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $linea .= ' | ' . (is_string($json) ? $json : '');
            }

            @file_put_contents($fichero, $linea . PHP_EOL, FILE_APPEND | LOCK_EX);
            self::limpiar();
        } catch (Exception $e) {
            // El registro nunca puede tumbar una peticion.
        }
    }

    /**
     * Borra los registros mas antiguos que MAX_DIAS.
     *
     * @return void
     */
    protected static function limpiar()
    {
        $marca = _PS_MODULE_DIR_ . 'ecom_mailbajaclick/logs/.limpieza';
        if (is_file($marca) && (time() - (int) @filemtime($marca)) < 86400) {
            return;
        }
        @touch($marca);

        $ficheros = glob(self::dir() . 'ecom_mailbajaclick-*.log*');
        $limite = time() - (self::MAX_DIAS * 86400);
        foreach (is_array($ficheros) ? $ficheros : array() as $fichero) {
            if (@filemtime($fichero) < $limite) {
                @unlink($fichero);
            }
        }
    }

    /**
     * Devuelve las ultimas lineas del registro mas reciente.
     *
     * @param int $lineas
     *
     * @return string
     */
    public static function ultimas($lineas = 200)
    {
        $ficheros = glob(self::dir() . 'ecom_mailbajaclick-*.log');
        $ficheros = is_array($ficheros) ? $ficheros : array();
        if (empty($ficheros)) {
            return '';
        }
        rsort($ficheros);
        $contenido = @file($ficheros[0], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $contenido = is_array($contenido) ? $contenido : array();

        return implode("\n", array_slice($contenido, -(int) $lineas));
    }

    /**
     * Borra todos los ficheros de registro del modulo.
     *
     * @return int Numero de ficheros borrados
     */
    public static function vaciar()
    {
        $ficheros = glob(self::dir() . 'ecom_mailbajaclick-*.log*');
        $borrados = 0;
        foreach (is_array($ficheros) ? $ficheros : array() as $fichero) {
            if (@unlink($fichero)) {
                ++$borrados;
            }
        }

        return $borrados;
    }
}
