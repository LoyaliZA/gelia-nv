# CHECKPOINT Q5 — Pesaje, elegibilidad documental y continuidad operativa

## 1. Resumen ejecutivo

La hipótesis quedó delimitada, no convertida en una exención general.

El pesaje físico de CEDIS sigue siendo obligatorio en todo pedido con logística que no está en preparación de Tienda, aunque la paquetería local tenga `requiere_peso = false`. Esa exención local (O02) no se restauró.

El defecto comprobado era otro: la respuesta de Tienda escribe `pesaje_respondido_at` sin crear envíos CEDIS, y el validador de avance trataba esa fecha como pesaje de CEDIS. Pedía peso, tipo de caja, número de cajas y detalle de envíos que esa preparación no produce. Ahora el avance usa el peso de la tarea solo cuando la modalidad o el transporte ya lo exigen.

Un segundo defecto de presentación: la tarjeta de CEDIS trataba una tarifa local sin peso como consulta de mercancía, mientras Laravel y el modal de respuesta seguían pidiendo kilogramos. La tarjeta quedó alineada con Laravel.

El empaque municipal pedía foto de báscula aunque `peso_real_obligatorio` fuera falso. La foto queda condicionada a ese indicador. Bulto, carátula y entrega al transporte siguen siendo obligatorios.

No se creó una modalidad documental nueva ni una regla por el nombre Aromas o Bellaroma.

## 2. Causa raíz

Tres contratos distintos se evaluaban con la marca de pesaje:

1. `PedidoBma::consultaCedisModoRevisionMercancia()` es verdadero solo si el origen no requiere logística. El comentario del modelo y `ControlPedidosPesajeTest` fijan que toda operación con logística es pesaje, sin mirar departamento ni `requiere_peso`.
2. `ResponderPreparacionTiendaService::aplicarSincronizacionPedido()` copia la respuesta de Tienda a `pesaje_respondido_at` y `estatus_envio = pesaje_listo`, sin kilogramos ni filas de `pedido_bma_cajas`.
3. `ValidacionCamposPedidoBma::validarCamposRequeridos()`, con logística, pesaje respondido y modo distinto de revisión de mercancía, exigía el detalle de envíos CEDIS. Esa rama se aplicaba también a la preparación de Tienda.

En la bandeja, `TarjetasCedis.jsx` añadía una cuarta condición, `paqueteria.categoria === local_regional && requiere_peso === false`, que `pedidoModoRevisionMercancia()` y el backend ya habían retirado.

En empaque, `CalcularRequisitosPreparacionService::validarEmpaqueMunicipio()` pedía `Foto de báscula / peso` sin leer `peso_real_obligatorio`, indicador que `validarRespuesta()` sí respeta.

## 3. Reglas comprobadas

| Concepto | Regla que el código aplica | Evidencia |
|---|---|---|
| Pesaje físico CEDIS | Origen con `requiere_logistica`. Exige al menos un envío con tipo de caja, pesos, medidas y foto del contenido. | `ResponderPesajePedidoBmaService` cuando `consultaCedisModoRevisionMercancia()` es falso. Request `control_pedidos.cedis`. |
| Consulta de mercancía | Origen sin logística. Exige productos revisados, bultos aproximados (`numero_cajas` >= 1) y evidencia del lote. No exige kilogramos. | El mismo servicio en `soloRevisiones`. `test_consulta_de_mercancia_sin_logistica_no_exige_kilogramos`. |
| Peso de preparación Tienda | Solo si `CalcularRequisitosPreparacionService::efectivos()` marca `peso_real_obligatorio`. El valor vive en la tarea. No se piden líneas de envío CEDIS. | Matriz y `requisitos_json` de la modalidad; transporte municipal vía `CatalogoPaqueteriaPedido::reglasMunicipio()`. |
| Documentación de captura | PDF o foto del pedido antes de solicitar la consulta y antes de enviar al auxiliar. No sustituye el pesaje ni la revisión. | `tieneSoporteSolicitud()` y `tienePdfPedido()`. |
| Tarifa local sin peso | No cambia el modo CEDIS. Sigue siendo pesaje si hay logística y la salida no es preparación de Tienda. | `test_consulta_cedis_modo_revision_mercancia` y `test_envio_cedis_con_logistica_exige_cajas_y_peso_aunque_la_tarifa_local_no_pese`. |
| Departamento | Aromas y Bellaroma no tienen una rama propia en estos validadores. El mismo origen con logística exige pesaje CEDIS con cualquiera de los dos nombres. | `test_nombre_de_departamento_no_cambia_la_obligacion_de_pesaje`. La matriz de bodega sí distingue códigos de departamento para evidencia y caja; eso no se inventó en Q5. |

Quién actúa:

