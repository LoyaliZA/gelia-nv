import React from 'react';
import SeguimientoSolicitudTienda from './SeguimientoSolicitudTienda';
import { esCapturaTienda } from './flujoCapturaPedido';

/** Respuesta actual compartida por las vistas de consulta; no permite modificarla. */
export default function ConsultaSolicitudTienda({ pedido }) {
    if (!esCapturaTienda(pedido)) return null;
    const solicitud = pedido.solicitud_preparacion;
    const area = solicitud.area_revision || (pedido.usa_preparacion_tienda ? 'preparacion_tienda' : 'control_pedidos');
    const enviada = Boolean(pedido.tarea_preparacion || pedido.pesaje_solicitado_at || solicitud.revision_disponibilidad);
    if (!enviada) return <p className="text-sm theme-text-muted m-0">Borrador: la solicitud de revisión aún no se ha enviado.</p>;
    return <SeguimientoSolicitudTienda
        key={pedido.id}
        soloLectura
        pedidoId={pedido.id}
        pedido={pedido}
        area={area}
        nombreAlmacen={pedido.tarea_preparacion?.almacen?.nombre || ''}
        revisiones={pedido.revisiones_producto || pedido.revisionesProducto || []}
        consultaCerrada={Boolean(pedido.consulta_cerrada || pedido.consulta_cerrada_at)}
    />;
}
