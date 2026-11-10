# Conciliación documental — Fase 2A

Fecha: 10 de noviembre de 2026. Base: `develop`, `02735c3` (merge del PR #26).

**Estado de entrega: PR en borrador. No fusionar ni desplegar.** La migración nueva no se ejecutó sobre la base de la aplicación. Esta fase no activa la autoridad de Escalonamiento ni cambia sus períodos. La reversión integral de TAG queda en Fase 2B.

## Diseño elegido

Se reutiliza `escalonamiento_conciliaciones_solicitud` y `ConciliacionSolicitudEscalonamiento`. No hay una segunda tabla ni un segundo motor de conciliación. La fila existente ya unía solicitud y `DocumentoVenta`; Fase 2A le añade el contrato que faltaba: importe atribuible, estado de la conciliación, procedencia e inclusión respecto del acumulado oficial.

`DocumentoVenta.clave_documento` sigue siendo `TIPO|SERIE|SUCURSAL|folio`. La conciliación automática no busca por parecido ni por el delta del CSV. Exige un folio no vacío (`numero_remision` de la solicitud, recortado) que coincida exactamente con `folio` o `remision_original`, del mismo cliente, documento activo, moneda MXN y total mayor que cero. Cero o varios documentos verificables no eligen uno.

`conciliar` no modifica `cubierto_por_carga_masiva` ni `monto_venta_actual`. No llama a `sincronizarIncidenciaCobertura`; esa sincronización permanece en la asignación y el retiro manuales. La importación (`ProcesarFilaClienteAction`) no crea conciliaciones. `marcarCubiertasPorCarga` sigue devolviendo 0.

## Contrato

| Pieza | Regla |
| --- | --- |
| Solicitud | Reclamo = `monto_aplicado_al_cliente` si el pago está confirmado y es positivo; si no, `monto_cotizado`. |
| Documento | Identidad verificable y `total` propio. El disponible es `total` menos otras filas en estado `asignada`. |
| Importe atribuible | Solo una fila `asignada` consume disponible. El automático asigna `min(objetivo, disponible)`. Si el objetivo o el disponible es cero, la fila queda `sin_disponibilidad` con importe 0. La asignación manual que pide más que el disponible se rechaza. |
| Estado | `asignada`, `pendiente`, `indeterminada`, `sin_disponibilidad`, `anulada`, `reemplazada`. `pendiente` e `indeterminada` no consumen disponible. |
| Procedencia | `documento`, `historica_sin_respaldo`, `identidad_insuficiente`, `identidad_ambigua`, `pago_no_confirmado`. |
| Inclusión | `incluida`, `fuera` o `indeterminada`, calculada al conciliar y guardada en la fila. Volver a conciliar la recalcula. Cambiar la autoridad no reescribe filas ya guardadas. |
| Clave | `doc:{id}` o `sin-documento`. Única por `(solicitud_tag_id, clave_asignacion)`. Repetir la operación actualiza la misma fila. |
| Decisión | `proyectado` es igual a `venta_oficial`. Las demás cubetas se informan y no se suman. |

Estados de la conciliación automática:

- Solicitud Cancelada, Incorrecta o Rechazada: se anulan las asignaciones y queda un marcador `sin-documento` anulado. El reclamo va a `excluidas`.
- Varios documentos verificables: `indeterminada`, procedencia `identidad_ambigua`, sin documento elegido.
- Marca histórica y ningún documento verificable: `indeterminada`, procedencia `historica_sin_respaldo`, importe 0. La marca no se borra ni se reconstruye.
- Folio sin documento, o folio vacío sin marca: `indeterminada` o `pendiente`, procedencia `identidad_insuficiente`.
- Documento único y pago no confirmado: `pendiente`, importe 0, procedencia `pago_no_confirmado`. No consume disponible.
- Documento único y pago confirmado: `asignada` hasta el disponible, o `sin_disponibilidad` si no queda monto.

Inclusión:

- Autoridad apagada: siempre `indeterminada`. El CSV no identifica documentos, así que un documento verificable no prueba que su monto esté dentro ni fuera de `monto_venta_actual`.
- Autoridad encendida, período distinto del operativo, sin movimiento o con efecto 0: `fuera`.
- Autoridad encendida y efecto distinto de cero en el período operativo: `incluida`.

Esa clasificación es el límite de integridad, no un comportamiento provisional. No se inventa la asociación porque la venta aumentó, ni se resta un monto legítimo del acumulado oficial.

## Proyección

`ProyeccionClienteEscalonamiento::montoAcumulado` no cambia. Con autoridad apagada lee `Cliente.monto_venta_actual`. Con autoridad encendida lee el acumulado del módulo en el período operativo.

`proyectarMonto` ya no suma `monto_venta_actual` y el monto de la solicitud, y ya no trata `cubierto_por_carga_masiva` como cobertura. El desglose separa:

- `venta_oficial`: acumulado publicado por la autoridad vigente.
- `cotizacion_pendiente`: reclamo no pagado que no está asignado.
- `pago_confirmado_sin_conciliar`: parte pagada que no está en una fila `asignada`.
- `venta_respaldada_en_acumulado`: importe `asignada` con inclusión `incluida`.
- `venta_documentada_fuera`: importe `asignada` con inclusión `fuera`.
- `venta_documentada_inclusion_indeterminada`: importe `asignada` cuya inclusión no es `incluida` ni `fuera`.
- `historica_sin_respaldo`: reclamo con marca histórica y ninguna fila `asignada`.
- `excluidas`: reclamo de solicitudes canceladas, rechazadas o vencidas.

`proyectado` y `base` son `venta_oficial`. `aplicara_monto` es falso. Una asignación parcial deja el resto en pago o cotización, según el pago esté confirmado. No se resta el oficial. No se suma lo incluido, lo indeterminado, lo histórico ni lo excluido.

Efecto de lista, solo para la solicitud en curso:

- `excluida` si la solicitud está cancelada, rechazada o vencida.
- `retenida` si la cobertura no está determinada por completo como `incluida`, o si la marca histórica no tiene documento. La lista no cambia.
- `pago_insuficiente` solo cuando el reclamo está cubierto por asignaciones `incluida`, no hay resto ni inclusión `fuera`/`indeterminada`, y el oficial es menor que el `monto_requerido` de la lista solicitada.
- `sin_cambio` cuando esa comparación no exige un rechazo.

Confirmar el pago no llama a `conciliar` y no suma el pago a `monto_venta_actual`. `confirmarPago` solo rechaza en `pago_insuficiente`. En cualquier otro efecto registra el pago con `asignarListaSolicitada` en falso y conserva la lista. Una segunda confirmación, con la solicitud ya pagada, sale sin otra auditoría y sin cambiar `monto_aplicado_al_cliente`: vale el primer monto confirmado. `confirmarCambioLista` ya no sustituye la lista solicitada por una lista calculada con oficial más cotización. `aplicarBeneficios` sigue copiando una lista ya solicitada; revertir ese beneficio es Fase 2B.

El ascenso de lista por una venta real de Wizerp sigue ocurriendo en la importación, cuando el monto del CSV califica una lista superior. Un pago o un documento de inclusión indeterminada no ascienden la lista.

## Marcas históricas

No se modifica ni se reconstruye `cubierto_por_carga_masiva`.

- Sin documento único verificable: la conciliación queda indeterminada, importe 0, procedencia `historica_sin_respaldo`. El reclamo se informa en `historica_sin_respaldo` y no se suma ni se resta.
- Con documento único verificable: manda el documento. La marca permanece. El importe asignado sigue las reglas de disponible e inclusión.
- `capaPendiente` devuelve 0 si la solicitud está excluida o conserva la marca. Ese valor no se suma a `monto_venta_actual`. La proyección usada para decidir es `desglose`, no este auxiliar.

La migración rellena filas previas con clave `doc:{id}` o `sin-documento:{id}`, procedencia `documento` o `identidad_insuficiente`, e inclusión `indeterminada`. No las recalcula en masa. El período pasa a ser anulable para poder registrar una conciliación indeterminada sin abrir un período. `down()` elimina las filas sin período antes de volver a exigir el período. No se cambian las reglas de períodos.

## Integridad y concurrencia

- Pago: transacción, bloqueo de la solicitud y después del cliente. Si `pago_confirmado` ya es verdadero, no cambia el monto.
- Conciliación: transacción, bloqueo de la solicitud, después de los documentos verificables ordenados por id, después de las filas `asignada` del documento. La clave única impide dos importes para la misma solicitud y documento.
- Si dos procesos chocan en esa clave, se conserva la fila existente. El manejador de la violación única devuelve la fila más reciente de la solicitud; la restricción evita el segundo importe, aunque el valor devuelto puede no ser la clave que chocó si ya existía otra fila posterior.
- Cancelación, rechazo por pago insuficiente y vencimiento de una solicitud Respondida llaman a `liberar`: las filas `asignada` pasan a `anulada` y conservan el importe como auditoría. Liberan capacidad del documento. No revierten TAG ni alteran `monto_venta_actual`.
- La importación sigue bloqueando el cliente por número y no escribe solicitudes.

## Compatibilidad

Se conservan las reglas de Fase 1: el CSV no infiere cobertura, Wizerp sigue siendo la autoridad de la venta real y de la inactividad, y la autoridad de Escalonamiento no se activa. Los cambios de integración se limitan a dejar de decidir la lista con una suma ambigua y a liberar la capacidad documental al cancelar, rechazar o vencer. No hay reversión completa de TAG.

## Riesgos residuales

1. **Inclusión indeterminada con autoridad apagada.** Un documento verificable prueba identidad y monto, y puede asignarse hasta su disponible. No prueba que ese monto esté dentro del CSV. Esas filas no se suman a `proyectado` ni se restan de `monto_venta_actual`. La lista queda `retenida`. GELIA no asciende una lista por un pago ni por un documento que el acumulado oficial no haya publicado. Este límite permanece mientras el CSV no identifique documentos.
2. **Inclusión `fuera`.** Se informa y no se suma. Un efecto cero puede ser una exclusión deliberada del módulo; un período distinto no se mezcla con el operativo.
3. **Inclusión almacenada.** Cambiar la autoridad después de conciliar no actualiza la fila. Hay que volver a conciliar o asignar para recalcularla. El relleno de la migración deja las filas anteriores en `indeterminada` hasta esa operación.
4. **Marca histórica con un pago posterior y sin documento.** `desglose` clasifica todo el reclamo como histórico si no hay fila `asignada`. `capaPendiente` también devuelve 0. No se suma, y tampoco aparece como pago sin conciliar.
5. **`pago_insuficiente` en el primer HTTP.** La primera confirmación, sin asignación previa `incluida`, queda `retenida` y no rechaza. El efecto `pago_insuficiente` se produce cuando una solicitud aún no pagada ya tiene asignaciones `incluida` y el oficial no alcanza la lista. La rama del controlador existe; el recorrido ordinario de la primera confirmación no entra en ella.
6. **Fase 2B.** Rechazo, vencimiento y cancelación liberan el documento y recalculan la lista desde el oficial. No resuelven la procedencia ni la vigencia de beneficios y TAG. `confirmarCambioLista` todavía puede aplicar una lista ya solicitada mediante `aplicarBeneficios`.
7. **Vigencia del CSV.** Repetir una fotografía antigua puede sobrescribir una más reciente. Fase 1, punto 5, sigue abierto.
8. **Consumidores externos.** La lista almacenada de un cliente inactivo y los lectores que no pasen por la proyección conservan el límite descrito en Fase 1.
9. **Valor devuelto ante una carrera de clave única.** La restricción evita el importe duplicado. El retorno puede ser otra fila más reciente de la misma solicitud.

## Criterios de aceptación

- El CSV antes o después del pago no crea conciliaciones, no marca `cubierto_por_carga_masiva` y no cambia el resultado de `proyectado`.
- Confirmar un pago no modifica `monto_venta_actual`. Conciliar después tampoco. El pago no conciliado queda en `pago_confirmado_sin_conciliar` y no entra en `proyectado`.
- Dos solicitudes del mismo cliente no suman dos veces el mismo componente al `proyectado`.
- Un documento puede respaldar varias solicitudes solo hasta su `total`. El excedente queda `sin_disponibilidad` o se rechaza en la asignación manual. La suma `asignada` no supera el total.
- Repetir la confirmación no cambia `monto_aplicado_al_cliente` ni crea otra auditoría. Repetir la conciliación no crea otra fila ni otro importe.
- Cancelar o vencer anula la asignación, libera el disponible y no resta la venta oficial.
- Una marca histórica sin documento queda indeterminada, conserva la marca y no se suma ni se resta.
- Con autoridad apagada, un documento asignado queda `indeterminada` y `proyectado` sigue siendo el oficial.
- Con autoridad encendida, un documento `incluida` no se vuelve a sumar. Un documento `fuera` se informa y no se suma. Estas pruebas aisladas no dejan la autoridad activa.
- Una identidad ambigua o insuficiente no elige documento.
- El rollback deshace la conciliación. Dos conciliaciones concurrentes del mismo documento no superan su total. Una conciliación que espera un pago bloqueado usa el monto confirmado, no la cotización anterior.

## Verificación en este entorno

Sail y Docker no están disponibles. Las pruebas PHPUnit de SQLite se ejecutaron con el PHP del host y cachés aisladas. La base `gelia` no se migró.

MySQL: se creó el esquema exclusivo `gelia_wizerp_test` en el servidor local 8.0.46, se migró ese esquema vacío y se ejecutó `tests/Integration/Solicitudes/ConciliacionDocumentalMySqlTest.php`. El esquema se eliminó al terminar. No se usó `migrate:fresh` ni `ALLOW_DESTRUCTIVE_DB`. No se ejecutó `scripts/qa/wizerp-mysql.sh`: ese script depende de Docker y de un contenedor MySQL 8.4, y las pruebas de Clientes que incluye usan truncado con migración fresca sobre ese contenedor. SQLite no acredita los bloqueos de InnoDB; la tanda MySQL de esta fase sí los ejecutó, en 8.0.46 y no en el contenedor 8.4.

No hay cambio de interfaz. No se publicó `public/build` y no se corrió el build aislado de Vite, porque Sail no está disponible y no hay assets nuevos.
