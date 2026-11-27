# API móvil — espejo de Punto de Venta y Gestión de pedidos

- [verificado] La app móvil inicia sesión con la cuenta GELIA (`POST /api/v1/mobile/login`) y recibe `permissions` (los efectivos, igual que la web; `User::getPermissionsViaRoles()` está anulado, así que los roles no otorgan acceso) y `roles` (solo identidad). La app rige sus vistas con esa lista; el servidor vuelve a validar cada ruta.
- [verificado] `routes/api/mobile-punto-venta.php` y `routes/api/mobile-control-pedidos.php` espejan las rutas web de `punto-venta` y `control-pedidos` bajo `/api/v1/mobile/{misma-uri-web}`, con el mismo controlador y el mismo permiso (`can:`, `role_or_permission:`, `pdv.permiso:`). En PDV, `pdv.piso` equivale a `mobile.pdv:operacion` y `pdv.modulo` a `mobile.pdv`.
- [verificado] Middleware común (`routes/api.php`): `require.json`, `auth:sanctum`, `api.mobile`, `throttle:api-mobile` y `mobile.json` ([ResponderJsonMovil](../../app/Http/Middleware/ResponderJsonMovil.php)), que fuerza `Accept: application/json`, convierte redirecciones con flash en JSON (422 con error, 200 con éxito) y responde 406 si un controlador intenta devolver una pantalla HTML.
- [verificado] Las pantallas Inertia sin versión JSON (`index` de Tienda, CEDIS, Delegado, Auditar, Direcciones, Plazos, Configurar sucursal, Pantalla sala, Publicidad, Auditoría de operación) no se espejan; están en `config/api_paridad.php` con su motivo. La app usa los endpoints de datos (`listado`, `datos`, `detalle`).
- Credenciales vigentes: los tokens móviles ya emitidos funcionan en las rutas nuevas sin volver a iniciar sesión (mismo guard, sin habilidades nuevas). Las aplicaciones externas con `client_id/secret` siguen limitadas a `clientes`: estas rutas operan a nombre de un usuario con permisos, no de una aplicación.

## Mantener al día

Al agregar o cambiar una ruta web en `punto-venta` o `control-pedidos`, espejarla en el archivo `routes/api/mobile-*.php` correspondiente (misma uri con prefijo `mobile/`, mismo permiso) o excluirla en `config/api_paridad.php` con motivo. Comprobar con `./vendor/bin/sail artisan api:paridad-rutas`; la prueba `MobileEspejoRutasTest` falla si hay desfase.

- Última verificación: 2026-11-26, sin commit. `[verificado]`: 334 rutas web revisadas, 334 cubiertas; lint de PHP, listado de rutas sin sombras y traducción del middleware probados en `gelianv_app`. `[inferido]`: el comportamiento de cada acción con un token real y las pruebas PHPUnit no se ejecutaron.
