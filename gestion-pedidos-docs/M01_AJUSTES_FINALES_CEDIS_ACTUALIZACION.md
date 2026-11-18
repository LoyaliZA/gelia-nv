# Consultas y modificaciones compartidas — diagnóstico y propuesta

Fecha: 17 de noviembre de 2026. Base inspeccionada: `develop`, HEAD `08d240a7`, incluyendo cambios locales rastreados y archivos sin seguimiento.

**Documento rector del diagnóstico original.** La implementación posterior y las comprobaciones de las etapas 1 y 2 están en [el corte de revisión](M01_CORTE_ETAPAS_1_2.md). Las etapas 3 a 6 esperan la revisión solicitada por el usuario. El resto de este documento conserva el diagnóstico previo y la propuesta, sin atribuirle las pruebas posteriores.

## 1. Resultado del diagnóstico

La separación actual combina dos formularios, dos mecanismos de actualización de consulta y restricciones específicas dentro de servicios parcialmente compartidos. Compartir botones no resolvería esa diferencia. La propuesta es extraer el espacio de consulta/modificaciones de Tienda y conectarlo mediante un contrato común a los recorridos existentes, conservando sus reglas operativas.

| Aspecto | Tienda actual | Municipal nuevo | Nacional y Municipal histórico |
| --- | --- | --- | --- |
| Formulario de edición | `FormularioRegistrarTienda` | El mismo formulario común, con `FormularioRegistrarMunicipal` | `ModalFormPedidoLegado` |
| Consulta y respuesta | `SeguimientoSolicitudTienda`, disponibilidad y tareas según origen | `SeguimientoSolicitudMunicipal`, `consulta_municipal` y revisión de disponibilidad | Consulta de mercancía/pesaje, preparación según origen |
| Modificación detallada | `AjustePiezas`: anexos y retiros por catálogo/documentos | Acciones rápidas por excepción; no incorpora `AjustePiezas` ni su historial completo | Adjuntar anexo y solicitar actualización por motivo; no incorpora el editor completo de Tienda |
| Decisiones por excepción | Confirmar estado, retirar o sustituir; faltante excluye confirmar | Reutiliza `AccionesRapidasDisponibilidad` | El servicio de acciones rápidas excluye Nacional |
| Actualización de consulta | JSON con revisión, ciclo y solicitudes | El mismo JSON con seguimiento municipal adicional | `consulta_actualizacion_pendiente`, `motivo_repesaje`, datos de pesaje/cajas |
| Lectura automática en formulario actual | Hook compartido; lectura de resumen de pagos | Hook compartido; GET de disponibilidad y fusión municipal | Actualizaciones locales del formulario legado y callbacks propios; no usa el mismo espacio de consulta |
| Recepción de la modificación | Distingue revisión documental y trabajo físico del auxiliar | Consulta sin crear tarea de preparación; alcance municipal propio | Conserva condiciones de pesaje, cajas, preparación y envío |

Evidencia: `ModalFormPedido.jsx:8-18`, `flujoCapturaPedido.js`, `SeguimientoSolicitudTienda.jsx:190` y `:721`, `Municipal/SeguimientoSolicitudMunicipal.jsx:89`, `ModalFormPedidoLegado.jsx:2345-2610`, `ActualizarConsultaPedidoBmaService.php`.

Los municipales históricos no se deben identificar solo por la etiqueta comercial: la rama nueva requiere `solicitud_preparacion.modalidad_entrega = municipal`. Tampoco se debe enviar todo Nacional al formulario de Tienda para obtener sus controles.

## 2. Actualización automática: hallazgos y límites

### CEDIS: obstáculos confirmados por código

