# Comprobación visual de Tienda

Monta componentes reales con cliente, pedido, documento y respuestas ficticias. No abre una sesión operativa ni escribe en la base de datos. Complementa las pruebas de servidor; no acredita un recorrido autenticado entre usuarios.

Vistas: solicitud, importes, revisión inicial, espera de Ventas, anexo, retiro, entrega, CEDIS y auditoría. Se revisan a 1440×1000 y 390×844, en claro y oscuro. Claro incluye una fuente y un acento personalizados.

## Ejecutar

Con los contenedores de aplicación y Selenium activos, iniciar Vite de QA:

```bash
docker exec -d gelianv-laravel.test-1 sh -c 'node node_modules/vite/bin/vite.js --config scripts/qa/tienda-recorrido/vite.config.mjs --host 0.0.0.0 --port 5186 > /tmp/tienda-vite.log 2>&1'
```

El navegador de Selenium debe resolver `laravel.test` al contenedor de aplicación. La configuración permite expresamente ese host y usa su propio archivo temporal de Vite; no sustituye `public/hot`. No usar `allowedHosts: true` para evitar un error de configuración.

El verificador necesita una instalación existente de `playwright-core` dentro del contenedor, indicada mediante `PLAYWRIGHT_MODULE` (ruta absoluta a `index.mjs`). La ejecución de esta revisión utilizó una copia temporal del paquete disponible en el entorno, `/tmp/qa-playwright-core/index.mjs`, y el Chrome del contenedor Selenium; no añadió dependencias al proyecto.

```bash
docker exec gelianv-laravel.test-1 node scripts/qa/tienda-recorrido/verificar.mjs
```

El verificador utiliza una sesión de Selenium disponible o crea una; debe ejecutarse sin otra automatización concurrente en ese Selenium. La cierra al finalizar. Rechaza páginas de host bloqueado, errores de JavaScript, contenido incorrecto, imágenes rotas, desbordamiento horizontal de la página/modal y controles de ajustes que salgan de su panel. Genera primero en una carpeta temporal y solo publica las 36 capturas cuando todas pasan.

Salida: `resources/design-references/tienda-recorrido/`, con informe `verificacion.json`. Abrir las imágenes para revisar composición y jerarquía además de los controles automáticos. Los formularios largos conservan su desplazamiento vertical: una captura no muestra todos sus campos simultáneamente.

## Selección de modales y consulta del flujo actual

`verificar-modales.mjs` usa el mismo Vite de QA y una sesión propia de Selenium (se detiene si está ocupado). Ejecutar con `docker exec gelianv-laravel.test-1 node scripts/qa/tienda-recorrido/verificar-modales.mjs`. Revisa edición de un borrador sin modalidad y detalles en Registro, CEDIS y Auditoría, a 1440×1000 y 390×844, claro personalizado y oscuro. Son 16 capturas en `/tmp/gelia-modales-qa`, con informe, sin sobrescribir las referencias anteriores. Comprueba contenido real, errores de ejecución, imágenes y desbordamiento de paneles. Para la respuesta compartida, comprueba que no se ofrezca Cerrar consulta. No acredita guardado autenticado ni operación entre usuarios.

`verificar-estados.mjs` comprueba la tabla real (`vista=estados-consulta`) con siete pedidos ficticios: pendientes/respondidos de Tienda y CEDIS, apartado confirmado, pesaje nacional pendiente y respondido. Usa el mismo Vite/Selenium y genera cuatro capturas con informe en `/tmp/gelia-estados-qa`. Valida etiquetas legibles, ausencia del código `apartado_confirmado`, ausencia de la separación pendiente en consultas, errores de ejecución y desbordamiento horizontal. No guarda ni modifica pedidos.

`verificar-t01.mjs` comprueba el T-01 documental: importes sin cargos de transporte, Pago al recoger sin comprobante, separación del conjunto sin captura individual y entrega con remisión/foto/ticket opcional. Son 12 capturas (tres vistas, dos tamaños y dos temas), con contador y acento/fuente personalizados en claro, generadas en `/tmp/gelia-t01-qa`. Las referencias de la revisión del 4 de noviembre se conservan en `resources/design-references/tienda-t01/`. Se rechazan errores de ejecución, contenido inválido y desbordamientos del modal o paneles. La demostración fija el reloj al 4 de noviembre a las 10:00 de México y el vencimiento a las 17:00; los cortes reales se verifican en servidor. No registra cobros ni entrega pedidos operativos.

