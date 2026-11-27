# SDD ledger — plan: docs/superpowers/plans/2026-11-27-multienvio-nacional-fase1.md

Spec: docs/superpowers/specs/2026-11-27-multienvio-nacional-design.md
Branch: develop (HEAD 0e5be6cb), trabajo en el árbol, sin worktree.

## Rulings
- Ruling: sin worktree, se trabaja en `develop` en sitio — el usuario pidió trabajar en develop y el repo está montado por contenedores — costo si falla: ninguno (reversible con git).
- Ruling: NO se hacen commits (regla del usuario y de AGENTS.md). Las instrucciones de "commit" del implementador se sustituyen por `git status --short`. El diff por tarea se obtiene comparando tree-ids de `snap.sh` (antes/después), que no crea commits — costo si falla: diff de tarea impreciso.
- Ruling: pruebas con rsync a /home/ti-admin/gelianv + docker exec gelianv-laravel.test-1 (ver Global Constraints del plan).
- Ruling: Tarea 8 Step 5 (MySQL) requiere confirmar con el usuario la base/credenciales; se difiere hasta ese punto.

## Pre-flight (pares que comparten archivo/interfaz)
| Par | Produce/consume | Hallazgo |
| T2-T3..T8 | MultienvioNacional + excepciones | firmas consistentes |
| T3-T4/T6 | `EvaluarMultienvioNacional` array con `revision` | consistente |
| T4-T5 | estado `configurando` | consistente |
| T5-T6 | `borrador_json` lista {uuid_operativo, folio_remision, total_mercancia, contenido} | consistente |
| T6-T7 | `ConfirmarMultienvio` modificado por T7 | T6 crea miembros; T7 añade rama regularización (mismo archivo, secuencial) OK |
| T1-T6 | doc R1 define campos a copiar | T6 depende de T1; OK |
| T8 | rutas + espejo móvil | OK |
Tareas autoconsistentes: T1-T8 sí. Nota: T2 test "origen único" y T4 `firstOrCreate` coherentes.

## Tareas
T1 BASE tree 96cf6b5160cf6ef1bb9b2ab6cd010b16a2ffab69
Task 1: complete (sin commits; spike de solo lectura; el controlador verificó por muestreo consultaCerrada() PedidoBma.php:1159 y ValidacionCampos línea 100; doc docs/superpowers/specs/2026-11-27-multienvio-r1-hallazgos.md)
  Ruling: no se dispatcha revisor para un spike documental de solo lectura — costo si falla: una cita errónea llega a T6, que la reverifica.
  Rulings R1 para T6: piezas (revisiones_producto) se quedan en el origen, miembros las leen del grupo (propuesta R1 §3.3); cajas se mueven con UPDATE directo (no Sincronizar...); documentos con pedido_bma_caja_id se mueven; soporte (pdf_pedido/cotizacion/pieza_solicitada_nacional) se DUPLICA por miembro; caja_remisiones no se reescribe; recalcular numero_cajas/pesos/tipo_caja/total_mercancia/costos en origen y miembros; JSON: quitar cotizacion_nacional y hechos del origen en miembros, vaciar piezas_nacionales*. Abierto: tipo de referencia Wizerp del miembro (TIPO_PEDIDO vs REMISION) -> T5/T6 usan el mismo mecanismo que Crear/GestionarRemision.
Task 2: review 1 -> Important: down() de 100100 (orden dropUnique antes de FK; falla en MySQL 1553; sin test). Fix round 1/5 despachado (mismo implementador). 
  Task 2: minor (deferred): imports sin uso y setUp excesivo en MultienvioNacionalModeloTest; test_casts_y_excepciones débil; mock de NotificarPedidoBmaService innecesario.
Task 2: fix round 1/5 (1 addressed, 0 open — down() ordering + tests; re-review Approved). 
Task 2: complete (sin commits, review clean tras 1 ronda). Minor diferido extra: fail() dentro de try/catch RuntimeException en tests de down(); require_once redundante. Pendiente verificar en MySQL: `migrate:rollback --step=2` (Tarea 8).
Task 3: implementada, en revisión
Task 3: complete (sin commits, review clean). Ruling: exclusión de complementos (esComplemento) acordada en el despacho (anexo no es agrupador). Minor diferido: sin test para miembro con multienvio_nacional_id ni caja con estado_operativo null; n_cajas/revision de un miembro cuentan solo sus cajas (no documentado); consulta de cajas corre aunque no sea nacional.
Task 4: implementada, en revisión. Ruling: configurando -> estandar_sin_seguro permitido (plan §4.2: cliente retira seguro durante configuración sin hechos consolidados).
Task 4: review 1 -> Important: revisión/elegibilidad se validan antes del estado terminal (repetición sobre grupo activo/regularizacion lanza en vez de devolver igual). Fix round 1/5 despachado. 
  Task 4: minor (deferred): User::findOrFail lanza ModelNotFound; borrador_json queda al volver a sin_seguro; carrera sin test en SQLite (depende de lockForUpdate de MySQL -> cubrir en Tarea 8).
