# Vista aislada Q8.1

Desde la raíz del proyecto, con Sail activo y el puerto 5179 libre:

```bash
./vendor/bin/sail npm run dev -- --config scripts/qa/pedidos-q81/vite.config.mjs
```

Abrir `http://localhost:5179/scripts/qa/pedidos-q81/preview.html?vista=tienda`.
Cambiar `vista` a `ventas`, `auxiliar` o `cedis`; añadir `&tema=oscuro` para ese tema.

Renderiza los componentes reales y el CSS actual. Usa datos ficticios. Axios y fetch están simulados únicamente en esta página; no guarda en la base ni autentica usuarios. Los botones de detalle/respuesta en las tarjetas anuncian la simulación. La carga de fotos permite ver un archivo local, pero el guardado simulado vuelve al fixture inicial: no acredita persistencia. La persistencia real y permisos se comprueban en las pruebas de servidor y DOM.

La configuración usa Vite de desarrollo sin el plugin Laravel y sin escribir `public/hot`. No cambia los recursos que sirve la aplicación en el puerto 9080. Detener el proceso al terminar. No usar esta página como un recorrido autenticado ni como aplicación operativa.

Capturas y resultados: `evidencia/q8-1/`. Los viewports de la confirmación final son exactos mediante emulación de Chromium: 1366×900, 390×844, 360×800; altura reducida de 480 px. No es un dispositivo físico ni un teclado nativo.
