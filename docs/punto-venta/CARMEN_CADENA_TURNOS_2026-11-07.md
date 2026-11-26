# Carmen Cadena — turnos del 7 de noviembre de 2026

Revisión de solo lectura del día operativo en Punto de Venta, sucursal CARMEN CADENA (`PDV-01`, id 1). Contrasta el reporte de Lorenzo con los eventos guardados y con el flujo actual de transferencia, pausa, espera de 20 segundos y orden de los cuadros de gerencia.

Zona horaria de los registros: America/Mexico_City. Configuración vigente de plazos: espera entre turnos de 20 segundos e inicio automático de la atención.

La cuenta que coincide con «Don Lalo» es Eduardo. No hay otra cuenta con un nombre parecido a Lalo en la sucursal ese día.

## Problema reportado

Lorenzo, en PDV, pidió revisar este movimiento:

- Se transfirió un cliente a Diego, que estaba en pausa.
- Al querer poner en pausa a Don Lalo no se marcaron los 20 segundos y el cliente le pasó automáticamente. Eso desordenó la fila.
- A partir del segundo 34 del video hizo un movimiento parecido y sí salió bien. La diferencia que señala es que la fila ya estaba en 0.
- Pide que, aunque haya fila, no pasen clientes de inmediato: en los turnos les causa problemas con la clientela.
- En la pantalla, al cambiar el estado de quien vende, los cuadros cambian de lugar. Prefieren que el orden quede fijo según fueron activados, sea que el estado pase de Atendiendo a Disponible o al revés.
- En el video, Diego estaba en el primer cuadro; al reasignarlo se movió al lugar de Gerardo y Gerardo quedó en el primer cuadro.

## Hallazgos

El reporte es cierto en el efecto de piso. A las 12:04 el cliente de Eduardo pasó a Diego y, dos segundos después, el sistema le entregó a Eduardo el siguiente de la fila, sin los 20 segundos. La pausa de Eduardo no quedó registrada.

### Secuencia de las 12:04

| Hora | Registro |
|---|---|
| 11:33:56 | Lorenzo inició la pausa de Diego (intervalo 775). |
| 11:57:44 | Alta de V-0025, Josué Pérez García. Permaneció en fila. |
| 12:00:36 | Apertura de la jornada de Eduardo. |
| 12:00:38 | V-0024, Karina Cornelio, se asignó a Eduardo. Origen: `jornada.abierta`. |
| 12:04:19 | Lorenzo transfirió V-0024 de Eduardo a Diego. La transferencia cerró la pausa de Diego. Espera registrada hasta las 12:04:39 (20 segundos, origen `pausa.finalizada`). |
| 12:04:21 | V-0025 se asignó solo a Eduardo. Origen: `pausa.finalizada`. |
| 12:04:41 | La atención de Diego sobre V-0024 inició 22 segundos después de la transferencia. |

Diego sí tuvo la espera de 20 segundos en el reloj de su atención. En el cuadro, el estado pasó de inmediato a Atendiendo: una atención abierta tiene prioridad sobre Pre-disponible. La cuenta queda como una línea dentro de la ficha («La atención inicia al terminar la cuenta»), no como el estado Pre-disponible del cuadro.

Eduardo no tuvo esos 20 segundos. No existe un intervalo de pausa suyo en el día. Con la atención de V-0025 abierta, el sistema rechaza iniciar pausa.

La fila no estaba en cero: V-0025 esperaba desde las 11:57.

### Repetición a las 12:09

| Hora | Registro |
|---|---|
| 12:09:14 | Eduardo transfirió V-0025 a Juan Alcidez. |
| 12:09:19 | V-0027 se asignó solo a Eduardo. Origen: `cooldown`. |

Mismo efecto por otra vía: quien acaba de soltar un cliente recibe el siguiente sin espera propia. Aquí el reparto lo disparó el fin de la espera de otra persona, no el cierre de una pausa.

### Movimiento con fila en cero

