# Q8.1 — Interfaz y verificación de pedidos

Fecha: 15 de octubre de 2026, America/Mexico_City.

## Entrega y alcance

Se implementaron los ajustes en el código del módulo, conservando el trabajo local anterior de Q8 y los contratos Q5.1/Q5.2/Q6/Q6.1. No hubo commit, push, despliegue, compilación de producción, cambios de autenticación, permisos, datos reales ni migraciones ejecutadas por este encargo.

Rama inicial y final: `main`. Commit: `83a5ac1aad034c13be82c7d424d7b0cb73c764f3`.
El árbol inicial tenía 18 archivos modificados y 6 sin seguimiento de Q8. Se guardó una copia inicial en `/tmp/q81-before/` para separar el delta de este trabajo. La reversión durable está en `evidencia/q8-1/cambio-codigo-q81.patch`; no depende de restaurar HEAD.

**Q8 no se declara cerrado operativamente:** falta el recorrido autenticado completo, el uso de los recursos Q8.1 por el procedimiento del entorno y la revisión de negocio.

## Implementado por pantalla

### Ventas

- Cabecera progresiva compacta, con folio, cliente, estado y guardado; en escritorio distribuye la información horizontalmente. El nombre completo puede envolver y no se corta de forma permanente.
- Pie de acciones con menos relleno y acciones frecuentes de enviar/guardar. Guardar es principal cuando no corresponde enviar.
- «Limpiar» queda en «Opciones de captura», dentro del contenido desplazable. Se deshabilita mientras se guarda. No cambia la recuperación, autoguardado, concurrencia o payload.
- Se mantienen las fases, navegación, validaciones, comprobantes, remisión posterior, crédito y SAF. No se añaden campos comerciales.

### Auxiliar administrativo

- Cabecera más compacta; folio comercial, cliente y estados siguen visibles.
- Folio interno GELIA identificado como generado por el sistema, número de cliente, origen y fecha/responsable de validación quedan en «Datos de revisión».
- Liberar resguardo y reportar error se agrupan en «Más acciones». Aprobar conserva su botón, condición, permiso, confirmación y explicación cuando faltan pago validado o remisión.
- Los paneles de cobertura y comprobantes mantienen su distribución de escritorio; móvil conserva la navegación y el desplazamiento del contenido.

### Preparación Tienda

- Cabecera exterior con folio/estado/cierre más compacta. Contexto interior resumido a operación, almacén y cliente.
- Vendedora, abastecimiento y reporte de problema se encuentran en «Datos y opciones de preparación». Los avisos de dependencia y abastecimiento pendiente siguen visibles en la captura.
- Se conserva escaneo continuo, búsqueda, cantidades, condición, fotos, documentos de municipio, guardado parcial, confirmación y mensajes de validación.
- Pedido de referencia visible inicialmente en escritorio y contraído inicialmente en móvil. «Ver pedido» abre y enfoca la referencia en móvil; «Volver a productos» regresa sin perder cantidades.
- **Móvil:** contexto, navegación, productos, evidencias, cierre y pie forman un solo recorrido de desplazamiento dentro del modal. El pie no ocupa permanentemente el área de trabajo. Las dos acciones frecuentes y el estado de guardado siguen al final, con botones de al menos 44 px.
- **Escritorio:** referencia y captura conservan paneles separados, centro flexible y pie compacto con estado/acciones en una franja cuando el ancho lo permite.

### CEDIS, folios y badges

- Cabecera legible «Tipo de pedido» con el nombre real de `origen`; el traslado interno se indica aparte. No se infiere el tipo a partir del almacén o destino.
- Se separan almacén de salida, sucursal destino, modalidad de preparación si viene en el pedido, bultos y logística externa. No se muestran códigos de modalidad como etiquetas.
- Cliente con mayor jerarquía; folio del pedido y folio interno GELIA diferenciados. El componente compartido identifica este último como generado por el sistema.
- Se conservan departamento/vendedora, nota de compra, empaque, complementos, avisos, documentos, detalle y bitácora, así como las acciones correspondientes a cada estado.
- Corrección confirmada: la tarjeta podía decir «Pendiente de pesaje CEDIS» en un recorrido de consulta de mercancía. Ahora el badge utiliza la misma clasificación efectiva de mercancía/pesaje que la respuesta; no se cambia la regla de servidor.
- La actualización pendiente explica que empaque y salida están bloqueados y no habilita esas acciones.
- «Única pieza» y «Mejor ejemplar» son badges con texto explícito, borde, superficie y texto semánticos, espacio propio y controles táctiles al editar. No se activan por defecto. La actualización conserva sus valores; se comprobó también en servidor.

