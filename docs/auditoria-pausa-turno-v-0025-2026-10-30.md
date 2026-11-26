# Auditoría: pausa de Diego y turno V-0025

Fecha del incidente: 30/10/2026. Sucursal 1. Turno de fila `V-0025` (id 1048).

Personas en el registro: Diego (usuario 33), Lorenzo (usuario 32), Eduardo (usuario 29), recepción (usuario 46).

## Conclusión

Diego estuvo en el cronómetro de pausa de las 13:11:01 a las 13:44:29. En ese intervalo el sistema sí tomó un cliente de la sala de espera: `V-0025`, que entró a la cola a las 13:26:16 y fue asignado a las 13:28:49. El destinatario guardado es Eduardo. En la línea de Diego esa fila aparece en medio de la pausa, como «Registrado por Sistema», porque después Diego atendió el mismo turno.

No se sabe qué cliente ya atendido iban a reasignarle. No fue uno que Diego hubiera cerrado: la primera atención de `V-0025` es de Eduardo. Entre las 12:00 y las 15:00 no hay ningún `turno.reatencion`. La declaración de que gerencia no asignó la reatención coincide con el registro: Lorenzo no tiene ese evento, y nadie más lo tiene esa tarde.

Diego recibió a `V-0025` a las 13:44:33, cuatro segundos después de quitar la pausa, por una transferencia hecha con la sesión de Eduardo. No es un clic del apartado de reatención. La espera de 20 segundos sí quedó guardada hasta las 13:44:49. La tarjeta deja de mostrarla en cuanto existe esa atención.

## Argumento de piso

Lo que se comentó:

- No iban a esperar una asignación de fila. Querían reasignar un cliente que ya había sido atendido.
- El sistema no lo permitió.
- Al quitar la pausa, en automático le pasó un cliente de fila.
- No marcó los 20 segundos. Ocurrió de inmediato.

Qué parte confirma el registro y qué parte no:

| Dicho en piso | Registro |
| --- | --- |
| Diego estaba en el cronómetro de pausa. | Pausa de las 13:11:01 a las 13:44:29, motivo Comida, registrada por Lorenzo. |
| Iban a reasignar a mano un cliente ya atendido. No se sabe cuál, y no era uno que Diego hubiera atendido. | No hay forma de identificar ese cliente: no quedó reatención. `V-0025` no es una reatención ni una atención previa de Diego. |
| Gerencia no le asignó la reatención. | Cierto. Cero eventos `turno.reatencion` entre las 12:00 y las 15:00. Con la pausa abierta, la tarjeta de Diego ni siquiera lo ofrece como destino. |
| En medio de la pausa el sistema asignó a alguien de la sala de espera. | Cierto para el turno, con otro destinatario. A las 13:28:49 el sistema asignó `V-0025` a Eduardo. La línea de Diego lo muestra en medio de su pausa. |
| Le puso un cliente de la cola, no el reatendido. | El que Diego recibió es `V-0025`, de la cola. Le llegó a las 13:44:33 por transferencia de la sesión de Eduardo, cuatro segundos después de quitar la pausa. |

## Hechos

| Hora | Qué quedó registrado |
| --- | --- |
| 13:11:01 | Lorenzo inicia la pausa de Diego. Motivo: Comida. Intervalo 534, jornada 99. Estado: en pausa. El cronómetro de pausa queda corriendo. |
| 13:11:40 | Eduardo cierra su jornada. En ese momento también estaba en pausa. |
| 13:26:16 | Recepción da de alta `V-0025`. Queda en cola. Diego sigue en pausa y Eduardo no tiene jornada abierta. |
| 13:28:48 | Eduardo reactiva su jornada. Eso dispara el asignador con origen `jornada.abierta`. |
| 13:28:49 | El sistema asigna `V-0025` a Eduardo. Atención 1089. `snapshot.user_id` es 29. Diego sigue en pausa y no entra en la selección. |
| 13:44:29 | Lorenzo termina la pausa. Duración: 33 min 28 s. Estado: en espera. Espera 100: 20 segundos, marca anterior 13:05:40, marca nueva 13:44:49, origen fin de pausa. |
| 13:44:33 | Eduardo transfiere `V-0025` a Diego. Actor 29, destino 33, atención 1091. Faltaban 16 segundos de espera. |
| 13:44:49 | Vence la espera. El asignador entrega `V-0027` a Eduardo, origen `cooldown`. No a Diego. |
| 13:54:33 | Diego cierra `V-0025` con venta. |
| 12:00 a 15:00 | Cero eventos `turno.reatencion` en la sucursal. |

La línea de auditoría de Diego sigue mostrando la asignación de las 13:28:49 como «Registrado por Sistema» y «Origen: apertura de jornada», en medio de la pausa. Esa fila es la de Eduardo. La transferencia de las 13:44:33 no sale en esa línea.

## Diagnóstico

### 1. La reatención manual no quedó registrada, y con la pausa abierta no se podía guardar en Diego

El botón de reatención de la tarjeta solo lista turnos en ventana cuya persona destino incluye a quien se está viendo. Con la pausa abierta, Diego no es destino de ninguno. El botón queda apagado. Si no hay destinos, el texto dice «No hay clientes en ventana de re-atención», aunque a esa hora sí había clientes ya atendidos en ventana. El mensaje no dice que la pausa es lo que impide elegirlos.

