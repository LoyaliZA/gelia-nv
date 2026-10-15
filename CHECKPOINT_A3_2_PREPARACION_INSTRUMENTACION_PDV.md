# GELIA — A3.2: validación operativa y preparación de instrumentación histórica

Fecha: 14 de octubre de 2026.

## 1. Resumen ejecutivo

**Defecto del enfriamiento gerencial confirmado y corregido.** Al cerrar la atención
de otro empleado, el código aplicaba el enfriamiento al autor del comando. Ahora lo
aplica a `TurnoPdvAtencion.user_id`, conservando al gerente como actor del evento.
La única modificación de producción es una línea del servicio de cierre.

Se añadieron pruebas del destinatario, asignaciones posteriores, límite temporal,
duración cero e idempotencia, además de invariantes de reserva, pausa y cierre
pendiente sobre servicios reales. Resultado final: **42 pruebas satisfactorias,
162 aserciones, sin fallos, errores ni incompletos en esta ejecución**.

Se entrega inventario de escritores, contrato de estados verificable, esquema SQL
propuesto y primera implementación vertical. **No se creó ni ejecutó migración,
no existe todavía `pdv_medicion_eventos`, no se instrumentaron transiciones ni se
habilitaron métricas V2.** Los diez diagnósticos incompletos de A3 se conservaron;
no se consideran resueltos por las pruebas satisfactorias de este checkpoint.

Direcciones aceptadas: D1–D7, D9, D11–D13, con las restricciones del encargo.
La estructura definitiva sigue pendiente de aprobación. D8, D10 y la implementación
transversal de D5 no quedan autorizadas por este documento.

Referencia: [contrato A3.1](CHECKPOINT_A3_1_CONTRATO_MEDICION_PDV.md).

## 2. Investigación del enfriamiento gerencial

Fuentes de evidencia:

- [CerrarAtencionTurnoPdvService](app/Services/PuntoVenta/Turnos/CerrarAtencionTurnoPdvService.php):
  permite cerrar la atención propia o una ajena con permiso de gestión. Identifica
  explícitamente `$actor` y `$atencion->user_id`; no son necesariamente la misma persona.
- [ConsultaPersonaDisponiblePdvService](app/Services/PuntoVenta/Operacion/ConsultaPersonaDisponiblePdvService.php):
  consulta `disponible_desde` de la jornada del usuario candidato, no del autor del cierre.
- [Prueba existente de rotación](tests/Feature/PuntoVenta/JornadaDisponibilidadOperacionPdvTest.php):
  `test_cooldown_rota_al_siguiente_vendedor_y_cero_asigna_en_el_acto` establece
  el enfriamiento del empleado que acaba de atender y el comportamiento con cero segundos.
- [Ciclo de atención](tests/Feature/PuntoVenta/CicloAtencionTurnosPdvTest.php):
  ya permite y prueba el cierre gerencial, conservando al gerente en `actor_id`.

Comportamiento anterior confirmado con dos usuarios elegibles y jornadas abiertas:
el empleado quedaba disponible inmediatamente; al gerente se le aplicaba el
enfriamiento. Si el gerente no tenía jornada abierta, el UPDATE no encontraba su
jornada y el empleado tampoco recibía protección temporal. Este último resultado
se desprende de la consulta; la reproducción automatizada usó ambos con jornada.

La regla no resulta ambigua en estas fuentes: el enfriamiento corresponde a quien
tenía la atención, y el actor corresponde a quien autorizó/ejecutó el cierre.
No se cambian duración, permisos, prioridad/FIFO, motivos de cierre ni ventana de reatención.

## 3. Corrección efectivamente realizada

En el cierre de atención:

```php
// Antes:
$this->aplicarCooldown($actor->id, (int) $turnoBloqueado->sucursal_id, $ahora, $plazos);

// Después:
$this->aplicarCooldown((int) $atencion->user_id, (int) $turnoBloqueado->sucursal_id, $ahora, $plazos);
```

La fecha continúa siendo `ahora + cooldown_segundos`; con cero segundos continúa
siendo nula. El trabajo diferido de asignación conserva su formato, pero su clave
queda asociada al empleado correcto. `actor_id` del evento permanece siendo el gerente.
Cuando la atención es propia, ambos IDs coinciden: el comportamiento se conserva.

No se reparan datos previos ni enfriamientos existentes. No se modifica el cierre
pendiente ni se amplía el alcance de la actualización de jornadas.

## 4. Pruebas y resultados

Nueva clase: [ContratoOperativoMedicionPdvTest.php](tests/Feature/PuntoVenta/ContratoOperativoMedicionPdvTest.php).
Utiliza asignador, selector, apertura, pausa, cierre y persistencia reales, sin
reemplazar la consulta de disponibilidad por una simulación. Colas y eventos de
dominio seleccionados se simulan para controlar los efectos secundarios de pruebas.

| Caso | Antes | Después |
| --- | --- | --- |
| Cierre por gerente, enfriamiento 10 s | Falla: empleado con `disponible_desde=null` | Límite exacto en jornada del empleado; gerente sin enfriamiento impuesto; trabajo diferido identifica al empleado |
| Elegibilidad y asignaciones de ambos | Falla: selector devuelve empleado disponible inmediatamente | Primer turno nuevo al gerente; segundo permanece en cola a los 9 s; a los 10 s se asigna al empleado |
| Enfriamiento 0 s | Control satisfactorio antes del cambio | Sigue sin fecha ni trabajo de enfriamiento; no introduce espera artificial |
| Actor del cierre | Existía control parcial | Evento sigue atribuido al gerente y atención al empleado |
| Reserva antes del inicio efectivo | No requiere corrección | Empleado reservado no disponible; inicio efectivo nulo, sin inventar atención |
| Pausa y finalización | No requiere corrección | Selector bloquea durante pausa y permite al finalizar bajo condiciones vigentes |
| Pausa con reserva abierta | No requiere corrección | Validación rechaza el conflicto; no se crea una transición incompatible |
| Cierre pendiente | No requiere corrección | Jornada `CerradaConAtencion` no habilita disponibilidad |
| Reintento del cierre | No requiere nuevo contrato | Mismo evento, un solo cierre y mismo límite; no extiende enfriamiento cinco segundos después |

