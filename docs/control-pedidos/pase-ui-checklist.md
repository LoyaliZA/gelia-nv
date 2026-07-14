# Checklist UI — Direcciones (Pase UI)

Smoke visual / funcional para el flag `CONTROL_PEDIDOS_DIRECCIONES_NORMALIZADAS` y pantallas de gestión.

## Flag OFF (legado)

- [ ] Pedidos BMA: aparece textarea de domicilio + “Rellenar dirección”; no selector de direcciones.
- [ ] Envío de pedido con logística sigue validando domicilio texto.
- [ ] Detalle / Auditar / CEDIS muestran `domicilio_entrega` si no hay snapshot.

## Flag ON (normalizado)

- [ ] Pedidos: lista de direcciones verificadas (principal primero) + card preview al seleccionar.
- [ ] Sin direcciones: CTA “Generar enlace / solicitar” y, con permiso, “Usar dirección manual” (motivo).
- [ ] Validación al enviar: exige `cliente_direccion_id` o excepción manual.
- [ ] Detalle / Auditar / Revisar: snapshot estructurado (`DireccionPedidoResumen`).
- [ ] “Cambiar dirección” (permisos) → motivo + `POST control_pedidos.cambiar_direccion`; si hay guía, confirma invalidación.

## Auxiliar / bandeja

- [ ] Sidebar → Solicitudes → “Solicitudes de dirección”.
- [ ] Bandeja: filtros estado / acción / con remisión + paginación Gelia.
- [ ] Revisión: diff campo a campo, remisión descargable, vincular vía búsqueda `/api/clientes`, notas en rechazo/corrección.

## Admin / clientes

- [ ] Admin Clientes: MapPin con badge de conteo o “!” si hay sin verificar.
- [ ] Modal editar cliente: enlace “Administrar direcciones de envío”.
- [ ] Index direcciones: alta/edición modal, principal, desactivar, copiar enlace, revocar vigentes.

## Público

- [ ] Formulario: secciones Identidad → Acción → Destinatario → Domicilio → Remisión → Comentario.
- [ ] Campos etiqueta, tipo, indicaciones; CP 5 dígitos; preview de remisión.
- [ ] Confirmación: `pending` → “En revisión” (labels ES).

## Mis Clientes

- [ ] Columna direcciones: acceso Index (permiso ver) + generar enlace rápido.
- [ ] Badge de solicitudes pendientes si aplica; barra para copiar URL del enlace.

## Permisos mínimos de prueba

- `clientes.direcciones.ver`, `.crear`, `.editar`, `.desactivar`, `.generar_enlace`, `.revisar_solicitudes`
- Pedidos: `control_pedidos.direccion.seleccionar`, `.usar_manual`, `.cambiar` / `.cambiar_despues_*`
