# Publicidad y pantallas de sala

- Propósito: playlist de imágenes/videos para PDV. Misma playlist en la TV de turnos y en el kiosko de publicidad.
- Gestión: `GET /punto-venta/publicidad` (`PuntoVenta/Publicidad/Index.jsx`). Permisos `pdv.publicidad.ver|crear|editar|eliminar|ordenar`.
- TV de turnos: `GET /sala-turnos/t/{token}` (permanente) y `GET /s/{codigo}` (corto). → `PuntoVenta/Pantallas/Sala`.
- Kiosko: `GET /sala-publicidad/t/{token}` y `GET /k/{codigo}`. Query `disposicion=auto|vertical`.
- Enlace: un token y un `codigo_corto` de 6 (mayúsculas, sin 0/O/1/I) por sucursal. JSON: `url`, `url_kiosko`, `url_corta`, `url_kiosko_corta`, `codigo_corto`. Activar/desactivar apaga ambas. El token largo no se recambia.
- Archivos: disco `public` `pdv/pantalla-publicidad/`. Sin recodificar ni ffmpeg. `ajuste` = `cover|contain`. `rotacion` = `0|90|180|270` (CSS).
- Playlist pública: `id`, `tipo`, `url`, `duracion_seg`, `ajuste`, `rotacion`, `volumen_pct`.
- Mini editor: `EditorEncuadrePublicidad.jsx` (marcos 16:9 y 9:16). Reproductor: `CapaMediaRotada.jsx`.
- Acceso: `GET /punto-venta/pantalla-sala`. Permiso `pdv.pantalla_sala.abrir`.
- Pruebas: `PublicidadPdvTest`, `PantallaSalaPdvTest`, `EnlacePantallaSalaPdvTest`; `rotacionMedioPublicidad.test.js`.

## Estado actual

- [verificado] Kiosko reusa el poll de sala (`/sala-turnos/.../estado` o `/s/{codigo}/estado`). No anuncia turnos.
- [verificado] `/s/{codigo}` y `/k/{codigo}` resuelven el mismo registro; minúsculas se aceptan.
- [verificado] `disposicion=vertical` rota el escenario si el viewport es apaisado.
- [verificado] Piezas globales y de sucursal se mezclan; vigencia y depuración no cambian.
- Fuera de alcance: recodificar videos, playlists distintas, segundo token.

## Reglas que no se rompen

- Conservar la TV de turnos y los pedidos/flujos municipales y nacionales.
- No restaurar «Decisión del cliente». En UI, nombres de operación, no códigos de ficha.
- El archivo original no se reescribe al girar.

Última verificación: 2026-11-27, sin commit, árbol de trabajo.
