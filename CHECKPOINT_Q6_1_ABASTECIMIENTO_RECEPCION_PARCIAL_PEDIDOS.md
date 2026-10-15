# Q6.1 — Abastecimiento y recepción parcial

## Línea base y contratos antes de editar

Rama `main`, commit `99ff3b0d5bb545c5d3ad37990c1d1167cd1bd3e8`; árbol con Q5.1/Q5.2/Q6 conservado. Parche inicial y listado en `/tmp/q61-before`. Línea base Sail/SQLite en memoria: 59 pruebas, 510 aserciones, todas pasan (`/tmp/q61-baseline.txt`).

- `SolicitudTraspaso`/`SolicitudTraspasoProducto`: origen, estados y revisión física existentes; vínculo de tarea actual conduce a CEDIS. Falta propósito y tarea receptora PDV.
- `PedidoBmaTareaPreparacion`/productos: cantidades solicitadas y encontradas; la salida no debe contarlas como recibidas.
- `RegistrarRecepcionFisicaPdvService`: crea todos los bultos normalizados. Request/controlador/formulario de confirmación no seleccionan folios. `EstadoResguardoPdv` ya separa llegada de custodia y permite contar pendientes.
- `folio_remision` guarda el folio comercial del pedido; `numero_remision` pertenece a auditoría posterior. El modal añade una referencia duplicada opcional y el progreso acepta soporte o folio indistintamente.


## Resultado implementado y puntos de entrada

Se completaron en código, HTTP y formularios el abastecimiento de una preparación existente desde CEDIS u otro PDV y la selección real de llegadas parciales. Se conservan los cambios locales anteriores; no se hicieron commits, push, despliegues, migraciones sobre bases operativas ni build de producción.

**Prioridad añadida por negocio durante Q6.1:** corregir la captura de las vendedoras, a partir de la imagen que mostraba «ENVÍO» junto a «Modalidad de recolección» y paquetería en una sección posterior. La causa era mostrar preparación por estar habilitada globalmente, sin exigir un almacén elegible, y usar instrucciones de Tienda para cualquier envío.

La captura ahora sigue este orden:

- Envío externo normal: tipo → paquetería → almacén de salida → folio/soporte → consulta de mercancía o pesaje según transporte. Sin selector de recolección ni instrucciones que obliguen a preparar en Tienda por tener habilitado ese módulo.
- Tienda: tipo → opciones existentes de «Recoge hoy» / «Resguardo con transferencia» → almacén y destino pertinentes → soporte y preparación. No exige paquetería comercial.
- Envío que realmente se prepara desde PDV: conserva las opciones Tienda → CEDIS y municipal, con el nombre **«Preparación del envío desde Tienda»**, después de elegir transporte y almacén. Una intención guardada incompatible sigue visible para corregirla, sin cambiar el almacén por inferencia.
- Traslado interno a PDV: opción explícita en un desplegable separado, cerrado cuando no hay destino capturado; al seleccionar su destino conserva las exenciones Q6. No muestra las tarjetas de recogida de Tienda por el solo hecho de estar exento de logística externa.

Cambiar entre Tienda y Envío retira la intención de recogida/preparación incompatible y su destino de la captura; conserva almacén, documentos y datos comerciales. Una tarea activa debe pasar por su cambio autorizado antes de cambiar el tipo. Las reglas de servidor de cada recorrido permanecen vigentes.

## Abastecimiento a preparación

Desde la preparación en Tienda, «Abastecer preparación» abre `/traspasos/abastecimiento?tarea=…`. Hay acceso a la bandeja desde Tienda, CEDIS y Traspasos. La solicitud requiere un renglón receptor con SKU/producto identificado y cantidad solicitada pendiente; no extrae ni inventa renglones desde el PDF.

Se reutilizan `SolicitudTraspaso`, sus productos, folio generado, estados Pendiente/Respondida/Verificada, auditoría y `GuardarRevisionesTraspasoService`. La extensión es un propósito explícito `ABASTECIMIENTO_PREPARACION`, almacén de destino, pedido/tarea receptores y producto receptor por renglón. `tarea_preparacion_id` conserva su significado previo para Tienda → CEDIS. `pedido_principal_id` no se utiliza para acreditar una recepción.

`TraspasoOperacion` es un registro de hechos con clave única, actor, fecha, renglones, observación y evidencia: creación, salida, llegada, faltante y rechazo autorizado. No introduce otra máquina de estados ni un pedido comercial. Los registros históricos conservan propósito/vínculos nulos.

Recorrido operativo:

1. El responsable de la tarea destinataria solicita cantidades pendientes a un origen físico distinto. Las cantidades ya comprometidas no se vuelven a solicitar.
2. El operador autorizado del origen revisa cada pieza y confirma salida con evidencia. Se exige un lote íntegro en buena condición; no se fabrica peso, caja comercial, guía ni remisión inicial.
3. La salida deja el traspaso en tránsito, sin acreditar piezas en destino ni completar la preparación.
4. El responsable de destino captura cantidades buenas y dañadas de cada llegada con evidencia. Solo las buenas incrementan `cantidad_encontrada` del renglón receptor. No se exige que el catálogo tenga stock positivo ni se crea una fila de inventario para permitir recibir.
5. Una llegada complementaria acredita únicamente lo pendiente. Un faltante puede reportarse con motivo y evidencia; permanece trazado y bloqueado hasta la llegada que lo satisface.
6. Un daño no abastece. El permiso existente `control_pedidos.preparacion.corregir`, con alcance de sucursal, motivo y evidencia, permite rechazar esas piezas; después se solicita reposición por el mismo circuito. El rechazo no convierte piezas dañadas en buenas ni satisface el déficit de la preparación.
7. Una vez satisfechas las cantidades y resueltas las incidencias, continúa la confirmación existente de preparación/apartado, con sus demás requisitos. La prueba integrada llega a `RESPONDIDA` usando `GuardarApartadoRecogeHoyService`; generar la carátula se aísla en esa prueba y sigue cubierto por las regresiones existentes.

Se bloquean el cierre con abastecimiento pendiente y la sustitución/alteración ordinaria de cantidades vinculadas. No se sustituyen los permisos de corrección. Una llegada incrementa las versiones de tarea y apartado existente, evitando que una pantalla anterior sobrescriba unidades recibidas. Las mutaciones bloquean primero tarea y después solicitud; las claves únicas y versiones cubren reintentos y operaciones desde pantallas concurrentes. Se distinguen cantidades comprometidas, aún sin salida, de las que están realmente en tránsito.

No se crea resguardo para recoger ni entrega al cliente al recibir abastecimiento. Los endpoints genéricos de respuesta, verificación CEDIS, verificación Auxiliar y eliminación no pueden acreditar ni borrar este propósito. Su bandeja específica conserva el historial; el listado tradicional continúa dedicado a los recorridos anteriores.

## Recepción física por bulto

`RegistrarRecepcionFisicaPdvRequest`, controlador, servicio, consulta del formulario y formulario activo reciben ahora `folios[]` y condición. La ausencia de selección ya no confirma todos los bultos. El servidor compara folios contra `BultosEsperadosResguardoPdv`, rechaza ajenos/repetidos/ya recibidos y conserva su identidad existente.

Con tres esperados y dos seleccionados se crean solo dos bultos físicos, con responsable, fecha y condición. El estado general sigue pendiente de recepción; el evento es parcial, con esperado, recibido acumulado y pendiente. El tercer bulto se confirma posteriormente con otra clave/versión. Un reintento de la primera llegada no vuelve a crearlos. La fecha física registra la primera llegada y cada evento conserva la fecha propia.

El formulario muestra el conjunto esperado, marca como no seleccionables los ya recibidos y solicita evidencia física para cada llegada de pedidos. Una condición distinta de buena requiere descripción y abre incidencia por bulto con evidencia, usando el modelo y la resolución de incidencias vigentes. Para condiciones diferentes se registran grupos de llegada separados. El servicio de custodia impide incorporar un bulto con incidencia abierta; la confirmación automática del procedimiento manual no se usa mientras existan daños pendientes.

Los registros manuales mantienen su contrato vigente de ticket, foto, omisiones justificadas y procedimiento de doble confirmación. Web y API móvil comparten el controlador; el formulario consultable devuelve `bultos_esperados` con los folios y si ya llegaron. Los clientes móviles del repositorio separado deben enviar este nuevo contrato: omitir la selección devuelve validación y nunca acredita todo implícitamente.

Llegada y custodia siguen separadas. Se conserva el requisito existente de completar la llegada de gerencia para pasar a recepción; después se permite custodia parcial. No se permite custodiar un folio que no llegó. Los textos de éxito ya no dicen que la mercancía quedó en custodia por confirmar llegada. La evidencia se envía por POST con `_method=PUT`, para que PHP reciba correctamente archivos y selección.

## Folios y datos por etapa

`folio_remision` conserva su nombre técnico y representa el **folio del pedido**, capturado una vez. Tanto solicitud CEDIS como preparación Tienda lo validan en servidor; el PDF/imagen no lo sustituye. El progreso inicial exige folio y soporte, sin pedir `numero_remision`, una referencia adicional ni un segundo folio.

La captura lo identifica como «Folio del pedido» y muestra el valor guardado en la solicitud. Las referencias legítimas se conservan bajo «Referencia adicional opcional (otro caso)». El folio generado se identifica como «Folio interno GELIA». `numero_remision`, documentos de remisión, pago, SAF y guías posteriores no se eliminan ni se trasladan al inicio.

## Archivos y esquema