- `Cedis/Index.jsx:82` pausa el hook cuando cualquiera de sus modales está abierto. El pedido del panel se lee al abrir (`:104`); el refresco del listado no actualiza por sí solo el objeto conservado en `disponibilidad.pedido`.
- El mismo consumidor no configura respaldo periódico. El respaldo de 15 segundos del formulario municipal de Ventas no mantiene actualizado el panel abierto de CEDIS.
- `PanelRespuestaDisponibilidad.jsx:52-67` conserva `enviada` local. Una nueva revisión del mismo pedido no reinicia esa marca; puede mantener la interfaz como respondida. Su `key` en CEDIS solo distingue el ID del pedido. La solución debe distinguir ciclos sin desmontar capturas pendientes.
- Después de responder, `Cedis/Index.jsx:230-240` reconstruye una revisión parcial con solo `estado` y marca anexos como respondidos con hora del navegador. Se pierden en esa copia local identificadores y metadatos que debe determinar el servidor. Hay que adoptar la lectura canónica de la respuesta o volver a leerla.

### Ventas: problemas y riesgos identificados

- `FormularioRegistrarTienda.jsx:811-824` activa el respaldo municipal solo con consulta enviada y abierta; las pausas cambian según ese estado. La apertura del modal y la presencia de un temporizador no garantizan que la lectura se aplique.
- `Municipal/vigenciaConsultaMunicipal.js` fusiona consulta, revisión, documentos y piezas, pero omite `anexo_sin_remision`, `retiro_piezas` y `acciones_disponibilidad`. No sirve todavía como contrato completo de modificaciones.
- La vigencia se compara por `respondida_at`, con `updated_at` como alternativa. Una actualización o reapertura puede conservar la fecha de la respuesta anterior; comparar fechas de respuesta no representa por sí solo el avance del ciclo. Es necesario validar también identidad de actualización y revisión, sin ordenar UUID.
- Hay varias copias de la solicitud: `pedido`, `solicitudViva`, `actual` municipal y estado del seguimiento. Deben consumir una lectura operativa común; la captura editable debe permanecer separada.
- En `useActualizacionesPedidos` un evento recibido con una petición en vuelo puede descartarse; no se encola automáticamente una nueva lectura por ese caso. Además, `ultimoAplicado` se marca antes del éxito de la petición. Son casos que requieren pruebas dirigidas de convergencia.

### Emisión y transporte

Existe `PreparacionPedidosRealtimeObserver`, registrado sobre pedido, tarea y documentos/cumplimiento de tarea en `AppServiceProvider`. Emite `PedidoBmaActualizado`, que usa `ShouldBroadcast` y despacho después del commit. El hook escucha `.pedido.actualizado` en `control-pedidos.operaciones`. El canal tiene autorización propia en `routes/channels.php`.

Esto acredita la ruta estática prevista. **No acredita entrega real**: quedan por comprobar cola de broadcast, transporte, suscripción de cada cuenta, evento emitido, GET recibido y estado React finalmente mostrado. No se propone cambiar permisos del canal ni crear infraestructura paralela. El GET sigue autorizándose por pedido.

El reporte anterior `M01_INTEGRACION_CONSULTA_CEDIS_VENTAS.md` declara expresamente que no comprobó una respuesta entre dos sesiones con seguimiento abierto. La causa completa del fallo observado en Ventas sigue pendiente de instrumentación en navegador; no se atribuye solo a Echo.

## 3. Servicios, endpoints y permisos

