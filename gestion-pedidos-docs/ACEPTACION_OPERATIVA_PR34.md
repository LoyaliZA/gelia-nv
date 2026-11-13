# PR #34 — aceptación operativa en MySQL aislado

12 de noviembre de 2026, hora de México. Rama `develop`, HEAD `7c32f05a`. Resultado aplicable al árbol de trabajo local, incluida la corrección documental descrita abajo.

**Dictamen: Tienda y CEDIS permiten continuar con Envíos en el entorno de QA para los cinco ajustes evaluados.** Los recorridos de consulta, transferencia tras anexo, actualización real por Reverb, prioridad/paginación y archivos pasaron con sesiones autenticadas, MySQL y almacenamiento persistente. Se encontró y corrigió un 403 de documentos del auxiliar asignado. La corrección sigue sin commit ni publicación; no debe atribuirse ese arreglo al PR publicado sin incorporarlo antes.

No hubo commit, push, merge ni despliegue a producción. No se modificaron Clientes ni la integración Wizerp. El cliente y la remisión usados son fixtures de la base exclusiva de QA.

## Instancia preparada y aislamiento

- Proyecto Compose **`gelia-pr34-qa`**, separado de `gelianv`, definido en `compose.pr34-qa.yaml`.
- MySQL 8.4 exclusivo: base/usuario `gelia_pr34_qa`, volumen `gelia-pr34-qa_mysql-qa`. Se aplicaron **451 migraciones existentes** sobre esa base nueva; sin `migrate:fresh`, borrado de volúmenes ni limpieza de la base existente.
- Laravel y Reverb propios, más un worker con cola `database` y Selenium/Chromium con dos sesiones simultáneas. Las primeras pruebas de Reverb usaron `sync`; la comprobación final también pasó con worker asíncrono real. Cola final: **0 pendientes, 0 fallidos**.
- Cuatro cuentas: `ventas@pr34.example.test`, `auxiliar@pr34.example.test`, `tienda@pr34.example.test`, `cedis@pr34.example.test`. Roles organizacionales y permisos individuales limitados a cada operación; **sin Super Admin**. Departamento y sucursal ficticios, piloto de preparación habilitado solo dentro de QA.
- **57 pedidos ficticios** al cierre, incluidos los conjuntos de paginación y fixtures de diagnóstico; no significa que se hayan aceptado 57 recorridos completos.
- Claves nuevas de Laravel, MySQL, Reverb y acceso de usuarios en `.env.qa-pr34`, modo `600`, ignorado por Git. No se copiaron claves de la instancia existente. La configuración definitiva no carga su `.env`.
- Código montado de lectura; almacenamiento, cachés, assets compilados y temporales de Vite independientes en `storage/pr34-qa/`, también ignorado. Laravel, build y escrituras de evidencias usan UID **1000**.
- Red interna para Laravel/MySQL/Reverb/worker. Solo Selenium tiene una segunda red con salida para descargar las fuentes y PDF.js auténticos. Laravel no dispone de claves ni salida a Wizerp/servicios operativos externos. Correo `array`, webpush apagado.
- Navegación interna: `http://qa-pr34`; WebSocket: `reverb-pr34:8080`. Son nombres de la red Docker. Las pruebas se ejecutan dentro de esta red; no se publicó una URL pública de QA. Los puertos loopback declarados no sustituyen una comprobación de acceso desde otro equipo.
- Un reinicio del servidor ocurrió durante el trabajo. MySQL, documentos, credenciales y resultados persistieron; los servicios volvieron a iniciar. Se comprobó después el tamaño real de los archivos. Los logs nuevos se guardan en almacenamiento persistente, porque `/tmp` se perdió con el reinicio.

### Incidencia de arranque del entorno

El primer arranque de Compose tomó el nombre del proyecto existente y recreó temporalmente sus contenedores de Laravel, MySQL y Reverb con la configuración de QA. Se detectó **antes de migrar o sembrar**. Se restauraron inmediatamente los servicios, puertos y el volumen original `gelianv_sail-mysql`; el login existente volvió a HTTP 200. No se ejecutaron migraciones ni fixtures sobre esa base.

Se corrigió el lanzador: exporta la configuración exclusiva, fija `COMPOSE_ENV_FILES` y valida proyecto, archivo Compose y nombre de base antes de invocar Sail. El volumen auxiliar creado en aquel intento no se eliminó, respetando la restricción de no borrar volúmenes. No contiene datos operativos importados. Esta incidencia de infraestructura se conserva como antecedente; no es un fallo del flujo de pedidos.

## Escenarios y resultados