Nuevos:

- `app/Services/Traspasos/AbastecerPreparacionService.php`, `app/Models/TraspasoOperacion.php`.
- `app/Http/Controllers/Traspasos/AbastecimientoPreparacionController.php`, `app/Http/Requests/Traspasos/AbastecimientoPreparacionRequest.php`.
- `resources/js/Pages/Traspasos/Abastecimiento.jsx`.
- `database/migrations/2026_10_15_200000_add_abastecimiento_preparacion_to_traspasos.php`.
- `tests/Feature/ControlPedidos/AbastecimientoPreparacionQ61Test.php`, `resources/js/Pages/Traspasos/abastecimientoQ61.test.jsx`, `resources/js/Pages/PuntoVenta/Resguardos/Partials/recepcionQ61.test.jsx`.

Responsables modificados en Q6.1:

- Modelos `SolicitudTraspaso` / `SolicitudTraspasoProducto`; rutas `routes/web/modules/traspasos.php`; controlador genérico `SolicitudTraspasoController`.
- Servicios Traspasos `ResponderSolicitudTraspasoService`, `ConfirmarTraspasoCedisService`, `EliminarSolicitudTraspasoService`, `ListarSolicitudesTraspasoService`.
- ControlPedidos: `CrearTareaPreparacionService`, `SolicitarPesajePedidoBmaService`, `CalcularProgresoPedidoBmaService`, `RegistrarProductosPreparacionService`, `ResponderPreparacionTiendaService`, `ConfirmarApartadoRecogeHoyService`, `VisibilidadTareaPreparacion`.
- PDV: `RegistrarRecepcionFisicaPdvRequest`, `RecepcionFisicaResguardoPdvController`, `RegistrarRecepcionFisicaPdvService`, `ConsultaFormularioRecepcionFisicaPdvService`, `RegistrarConfirmacionCustodiaPdvService`.
- Interfaz: `ModalFormPedidoLegado`, índices Tienda/CEDIS/Traspasos, `PrepararApartadoRecoge`, `PanelAccionesShowTienda`, `FormularioConfirmarRecepcionResguardo`, `ModalRecepcionResguardo`, `useRecepcionFisica`, `Recepcion.jsx`.
- Fixtures y expectativas ajustados: elegibilidad CEDIS, flujo Recoge, Q6, recepción física/parcial, registro manual, etiquetas QR, persistencia/concurrencia del modal e índice Tienda. Se añaden pruebas de orden de campos y cambio Tienda → Envío.

Orden requerido al preparar un entorno mediante su proceso habitual:

1. Migración Q6 `2026_10_15_190000_add_cantidades_to_pedido_bma_revisiones_producto.php`.
2. Migración Q6.1 `2026_10_15_200000_add_abastecimiento_preparacion_to_traspasos.php`: campos anulables y versión en solicitudes, referencia anulable al renglón receptor y tabla de operaciones con clave única.
3. Código y recursos correspondientes; renovar caché de rutas/configuración por el proceso de despliegue habitual. El entorno tenía rutas cacheadas que no incluían las nuevas; las pruebas usaron rutas/configuración temporales sin borrar esos cachés.

Ambas migraciones se ejecutaron exclusivamente mediante `RefreshDatabase` en SQLite `:memory:`. No hubo relleno histórico ni cambios de configuración persistentes.

## Validación, compatibilidad y límites

Las pruebas verifican ambos orígenes, vínculo completo, salida sin crédito, parcialidad de piezas y bultos, complemento/reintento/versiones, daño y reposición, faltantes, permisos por sucursal/producto/tarea, evidencia, continuidad de preparación y ausencia de pedidos/entregas/resguardos nuevos por abastecimiento. Comprueban inventario idéntico antes/después, incluso con existencia cero.

Las regresiones incluyen O02, externos con peso/guía, consulta pendiente bloqueando empaque/salida, guía y empaque paralelos, Tienda → CEDIS, permisos, alertas/plazos, preparación, pagos/remisión del recorrido Q6, recepción, custodia, incidencias y registros manuales. Resultados finales y reproducción se consignan al cierre de este documento.

**Verificación manual autenticada pendiente:** no se dispuso de una sesión de navegador controlable/autenticada ni de un navegador con base de prueba aislada configurada. No se alteró la base operativa para preparar esa revisión. La captura aportada por negocio se inspeccionó y originó las pruebas nuevas de orden/visibilidad; no se presenta como verificación visual del resultado. HTTP, DOM y renderizado estático no sustituyen recorrer manualmente los modales. Faltan esos recorridos visuales con cuentas de prueba de Ventas, origen y destino, incluidas dos terminales reales.