| Hora | Registro |
|---|---|
| 12:14:33 | Lorenzo transfirió V-0028 de Diego a Juan Alcidez. |
| 12:17:00 | Alta de V-0030, el siguiente turno. Entre la transferencia y esta alta no había fila. |

Nadie recibió un cliente de golpe. Coincide con la diferencia que Lorenzo describe: el movimiento parecido sale bien cuando la fila está en 0. El video no se revisó; el segundo 34 no se puede fijar en el reloj del sistema.

### Cuadros que cambian de lugar

El tablero de gerencia reordena a quienes están activos en cada cambio de estado:

1. Atendiendo
2. Disponible
3. Pre-disponible
4. En pausa
5. Cierre pendiente

Si el estado es el mismo, el desempate es el nombre, no la hora de activación. El orden de activación de la jornada ese día fue SAIT, Diego, Gerardo, Juan Alcidez, Isaac y Eduardo.

A las 12:04 Diego estaba en pausa, detrás de quienes atendían. Al recibir V-0024 pasó a Atendiendo y, por el nombre, quedó delante de Gerardo. Gerardo se desplazó.

De 12:16:36 a 12:18:47 Diego era el primer cuadro y Gerardo el segundo: Eduardo ya había cerrado su atención y no ocupaba un lugar entre ambos. A las 12:18:47 Diego cerró su venta, pasó a Pre-disponible y Gerardo quedó en el primer cuadro.

## Diagnóstico

Dos reglas del flujo actual producen el pase inmediato. Una tercera produce el cambio de lugar de los cuadros.

### Quien suelta el cliente no entra en espera

Los 20 segundos se aplican al cerrar una venta y al terminar una pausa (`AplicarEsperaDisponibilidadPdvService`). La transferencia cierra la atención anterior con motivo de transferencia y no aplica esa espera a quien soltó el cliente. Esa persona queda Disponible al momento, con `disponible_desde` vencido o vacío.

Eduardo quedó así a las 12:04:19 y otra vez a las 12:09:14.

### Terminar una pausa reparte la fila de inmediato

Transferir a alguien en pausa cierra esa pausa, programa la espera de quien la tenía y publica `PausaFinalizada`. El escucha `DespacharMatchmakerTrasPausaFinalizada` lanza el reparto sin esperar los 20 segundos.

Diego quedó bloqueado hasta las 12:04:39, así que no era elegible. Eduardo ya estaba libre y sin atención abierta. A los 2 segundos, el reparto con origen `pausa.finalizada` le entregó V-0025. Con esa atención abierta, la pausa que se intentaba ponerle no puede guardarse.

Con fila, el siguiente cliente pasa de inmediato a quien acaba de quedar libre. Con fila en cero no hay a quién pasarle y el movimiento se ve correcto.

### El orden visual sigue el estado, no la activación

`particionarEquipoGerencia` en `resources/js/Pages/PuntoVenta/Operacion/Partials/operacionUtils.js` ordena por la prioridad de estado y, en empate, por nombre. Cada cambio entre Atendiendo, Disponible, Pre-disponible y En pausa mueve el cuadro. Por eso Diego y Gerardo intercambiaron el primer lugar cuando uno dejó de atender y el otro siguió atendiendo.

## Posible solución

1. Al transferir, aplicar los mismos 20 segundos a quien suelta el cliente, antes de que pueda recibir otro turno automático. Así, aunque haya fila, no le cae el siguiente de inmediato y hay tiempo de pausarlo.
2. Lanzar el reparto de la fila cuando termine esa espera, no en el aviso inmediato de pausa terminada. Quien ya cumplió su espera puede seguir recibiendo turnos; la fila no se detiene para el resto.
3. Fijar los cuadros activos por la hora de apertura de la jornada y no reordenarlos cuando el estado cambie entre Atendiendo, Disponible, Pre-disponible y En pausa.

Ninguna de estas correcciones está aplicada. Esta nota solo registra el contraste del 7 de noviembre.