| Pieza existente | Reutilización prevista | Condición que impide generalizarla directamente |
| --- | --- | --- |
| GET `/{pedido}/disponibilidad-tienda` | Lectura operativa común; conservar URL compatible | Hoy devuelve la presentación general; falta acordar campos/versiones de consulta para los tres recorridos |
| POST de acciones en `disponibilidad-tienda` | Decisiones por revisión | `AccionesDisponibilidadTiendaService:24` admite solo Tienda/Municipal; bloqueos de etapa, pago y remisión deben conservarse |
| POST `anexo-tienda` | Captura, archivos, trazabilidad e idempotencia | `RegistrarAnexoTiendaService::pedidoRelacionado` crea el relacionado como Tienda; notificación y estado pendientes dependen de `area_revision` |
| POST `retiro-piezas-tienda` | Solicitud con motivo, documentos y evidencias | El retiro documental de faltantes por revisión solo se aplica para modalidad Tienda; el servicio exige respuesta previa y bloquea pago/remisión |
| POST `anexo-piezas` + `solicitar-repesaje` | Compatibilidad con modificación de envíos | No representa la misma operación completa que los anexos/retiros de Tienda; `ActualizarConsultaPedidoBmaService` mantiene su propia marca de actualización |
| POST `cerrar-consulta` / `reabrir-consulta` | Comandos comunes | Servicios y modelo tienen reglas distintas por modalidad y etapa; no igualar sus autorizaciones por interfaz |
| `EjecutarSolicitudAjusteTienda` | Clave UUID y lotes bajo bloqueo transaccional | Debe envolver todos los comandos nuevos; no añadir una segunda deduplicación independiente |
| `AdjuntosConsultaTienda` / `lotesAdjuntosConsulta` | Cupo, ciclos, huellas y cargas múltiples | Conservar el cupo configurable de 50 MB por consulta, además de límites específicos de cada operación |

Las rutas de anexo/retiro requieren `control_pedidos.crear`, acceso al pedido y comprobación de titularidad por `VisibilidadPedidoBma::puedeMutarComoVendedora`. Leer, modificar y responder son facultades diferentes. `AlcanceConsultaMunicipal` conserva permiso CEDIS o permiso municipal de respuesta y sucursales operables; la preparación física mantiene sus permisos actuales. La homologación no debe ampliar esos alcances.

Los adjuntos no tienen un límite uniforme: las respuestas de disponibilidad validan archivos de hasta 10 MB y hasta cinco fotos por producto; el ajuste detallado gestiona el cupo agregado y lotes. La sustitución rápida usa un único archivo de hasta 10 MB. Conservar estas diferencias explícitas y sus constantes, sin imponer accidentalmente el límite de una operación a todas.

## 4. Presentación CEDIS y borradores

`TarjetasCedis.jsx:99` conserva «Tipo de pedido» y muestra «Envío municipal». En `:163-167`, Municipal sustituye la ruta por Terminal destino/Área responsable; las otras ramas usan `RutaPedidoTienda`. El adjunto pide corregir exactamente esa divergencia.

Propuesta: mantener la misma composición de tarjeta y `RutaPedidoTienda`; para envíos usar «Envío: MUNICIPAL» o «Envío: NACIONAL». Municipal obtiene origen del almacén persistido de la consulta y destino de `paqueteria.nombre`, mediante las relaciones hidratadas. Si falta un dato, indicar que está pendiente; no inferir un almacén por el nombre del área ni inventar municipio. Esto representa destino operativo, sin acreditar CEDIS → PDV → Terminal. Conservar PDF Wizerp, Revisar disponibilidad y las etiquetas propias de Tienda. Aplicar la misma resolución de datos al detalle y al panel cuando corresponda.

En `FormularioRegistrarTienda.jsx:776-800`, el autoguardado considera contenido suficiente `datos.modalidad` o `datos.area`; un cambio de selector puede provocar persistencia sin cliente, folio ni documento. Es una explicación estática compatible con el borrador reportado, no una reproducción nueva de apertura sin acciones. Verificar por separado apertura vacía, selección de modalidad y captura significativa.

Propuesta: no persistir por montaje, valores iniciales o selección aislada de modalidad/área. Conservar autoguardado de captura significativa y el guardado explícito requerido al pulsar Enviar consulta, incluida subida de documento antes del envío. No eliminar ni modificar borradores existentes durante la comprobación.

## 5. Solución compartida propuesta

### Interfaz

Extraer `AjustePiezas` del archivo de seguimiento de Tienda a un componente compartido, manteniendo composición, catálogo/escaneo, captura documental contigua, múltiples archivos, motivo y estados. Mantener una exportación compatible durante la transición.

Componer un espacio común de consulta con `RespuestaDisponibilidadPedido`, `AccionesRapidasDisponibilidad`, `ResumenCambiosDisponibilidad`, galería y visor existentes. Usarlo desde consulta y desde Editar pedido. Ambos accesos ejecutan los mismos comandos y muestran el mismo historial; la captura financiera/logística continúa en su formulario actual.