Otros límites concretos: requiere renglones receptores identificados y asignación de sucursal/responsable; no añade extracción de productos desde documentos, sincronización de stock, cancelación/reencaminamiento automático de traslados ni inferencia sobre históricos. Rechazar un daño exige autorización y posterior reposición; no hay aceptación automática de mercancía dañada. La generación de una nueva preparación tras una corrección sigue el circuito existente; no se transfieren silenciosamente llegadas de otra tarea.

Reversión: retirar únicamente el delta Q6.1 sobre el árbol inicial, conservando Q5.1/Q5.2/Q6. No usar restauración global a HEAD. Las columnas anulables y operaciones pueden conservarse al revertir código; ejecutar `down()` borraría vínculos y trazabilidad nueva, por lo que exige respaldo previo. No hay movimientos de inventario que reintegrar. Los clientes que consuman recepción deben coordinar su actualización al contrato explícito de folios/condición/evidencia.


## Resultados finales y reproducción

| Comprobación | Resultado |
|---|---|
| Línea base antes de editar | 59/59 PHP, 510 aserciones |
| Regresión final relacionada | **180/180 PHP, 1186 aserciones** |
| ControlPedidos completo + vistas/recepción Q6.1 | **131/131 JS, 27 archivos** |
| Estilos globales | `check:style` pasa |
| Integración HTTP + registros manuales + QR + formulario | Incluidos en las 180 pruebas |
| Comprobación manual autenticada | Pendiente, con las limitaciones anteriores |

Evidencia local temporal: `/tmp/q61-baseline.txt`, `/tmp/q61-verified-php.txt`, `/tmp/q61-verified-js.txt`, `/tmp/q61-style-final.txt`. Los fallos intermedios nuevos (fixtures sin selección, folio inicial o tablas del contrato nuevo, selectores con etiquetas anteriores, caché de rutas y configuración del buscador de páginas en pruebas) fueron corregidos; no se atribuyen a la línea base.

Se ejecutó aparte `recepcionFisicaUtils.test.js`: **9/10**. El único fallo espera habilitar entrega total por `recepcion_completa` sin custodia. Ya está documentado en Q6 y los archivos de esa utilidad y su prueba son idénticos a HEAD (`git diff --exit-code HEAD -- …` pasa). No se relajó la separación recepción/custodia para satisfacer esa expectativa. La suite ampliada de 442 pruebas con 26 fallos preexistentes descrita por Q6 no se volvió a presentar como verde ni se declara saneada aquí.

Todos los comandos de aplicación se ejecutaron mediante Sail. Para reproducir:

```bash
./vendor/bin/sail exec -T laravel.test env APP_CONFIG_CACHE=/tmp/gelia-q61-no-config APP_ROUTES_CACHE=/tmp/gelia-q61-no-routes php artisan test --compact --filter='AbastecimientoPreparacionQ61Test|TrasladosInternosQ6Test|DefinicionesOperativasQ52Test|ElegibilidadPesajePedidoTest|ControlPedidosPesajeTest|DestinoSucursalPedidoBmaTest|ElegibilidadCedisPdvPedidoTest|ControlPedidosFlujoParaleloTest|RecogeTiendaFlujoTest|PreparacionTiendaWorkspaceTest|HandoffRecepcionEsperadaPdvTest|RecepcionFisicaResguardoPdvTest|RecepcionParcialResguardoPdvTest|IncidenciaResguardoPdvTest|SolicitudTraspasoFlujoTest|ControlPedidosPlazosRetrasoTest|ControlPedidosEvaluarRetrasosTest|RegistroManualResguardoPdvTest|EtiquetasQrResguardoPdvTest|UiRecepcionFisicaResguardoPdvTest'
./vendor/bin/sail npm test -- resources/js/Pages/ControlPedidos resources/js/Pages/Traspasos/abastecimientoQ61.test.jsx resources/js/Pages/PuntoVenta/Resguardos/Partials/recepcionQ61.test.jsx
./vendor/bin/sail npm run check:style
```

Pendientes de puesta en uso, sin ejecutarlos en este encargo: migraciones y actualización de rutas/recursos por el procedimiento del entorno, coordinación del contrato con el cliente móvil y recorrido manual autenticado. No quedan las llegadas parciales ni el vínculo receptor únicamente modelados: cuentan con rutas, permisos, formularios y pruebas de ejecución. Se mantiene expresamente pendiente la verificación visual manual.

Cierre técnico: comprobación de sintaxis de los 69 archivos PHP modificados/nuevos del árbol pasa (`/tmp/q61-syntax.txt`), `git diff --check` pasa y la revisión final de estilos vuelve a pasar (`/tmp/q61-style-verified.txt`). Después de hacer explícito el orden cronológico de operaciones se repitieron las 12 pruebas de abastecimiento: 12/12, 78 aserciones (`/tmp/q61-order-check.txt`). Rama y commit permanecen en `main` / `99ff3b0d5bb545c5d3ad37990c1d1167cd1bd3e8`.