Resultados obtenidos:

1. Antes de modificar producción, filtro `test_enfriamiento_gerencial`:
   **3 casos, 10 aserciones, 2 fallos y 1 satisfactorio**, 14.058 s.
   Los fallos demuestran la ausencia del límite y la elegibilidad inmediata incorrecta.
2. Después de la corrección, nueva clase y dos clases existentes relacionadas:
   **42 pruebas, 162 aserciones, todas satisfactorias**, 28.544 s, 107 MB.
   Incluye cierres propios/ajenos, permisos, idempotencia, asignación y enfriamiento.

No se ejecutaron suites globales. No se ejecutaron los diez diagnósticos A3 en esta
intervención; sus archivos permanecen idénticos. Siguen incompletos, no aprobados.
En particular, reactivación sin pérdida de historia V2 y certificación de conflictos
no están implementadas ni se habilitan eliminando esas pruebas.

### Entorno y comandos

PHP 8.4.25 / PHPUnit 12.5.25, imagen local `sail-8.4/app:latest`; contenedor efímero
sin red, repositorio de solo lectura, almacenamiento temporal, SQLite `:memory:` y
entorno `testing`. Sin Compose ni acceso a bases/volúmenes productivos. Las tablas
de pruebas se crean solo en memoria; no se crean migraciones del proyecto.

Comando final:

```bash
docker run --rm --network none --entrypoint /bin/sh \
  --mount type=bind,source=/home/ti-admin/gelianv,target=/var/www/html,readonly \
  --tmpfs /var/www/html/storage:mode=1777 --tmpfs /tmp:mode=1777 \
  -w /var/www/html \
  -e APP_ENV=testing -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: \
  -e APP_CONFIG_CACHE=/tmp/gelia-a32-config.php \
  -e APP_EVENTS_CACHE=/tmp/gelia-a32-events.php \
  -e APP_ROUTES_CACHE=/tmp/gelia-a32-routes.php \
  sail-8.4/app:latest -c 'mkdir -p storage/framework/views storage/framework/cache/data storage/framework/sessions storage/logs
exec php vendor/bin/phpunit --do-not-cache-result tests/Feature/PuntoVenta/ContratoOperativoMedicionPdvTest.php tests/Feature/PuntoVenta/CicloAtencionTurnosPdvTest.php tests/Feature/PuntoVenta/JornadaDisponibilidadOperacionPdvTest.php'
```

Para la reproducción anterior se usó el mismo aislamiento con este comando interno:

```bash
php vendor/bin/phpunit --do-not-cache-result --filter test_enfriamiento_gerencial tests/Feature/PuntoVenta/ContratoOperativoMedicionPdvTest.php
```

Las pruebas de esta fase son deterministas y no demuestran concurrencia real entre
dos procesos. Varios controles existentes denominados concurrentes ejecutan operaciones
sucesivas; deberán complementarse con conexiones MySQL aisladas y barreras al instrumentar.

## 5. Inventario de escritores operativos relevantes

