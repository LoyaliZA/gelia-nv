# Reversión de beneficios TAG — Fase 2B

Fecha: 10 de noviembre de 2026. Base: `develop`, `725e3724`.

**Estado: diseño y pruebas de aceptación. Sin implementación.** No fusionar este documento como si la reversión ya existiera. No activa la autoridad de Escalonamiento, no cambia períodos y no escribe `monto_venta_actual`.

Depende del contrato de Fase 2A: la cobertura documental se libera al cancelar, rechazar o vencer, y el pago no entra en el acumulado oficial. Esta fase no vuelve a asignar documentos ni concilia por un delta del CSV.

## Qué se concede hoy

`aplicarBeneficios` copia, sin tocar `monto_venta_actual`:

- la lista solicitada, si el cliente no está inactivo, la lista no está bloqueada y el módulo no lo gobierna;
- el tipo solicitado, si el cliente no está inactivo;
- el vendedor, solo cuando el proceso asigna TAG o cliente.

Eso ocurre al aprobar una solicitud financiera y al confirmar el cambio de lista. `evaluarCompraCliente` propone esa lista sumando el histórico oficial y la cotización. La suma no es una venta confirmada.

`revertirBeneficios` recalcula la lista solo con `monto_venta_actual`, borra el tipo si la solicitud lo tenía y pone el monto aplicado en cero. No restaura el vendedor anterior. La cancelación omite el recálculo si hay una solicitud financiera posterior en Respondida o Verificada. El rechazo y el vencimiento no hacen esa comprobación.

## Contrato

1. Wizerp sigue siendo el acumulado oficial mientras la autoridad esté apagada. La reversión no escribe `monto_venta_actual` ni `cubierto_por_carga_masiva`.
2. Un beneficio se conserva si la condición oficial actual lo justifica o si otra solicitud vigente del mismo cliente todavía lo sostiene.
3. La lista resultante es la que califica `monto_venta_actual`, salvo lista bloqueada o cliente gobernado por el módulo. Cancelar una cotización no baja de la lista oficial. Una lista provisional no se conserva si ni el oficial ni una solicitud vigente la sostienen.
4. Tipo y vendedor vuelven al valor anterior a la concesión que se revierte, salvo que otra solicitud vigente pida el valor actual.
5. Una solicitud cancelada, rechazada o vencida no sostiene beneficios. Repetir la reversión no concede ni quita dos veces.
6. Cada reversión guarda el antes y el después de lista, tipo y vendedor, la solicitud que originó el movimiento y las solicitudes que todavía sostienen algo. No se borra auditoría para ocultar el cambio.
7. La operación es transaccional. Bloquea el cliente y después las solicitudes de ese cliente por id. Relee el estado bajo el bloqueo. Una aprobación y una cancelación concurrentes no dejan el beneficio de la solicitud excluida ni quitan el de la que sigue vigente.

## Fuera de alcance

No activar la autoridad. No cambiar períodos. No modificar el importador. No decidir la lista sumando oficial y pago. No crear una asignación documental excepcional.

## Aceptación

Las pruebas están en `tests/Fase2b/ReversionTagAceptacionTest.php`. No pertenecen a las suites Unit ni Feature. Cada una está incompleta a propósito: ejecutarlas no debe contarse como aprobación de la reversión.

- Cancelar, rechazar o vencer la única solicitud que concedió lista, tipo o TAG restaura la condición oficial o el valor anterior, sin cambiar `monto_venta_actual`.
- Otra solicitud vigente que sostiene el mismo beneficio impide quitarlo.
- Si el oficial ya califica la lista provisional, cancelar no la baja.
- Repetir la reversión no cambia el cliente por segunda vez ni duplica la auditoría de negocio.
- Revertir la primera de dos solicitudes no deshace lo que la segunda todavía justifica.
- Aprobación y cancelación concurrentes dejan el estado que explican las solicitudes vigentes.
- La marca histórica y las filas de conciliación anuladas permanecen.
- La autoridad apagada sigue leyendo `monto_venta_actual`. Una prueba con la autoridad encendida no la deja activa.