Task 4: fix round 1/5 (1 addressed, 0 open — terminal-state shortcut; re-review Approved).
Task 4: complete (sin commits, review clean tras 1 ronda). Minor diferido: sin test de revisión obsoleta en transición no terminal; carrera real solo verificable en MySQL (Tarea 8).
Task 5: implementada, en revisión.
Task 5: complete (sin commits, review clean). Minor diferido: INF/1e999 y montos enormes aceptados en total_mercancia (500 al json_encode) -> añadir is_finite + tope en Tarea 6/8; folio/contenido sin límite de longitud (max:100 del request); null en total_mercancia bloquea borradores parciales (juicio); N+1 de exists() por folio; huecos de tests (estados activo/regularizacion, soft-deleted, folio en blanco, uuid repetido, folio origen repetido en otra caja); comparación de folios por mayúsculas depende de la colación MySQL.
Task 6: implementada (DONE_WITH_CONCERNS: ruta_archivo compartida en soportes duplicados; referencia TIPO_PEDIDO; mercancía 0 rechazada; cotización guardada se limpia), en revisión.
Task 6: review 1 -> Important x2: (1) guard sin SAF/cierres/cancelación activa (riesgo de cobrar mal el saldo), (2) soportes duplicados comparten ruta_archivo (borrado en un miembro destruye el de los demás). Fix round 1/5 despachado (mismo implementador). Ruling: regularizacion_pendiente se devuelve sin cambios en Confirmar (coherente con Decision).
  Task 6: minor (deferred): TIPO_PEDIDO vs TIPO_REMISION de la referencia Wizerp requiere decisión de Ventas/Wizerp (cambia la etiqueta visible «Folio · Pedido»); mercancía 0 rechazada (acorde a ValidacionCampos); foto() de tests sin pedido_bma_direcciones.
Task 6: fix round 1/5 (2 important + M3 + ruling5 addressed, 0 open; re-review Approved).
Task 6: complete (sin commits). Minor diferido: error de soporte faltante es RuntimeException genérica (Tarea 8 debe mapearla a 422); ventana teórica de limpieza de copias si un listener after-commit lanza; SafPedidoAplicacion sin test propio; ConfirmarMultienvio.php 259 líneas (T7 extrae el guard a DetectarHechosPreviosMultienvio).
  Ruling (T7): el plan decía que con solo pago capturado (sin validar) se conserva el pago en el origen y se continúa; eso dejaría el pago completo contra el envío 1 (sobrepago aparente) y moveria la contabilidad sin servicio seguro. Ruling: pago_capturado TAMBIÉN va a regularizacion_pendiente en esta fase (nada se mueve; flag en regularizacion_json); la reasignación autorizada es Fase 3 — costo si falla: el usuario esperaba continuar directo; reversible cambiando una condición.
Task 7: implementada, en revisión.
Task 7: complete (sin commits, review clean). Minor diferido: claves JSON cancelacion_nacional/cambio_a_tienda/invalidacion_guia/vinculo_resguardo se agrupan bajo `complemento` en Detectar (la UI de Fase 3 las leerá; separar); snapshot foto() sin tabla de pagos/remisiones/archivos; imports sin usar en ConfirmarTest; hijoComplemento público.
Ruling (T8): el paso 5 (MySQL concurrencia/rollback) NO se despacha al subagente: requiere crear una base en gelianv-mysql-1 (efecto fuera del árbol) -> se pregunta al usuario al terminar el código de T8.
Task 8: implementada (DONE_WITH_CONCERNS: GET sin can middleware usa puedeConsultar; N+1 acotado por firmas de visibilidad), en revisión. MySQL step 5 pendiente de pregunta al usuario.
Task 8: review 1 -> Important x3: I-1 borrador_json filtra folios/importes de miembros ocultos; I-2 clave de caché de visibilidad insuficiente fuera de nacional; I-3 faltan tests de Auxiliar/Delegado y la guía lo afirma. Fix round 1/5 despachado (mismo implementador) con minors M-1..M-7.
Task 8: fix round 1/5 (3 important + 7 minors addressed; re-review Approved). Task 8: complete salvo paso 5 MySQL. Minor diferido: $veTodos en regularizacion es código muerto (origen sin multienvio_nacional_id en ese estado); assertNoMuta solo prueba el gate de ruta; sin test de 403-vs-422 con cuerpo inválido; orden de imports; query count no acotado con fases mezcladas; ruta no-nacional llama puedeConsultar por miembro.
Ruling (confirmado por el usuario 27-nov-2026): pago capturado sin validar -> regularizacion_pendiente en Fase 1 (ratifica el ruling de T7; el diseño se actualiza). Folio Wizerp de cada envío = referencia TIPO_PEDIDO; la auxiliar registra después las remisiones (una por envío, con su número) — confirmado por el usuario.
Usuario autoriza pruebas MySQL sobre la BD gelianv del entorno produccion-test (gelianv_db vía gelianv_app): entorno de desarrollo; usar solo operaciones acotadas y limpiar datos sintéticos.
Final review: Important I-1 (remisión PDF no detectada), I-2 (miembro puede ser origen), I-3 (revisión atascada en configurando), I-4 (sin rastro de cotización previa) -> fix wave despachado (un solo implementador).
MySQL 8.0 (gelianv_db vía gelianv_app, autorizado por el usuario): migrate up OK (2 migraciones, 0 filas afectadas), migrate:rollback --step=2 OK (orden FK->unique->index->columnas válido en MySQL), BD dejada como estaba (migraciones Pending). Concurrencia real con lockForUpdate NO ejecutada: PHPUnit solo existe en gelianv-laravel.test-1 (red gelianv_sail), que no alcanza gelianv_db (red produccion-test_gelianv_net); falta decisión del usuario.