## Defecto adicional confirmado de Q8

`SeccionRevisionFisicaPedido`, compartida por Ventas/Auxiliar, consumía todas las revisiones, incluidas las que el servidor conserva con `retirada_at`. Podían contarse y mostrarse como actuales.

Se separaron las filas actuales del «Historial de piezas retiradas», con fecha, comentario y acceso a la evidencia conservada. La relación del servidor mantiene el historial; no se borran filas ni archivos. El detalle CEDIS ya separaba ambas listas desde Q8. Se añadió una prueba que verifica el conteo actual y la apertura de la foto histórica.

## Entorno y esquema efectivos

Comprobación de solo lectura en Sail, tanto con configuración efectiva cacheada como con configuración temporal sin caché:

| Elemento | Resultado observado |
|---|---|
| Aplicación | Laravel en Sail, entorno `local` |
| URL configurada | `http://100.75.11.59:9080` |
| Acceso local | `http://localhost:9080`, redirige a `/login` sin sesión |
| Conexión | MySQL, host `mysql`, base `laravel` |
| Cachés existentes | Configuración y rutas cacheadas; no se regeneraron ni borraron |
| Ruta de abastecimiento | Presente en las rutas efectivas |
| Q6 cantidades | Migración aplicada, batch 171 |
| Q6.1 abastecimiento | Migración aplicada, batch 172; `tarea_receptora_id`, `proposito`, `traspaso_operaciones` presentes |
| Q8 retirada | `retirada_at` presente, migración aplicada, batch 172 |
| Q8 reintentos | `idempotency_key` presente, migración aplicada, batch 172 |
| Migraciones pendientes | Ninguna reportada por `migrate:status` en esta conexión |
| Pruebas PHP | SQLite `:memory:`, `RefreshDatabase`, entorno `testing`, medios simulados |

Esto **difiere del checkpoint Q8 anterior**, que describía esas migraciones pendientes. No se aplicaron aquí. La presencia del esquema permite las funciones; no acredita por sí sola un recorrido operativo completo.

La preparación básica y el aviso cuando falta abastecimiento siguen cubiertos por Q8. El abastecimiento completo se verifica en las pruebas Q6.1 aisladas. No se eliminaron las barreras existentes de Q8 para una actualización sin `retirada_at`, ni se promete idempotencia en una base sin `idempotency_key`.

## Recursos y navegador

- Al iniciar no existía `public/hot`; la aplicación servía `public/build/manifest.json`, con fecha local aproximada 16:18 del 15 de octubre.
- Se inspeccionaron las entradas **activas del manifest**, no solo archivos viejos retenidos: el bundle de preparación contiene `abastecimiento_aviso` y los de respuesta/detalle CEDIS contienen `retirada_at`. Es evidencia de esos cambios de Q8, no garantía de equivalencia de cada línea del build con todas las fuentes.
- La compilación existente **no incorpora Q8.1**. No se utilizó una pantalla de esa compilación como resultado de este cambio.
- Se sirvieron componentes y CSS actuales con Vite de desarrollo en el puerto 5179, mediante una configuración aislada sin plugin Laravel, sin escribir `public/hot` ni sustituir recursos de la aplicación principal.
- Vista reproducible: `scripts/qa/pedidos-q81/preview.html`; instrucciones en su `README.md`. Los datos son ficticios y el transporte está simulado solo en esa página.
- Selenium/Chromium 147 disponible, sin sesión previa. No hubo credenciales/sesión autenticada adecuada para los cuatro perfiles. No se modificó autenticación ni se crearon cuentas reales.

