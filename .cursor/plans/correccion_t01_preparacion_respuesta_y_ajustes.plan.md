---
name: Corrección T-01 — preparación, respuesta y ajustes
overview: Corregir la secuencia real de disponibilidad y separación y aplicar las observaciones del usuario sobre respuesta, retiro documental, motivos y cobertura. Conservar avances de 2.1 y verificar la aplicación servida.
todos:
  - id: comprobar-ruta-y-assets
    content: Confirmar componentes y assets realmente servidos antes de atribuir el fallo a un modal legacy o repetir cambios presentes.
    status: completed
  - id: preparar-y-responder
    content: Resolver los dos cierres simultáneos y sus validaciones contradictorias; conservar revisión por excepciones, escáner, atributos y evidencia física.
    status: completed
  - id: retirar-y-anexar
    content: Completar retiro documental o por catálogo, múltiples capturas sin límite funcional de cantidad, motivos y evidencia opcional de petición del cliente.
    status: completed
  - id: respuesta-y-etapas
    content: Unificar presentación de la respuesta y bloquear cobertura y secciones posteriores hasta cierre vigente sin ajustes pendientes.
    status: completed
  - id: comprobar-recorrido
    content: Verificar T-01 por interfaz y contratos afectados, incluyendo reapertura, evidencia y respuesta de ajustes; documentar resultados sin presumir aceptación humana.
    status: completed
isProject: false
---

# Corrección de T-01, respuesta y ajustes — 3 de noviembre de 2026

## 1. Alcance y autoridad

Corrección dentro de la fase 2.1, autorizada por las observaciones y capturas del usuario. Esta versión precisa las instrucciones previas sobre separación, ajustes y cobertura. Leer `AGENTS.md`, `GUIA_DISENO_GELIA.md` y el plan integral; no reiniciar su implementación ni rehacer pantallas que ya cumplan.

El objetivo es que T-01 pueda revisarse hasta separación de manera coherente y que la vendedora reciba el resultado, solicite cambios y cierre la consulta antes de avanzar. Conservar las conexiones de las otras vistas y adaptar los contratos compartidos indispensables.

Referencia visual elegida por el usuario: secciones **Datos del pedido** y **Atención en tienda** de `FormularioRegistrarTienda.jsx`. Reutilizar `registrar-tienda-panel` y `registrar-tienda-seccion-titulo`, iconos, controles y estilos efectivos. En oscuro las secciones se distinguen del fondo del modal como esas referencias; en claro y con personalización mantener la adaptación por tokens, sin imponer negro literal.

## 2. Diagnóstico comprobado por lectura, no por ejecución

- `Tienda/Index.jsx` abre `Tienda/Partials/ModalPreparacionTienda.jsx`; para tareas pendientes/en atención este renderiza `PrepararApartadoRecoge.jsx`. También lo utiliza `Tienda/Show.jsx`. No hay evidencia de que esta entrada abra un modal legacy.
- El panel de disponibilidad separado ya fue retirado de ese modal. Su revisión se agregó dentro de `PrepararApartadoRecoge`, pero permanece el cierre físico anterior en el pie.
- `registrarRevision` permite confirmar el conjunto sin transcribir todas las piezas y exige foto física. `guardar` y `faltantesRespuesta` siguen exigiendo líneas; el pie ofrece «Confirmar apartado» aun antes de completar revisión/decisión. La evidencia sigue rotulada opcional para RECOGE_TIENDA. Esto explica las contradicciones visibles, no demuestra una pérdida de todos los cambios.
- El código actual de `SeguimientoSolicitudTienda` ya usa paneles de la referencia, catálogo de motivos, búsqueda por catálogo y galería de evidencias de retiro. `ContinuidadTienda` ya recibe `consultaCerrada` para cobertura. Verificar esos avances en la app servida antes de reconstruirlos por capturas anteriores.
- `RegistrarRetiroPiezasTiendaService` y su petición HTTP todavía requieren una lista de SKU; el servicio recorta a 20 piezas. Adjuntar capturas no es hoy una entrada documental autónoma.

La lectura no acredita el bundle servido, la integración funcional ni el comportamiento de pedidos existentes. No atribuir automáticamente las diferencias a caché: comprobarlo.

