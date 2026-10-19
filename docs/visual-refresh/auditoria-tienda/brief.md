# Ficha de tienda — auditoría de pagos

Prototipo aislado. No cambia rutas, modales ni tokens de la aplicación.

## Vista y tarea

- Superficie: revisión de la persona auxiliar en recepción de pagos, pedido solo de tienda.
- Archivo vigente: `resources/js/Pages/ControlPedidos/Auditar/Partials/ModalRevisarPedido.jsx`.
- Captura relacionada (misma regla de campos, otra etapa): `resources/js/Pages/ControlPedidos/Partials/ModalFormPedidoLegado.jsx`.
- Perfil: SaaS/ERP operativo. La tarea es comprobar el pago y el archivo de tienda.
- Dispositivo: escritorio primero; el mismo diálogo debe poder completarse en móvil sin una barra fija sobre el teclado.

Hoy el diálogo de auditoría es un escritorio ancho (`max-w-6xl`) con cobertura, pedido compartido, exhibiciones, remisión, envío y costos (paquetería, guía, reexpedición, pesaje, cajas, pesos, seguro) y anexo de envío. Esos bloques se muestran aunque el origen no requiera logística.

## Cuándo aplica la ficha

Usar `pedidoRequiereLogistica` (`resources/js/Pages/ControlPedidos/Partials/pedidosBmaStyles.js`): origen con `requiere_logistica === false` y que no sea traslado interno.

No usar esta ficha en:

- Recoge hoy (`es_recoge_tienda_apartado`): no lleva pago, ni remisión, ni pasa a auditoría.
- Envío y traslado interno: conservan la revisión actual.
- Crédito (`RECOGE_TIENDA_CREDITO`): misma ficha corta, sin banco ni comprobante. El prototipo muestra el caso de transferencia de la referencia; el crédito queda descrito aquí y no dibujado.

## Campos

Datos ficticios del prototipo: pedido 24595, cliente HUB JUAREZ CARRASCO LANDY CRISTEL, almacén VTA, banco BANORTE, total $3,148.66.

| Dato | Tienda con pago | Crédito | Envío (sin cambio) |
| --- | --- | --- | --- |
| Número de pedido, cliente, cuenta, almacén, tipo | Sí | Sí | Ficha actual |
| Banco y comprobante ya cargado | Sí, solo lectura para auxiliar | No | Ficha actual |
| Comentarios | Solo si existen | Solo si existen | Ficha actual |
| Saldo a favor | Solo si el monto es mayor que cero | Igual | Ficha actual |
| Total de la compra | Sí | Sí | Dentro del desglose actual |
| Archivo de tienda (remisión) | Sí | Sí | Remisión actual |
| Paquetería, guía, domicilio, reexpedición, pesaje, cajas, pesos, seguro, anexo de envío, número de cajas | No | No | Sí |

Lo que no aplica no se muestra como «N/A». Caja y número de cajas salen de la ficha de tienda.

La modalidad de entrega en la captura (recoge hoy, resguardo con transferencia, resguardo con crédito, consulta en CEDIS) se conserva: elige el subtipo. Lo que se recorta son los campos de envío.

## Acciones que ya existen

Ningún botón nuevo llama a una ruta nueva.

| Acción | Condición actual | Efecto que el prototipo solo simula |
| --- | --- | --- |
| Validar pago | Pendiente de auxiliar, no es recoge hoy, sin bloqueos de cobertura | `validarPago` en el modal |
| Rechazar pago | Motivo obligatorio; el comprobante se conserva y deja de cubrir | Rechazo de exhibiciones |
| Adjuntar o ver remisión | Pendiente de auxiliar | Archivo de tienda |
| Aprobar | Pago validado, hay remisión, permiso `control_pedidos.auditar.aprobar`, no es recoge hoy | Aprobación |
| Cerrar | Siempre | Cierra el diálogo |

La persona auxiliar no registra pagos (`puedeRegistrar={false}`). El comprobante lo carga quien registra el pedido; aquí solo se consulta, se valida o se rechaza.

Montos, permisos y cobertura siguen calculándose en el servidor. En la dirección A la cobertura no ocupa un panel: solo se ve el total de la compra y, si existe, el saldo a favor.

