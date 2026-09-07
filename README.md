# Baja en un clic (List-Unsubscribe) — módulo para PrestaShop 8 y 9

Añade a los correos de la tienda las cabeceras **`List-Unsubscribe`** y
**`List-Unsubscribe-Post`** que Gmail, Yahoo y Outlook exigen al correo comercial desde
febrero de 2024 (RFC 8058), y atiende la baja en un clic desde un controlador propio del
front.

Sin esas cabeceras, los comprobadores de entregabilidad avisan de *«Baja en un clic: no hay
ninguna forma de darse de baja»*, y los buzones penalizan la reputación del remitente hasta
mandar los envíos a la carpeta de correo no deseado.

- **Compatible con PrestaShop 8.x y 9.x.** En PrestaShop 8 el mensaje es un `Swift_Message`
  y en PrestaShop 9 un `Symfony\Component\Mime\Email`; el módulo usa el hook
  `actionMailAlterMessageBeforeSend`, que existe en las dos y expone la misma API de
  cabeceras.
- **Estructura legacy**: sin Composer, sin `vendor/`, sin `src/`, sin `config/services.yml`.
  Se instala copiando la carpeta.
- **Sin dependencias externas.** PHP 7.4 y superiores.

---

## Qué hace

| | |
|---|---|
| Cabecera `List-Unsubscribe` | Con una URL **distinta y firmada para cada destinatario**, más un `mailto:` opcional |
| Cabecera `List-Unsubscribe-Post` | `List-Unsubscribe=One-Click`, solo si la URL sale por HTTPS (lo exige la RFC) |
| Enlace visible en el pie | Opcional, con texto propio por idioma; obligatorio por ley en casi todos los países |
| Página de baja | Confirma la baja al visitante, o pide el correo si el enlace no trae código |
| Efecto de la baja | Desmarca `newsletter` en la ficha del cliente y desactiva la suscripción en `ps_emailsubscription` |
| Registro | Cada baja queda con fecha, IP, plantilla de origen y tienda, y se descarga en CSV |
| Aviso a otros módulos | Dispara el hook `actionEcomMailbajaclickUnsubscribe` |

El enlace es **autocontenido y firmado con HMAC-SHA256**: no hay que guardar nada por cada
correo enviado, sigue siendo válido meses después, y nadie puede dar de baja a otra persona
manipulando la URL.

---

## Instalación

1. Copiar la carpeta `ecom_mailbajaclick` en `modules/` de la tienda (o subir el ZIP desde
   **Módulos → Subir un módulo**).
2. Instalar el módulo.
3. Abrir su configuración y mirar el panel **Estado**: dice si los hooks están conectados,
   si la tabla existe, si hay clave de firma y si la URL de baja responde por HTTPS.

No hay que tocar nada más: con la configuración por defecto el módulo ya añade las
cabeceras a los correos de boletín.

---

## Configuración

Un único formulario con cuatro pestañas.

**General** — interruptor general, forzar HTTPS, `mailto:` de baja, enlace visible en el
pie y su texto por idioma.

**Plantillas** — decide qué correos llevan las cabeceras. Tres modos:

- *Solo las plantillas listadas* (**por defecto**), precargado con `newsletter`,
  `*newsletter*`, `*promo*`, `*campaign*`, `*mailing*`, `*marketing*`…
- *Todos los correos menos los listados*, con los transaccionales ya excluidos.
- *Todos los correos*.

Se admite el comodín `*`. Un acordeón lista todas las plantillas de correo que hay en la
tienda (del núcleo y de los módulos) y basta pulsar una para añadirla.

> **El correo transaccional no debe llevar estas cabeceras.** Una confirmación de pedido o
> un aviso de contraseña no son correo comercial, y ofrecer ahí una baja de boletín confunde
> al cliente. Por eso el modo por defecto es la lista blanca.

**Baja** — qué se desactiva al dar de baja (ficha de cliente, suscripción de invitados,
todas las tiendas a la vez), si se muestra el formulario manual y a qué página redirigir
después.

**Avanzado** — URL amigable y su ruta, días de validez del enlace (0 = no caduca, lo
recomendable), confianza en las cabeceras del proxy para la IP y registro de depuración.

---

## Cómo comprobar que funciona

