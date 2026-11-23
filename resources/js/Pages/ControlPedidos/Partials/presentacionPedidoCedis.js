import { etiquetaSucursal } from './pedidosBmaStyles';
import { pedidoEsTrasladoInternoPdv } from './destinoSucursalPedido';

/** Presentación comercial; no cambia la modalidad ni activa un recorrido operativo. */
export function presentacionPedidoCedis(pedido = {}) {
    const solicitud = pedido.solicitud_preparacion || {};
    let modalidad = solicitud.modalidad_entrega;
    if (!modalidad && solicitud.codigo_modalidad === 'ENVIO_MUNICIPIO') modalidad = 'municipal';
    if (!modalidad && !pedidoEsTrasladoInternoPdv(pedido)
        && !String(solicitud.codigo_modalidad || '').startsWith('RECOGE_TIENDA')
        && pedido.origen?.requiere_logistica === true) modalidad = 'nacional';
    const municipal = modalidad === 'municipal';
    const envio = municipal || modalidad === 'nacional';
    const sucursal = pedido.sucursal_destino || pedido.sucursalDestino;
    // Nacional con domicilio ya capturado: el destino es el lugar de entrega, no «Por confirmar».
    const domicilio = pedido.direccion_vigente || pedido.direccionVigente;
    const destinoNacional = modalidad === 'nacional' && !sucursal && domicilio
        ? [domicilio.municipio || domicilio.ciudad, domicilio.estado].filter(Boolean).join(', ') || null
        : null;
    return {
        modalidad,
        etiqueta: envio ? 'Envío:' : 'Tipo de pedido',
        nombre: municipal ? 'MUNICIPAL' : modalidad === 'nacional' ? 'NACIONAL' : pedido.origen?.nombre || 'Tipo no disponible',
        origen: pedido.almacen?.nombre || pedido.almacen?.codigo
            || (!envio && solicitud.area_revision === 'control_pedidos' ? 'CEDIS' : 'Por confirmar'),
        destino: municipal ? pedido.paqueteria?.nombre || 'Por confirmar'
            : destinoNacional ? destinoNacional
            : sucursal ? etiquetaSucursal(sucursal) : pedido.sucursal_destino_nombre || pedido.expediente_registrar?.sucursal || 'Por confirmar',
    };
}
