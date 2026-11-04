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