- Solicitar consulta o pesaje: quien ya puede mutar el pedido, en borrador o rechazado, con soporte adjunto. Con logística hace falta paquetería. Una tarea de Tienda vigente lo impide. Una solicitud ya pendiente se devuelve igual, sin segundo historial.
- Responder: permiso `control_pedidos.cedis`, con `estatus_envio = pendiente_pesaje` y sin `empacado_at`.
- Cerrar la consulta y enviar al auxiliar: la persona que captura, después de la respuesta. El cierre sigue siendo obligatorio salvo complemento de resguardo.
- Pedido ENVIADO: no puede solicitar ni responder pesaje.

## 4. Matriz de elegibilidad

| Escenario | Origen | Salida | Destino | Modalidad | Responsable | Requisito de pesaje | Evidencia | Acción permitida | Bloqueo que había | Comportamiento respaldado |
|---|---|---|---|---|---|---|---|---|---|---|
| Envío con logística | Requiere logística | CEDIS | Según modalidad; sucursal prohibida si no es fase 4 | Pesaje CEDIS | CEDIS | Kilogramos, cajas y volumétrico por envío | Foto de cada envío; daños con evidencia de producto | Responder pesaje y, ya cerrado, enviar | Ninguno indebido: el peso sigue siendo obligatorio | Confirmado. La tarifa local sin peso no exime |
| Salida CEDIS con destino sucursal | Tienda, sin logística | CEDIS | Sucursal | Consulta de mercancía | CEDIS | No hay kilogramos. Sí bultos aproximados | Revisión de piezas y foto del lote | Consulta CEDIS, como Q4 | No era un bloqueo de peso | Confirmado. Q4 se conserva |
| Origen Tienda, salida CEDIS | Sin logística | CEDIS | Sucursal si aplica | Consulta de mercancía | CEDIS | Igual que la fila anterior | Igual | Consulta, sin tarea de Tienda | Q4 ya lo corrigió | Confirmado de nuevo en la suite Q4 |
| Preparación vigente en Tienda | El del pedido | PDV de la tarea | El de la modalidad | La de la tarea | Tienda | El de `efectivos()`, no el detalle CEDIS | Evidencia general y la de la modalidad | Preparación. Otra consulta CEDIS se rechaza | Tras responder, el avance pedía detalle de envíos CEDIS | Corregido. Bodega exige el peso de la tarea |
| Recoge hoy en PDV | Sin logística en el caso de tienda | PDV | Según recoge | `RECOGE_TIENDA` | PDV | El JSON de recoge no marca peso real | Evidencia general al responder | Apartado del mismo día | No hereda kilogramos CEDIS porque no hay logística | Confirmado. La restricción de almacén PDV no se tocó |
| Operación solo documental | — | — | — | No existe como modalidad | — | — | El soporte de captura ya es obligatorio | No se añadió un estado nuevo | — | Decisión pendiente O03. No implementada |
| Pesaje ya respondido | Con logística, salida CEDIS | CEDIS | El capturado | Pesaje | CEDIS | Sigue exigiendo las cajas guardadas | Las ya adjuntas | Avanzar si el detalle está completo | Una respuesta sin cajas sigue bloqueada | Confirmado |
| ENVIADO o ENTREGADO | El histórico | El guardado | El guardado | Ninguna nueva | Ninguno | No se reevalúa para operar | Se conserva | Sin solicitar ni responder | — | Confirmado. No se escribieron folios históricos |
| Captura incompleta | Con logística | CEDIS | — | Pesaje | CEDIS | El envío vacío se rechaza | Foto y pesos faltantes impiden registrar | No cambia de fase | — | Confirmado. La consulta sin productos también se rechaza |
| Solicitudes concurrentes | Cualquiera elegible | La de Q4 | El de Q4 | La de Q3 | El de Q4 | Igual que la fila que corresponda | El soporte ya exigido | La segunda solicitud pendiente no crea otro historial | — | Confirmado. Lock, revisión e idempotencia de Q3 siguen |

## 5. Antes y después

Datos sintéticos en SQLite `:memory:`. No se usaron los folios productivos.

