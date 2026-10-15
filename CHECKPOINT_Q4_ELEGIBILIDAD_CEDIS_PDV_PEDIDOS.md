# CHECKPOINT Q4 — Elegibilidad CEDIS, Tienda y PDV

## 1. Resumen de defectos corregidos

Un pedido con origen comercial Tienda, almacén de salida CEDIS y sucursal destino PDV podía quedar atribuido a Preparación Tienda o perder el almacén CEDIS. La corrección separa esos conceptos:

- Una tarea histórica, liberada o cancelada ya no cuenta como preparación vigente.
- El modal conserva un almacén de salida válido. Ya no lo sustituye por PDV al elegir una modalidad que no es Recoge hoy.
- La consulta de mercancía de CEDIS sigue disponible cuando la salida no es un almacén PDV habilitado para preparación.
- Una tarea vigente de Tienda conserva su responsable y sigue impidiendo una segunda consulta CEDIS.
- Recoge hoy sigue exigiendo almacén PDV.
- Un almacén marcado como exclusivo receptor sigue rechazado como salida nueva.
- El destino sucursal no se usa para decidir el responsable ni se borra al cambiar a una modalidad que aún lo admite.

No se creó otra máquina de estados, no se revirtió Q3 y no se alteró el circuito de resguardos PDV.

## 2. Contraste histórico concreto

Los folios `PBMA-2026-00028`, `00025`, `00023` y `00022` se usaron solo como referencia documental del responsable. No se consultaron ni modificaron en una base operativa.

Esos recorridos llegaron a ENVIADO o ENTREGADO con salida capaz de cerrar en CEDIS (incluido «Envío para tienda» / «VTA - CEDIS»). No acreditan por sí solos un traslado físico CEDIS→PDV ni una recepción. Sirven para contrastar el código actual: el recorrido que sí existía era consulta o pesaje de CEDIS y salida desde CEDIS, no una preparación de Tienda deducida del nombre comercial.

P1.2 (R01) ya ubicaba el acoplamiento en el filtro de almacenes del modal y en `PreparacionTiendaConfig::almacenPermitido`, que por lista vacía solo admite PDV. Q3 caracterizó el síntoma visible: con historia de Tienda y sin tarea actual, el modal decía «Esperando respuesta de Tienda» y el servicio de consulta CEDIS rechazaba la mercancía si la persona usuaria estaba habilitada para preparación. Q4 corrige esa elegibilidad. No restaura el circuito antiguo de resguardo.

## 3. Causa raíz

Tres reglas distintas se trataban como si fueran la misma:

1. **Origen comercial Tienda** (`requiere_logistica = false`) se interpretaba como obligación de preparar en Tienda. `SolicitarPesajePedidoBmaService` rechazaba toda consulta de mercancía si la configuración de preparación estaba activa para esa persona, sin mirar el almacén de salida.
2. **Cualquier fila de tarea** hacía verdadero `PedidoBma::usaPreparacionTienda()`. El listado, el progreso y el modal atribuían a Tienda una espera que era de CEDIS.
3. **El modal**, al entrar en flujo Tienda por una modalidad seleccionada, filtraba almacenes al subconjunto PDV y, si CEDIS no estaba, escribía el único PDV o vaciaba la salida. Recoge hoy tiene su propia regla y se conservó.

La restricción predeterminada de preparación a PDV sigue vigente para **crear** una tarea de Tienda. Dejó de usarse para reescribir la salida CEDIS o para bloquear la consulta que corresponde a esa salida.

## 4. Casos reproducidos antes y después

Pruebas sintéticas en SQLite de memoria y en el modal real montado. No se usaron pedidos productivos.