## Verificado automáticamente

| Comprobación | Resultado final |
|---|---|
| Línea base PHP acotada previa | 55 pruebas, 331 aserciones, pasan |
| Línea base JS de ControlPedidos previa | 131 pruebas, 26 archivos, pasan |
| Regresión PHP ampliada final | **262 pruebas, 1846 aserciones, pasan** |
| Interfaz final y regresiones Q6.1 | **159 pruebas, 30 archivos, pasan** |
| Guardias de estilos del proyecto | Pasan |
| Stylelint de los dos CSS modificados | Pasa |
| `git diff --check` | Pasa |
| Aplicación inversa del delta, solo comprobación | `git apply -R --check` pasa |

No se ejecutó toda la suite del repositorio. No se observaron fallos preexistentes en la línea base acotada. Los fallos de suites ampliadas documentados en Q6/Q6.1 quedan fuera de esta ejecución y no se declaran corregidos. Un error intermedio de selector CSS, una aserción nueva sobre un atributo incorrecto del fixture SAF, el pie reducido por flex y un error transitorio de sintaxis durante la extracción de un manejador se corrigieron antes de las comprobaciones finales; no se atribuyen al trabajo anterior.

### Pagos exactos y SAF

Se añadieron **48 escenarios PHP**: transferencia/efectivo/crédito × con/sin SAF × legado/detalle de cajas × con/sin parcial anterior × exacto/excedente real. Cada uno comprueba el reintento y que no duplica pago, crédito ni movimientos.

Importe contractual: mercancía 1000, flete 100 y reexpedición 150, total 1250. En legado, el encabezado ya contiene los 250; en detalle, la zona entra una vez sobre los 100. Se aplica SAF real de 80 cuando corresponde, con disponible restante de 20 desde un crédito generado de 100; se verifica un único movimiento de aplicación y reejecución sin doble aplicación. Los pagos de transferencia requieren banco y archivo; efectivo conserva su comprobante; crédito mantiene su clasificación. Parcial anterior 200 y excedente real 25 son comprobados por separado en las combinaciones. No se modificaron tolerancias ni políticas financieras.

**16 escenarios JS** comparan los importes equivalentes de pantalla y comprueban pendiente/excedente desde un mismo cálculo, preservando bloqueos canónicos. Las regresiones anteriores mantienen pagos rechazados fuera de cobertura y límite de SAF disponible. No se repararon históricos.

### Evidencia, actualización y contratos conservados

Las pruebas finales incluyen: foto de pieza dañada sin cambio; retirada con historial; sustitución sin heredar foto; cambio de cantidad conservando evidencia de producto y exigiendo la nueva del lote; resumen de cambios; bloqueo de empaque; badges persistidos. Se mantienen las pruebas de alcance/permisos, folio inicial, consulta frente a pesaje, transporte externo, traslados internos, abastecimiento, recepción parcial, custodia, reintentos, inventario sin sincronización y los contratos DOM de guardado/concurrencia del modal.

## Verificado visual y funcionalmente en vista aislada

La confirmación final usó emulación exacta de Chromium: **1366×900, 390×844 y 360×800** para Tienda, Ventas, Auxiliar y tarjetas CEDIS. Se inspeccionaron las capturas resultantes; no basta con configurar el viewport.

- Las 12 combinaciones finales no muestran desbordamiento horizontal del documento ni errores de consola. Los textos largos envuelven; la navegación horizontal de secciones sigue accesible.
- Tienda móvil tiene un único contenedor de desplazamiento operativo; no se conserva el pie alto fijo.
- Se enfocaron primer y último campo de cantidad, abrieron Productos/Evidencias/Cierre, abrió/enfocó la referencia y regresó sin perder cantidades.
- Se seleccionó una imagen ficticia mediante el input real y se comprobó su preview local. Se ejecutó guardar y se recibió la respuesta **simulada**, sin escritura en la base.
- Se comprobó foco visible y acceso a los extremos con altura reducida a 480 px en anchos 390/360. Esto simula menos espacio disponible, **no un teclado nativo ni un dispositivo físico**.
- Se inspeccionaron los cuatro modos en tema oscuro a 390 px, además del claro. No es una auditoría formal completa de contraste o accesibilidad.