El servidor entrega capacidades y motivos de bloqueo según actor, sucursal y etapa: consultar, anexar, solicitar retiro, decidir excepción, responder, cerrar y reabrir. Estas capacidades reflejan las reglas actuales, no nuevos permisos. Cada comando vuelve a validarlas bajo transacción.

### Servicios y compatibilidad

Extraer progresivamente el núcleo documental de anexos/retiros: validación, idempotencia/lotes, ciclo, conservación de documentos, historial y notificación. Los servicios actuales delegan a ese núcleo; conservar inicialmente rutas, nombres y claves JSON para pedidos existentes.

Resolver las diferencias con políticas/adaptadores pequeños por recorrido sobre los servicios actuales. Tienda conserva cobertura, apartado, remisión y custodia; Municipal conserva consulta sin tarea, terminal, expediente y finanzas municipales; Nacional conserva pesaje, cajas, costos, empaque y guía. Los adaptadores no duplican captura, archivos, historial ni sincronización.

Para Nacional, registrar el cambio documental y solicitar la revisión/pesaje correspondiente mediante su recorrido vigente. No usar `area_revision = control_pedidos` para convertir automáticamente todo envío en consulta sin peso. Conservar peso/costo anteriores como antecedentes hasta su respuesta operativa y aplicar sus bloqueos actuales.

El anexo de un pedido remisionado debe mantener su gestión documentada/relacionada; el flujo compartido no debe crear un relacionado Tienda para cualquier modalidad ni modificar el principal. Los retiros físicos permanecen con el ejecutor autorizado; retirar un faltante sin inventario solo registra la decisión documental.

### Lectura y concurrencia

Mantener `useActualizacionesPedidos` y el canal existente. Asignar un solo responsable de sincronización del pedido abierto; la bandeja puede registrar invalidaciones y refrescarse sin competir con el editor. El responsable aplica tanto eventos como respaldo al mismo GET y al mismo contrato de lectura.

Separar lectura operativa del servidor y captura local editable. Fusionar consulta, ajustes, decisiones, evidencias e historial por sus identidades/versiones; no sustituir el formulario completo. Las respuestas tardías no deben revertir un ciclo nuevo ni el cierre. Reutilizar las identidades existentes de actualización/respuesta y revisar si hace falta una versión monotónica adicional antes de diseñar una migración.

Si llega una revisión nueva durante captura, actualizar el contexto y conservar archivos/valores locales; señalar conflicto cuando la operación preparada haya quedado obsoleta. Reiniciar estados de respuesta solamente al adoptar el nuevo ciclo y sin borrar una captura pendiente. Volver a leer al terminar una mutación o una pausa y si hubo cambios durante una petición en vuelo.

## 6. Orden incremental y puertas de comprobación

1. **Actualización y alta vacía.** Corregir la propiedad de la lectura en CEDIS/Ventas, la adopción canónica tras responder y el avance de ciclo. Verificar segunda respuesta y excepciones con dos sesiones. Corregir autoguardado sin borrar borradores.
2. **Tarjetas CEDIS.** Reutilizar estructura y ruta, datos persistidos y etiquetas solicitadas. Comprobar Tienda, Nacional y Municipal en tarjeta, detalle y visor.
3. **Extraer sin cambiar Tienda.** Mover el editor y la orquestación documental al núcleo compartido; probar el contrato antes/después con los mismos casos de Tienda. Mantener rutas y payloads compatibles.
4. **Municipal.** Incorporar editor detallado e historial en consulta/edición, con alcance municipal y sin tareas, pesaje nacional ni movimientos físicos ficticios. Cubrir municipal nuevo e histórico por separado.
5. **Nacional.** Integrar el mismo editor en el formulario legado con el adaptador de revisión/pesaje. Preservar costos, cajas, pagos y logística; no migrar de golpe todo el formulario.
6. **Consolidación.** Retirar caminos duplicados únicamente tras cubrir sus consumidores y pedidos legados. Publicar un build por lote coherente mediante Sail; mantener retención de assets y ausencia de `public/hot`.

