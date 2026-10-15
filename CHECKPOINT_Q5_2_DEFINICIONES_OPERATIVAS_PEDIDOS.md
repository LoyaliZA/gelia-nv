# CHECKPOINT Q5.2 — Definiciones operativas de pedidos

## 1. Conclusión

Quedaron aplicadas las tres decisiones que Q5.1 dejó abiertas, sin nueva modalidad documental y sin migración.

1. **O02.** Un transporte `local_regional` con `requiere_peso = false` responde y avanza como consulta de mercancía, aunque el origen tenga `requiere_logistica`. No se piden kilogramos de CEDIS. La revisión física, los bultos y la evidencia se conservan. La caja se pide solo si `requiere_caja` es verdadero.
2. **O03.** Resuelto por definición. La operación es separación y revisión física, con respuesta de Tienda o de CEDIS, y sin kilogramos cuando el recorrido no los exige. No hay modalidad, estado ni responsable “solo documental”.
3. **CEDIS → PDV.** La salida que no es punto de venta más la sucursal destino reconocen el traslado interno. No exige paquetería ni guía comercial, registra la salida hacia recepción y custodia en PDV, y no crea una tarea de preparación en Tienda por el destino.
4. **Anaqueles.** Salen de la preparación, del apartado y del retorno físico. El retorno sigue siendo un hecho distinto de la cancelación, la entrega y la liberación de inventario. El estado técnico `DEVUELTA_ANAQUEL` se conserva; la etiqueta visible es «Devuelta al almacén».

Se conservan los arreglos de Q5.1: preparación sin ubicación obligatoria, bloqueo de empaque y envío con consulta pendiente, y alertas de retraso distintas. No se tocó el ancla de 24/48 horas, la clasificación de `MUNICIPIO_DIFERIDO` ni el crédito.

No hay commit, push ni migración. No se ejecutó nada sobre una base operativa.

## 2. Antes y después

| Escenario | Antes | Después |
|---|---|---|
| Local/regional, `requiere_peso = false`, origen con logística | Pesaje CEDIS con kilos, caja y volumétrico | Consulta de mercancía: piezas, condición, bultos y evidencia. Sin kilos |
| Local/regional, `requiere_peso = true` | Pesaje CEDIS | Sigue exigiendo peso real de CEDIS |
| Comercial con logística, aunque `requiere_peso` sea falso | Pesaje CEDIS | Igual. La categoría comercial no entra en la exención |
| Misma exención con `requiere_caja = true` | La caja iba mezclada con el pesaje | Pide tipo de caja y bultos, y no pide kilogramos |
| Preparación de Tienda | Matriz propia; sin filas de pesaje CEDIS | Igual. El peso sale de `efectivos()` |
| CEDIS → PDV con origen que requiere logística | La sucursal destino estaba prohibida y la paquetería era obligatoria | La sucursal se puede indicar. No pide paquetería ni guía. Al marcar la salida abre recepción en PDV |
| Salida desde almacén PDV con logística | Paquetería y rastreo según el origen | Sigue pidiendo paquetería. No es traslado interno |
| Retorno de una recogida no concretada | Exigía «ubicación final en el anaquel» y foto | Pide foto, motivo, responsable y fecha. No pide anaquel ni ubicación. El estado sigue siendo `DEVUELTA_ANAQUEL` y no sincroniza inventario |
| Histórico ya devuelto con texto de anaquel | Visible como ubicación | Sigue abriendo, muestra «Ubicación registrada» y no pide recaptura |

## 3. O02

La categoría local/regional del catálogo es `CatalogoPaqueteriaPedido::CATEGORIA_LOCAL_REGIONAL` (`local_regional`). La regla usa `categoria` y `requiere_peso`. No compara nombres de paquetería ni de departamento.

`PedidoBma::consultaCedisModoRevisionMercancia()` es verdadero si el origen no requiere logística, o si la paquetería es local/regional y `requiere_peso` es falso. Una paquetería comercial, una local con peso, o un origen con logística sin paquetería, siguen en pesaje. `requiere_logistica` no cambió de significado.

`requiere_caja` se evalúa aparte en la respuesta y en el avance. Quitar kilos no quita bultos ni evidencia, ni inventa kilogramos.

El costo diferido y `MUNICIPIO_DIFERIDO` quedan como estaban.

## 4. O03

No se creó modalidad, estado, responsable ni transición documental. PDF o foto de captura del pedido siguen siendo soporte de la solicitud, no evidencia de preparación.

La respuesta de Tienda sigue su matriz. La de CEDIS sigue siendo consulta de mercancía o pesaje, según la regla de O02. No hay excepción por el nombre Aromas o Bellaroma.

O03 no queda como autorización pendiente para inventar otro recorrido.

## 5. CEDIS → PDV

`PedidoBma::esTrasladoInternoCedisAPdv()` es verdadero cuando el pedido existe, la salida no es un almacén PDV (`AlmacenPdv`) y hay `sucursal_destino_id`.

En ese caso:

- la sucursal destino deja de estar prohibida, pero no se vuelve obligatoria en todo envío con logística;
- la validación no pide paquetería, tipo de guía, zona, código postal, domicilio ni costo comercial;
- `ofreceRastreo()` es falso y el empaque pasa a pendiente de envío, sin guía comercial;
- al quedar enviado se crea la recepción esperada en PDV;
- solicitar la consulta no crea tarea de Tienda.

Si el almacén de salida es PDV, el destino de traslado interno sigue prohibido y la paquetería comercial se conserva. Ese es el envío externo desde PDV.

Si el traslado interno no tiene cajas ni bultos capturados, la recepción usa el mismo bulto operativo que ya existía para pedidos a sucursal. No se inventan kilos ni cajas de catálogo.

