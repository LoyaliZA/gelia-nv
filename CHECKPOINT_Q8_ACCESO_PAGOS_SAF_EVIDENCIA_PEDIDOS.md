# Q8 — Acceso a preparación, pagos, SAF y evidencia CEDIS

Rama `main`, commit de partida `83a5ac1`. No hay commit ni push de este trabajo. No se ejecutaron migraciones sobre la base MySQL de Sail.

## Reproducción y causa

### 1. Preparación en Tienda

Confirmado. Abrir la preparación llama `VisibilidadTareaPreparacion::payloadTienda()`, y ese payload siempre consultaba `AbastecerPreparacionService::pendientes()`. En la base de trabajo faltan `solicitudes_traspasos.tarea_receptora_id`, `solicitudes_traspasos.proposito` y la tabla `traspaso_operaciones` (migración `2026_10_15_200000_add_abastecimiento_preparacion_to_traspasos`, de Q6.1, no aplicada).

Reproducción en tinker, solo lectura, tarea 1:

`SQLSTATE[42S22] Unknown column 'tarea_receptora_id'`.

La bandeja no hace esa consulta, así que el listado abre y el modal muestra «No se pudo abrir la preparación». No es un permiso. `puedeVer` ya dejaba pasar a quien tiene `control_pedidos.tienda.ver`; mutar sigue exigiendo asignación.

Después del cambio, el mismo tinker arma el payload: `abastecimiento_esquema=0` y un aviso visible. La preparación carga sus productos.

### 2. Pago exacto con excedente

Confirmado como divergencia de presentación y de desglose, no como tolerancia.

En esta base `control_pedidos.envios.detalle_cajas` está activo y `reexpedicion_alcance` es `pedido`. La zona «Con reexpedición» cobra 150. El campo `costo_envio` ya guarda flete más reexpedición. La pantalla sumaba otra vez `costoReexpedicion`. El recorte que separa ese cargo se marcaba hecho aunque el catálogo de zonas aún no hubiera llegado, así que no volvía a ejecutarse.

El servidor, en camino legado, usa el encabezado una sola vez. Pagar el importe inflado de la pantalla contra ese pendiente genera excedente igual al cargo de zona.

Pedido 35, solo diagnóstico: mercancía 5999, `costo_envio` 150, seguro 149.98, zona 1, caja activa sin costos (el detalle cae a legado). No se reparó.

Segundo hueco: con cajas completas, `CalcularTotalesEnvioPedidoService` sumaba flete y adicional de caja y no volvía a incorporar la reexpedición de zona. `aplicarAlPedido` escribía ese total en el encabezado y la zona desaparecía del cobro. Ahora, si el alcance es `pedido`, la zona se suma una vez sobre el detalle.

`mezclarResumenCoberturaConPedido` podía mostrar el pendiente del servidor y el excedente del cálculo vivo a la vez. Pendiente y excedente salen ahora del mismo cálculo.

### 3. SAF como importe libre

El libro ya validaba titular, disponible, vencimiento y alcance, con `lockForUpdate`. Lo que se veía como captura libre era el campo numérico: si no había filas FIFO, el formulario enviaba `[{ monto }]` sin `saf_credito_id`, y el total en pantalla usaba el número escrito aunque el reparto real fuera menor. `reservarParaPedido` actualizaba `saldo_a_favor` y no `total_a_cobrar`.

No se cambió la política de generación, autorización ni devolución. Un excedente real sigue `reconciliarExcedenteTrasExhibicion`. El crédito no exige comprobante ni banco y no se trata como ingreso bancario.

Los pedidos que tienen `saldo_a_favor` en columna y ninguna aplicación no se borran al guardar si el libro del cliente aún no cargó. No se repararon filas históricas.

### 4. Evidencia que desaparece al actualizar la consulta

La precarga de `ModalResponderPesaje` dejaba `evidencias` y `previews` vacíos. El servidor solo aceptaba archivo nuevo o foto de sesión; no contaba `evidencia_condicion` ya ligada al renglón. La pieza y el archivo seguían en base, pero la nueva revisión los exigía otra vez.

Una pieza que ya no venía en la respuesta y tenía documentos se conservaba como renglón vigente. No existía `retirada_at`.