## Direcciones

**A. Ficha de mostrador (recomendada).** Una columna, diálogo cercano a 40rem. Encabezado con el número de pedido, el cliente y el estado. Datos, comprobante, total y archivo. En móvil las acciones quedan al final del contenido, no fijas.

**B. Hoja compacta.** Diálogo ancho de dos columnas: cobertura reducida y pedido compartido a un lado; datos de tienda al otro. Sin envío. Más parecida a la auditoría de hoy y conserva contexto que esta solicitud quiere quitar.

Recomendación: **A**.

## Puente visual

El HTML no carga el bundle. Repite los valores efectivos de `resources/css/gelia/tokens.css` y el diálogo de pedidos en `resources/css/gelia/features/control-pedidos-gestion-bma.css`:

- Superficie `#ffffff`, elemento `#fafafa`, texto `#18181b`, borde `#e4e4e7`.
- Acento de foco y selección `#ec4899`, éxito `#059669`, peligro `#dc2626`.
- Fuente Inter. Radio del diálogo acotado a 1.5rem, como `.gelia-pedidos-dialog`.
- Botones del diálogo: 0.8125rem, peso 600, sin mayúsculas forzadas, alto mínimo 44px.
- El texto secundario usa `#3f3f46`. El token `--theme-text-muted` (`#71717a`) queda justo sobre blanco.
- El relleno del botón principal es `#9d174d` y el de “pagado” confirmado es `#065f46`. Blanco sobre `#ec4899` queda en 3.53:1 y sobre `#059669` en 3.77:1; el anillo de foco sigue usando el acento.

## Prototipo

Abrir `docs/visual-refresh/auditoria-tienda/prototipo.html`.

- Dirección A y dirección B comparten el mismo pedido simulado.
- Estados: pendiente de validar, pagado y validado, rechazo con motivo, sin archivo de tienda.
- Validar, rechazar, elegir archivo, aprobar y cerrar responden en la página y avisan que no guardan nada.
- La URL guarda `direccion` y `estado` para volver al mismo caso.

## Skills usadas

- `visual-discovery`: instrucciones, `references/project-context.md` y `references/profiles.md`. Perfil SaaS/ERP y prototipo aislado.
- `frontend-design`: leída. La paleta y la fuente siguen siendo las de GELIA.
- `ui-ux-pro-max`: leída. No hay `search.py` en esa instalación; la densidad y los estados salen del perfil operativo.
- Impeccable: se ejecutó `impeccable context`. No hay `PRODUCT.md`. Este encargo es una ficha acotada sobre la auditoría existente, así que no se inició un producto nuevo. Se leyeron `reference/craft-floor.md` y `reference/operate.md`.
- `web-design-guidelines`: se obtuvo `command.md` y se aplicó al HTML (foco visible, etiquetas, región en vivo, `prefers-reduced-motion`, scroll del diálogo).

## Comprobación

Se abrió `prototipo.html` en el navegador.

- Dirección A, pendiente: una sola ficha, datos de tienda, comprobante, total $3,148.66 y archivo. No aparece paquetería, cajas ni pesos.
- Rechazo vacío: el motivo queda inválido y pide el texto. Con «Comprobante ilegible» el estado pasa a rechazado, los botones se desactivan y la URL guarda `estado=rechazo`.
- Pagado con archivo: aparece «Aprobar pedido». Sin archivo: zona para elegirlo y la aprobación no se ofrece.
- Dirección B: dos columnas, cobertura reducida y pedido compartido, sin envío.
- En 390px las dos direcciones pasan a una columna y los controles siguen en el contenido, no en una barra sobre el teclado.

Las capturas quedaron en la sesión del navegador. Esta carpeta no incluye los PNG.

## Siguiente etapa

Si se elige A, `visual-implementation` recorta secciones en `ModalRevisarPedido.jsx` cuando `pedidoRequiereLogistica` es falso y no es recoge hoy, y aplica el mismo recorte de envío en el tramo de tienda de `ModalFormPedidoLegado.jsx`. Reutiliza componentes y endpoints actuales.
