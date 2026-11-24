import { esCapturaTienda } from '../flujoCapturaPedido';

/** Cuerpo de «Ver detalles»: captura actual o el circuito logístico anterior. */
export function resolverModalidadDetalle(pedido) {
    const modalidad = pedido?.solicitud_preparacion?.modalidad_entrega;
    if (modalidad === 'municipal') return 'municipal';
    if (modalidad === 'nacional') return 'nacional';
    if (modalidad === 'tienda' || esCapturaTienda(pedido)) return 'tienda';
    return 'legado';
}

export function etiquetaRespuestaDetalle(pedido) {
    if (pedido?.solicitud_preparacion?.revision_disponibilidad
        || pedido?.tarea_preparacion?.respuesta?.respondida
        || pedido?.pesaje_respondido_at) {
        return 'Recibida';
    }
    if (pedido?.resguardo_apartado_at) return 'Apartado confirmado';
    return 'Pendiente';
}

export function textoPagosPedido(pedido) {
    const fuentes = pedido?.fuentes_pago?.length
        ? pedido.fuentes_pago
        : (pedido?.banco?.nombre ? [pedido.banco.nombre] : []);
    const texto = fuentes.filter(Boolean).join(', ');
    return texto || null;
}

export const META_MODALIDAD_DETALLE = {
    tienda: { label: 'Tienda' },
    municipal: { label: 'Envío municipal' },
    nacional: { label: 'Envío nacional' },
    legado: { label: 'Pedido anterior' },
};