### 5. Resumen de cambios

El resumen comparaba por SKU o descripción. Dos renglones del mismo SKU se colapsaban. Una cantidad o una condición distinta podía quedar como «sin cambio de SKU». Una retirada con la misma clave que una pieza que permanece no se anunciaba.

## Hipótesis descartadas

- Ampliar permisos de Tienda. El fallo es SQL por esquema, no autorización.
- Subir la tolerancia de pagos. El importe canónico, pagado una vez, cubre sin excedente.
- Pagos rechazados o sustituidos sumados dos veces. Ya quedan fuera por `activo_para_cobertura`.
- El servidor cobrando el pago actual dos veces. El excedente salía de la pantalla, o de omitir la zona cuando el detalle de cajas está completo.
- Borrar o clonar archivos para «conservar» evidencia. Se reutiliza la relación `revision_producto` / `revision_general` / `envio_caja`.

## Comportamiento

| Caso | Antes | Después |
| --- | --- | --- |
| Abrir preparación propia | Error SQL si falta la migración de abastecimiento | Abre productos y evidencias. Aviso si el abastecimiento aún no tiene esquema |
| Usuario sin permiso | Sigue bloqueado | Igual |
| Pago del pendiente que muestra el cálculo canónico | Podía exceder por reexpedición duplicada | Cubierto, sin SAF de ese pago |
| Pago mayor al pendiente | Excedente y SAF según la regla vigente | Igual |
| Reintento con la misma clave | Podía crear otra exhibición | Devuelve la misma exhibición cuando existe la columna |
| SAF | Número escrito, sin tope visible | Tope del disponible del libro; el servidor vuelve a reservar FIFO |
| Pieza dañada sin cambio | Había que fotografiarla otra vez | Conserva condición y evidencia |
| Producto sustituido | Podía seguir el renglón anterior | Renglón nuevo; la foto anterior queda en la pieza retirada |
| Cantidad distinta | La foto del producto se perdía de la revisión | Se conserva la del producto y se exige foto nueva del lote |
| Pieza retirada | Seguía como pieza actual si tenía documentos | Queda retirada, con su evidencia, fuera del listado actual |
| Resumen | «Sin cambio de SKU» | Agregadas, retiradas, sustituidas, cantidades y condición. Si no hay cambio de piezas, lo dice y conserva el motivo |
| Empaque / salida con actualización pendiente | Ya bloqueados desde Q5.1 | Se mantiene |

La precarga no aprueba sola la nueva revisión: hay que guardarla. Empaque y salida siguen bloqueados mientras `consulta_actualizacion_pendiente` es verdadero.

## Servicios reutilizados

- Cobertura: `CoberturaPagoPedidoBmaService` y `PagosPedidoBmaConfig` (centavos, tolerancia existente).
- Envío: `CalcularTotalesEnvioPedidoService`. La pantalla usa `calcularTotalACubrirPedido` con el mismo criterio: si el campo ya incluye la zona, no se suma de nuevo; si el campo es solo flete (`envioEsBase`), la zona entra una vez. Alcance `caja` no agrega la zona al pedido.
- SAF: `SincronizarAplicacionesPedidoSafService`, `ReservarSaldoSafService`. Tras reservar o aplicar se recalcula `total_a_cobrar` con el envío que usa la cobertura. Si el detalle está incompleto, no se inventa el costo de envío.
- Evidencia: documentos `evidencia_condicion` y `ResponderPesajePedidoBmaService`.

## Identidad y evidencia

La identidad del renglón es `revision_id`. Si el producto o el SKU cambian, no se actualiza ese renglón: se crea otro y el anterior queda con `retirada_at`. La evidencia no se copia.

La foto vigente del producto cubre daño solo si el renglón es del pedido, no está retirado y conserva producto y condición. Un cambio de condición deja el antecedente y pide evidencia nueva.

La foto del lote o de la caja cubre el paquete solo si no cambian producto, SKU, cantidades, bultos ni pesos. Una foto de daño no acredita el contenido nuevo de la caja. No se clonan archivos ni se borran los anteriores.

## Archivos y migraciones