| Recorrido | Antes | Después |
|---|---|---|
| Logística, salida CEDIS, tarifa local con `requiere_peso = false`, pesaje respondido sin kilos | El avance pedía peso real de pesaje CEDIS. La tarjeta podía decir «Consulta mercancía». | Sigue pidiendo peso, cajas y detalle CEDIS. La tarjeta dice «Consulta de pesaje» y explica que la tarifa no exime el pesaje. |
| Logística con preparación de bodega respondida, peso en la tarea, sin cajas CEDIS | El avance pedía peso, tipo de caja, número de cajas y detalle de envíos CEDIS. | Pide el peso de la preparación si la matriz lo marca. Con 1.25 kg en la tarea, el avance no pide detalle CEDIS. |
| Municipio en Tienda, transporte sin `requiere_peso` | El avance pedía el detalle CEDIS y el empaque pedía foto de báscula. | El avance no pide kilogramos. El empaque no pide báscula. Sí pide bulto, carátula y entrega. |
| Municipio, transporte con `requiere_peso`, sin peso en la tarea | Igual, mezclado con el detalle CEDIS. | El mensaje es «peso real (preparación de Tienda)». No menciona pesaje CEDIS ni detalle de envíos. |
| Origen sin logística | No pedía kilogramos. | Igual. |
| Segunda solicitud de consulta | Un historial. | Igual. |
| Pedido en fase ENVIADO | Sin solicitar ni responder. | Igual. |
| Nombres «Aromas Q5» y «Bellaroma Q5» con logística y salida CEDIS | Ambos pedían pesaje CEDIS. | Igual. No hay exención por nombre. |

## 6. Cambios backend

- `ValidacionCamposPedidoBma`: si `enRutaPreparacionTienda()`, no exige el detalle de envíos CEDIS. Exige peso o tipo de caja de la tarea solo cuando `efectivos()` los marca. El resto de la logística (paquetería, guía, domicilio, costo) queda igual.
- `CalcularRequisitosPreparacionService::validarEmpaqueMunicipio()`: la foto de báscula depende de `peso_real_obligatorio`. Las otras tres fotos de empaque no.
- `ListarPedidosBmaService`: publica `en_ruta_preparacion_tienda`, `preparacion_peso_real_obligatorio` y `preparacion_peso_capturado` para que el modal use el mismo cálculo.

No se modificaron locks, revisión de captura, idempotencia, `usaPreparacionTienda()`, almacenes receptores, Recoge hoy ni la máquina de estados.

## 7. Cambios frontend

- `validarCamposEnvioPedido` distingue respuesta de Tienda, consulta CEDIS y pesaje CEDIS. En ruta de Tienda no lista el detalle de pesaje CEDIS. Si el servidor marca peso de preparación y no está capturado, el faltante es «peso real (preparación de Tienda)».
- `cedisConsultaEsRevisionMercancia` ignora la tarifa. La tarjeta de CEDIS usa esa función en la etiqueta, el texto y el botón.
- `EmpaqueMunicipioTienda` oculta la báscula cuando el transporte no exige peso y explica qué fotos siguen bloqueando el empaque.
- El modal de ventas lee los tres indicadores nuevos del pedido. No se rediseñó el módulo.

## 8. Archivos modificados

Nuevos:

- `tests/Feature/ControlPedidos/ElegibilidadPesajePedidoTest.php`
- `resources/js/Pages/ControlPedidos/Tienda/Partials/EmpaqueMunicipioTienda.test.jsx`
- `CHECKPOINT_Q5_PESAJE_ELEGIBILIDAD_DOCUMENTAL_PEDIDOS.md`

Existentes:

- `app/Services/ControlPedidos/ValidacionCamposPedidoBma.php`
- `app/Services/ControlPedidos/CalcularRequisitosPreparacionService.php`
- `app/Services/ControlPedidos/ListarPedidosBmaService.php`
- `resources/js/Pages/ControlPedidos/Partials/pedidosBmaStyles.js`
- `resources/js/Pages/ControlPedidos/Partials/pedidosBmaStyles.validacion.test.js`
- `resources/js/Pages/ControlPedidos/Partials/ModalFormPedidoLegado.jsx`
- `resources/js/Pages/ControlPedidos/Cedis/Partials/TarjetasCedis.jsx`
- `resources/js/Pages/ControlPedidos/Tienda/Partials/EmpaqueMunicipioTienda.jsx`
- `tests/Unit/ControlPedidos/ControlPedidosGuiaClienteTest.php`

El ajuste de `ControlPedidosGuiaClienteTest` completa la fixture que ya debía tener consulta cerrada y PDF. Esa regla no cambió en Q5. Antes el archivo no llegaba a la aserción porque `Storage::fake` no podía abrir `storage/framework/testing/disks/public/pedidos_bma`.

## 9. Pruebas ejecutadas

Entorno: Sail, SQLite `:memory:`. No se usó `ALLOW_DESTRUCTIVE_DB` ni una base operativa.

Comando final:

`./vendor/bin/sail artisan test` sobre `ElegibilidadPesajePedidoTest`, `ControlPedidosPesajeTest`, `ControlPedidosGuiaClienteTest`, `ElegibilidadCedisPdvPedidoTest`, `BorradorPreparacionContratoTest`, `ConcurrenciaCapturaPedidoTest`, `ConfirmarCaratulaIdentidadTest`, `ValidarAlmacenSalidaPedidoTest`, `BusquedaRemisionDelegadoTest`, `PreparacionTiendaWorkspaceTest` y `Fase5MunicipioEmpaqueTest`.

