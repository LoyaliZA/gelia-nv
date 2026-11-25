import { etiquetaSucursal } from './pedidosBmaStyles';
import { pedidoEsTrasladoInternoPdv } from './destinoSucursalPedido';

function recolectaEnSucursal(pedido) {
    return Boolean(pedido?.cambio_a_tienda || pedido?.solicitud_preparacion?.cambio_a_tienda);
}

/** Destino nacional: quien recibe y domicilio. Sucursal solo si hay recolección en sucursal. */
export function destinoDomicilioNacional(pedido = {}, { prefijoRecibe = false } = {}) {
    if (recolectaEnSucursal(pedido)) {
        const sucursal = pedido.sucursal_destino || pedido.sucursalDestino;
        return sucursal ? etiquetaSucursal(sucursal) : pedido.sucursal_destino_nombre || 'Sucursal por confirmar';
    }
    const domicilio = pedido.direccion_vigente || pedido.direccionVigente;
    const recibe = String(domicilio?.nombre_destinatario || '').trim();
    const lugar = [domicilio?.municipio || domicilio?.ciudad, domicilio?.estado].filter(Boolean).join(', ');
    const cuerpo = [recibe, lugar].filter(Boolean).join(' · ');
    if (!cuerpo) return 'Domicilio por confirmar';
    return prefijoRecibe && recibe ? `Recibe: ${cuerpo}` : cuerpo;
}

/** Presentación comercial; no cambia la modalidad ni activa un recorrido operativo. */
export function presentacionPedidoCedis(pedido = {}) {
    const solicitud = pedido.solicitud_preparacion || {};
    let modalidad = solicitud.modalidad_entrega;
    if (!modalidad && solicitud.codigo_modalidad === 'ENVIO_MUNICIPIO') modalidad = 'municipal';
    if (!modalidad && !pedidoEsTrasladoInternoPdv(pedido)
        && !String(solicitud.codigo_modalidad || '').startsWith('RECOGE_TIENDA')
        && pedido.origen?.requiere_logistica === true) modalidad = 'nacional';
    const municipal = modalidad === 'municipal';
    const nacional = modalidad === 'nacional';
    const envio = municipal || nacional;
    const sucursal = pedido.sucursal_destino || pedido.sucursalDestino;
    const cambioSucursal = nacional && recolectaEnSucursal(pedido);
    return {
        modalidad,
        etiqueta: envio ? 'Envío:' : 'Tipo de pedido',
        nombre: municipal ? 'MUNICIPAL' : nacional ? 'NACIONAL' : pedido.origen?.nombre || 'Tipo no disponible',
        origen: pedido.almacen?.nombre || pedido.almacen?.codigo
            || (!envio && solicitud.area_revision === 'control_pedidos' ? 'CEDIS' : 'Por confirmar'),
        destino: municipal ? pedido.paqueteria?.nombre || 'Por confirmar'
            : nacional ? destinoDomicilioNacional(pedido)
            : sucursal ? etiquetaSucursal(sucursal) : pedido.sucursal_destino_nombre || pedido.expediente_registrar?.sucursal || 'Por confirmar',
        tipoDestino: municipal ? 'terminal' : (nacional && !cambioSucursal) ? 'domicilio' : 'sucursal',
    };
}