| Recorrido | Resultado y evidencia |
| --- | --- |
| A. Consulta y Preparación Tienda | Ventas creó la consulta; Tienda la tomó y respondió desde el formulario real. Se solicitó un anexo, Tienda respondió la actualización con foto y Ventas cerró la consulta; el cierre y los documentos se leyeron nuevamente de MySQL. Pedido inicial **1**. Una segunda fixture visual válida, pedido **55**, tarea **25**, probó selección y persistencia de **8 fotografías**, respuesta, cierre, galería y visor PDF real. Scroll y acciones inferiores comprobados a **1440, 1024 y 390 px**, sin desbordamiento ni scroll anidado en las medidas registradas. |
| B. Transferencia tras anexo | Pedido **2**: consulta CEDIS respondida → cierre → transferencia $100 → anexo autorizado/documentado y respuesta → importe $150 → envío rechazado por diferencia de **$50** → transferencia complementaria → envío a Auxiliar → ingreso aprobado. El cambio informal posterior a $200 devolvió **422**, conservando importe $150, pagos y evidencia. Remisión ficticia registrada por su operación normal, aprobación del pedido y preparación explícita de **una bolsa** por CEDIS. Estado final **`preparada`**, sin registrar salida, recepción ni entrega. |
| C. Reverb entre sesiones | Ventas mantuvo abierto el seguimiento y Auxiliar aprobó desde otra sesión. Hubo frame WebSocket **`pedido.actualizado`**, suscripción privada autenticada, mismo socket y misma navegación. El seguimiento reflejó **Ingreso verificado por auxiliar**. Primera medición: ~**740 ms** desde evento recibido hasta lectura actualizada. Comprobación adicional con **cola database y worker**: ~**390 ms**, un frame real observado y pago validado nuevamente leído de MySQL. No se usó recarga manual ni evento simulado. |
| C. Captura sin guardar | En otra pareja de sesiones de Auxiliar se escribió un número de remisión sin guardar. Llegó el evento de aprobación y el valor quedó intacto; no se volvió a solicitar el detalle de Auditoría durante la captura. Al vaciar el cambio local se ejecutó la lectura pendiente. El formulario de seguimiento puede realizar su propia consulta; eso no sobrescribió la remisión. Pedido **31**, registro `realtime-captura.json`. |
| D. Jerarquía | **23 pedidos por bandeja**, con conjuntos separados para Tienda y CEDIS: Mismo día → actualización pendiente → nueva consulta → municipales y nacionales según configuración. Páginas de **15 + 8**, sin duplicados ni omisiones; volver a página 1 devolvió el mismo orden. Se respetó la exclusión normal de instrucciones activas de Tienda en CEDIS; no se mezcló artificialmente el mismo pedido en ambas bandejas. Capturas de ambas a los tres anchos. |
| E. Selección y lotes desde React | Desde el formulario real de Ventas se seleccionaron **dos PDF de 6 MiB**. El frontend envió **dos lotes** con el mismo `solicitud_id`; persistieron dos documentos en una sola solicitud. Pedido **5**, **12 582 912 bytes**. Los interceptores de QA solo registraron las peticiones; no las sustituyeron ni simularon su respuesta. |
| E. Cupo acumulado real | Pedidos **3 y 4**: archivo previo de 1 MiB + nuevos lotes de 8 MiB. Se verificaron acumulados de **49 MiB**, exactamente **52 428 800 bytes (50 MiB)** y **52 428 801 bytes**, rechazando el último con 422 y conservando lo ya guardado. Corregir el lote fallido permitió llegar al límite exacto. Un reintento no duplicó documentos. Agregar otro archivo a una consulta llena fue rechazado sin alterar los IDs anteriores. |
| E. Cupo en la galería | La interfaz sumó los **50 MiB previamente guardados** y mostró el rechazo de otro archivo **antes de enviarlo**. Contador de POST de anexo: **0**. Las ocho evidencias previas y su suma permanecieron intactas. |
| Permisos y almacenamiento | Los archivos de los pedidos 3/4/5 tienen tamaño físico igual al metadato, SHA-256 registrado y propietario **UID 1000**. La lectura autenticada autorizada devolvió 200; documento ajeno y expediente general no autorizado, 403. La creación de subdirectorios ocurrió durante las cargas reales, sin `chmod 777`, cambios recursivos generales ni usuario root para el proceso HTTP. |

Las aprobaciones, respuestas, importes, anexos y archivos se escribieron por endpoints reales, con sesiones y CSRF. Las fixtures iniciales y conjuntos de prioridad se crearon con modelos/servicios reales dentro de QA. No se sustituyó la aplicación por maquetas, props de demostración ni transporte ficticio.

