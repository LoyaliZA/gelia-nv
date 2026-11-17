# Reparar el evento de asignación de turnos PDV

Fecha: 2026-11-17  
Entorno: producción Carmen Cadena (`aromas-server`)  
Publicación: `./update.sh` (sin migración)

## Problema

Recepción ve clientes en fila y reporta que el sistema no asigna. Operación, a las 13:42–13:47 del 17 de noviembre de 2026, coincidía con la base:

- 0 personas en «Disponible»
- 5 atendiendo, 2 en pausa (comida y encargo de gerencia)
- fila V-0046… en espera
- el worker `gelianv_queue` sí ejecutaba `EjecutarMatchmakerTurnosPdvJob`

Mientras nadie está libre, no asignar es correcto. El fallo a reparar es otro: **cuando alguien sí queda libre, el disparo por evento puede perderse**.

Causa: `EjecutarMatchmakerTurnosPdvJob` implementa `ShouldBeUnique` 30 s por sucursal (`pdv-matchmaker:{sucursalId}`). Alta, cierre de atención y jornada abierta comparten esa clave. Si un job sigue en cola (el mismo worker procesa ~15 000 webhooks de Tiendanube / 2 h) y llega un segundo evento, Laravel **descarta** el segundo. El primero puede correr cuando aún no hay nadie elegible; el cliente se queda en `EN_COLA` hasta el siguiente evento que no se tire.

El job diferido al vencer el cooldown usa `uniqueSuffix` y no choca con esa clave. El hueco está en alta / cierre / jornada, no en el reloj de 20 s.

## Fuera de alcance

- Cron, heartbeat, webpush o websocket de tiempo (regresión del contrato evento → asignación).
- Restaurar `DespacharMatchmakerTrasPausaFinalizada` (asignaba al segundo 0 y saltaba el cooldown; se retiró el 2026-11-07 en `9f4ce85b`).
- Asignar a quien está en pausa o dentro de la espera.
- Separar la cola de Tiendanube.
- Aviso o tope de pausas largas.
- Las 22 reatenciones del tablero.
- Cambio de plazos (`cooldown_segundos` sigue en configuración, hoy 20).

## Comportamiento deseado

1. Alta, cierre de atención, jornada abierta y vencimiento de espera siguen disparando el mismo job. Nada se programa por reloj.
2. Finalizar pausa / cerrar atención / transferir aplica la espera configurada y **no** asigna durante esa cuenta. Al vencer, el job de origen `cooldown` reparte.
3. Un segundo disparo para la misma sucursal **no se descarta** porque el primero aún esté en cola o ejecutándose. Se serializa: uno a la vez por sucursal; el que llega encima **se reencola** y corre después.
4. Elegibilidad sin cambio: jornada abierta del día, sin pausa, sin atención abierta, `disponible_desde` nulo o vencido, sucursal acepta altas.

## Diseño

### Job

Archivo: `app/Jobs/PuntoVenta/Turnos/EjecutarMatchmakerTurnosPdvJob.php`

- Quitar `ShouldBeUnique`, `uniqueFor` y `uniqueId()`.
- Añadir middleware `Illuminate\Queue\Middleware\WithoutOverlapping` con clave `pdv-matchmaker:{sucursalId}` (todas las orígenes, con o sin `uniqueSuffix`).
- `releaseAfter(1)`: el job solapado vuelve a la cola; no se borra.
- `expireAfter(60)`: si el worker muere, el candado no queda eterno.
- Conservar el constructor (`sucursalId`, `origenDisparador`, `uniqueSuffix`) para no tocar listeners ni `AplicarEsperaDisponibilidadPdvService`. `uniqueSuffix` deja de gobernar unicidad; sigue siendo dato del disparo de espera.

`handle()` no cambia: sucursal demo no asigna; el resto llama `MatchmakerTurnosPdvService::ejecutar`.

### Disparadores (sin cambio de contrato)

| Origen | Dónde | Cuándo corre |
|---|---|---|
| Alta | `DespacharMatchmakerTrasTurnoCreado` + asignación inmediata en `AltaTurnoPdvService` | Al crear el turno |
| Cierre | `DespacharMatchmakerTrasAtencionCerrada` | Al cerrar atención |
| Jornada | `DespacharMatchmakerTrasJornadaAbierta` | Al abrir jornada |
| Espera | `AplicarEsperaDisponibilidadPdvService` | `afterCommit`; `delay` hasta `disponible_desde`; inmediato si cooldown es 0 |

No se vuelve a escuchar `PausaFinalizada` para asignar. Ese evento sigue para Reverb y auditoría. El reparto tras pausa es el job de espera al vencer.

### Concurrencia

`MatchmakerTurnosPdvService` ya bloquea el turno (`lockForUpdate`) y rechaza a quien tiene atención abierta. El overlapping evita dos matchmakers de la misma sucursal a la vez (carrera sobre la persona). Sucursales distintas no se bloquean entre sí.

### Errores

- Job fallido: `tries` del worker de producción (3). El siguiente evento o el reencolado por overlapping vuelve a intentar.
- Nadie elegible: el job termina en 0 asignaciones; el próximo evento (cierre, vencimiento de espera, alta) vuelve a disparar y **sí se encola**.
- Sin migración. Sin `Cache::forget` de plazos. Sin `optimize:clear` extra: `update.sh` ya hace `queue:restart`.

## Pruebas

En `tests/Feature/PuntoVenta/MatchmakerTurnosPdvTest.php` (caché `array` como hoy):

1. **Nuevo:** dos `dispatch` sin sufijo a la misma sucursal (p. ej. `TIPO_ALTA` y `TIPO_ATENCION_CERRADA`) → `Queue::assertPushed` 2 veces. Hoy, con `ShouldBeUnique`, el segundo se pierde.
2. Conservar: finalizar pausa con cooldown 20 s no asigna; al vencer, sí.
3. Conservar: cooldown 0 asigna al finalizar la pausa.
4. Conservar: finalizar pausa programa un solo job `cooldown` con `afterCommit` y `delay`.

No hace falta suite completa ni build. Correr solo este archivo con Sail, caché de config/rutas de prueba aislada según `AGENTS.md`.

## Publicación

1. Implementar y pasar las pruebas del archivo anterior.
2. `./update.sh` en este servidor (git, migrate vacío, build si toca front — este parche es solo PHP —, `optimize`, `queue:restart`).
3. Comprobar en piso: con gente en fila, al terminar una atención o al vencer la espera de una pausa, el siguiente de cola se asigna sin saltar los 20 s.

## Evidencia de este diagnóstico

- Operación 13:42 y 13:47: 0 disponibles, fila > 0, pausas activas. El tablero coincidía con MySQL.
- Eduardo recibió V-0046 al cerrar: el listener de cierre a veces sí entrega.
- `failed_jobs` de matchmaker: 0. El hueco es descarte por unicidad, no excepción.
- Comentario ya existente en el test: el candado unique sobrevive entre casos con el mismo `sucursal_id`.
