# F01.2 — Estabilidad, pagos y regresiones

Segunda intervención de F01. No cambia objetivos ni documentación de Q8, Q8.1, Q8.2 ni Q9.

Modalidad de referencia: **Envío con paquetería mediante Logística**.

**F01 no está listo para aprobación operativa.** Esta pasada corrige causas confirmadas en código y las cubre con pruebas automáticas. No hubo recorrido en navegador sobre un pedido sintético de la base local: no se abrió el formulario con sesión de ventas, no se registró un pago real y no se recorrieron pesaje, PDF ni evidencias en pantalla.

## 1. Diagnóstico

### Problemas confirmados

| Problema | Causa raíz | Relación con F01 anterior |
| --- | --- | --- |
| Falso conflicto «El servidor actualizó este pedido» | El listado y la respuesta de autoguardado no comparten el mismo texto de `updated_at` (`toIso8601String()` frente a la serialización del modelo). Si el formulario ya tenía cambios locales, ese eco se trataba como otra sesión. Además, dos escrituras del mismo segundo se distinguen por la base de captura, no por el reloj. | F01 comparaba las cadenas de fecha en crudo. La protección de `revision_esperada` / `base_captura` se mantiene. |
| Escritura al solo abrir | Al abrir, la huella confirmada quedaba vacía, así que el autoguardado volvía a guardar el pedido sin una edición. Eso movía `updated_at` y alimentaba el eco del listado. | Efecto colateral de la huella introducida en F01. |
| «Guardar borrador» cierra el modal | `guardar(false)` usa `cerrar = true` por defecto y, al confirmar, llama `onClose()`. | No estaba en la primera pasada. |
| «No se pudo confirmar el guardado» con cuerpo vacío | El guardado manual anunciaba `multipart/form-data` pero enviaba un objeto JSON. Laravel no interpretaba ese cuerpo. | El autoguardado ya iba en JSON. El manual no. |
| Reapertura en Datos del cliente | Al abrir, la etapa se forzaba a `datos`. No había memoria de interfaz. | No existía persistencia de etapa. |
| Resumen financiero confuso | El total grande de cotización restaba el saldo a favor y no los pagos. El panel de pago repetía «saldo pendiente» y otra vez el saldo a favor. Las fórmulas no estaban mal. | Etiquetas de F01 / cobertura Q8. |
| Error de pago invisible | Un rechazo de negocio vuelve como flash. Con el modal abierto, el listado no muestra ese flash y el formulario lo tomaba como éxito. | El endpoint ya devolvía el mensaje. La interfaz lo perdía. |
| Pago bloqueado sin decir qué falta | `esCotizacionLista` exige paquetería, costo (si aplica), tipo de guía y zona. El aviso solo decía «complete la cotización». | Regla previa. No se relajó. |

### Descartado tras la investigación

- La casilla de saldo a favor no se pierde por varias llamadas a `setData`: Inertia 3 aplica cada clave sobre el estado anterior.
- `PedidoBmaRequestBase` ya normaliza `"true"` / `"false"` / `"1"` / `"0"` antes de la regla `boolean`. Una cadena cualquiera no se acepta (`BooleanosPedidoBmaRequestTest`).
- El reparto del seguro (importe en la primera caja y cero en el resto) sigue siendo el contrato de F01. No apareció una discrepancia nueva en las pruebas de `distribuirSeguroEnLineas`. No se sustituyó.
- No hace falta una columna de etapa en el pedido. La etapa es estado de interfaz de la sesión.

## 2. Implementación

### Archivos

- `resources/js/Pages/ControlPedidos/Partials/persistenciaFormularioPedido.js` (nuevo)
- `resources/js/Pages/ControlPedidos/Partials/persistenciaFormularioPedido.test.js` (nuevo)
- `resources/js/Pages/ControlPedidos/Partials/ModalFormPedidoLegado.jsx`
- `resources/js/Pages/ControlPedidos/Partials/SeccionPagosExhibicion.jsx`
- `resources/js/Pages/ControlPedidos/Partials/FormularioProgresivo/SeccionResumenEnvioPedido.jsx`
- `resources/js/Pages/ControlPedidos/Partials/modalConcurrenciaContrato.test.jsx`
- `tests/Unit/SaldosAFavor/ResumenPagoPedidoBmaTest.php`

