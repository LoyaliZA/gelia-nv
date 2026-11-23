# Cambio: exhibición de pago cubre mercancía y envío

Fecha: 2026-11-23  
Pedido reportado: PEMA-2026-00022 (municipal).  
Árbol: trabajo local sobre `10d96aa5`.

## Problema

Al pagar un pedido municipal, el total de mercancía ($2,156.00) se cubría y el envío ($50.00) no. «Usar saldo restante» / «Cubrir pendiente» rellenaba solo el pendiente ya descontado de la exhibición en edición, o proyectaba cobertura sin flete si el formulario no traía `costo_envio`. Editar el monto seguía mostrando «Faltan $50.00» porque el aviso usaba el pendiente guardado, no el de esta captura.

El servidor ya exigía mercancía + envío + seguro. No se cambió la fórmula canónica ni el envío a revisión.

## Qué hace ahora

1. Al editar una exhibición, el botón suma el monto ya contado de esa fila. Con $2,156 exhibidos y $50 de envío pendientes, rellena $2,206.00.
2. El texto «Quedaría pendiente / Excedente» usa el mismo neto. Escribir $2,206.00 en esa fila ya no se lee como excedente.
3. Si el formulario no trae flete (null, vacío o 0) y el resumen sí, la proyección toma `totales_envio.costo_para_cobertura` o `total_a_cubrir − mercancía − seguro`.
4. Guardar el importe de mercancía municipal conserva `costo_envio` (la respuesta de actualización no lo devolvía).

## Archivos afectados

| Archivo | Cambio |
| --- | --- |
| `resources/js/Pages/ControlPedidos/Partials/persistenciaFormularioPedido.js` | `importeNetoRestante(resumen, montoYaContado)`, `deltaPendienteExhibicion`, `costoEnvioParaResumenVivo`. |
| `resources/js/Pages/ControlPedidos/Partials/persistenciaFormularioPedido.test.js` | Caso $2,156 + $50 de envío y recuperación del flete desde el resumen. |
| `resources/js/Pages/ControlPedidos/Partials/SeccionPagosExhibicion.jsx` | Botón y aviso usan esas funciones; al editar, el resumen visible proyecta el monto en captura. |
| `resources/js/Pages/ControlPedidos/Municipal/FormularioRegistrarMunicipal.jsx` | Conserva `costo_envio` al guardar mercancía; pasa flete del pedido o de la captura municipal. |
| `docs/ai/modulos/AUDITORIA_PAGOS.md` | Hecho vigente de «Cubrir pendiente» y flete del resumen. |
| `docs/ai/modulos/ENVIO_MUNICIPAL.md` | Guardar mercancía no borra el envío. |
| `docs/ai/cambios/2026-11-23-exhibicion-cubre-envio.md` | Este informe. |

No se modificaron servicios PHP de cobertura, validación ni envío a revisión.

## Cómo comprobar

1. Pedido municipal con consulta cerrada, mercancía $2,156.00 y envío $50.00.
2. Primera exhibición: el botón debe proponer $2,206.00.
3. Si ya hay $2,156.00 registrados: Editar → el botón propone $2,206.00; el aviso no habla de excedente.
4. Enviar a revisión solo cuando el pendiente real sea $0.00 (salvo resguardo, que sigue pidiendo solo mercancía).

Prueba automatizada: `persistenciaFormularioPedido.test.js` (6 casos).