No quedó intento guardado. Un clic de gerencia que sí se completara dejaría `turno.reatencion` con la cuenta de quien confirmó. No existe ninguno esa tarde, ni de Lorenzo ni de otra cuenta. La declaración de que gerencia no asignó la reatención coincide con eso. Tampoco se puede saber cuál cliente ya atendido iban a elegir.

Al terminar la pausa, durante los 20 segundos, Diego sí podía recibir una reatención manual de un cliente atendido por otra persona. Esa ventana duró hasta que, a los 4 segundos, la transferencia lo dejó con una atención abierta.

### 2. El cliente de fila no lo puso el asignador al quitar la pausa

`V-0025` estuvo 16 minutos con Eduardo. A las 13:44:33 Eduardo lo transfirió. `TransferirTurnoPdvService` no revisa pausa ni espera: basta con que el destino no tenga otra atención abierta. La lista de transferencia del tablero tampoco filtra eso.

La transferencia y el fin de pausa quedaron a 4 segundos. En la operación se ven como el mismo momento.

### 3. Los 20 segundos se guardaron y la tarjeta los tapa

`ResolverEstadoVendedorOperacionPdvService` mira primero si hay una atención abierta. Si la hay, el estado es «Atendiendo», antes de evaluar la pausa o la espera. El cronómetro de «En espera» y el texto «Volverá a la cola al terminar la cuenta regresiva» solo se muestran en `en_espera`.

Con la transferencia a los 4 segundos, esa cuenta casi no alcanza a verse. Si la pantalla se actualizó ya con el turno puesto, no se ve nunca. El registro de la espera 100 sigue en la base.

El asignador sí respetó esa marca: a las 13:44:49 no eligió a Diego.

### 4. La línea de tiempo refuerza la lectura de «el sistema se lo asignó en la pausa»

`ConsultarLineaTiempoAuditoriaOperacionPdvService` lista la asignación de cualquier turno que la persona haya atendido después, sin mirar `snapshot_json.user_id`. No incluye `turno.transferido`. La captura muestra la asignación de Eduardo a las 13:28:49 dentro de la pausa de Diego y no muestra la entrega real.

## Causa

La reatención no quedó porque, con el cronómetro de pausa corriendo, Diego no es un destino válido y la pantalla no envía la asignación. No hay un cliente identificado: el que sí llegó, `V-0025`, venía de la cola y su primera atención es de Eduardo.

El cliente de fila apareció al quitar la pausa porque la transferencia no usa la misma regla que la fila. La espera de 20 segundos sí corrió en el dato y en el asignador, y dejó de verse en la tarjeta en cuanto existió la atención.

La captura de auditoría mezcla la asignación de Eduardo con la jornada de Diego y esconde la transferencia. De ahí sale la idea de que el sistema asignó durante la pausa, antes de quitarla.

## Solución

- Mientras haya pausa, el control de reatención debe decir que esa persona está en pausa y no puede recibir la reasignación. No debe decir que no hay clientes en ventana si los hay.
- La transferencia de una atención activa debe usar `esDisponible()`. Con pausa o con la espera de 20 segundos sin vencer, no debe poder elegirse ese destino. Así los 20 segundos quedan libres para la reatención manual, que sí está permitida en esa espera, y no los ocupa un cliente de fila.
- La línea de tiempo debe mostrar la asignación solo si la atención es de la persona consultada, y debe mostrar la transferencia con quién entrega, quién recibe y la hora.

Con eso, a las 13:44:33 la transferencia de `V-0025` se habría rechazado. Diego habría seguido en espera hasta las 13:44:49, con la cuenta visible, y en esa ventana gerencia habría podido asignarle la reatención de un cliente ya atendido por otra persona.

## Mejora

- La reatención manual de un cliente atendido por otra persona ya se permite durante los 20 segundos de fin de pausa, y la fila automática no entra en esa espera. Lo que la ocupó en este caso fue la transferencia.
- En cada fila de la auditoría, nombrar a quien recibe el turno. El origen (`apertura de jornada`, `fin de pausa`, `espera entre turnos`) solo dice por qué corrió el asignador.
- Cubrir con un caso estos tres momentos: pausa activa y clientes en ventana, con el mensaje de pausa y sin asignación de fila; fin de pausa dentro de los 20 segundos, reatención manual aceptada y transferencia de fila rechazada; cliente cuya atención anterior cerró la misma persona, oculto en su tarjeta si hay alguien más libre.

## Hallazgo aparte

No forma parte de la reatención que se quería hacer. Al reactivar una jornada, `AbrirJornadaPdvService::reabrirIntervaloDisponible()` reescribe el último intervalo cerrado: le cambia el tipo a disponible, le pone la hora actual y le borra el fin.

El intervalo 532 de Eduardo se creó a las 12:07:29. A las 13:28:48, al reactivar, quedó como disponible y conservó el motivo de pausa y a quién lo inició. El historial de esa pausa ya no se puede leer en el intervalo.

La mejora es no reutilizar esa fila: cerrarla si sigue abierta y crear un intervalo disponible nuevo. La reactivación de Eduardo y la asignación de `V-0025` a las 13:28:49 siguen siendo coherentes con la regla de la fila. Lo que se pierde es el rastro del intervalo anterior.
