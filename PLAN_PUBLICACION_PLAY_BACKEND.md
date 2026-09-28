# GELIA-NV: fases del backend para publicar la app móvil

Revisión del repositorio `LoyaliZA/gelia-nv`, `main` en `c7994ee`, 28 de septiembre de 2026. Esta guía describe trabajo pendiente; no certifica cumplimiento ni cambia el código. Acompañar con el [plan móvil](https://github.com/LoyaliZA/gelia-nv-mobile/blob/main/PLAN_PUBLICACION_PLAY_MOBILE.md) una vez integrado allí.

## Estado real que conviene conservar

- `routes/api.php` ya separa `/api/v1/mobile/login`, `/mobile/me`, `/mobile/logout`, perfil y sincronización, y protege las rutas móviles con Sanctum, `api.mobile` y límites de solicitudes.
- `MobileAuthService` crea tokens Sanctum `mobile:{device_uuid}` que caducan a los 30 días por defecto; `AuthenticateMobileUser` comprueba que el token siga existiendo y que el dispositivo no esté revocado. El logout revoca el token. No hace falta sustituir esta arquitectura para publicar.
- `MobileClienteSerializerService` filtra campos por permisos; `MobileClienteAlcanceService` limita los clientes visibles; `EnsureMobileSyncScope` devuelve `409 scope_changed` ante una versión distinta. Los datos enviados **sí** pueden incluir RFC, correo y domicilio fiscal, además de montos de crédito para permisos específicos.
- `routes/web/guest.php` contiene registro web de colaboradores mediante enlace firmado. Determinar si ese recorrido forma parte de la creación de la cuenta de esta app antes de responder la declaración de eliminación de cuentas de Play; el cliente móvil actual no muestra un formulario de registro.

## Fase B1 — Inventario de datos y política pública

**Archivos:** `config/mobile.php`, `MobileClienteSerializerService.php`, `MobileProfileService.php`, `routes/web/public.php`; nueva vista/ruta pública.

1. Levantar una matriz de los datos recibidos y enviados por login/password, passkeys, perfil, foto, cliente, sincronización y logs/auditoría. Distinguir datos del colaborador y datos de los clientes de la empresa; documentar quién los procesa, infraestructura/proveedores, finalidad, retención, borrado y solicitudes de privacidad. Revisar también backend, logs, respaldos y SDKs reales; el código móvil no muestra por sí solo toda la operación.
2. Aprobar texto con el responsable legal/privacidad de GELIA. Publicarlo en una URL estable, pública, sin autenticación, sin restricción geográfica y en HTML, por ejemplo `/privacidad-app`, con nombre de la app y entidad publicadora, contacto, datos tratados, usos, terceros, medidas de seguridad, plazos y procedimiento de solicitudes. No rellenar detalles legales o plazos con conjeturas.
3. Dejar una URL/versionado de la política que la app pueda abrir incluso antes del login; enlazarla también en Play Console. Comprobar respuesta 200 desde una sesión anónima y desde fuera de la red corporativa.
4. Para Seguridad de los datos, revisar categoría por categoría el flujo completo. Candidatos a confirmar: nombre, correo, ID de usuario, credenciales de autenticación, ID generado de dispositivo, foto opcional y datos profesionales/fiscales de clientes transferidos desde la API. La copia offline en el teléfono por sí sola no equivale a “recopilación”; el envío al servidor sí requiere evaluación. No marcar “no se comparte” sin verificar proveedores y tratamiento.

**Hecho cuando:** existe URL pública aprobada y una matriz trazable `campo → endpoint → finalidad → almacenamiento/retención → categoría Play → responsable`. El texto de Play coincide con el comportamiento publicado.

## Fase B2 — Sesiones, revocación y límites de acceso

**Archivos:** `app/Services/Mobile/MobileAuthService.php`, `app/Http/Middleware/AuthenticateMobileUser.php`, `app/Models/MobileDevice.php`, `app/Providers/AppServiceProvider.php` y pruebas `tests/Feature/Mobile/*`.

1. Mantener la revocación actual de Sanctum y añadir un flujo administrativo verificable de **revocar un dispositivo/usuario** que invalide sus tokens `mobile:*` y marque el dispositivo revocado. Revisar la semántica actual de `updateOrCreate(..., revocado_at => null)`: volver a iniciar sesión en un dispositivo revocado lo reactiva; decidir si se permite únicamente tras autorización o si el usuario puede rehabilitarlo con contraseña/passkey. Probar la decisión.
2. Confirmar política para usuarios deshabilitados/retirados y asegurar que ni password ni passkey puedan emitir un token nuevo si la cuenta no debe entrar; validar esto también en `/mobile/me` y en las rutas protegidas. No prometer borrado remoto de una copia offline mientras el dispositivo permanezca sin conexión.
3. Revisar límites de fuerza bruta para **login con contraseña**: la ruta pública `/mobile/login` no usa el grupo `throttle:api-mobile`; sí existe `passkeys-login` y el grupo protegido tiene límites. Aplicar límite por IP y por identificador normalizado sin revelar existencia del usuario; probar `429` y `Retry-After`, considerando el acceso de revisión de Google.
4. Confirmar el plazo de 30 días (`config/mobile.php`) con operaciones y seguridad. Si se acorta, acordar con móvil cómo volver a iniciar sesión, el mensaje de expiración y cuándo se purga el catálogo. Evitar poner tokens/contraseñas en logs y excepciones.

**Hecho cuando:** revocar token/dispositivo devuelve `401` en `/mobile/me` y sincronización; el reingreso sigue la política decidida; intentos repetidos de password son limitados; no hay secretos en logs. Cambios de servidor se despliegan antes de que la nueva app dependa de ellos.

## Fase B3 — Alcance y minimización del catálogo

**Archivos:** `config/mobile.php`, `MobileScopeVersionService.php`, `MobileClienteAlcanceService.php`, `MobileClienteSerializerService.php`, `MobileSyncPublicationService.php`, servicios de bootstrap/cambios.

1. Confirmar qué campos necesita realmente el trabajo offline. RFC, dirección fiscal, correo y crédito deben salir solo cuando la función y permiso los requieran; reducir `campos.base` si no son necesarios. Toda modificación de campos debe elevar `field_policy_version` o `serializer_version` y provocar reemplazo de caché en la app.
2. Probar permiso retirado, cambio de vendedor, reasignación de cliente, revocación de dispositivo y cambio de rol, tanto en búsqueda directa como en snapshots y `/sync/changes`. La versión actual de `MobileScopeVersionService` depende de permisos y versiones del serializador: **no representa por sí sola una reasignación de clientes con permisos idénticos**. Asegurar eventos de revocación en el feed para ese caso, o extender el mecanismo de invalidación.
3. Acordar con móvil que `401/403`, `409 scope_changed` y pérdida de permisos lleven a purga local según el contrato de B4. Los controles del servidor protegen las solicitudes en línea; la copia offline necesita un límite de vigencia explícito y no admite revocación instantánea sin red.

**Hecho cuando:** un usuario no obtiene campos/clientes fuera de su alcance por ninguna ruta; los cambios de alcance terminan purgando o reconstruyendo el catálogo local al reconectar; las pruebas contemplan el intervalo sin red.

## Fase B4 — Contrato backend ↔ app, revisión y despliegue

| Situación | Respuesta del backend | Obligación móvil |
|---|---|---|
| Logout conectado | `POST /mobile/logout` revoca token | Detener sync, borrar sesión y **todos** los scopes locales de la app |
| Logout sin red | No puede garantizarse revocación remota inmediata | Borrar datos locales inmediatamente; definir política de token huérfano/expiración y opción administrativa de revocación |
| Token revocado o caducado | `401` consistente | Borrar sesión y catálogos; pedir login nuevo |
| Permiso retirado | `403` o versión nueva según ruta | Bloquear lectura offline y purgar catálogo; no dejar copia utilizable |
| Alcance/serializador cambia | `/mobile/me` devuelve `scope_version` nuevo; sync puede devolver `409 scope_changed` | Borrar scopes obsoletos y reconstruir desde bootstrap |
| Dispositivo sin conexión | El servidor no puede intervenir | Aplicar límite de vigencia local acordado; revalidar al volver la red |

Crear una cuenta de **revisión de Google Play** con datos ficticios y acceso funcional a todas las vistas declaradas. Entregar sus credenciales e instrucciones **solo por la sección de acceso de Play Console**, nunca por GitHub ni en esta guía. Verificar que no dependa de OTP, aprobación manual, geobloqueo ni datos reales y que pueda mantenerse durante la revisión. Si passkeys son opcionales, indicar que el revisor use usuario y contraseña.

En la publicación, desplegar B1–B3 antes de la app que requiera nuevos comportamientos; conservar compatibilidad temporal con la versión instalada. Probar en entorno de staging con datos ficticios, después revisar rutas públicas, login, permisos, sincronización, revocación y logs en producción. Registrar responsable, fecha y versión final del formulario de Play.

## Decisiones pendientes de producto y operaciones

- Entidad legal y contacto que aparecerán en política y ficha de Play; proveedores, retención y procedimiento de solicitudes.
- Si el registro web por invitación se ofrece o enlaza como creación de cuenta de esta app. La política de eliminación de Play se activa si la app permite crear la cuenta **desde la app**; revisar el recorrido final y la declaración con el responsable legal.
- Política de acceso offline: duración máxima de catálogo sin validación y tratamiento de cierres de sesión sin red. Este límite no equivale a revocación remota instantánea.
- Permisos y datos sintéticos para la cuenta revisora; firma de producción para `/.well-known/assetlinks.json` si se usarán passkeys.

## Referencias oficiales

- [Google Play: datos de usuario y política de privacidad](https://support.google.com/googleplay/android-developer/answer/10144311?hl=es)
- [Google Play: Seguridad de los datos](https://support.google.com/googleplay/android-developer/answer/10787469?hl=es)
- [Google Play: detalles de acceso para revisión](https://support.google.com/googleplay/android-developer/answer/9859455?hl=es)
- [Google Play: eliminación de cuentas](https://support.google.com/googleplay/android-developer/answer/13327111?hl=es)