`verificar-configuracion-operativa.mjs` utiliza el build publicado y Selenium, sin iniciar Vite. Monta Configuración operativa con datos ficticios en una respuesta inicial interceptada, evita guardar cambios y comprueba escritorio/móvil y ambos temas. Genera cuatro capturas en `/tmp/gelia-configuracion-operativa-qa` dentro del contenedor. Las referencias se conservan en `resources/design-references/configuracion-operativa/`. No acredita un guardado autenticado ni prueba notificaciones, audio o conexiones en tiempo real. Ejecutar con `./vendor/bin/sail node scripts/qa/tienda-recorrido/verificar-configuracion-operativa.mjs`.

`verificar-respuesta-ajustes.mjs` monta SeguimientoSolicitudTienda desde el build publicado con datos ficticios. Comprueba Sin existencias sin atributos mezclados, miniatura del archivo local y visor modal en escritorio oscuro/móvil claro. Genera cuatro capturas y un informe en `/tmp/gelia-respuesta-ajustes-qa` del contenedor. No inicia sesión ni guarda pedidos. Ejecutar con Sail; no requiere Vite.

`verificar-confirmacion-tienda.mjs` comprueba guardar Pago al recoger y enviar confirmación con el componente real y respuestas interceptadas. Fija el reloj a las 10:00 de México, sin iniciar sesión ni escribir datos operativos. Captura modalidad guardada y confirmación enviada en escritorio oscuro y móvil claro desde el build publicado. Las referencias están en `resources/design-references/tienda-confirmacion`.

`verificar-cedis-disponibilidad.mjs` monta el modal real de disponibilidad de CEDIS desde el build publicado. Comprueba captura de excepción, miniatura, ampliación de evidencia/pedido y acceso a Enviar respuesta en móvil. Genera cuatro capturas en `/tmp/gelia-cedis-disponibilidad-qa` del contenedor; referencias en `resources/design-references/cedis-disponibilidad`. Usa datos ficticios y no envía respuestas operativas. No requiere Vite.

`verificar-cedis-captura.mjs` comprueba la revisión vigente del 6 de noviembre usando el build publicado y Selenium, sin Playwright ni Vite. Monta el modal de CEDIS con catálogo y transporte ficticios en el puerto temporal interno 9192. Recorre contexto, catálogo opaco, campo único nombre/SKU/código, lecturas repetidas como unidades, disparador externo de cámara, hasta cinco fotos, atributos, faltante, retiro rápido, fichas contraídas, visor y acceso al pie. Tamaños 1440×1000, 544×932 y 390×844 claro/oscuro; 24 capturas y reporte en `/tmp/gelia-cedis-unidades-qa`, conservados en `resources/design-references/cedis-unidades-2026-11-06/`. Las capturas de `cedis-captura-2026-11-06` corresponden a la revisión anterior. Ejecutar `./vendor/bin/sail node scripts/qa/tienda-recorrido/verificar-cedis-captura.mjs`. No escribe pedidos ni prueba la cámara física del teléfono.

Para revisar solo la opacidad del catálogo: `./vendor/bin/sail exec -e QA_SOLO_CATALOGO=1 laravel.test node scripts/qa/tienda-recorrido/verificar-cedis-captura.mjs`. Activa cristal en los cuatro tamaños/temas, exige alfa 255/255 y sin blur, y guarda ocho capturas más reporte en `/tmp/gelia-cedis-catalogo-cristal-qa`. Evidencia conservada en `resources/design-references/cedis-catalogo-cristal-2026-11-06/`. El recorrido completo del mismo script también activa cristal y comprueba el alfa.

`./vendor/bin/sail exec -e QA_SOLO_ATRIBUTOS=1 laravel.test node scripts/qa/tienda-recorrido/verificar-cedis-captura.mjs` reproduce a 544 px oscuro y 390 px claro el envío de una unidad con ambos atributos y fotos, sin observaciones escritas y con fila contraída. Es un envío simulado del formulario; las pruebas HTTP aisladas acreditan persistencia. Seis capturas e informe en `/tmp/gelia-cedis-atributos-envio-qa`, conservados en `resources/design-references/cedis-atributos-envio-2026-11-06/`.