### Frontend

- El eco del listado es propio solo si `base_captura` y `revision_registro` coinciden con lo confirmado. Si el listado no trae base, se compara el instante de `updated_at`, no el texto.
- Si la base remota es otra y el formulario tiene cambios sin confirmar, sigue el conflicto, se detiene el autoguardado y permanece «Conservar copia y recargar».
- Si la base remota es otra y el formulario no tiene cambios locales, se adoptan la base y la fecha nuevas para que el siguiente guardado no choque con una actualización ya incorporada (pesaje u otro proceso).
- Abrir un pedido existente marca como confirmada la huella hidratada. No hay autoguardado hasta que algo cambie.
- Respuestas de una apertura anterior se siguen ignorando (`sesion.cerrada`). La cola sigue en serie: un guardado anterior no pisa una huella más nueva.
- Guardar borrador llama `guardar(false, { cerrar: false })`. El encabezado pasa a «Guardado» solo tras `id` y `base_captura` en la respuesta. Un error deja el modal abierto y conserva la copia local.
- El guardado manual sin archivos va en JSON, igual que el autoguardado. Con archivos arma `FormData` y los booleanos salen como `1` / `0`.
- La última etapa se guarda en `sessionStorage` por usuario y pedido (`control_pedidos.etapa_ui.*`). No es un dato del pedido. Si esa etapa ya no está en el flujo, se abre la última válida y un aviso informativo.
- Junto al monto: **Usar saldo restante**. Copia `resumen.pendiente` (céntimos, sin negativos) y no envía el pago.
- Etiquetas: total exigible, saldo a favor aplicado, pagos válidos acumulados, importe neto restante. El número grande usa `pendiente`, que ya resta saldo a favor y pagos. No se cambió `calcularTotalCobrar` ni `calcularResumenCoberturaPago`.
- Si la cotización no está lista, el aviso nombra paquetería, costo de envío, tipo de guía o zona.
- El flash de error del pago se muestra en el campo de monto. `preserveState` evita cerrar el pedido al registrar. La clave de idempotencia solo rota después de un éxito.

### Backend

No hubo cambio de reglas, servicios ni migraciones. Se añadió la prueba del caso $6,620.48 − $5,000.00 = $1,620.48 sobre `RegistrarPagoPedidoBmaService::calcularResumenCobertura`.

### Concurrencia que se conserva

`RevisionCapturaPedido` sigue exigiendo `revision_esperada` y `base_captura`. Un 409 no adopta la base ajena. El autoguardado no reintenta solo después de un 409.

## 3. Pruebas

### Automáticas

| Comando | Resultado |
| --- | --- |
| `npx vitest run` sobre `persistenciaFormularioPedido.test.js`, `modalConcurrenciaContrato.test.jsx`, `distribuirSeguroEnLineas.test.js` | 17 passed |
| `./vendor/bin/sail artisan test --filter='ResumenPagoPedidoBmaTest\|CalcularSeguroPedidoServiceTest\|BooleanosPedidoBmaRequestTest'` | 12 passed, 44 assertions |

Cubren: eco con fechas de formato distinto, conflicto del mismo segundo, borrador que no cierra, etapa al reabrir, reintento de red con la misma base, 409 que no adopta la base ajena, seguro FEDEX/DHL/sin cobertura y reparto en la primera caja, booleanos `true`/`false`/`0`, y el neto $1,620.48.

No se ejecutó `migrate`, `migrate:fresh`, `refresh`, `reset` ni `ALLOW_DESTRUCTIVE_DB`. No hay commit ni push.

### Navegador

No ejecutadas. La aplicación local responde en el entorno Sail, pero esta pasada no inició sesión de ventas ni creó un pedido sintético en MySQL. Hacerlo habría escrito pedidos, pagos o archivos en la base de trabajo.

### Escenarios no ejecutados