**El botón «Enviar un correo de prueba» del back-office NO sirve para esto.**
`Mail::sendMailTest()` se fabrica su propio mensaje y **no dispara ningún hook**
(`classes/Mail.php`), así que ese correo nunca llevará la cabecera — ni con este módulo ni
con ningún otro.

Para comprobarlo de verdad:

1. Envía un correo real de una plantilla que esté en la lista (por ejemplo el boletín, o
   pon el modo «Todos los correos» mientras haces la prueba).
2. Ábrelo en Gmail → **Mostrar original** y busca:

   ```
   List-Unsubscribe: <https://tutienda.com/baja-boletin?u=eyJlIjoi...>
   List-Unsubscribe-Post: List-Unsubscribe=One-Click
   ```

3. En la bandeja de Gmail tiene que salir el enlace **«Cancelar suscripción»** junto al
   nombre del remitente.

En la pantalla del módulo, el botón **Abrir la URL de baja** comprueba que tu servidor
responde a esa dirección.

### HTTPS es obligatorio

`List-Unsubscribe-Post` solo se añade si la URL sale por `https://`. Sobre HTTP el módulo
sigue poniendo `List-Unsubscribe` (que también vale para el enlace de baja), pero no la
parte de un clic, porque los buzones la rechazan.

---

## Para desarrolladores

### Hooks que usa

| Hook | Para qué |
|---|---|
| `actionEmailSendBefore` | Captura la plantilla y el destinatario: es el único que los recibe |
| `actionMailAlterMessageBeforeSend` | Añade las dos cabeceras al mensaje |
| `actionEmailAddAfterContent` | Añade el enlace visible al pie, dentro de `</body>` |
| `moduleRoutes` | URL amigable de la página de baja |

### Hook que dispara

```php
public function hookActionEcomMailbajaclickUnsubscribe(array $params)
{
    // $params['email'], ['id_shop'], ['metodo'], ['origen'], ['resultado']
    // Aquí propagas la baja a Mailchimp, Brevo, tu CRM...
}
```

`metodo` vale `oneclick` (el buzón), `enlace` (la persona pulsando el pie), `formulario`
(escribiendo su correo) o `manual`.

### Endpoints del front

```
GET  /baja-boletin?ping=1     JSON de estado (lo usa el diagnóstico del back-office)
POST /baja-boletin?u=<código> Baja en un clic (RFC 8058). Responde texto plano
GET  /baja-boletin?u=<código> Baja desde el enlace del pie, con página de confirmación
GET  /baja-boletin            Formulario para escribir el correo a mano
```

También funcionan sin URL amigable:
`index.php?fc=module&module=ecom_mailbajaclick&controller=unsubscribe&…`

### Estructura

```
ecom_mailbajaclick.php              clase principal, en el espacio de nombres global
classes/
  Ecom_MailbajaclickToken.php       firma y verificación del código de la URL
  Ecom_MailbajaclickBaja.php        ejecuta y registra la baja
  Ecom_MailbajaclickLog.php         registro de depuración
controllers/front/unsubscribe.php   los cuatro endpoints de arriba
views/                              css, js y plantillas
translations/es-ES/                 ModulesEcommailbajaclick{Admin,Shop}.es-ES.xlf
sql/install.sql                     tabla del registro de bajas
```

Migraciones y altas de hooks en `addnewfeatures()`, que corre al abrir la configuración: no
hay ficheros `upgrade/`.

---

## Probado

Instalado y ejercitado en tiendas reales de las dos ramas:

| | PrestaShop 8.2.7 | PrestaShop 9.1.1 |
|---|---|---|
| Objeto de correo | `Swift_Message` | `Symfony\Component\Mime\Email` |
| Cabeceras sobre un mensaje real | ✅ | ✅ |
| Baja en un clic por POST | ✅ | ✅ |
| Enlace del pie y formulario manual | ✅ | ✅ |
| Pantalla de configuración, guardado por pestañas | ✅ | ✅ |
| Errores de JavaScript | 0 | 0 |

Incluye una autocomprobación en la propia pantalla del módulo que interroga al sistema ya
montado: hooks registrados, tabla creada, clave de firma, HTTPS y respuesta real de la URL
de baja.

---

## Licencia

AFL-3.0 — Ecom Experts <ecomyseo@gmail.com>