Evidencia final: `evidencia/q8-1/`, con 18 capturas, `navegador-layout.json`, `navegador-interacciones.json`, registros de pruebas/estilos y el parche de reversión. No se guardaron capturas autenticadas de antes; no se inventa una comparación antes/después.

### Límite del recorrido funcional

No se realizaron desde un navegador autenticado: creación/solicitud real por Ventas, respuesta persistida de Tienda, validación/aprobación real de Auxiliar, pago real con SAF ni respuesta persistida CEDIS. Las pruebas PHP/DOM cubren los contratos incluidos y el navegador aislado cubre el renderizado/interacciones indicadas; no equivalen a un recorrido integrado con sesión y datos de prueba autorizados.

## Archivos de código modificados por Q8.1

Sobre la línea base local, no sobre HEAD:

- `resources/css/gelia/features/control-pedidos-gestion-bma.css`
- `resources/css/gelia/features/control-pedidos-tienda.css`
- `resources/js/Pages/ControlPedidos/Partials/ModalFormPedidoLegado.jsx`
- `resources/js/Pages/ControlPedidos/Partials/FormularioProgresivo/EncabezadoPedido.jsx`
- `resources/js/Pages/ControlPedidos/Partials/EncabezadoFolioPedido.jsx`
- `resources/js/Pages/ControlPedidos/Partials/SeccionRevisionFisicaPedido.jsx`
- `resources/js/Pages/ControlPedidos/Auditar/Partials/EncabezadoRevisionPedido.jsx`
- `resources/js/Pages/ControlPedidos/Auditar/Partials/BarraAccionesRevision.jsx`
- `resources/js/Pages/ControlPedidos/Tienda/Partials/ModalPreparacionTienda.jsx`
- `resources/js/Pages/ControlPedidos/Tienda/Partials/PrepararApartadoRecoge.jsx`
- `resources/js/Pages/ControlPedidos/Tienda/Partials/PrepararApartadoRecoge.test.jsx`
- `resources/js/Pages/ControlPedidos/Cedis/Partials/TarjetasCedis.jsx`
- `resources/js/Pages/ControlPedidos/Cedis/Partials/ModalResponderPesaje.jsx`
- `resources/js/Pages/ControlPedidos/Cedis/Partials/q81Interfaz.test.jsx` (nuevo)
- `resources/js/Pages/ControlPedidos/Partials/q81Cobertura.test.js` (nuevo)
- `tests/Feature/ControlPedidos/Q8AccesoPagosSafEvidenciaTest.php` (ampliado; ya existía sin seguimiento)

Adicionales nuevos: este checkpoint; `scripts/qa/pedidos-q81/` (preview/configuración/instrucciones); `evidencia/q8-1/` (capturas, resultados y reversión). Las notas breves de skills en `docs/visual-refresh/pedidos-q8-1/` son locales porque `/docs` está ignorado; la información necesaria y la evidencia durable también quedan fuera de ese directorio. Ningún servicio, modelo, controlador, permiso o migración de negocio fue cambiado por Q8.1.

## Riesgos, reversión y revisión Q8.2

La distribución de acciones secundarias cambia su ubicación: negocio debe confirmar que los desplegables son fáciles de descubrir y que el pie al final del contenido funciona bien durante preparación larga. El prefijo del folio interno es compartido por otras pantallas de pedidos; verificar folios largos en datos de prueba. No se garantiza una experiencia de teclado/dispositivo físico a partir de emulación.

Reversión exclusivamente de código Q8.1, conservando los cambios previos Q8:

```bash
git apply -R --check evidencia/q8-1/cambio-codigo-q81.patch
git apply -R evidencia/q8-1/cambio-codigo-q81.patch
```