## 3. Confirmar entrada y versión servida

Identificar desde la bandeja la ruta, componente y tarea que se abren. Revisar también la entrada Show para que ambas mantengan la misma secuencia. Si está activo Vite, comprobar que la página realmente lo usa; si consume manifiesto, comprobar assets vigentes y compilar al final cuando corresponda. No modificar Vite, retención de assets ni infraestructura como solución preventiva.

Distinguir un pedido antiguo con productos ya capturados de uno nuevo solo con PDF. Conservar los datos válidos de ambos. No resetear ni convertir pedidos reales para probar.

## 4. Una secuencia coherente de preparación

Usar la superficie existente con documento, escáner/buscador, atributos, evidencias y cierre contextual. No añadir otro modal/formulario encima ni volver a exigir transcribir las piezas correctas para responder disponibilidad.

| Momento | Acción pertinente | Condición y resultado |
| --- | --- | --- |
| Revisión pendiente/en curso | Guardar avance; Enviar respuesta de disponibilidad | Sin excepciones: confirmación explícita del conjunto y foto física. Con excepciones: piezas/cantidades afectadas y evidencia pertinente. No inventar productos omitidos ni confirmar apartado. |
| Respuesta con cambios o excepciones pendientes | Ver solicitud y atender lo pendiente | Mostrar responsable y piezas/documentos. No confirmar separación de un conjunto todavía no aceptado. |
| Selección vigente aceptada, sin pendientes | Confirmar separación/apartado | Reutilizar el contrato físico de inventario; debe conocer los productos/cantidades físicos que ese contrato necesita. Si faltan, pedir su identificación en esta etapa, sin convertir la captura por línea en requisito de la consulta previa. |
| Apartado confirmado | Resultado y siguiente acción de T-01 | Pago presencial pendiente/confirmado y entrega según estado real. No repetir «Falta el apartado» después de confirmarlo ni declarar pago/entrega inexistentes. |

El pie muestra la acción principal del momento actual, sin duplicar «Registrar revisión» en el cuerpo y «Confirmar apartado» como cierres competidores. Guardar avance debe admitir una revisión con evidencia y sin líneas cuando corresponda; no ejecutar el guardado físico de productos vacíos para conseguirlo.

Usar estado persistido y permiso del servidor, no solo el código RECOGE_TIENDA o un booleano local. Reutilizar la resolución/aceptación existente; no crear otra aceptación implícita. Conservar restricciones de horario, instrucción sustituida, pago y entrega.

Los productos capturados mediante escáner/catálogo se conservan. Única pieza y Mejor ejemplar son atributos independientes de la condición física. Sin existencias y daños sí se comunican como excepción; una falta no se acepta como disponibilidad.

## 5. Evidencia de preparación y presentación de la respuesta

La foto general obligatoria para la revisión debe aparecer como **obligatoria**, con carga/pegado, galería, ampliación y captura desde celular existentes. Mostrar por separado las fotos por pieza cuando existan. El PDF del pedido es referencia documental y no satisface la foto física. Evidencia válida ya guardada en la misma revisión se reconoce sin otra carga.

Presentar a la vendedora una respuesta vigente: quién/cuándo, estado del conjunto, excepciones, atributos, cantidades conocidas y documentos/fotos. No llamar «Resultado del apartado» a una consulta que todavía no confirma separación. No presentar como total del pedido la suma de las únicas excepciones capturadas; decir «piezas afectadas» cuando ese sea el dato disponible.

Reutilizar un mismo contrato de lectura para respuesta de tarea y revisión del pedido. Evitar que la existencia de alguna línea oculte evidencia general guardada en el otro contrato. No agregar al resultado archivos de otras revisiones, peticiones del cliente o instrucciones sustituidas por compartir tipo de documento.

Resultado y Ajuste de piezas usan las superficies e iconos de las referencias elegidas. Mantener etiquetas coherentes en todas las vistas receptoras, con separaciones claras entre pedido solicitado, respuesta de disponibilidad, apartado físico y entrega.

## 6. Retiro de piezas: dos entradas reales

### Entrada documental, habitual

