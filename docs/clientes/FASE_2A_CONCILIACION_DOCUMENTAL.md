# Conciliación documental — Fase 2A

Fecha: 10 de noviembre de 2026. Base: `develop`, `02735c3` (merge del PR #26).

**Estado de entrega: PR en borrador. No fusionar ni desplegar.** GELIA decide la conciliación. Un archivo importado no prueba que un documento esté incluido en el acumulado, y `conciliar()` no tiene llamador de producción. No hay API con el origen del reporte. El incidente del 12 de noviembre de 2026 conserva ese nombre. La migración nueva no se ejecutó sobre la base de la aplicación. Esta fase no activa la autoridad de Escalonamiento ni cambia sus períodos. La reversión integral de TAG queda en Fase 2B.

## Diseño elegido

Se reutiliza `escalonamiento_conciliaciones_solicitud` y `ConciliacionSolicitudEscalonamiento`. No hay una segunda tabla ni un segundo motor de conciliación. La fila existente ya unía solicitud y `DocumentoVenta`; Fase 2A le añade el contrato que faltaba: importe atribuible, estado de la conciliación, procedencia e inclusión respecto del acumulado oficial.

`DocumentoVenta.clave_documento` sigue siendo `TIPO|SERIE|SUCURSAL|folio`. La conciliación automática no busca por parecido ni por el delta del CSV. Exige un folio no vacío (`numero_remision` de la solicitud, recortado) que coincida exactamente con `folio` o `remision_original`, del mismo cliente, documento activo, moneda MXN y total mayor que cero. Cero o varios documentos verificables no eligen uno.

`conciliar` no modifica `cubierto_por_carga_masiva` ni `monto_venta_actual`. No llama a `sincronizarIncidenciaCobertura`; esa sincronización permanece en la asignación y el retiro manuales. La importación (`ProcesarFilaClienteAction`) no crea conciliaciones. `marcarCubiertasPorCarga` sigue devolviendo 0.

## Contrato

| Pieza | Regla |
| --- | --- |
| Solicitud | Si el pago está confirmado, el reclamo es `monto_aplicado_al_cliente`, aunque sea cero. Si no, es el tentativo positivo o `monto_cotizado`. |
| Documento | Identidad verificable y `total` propio. El disponible es `total` menos otras filas en estado `asignada`. |
| Importe atribuible | Solo una fila `asignada` consume disponible. El automático asigna `min(objetivo, disponible)`. Si el objetivo o el disponible es cero, la fila queda `sin_disponibilidad` con importe 0. La asignación manual usa el mismo contrato de identidad y rechaza un importe mayor que el disponible o que el monto confirmado. |
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

## Asignación manual

`asignar()` y `conciliar()` comparten `motivoInelegible()`. No hay una segunda validación. Antes de escribir, `asignar()` bloquea la solicitud y vuelve a leer el documento. Rechaza, sin crear cobertura, cuando falla cualquiera de estas condiciones: mismo cliente, folio exacto o remisión original, documento activo, MXN, total positivo, período de la operación, un solo documento verificable, pago confirmado, importe positivo que no supera el monto confirmado ni el disponible, y solicitud que no esté cancelada, rechazada o vencida.

No existe una asignación excepcional silenciosa. Una excepción legítima —vincular un documento que no cumple esa identidad— tendría que ser un flujo distinto, con permiso propio, motivo obligatorio y una fila de auditoría que no use el estado `asignada` ni consuma disponible. Ese flujo no está habilitado.

El orden de bloqueo es solicitud, luego documentos por id, luego filas de conciliación. Cancelación, rechazo de pago y vencimiento bloquean la solicitud antes de cambiar el estado y liberan la cobertura dentro de la misma transacción. Una asignación concurrente espera ese bloqueo y no reactiva una solicitud ya cancelada, rechazada o vencida.

## Cobertura obsoleta

`resolverObsolescencia()` retira la cobertura `asignada` cuando la identidad ya no es válida: cambia el folio, el cliente, el estado de la solicitud, el pago deja de estar confirmado, el documento se desactiva o deja de ser el único verificable, o el importe asignado supera el monto confirmado. La fila no se borra. El importe y la evidencia anterior permanecen, y se añade el motivo del retiro. Si el documento sigue siendo válido y el monto confirmado baja, el importe se reduce. Si el monto confirmado sube, este método no concede más cobertura: eso solo lo hace una llamada explícita a `conciliar()`.

La solicitud llama al resolvedor al cambiar folio, cliente, montos, pago o estado, y al eliminarse. Un alta o un cambio de documento programa el mismo resolvedor al confirmar su transacción. Esas llamadas no crean una conciliación nueva y no modifican `monto_venta_actual` ni la marca histórica.

## Dónde se concilia

`conciliar()` no tiene ningún llamador de producción. Confirmar un pago, registrar un documento, validar una remisión y actualizar el acumulado oficial son hechos distintos. Ninguno de ellos ejecuta `conciliar()` ni modifica `monto_venta_actual` por esta fase.

Esos hechos no prueban lo mismo:

1. El pago confirmado acredita el importe reclamado de la solicitud, no una venta oficial.
2. El documento registrado y verificable acredita identidad, cliente, moneda y el tope disponible.
3. La venta incorporada al acumulado oficial solo puede afirmarse cuando la autoridad está activa y el movimiento del documento ya está en el período. Con la autoridad apagada la inclusión queda `indeterminada`.
4. La evidencia insuficiente —folio ausente, varios documentos o documento ajeno— no elige un documento y no se resuelve por la diferencia del CSV.

El punto correcto para una conciliación explícita y autorizada es una operación que ya tenga el pago confirmado y un único documento verificable. Aun en ese caso, mientras la inclusión siga `indeterminada`, esa operación no puede promover la lista. La promoción exige la prueba de que el importe ya está en el acumulado oficial. El CSV actual no identifica documentos, así que esa prueba no existe con la autoridad apagada. Por eso se conserva el comportamiento seguro: `confirmarPago` no llama a `conciliar()`. El resolvedor solo retira cobertura que dejó de ser válida.

## Consumidores revisados

`proyectarMonto` decide con la venta oficial. `confirmarPago` no suma el pago al acumulado ni asciende la lista. `confirmarCambioLista` ya no recalcula la lista con oficial más cotización. `revertirBeneficios` recalcula la lista solo con `monto_venta_actual` y libera el documento. `capaPendiente` no se suma al oficial; con marca histórica o solicitud excluida devuelve cero. `estaCubierta` solo lee la marca.

`evaluarCompraCliente` sigue proponiendo la lista solicitada sumando el histórico y la cotización. Esa propuesta se guarda en la solicitud. `aplicarBeneficios`, al aprobar o al confirmar el cambio de lista, puede copiar esa lista, el tipo y el TAG al cliente. No toca `monto_venta_actual`. Revertir esos beneficios de forma consistente, sin quitar un beneficio que otra solicitud o la condición oficial todavía justifican, es Fase 2B. No se reescribió ese evaluador en esta fase.

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
10. **`conciliar()` no está conectado a producción.** Confirmar el pago, registrar el documento o importar el CSV no crean la cobertura. El resolvedor sí retira la que dejó de ser válida. Un documento nuevo la resuelve al confirmar su transacción; una prueba envuelta en una transacción que nunca confirma no ejecuta ese callback. El método se comprobó llamándolo de forma directa.
11. **Lista solicitada por cotización.** `evaluarCompraCliente` sigue sumando histórico y cotización para proponer la lista de la solicitud. `aplicarBeneficios` puede copiarla después. No altera `monto_venta_actual`. Su reversión consistente es Fase 2B.

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

## Criterios cumplidos y pendientes

Cumplidos en las pruebas de esta revisión: orden CSV/pago, pago y conciliación sin mover `monto_venta_actual`, varias solicitudes y un documento compartido, repetición de pago y de conciliación, cancelación y vencimiento, marca histórica, autoridad apagada y encendida en pruebas aisladas, identidad ambigua, rechazo de asignación manual inválida, retiro de cobertura obsoleta, fila previa a la migración, rollback, dos operaciones concurrentes sobre la misma solicitud, cancelación concurrente con asignación manual y dos confirmaciones HTTP simultáneas.

Pendiente: `scripts/qa/wizerp-mysql.sh` en MySQL 8.4. El script solo crea un contenedor efímero `gelia-wizerp-mysql-test`, base `gelia_wizerp_test`, red `gelianv_sail`, y lo elimina al salir. No apunta a la base `gelia`. Las pruebas de Clientes que incluye sí ejecutan migración fresca y truncado, pero únicamente sobre ese contenedor. Este equipo no tiene Docker ni Podman, así que esa corrida no se ejecutó y no se da por aprobada. El procedimiento reproducible en el servidor de pruebas es ejecutar el script tal como está, sin redirigirlo a la base operativa. El build de Vite tampoco: no hay cambio de interfaz.

## Verificación en este entorno

Repetición final del 11 de noviembre de 2026 sobre `63d42f1`. Sail y Docker no están disponibles. SQLite en memoria, PHP 8.4.27 del host, cachés aisladas. La base `gelia` no se migró y conservó 353 tablas. El flujo de CI de ese commit terminó en éxito; no cierra el límite de inclusión ni sustituye MySQL 8.4.

- `ConciliacionDocumentalSolicitudTest`, `ConciliacionSolicitudEscalonamientoTest`, `MontoSolicitudVsCargaMasivaTest`, `IntegridadImportacionWizerpTest`, `ProteccionWizerpCierreTest`, `AutoridadEscalonamientoTest`: **72 aprobadas, 336 aserciones**.

MySQL 8.0.46, esquema exclusivo `gelia_wizerp_test`, migrado vacío —no con `migrate:fresh`— y eliminado al terminar. No se usó `ALLOW_DESTRUCTIVE_DB`.

- `ConciliacionDocumentalMySqlTest`: **6 aprobadas, 32 aserciones**. Incluye rollback, dos conciliaciones del mismo documento, conciliación que espera un pago bloqueado, conciliar y asignar la misma solicitud, cancelación concurrente con asignación, y dos confirmaciones HTTP simultáneas.