La comprobación inversa pasó sobre el resultado final. Si el código cambia posteriormente, revisar los conflictos y retirar solo los bloques de Q8.1; no forzar el parche ni restaurar todo a HEAD. Conservar este checkpoint y la evidencia para trazabilidad. La vista aislada puede retirarse aparte eliminando únicamente `scripts/qa/pedidos-q81/`, que se creó en este encargo.

**No ejecutar `down` de Q8 ni borrar `retirada_at` o `idempotency_key`.** El código anterior Q8 que queda tras esta reversión conserva su tratamiento de retiradas/reintentos y es compatible con las columnas existentes. Tampoco se retira el esquema de abastecimiento Q6.1 ni sus operaciones. No hay movimientos de inventario que revertir. La recomendación de eliminación de columnas del checkpoint Q8 anterior no aplica a una base donde ya guardan identidad o historial.

Pendientes concretos para negocio/Q8.2:

1. Incorporar las fuentes Q8.1 mediante el procedimiento autorizado de recursos; no interpretar el build actual del puerto 9080 como Q8.1.
2. Recorrer con cuentas y datos de prueba de Ventas, Auxiliar, preparador y CEDIS la cadena solicitud → preparación → revisión → pago exacto/SAF → cambios → respuesta/empaque/salida, incluidos documentos y permisos de sucursal.
3. Revisar móvil real con teclado, fotos/cámara, sesiones de evidencia y escáner físico; preparar pedidos largos y revisar descubrimiento de acciones, primero/último campo y mensajes.
4. Confirmar en negocio tarjetas Tienda/Envío, traslados, preparación desde PDV y los estados relevantes, y contrastar con los documentos autorizados.
5. Validar históricos de pagos/evidencia por un encargo específico si se requiere. No se repararon saldos ni exhibiciones anteriores.

## Reproducción de comprobaciones automáticas

```bash
./vendor/bin/sail exec -T laravel.test env APP_CONFIG_CACHE=/tmp/q81-no-config APP_ROUTES_CACHE=/tmp/q81-no-routes php artisan test --compact --filter='Q8AccesoPagosSafEvidenciaTest|BlindarPagoTotalPedidoTest|IntegracionPedidosYCajaSafTest|Fase2EnviosCajasTest|AbastecimientoPreparacionQ61Test|TrasladosInternosQ6Test|DefinicionesOperativasQ52Test|ElegibilidadPesajePedidoTest|ControlPedidosPesajeTest|DestinoSucursalPedidoBmaTest|ElegibilidadCedisPdvPedidoTest|ControlPedidosFlujoParaleloTest|RecogeTiendaFlujoTest|PreparacionTiendaWorkspaceTest|HandoffRecepcionEsperadaPdvTest|RecepcionFisicaResguardoPdvTest|RecepcionParcialResguardoPdvTest|IncidenciaResguardoPdvTest|SolicitudTraspasoFlujoTest|ControlPedidosPlazosRetrasoTest|ControlPedidosEvaluarRetrasosTest|RegistroManualResguardoPdvTest|EtiquetasQrResguardoPdvTest|UiRecepcionFisicaResguardoPdvTest'
./vendor/bin/sail npm test -- resources/js/Pages/ControlPedidos resources/js/Pages/Traspasos/abastecimientoQ61.test.jsx resources/js/Pages/PuntoVenta/Resguardos/Partials/recepcionQ61.test.jsx
./vendor/bin/sail npm run check:style
./vendor/bin/sail npm exec -- stylelint resources/css/gelia/features/control-pedidos-tienda.css resources/css/gelia/features/control-pedidos-gestion-bma.css
```

## Distinción de cierre

1. **Implementado:** distribución, tarjetas, badges, vocabulario y separación de historial retirado descritos arriba.
2. **Verificado automáticamente:** 262 PHP / 159 JS, guardias de estilo, CSS, diff y reversión comprobada.
3. **Verificado visual y funcionalmente:** componentes reales con datos ficticios/transporte simulado, escritorio/móvil emulado/tema oscuro y las interacciones enumeradas.
4. **Pendiente de entorno o revisión humana:** integración autenticada, recursos de aplicación principal, dispositivos físicos y revisión completa de negocio. Q8 permanece abierto operativamente hasta esa revisión.