## Error demostrado y corrección limitada

**403 al leer evidencias de una consulta asignada a Tienda.** El modal utilizaba URLs autenticadas del pedido o de preparación; la autorización general no reconocía al auxiliar que atendía la instrucción vigente. Con cuentas con privilegios amplios el defecto podía quedar oculto.

Se agregó una autorización exclusiva para lectura documental: mantiene los accesos generales existentes y admite los documentos de consulta/preparación para quien tiene `control_pedidos.tienda.ver` y está asignado a la instrucción **vigente** de área **TIENDA**. La excepción no permite comprobantes financieros ni abre el expediente general, la bitácora o acciones de modificación. Continúan las comprobaciones de pertenencia documento/pedido/tarea y los tipos permitidos por cada endpoint.

Archivos funcionales corregidos:

- `app/Support/ControlPedidos/VisibilidadPedidoBma.php`.
- `app/Http/Controllers/ControlPedidos/PedidoBmaController.php`, solo descarga documental.
- `app/Http/Controllers/ControlPedidos/DocumentoPreparacionController.php`.

Regresión adicional en `tests/Feature/ControlPedidos/PreparacionTiendaWorkspaceTest.php`: **1 caso aprobado, 14 aserciones**, con SQLite en memoria y cachés aisladas. Cubre ambos endpoints, acceso del asignado, rechazo de ajeno, instrucción sustituida, expediente general y comprobante financiero. Sintaxis PHP de los tres archivos y `git diff --check` aprobados. No se ejecutó una suite general ni se recompiló después del ajuste de backend.

## Evidencia auténtica y límites de comprobación

Directorio versionable: [`resources/design-references/pr34-aceptacion-operativa/`](../resources/design-references/pr34-aceptacion-operativa/): **25 capturas** y reportes JSON; se agregan la instantánea final del entorno y los logs dirigidos. Los reportes copiados excluyen claves, tokens de sesión y HTML de redirecciones.

- [Consulta y medidas](../resources/design-references/pr34-aceptacion-operativa/visual.json), [cierre actualizado](../resources/design-references/pr34-aceptacion-operativa/consulta-finalizacion.json).
- [Transferencia](../resources/design-references/pr34-aceptacion-operativa/transferencia.json), [preparación persistida](../resources/design-references/pr34-aceptacion-operativa/preparacion-transferencia-final.json).
- [Reverb](../resources/design-references/pr34-aceptacion-operativa/realtime.json), [worker asíncrono](../resources/design-references/pr34-aceptacion-operativa/realtime-asincrono.json), [captura conservada](../resources/design-references/pr34-aceptacion-operativa/realtime-captura.json).
- [Jerarquía](../resources/design-references/pr34-aceptacion-operativa/jerarquia.json).
- [Cupo/reintentos](../resources/design-references/pr34-aceptacion-operativa/archivos-resultados.json), [lotes del frontend](../resources/design-references/pr34-aceptacion-operativa/archivos-ui.json), [rechazo visual](../resources/design-references/pr34-aceptacion-operativa/archivos-cupo-ui.json), [integridad física](../resources/design-references/pr34-aceptacion-operativa/integridad-archivos.json).
- [Visor PDF y lectura final](../resources/design-references/pr34-aceptacion-operativa/revision-final.json).

Inspección visual directa: consulta inicial móvil con pie accesible y ocho fotos; respuesta en 1024; formulario de Ventas en 1440; seguimiento después del evento; bandeja CEDIS móvil; selección múltiple enfocada y visor PDF móvil. No se observaron regresiones evidentes en esas vistas. Las capturas operativas nuevas usan tema claro; las variantes claro/oscuro del CSS siguen acreditadas únicamente por la revisión técnica anterior, no por un nuevo recorrido completo de negocio en ambos temas.

La consola final no mostró excepciones JavaScript del recorrido. Los 403 provocados expresamente para probar denegaciones son esperados. Chromium emitió una advertencia de autoplay para un tono antes de interacción confiable: no se acredita aceptación de sonido/notificación física. Las fuentes y PDF.js pudieron cargarse al dar salida únicamente al navegador; el visor final renderizó un canvas del PDF real.

Los primeros intentos de automatización tuvieron selectores equivocados, una base de captura anterior a la relectura de MySQL, permisos de roles usados como si fueran facultades individuales, y dos PNG de fixture con codificación inválida. Se corrigieron los guiones/fixtures de aceptación, sin cambiar esas reglas de producto. Para la evidencia visual final se creó otra consulta con ocho PNG válidos y un PDF con tabla xref; los documentos anteriores de QA no se sobrescribieron. Una fixture asíncrona se copió inicialmente de un pedido ya preparado y no era auditable; se conservó y se creó una nueva pendiente de Auditoría. Esos intentos no se contabilizan como fallos funcionales ni como escenarios aprobados.