Convenciones de rutas: **O/** = `app/Services/PuntoVenta/Operacion/`, **T/** =
`app/Services/PuntoVenta/Turnos/`. Los archivos citados son reales; los nombres de
eventos históricos son propuestas. **TX** indica transacción actual; **LU** bloqueo
`lockForUpdate`; **V** control de versión; **U** restricción única/idempotencia.
“TX del llamador” no garantiza atomicidad si se invoca el método aislado.

Pruebas abreviadas, todas en `tests/Feature/PuntoVenta/` salvo indicación:
**J** `JornadaDisponibilidadOperacionPdvTest`; **C** `CicloAtencionTurnosPdvTest`;
**P** `PausaOperacionPdvTest`; **PG** `PausaGerencialMotivosPdvTest`;
**M** `MatchmakerTurnosPdvTest`; **AH/CH** `AperturaHorarioOperacionPdvTest`/
`CierreHorarioOperacionPdvTest`; **N** nueva clase de contrato de esta fase;
**A3** clase unitaria de ocupación. Estas referencias no afirman que todas se ejecutaran.

| Orden / archivo y método | Evento/entidades que modifica | TX y concurrencia actuales | Riesgo al instrumentar | Evidencia histórica requerida | Pruebas existentes |
| --- | --- | --- | --- | --- | --- |
| 1. O/AbrirJornadaPdvService.php `ejecutar`, `abrirParaUsuario` | Apertura; jornada e intervalo disponible | TX, helpers de jornada activa, U y recuperación de colisión | Duplicar apertura al reintentar; evento parcial | `jornada.abierta`, IDs y hora real, actor/origen, cobertura parcial | J, M, N |
| 2. T/MatchmakerTurnosPdvService.php `ejecutar`; T/SeleccionarTurnoColaPdvService.php `siguiente`; T/AsignarTurnoPdvService.php `ejecutar` | Selección/reserva, turno, atención, evento de turno | TX externa, LU de turno, V; consulta de persona y comprobación de atención abierta | Nuevos locks pueden alterar rendimiento/orden de asignación; no hay que ejecutar otra asignación paralela | `turno.reservado` para empleado; correlación con evento de turno | M, C, N |
| 3. T/IniciarAtencionTurnoPdvService.php `ejecutar`; T/AplicarInicioAtencionTurnoPdvService.php `ejecutar`; T/AplicarPlazosAtencionNuevaTurnoPdvService.php `ejecutar` | Inicio efectivo manual/automático en atención; alertas diferidas | TX/LU en entrada manual o TX de asignación/transferencia/reatención; helper no abre TX propia | Instrumentar solo entrada web omite automático; doble registro de un inicio | `atencion.iniciada`, origen y configuración aceptada | C, M, N(reserva), A3 |
| 4. T/CerrarAtencionTurnoPdvService.php `ejecutar`, `aplicarCooldown` | Fin de atención, turno en reatención, evento, `disponible_desde`, trabajos | TX, LU turno/atención, V/U; UPDATE de jornada por usuario | Confundir actor/empleado, prolongar límite en reintento, publicar antes de commit | `atencion.finalizada`, `enfriamiento.programado` con límite real | C, J, N |
| 5. T/TransferirTurnoPdvService.php `ejecutar` | Fin de atención origen, nueva atención destino, turno/evento; posible inicio automático | TX, LU turno/atención origen, V/U; disponibilidad destino comprobada | Debe bloquear/reservar dos personas en un orden común, sin media transferencia | Eventos correlacionados de liberación/reserva; fin/inicio por episodio | C |
| 6. T/AsignarReatencionTurnoPdvService.php `ejecutar` | Nueva secuencia, turno asignado, evento y plazos | TX, LU turno, V/U; disponibilidad/ventana | Duplicar turno único o ignorar origen de inicio | `turno.reatencion_reservada` y eventual `atencion.iniciada` | C |
| 7. O/IniciarPausaPdvService.php `ejecutar`, `iniciarParaUsuario` | Cierra disponible, abre pausa, motivo/actor | TX, LU jornada e intervalo, comprobación atención abierta; reintento por estado | Carrera con reserva; motivo puede ser nulo según flujo | `pausa.iniciada` con motivo/contexto conocido | P, PG, N |
| 7. O/FinalizarPausaPdvService.php `ejecutar`, `finalizarParaUsuario` | Fin pausa, actor, nuevo disponible | TX, LU jornada/intervalo, reintento cercano | No deducir elegibilidad efectiva solo del nuevo intervalo | `pausa.finalizada`, causa y estado observado | P, PG, M, N |
| 8. O/CerrarJornadaPdvService.php `ejecutar`, `cerrarParaUsuario` | Cierre/pendiente, fin intervalos no atención | TX, LU jornada, V; consulta atención abierta | Truncar atención o confundir solicitud con terminación | `jornada.cierre_solicitado` y, si procede, `jornada.terminada` | J, C, N |
| 8. O/CompletarCierreJornadaTrasAtencionPdvService.php `ejecutar` | Pendiente → cerrada; cierra intervalos abiertos | TX, LU jornada, V; invocación por listener | Fuente del fin distinta del cierre solicitado; posible otro límite transaccional | `jornada.terminada` con causa de atención finalizada | C, J |
| 9. O/AbrirJornadaPdvService.php `reactivarParaUsuario`, `reabrirIntervaloDisponible` | Reabre jornada, borra cierre, reutiliza último intervalo | TX, LU jornada, versión incrementada; límites de actividad | Sobrescritura histórica confirmada | `jornada.reactivada`, nueva sesión/segmento; nunca reescribir intervalo cerrado | A3 incompleto; J/gestión cubren comportamiento actual |
| 9. O/CancelarCierrePendienteJornadaPdvService.php `cancelarParaUsuario` | Cancela pendiente; puede reutilizar intervalo | TX, LU jornada, V | Confundir cancelación con una sesión realmente terminada | `jornada.cierre_cancelado`, estado de actividad acreditado | Gestión/operación; A3.1 estrategia |
| 10. O/CerrarEquipoAlCerrarSucursalPdvService.php `cerrarEquipoDelDia`, `cerrarJornadasDeDiasAnteriores` | Cierre de múltiples jornadas mediante servicio de cierre | LU de jornadas, TX del llamador y TX anidadas de cierre | Hueco si solo se instrumenta cierre personal; abarcar todas las filas | Los mismos eventos por jornada, causa de sucursal/día | AH, CH, J |
| 10. Mismo archivo `retirarColaDeDiasAnteriores`, `retirarColaDeFecha`, `cerrarTurnoPorCambioDeDia` | Cierra turnos y atenciones abiertas; baja de cola | TX del llamador, LU, V, clave de evento por turno | Escritor directo fuera del cierre de atención; podría perderse fin real | `turno.retirado_por_cierre`, fin de segmento si existe evidencia; no crear inicio | AH, CH |
| 11. T/AltaTurnoPdvService.php `ejecutar`, `cerrarReatencionesVigentesPorNuevaAlta` | Alta, contador/folio, evento; cierra ventanas anteriores del cliente | TX, U; LU de reatenciones, V | Nueva alta invalida una ventana previa; no es atención laboral | Evento de turno y `turno.ventana_reatencion_cerrada` referenciado | AltaTurnoPdvTest, C |
| 11. T/BajaColaTurnoPdvService.php `ejecutar` | Baja de turno en cola | TX, LU, V/U | No inventar usuario/atención para una baja sin reserva | Evento de turno existente; reflejo histórico si afecta reserva acreditada | C |
| 11. T/VencerVentanaReatencionTurnoPdvService.php `ejecutar` | Turno cerrado y evento por vencimiento | TX, LU, V/idempotencia por estado | Un vencimiento no implica duración de atención ni turno nuevo | `turno.ventana_reatencion_vencida` como evidencia de turno | C, AlertasPlazosTurnoPdvTest |
| 12. O/GestionarEquipoOperativoPdvService.php `activar`, `reactivar`, `desactivar`, `cerrarJornada`, `cancelarCierrePendiente`, `iniciarPausa`, `finalizarPausa`, `marcarNoLlego` | Delega comandos y modifica asistencia del día; auditoría | TX en servicios delegados; envoltura de auditoría no es una TX única para todo; U de auditoría opcional | Evento duplicado si también se registra en envoltura; asistencia no equivale a horas inferidas | Instrumentar dueño del cambio, conservar actor gerencial y marca administrativa | PG, OperacionUiPdvTest, auditoría unitaria |
| 12. O/RegistrarAuditoriaGestionOperativaPdvService.php `registrar`; O/ResuelveConcurrenciaJornadaPdv.php helpers | Auditoría; limpieza de marcadores de jornada/intervalo | Registro sin TX propia, U; helpers dependen del llamador | Marcador técnico no es nueva actividad; auditoría no reemplaza bitácora | Causa/referencia para reparación técnica; sin tiempo ficticio | Pruebas unitarias de operación, J |
| 13. O/ResolverSucursalDiaOperacionPdv.php `obtenerOCrear` | Crea día y aceptación inicial según horario | U sucursal/fecha y recuperación de colisión; TX del llamador | Crear un día para auditar podría habilitar condiciones operativas | `sucursal.dia_inicializado` con regla vigente | AH, CH, J |
| 13. O/AperturaHorarioSucursalPdvService.php `ejecutar`; O/AperturaManualSucursalPdvService.php `ejecutar` | Aceptación de altas y apertura; limpieza de días anteriores, evento global | TX, LU día, V/U según entrada; apertura automática tiene etapas transaccionales | Operación compuesta no es un solo hecho; observar fin de cada subcomando | `sucursal.abierta` y eventos individuales de limpieza, con correlación | AH, J |
| 13. O/CierreHorarioSucursalPdvService.php `ejecutar`; O/CierreManualSucursalPdvService.php `ejecutar` | Bloqueo de altas, horario/marca de cierre, equipo/cola | TX, LU día, V/U según entrada | Cierre global con atención pendiente; no cambiar trabajo vigente | `sucursal.cerrada` más solicitud/fin individual | CH, J |
| 13. O/ReabrirSucursalPdvService.php `ejecutar`; O/AmpliarHorarioSucursalPdvService.php `ejecutar` | Restablece altas/modifica límite e invalidación automática | TX, LU día, V | No representa por sí solo reactivación de empleados | `sucursal.reabierta`, `sucursal.horario_ampliado` | J, CH |
| 14. O/ActualizarHorarioCierreSucursalPdvService.php `ejecutar`; O/HorarioCierreOperacionPdvConfig.php `persistir` | Configuración, zona y horarios/overrides | Persistencia de configuración y caché; sin LU/TX envolvente en servicio actual | Sobrescribir política que cambia interpretación del pasado | `politica.actualizada`, valores relevantes/versión | AH, CH y configuración UI |
| 14. O/ActualizarPlazosTurnosPdvService.php `ejecutar`; T/PlazosTurnosPdvConfig.php `persistir` | Espera, automático, ventana y enfriamiento en configuración/caché | Sin TX/LU conjunta con acciones afectadas | Distinción entre regla programada y configuración posterior | `politica.actualizada`; cada acción conserva valores usados | M, C, J, AlertasPlazosTurnoPdvTest |
| 15. T/AlertaEsperaProximoVencerTurnoPdvService.php, T/AlertaProrrogaAtencionTurnoPdvService.php, T/AlertaProrrogaProximoVencerTurnoPdvService.php `ejecutar` | Eventos de alerta/marcas y prórroga | TX, LU, comprobaciones/idempotencia propias | Una alerta no inicia/finaliza actividad | Referencia al evento existente; no crear transición de atención por avisar | AlertasPlazosTurnoPdvTest, C |

### Enfriamiento: no existe un segundo escritor obligatorio al vencer

`aplicarCooldown` persiste el límite; el selector evalúa `disponible_desde <= now()`.
El [trabajo de asignación](app/Jobs/PuntoVenta/Turnos/EjecutarMatchmakerTurnosPdvJob.php)
despierta al asignador, pero no borra el límite ni demuestra que al vencer ya fueran
verdaderas todas las demás condiciones. La bitácora deberá guardar programación y
límite: el fin del impedimento temporal se puede derivar de ese hecho; no registrar
“disponible” retrospectivo porque un trabajo se ejecutó tarde. Esto no implementa D5.

### Escritores de elegibilidad fuera del núcleo PDV — inventariados, no autorizados

| Archivo/método real | Dato relevante y concurrencia observada | Riesgo/evento futuro/pruebas/orden |
| --- | --- | --- |
| [User.php](app/Models/User.php) `concederAccesoSucursal`, `sincronizarSucursalesAsignadas` | Pivot de sucursales; primero usa TX, segundo sincroniza/desvincula sin envoltura propia | `elegibilidad.asignacion_cambiada`; cambios no deben convertirse en pausas. Pruebas de acceso/selector; etapa transversal posterior |
| [AdminController.php](app/Http/Controllers/AdminController.php) `storeUsuario`, `updateUsuario`, `updateRolePermisosHerencia`, `storeGrupoPredefinido` | Altas/roles/permisos y sucursales; no hay transacción PDV común con todas las escrituras | Puede afectar varios usuarios simultáneamente; `elegibilidad.roles_permisos_cambiados`; requiere análisis transversal D5 y pruebas administrativas antes de instrumentar |
| Mismo controlador `archivarUsuario`, `restaurarUsuario`; [RestaurarUsuarioArchivadoService.php](app/Services/Usuarios/RestaurarUsuarioArchivadoService.php) `ejecutar` | Estado/ciclo de vida de usuario; restauración usa TX | Comprobar efecto real de scopes y elegibilidad; no asumir exclusión solo por nombre del campo; futura evidencia de condición. No modificar en A3.2 |
| [CatalogoController.php](app/Http/Controllers/CatalogoController.php) `storeSucursal`, `updateSucursal`, `destroySucursal` | Alta/activo/baja de sucursal; sin TX PDV común | `sucursal.condicion_cambiada`; retención/FK pueden afectar eliminación; pruebas de catálogo/acceso. Etapa transversal posterior |

El inventario cubre los escritores PDV y fronteras administrativas identificadas en
el código inspeccionado; no certifica scripts externos, SQL manual, importadores o
paquetes ajenos a estas rutas. Antes de certificar D5 se exige cerrar ese inventario
transversal y verificar los cambios de roles heredados y asignaciones.

## 6. Contrato de estados e invariantes ejecutables

No se añadieron enums ni un nuevo resolver de producción. La nueva clase de pruebas
es la estructura mínima para verificar servicios reales sin habilitar V2.

| Invariante | Estado de validación |
| --- | --- |
| Atención abierta/reserva impide asignación incompatible | Verificado con asignador y selector real; reserva no exige inicio efectivo |
| Pausa bloquea y su fin reevalúa disponibilidad vigente | Verificado por servicios actuales y selector |
| Espera de inicio no equivale a atención efectiva | Verificado: atención reservada con `atencion_inicio_at=null` |
| Enfriamiento usa empleado, límite real y cero configurado | Regresión corregida y verificada en fechas y asignaciones |
| Cierre pendiente no inventa disponibilidad | Verificado: `CerradaConAtencion` y selector bloqueado |
| Reintento no duplica el cierre ni extiende el límite | Verificado sobre evento de turno actual; no demuestra idempotencia de la bitácora aún inexistente |
| Reactivación preserva historia en V2 | Pendiente; diagnóstico A3 conservado, sin modificar operación |
| Conflictos entre actividades son identificables | Rechazo actual pausa/reserva verificado; conflictos cerrados de historia siguen pendientes según A3 |
| Sin atención no implica inactividad laboral | Contrato semántico conservado; no hay nuevo cálculo de inactividad |

Se mantienen las dimensiones aceptadas: ciclo de jornada, actividad exclusiva y
condiciones. El contrato actual sigue usando sus estados publicados; mapearlos a
V2 no autoriza modificar retorno tras transferencia, pausa o cierre.

## 7. Propuesta concreta de `pdv_medicion_eventos`

**Componente propuesto, inexistente en el proyecto.** El SQL siguiente es material
de revisión dentro del informe; no es una migración ejecutada ni un archivo de
migración incorporado. Versión inicial del esquema y contrato de evento: 1; versión
de métricas V2 es independiente y permanece deshabilitada.

### Columnas y relaciones

| Grupo | Campos y tipos propuestos | Regla |
| --- | --- | --- |
| Identidad | `id BIGINT UNSIGNED`, `evento_uuid CHAR(36)` | PK técnica y UUID único; ninguno representa orden global de commits |
| Contrato | `esquema_version SMALLINT UNSIGNED`, `contrato_version SMALLINT UNSIGNED`, `tipo VARCHAR(64)` | Enum/validador de aplicación versionado; rechazar tipos no admitidos |
| Secuencia | `agregado_tipo VARCHAR(16)`, `agregado_id BIGINT UNSIGNED`, `secuencia BIGINT UNSIGNED` | Persona, sucursal o configuración; orden por agregado bajo bloqueo estable |
| Sujetos | `user_id`, `actor_id` nullable BIGINT UNSIGNED; `actor_tipo VARCHAR(16)` | Usuario afectado distinto del autor; sistema no usa una cuenta humana ficticia |
| Contexto relacional | `sucursal_id`, `jornada_id`, `turno_id`, `atencion_id`, `intervalo_id`, `motivo_pausa_id`, `configuracion_id` nullable BIGINT UNSIGNED | FK a tablas existentes; coherencia de pertenencia verificada por escritor |
| Sesión/origen | `sesion_uuid CHAR(36)` nullable, `origen VARCHAR(32)` | Sesión identificada por eventos, sin tabla de sesiones adicional inicial; web/móvil/gerente/worker/planificador |
| Tiempo | `efectivo_at DATETIME(6)`, `registrado_at DATETIME(6)`, `zona_horaria VARCHAR(64)` nullable, `fecha_operativa DATE` nullable | UTC en las dos fechas; segundo reloj generado por DB; zona IANA explícita donde corresponda |
| Estados | `estado_anterior JSON`, `estado_nuevo JSON` nullable | Dimensiones conocidas; un campo desconocido no se rellena con el estado actual |
| Idempotencia | `idempotency_key CHAR(64)`, `comando_hash CHAR(64)`, `correlacion_uuid CHAR(36)` | Clave de evento SHA-256 determinista por comando/agregado/ordinal; huella del contenido semántico |
| Causa/corrección | `causado_por_id BIGINT UNSIGNED` nullable, `corrige_evento_id BIGINT UNSIGNED` nullable | Referencias a eventos anteriores, sin borrar/modificar el original |
| Evidencia | `contexto JSON NOT NULL` | Parámetros usados, origen de inicio, límite de enfriamiento, versión/hash de reglas y cobertura; sin datos personales innecesarios |

Configuración global utiliza agregado `configuracion` y `configuracion_id` de
`configuraciones_sistema`; puede no tener sucursal. Persona requiere usuario y
sucursal; sucursal requiere su propia referencia. No se crea un día operativo solo
para satisfacer una FK de auditoría. En transferencia se escriben eventos por
persona correlacionados, no un usuario ambiguo con dos estados.

### SQL propuesto, pendiente de aprobación

```sql
CREATE TABLE pdv_medicion_eventos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  evento_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  esquema_version SMALLINT UNSIGNED NOT NULL,
  contrato_version SMALLINT UNSIGNED NOT NULL,
  tipo VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  agregado_tipo VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  agregado_id BIGINT UNSIGNED NOT NULL,
  secuencia BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  actor_id BIGINT UNSIGNED NULL,
  actor_tipo VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  sucursal_id BIGINT UNSIGNED NULL,
  jornada_id BIGINT UNSIGNED NULL,
  turno_id BIGINT UNSIGNED NULL,
  atencion_id BIGINT UNSIGNED NULL,
  intervalo_id BIGINT UNSIGNED NULL,
  motivo_pausa_id BIGINT UNSIGNED NULL,
  configuracion_id BIGINT UNSIGNED NULL,
  sesion_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
  origen VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  efectivo_at DATETIME(6) NOT NULL,
  registrado_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  zona_horaria VARCHAR(64) NULL,
  fecha_operativa DATE NULL,
  estado_anterior JSON NULL,
  estado_nuevo JSON NULL,
  idempotency_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  comando_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  correlacion_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  causado_por_id BIGINT UNSIGNED NULL,
  corrige_evento_id BIGINT UNSIGNED NULL,
  contexto JSON NOT NULL,
  UNIQUE KEY pdv_med_uuid_uq (evento_uuid),
  UNIQUE KEY pdv_med_idem_uq (idempotency_key),
  UNIQUE KEY pdv_med_secuencia_uq (agregado_tipo, agregado_id, secuencia),
  KEY pdv_med_user_ef_idx (user_id, efectivo_at, id),
  KEY pdv_med_suc_ef_idx (sucursal_id, efectivo_at, id),
  KEY pdv_med_reg_idx (registrado_at, id),
  KEY pdv_med_jornada_idx (jornada_id, efectivo_at, id),
  KEY pdv_med_correlacion_idx (correlacion_uuid),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE RESTRICT,
  FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE RESTRICT,
  FOREIGN KEY (jornada_id) REFERENCES pdv_jornadas(id) ON DELETE RESTRICT,
  FOREIGN KEY (turno_id) REFERENCES pdv_turnos(id) ON DELETE RESTRICT,
  FOREIGN KEY (atencion_id) REFERENCES pdv_turno_atenciones(id) ON DELETE RESTRICT,
  FOREIGN KEY (intervalo_id) REFERENCES pdv_intervalos_operativos(id) ON DELETE RESTRICT,
  FOREIGN KEY (motivo_pausa_id) REFERENCES pdv_motivos_pausa(id) ON DELETE RESTRICT,
  FOREIGN KEY (configuracion_id) REFERENCES configuraciones_sistema(id) ON DELETE RESTRICT,
  FOREIGN KEY (causado_por_id) REFERENCES pdv_medicion_eventos(id) ON DELETE RESTRICT,
  FOREIGN KEY (corrige_evento_id) REFERENCES pdv_medicion_eventos(id) ON DELETE RESTRICT,
  CHECK (secuencia > 0 AND esquema_version > 0 AND contrato_version > 0),
  CHECK ((actor_tipo = 'usuario' AND actor_id IS NOT NULL)
      OR (actor_tipo = 'sistema' AND actor_id IS NULL)),
  CHECK ((agregado_tipo = 'persona' AND user_id IS NOT NULL
          AND agregado_id = user_id AND sucursal_id IS NOT NULL)
      OR (agregado_tipo = 'sucursal' AND sucursal_id IS NOT NULL
          AND agregado_id = sucursal_id AND user_id IS NULL)
      OR (agregado_tipo = 'configuracion' AND configuracion_id IS NOT NULL
          AND agregado_id = configuracion_id AND user_id IS NULL))
) ENGINE=InnoDB;
```

Objetivo MySQL 8.4/InnoDB, con restricciones CHECK efectivas; no se presume igual
comportamiento en versiones antiguas ni en SQLite. Laravel puede expresar la tabla
con Blueprint y restricciones SQL explícitas; el modelo propuesto no usaría
`updated_at` y dejaría el reloj de registro a la base. Conexión/escritura nuevas en
UTC, sin reinterpretar silenciosamente timestamps legados. Compatibilidad y nombres
de FK/índices se deben validar contra una base de pruebas antes de aprobar migración.

### Catálogo inicial de eventos

- Ciclo: `jornada.abierta`, `jornada.cierre_solicitado`, `jornada.terminada`,
  `jornada.cierre_cancelado`, `jornada.reactivada`.
- Actividad: `turno.reservado`, `atencion.iniciada`, `atencion.finalizada`,
  `turno.transferido_origen`, `turno.transferido_destino`, `turno.reatencion_reservada`,
  `pausa.iniciada`, `pausa.finalizada`, `enfriamiento.programado`.
- Cobertura/contexto: `medicion.activada`, `medicion.suspendida`,
  `medicion.observacion_inicial`, `sucursal.condicion_cambiada`, `politica.actualizada`.
- Corrección: `medicion.correccion` con referencia/motivo/evidencia.
- Posteriores, **no incluidos en la primera vertical**: evidencia transversal de
  elegibilidad; referencias a alta/baja/vencimiento de turno y alertas. No crear una
  actividad laboral de empleado sin vínculo acreditado por un evento de turno.

El catálogo se valida por versión en aplicación; no se usa enum SQL que obligue a
alterar la tabla cada vez. Cada evento define referencias obligatorias adicionales:
inicio/fin de atención requieren turno/atención y persona; pausa exige intervalo y
persona, con motivo nullable; reactivación exige identidad de sesión nueva. La
integridad de FK no acredita por sí sola que todos los IDs pertenezcan a la misma
persona/sucursal: esa validación es del comando y sus pruebas.

### Reutilización frente a evidencia nueva

Se reutilizan IDs, fechas de atención, turno, jornada, intervalo, motivos y eventos
actuales como referencias. Deben persistirse por primera vez de forma uniforme:
origen manual/automático, límite/valores de regla realmente utilizados, secuencia,
sesión/segmentos no sobrescritos, ambos relojes y nivel de cobertura.

No duplicar nombres de cliente, correo, teléfono, detalle completo de pedido ni
snapshots grandes. Conservar solo IDs y parámetros necesarios. Una configuración
referenciada por ID/hash sin sus valores relevantes no basta si esa fila es mutable;
guardar valores mínimos usados, no toda la configuración del sistema.

## 8. Estrategia transaccional, concurrencia e inmutabilidad

1. El evento canónico se inserta en la **misma conexión y transacción** que modifica
   la entidad operativa. Registrar en un listener después no acredita atomicidad.
2. Para la primera apertura, bloquear un agregado estable existente (`User`) antes
   de leer/asignar secuencia. `MAX(secuencia)+1` solo es seguro bajo ese bloqueo y
   si todos los escritores del agregado respetan el protocolo. Un unique de secuencia
   detecta una violación, no sustituye la serialización.
3. Clave de evento derivada del identificador estable de comando, agregado y ordinal.
   El hash semántico excluye hora actual/trace ID generados de nuevo; detecta cambios
   de argumentos. Reintento idéntico devuelve el evento/respuesta; contenido distinto
   con la misma clave genera conflicto, nunca un evento duplicado.
4. Transferencia escribirá dos eventos de persona con correlación común. La fase
   transversal deberá adoptar orden de bloqueos común, aprobado y ensayado, para
   sucursales/usuarios/turnos/jornadas/atenciones. No imponerlo parcialmente ahora.
5. Evento o comando fallido revierte ambos. Error de publicación después del commit
   no vuelve a ejecutar la operación; se reintenta la entrega desde evidencia durable.
6. El tipo, identidad y secuencia se preservan; correcciones son nuevas filas. Guardas
   de modelo/repository no bastan contra SQL directo: proponer usuario DB de aplicación
   con INSERT/SELECT y sin UPDATE/DELETE sobre esta tabla, pruebas de permisos y
   procedimiento administrativo restringido. No se ampliaron permisos en A3.2.

`registrado_at` de inserción no es el instante exacto de commit/visibilidad; ID global
tampoco ordena commits. Se conservan dos relojes sin prometer aún reconstrucción
exacta de “lo conocido entonces”. D10 sigue pendiente; no se crean manifiestos ni
snapshots. No cerrar eventos retrospectivamente para esconder una cola atrasada.

Las FK RESTRICT impiden borrados que eliminarían referencias, pero podrían cambiar
el comportamiento de bajas administrativas: debe aprobarse retención/compatibilidad.
No se usan cascadas ni SET NULL que reescriban el evento histórico. Se necesitan
pruebas MySQL y revisión de política de conservación antes de implantar el esquema.

## 9. Compatibilidad con web, móvil, trabajos y listeners

| Entrada real | Relación con escritores y compatibilidad |
| --- | --- |
| `app/Http/Controllers/PuntoVenta/Turnos/` y `Operacion/` | Delegan comandos en los servicios inventariados. Instrumentar el dueño del cambio, no el controlador, evita omisiones y duplicados |
| [TurnoPdvMobileController](app/Http/Controllers/Api/V1/Mobile/PuntoVenta/TurnoPdvMobileController.php) `store` | Delega la misma alta web/servicio; `recepcion` es lectura. No se identificó un escritor móvil independiente de cierre en este controlador; no se modifica su contrato |
| `app/Listeners/PuntoVenta/DespacharMatchmakerTras{TurnoCreado,JornadaAbierta,PausaFinalizada,AtencionCerrada}.php` | Despiertan asignador; deben conservar trabajo idempotente. No duplicar registro de asignación en listener y servicio |
| `CompletarCierreJornadaTrasAtencionCerrada.php` | Sí provoca escritura delegada de cierre pendiente; requiere instrumentar ese servicio y correlacionar su fin |
| `Jobs/PuntoVenta/Turnos/EjecutarMatchmakerTurnosPdvJob.php`, trabajos de vencimiento y alertas | Delegan en los escritores de turno; registrar origen `job` con actor sistema y causa, no un usuario ficticio |
| `Jobs/PuntoVenta/Operacion/{AperturaHorarioSucursalPdvJob,CierreHorarioSucursalPdvJob}.php` | Ejecutan servicios con instante evaluado; distinguir fecha efectiva validada y fecha real de registro al procesar tarde |
| `Console/Commands/PuntoVenta/Evaluar{Apertura,Cierre}HorarioOperacionPdvCommand.php`, evaluadores y `routes/console.php` | Evaluación periódica cada cinco minutos que encola trabajos; no son por sí solos transiciones realizadas |
| `GestionarEquipoOperativoPdvService` y pantallas gerenciales | Mismo escritor con actor diferente. Conservar actor, usuario afectado y semántica de permisos actual |

No se cambiaron rutas, cuerpos JSON, permisos, serializadores, broadcasts, app móvil
ni notificaciones. Instrumentar apertura deberá cubrir `abrirParaUsuario`, utilizado
tanto desde apertura propia como gerencial; no añadir un evento en cada envoltura.
Tras commit se emitirán notificaciones compatibles, sin iniciar un segundo comando.

## 10. Primera implementación vertical y etapas propuestas

Todas las etapas siguientes requieren aprobación posterior; no se implementaron.

| Etapa | Servicios/eventos | Atomicidad y pruebas | Fallo/activación y cobertura |
| --- | --- | --- | --- |
| V0. Infraestructura aprobada | Migración aditiva, modelo/registrador y flag de instrumentación, todavía sin activar escritores | SQL en MySQL efímero, FK/CHECK/U, actor sistema, idempotencia, restricciones de modificación | Tabla instalada no significa medición activa; desactivar flag no borra evidencia |
| V1. Apertura real | `AbrirJornadaPdvService::abrirParaUsuario`; `jornada.abierta` con referencias y actor/origen | Misma TX de jornada/intervalo/evento; bloqueo de User para secuencia; doble apertura y fallo de INSERT prueban no duplicación/rollback | Canario autorizado. Cobertura declarada **solo apertura**; no disponibilidad certificada ni sesión completa. Sesión previa no se rellena |
| V2. Reserva e inicio | Asignación, inicio manual/helper automático, transferencia y reatención | Registrar reserva e inicio solo una vez; carreras con pausa y dos asignaciones en MySQL aislado | Origen explícito y misma política vigente. No hay cobertura integral si faltan transferencia o automático |
| V3. Final y enfriamiento | Cierre, límites reales, completado pendiente y escritores de cierre global | Mismo comando/eventos; idempotencia, cierre ajeno, cero segundos, expiración sin disponibilidad inventada | Tiempo de atención acreditable solo con extremos/cobertura suficientes; no modificar métricas aún |
| V4. Pausa, cierre y reactivación | Pausas y todos los finales/reactivaciones/cancelaciones | Secuencias completas, fin por cierre y no sobrescribir intervalos, historia repetida estable | Sesiones instrumentadas de extremo a extremo; coexistencia de escritores antiguos marca hueco, no continuidad |
| V5. Condiciones de sucursal | Apertura/cierre/ampliación/configuración y limpieza de días | Políticas/efectos correlacionados; ventanas/zona y recepción tardía de jobs | Todavía no certificar D5 completo si permisos/asignaciones no están instrumentados |
| Posterior. D5 transversal y métricas | Elegibilidad fuera de PDV y motor V2 | Requiere decisiones y alcance específico, nuevas pruebas y aceptación | Solo entonces evaluar habilitación de disponibilidad efectiva y porcentajes; D8/D10 permanecen separados |

### Contrato preciso para V1

- Entrada: persona, sucursal, instante efectivo validado, actor/origen y clave estable
  de operación. Propuesta de contexto interno opcional compatible; todavía inexistente.
- Crear el evento dentro de la TX solo cuando se crea de verdad una apertura; la
  rama idempotente que devuelve jornada existente no inventa otra transición.
- Guardar `jornada_id`, `intervalo_id`, nueva `sesion_uuid`, transición de ciclo y
  observaciones reales. La etiqueta disponible recién creada no declara que se haya
  reconstruido toda la elegibilidad de la persona.
- Una jornada previa a activación requiere observación inicial desde ahora o una
  nueva sesión real; no atribuir al pasado un evento que nunca se registró.
- Flag por cohorte explícita; cambios de activación/suspensión también quedan
  documentados en evidencia. Fallo de escritura con instrumentación activa revierte
  el comando. Reversión operativa a modo anterior es decisión visible y suspende
  cobertura desde ese punto; nunca continúa certificando silenciosamente.

Cobertura completa exige: todos los escritores aplicables activados, estado inicial
conocido, secuencia continua, extremos acreditados, condiciones necesarias conocidas
y ausencia de conflictos. “Registramos aperturas” no autoriza un reporte completo.

## 11. Riesgos y mecanismos de reversión

- Mayor contención/escrituras; cambiar orden de locks puede provocar deadlocks.
  Reintentos acotados solo con idempotencia; probar colisiones reales, no solo secuencias.
- Muchos eventos de dominio actuales se emiten dentro de TX, otros después. La
  instrumentación durable debe preceder al commit; mover notificaciones de forma
  transversal necesitaría revisión para no cambiar la operación.
- La cobertura parcial es el riesgo principal: cierres masivos, helper automático,
  reatención y limpieza de días no pueden quedar fuera de la certificación.
- JSON es flexible pero no sustituye referencias ni validación de contrato; FKs
  verifican existencia, no coherencia del agregado ni exclusividad de actividades.
- FK/privilegios de inmutabilidad afectan retención y bajas administrativas. Reversión
  recomendada: volver al lector/escritor anterior con flag, conservar la tabla y
  evidencias; no ejecutar `down` destructivo ni borrar historia para “revertir”.
- No convertir eventos viejos al nuevo contrato por timestamp o nombre parecido.
  Reanudar después de una suspensión no certifica el intervalo faltante.
- Corrección puntual reversible con una línea, pero revertirla restituiría el defecto.
  No necesita migración ni reparación masiva de fechas ya existentes.

## 12. Decisiones que todavía requieren autorización

1. Aprobar columnas, restricciones, retención y privilegios del esquema propuesto,
   y autorizar expresamente creación de migración aditiva. Este SQL no lo autoriza.
2. Autorizar V0/V1: modelo/registrador, protocolo de secuencia e idempotencia, contexto
   de origen y clave de apertura, flag/canario y política de error/rollback.
3. Aprobar el alcance de la primera cohorte y que solo se declare cobertura de apertura;
   fijar aceptación operativa y pruebas MySQL aisladas antes de cualquier despliegue.
4. Confirmar compatibilidad de retención RESTRICT con administración y el rol DB
   append-only, sin ampliar permisos operativos del usuario.
5. Reservar para fases posteriores cambios de escritores adicionales, D5 transversal,
   D8/D10 y habilitación de métricas/porcentajes V2. No reinterpretar una aprobación de
   dirección arquitectónica como autorización global de comportamiento.

La corrección gerencial ya realizada está dentro de la autorización puntual del
encargo, respaldada por la reproducción y la regla existente; no requiere cambiar
una regla de negocio ambigua.

## 13. Archivos modificados

| Archivo | Cambio efectivo |
| --- | --- |
| [CerrarAtencionTurnoPdvService.php](app/Services/PuntoVenta/Turnos/CerrarAtencionTurnoPdvService.php) | Una línea: enfriamiento al empleado de la atención, preservando actor del cierre |
| [ContratoOperativoMedicionPdvTest.php](tests/Feature/PuntoVenta/ContratoOperativoMedicionPdvTest.php) | Nueva clase de regresión/invariantes sobre servicios reales |
| `CHECKPOINT_A3_2_PREPARACION_INSTRUMENTACION_PDV.md` | Este informe; incluye únicamente propuesta de esquema e instrumentación |

No se modificaron los archivos anteriores de A1/A2, el contrato A3.1 ni las pruebas
y diagnósticos A3. No se añadieron componentes de producción para estados/eventos,
migraciones, indicadores, interfaces o integraciones móviles. Sin commits ni push.

## 14. Estado final

**A3.2 finalizado: corrección gerencial confirmada, aplicada y verificada; preparación
de instrumentación entregada. Arquitectura histórica y métricas V2 aún pendientes.**

Respuestas de cierre:

1. Sí, el defecto se confirmó y se corrigió con una línea; 42 pruebas/162 aserciones
   relacionadas son satisfactorias.
2. Instrumentar primero apertura en `abrirParaUsuario`; después reserva/inicio y
   todos sus productores, fin/enfriamiento, pausas y sesiones/cierres, según etapas.
3. El esquema permite conservar evidencia genuina si se escribe atómicamente desde
   comandos reales y con cobertura explícita; la tabla por sí sola no certifica nada.
4. Riesgos principales: cobertura parcial, concurrencia, publicación antes de commit,
   retención de referencias, reactivaciones legadas y condiciones externas sin historia.
5. Para comenzar la primera vertical se requiere aprobar esquema/migración, V0/V1,
   protocolo transaccional/idempotencia, activación y reversión; no métricas V2.

Se detiene aquí el trabajo, pendiente de aprobación antes de crear migraciones,
instrumentar transiciones o modificar métricas V2.
