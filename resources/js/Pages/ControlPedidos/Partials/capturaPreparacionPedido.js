import { esTrasladoInternoPdv } from './destinoSucursalPedido';

/** Serialización e hidratación de la intención de preparación. Sin binarios. */

export const ENTREGA_MUNICIPAL_VACIA = {
    destinatario_es_cliente: true,
    destinatario_nombre: '',
    destinatario_telefono: '',
    municipio_destino: '',
    direccion_referencia: '',
    catalogo_paqueteria_id: '',
    modalidad_cobro: 'PAGADO',
};

export function entregaMunicipalVacia() {
    return { ...ENTREGA_MUNICIPAL_VACIA };
}

export function estadoPreparacionVacio() {
    return {
        codigoModalidad: '',
        prioridadMd: false,
        folioReferencia: '',
        tipoReferencia: 'COTIZACION',
        entregaMunicipal: entregaMunicipalVacia(),
    };
}

function normalizarEntrega(raw) {
    const entrega = entregaMunicipalVacia();
    if (!raw || typeof raw !== 'object') return entrega;
    if (Object.prototype.hasOwnProperty.call(raw, 'destinatario_es_cliente')) {
        entrega.destinatario_es_cliente = Boolean(raw.destinatario_es_cliente);
    }
    entrega.destinatario_nombre = raw.destinatario_nombre || '';
    entrega.destinatario_telefono = raw.destinatario_telefono || '';
    entrega.municipio_destino = raw.municipio_destino || '';
    entrega.direccion_referencia = raw.direccion_referencia || '';
    entrega.catalogo_paqueteria_id = raw.catalogo_paqueteria_id ? String(raw.catalogo_paqueteria_id) : '';
    entrega.modalidad_cobro = raw.modalidad_cobro === 'POR_COBRAR' ? 'POR_COBRAR' : 'PAGADO';
    return entrega;
}

function municipalDeTarea(tarea) {
    const municipal = tarea?.entrega_municipal;
    if (!municipal || typeof municipal !== 'object') return null;
    if (municipal.municipio_destino || municipal.destinatario_nombre || municipal.catalogo_paqueteria_id || municipal.direccion_referencia) {
        return municipal;
    }
    return null;
}

export function estadoPreparacionDesdePedido(pedido) {
    if (!pedido) return estadoPreparacionVacio();
    const solicitud = pedido.solicitud_preparacion || pedido.solicitud_preparacion_json || null;
    const tarea = pedido.tarea_preparacion || null;
    const municipal = municipalDeTarea(tarea) || solicitud?.entrega_municipal || null;
    const referencia = pedido.referencia_captura || null;

    return {
        codigoModalidad: tarea?.modalidad?.codigo || solicitud?.codigo_modalidad || '',
        prioridadMd: Boolean(pedido.prioridad_md),
        folioReferencia: referencia?.folio || solicitud?.folio_referencia || '',
        tipoReferencia: referencia?.tipo || solicitud?.tipo_referencia || 'COTIZACION',
        entregaMunicipal: municipal ? normalizarEntrega(municipal) : entregaMunicipalVacia(),
        trasladoInternoPdv: pedido?.origen?.requiere_logistica
            ? (solicitud && Object.prototype.hasOwnProperty.call(solicitud, 'traslado_interno_pdv')
                ? Boolean(solicitud.traslado_interno_pdv)
                : esTrasladoInternoPdv(pedido))
            : false,
    };
}

export function estadoPreparacionDesdeLocal(borrador) {
    const captura = borrador?.captura_preparacion;
    if (!captura || typeof captura !== 'object') return estadoPreparacionVacio();
    return {
        codigoModalidad: captura.codigo_modalidad || '',
        prioridadMd: Boolean(captura.prioridad_md),
        folioReferencia: captura.folio_referencia || '',
        tipoReferencia: captura.tipo_referencia || 'COTIZACION',
        entregaMunicipal: captura.entrega_municipal ? normalizarEntrega(captura.entrega_municipal) : entregaMunicipalVacia(),
        trasladoInternoPdv: typeof captura.traslado_interno_pdv === 'boolean'
            ? captura.traslado_interno_pdv
            : Boolean(borrador?.origen?.requiere_logistica) && esTrasladoInternoPdv(borrador),
    };
}