Permitir adjuntar **PDF o capturas de las piezas a retirar**, incluso si la respuesta solo enumera excepciones. No exigir seleccionar cada pieza ni transcribir SKU para registrar esta solicitud. El auxiliar recibe los documentos y determina productos/cantidades afectados antes de ejecutar el retiro físico. No usar OCR ni SKU ficticios.

### Entrada por productos

Conservar selección de líneas conocidas y cantidades parciales. Si la respuesta no contiene todas las piezas, ofrecer búsqueda por SKU/nombre usando `SelectorProducto`, incluso cuando no hay existencias disponibles: retirar una reserva no exige stock libre. Un producto del catálogo no prueba que esté en el pedido; el servidor/ejecutor verifica pertenencia y cantidad comprometida antes de liberarlo.

Ambas entradas producen una solicitud identificable con versión, documentos, motivo, responsable y estado pendiente/aplicada/rechazada. Solicitar no ejecuta el retiro ni confirma liberación de stock. Completar la atención desde la vista del auxiliar y devolver el resultado a la vendedora; no resolver únicamente con una notificación.

No aplicar retiro informal a un pedido pagado/remisionado. Usar su gestión documentada y conservar referencias. No confundir retirar piezas con retirar custodia para enviar a CEDIS.

## 7. Adjuntos, motivos y petición del cliente

Distinguir dos grupos:

- **Piezas a retirar:** PDF/capturas que indican los productos, como alternativa a la lista seleccionada.
- **Evidencia petición Cliente:** soporte opcional de la petición del cliente, no obligatorio para una solicitud válida. Conservar exactamente esta etiqueta o su equivalente gramatical con el mismo significado.

No imponer un límite funcional al número de capturas que comparte la vendedora. Conservar límites de tamaño/formato por archivo; resolver límites de transporte mediante cargas acumulativas o por lotes si hace falta. Ningún archivo o pieza puede descartarse silenciosamente. Revisar UI, petición, servicio, guardado y visualización; eliminar el recorte silencioso a 20 piezas y límites fijos de galería aplicables a estos adjuntos, sin levantar indiscriminadamente límites de todo el proyecto. Permitir añadir, quitar y volver a añadir archivos antes de enviar y revisar todos después de reabrir.

Conservar el catálogo de motivos ya implementado si cumple: elección rápida y opción **Otro** que habilita texto obligatorio. No dejar simultáneamente un motivo libre obligatorio para las opciones normales. Guardar un valor estable y su detalle cuando corresponda; no exigir crear un CRUD de catálogos para esta corrección.

El anexo mantiene su entrada principal por PDF/captura y la alternativa por catálogo acordadas. No restaurar el SKU obligatorio para la entrada documental al modificar el contrato de retiros.

## 8. Cierre de consulta y cobertura

La respuesta recibida no equivale a consulta cerrada. Mostrar primero respuesta, ajustes y confirmación del cliente. **Ocultar cobertura y las secciones siguientes hasta el cierre vigente de la consulta**, también para administradores.

El servidor debe impedir registrar cobertura si la consulta sigue abierta, la respuesta está sustituida o quedan decisiones/anexos/retiros pendientes. Completar la misma condición en el presentador y la interfaz. Si no puede cerrarse, mostrar causa y responsable, no una acción que aparente avanzar.

Un ajuste pendiente invalida la aceptación de la versión afectada y espera nueva respuesta cuando corresponde. No borrar pago/remisión o cobertura ya válidos para pedidos avanzados: gestionar la modificación documentada. Reabrir el modal o recibir una actualización debe recuperar el estado persistido correcto, sin conservar flags locales antiguos.

## 9. Implementación y comprobación acotadas

Orden: confirmar versión/entradas → alinear estados y contratos → corregir preparación/evidencias → completar retiro documental y su atención → gates del cierre/cobertura → pulir presentación y comprobar receptoras.

Archivos de entrada: `Tienda/Index.jsx`, `Tienda/Show.jsx`, `Tienda/Partials/ModalPreparacionTienda.jsx`, `PrepararApartadoRecoge.jsx`, `requisitosRespuesta.js`, `Partials/SeguimientoSolicitudTienda.jsx`, `RespuestaPreparacionTienda.jsx`, `ContinuidadTienda.jsx`, `FormularioRegistrarTienda.jsx`; servicios y Requests de disponibilidad, apartado, retiro, cierre y cobertura; presentadores del pedido/tarea y CSS efectivo de preparación/registro. Seguir solo los consumidores necesarios; no ampliar a otros módulos completos.