Un traslado interno cuyo transporte no sea local sin peso sigue pidiendo el pesaje CEDIS. La exención de kilos es solo O02; la de paquetería y guía es solo este recorrido.

## 6. Anaqueles y retorno físico

`ConfirmarDevolucionAnaquelService` confirma el retorno físico de una recogida no concretada. Pasa de `DEVOLUCION_PENDIENTE` a `DEVUELTA_ANAQUEL`, guarda foto, responsable y fecha, y registra `inventario_sincronizado = false`. No cancela el pedido, no lo entrega y no libera inventario.

La ubicación ya no es obligatoria. Si un confirmado antiguo trae texto, se conserva y se muestra como ubicación registrada. Un confirmado nuevo puede quedar con `ubicacion` nula.

Nombres técnicos que se conservan, porque renombrarlos cambiaría datos y rutas ya guardadas:

- estado `DEVUELTA_ANAQUEL` y evento `DEVUELTA_ANAQUEL`;
- `ConfirmarDevolucionAnaquelService` y `ConfirmarDevolucionAnaquelRequest`;
- tipo de documento `devolucion_anaquel`;
- permiso `control_pedidos.tienda.apartado.confirmar_devolucion`;
- columna `ubicacion`.

Ninguno de esos nombres vuelve a pedir un anaquel en pantalla. La etiqueta visible del estado y del evento es «Devuelta al almacén». El almacén real y el resguardo o custodia en PDV siguen en su circuito.

## 7. Archivos

Sin migración.

- `app/Models/ControlPedidos/PedidoBma.php`
- `app/Models/ControlPedidos/PedidoBmaCumplimientoFisico.php`
- `app/Models/ControlPedidos/PedidoBmaCumplimientoEvento.php`
- `app/Services/ControlPedidos/ValidacionCamposPedidoBma.php`
- `app/Services/ControlPedidos/SolicitarPesajePedidoBmaService.php`
- `app/Services/ControlPedidos/ResponderPesajePedidoBmaService.php`
- `app/Services/ControlPedidos/MarcarEnviadoPedidoBmaService.php`
- `app/Services/ControlPedidos/ConfirmarDevolucionAnaquelService.php`
- `app/Services/ControlPedidos/SolicitarDevolucionCumplimientoService.php`
- `app/Services/PuntoVenta/Resguardos/CrearRecepcionEsperadaPdvService.php`
- `app/Support/ControlPedidos/MaquinaEstadosPedidoBma.php`
- `app/Support/PuntoVenta/Resguardos/CantidadBultosEsperadaResguardoPdv.php`
- `app/Http/Requests/ControlPedidos/ResponderPesajePedidoBmaRequest.php`
- `app/Http/Requests/ControlPedidos/ConfirmarDevolucionAnaquelRequest.php`
- `app/Http/Controllers/ControlPedidos/PedidoBmaCedisController.php`
- `app/Http/Controllers/ControlPedidos/PedidoBmaTiendaController.php`
- `resources/js/Pages/ControlPedidos/Partials/pedidosBmaStyles.js`
- `resources/js/Pages/ControlPedidos/Partials/destinoSucursalPedido.js`
- `resources/js/Pages/ControlPedidos/Partials/ModalFormPedidoLegado.jsx`
- `resources/js/Pages/ControlPedidos/Cedis/Partials/TarjetasCedis.jsx`
- `resources/js/Pages/ControlPedidos/Cedis/Partials/ModalResponderPesaje.jsx`
- `resources/js/Pages/ControlPedidos/Tienda/Partials/ApartadoFisicoTienda.jsx`
- `resources/js/utils/permisos.js`
- Pruebas de elegibilidad, pesaje, destino, Q5.2, validación JS y destino JS

## 8. Pruebas

Entorno Sail, SQLite `:memory:`. No se usó `ALLOW_DESTRUCTIVE_DB` ni una base operativa.

| Conjunto | Resultado |
|---|---|
| `DefinicionesOperativasQ52Test`, `ElegibilidadPesajePedidoTest`, `ControlPedidosPesajeTest`, `DestinoSucursalPedidoBmaTest`, `ElegibilidadCedisPdvPedidoTest`, `ControlPedidosFlujoParaleloTest`, `RecogeTiendaFlujoTest`, `PreparacionTiendaWorkspaceTest` | 84/84 |
| Vitest de validación de envío, destino de sucursal y apartado Recoge | 33/33 |

Cubren los once comportamientos pedidos: exención local, peso local obligatorio, comercial, caja independiente, matriz de Tienda, CEDIS → PDV, salida externa desde PDV, retorno sin anaquel, recepción en PDV, histórico con texto de anaquel, y los bloqueos de Q5.1 que ya cubrían el flujo paralelo y Recoge.

No se recorrió la interfaz autenticada en el navegador. La comprobación de pantallas es estática y de Vitest. Sigue pendiente ver, en un pedido de prueba no productivo, la consulta local sin kilos, el selector opcional de sucursal en una salida CEDIS y el retorno al almacén sin campo de ubicación.

## 9. Riesgos y pendientes

- Una paquetería local ya guardada con `requiere_peso` falso deja de pedir kilos de CEDIS. Esa es la regla aprobada; conviene revisar el catálogo si alguna local debía pesar y tiene el indicador apagado.
- Indicar sucursal destino en una salida CEDIS convierte el pedido en traslado interno y deja de exigir paquetería y guía. Sin sucursal, el envío comercial conserva esos requisitos.
- El traslado interno que no cae en O02 sigue pidiendo pesaje CEDIS.
- El retorno no reintegra inventario. Sigue marcado con `inventario_sincronizado = false`.
- Siguen sin definirse el ancla de 24/48 horas, una reclasificación de `MUNICIPIO_DIFERIDO` y políticas nuevas de crédito o contabilidad.