| Caso | Antes | Después |
|---|---|---|
| A. Tienda comercial, salida CEDIS, destino PDV, sin tarea | La consulta CEDIS se rechazaba si la persona estaba en la configuración de Tienda. Con Recoge hoy habilitado, el modal pedía elegir entrega de tienda y ocultaba la consulta CEDIS. | La consulta de mercancía se envía a CEDIS, no se crea tarea y el destino permanece. El modal ofrece «Solicitar separación de mercancía a CEDIS». |
| B. Tarea terminada (liberada o respondida) | Cualquier tarea histórica activaba preparación Tienda y el texto «Esperando respuesta de Tienda». | Liberada no abre ruta. Respondida no es vigente: la espera pendiente se atribuye a CEDIS. El almacén no se sustituye. |
| C. Tarea vigente | Se conservaba, pero mezclada con el caso histórico. | Sigue en Tienda. Una consulta CEDIS adicional se rechaza por estado, no por el nombre comercial. |
| D. Municipio con salida CEDIS | Elegir la modalidad podía vaciar o cambiar el almacén y empujar a preparación Tienda. | La consulta CEDIS no crea tarea. La intención municipal guardada permanece. El modal no cambia CEDIS por PDV. |
| E. Salida PDV | Preparación de Tienda. | La consulta de mercancía de CEDIS sigue bloqueada con un mensaje que nombra el almacén de Tienda. |
| F. Almacén solo receptor | Rechazo como salida nueva; el mismo id ya guardado se conserva. | Igual. Cubierto de nuevo en la prueba de Q4. |
| G. Recoge hoy | Fuerza el almacén PDV. | Igual. Con un solo PDV, la salida pasa a ese almacén y el botón es «Solicitar apartado en PDV». |
| H. Cambio de modalidad | Podía sustituir CEDIS y borrar el destino. | Resguardo con transferencia conserva CEDIS y la sucursal, explica que esa modalidad prepara en Tienda y deja la consulta en CEDIS. |
| I. Pedido enviado | Una tarea respondida podía leerse como preparación pendiente. | No hay preparación vigente ni acción de consulta. Almacén y destino no cambian. |
| J. Separación Bellaroma/Aromas | El bloqueo era por origen sin logística, no por departamento. | La consulta CEDIS no depende del nombre Tienda. No se inventó una regla de marca. |

## 5. Matriz de elegibilidad y responsable

| Escenario | Origen comercial | Salida | Destino | Tarea vigente | Responsable | Acción permitida | Regla que estaba mal |
|---|---|---|---|---|---|---|---|
| A | Tienda | CEDIS | Sucursal PDV | No | CEDIS | Consulta de mercancía | Bloqueo por configuración de Tienda y por origen sin logística |
| B | Tienda | CEDIS | Sucursal PDV | No; hay tarea respondida o liberada | CEDIS si la espera sigue pendiente | Lectura y consulta según fase | `usaPreparacionTienda()` con cualquier tarea |
| C | Tienda u otro | El de la tarea | El ya capturado | Sí | Tienda | Preparación en curso; no otra consulta CEDIS | Ninguna: se conserva |
| D | Envío / municipio | CEDIS | Según modalidad | No | CEDIS | Pesaje o consulta; no se crea tarea | Sustitución de almacén al elegir modalidad |
| E | Tienda | PDV | Según modalidad | Según solicitud | Tienda | Preparación o Recoge hoy | Ninguna en el bloqueo de consulta CEDIS |
| F | Cualquiera | Receptor | Su sucursal, si aplica | No | — | Alta de salida rechazada | Ninguna |
| G | Tienda | PDV | Según Recoge hoy | Al solicitar | PDV | Apartado del mismo día | Ninguna |
| H | Tienda | CEDIS conservado | Sucursal conservada si la modalidad la admite | No | CEDIS hasta que la salida sea un almacén de preparación | Consulta CEDIS; la modalidad de Tienda no se ejecuta sobre CEDIS | Reemplazo silencioso de almacén o destino |
| I | El histórico | El guardado | El guardado | No | Ninguno de preparación | Solo lectura operativa | Reinterpretar historia como pendiente |
| J | El del departamento | CEDIS | El que corresponda | No, salvo tarea real | CEDIS en la consulta | Separación del departamento que ya atiende CEDIS | Inferir Tienda por el origen comercial |