## Archivos locales sin commit

Ya existían al iniciar esta aceptación:

- `resources/css/gelia/features/control-pedidos-gestion-bma.css`: retiro de tres marcadores, sin cambios adicionales en esta fase.
- `resources/js/Pages/ControlPedidos/Partials/ajustesRespuestaTienda.test.jsx`: expectativa de terminología vigente, conservada.
- `gestion-pedidos-docs/VALIDACION_LOCAL_PR34.md`, `resources/design-references/pr34-validacion-local/` y guiones de revisión técnica en `scripts/qa/pr34/`.

Agregados/modificados en esta aceptación:

- Los tres archivos PHP funcionales y la prueba de regresión indicados arriba.
- `.gitignore`: exclusivamente `.env.qa-pr34` y `storage/pr34-qa/` y el salto final de línea.
- `compose.pr34-qa.yaml`, `scripts/qa/pr34/sail-qa`, `scripts/qa/pr34/preparar-entorno.py` y guiones de `scripts/qa/pr34/aceptacion/`.
- Este informe, enlace de continuidad en el informe anterior y `resources/design-references/pr34-aceptacion-operativa/`.

`.env.qa-pr34`, datos, almacenamiento, cachés y build de QA permanecen ignorados. No llevarlos a Git. Los cambios locales anteriores se conservaron; no hubo reset, restauración indiscriminada ni cambios de rama.

## Continuación y reproducción

Para inspeccionar la instancia que quedó persistente:

```bash
scripts/qa/pr34/sail-qa ps
scripts/qa/pr34/sail-qa logs --tail=30 queue reverb
```

Para preparar una instancia **nueva y vacía**, con las mismas imágenes locales disponibles:

```bash
python3 scripts/qa/pr34/preparar-entorno.py
scripts/qa/pr34/sail-qa up -d mysql laravel.test reverb
scripts/qa/pr34/sail-qa artisan migrate --force
scripts/qa/pr34/sail-qa npm run build
scripts/qa/pr34/sail-qa up -d queue selenium
scripts/qa/pr34/sail-qa php scripts/qa/pr34/aceptacion/seed.php
```

El preparador no sobrescribe claves existentes. El seeder valida entorno y nombre de base, y conserva las fixtures ya registradas. Las contraseñas solo se consultan en el archivo local; no se imprimen en el informe.

Guiones por recorrido: `consulta*.mjs` para la primera consulta/actualización, `transferencia.mjs` y `preparacion-transferencia.mjs`, `realtime*.mjs`/`captura.mjs`, `jerarquia.mjs`, `archivos.mjs`, `ui-archivos.mjs`, `cupo-ui.mjs`, `visual.mjs` y `revision-final.mjs`. Los preparadores adicionales guardan IDs en `storage/app/qa-pr34-*.json`. **Son recorridos con escritura y etapas específicas**: no ejecutar todos otra vez sobre pedidos ya avanzados ni reiniciar estados para forzar su aprobación. Revisar primero el reporte y los IDs, o crear fixtures nuevas. Las cuentas y pedidos quedan disponibles para continuar el trabajo en QA.

Regresión documental reproducible sin tocar MySQL:

```bash
./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-test-config.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes.php laravel.test php artisan test tests/Feature/ControlPedidos/PreparacionTiendaWorkspaceTest.php --filter=auxiliar_asignado_lee_documentos
```

## Riesgos y siguiente paso

No quedan bloqueos reproducidos en los cinco ajustes después de la corrección local. La aceptación no acredita producción, carga concurrente sostenida, dispositivos/cámara/escáner físicos, pagos bancarios reales, Wizerp externo, ni salida/recepción/entrega físicas; no formaron parte de los hechos ejecutados. Tampoco se realizó una auditoría general de permisos o una prueba de todos los procesos legados.

El build de QA conserva los avisos ya conocidos de Node y tamaño de chunks; no se reabrió la limpieza global de Stylelint. La redacción «50 MB» de la interfaz corresponde al límite binario comprobado de **50 MiB**.

**Siguiente paso: continuar con Envíos utilizando esta instancia y mantener como requisito la corrección documental local al preparar la integración autorizada.** La revisión/commit/publicación de esos archivos queda pendiente de instrucción del usuario; no es necesario reiniciar los recorridos ya acreditados para comenzar el análisis de Envíos.
