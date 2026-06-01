# Notificaciones Push Web (GELIANV)

## Arquitectura

| Capa | Tecnología | Rol |
|------|------------|-----|
| **Tiempo real (app abierta)** | Laravel Reverb + Echo | Toasts, sonido, voz, `Notification` API en pestaña activa |
| **Push en segundo plano** | Web Push + VAPID + Service Worker | Alertas con navegador cerrado o en otra pestaña |
| **Persistencia** | Tabla `notifications` + `push_subscriptions` | Historial en campana + endpoints por dispositivo |

No se usa Firebase en esta implementación: **Web Push estándar** funciona en Chrome/Edge/Firefox (escritorio y Android). En **iOS Safari** requiere instalar el sitio como PWA (Añadir a pantalla de inicio) desde iOS 16.4+.

## Flujo

1. El usuario concede permiso de notificaciones y el frontend registra `/sw.js`.
2. `PushManager.subscribe()` genera una suscripción ligada a la clave pública VAPID.
3. La suscripción se guarda en `push_subscriptions` (`POST /push/subscribe`).
4. Al crear una solicitud (`AlertaSolicitud` → canal `database`), el listener `EnviarWebPushTrasNotificacion` envía Web Push.
5. Al enviar un mensaje (`NotificarMensajeEnviadoService`), se envía Web Push a participantes.

## Configuración servidor

```bash
php artisan webpush:vapid
# Copiar WEBPUSH_VAPID_PUBLIC_KEY y WEBPUSH_VAPID_PRIVATE_KEY al .env

php artisan migrate
php artisan config:clear
```

Variables `.env`:

```env
WEBPUSH_ENABLED=true
WEBPUSH_VAPID_SUBJECT=https://tu-dominio.com
WEBPUSH_VAPID_PUBLIC_KEY=...
WEBPUSH_VAPID_PRIVATE_KEY=...
```

Las colas deben estar activas (`QUEUE_CONNECTION=redis` + `php artisan queue:work`) porque las notificaciones de solicitud usan `ShouldQueue`.

## Rutas API

- `GET /push/vapid-public-key` — clave pública para el navegador
- `POST /push/subscribe` — guardar suscripción
- `DELETE /push/unsubscribe` — eliminar suscripción

## Frontend

- `public/sw.js` — Service Worker (eventos `push` y `notificationclick`)
- `resources/js/Services/WebPushService.js` — permisos, registro SW, suscripción
- `resources/js/hooks/useWebPush.js` — auto-suscripción si alertas de escritorio activas
- `AppLayout` — inicializa el hook

## Pruebas

1. Activar “Escritorio” en Preferencias → Alertas.
2. Aceptar permiso del navegador al abrir la campana de notificaciones.
3. En DevTools → Application → Service Workers: debe aparecer `/sw.js` activo.
4. Cerrar la pestaña y disparar una solicitud o mensaje desde otro usuario.
5. Debe aparecer la notificación del sistema operativo.