Resultado: **86/86**, 601 aserciones.

Vitest: `pedidosBmaStyles.validacion.test.js`, `EmpaqueMunicipioTienda.test.jsx`, `modalElegibilidadCedisPdv.test.jsx` y `modalConcurrenciaContrato.test.jsx`. **27/27** en la pasada de esos cuatro archivos. La repetición de validación y empaque, ya con el texto de «respuesta de Tienda», quedó en 13/13.

`npm run build`: correcto. Permanece el aviso de chunks mayores a 500 kB. El manifiesto local quedó verificado.

Permiso de entorno: `storage/framework/testing/disks/public/pedidos_bma` estaba en `700` y el proceso de prueba no podía abrirlo. Se aplicó `chmod -R a+rwx` solo a `storage/framework/testing/disks/public`. Con eso corrieron `BusquedaRemisionDelegadoTest` y `PreparacionTiendaWorkspaceTest`, que en Q4 no llegaban a sus aserciones. No se tocó configuración global ni datos operativos.

Los folios `PBMA-2026-00028`, `00025`, `00023` y `00022` no se consultaron ni se modificaron. Q4 ya los usó como referencia documental del responsable, no como prueba de exención de peso. El código actual no tiene una rama que los dispense.

## 10. Compatibilidad Q1–Q4

- Q1: `ConfirmarCaratulaIdentidadTest` dentro de las 86.
- Q2: `BorradorPreparacionContratoTest` dentro de las 86.
- Q3: `ConcurrenciaCapturaPedidoTest` dentro de las 86. La solicitud duplicada de Q5 deja un solo historial.
- Q4: `ElegibilidadCedisPdvPedidoTest` dentro de las 86. `usaPreparacionTienda()` sigue limitado a la tarea vigente. La consulta CEDIS con salida CEDIS y origen Tienda no crea tarea. Recoge hoy y el almacén receptor no se reabrieron.
- `ValidarAlmacenSalidaPedidoTest` dentro de las 86.

## 11. Riesgos y decisiones pendientes

**O02, pendiente.** Una paquetería local o regional con `requiere_peso = false` no exime el pesaje CEDIS de un envío con logística. El commit que retiró ese criterio sigue vigente y hay prueba que lo fija. Falta una decisión de negocio, con autorización y evidencia, antes de volver a exceptuarlo.

**O03, pendiente.** No hay una modalidad documental de Aromas distinta de «origen sin logística» o de la preparación de Tienda. El soporte de captura ya es obligatorio. No se creó estado, responsable ni transición nuevos para un envío solo documental.

**Observado, sin cambio.** `ResponderPesajePedidoBmaService` puede escribir `almacen_id` si el arreglo trae un almacén habilitado para buscar productos. El form request no declara `almacen_id` en `rules()`, así que `validated()` no lo entrega. No se usó esa vía para corregir Q5.

## 12. Impacto en Q7 y Q8

Q7 quedó reejecutado: `BusquedaRemisionDelegadoTest` pasó después del permiso del disco de pruebas. No se cambió la búsqueda ni las identidades documentales.

Q8 no se modificó. Recepción física, custodia, entrega y devolución PDV siguen fuera de esta entrega. El único empaque tocado es el municipal de Tienda, y solo la foto de báscula quedó atada al indicador de peso que ya existía. Verificar entrega o devolución no forma parte de Q5.

## 13. Veredicto

**GO condicionado.**

El avance ya no hereda el detalle de pesaje CEDIS cuando el recorrido vigente es la preparación de Tienda, y no se apagó el pesaje de los envíos CEDIS que sí lo requieren. La condición de salida es publicar juntos el PHP y el build del modal y de la tarjeta CEDIS. O02 y O03 siguen sin implementarse.

## 14. Instrucciones de despliegue

No se desplegó nada. No hay commit ni push.

1. Publicar el código de Q5 junto con los assets ya compilados en local (`npm run build` terminó y verificó el manifiesto). No publicar solo el PHP o solo el modal.
2. No ejecutar migraciones ni cambiar permisos, `permite_salida_pedidos` ni `almacenes_habilitados`.
3. No editar los pedidos históricos citados.
4. En un pedido de prueba, no productivo: envío con logística y salida CEDIS debe seguir pidiendo peso y cajas. Una preparación de bodega con peso en la tarea debe poder avanzar sin detalle de envíos CEDIS. Un municipio cuyo transporte no exige peso no debe pedir báscula. Recoge hoy en PDV y una salida solo receptora deben seguir como en Q4.