function tieneDatoMunicipal(entrega) {
    return Boolean(
        entrega?.destinatario_es_cliente === false
        || String(entrega?.municipio_destino || '').trim()
        || String(entrega?.destinatario_nombre || '').trim()
        || String(entrega?.destinatario_telefono || '').trim()
        || String(entrega?.direccion_referencia || '').trim()
        || entrega?.catalogo_paqueteria_id
    );
}

export function serializarCapturaPreparacion(estado) {
    const entrega = estado?.entregaMunicipal || entregaMunicipalVacia();
    const folio = String(estado?.folioReferencia || '').trim();
    const municipal = estado?.codigoModalidad === 'ENVIO_MUNICIPIO' || tieneDatoMunicipal(entrega);

    return {
        version: 1,
        codigo_modalidad: estado?.codigoModalidad || null,
        tipo_referencia: folio ? (estado?.tipoReferencia || null) : null,
        folio_referencia: folio || null,
        traslado_interno_pdv: typeof estado?.trasladoInternoPdv === 'boolean' ? estado.trasladoInternoPdv : undefined,
        entrega_municipal: municipal ? {
            destinatario_es_cliente: Boolean(entrega.destinatario_es_cliente),
            destinatario_nombre: String(entrega.destinatario_nombre || '').trim() || null,
            destinatario_telefono: String(entrega.destinatario_telefono || '').trim() || null,
            municipio_destino: String(entrega.municipio_destino || '').trim() || null,
            direccion_referencia: String(entrega.direccion_referencia || '').trim() || null,
            catalogo_paqueteria_id: entrega.catalogo_paqueteria_id ? Number(entrega.catalogo_paqueteria_id) : null,
            modalidad_cobro: entrega.modalidad_cobro === 'POR_COBRAR' ? 'POR_COBRAR' : 'PAGADO',
        } : null,
    };
}

export function capturaLocalDesdeEstado(estado) {
    return {
        ...serializarCapturaPreparacion(estado),
        prioridad_md: Boolean(estado?.prioridadMd),
    };
}

/** Omite la intención vacía para no inventar datos en un histórico. */
export function camposPreparacionParaGuardar(estado, confirmada = null) {
    const solicitud = serializarCapturaPreparacion(estado);
    const vacia = !solicitud.codigo_modalidad && !solicitud.folio_referencia && !solicitud.entrega_municipal
        && typeof solicitud.traslado_interno_pdv !== 'boolean';
    const habia = Boolean(
        confirmada
        && (confirmada.codigo_modalidad || confirmada.folio_referencia || confirmada.entrega_municipal)
    );
    const payload = {};
    if (!vacia || habia) payload.solicitud_preparacion = solicitud;
    if (estado?.prioridadMd || confirmada?.prioridad_md) {
        payload.prioridad_md = Boolean(estado?.prioridadMd);
    }
    return payload;
}

/**
 * No asigna un borrador de otro usuario o de otro pedido.
 * Un v1/v2 sin usuario_id sigue siendo legible: no trae la estructura nueva.
 */
export function borradorLocalAplicable(borrador, { usuarioId = null, pedidoId = null } = {}) {
    if (!borrador || typeof borrador !== 'object') return null;
    if (borrador.usuario_id != null && usuarioId != null && String(borrador.usuario_id) !== String(usuarioId)) {
        return null;
    }
    if (borrador.pedido_id != null && pedidoId != null && String(borrador.pedido_id) !== String(pedidoId)) {
        return null;
    }
    return borrador;
}
