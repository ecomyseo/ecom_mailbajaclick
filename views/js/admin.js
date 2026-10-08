/**
 * Baja en un clic (List-Unsubscribe)
 *
 * @author    Ecom Experts <ecomyseo@gmail.com>
 * @copyright 2026 Ecom Experts
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License (AFL 3.0)
 */
(function () {
    'use strict';

    /**
     * Todo se engancha por delegacion sobre el documento: los paneles del
     * HelperForm se pintan despues de que corra este fichero, y un
     * addEventListener directo sobre ellos seria null.
     */
    document.addEventListener('click', function (evento) {
        var plantilla = evento.target.closest ? evento.target.closest('.ecom-mbc-plantilla') : null;
        if (plantilla) {
            evento.preventDefault();
            moverPlantilla(plantilla);
            return;
        }

        var acordeon = evento.target.closest ? evento.target.closest('.ecom-mbc-acordeon') : null;
        if (acordeon) {
            evento.preventDefault();
            var destino = document.getElementById(acordeon.getAttribute('data-mbc-destino'));
            if (destino) {
                destino.style.display = (destino.style.display === 'none' || destino.style.display === '') ? 'block' : 'none';
            }
            return;
        }

        var confirmar = evento.target.closest ? evento.target.closest('.ecom-mbc-confirmar') : null;
        if (confirmar) {
            var aviso = confirmar.getAttribute('data-mbc-aviso') || '';
            if (aviso && !window.confirm(aviso)) {
                evento.preventDefault();
            }
            return;
        }

        var ficha = evento.target.closest ? evento.target.closest('.ecom-mbc-ficha') : null;
        if (ficha) {
            evento.preventDefault();
            anadirPlantilla(ficha.getAttribute('data-mbc-plantilla'));
        }
    });

    document.addEventListener('input', function (evento) {
        if (evento.target.id !== 'ecom-mbc-buscar-plantilla') {
            return;
        }
        var texto = normalizar(evento.target.value);
        document.querySelectorAll('.ecom-mbc-plantilla').forEach(function (plantilla) {
            var contenido = normalizar(plantilla.getAttribute('data-mbc-busqueda') || '');
            plantilla.style.display = contenido.indexOf(texto) !== -1 ? '' : 'none';
        });
    });

    function moverPlantilla(plantilla) {
        var estaActiva = plantilla.closest('#ecom-mbc-lista-si') !== null;
        var destino = document.getElementById(estaActiva ? 'ecom-mbc-lista-no' : 'ecom-mbc-lista-si');
        if (!destino || plantilla.getAttribute('data-mbc-plantilla') === 'test') {
            return;
        }
        destino.appendChild(plantilla);
        var flecha = plantilla.querySelector('span');
        if (flecha) {
            flecha.textContent = estaActiva ? '→' : '←';
        }
        sincronizarSelector();
    }

    function sincronizarSelector() {
        var campo = document.getElementById('ECOM_MBC_TPL_INCLUDE_SELECTOR');
        if (!campo) {
            return;
        }
        var activas = [];
        document.querySelectorAll('#ecom-mbc-lista-si .ecom-mbc-plantilla').forEach(function (plantilla) {
            activas.push(plantilla.getAttribute('data-mbc-plantilla'));
        });
        campo.value = activas.join('\n');
    }

    function normalizar(texto) {
        texto = (texto || '').toLowerCase();
        if (texto.normalize) {
            texto = texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
        return texto;
    }

    /**
     * Anade el nombre de una plantilla al area de texto que toca segun el modo
     * de filtrado elegido.
     *
     * @param {string} nombre
     */
    function anadirPlantilla(nombre) {
        if (!nombre) {
            return;
        }

        var excluir = document.getElementById('mbc_modo_excluir');
        var campo = document.getElementById(
            (excluir && excluir.checked) ? 'ECOM_MBC_TPL_EXCLUDE' : 'ECOM_MBC_TPL_INCLUDE'
        );

        if (!campo) {
            return;
        }

        var lineas = campo.value.split(/\r?\n/).map(function (linea) {
            return linea.trim();
        }).filter(function (linea) {
            return linea !== '';
        });

        if (lineas.indexOf(nombre) === -1) {
            lineas.push(nombre);
            campo.value = lineas.join('\n');
        }

        campo.focus();
    }

    /**
     * Autocomprobacion: las claves de las pestanas del HelperForm se convierten
     * en id del DOM. Si una choca con un id que ya existe en el back-office,
     * jQuery mueve esos campos FUERA del formulario y la pestana deja de
     * guardarse diciendo "Configuracion guardada". Esto lo avisa en la consola.
     */
    document.addEventListener('DOMContentLoaded', function () {
        var pestanas = ['mbcgeneral', 'mbcplantillas', 'mbcbaja', 'mbcavanzado'];

        pestanas.forEach(function (clave) {
            var panel = document.getElementById(clave);
            if (!panel) {
                return;
            }
            if (!panel.closest('form')) {
                window.console && window.console.error(
                    '[ecom_mailbajaclick] La pestaña "' + clave + '" ha quedado fuera del formulario: no se guardara.'
                );
            }
        });
    });
})();
