# Reversión de beneficios TAG — Fase 2B

Fecha de implementación: 11 de noviembre de 2026. Rama: `feature/fase2b-reversion-beneficios`, tomada de `feature/fase2a-conciliacion-documental`. No parte de `develop` y no se fusiona sola.

**Estado: implementación en borrador. No fusionar ni desplegar.** No activa la autoridad de Escalonamiento, no cambia períodos y no escribe `monto_venta_actual`.

El diseño de aceptación salió del borrador https://github.com/LoyaliZA/gelia-nv/pull/30. Esa rama no es la implementación. Aquí solo se tomó el contrato.

## Qué distingue cada beneficio

- Propuesto: `evaluarCompraCliente` suma el histórico oficial y la cotización para sugerir una lista. No escribe `monto_venta_actual`, `lista_actual_id` ni una fila de `solicitud_beneficios`.
- Provisional: `aplicarBeneficios` copia lista, tipo o TAG mientras la regla lo permite, y guarda una fila `vigente` con el valor anterior y el aplicado. Ocurre al aprobar una solicitud financiera y al confirmar el cambio de lista.
- Confirmado: la lista que califica `monto_venta_actual`, o el mismo valor sostenido por otra solicitud en Respondida o Verificada. Con la autoridad apagada el oficial es el acumulado Wizerp. Una conciliación `indeterminada` no confirma la lista.
- Revocado: la fila pasa a `revocado` o `reemplazado` y el valor solo cambia cuando esta solicitud todavía era responsable.

Una concesión sin fila previa no se borra por inferencia. La lista, en ese caso heredado, se alinea con el acumulado oficial si no hay otra concesión vigente, la lista no está bloqueada y el cliente no está inactivo. Tipo y vendedor no se vacían si esta solicitud no los concedió.

## Reversión

Al cancelar, rechazar o vencer se identifican las filas `vigente` de esa solicitud. Si el valor actual ya no coincide, se marca `reemplazado` y no se toca al cliente: una asignación posterior o una ratificación lo cambió. Si otra solicitud vigente tiene una concesión del mismo valor, se conserva. Si esta solicitud sigue siendo la responsable, la lista vuelve a la que califica el acumulado y el tipo o el TAG vuelven al valor anterior.

La lista bloqueada y el cliente gobernado por el módulo no se recalculan fuera de su regla. Un cliente inactivo no recibe una escritura de lista, tipo ni vendedor; la fila se cierra y el valor almacenado permanece. Ese valor almacenado sigue siendo un riesgo de los consumidores que leen `lista_actual_id` sin mirar la inactividad.

`monto_aplicado_al_cliente` vuelve a cero, como ya ocurría. `monto_venta_actual`, `cubierto_por_carga_masiva` y las filas de conciliación no se borran. `liberar()` sigue retirando solo cobertura `asignada`.

La cancelación ya no omite toda la reversión por existir una solicitud posterior. Esa comprobación queda limitada a la rebaja explícita de lista. Cada campo se decide por su propia concesión.

## Bloqueos

El diseño pedía bloquear primero al cliente y después las solicitudes. Eso se invierte a propósito. Fase 2A ya bloquea la solicitud y después el cliente en el pago, y la solicitud antes que sus documentos en la conciliación. El orden de esta fase es:

1. solicitudes del cliente, por id ascendente;
2. cliente;
3. filas de `solicitud_beneficios`, por id;
4. cobertura documental, dentro de `liberar()`, que vuelve a tomar la solicitud ya bloqueada.

Cancelación, rechazo, vencimiento, confirmación de pago, cambio de estado y confirmación de lista toman ese orden al inicio de su transacción. No se bloquea al cliente antes que las solicitudes.

## Pendiente

La corrida concurrente de MySQL 8.4 con `scripts/qa/wizerp-mysql.sh` sigue sin Docker en este equipo. La concurrencia de esta fase, cuando puede ejecutarse, usa solo `gelia_wizerp_test`. SQLite no acredita esa concurrencia.

No hay un llamador nuevo de `conciliar()`. La inclusión indeterminada no promueve la lista.
