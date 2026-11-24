# Pedido nacional: seleccionar paquetería, direcciones y seguro visible (2026-11-24)

## Intención
La vendedora cotiza envíos nacionales y hoy no ve cuánto cuesta el seguro ni el total antes de guardar; el cliente sin dirección queda bloqueado; las etiquetas confunden. Se corrige la vista de la vendedora sin cambiar la fórmula ni el recorrido de pago.

## Metas
1. Renombrar «Cambiar paquetería» → «Seleccionar paquetería» (y el botón acorde).
2. Resguardo en tienda (municipal y nacional) en CEDIS: «Marcar enviado» → «Enviado a sucursal» (tarjeta y modal). Solo etiqueta.
3. Separar en contenedores propios «Dirección de entrega» y «Costo de envío» dentro de Cotización y pago.
4. Cliente sin direcciones: aviso dentro del modal y acción para generar el enlace-formulario (reutilizar `ModalGenerarLinkDireccion`), pudiendo registrar principal o adicional sin exigir principal primero. Se conserva la captura manual.
5. Formulario público de dirección (`Clientes/Direcciones/FormularioPublico.jsx`): vista más cómoda y validación por tipo: teléfono con máscara de 10 dígitos, correo con formato, CP de 5 dígitos, etc. Validación igual en servidor.
6. Seguro opcional, apagado por defecto. Antes de guardar la vendedora ve el desglose: venta, guía, seguro, total.

## No metas
- No cambiar la fórmula: `CalcularSeguroPedidoService` ya la implementa (Estafeta y FedEx 2.5 %; DHL 2 % + $51) sobre (venta + guía). No se añaden columnas al catálogo.
- No tocar pagos, auditoría ni el flujo municipal más allá de la etiqueta del punto 2.
- El bug del pedido municipal 9797 (desaparece tras responder disponibilidad) se investiga aparte.

## Enfoque elegido (A, ajustado tras revisar el código)
El servidor calcula; la pantalla no duplica la fórmula. Se agrega un endpoint de vista previa (sin guardar) que invoca `DistribuirSeguroCajaNacional` con los fletes capturados y `aplica_seguro`, y devuelve venta, guía (fletes + reexpedición), seguro y total. La pantalla lo consulta con retardo al cambiar fletes, reexpedición o la casilla. Lo guardado y lo previsualizado usan el mismo código. Descartado B (calcular en JS): duplica la regla y no cubre multi-caja, donde el seguro usa el valor asegurado por caja.

## Decisiones del usuario
- Seguro opcional, por defecto apagado (`seguro ?? pedido.aplica_seguro` ya parte de falso en pedidos nuevos; verificar).
- «Guía» = fletes de cajas + reexpedición (propuesta del agente, aceptada con «A»).

## Pendiente del implementador
- Confirmar si la reexpedición forma parte de la base del seguro en el servidor actual (hoy solo se usa el flete); alinear vista previa y guardado, y avisar si difiere.
- Paqueterías sin cobertura (no Estafeta/FedEx/DHL): ocultar la casilla de seguro.
- Pruebas: actualizar `ContinuacionEnvioNacional.test.jsx`; pruebas del endpoint; actualizar guía operativa V3.1 §15 y el manual.