| Comprobación | Resultado requerido |
| --- | --- |
| T-01 nuevo con PDF, sin líneas, foto general y confirmación | Responde disponibilidad sin exigir transcribir todo; no confirma inventario ni apartado ficticios. |
| Excepción con escáner, cantidad, daño o falta; atributos independientes | Respuesta conserva atributos/fotos y espera decisión correcta. |
| Separación posterior | Conjunto aceptado y físicamente identificado; validación de inventario intacta; siguiente paso correcto. |
| Retiro documental con numerosas capturas | Registra sin SKU transcritos, conserva todos los archivos y se atiende por el auxiliar. |
| Retiro por catálogo no listado en respuesta, con cantidad parcial | Valida pertenencia/reserva, aplica una sola vez y devuelve resultado. |
| Motivo normal / Otro / petición sin evidencia | Catálogo rápido; texto solo para Otro; evidencia del cliente opcional. |
| Consulta abierta/cerrada y ajuste pendiente | Cobertura oculta y rechazada por servidor hasta cierre válido; reapertura consistente. |
| Reapertura y vistas receptoras | Estado, documentos, fotos y cantidades coinciden; ninguna respuesta vieja sustituye la vigente. |

Usar pedidos ficticios y pruebas dirigidas a los contratos modificados; reutilizar las existentes y agregar solo las que detecten estas regresiones. Comprobar por interfaz el modal de bandeja y Show, escritorio y móvil, claro y oscuro, con datos del pedido y documentos legibles. La selección azul del inspector de las capturas no es un color de producto a reproducir.

Entregar archivos modificados, recorrido ejecutado, pruebas, versión de assets servida y pendientes concretos. Actualizar el plan integral y el análisis en su trazabilidad, sin cambiar requisitos para encajar con un defecto. La aceptación operativa del usuario sigue pendiente. No commit, push ni despliegue remoto.

## 10. Resultado ejecutado — 3 de noviembre de 2026

La corrección quedó en el código servido. La aceptación operativa sigue pendiente.

Assets: `public/hot` apunta a `http://100.75.11.59:5179`. No hay `public/build/manifest.json`. No se reinició Vite ni se borró `public/hot`. La ruta nueva no aparecía porque `bootstrap/cache/routes-v7.php` estaba cacheada; se ejecutó `./vendor/bin/sail artisan route:clear` en este entorno.

Pruebas, con Sail, sin `migrate fresh` ni `ALLOW_DESTRUCTIVE_DB=1`:

- `SolicitudRegistrarTiendaTest` y `test_la_cobertura_de_mismo_dia_exige_facultad_y_dia_habil_sin_confirmarla`: 23 pruebas, 234 aserciones, en verde. Incluye retiro documental de 21 capturas sin SKU, atención del auxiliar una sola vez, rechazo de producto ajeno, motivo sin evidencia de petición, cierre sin abrir pago y cobertura bloqueada mientras la consulta sigue abierta.
- Vitest de `PrepararApartadoRecoge.test.jsx` y `ModalPreparacionTienda.test.jsx`: 19 pruebas en verde. La consulta de tienda muestra «Enviar respuesta de disponibilidad» y «Confirmar separación» solo con la selección aceptada.

Pantalla, oscuro, bandeja En atención, folio 45265236 / PBMA-2026-00088, sin enviar la respuesta: el modal servido es `PrepararApartadoRecoge`. La acción principal es «Enviar respuesta de disponibilidad». La foto del conjunto es obligatoria y el PDF queda como referencia. En Gestión de pedidos el mismo folio muestra «Esperando respuesta de Tienda» y no muestra cobertura. A 390 px el modal apila productos, evidencias y el pedido de referencia y el texto sigue legible. No se cambió el tema de la sesión, así que el claro no se recorrió. Show no se abrió. No se confirmó separación ni se pidió un retiro sobre ese folio.

Límite conocido: un retiro parcial ajusta la cantidad de la pieza y no parte la clave de inventario. El motivo «Otro» exige texto en la interfaz; el servidor exige el motivo ya resuelto y no distingue la opción por sí mismo.
