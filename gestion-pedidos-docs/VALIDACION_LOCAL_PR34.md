# PR #34 — corrección CSS y validación local

> Continuación: la aceptación con MySQL persistente y sesiones autenticadas se completó en [ACEPTACION_OPERATIVA_PR34.md](ACEPTACION_OPERATIVA_PR34.md), incluida una corrección local de lectura documental. Los pendientes de este informe describen el cierre técnico anterior.

12 de noviembre de 2026. Rama `develop`, HEAD inicial `7c32f05a`, árbol inicialmente limpio. Sin commit, push, merge adicional ni despliegue a producción.

## Corrección aplicada

`resources/css/gelia/features/control-pedidos-gestion-bma.css` contenía un conflicto textual sin resolver, en las líneas originales 1024–1073. El lado HEAD estaba vacío; el otro lado conservaba reglas completas para acciones secundarias, metadatos y cabecera de auditoría. Los marcadores se interpretaban como parte de los selectores siguientes.

Se retiraron exclusivamente `<<<<<<< HEAD`, `=======` y `>>>>>>> origin/develop`. Todas las declaraciones, selectores, media queries y su orden quedaron intactos. Se conservan los duplicados originales para no alterar la cascada ni ampliar el encargo. No se atribuye el origen histórico del conflicto al PR #34: ese PR no modificó este archivo.

Búsqueda en archivos versionados y fuentes, incluyendo ocultos y no versionados (excluyendo Git, dependencias, assets compilados y almacenamiento): no aparecieron otros marcadores de conflicto. `git diff --check` aprobado.

Build ejecutado una vez mediante `./vendor/bin/sail npm run build`, siguiendo AGENTS.md; npm y pnpm invocan el mismo script `build`. Salida exitosa, manifiesto y archivos verificados y publicados en `public/build`, con retención de assets anteriores. Desaparecieron las advertencias por selectores inválidos. Persisten los avisos de deprecación de `module.register()` y paquetes superiores a 500 kB. Sin `public/hot`. El usuario de `sail node/npm` es UID 1000; el manifiesto pertenece a ese usuario.

Stylelint del archivo completo no pasa: 28 observaciones de duplicados, especificidad y formato conservadas en el archivo. No se aplicó reformateo automático ni se presenta el lint como aprobado.

## Error de escritura atendido durante la revisión

La ruta `storage/app/public/pedidos_bma/tareas_preparacion` y su contenido pertenecían a `root:root` con directorios 755 y archivos 644. El directorio tenía fecha del 6 de noviembre. Laravel HTTP se ejecuta como `sail` (1000:1000), sin escritura sobre ese directorio.

Se ejecutó exclusivamente:

```bash
./vendor/bin/sail exec -u root laravel.test chown -R sail:sail /var/www/html/storage/app/public/pedidos_bma/tareas_preparacion
```

Se conservaron los modos existentes, sin permisos generales. Como UID 1000 se comprobó crear la subcarpeta `2`, escribir y retirar un archivo temporal; se retiró también esa subcarpeta al quedar vacía. No se alteraron documentos existentes. En el host UID 1000 se muestra como `ti-admin`; dentro del contenedor como `sail`. Falta reintentar la carga desde el formulario para acreditar el recorrido de esa operación.

## Entorno comprobado

- Sail activo: Laravel HTTP en puerto 9080, MySQL, Redis, Meilisearch, Mailpit, queue, scheduler, Reverb y Selenium/Chromium. Los servicios con healthcheck reportan healthy; los demás están Up, sin acreditar por ello su entrega funcional de eventos.
- Laravel informa entorno `local`, conexión MySQL con host interno `mysql`. Se comprobó conectividad con `SELECT 1`, sin consultar datos de clientes.
- Sesiones y colas en database; broadcasting Reverb. PHP: `upload_max_filesize=512M`, `post_max_size=512M`, `max_file_uploads=200`.
- Chromium navegó al login real de Laravel y cargó recursos de `/build/`. No se inició sesión ni se enviaron formularios en esa comprobación.
- Las pruebas HTTP usan SQLite `:memory:` y ambas cachés aisladas. Crean actores/pedidos ficticios efímeros; no dejan cuentas navegables en MySQL.
- No se ha provisionado una base persistente exclusiva de QA con actores autenticados para los recorridos cruzados. Los guiones visuales usan el HTML de Laravel y los componentes React/CSS reales del build, con props y transporte ficticios. No son una sesión operativa integrada ni maquetas HTML.

