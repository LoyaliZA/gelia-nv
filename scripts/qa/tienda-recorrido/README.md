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