| Escenario | Motivo |
| --- | --- |
| 1 Persistencia real: editar, cerrar, reabrir en la base | Sin sesión de navegador y sin pedido sintético en MySQL |
| 2 Dos sesiones reales | Solo el contrato del modal y el 409 de `ConcurrenciaCapturaPedidoTest` ya existente; no se abrieron dos navegadores |
| 3 Pago sin saldo a favor | No se llamó `pagos.store` |
| 4 Pago con saldo a favor de $5,000 | Solo el cálculo del neto en prueba unitaria |
| 5 Parciales, reintento e idempotencia en base | La clave existe en el servicio; no se insertó un pago |
| 6 Seguro con importes positivos, guardar y reabrir | Fórmula en PHP/JS. Sin pedido FEDEX/DHL de prueba en pantalla |
| 7 Responder pesaje, PDF, evidencias, enlace de dirección | Sin bandeja CEDIS de prueba y sin recorrido visual. El código de F01 sigue en su sitio |

## 4. Estado por corrección

| Ítem | Estado |
| --- | --- |
| F01-A1 Autoguardado y falsos conflictos | IMPLEMENTADO, PENDIENTE DE VALIDACIÓN |
| F01-A2 Guardar borrador sin cerrar | IMPLEMENTADO, PENDIENTE DE VALIDACIÓN |
| F01-A3 Última etapa | IMPLEMENTADO, PENDIENTE DE VALIDACIÓN |
| F01-A4 Alertas compactas | IMPLEMENTADO, PENDIENTE DE VALIDACIÓN |
| F01-B1 Registrar pago y seguir a remisión | PARCIALMENTE RESUELTO |
| F01-B2 Booleanos y saldo a favor | PARCIALMENTE RESUELTO |
| F01-B3 Usar saldo restante | IMPLEMENTADO, PENDIENTE DE VALIDACIÓN |
| F01-B4 Claridad de importes | IMPLEMENTADO, PENDIENTE DE VALIDACIÓN |
| F01-C1 Seguro automático | PARCIALMENTE RESUELTO |
| F01-C2 Separación de etapas | PARCIALMENTE RESUELTO |
| F01-D1 Enlace de dirección | IMPLEMENTADO, PENDIENTE DE VALIDACIÓN |
| F01-D2 Visor PDF | IMPLEMENTADO, PENDIENTE DE VALIDACIÓN |
| F01-D3 Responder pesaje | IMPLEMENTADO, PENDIENTE DE VALIDACIÓN |
| F01-D4 Evidencias por envío | IMPLEMENTADO, PENDIENTE DE VALIDACIÓN |

F01-B1 queda parcial porque el registro sigue sujeto a consulta cerrada, cotización lista (paquetería, costo, tipo de guía y zona) y comprobante o banco según la forma de pago. Ahora el motivo se nombra y el error del servidor se ve en el monto. No se forzó el avance ni se marcó un pedido como pagado.

F01-B2 queda parcial porque el contrato de booleanos ya estaba y sigue probado, pero no se aplicó un saldo a favor real de $5,000 en un pedido.

F01-C y F01-D no se reescribieron. Siguen como en el primer F01, sin la comprobación de pantalla que aquel checkpoint dejó abierta.

## 5. Pendientes operativos

Antes de aprobar F01, el responsable operativo debe recorrer un **pedido de prueba**, no uno productivo:

1. Editar un dato, esperar «Guardado», cerrar y reabrir. El dato y la etapa de Pago y remisión deben volver.
2. Seguir editando mientras dice «Guardando…». Lo último escrito no debe desaparecer ni mostrar conflicto.
3. Cortar la red o provocar un error y usar «Reintentar». Los datos locales deben seguir en el formulario.
4. Abrir el mismo pedido de prueba en dos sesiones, guardar en una y comprobar que la otra avisa el cambio y no lo pisa.
5. Con cotización completa, registrar un pago parcial y otro por el saldo restante con **Usar saldo restante**. Cerrar, reabrir y ver los dos pagos, sin duplicados.
6. Repetir con saldo a favor aplicado de $5,000 sobre un exigible de $6,620.48 y comprobar que el campo queda en $1,620.48 antes de registrar.
7. Generar la remisión solo cuando el resumen quede cubierto. Si falta algo, el mensaje debe decir el motivo.
8. Pedido de prueba FEDEX, ESTAFETA y DHL con mercancía y flete distintos de cero; otro sin cobertura; seguro desactivado; más de un envío. Guardar y reabrir.
9. Responder pesaje de prueba: sin dirección prematura, acciones arriba, PDF legible, fotos bajo su envío.
10. Abrir y cerrar el generador de enlaces sin generar un enlace a un cliente, y seguir en el mismo pedido en escritorio y en móvil.