## Resultado por ajuste

| Ajuste | Evidencia obtenida | Límite pendiente |
| --- | --- | --- |
| Scroll de Preparación y consultas respondidas | Guion existente de contenido largo: acción Enviar respuesta accesible en 1440, 1024 y 390. Guion nuevo: inicial, respuesta recibida y apartado en los tres anchos, claro/oscuro; sin scroll anidado ni desbordamiento en esas fixtures. | Respuesta extensa con muchas piezas/documentos en una sesión autenticada. |
| Transferencias tras cambios | HTTP: anexo invalida consulta, diferencia de $50 bloquea envío, cobertura permite revisión/aprobación; un cambio informal posterior conserva pagos y validación. | Retiro que reduce importe y genera excedente, y recorrido completo entre actores en navegador. |
| Jerarquía Tienda/CEDIS | HTTP: Mismo día → actualización pendiente → consulta nueva → resto; prioridad antes de paginar, configuración y desempate por antigüedad. | Confirmar bandejas y filtros con sesiones por sucursal y actores reales de prueba. |
| Actualización tras aprobación | Pruebas del hook: agrupa eventos, filtra por pedido, conserva captura, recupera actualización al dejar de editar y respaldo de 15 segundos cuando se configura. Componentes de transferencia/modal aprobados. | Evento Reverb y adopción de aprobación entre dos sesiones autenticadas, incluida auditoría con captura pendiente. |
| Varios archivos, cupo 50 MB | HTTP: varios lotes, idempotencia, rechazo del exceso conservando documentos, nuevo ciclo y retiro con varios adjuntos. Configuración/código: 52 428 800 bytes. | El test reduce el cupo a 4096 bytes para probarlo proporcionalmente; falta carga HTTP/navegador con el cupo real y un exceso de un byte. |

Pruebas de servidor: **8 distintas aprobadas, 95 aserciones**, en dos lotes dirigidos.

React: lote inicial **31 aprobadas y 1 fallida**. La expectativa antigua pedía «Con incidencias»; se actualizó únicamente el test a «Con detalles o faltantes», coherente con el componente y las decisiones vigentes. Se repitió solo ese caso: **1 aprobado, 3 omitidos por filtro**. Resultado acumulado: **32 casos distintos aprobados**; no se repitió la suite completa ni se cambió el texto del producto.

Navegador: **18 capturas de Preparación** y **12 de Auditoría**. Auditoría verifica también calendario, Escape/foco, fecha enviada a transporte ficticio y bloqueo de fecha registrada. Inspección visual directa: inicial 1440 oscuro, respuesta 1024 claro, apartado 390 claro y calendario de auditoría 1440 oscuro/390 claro. Sin regresiones evidentes en esas vistas; no acredita toda la interfaz ni los cinco procesos completos.

## Archivos y evidencia

Cambios funcionales limitados al CSS citado. Se actualizó la expectativa de `resources/js/Pages/ControlPedidos/Partials/ajustesRespuestaTienda.test.jsx`. Se agregaron este informe, tres guiones en `scripts/qa/pr34/` y capturas/reportes/logs en `resources/design-references/pr34-validacion-local/`.

- `verificacion.json`: escenarios y medidas de Preparación.
- `auditoria/verificacion.json`: seis vistas de Auditoría.
- `entorno.json`: navegación real al login y recursos cargados.
- `logs/`: build, pruebas de servidor, React inicial/reintento y scroll con contenido largo.

No se modificaron Clientes, integración Wizerp, credenciales, configuración operativa ni pedidos existentes. La corrección de propietario se limita al directorio de evidencias indicado.

## Reproducción técnica

Desde la raíz del repositorio, con Selenium libre y el build publicado. Ejecutar los guiones de navegador secuencialmente porque comparten Selenium y puertos internos temporales:

```bash
./vendor/bin/sail node scripts/qa/pr34/verificar-entorno.mjs
./vendor/bin/sail node scripts/qa/tienda-recorrido/verificar-scroll-preparacion.mjs
./vendor/bin/sail node scripts/qa/pr34/validar-interfaz.mjs
./vendor/bin/sail node scripts/qa/pr34/validar-auditoria.mjs
```

Servidor, lotes independientes, sin ampliar a una suite general:

```bash
./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-test-config.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes.php laravel.test php artisan test tests/Feature/ControlPedidos/PoliticasOperativasPedidosTest.php tests/Feature/ControlPedidos/TransferenciaTiendaTest.php tests/Feature/ControlPedidos/SolicitudRegistrarTiendaTest.php --filter='mismo_dia_luego_actualizacion|confirmar_transferencia_exige|anexo_avanzado_reintentado|anexo_y_retiro_admiten_varios'
./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-test-config.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes.php laravel.test php artisan test tests/Feature/ControlPedidos/TransferenciaTiendaTest.php tests/Feature/ControlPedidos/PoliticasOperativasPedidosTest.php --filter='editar_productos_antes_de_validar|tras_pago_comprobado_el_cambio|prioridad_antes_de_paginar|tareas_sin_metadata'
./vendor/bin/sail npm run test -- resources/js/Pages/ControlPedidos/Tienda/Partials/ModalPreparacionTienda.test.jsx resources/js/Pages/ControlPedidos/Partials/SeguimientoTransferenciaTienda.test.jsx resources/js/Pages/ControlPedidos/Partials/useActualizacionesPedidos.test.jsx resources/js/Pages/ControlPedidos/Partials/ajustesRespuestaTienda.test.jsx resources/js/Pages/ControlPedidos/Partials/ConfirmacionRevisionTienda.test.jsx
```

## Procedimiento de aceptación operativa pendiente

1. Provisionar una instancia Laravel de QA con base nueva vacía, almacenamiento y cachés exclusivos, correo capturado y colas/eventos aislados. Verificar el destino efectivo antes de migrar o sembrar; no usar `migrate:fresh` ni limpiar el MySQL existente. Mantener conexiones Wizerp externas fuera de los casos ficticios.
2. Crear mediante factories usuarios de Ventas, auxiliar de Tienda, CEDIS y Auditoría con permisos normales y sucursales asignadas. Crear pedidos identificados `QA-PR34-*`, cliente ficticio exclusivamente en esa base, documentos artificiales y banco de prueba. No conceder Super Admin para suplir permisos faltantes. Estos usuarios/pedidos no se crearon en la base existente.
3. En sesiones separadas, abrir consulta nueva, actualización pendiente, respondida y Mismo día. Verificar el orden antes/después de paginar y filtrar. Abrir respuestas extensas, desplazar hasta el final y comprobar que la cabecera/acciones siguen accesibles en 1440, 1024 y 390, claro/oscuro; revisar consola y red.
4. En una transferencia de $100, agregar piezas hasta $150: debe exigir responder/cerrar la consulta y cubrir $50 antes de enviar. Registrar y aprobar con otro actor. Repetir con retiro y excedente, conservando pagos y sin liberar mercancía por un cambio documental.
5. Mantener abierto el pedido en otra sesión; aprobar en Auditoría y comprobar actualización sin recarga manual. Repetir con campos locales sin guardar: la captura debe conservarse y el cambio persistido adoptarse al terminar. Comprobar conexión Reverb y la ruta de respaldo donde esté configurada.
6. Cargar PDF/imágenes artificiales en varios lotes cuya suma sea exactamente 52 428 800 bytes. Agregar un byte más: rechazar sin perder lo guardado. Reintentar un lote y comprobar que no duplica archivos/solicitudes; abrir nueva consulta y verificar su cupo separado. Comprobar también la carga que originó el error de permisos.
7. Conservar capturas, respuestas HTTP, IDs de fixtures y resultado por actor. No declarar recepción, inventario, cobro, remisión ni entrega por aprobar una consulta; cada hecho mantiene su operación propia.

Siguiente paso recomendado: completar ese recorrido en la instancia persistente exclusiva de QA. El servidor permite navegación y pruebas técnicas; la aceptación autenticada entre actores sigue pendiente.