Cada etapa debe quedar verificable antes de activar la siguiente. No se propone una migración masiva de pedidos, reescritura general del formulario ni modificación de reglas financieras.

## 7. Regresión requerida

| Escenario | Resultado exigido en las tres modalidades, con sus reglas propias |
| --- | --- |
| Consulta y Editar pedido | Mismo editor, servicios e historial; acciones según capacidad real |
| Anexo por catálogo y varias capturas/PDF | Conserva original, lotes y documentos anteriores; sin recapturar el conjunto correcto |
| Retiro de faltante y pieza existente | Faltante sin liberación ficticia; retiro físico por actor autorizado |
| Doble clic, reintento y fallo parcial | Mismo UUID/lote no duplica operación, archivo, historia ni aviso |
| Cupo y límites de archivo | Error conserva archivos guardados; respeta cupo por ciclo y restricciones propias |
| Reapertura, recarga y segunda respuesta | Solo la revisión vigente habilita cierre; historial anterior accesible |
| Dos sesiones abiertas | Respuesta de CEDIS visible en Ventas y actualización de Ventas visible en CEDIS, sin intervención manual |
| Echo sin entrega, reconexión, pestaña oculta y petición en vuelo | El mecanismo existente converge; sin peticiones paralelas del mismo pedido |
| Captura local pendiente | Eventos/GET no borran texto, archivos, terminal, domicilio o datos financieros locales |
| Pedido pagado, remisionado, preparado, enviado o cancelado | Conserva bloqueos y gestión documentada; no se alteran hechos operativos |
| Actor ajeno/sucursal ajena | Servidor deniega lectura/mutación según alcance; ocultar botón no sustituye autorización |
| Alta nueva sin acciones | Cero pedidos/documentos creados; Enviar consulta sí persiste en su secuencia autorizada |

Ampliar las pruebas existentes, especialmente `SolicitudRegistrarTiendaTest`, `MunicipalOrdinarioM01Test`, `RecorridoTiendaIntegralTest` y las de elegibilidad/respuesta de pesaje; componentes `useActualizacionesPedidos`, `PanelRespuestaDisponibilidad`, selección de formulario, ajustes y seguimiento municipal. Añadir contrato parametrizado para las tres modalidades y pruebas de lecturas fuera de orden.

Ejecutar PHP y frontend mediante Sail. Para servidor aislar ambas cachés con `./vendor/bin/sail exec -e APP_CONFIG_CACHE=/tmp/gelia-test-config.php -e APP_ROUTES_CACHE=/tmp/gelia-test-routes.php laravel.test php artisan test ...`. Seleccionar pruebas por etapa; no repetir suites/builds por rutina.

La comprobación final de actualización exige navegador autenticado con dos sesiones y datos aislados: primera respuesta, solicitud de actualización, segunda respuesta y excepción. Registrar evento/GET y resultado visible. Si no se puede ejecutar, documentar el impedimento concreto y dejar ese criterio pendiente. Capturas aisladas o tests de endpoints no lo sustituyen. Para presentación seguir `GUIA_EVIDENCIA_VISUAL.md` y conservar tema, fuente, acento y superficies GELIA.

## 8. Qué se hizo en esta etapa

Inspección estática del árbol local y documentación, trazado de formularios/rutas/servicios/permisos/eventos y elaboración de esta propuesta. Sail existe en el repositorio y no existe `public/hot`; no se verificó ejecución de contenedores.

No se ejecutaron pruebas, build ni sesiones de navegador en esta etapa de diagnóstico. No se comprobó la infraestructura de broadcast ni se reprodujo el alta vacía. No se modificaron código operativo, permisos, datos ni borradores; únicamente se añadió este informe. Se preservó el trabajo local previo. Sin pull, merge, reset, rebase, commit ni push.