Nuevas, retrocompatibles, no aplicadas en MySQL de Sail:

- `database/migrations/2026_10_15_210000_add_retirada_at_to_pedido_bma_revisiones_producto.php`
- `database/migrations/2026_10_15_210100_add_idempotency_key_to_pedido_bma_pagos.php`

Hasta que existan esas columnas, un pago se registra como antes (sin clave de reintento) y una actualización de consulta que ya tiene renglones se detiene con un mensaje: no se guarda para no perder evidencia. La primera consulta, sin renglones previos, sí puede guardarse. La preparación abre aunque falte la migración de abastecimiento de Q6.1.

Código principal: `VisibilidadTareaPreparacion`, `CalcularTotalesEnvioPedidoService`, `RegistrarPagoPedidoBmaService`, `SincronizarAplicacionesPedidoSafService`, `ResponderPesajePedidoBmaService`, `PedidoBmaRevisionProducto`, `CrearRecepcionEsperadaPdvService`, formulario de pedido, sección de pagos, modales CEDIS y resumen `resumenActualizacionConsulta.js`.

## Pruebas ejecutadas

Sail, SQLite en memoria. No es la suite completa.

- `Q8AccesoPagosSafEvidenciaTest`: 9 pruebas, incluidas pago exacto a crédito sin excedente ni SAF, reintento, parcial más rechazado, excedente real, zona una vez en detalle de cajas, SAF por encima del disponible, evidencia sin cambio, retirada, sustitución, cambio de cantidad y aviso de esquema. Resultado: pasaron.
- Con ellas, en la misma corrida anterior al ajuste de columnas ausentes: `BlindarPagoTotalPedidoTest`, `IntegracionPedidosYCajaSafTest`, `Fase2EnviosCajasTest`, `TrasladosInternosQ6Test`, `DefinicionesOperativasQ52Test`. 54 pruebas, 327 aserciones, pasaron.
- Después del aviso por migración ausente: `Q8AccesoPagosSafEvidenciaTest` y `TrasladosInternosQ6Test`, 24 pruebas, pasaron. Luego la prueba de cantidad, 1 prueba, pasó.
- Vitest: `resumenActualizacionConsulta.test.js` y `resolverReexpedicionForm.test.js`, 9 pruebas, pasaron.

## Verificación manual

No hubo recorrido autenticado en el navegador. No se comprobó la consola del modal ni un guardado real desde Tienda, Ventas o CEDIS. HTTP y estas pruebas no sustituyen esa revisión.

Sí se comprobó en la base de trabajo, sin migrarla, que el payload de la tarea 1 ya no lanza el SQL de `tarea_receptora_id` y devuelve el aviso de abastecimiento.

## Compatibilidad, riesgos y reversión

Se conservan traslados internos sin pesaje ni guía comercial, abastecimiento cuando el esquema existe, recepción parcial, separación recepción/custodia/entrega, inventario sin sincronización, anaqueles retirados, captura sin remisión anticipada, direcciones y O02. No hay modalidad «solo documental».

Riesgo: en MySQL de Sail, actualizar una consulta que ya tiene renglones falla con el mensaje de migración hasta aplicar `retirada_at`. El reintento de pago no es idempotente hasta aplicar `idempotency_key`. Pedidos como el 35 y el 42 no se corrigieron; un pago ya registrado con excedente por el cálculo viejo sigue en el libro.

Reversión: quitar estos archivos y las dos migraciones si aún no se aplicaron. Si ya se aplicaron, `down` elimina `retirada_at` e `idempotency_key` sin borrar documentos.

## Pendiente, separado de Q8.1

Q8.1, no hecho aquí: rediseño de encabezados, pies de modales y tarjetas CEDIS; más área operativa en móvil; acciones compactas; origen y tipo de pedido más visibles; badges más legibles de «Única pieza» y «Mejor ejemplar».

También pendiente, y no es Q8.1: aplicar en la base operativa las migraciones de abastecimiento (Q6.1), `retirada_at` e `idempotency_key`; recorrer en navegador los perfiles de Tienda, Ventas y CEDIS; proponer aparte, sin ejecutarla, la reparación de exhibiciones históricas infladas.
