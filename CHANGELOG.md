# Historial de cambios

## 1.2.1 - 2026-10-08

- Corrige el error «Undefined constant n» al guardar o mostrar la selección de plantillas.

## 1.2.0 - 2026-10-08

- Selector de dos columnas «NO» y «SÍ» con buscador.
- Inventario conjunto de plantillas del núcleo, tema activo y todos los módulos.
- Las plantillas nuevas se incorporan automáticamente.
- Las plantillas transaccionales conocidas quedan en «NO» y las demás en «SÍ».
- La plantilla de prueba permanece obligatoriamente en «SÍ».

## 1.1.1 - 2026-10-08

- La prueba de correo de PrestaShop lleva obligatoriamente las dos cabeceras de baja.
- Se cubre la ruta directa Mail::sendMailTest, que no ejecuta los hooks normales.
- La plantilla test se fuerza aunque no aparezca en la lista configurada.

## 1.1.0 - 2026-10-08

- GET ya no modifica suscripciones; muestra confirmación POST firmada.
- La validación RFC 8058 exige el campo requerido en el cuerpo.
- El formulario público envía confirmación y limita solicitudes.
- Las bajas son transaccionales y mantienen una lista de supresión.
- Mail::Send bloquea los envíos comerciales suprimidos.
- Se rechazan destinatarios múltiples y se respetan cabeceras existentes.
- La migración conserva opciones, bajas y enlaces anteriores.
- La desinstalación conserva las tablas de supresión.
