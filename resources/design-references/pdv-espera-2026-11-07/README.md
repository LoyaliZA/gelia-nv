# PDV — espera tras transferencia y orden de gerencia

Corrección del 7 de noviembre de 2026, basada en el diagnóstico adjunto de Carmen Cadena. El código confirmó las causas descritas; no se revisó el video ni se modificaron los registros históricos de los clientes.

## Comportamiento corregido

- Transferir cierra la atención del origen y registra su propia espera usando el plazo configurado. El servidor conserva 20 segundos; no se fija ese número en el código.
- La espera se aplica dentro de la transacción de transferencia y conserva vendedor, actor y atención anterior en auditoría, con origen `turno.transferido`. Reintentar la misma operación no duplica ni extiende la espera.
- Al finalizar una pausa, el servicio de espera programa el reparto para su vencimiento. Se retiró el escucha que disparaba otro reparto inmediato al publicar `PausaFinalizada`; los demás consumidores del evento se conservan.
- Los trabajos de espera se publican después de confirmar la transacción. Con cero segundos se publica un trabajo inmediato, también después de confirmar, para conservar el reparto sin depender del escucha retirado.
- El reparto consulta nuevamente disponibilidad: una persona pausada, ocupada o en espera no recibe otro turno por el vencimiento de una cuenta anterior. Otros vendedores disponibles pueden seguir recibiendo clientes.
- La espera de transferencia permite la reasignación manual con las facultades vigentes y conserva el inicio diferido hasta terminar la cuenta, como las esperas por pausa o cierre.
- Los cuadros activos se ordenan por `jornada.apertura_at` y, para aperturas simultáneas, por el identificador de jornada. El estado y el nombre dejan de moverlos. El servidor expone el identificador de jornada como dato adicional. Los inactivos conservan su agrupación anterior.

Se reutilizan Operación General, ListaEquipoGerencia, TarjetaVendedorGerencia y sus estilos existentes. No se cambió la composición, el tema, la fuente ni el acento.

## Comprobaciones

Pruebas mediante Sail, con configuración y rutas de prueba aisladas y SQLite en memoria:

- ReatencionGerencialPdvTest, MatchmakerTurnosPdvTest, CicloAtencionTurnosPdvTest, ContratoOperativoMedicionPdvTest y PausaOperacionPdvTest: 72 casos aprobados tras corregir la preparación de los casos afectados.
- EquipoOperativoPdvTest y GestionVendedoresPdvTest: 21 casos aprobados al añadir el identificador de jornada.
- operacionUtils.test.js y esperaEntreTurnos.test.jsx: 24 casos aprobados. Se comprobó nuevamente el ordenamiento tras añadir el desempate por jornada.
- Casos nuevos: transferencia a disponible y a persona pausada, bloqueo hasta el segundo anterior al límite, asignación al vencer, pausa del origen durante la espera, plazo configurable de 7 segundos, plazo cero, reintento y continuidad del reparto a una tercera persona disponible.
- Se corrigió una preparación de pruebas que suponía que el primer reparto entre dos vendedores sin turnos era determinista. Se mantiene la selección aleatoria del producto. El navegador simulado de la prueba de reloj recibió un almacenamiento local explícito.
- Formato PHP dirigido y `git diff --check` aprobados.

El build se publicó mediante el mecanismo de retención del proyecto. Se comprobó la cola `database`, sin pendientes en ese momento, se envió el reinicio gradual del procesador y se confirmó el contenedor nuevamente activo. No se realizaron migraciones, commit ni push.

## Evidencia visual y límites

`verificacion.json`, `escritorio.png` y `movil.png` proceden del build publicado y de la pantalla real con transporte sustituido por respuestas ficticias. El script reproducible es `scripts/qa/verificar-espera-pdv.mjs`, ejecutado mediante Sail. Usa un servidor temporal y Selenium; lo cierra al terminar y no modifica datos operativos ni deja Vite activo.

En escritorio oscuro (1440 × 1000) y móvil claro (390 × 844), los cuadros mantienen Zeta → Ana → Bruno antes y después de cambiar entre atención, espera, pausa y disponibilidad. El caso incluye jornadas abiertas en el mismo segundo con identificadores de usuario en distinto orden. Se comprobó la cuenta visible y la ausencia de desbordamiento horizontal o errores JavaScript. La captura móvil desplaza la página hasta el equipo.

Los avisos de conexión y las atenciones sin cliente de las capturas corresponden a la prueba aislada; no describen el estado del servidor. No acreditan notificaciones reales, una sesión autenticada ni una prueba de carga o concurrencia real de MySQL. Sigue pendiente observar el recorrido con usuarios en piso. Los 20 segundos son el mínimo de elegibilidad; una cola ocupada puede ejecutar el trabajo más tarde.