`verificar-respuestas-retroalimentacion.mjs` monta el **FormularioRegistrarTienda completo** desde el build publicado con datos/transporte ficticios y cristal activo. Revisa respuesta de CEDIS, menú nativo clicable, sustitución, cuatro variantes de ajuste, ruta/destino guardado y acción sobre la segunda unidad de un SKU repetido; también respuesta de Tienda sin ruta de CEDIS. CEDIS a 1440×1000, 544×932 y 390×844; Tienda a escritorio oscuro y móvil claro. Son 29 capturas y reporte en `/tmp/gelia-respuestas-retroalimentacion-qa`, conservados en `resources/design-references/tienda-respuestas-2026-11-06/`. Ejecutar `./vendor/bin/sail node scripts/qa/tienda-recorrido/verificar-respuestas-retroalimentacion.mjs`. Usa Selenium y servidor temporal 9192, sin Vite ni Playwright. Aísla solicitudes HTTP; no escribe pedidos ni acredita notificaciones reales.

Acción principal por etapa: `./vendor/bin/sail node scripts/qa/tienda-recorrido/verificar-acciones-etapa.mjs`. Usa el formulario del build con datos ficticios, servidor temporal interno en 9192 y Selenium; comprueba seis etapas y la transición a revisión a 1311×932 oscuro y 390×844 claro. Resultados en `/tmp/gelia-acciones-etapa-qa` dentro del contenedor. No envía pedidos reales. Evidencia conservada en `resources/design-references/tienda-acciones-etapa-2026-11-06`.

`./vendor/bin/sail node scripts/qa/tienda-recorrido/verificar-actualizacion-consulta.mjs` comprueba respuesta por conjunto tras cambios en CEDIS y Tienda, sin confirmación ni identificación individual del conjunto correcto, y captura actual con historial anterior. Monta los componentes del build con datos ficticios y Selenium a 1440×1000 oscuro y 390×844 claro. Seis capturas e informe en `/tmp/gelia-actualizacion-consulta-qa`, conservadas en `resources/design-references/consulta-actualizada-2026-11-06/`. No inicia sesión ni escribe pedidos; no acredita recepción real de alertas.

Revisión posterior del mismo guion: el recorrido actual de `verificar-actualizacion-consulta.mjs` no utiliza el selector general de estado. Comprueba capturas de anexos/retiros, Todo OK desde el visor, SKU precargado con falta de existencias y separación roja/verde de una sustitución. Agrega ancho 475×932 oscuro; salida actual en `/tmp/gelia-consulta-capturas-qa`, evidencia en `resources/design-references/consulta-capturas-2026-11-06/`. Las referencias anteriores se conservan como historial.

`./vendor/bin/sail node scripts/qa/tienda-recorrido/verificar-detalle-rapido.mjs` verifica el modal flotante **Registrar detalle**, catálogo/manual, dos piezas seleccionadas e incorporación al modal principal en CEDIS y Tienda. Usa build publicado, transporte ficticio y Selenium a 1440×1000 oscuro, 475×932 oscuro y 390×844 claro. Conserva 24 capturas y reporte en `/tmp/gelia-detalle-rapido-qa`; referencias en `resources/design-references/detalle-rapido-2026-11-06/`. Comprueba también que la cámara se abra sobre el selector rápido a 475 px; no acredita lectura con cámara física. No inicia sesión ni escribe pedidos.

`./vendor/bin/sail node scripts/qa/tienda-recorrido/verificar-tarjetas-cedis.mjs` monta la página CEDIS completa del build con cuatro pedidos ficticios, perfiles CEDIS/gerencia y cristal activo. Comprueba tipo centrado, folio/cliente/ruta, avisos de consulta, ausencia de Piezas extra y Bitácora por permiso, sin errores JS ni desbordamiento. A 1440×1000 oscuro, 475×932 oscuro y 390×844 claro. Captura 24 tarjetas completas mediante CDP (evita recortes por desplazamiento automático); salida `/tmp/gelia-tarjetas-cedis-qa`, referencias `resources/design-references/tarjetas-cedis-2026-11-06/`. No guarda pedidos ni acredita sesión autenticada; los permisos y la exclusión de historial se prueban en servidor.
