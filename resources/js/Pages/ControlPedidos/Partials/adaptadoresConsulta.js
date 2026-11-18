import axios from 'axios';
import { enviarEnLotes } from './lotesAdjuntosConsulta';

const ESTADOS_RESPUESTA = ['sin_excepciones', 'excepciones_pendientes', 'decision_registrada'];

function cabeceras() {
    return { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
}

function post(url, form, opciones) {
    return axios.post(url, form, opciones);
}

function documentosVigentes(pedido = {}) {
    return (pedido.documentos || []).filter((documento) => documento?.activo !== false && documento?.tipo !== 'pdf_pedido' && documento?.tipo !== 'remision');
}

function bloqueosDe(pedido = {}) {
    return {
        motivo: pedido.motivo_bloqueo_cierre || '',
        puedeCerrar: Boolean(pedido.puede_cerrar_consulta),
        pagoORemision: Boolean(pedido.pago_validado_at || pedido.numero_remision),
    };
}

function comando(url) {
    return ({ archivos, armar }) => enviarEnLotes({ url, archivos, armar, headers: cabeceras(), post });
}

export function adaptadorConsultaTienda({ pedidoId, pedido = {} }) {
    const solicitud = pedido.solicitud_preparacion || {};
    const cerrada = Boolean(pedido.consulta_cerrada || pedido.consulta_cerrada_at);
    const abierta = Boolean(pedidoId) && !cerrada;
    return {
        modalidad: 'tienda',
        almacenId: pedido?.tarea_preparacion?.almacen?.id || pedido?.almacen?.id || null,
        capacidades: {
            anexar: abierta,
            retirar: abierta,
            sustituir: abierta,
            consultar: true,
            adjuntar: abierta,
            solicitarRevision: false,
            historial: true,
            cerrar: Boolean(pedido.puede_cerrar_consulta),
            reabrir: cerrada,
        },
        bloqueos: bloqueosDe(pedido),
        revision: solicitud.revision_disponibilidad || null,
        historial: solicitud,
        evidenciasVigentes: documentosVigentes(pedido),
        comandos: {
            anexar: comando(`/control-pedidos/${pedidoId}/anexo-tienda`),
            retirar: comando(`/control-pedidos/${pedidoId}/retiro-piezas-tienda`),
        },
    };
}

export function adaptadorConsultaMunicipal({ pedidoId, pedido = {} }) {
    const solicitud = pedido.solicitud_preparacion || {};
    const consulta = solicitud.consulta_municipal || {};
    const revision = solicitud.revision_disponibilidad || {};
    const cerrada = consulta.estado === 'cerrada' || Boolean(pedido.consulta_cerrada || pedido.consulta_cerrada_at);
    const respondida = ESTADOS_RESPUESTA.includes(revision.estado);
    const abierta = Boolean(pedidoId) && respondida && !cerrada;
    return {
        modalidad: 'municipal',
        almacenId: consulta.almacen_id || pedido?.almacen?.id || null,
        capacidades: {
            anexar: abierta,
            retirar: abierta,
            sustituir: abierta,
            consultar: true,
            adjuntar: abierta,
            solicitarRevision: false,
            historial: true,
            cerrar: Boolean(pedido.puede_cerrar_consulta) && !cerrada,
            reabrir: cerrada && !pedido.pago_validado_at && !pedido.numero_remision,
        },
        bloqueos: bloqueosDe(pedido),
        revision: revision.estado ? revision : null,
        historial: solicitud,
        evidenciasVigentes: documentosVigentes(pedido),
        extension: { terminal: true, consignatario: true, caratula: false, pesajeNacional: false, tareaFisica: false },
        comandos: {
            anexar: comando(`/control-pedidos/${pedidoId}/anexo-tienda`),
            retirar: comando(`/control-pedidos/${pedidoId}/retiro-piezas-tienda`),
        },
    };
}

/** Contrato Nacional. Sin comandos ni interfaz: la etapa 5 conecta pesaje, cajas y transporte. */
export const contratoConsultaNacional = {
    modalidad: 'nacional',
    capacidades: ['anexar', 'retirar', 'sustituir', 'consultar', 'adjuntar', 'solicitarRevision', 'historial'],
    puntos: ['ModalFormPedidoLegado', 'ActualizarConsultaPedidoBmaService', 'solicitar-repesaje'],
    conserva: ['pesos', 'medidas', 'cajas', 'costos', 'documentosTransporte', 'bloqueosPesaje'],
    prohibido: ['area_revision_control_pedidos_como_consulta_sin_peso', 'pedidoRelacionadoTienda', 'montarEditor'],
};