## 6. Cambios backend

- `PedidoBma::usaPreparacionTienda()` queda limitado a `tareaPreparacionVigente`.
- `preparacionTiendaRespondida()` y `enRutaPreparacionTienda()` distinguen la tarea ya respondida o recibida en CEDIS. El cierre de consulta, la espera de pago y los mensajes de validación siguen usando esa ruta cuando la preparación de Tienda es el recorrido real.
- `CalcularProgresoPedidoBmaService` atribuye a Tienda la espera solo si hay tarea vigente. Si la respuesta ya existe y no hay espera, el área de la respuesta concluida sigue siendo Tienda.
- `SolicitarPesajePedidoBmaService` bloquea la consulta de mercancía únicamente cuando la salida es un almacén PDV permitido por `PreparacionTiendaConfig`. El lock, la revisión de captura y la idempotencia de Q3 no se modificaron.
- `permite_salida_pedidos` y el default de `almacenPermitido` (PDV si la lista está vacía) no se ampliaron.

## 7. Cambios React / modal

El diseño del modal no se rehizo. Cambió la decisión de elegibilidad:

- La preparación vigente se lee del estado de la tarea, no de `usa_preparacion_tienda` histórico ni de la sola existencia de `tarea_preparacion`.
- El flujo Tienda por modalidad seleccionada solo aplica si ese almacén está habilitado para preparación.
- Se eliminó el efecto que escribía el almacén PDV, o lo vaciaba, al entrar en ese flujo. Recoge hoy conserva el suyo.
- Si la modalidad de Tienda no aplica al almacén elegido, el selector lo conserva y muestra: «Este almacén de salida se conserva. La modalidad elegida solo prepara en un almacén de Tienda; la consulta de mercancía sigue en CEDIS.»
- La elección obligatoria Recoge hoy / resguardo solo aparece cuando la salida es PDV.
- El listado usa el mismo `usa_preparacion_tienda` ya restringido a tarea vigente, así que el badge deja de decir «Pendiente de respuesta de Tienda» por historia.

## 8. Archivos modificados

- `app/Models/ControlPedidos/PedidoBma.php`
- `app/Services/ControlPedidos/SolicitarPesajePedidoBmaService.php`
- `app/Services/ControlPedidos/CalcularProgresoPedidoBmaService.php`
- `app/Services/ControlPedidos/ListarPedidosBmaService.php`
- `app/Services/ControlPedidos/ValidacionCamposPedidoBma.php`
- `resources/js/Pages/ControlPedidos/Partials/ModalFormPedidoLegado.jsx`
- `resources/js/Pages/ControlPedidos/Partials/modalConcurrenciaContrato.test.jsx`
- `resources/js/Pages/ControlPedidos/Partials/modalElegibilidadCedisPdv.test.jsx` (nuevo)
- `tests/Feature/ControlPedidos/ElegibilidadCedisPdvPedidoTest.php` (nuevo)

`ModalFormPedidoLegado.jsx` ya contenía en el árbol de trabajo la coordinación de guardado de Q3. Esos cambios se conservaron.

## 9. Pruebas ejecutadas y resultados

Entorno de pruebas: SQLite `:memory:` dentro de Sail. No se usó `ALLOW_DESTRUCTIVE_DB` ni una base operativa.

| Conjunto | Resultado |
|---|---|
| `ElegibilidadCedisPdvPedidoTest` | 5/5 |
| `BorradorPreparacionContratoTest` (Q2) | incluido en 51 aprobadas |
| `ConcurrenciaCapturaPedidoTest` (Q3) | incluido en 51 aprobadas |
| `ConfirmarCaratulaIdentidadTest` (Q1) | incluido en 51 aprobadas |
| `ValidarAlmacenSalidaPedidoTest` | incluido en 51 aprobadas |
| `ControlPedidosPesajeTest` | incluido en 51 aprobadas |
| Vitest del modal Q4, Q3 de concurrencia, persistencia Q2 y destino sucursal | 25/25 en la pasada completa de esos archivos; la repetición Q4+Q3 quedó en 14/14 |
| `npm run build` | Correcto. Aviso existente de chunks mayores a 500 kB. Manifiesto local verificado. |

`BusquedaRemisionDelegadoTest` (Q7) y `PreparacionTiendaWorkspaceTest` no llegaron a sus aserciones: `Storage::fake` no pudo abrir `storage/framework/testing/disks/public/pedidos_bma` por permiso del directorio. El fallo ocurre antes de la lógica de elegibilidad. No se cambió ese directorio ni se interpretó como regresión de Q4.

## 10. Compatibilidad con Q3

Q3 está presente y no se reimplementó: `SolicitarPesajePedidoBmaService` sigue tomando lock del pedido, comprobando revisión y base de captura, y devolviendo el mismo resultado si la consulta ya está pendiente. La prueba de concurrencia pasó. La caracterización del modal que esperaba «Esperando respuesta de Tienda» sin tarea actual quedó actualizada al contrato de Q4: conserva el almacén y muestra «Esperando CEDIS». El guardado coordinado, el 409 y la idempotencia de esa suite siguen verdes.

## 11. Restricciones de almacenes preservadas

- `permite_salida_pedidos = false` impide una salida nueva y permite conservar el id ya guardado. La sucursal puede seguir siendo destino.
- Con `almacenes_habilitados` vacío, solo un almacén PDV puede recibir una tarea de preparación. CEDIS no quedó habilitado por esta corrección.
- Recoge hoy sigue limitado a almacén tipo PDV.
- No se activó una salida indiscriminada para forzar CEDIS→PDV.

## 12. Riesgos que corresponden a Q5 y Q8

**Q5.** El pesaje de un envío con logística no se desactivó. Esta entrega no decide si un hito documental de Aromas debe eximir peso, ni cambia `ResponderPesajePedidoBmaService`. Si un pedido CEDIS sigue detenido solo por cajas, kilos o evidencia de pesaje, ese bloqueo queda para Q5.

**Q8.** No se modificó la recepción esperada, la recepción física ni la custodia. Un pedido ya enviado no se reinterpretó ni se avanzó. `ENVIO_MUNICIPIO` sigue sin admitir sucursal destino; el modal no conserva ese destino porque el backend lo prohíbe. Verificar entrega y devolución no forma parte de Q4.

No se inició ninguno de los dos.

## 13. Veredicto

**GO condicionado.**

La selección de almacén, el responsable y la consulta CEDIS quedan alineados entre Laravel y el modal para los casos reproducidos. La condición de salida es publicar juntos el PHP y el build del modal. No hay migración, cambio de permisos ni cambio de configuración. Q7 no pudo reejecutarse en este entorno por el permiso del disco de pruebas; conviene repetirlo donde ese directorio sea escribible antes de dar por cerrada la compatibilidad documental, sin mezclarlo con esta corrección.

## 14. Instrucciones de despliegue

No se desplegó nada.

1. Publicar el código de Q4 junto con los assets ya compilados en local (`npm run build` terminó y verificó el manifiesto). No publicar solo el PHP o solo el modal.
2. No ejecutar migraciones ni cambiar `permite_salida_pedidos`, `almacenes_habilitados` ni permisos.
3. No editar los pedidos históricos citados.
4. Comprobar en un pedido de prueba, no productivo: origen Tienda, salida CEDIS, destino sucursal, sin tarea. Debe ofrecer consulta CEDIS y conservar ambos ids. Repetir Recoge hoy sobre un PDV y una salida exclusiva receptora, que debe rechazarse.
5. Después del despliegue, repetir `BusquedaRemisionDelegadoTest` en un entorno donde el disco de pruebas sea escribible.
