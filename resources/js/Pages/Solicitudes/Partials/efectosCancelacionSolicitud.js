import { leerSnapshotSeguro } from './solicitudHistorial';

const nombreClave = (nombre) => (nombre || '').replace(/\s+/g, ' ').trim().toUpperCase();

export const nombresCancelacionIguales = (a, b) => Boolean(nombreClave(a)) && nombreClave(a) === nombreClave(b);

export function snapshotAntesSolicitud(solicitud) {
    const auditorias = [...(solicitud?.auditorias || [])].sort((a, b) => (a.id || 0) - (b.id || 0));
    for (const registro of auditorias) {
        const antes = leerSnapshotSeguro(registro.datos_snapshot)?.antes;
        if (antes && typeof antes === 'object') return antes;
    }
    return null;
}

function nombreEstado(solicitud) {
    return solicitud?.estado?.nombre || '';
}

function solicitudCancelada(solicitud) {
    return nombreEstado(solicitud).toLowerCase() === 'cancelada';
}

function estadoAnteriorNombre(registro) {
    return registro?.estado_anterior?.nombre || registro?.estadoAnterior?.nombre || '';
}

function fueAplicada(solicitud) {
    const estado = nombreEstado(solicitud);
    if (['Respondida', 'Verificada'].includes(estado)) return true;
    if (estado.toLowerCase() !== 'cancelada') return false;
    return (solicitud.auditorias || []).some((registro) => ['Respondida', 'Verificada'].includes(estadoAnteriorNombre(registro)));
}

function nombreProceso(solicitud) {
    return (solicitud?.proceso?.nombre || '').toUpperCase();
}

export function efectosCancelacionSolicitud(solicitud) {
    const proceso = nombreProceso(solicitud);
    const asignaTag = proceso.includes('TAG') || proceso.includes('ASIGNAR CLIENTE');
    const pideLista = proceso.includes('LISTA') && Boolean(
        solicitud?.catalogo_lista_descuento_id
        || solicitud?.lista_descuento
        || solicitud?.listaDescuento,
    );
    const antes = snapshotAntesSolicitud(solicitud);
    const listaSolicitada = (solicitud?.lista_descuento || solicitud?.listaDescuento)?.nombre || null;
    const listaCliente = solicitud?.cliente?.lista_almacenada_nombre
        || solicitud?.cliente?.lista_descuento?.nombre
        || solicitud?.cliente?.listaDescuento?.nombre
        || solicitud?.cliente?.lista_actual
        || null;
    const listaRegreso = antes?.lista_nombre || listaCliente;
    const tipoSolicitado = solicitud?.tipo_cliente?.nombre || solicitud?.tipoCliente?.nombre || null;
    const tipoRegreso = antes?.tipo_cliente_nombre
        || solicitud?.cliente?.tipo?.nombre
        || solicitud?.cliente?.tipo_cliente?.nombre
        || solicitud?.cliente?.tipoCliente?.nombre
        || null;
    const cancelada = solicitudCancelada(solicitud);
    const pendienteCancelacion = Boolean(solicitud?.cancelacion_solicitada_at) && !cancelada;
    const aplicada = fueAplicada(solicitud);
    const muestraRegreso = cancelada || pendienteCancelacion;
    const cambiaLista = pideLista && listaSolicitada && listaRegreso && !nombresCancelacionIguales(listaSolicitada, listaRegreso);
    const cambiaTipo = tipoSolicitado && tipoRegreso && !nombresCancelacionIguales(tipoSolicitado, tipoRegreso);
    const tagNombre = solicitud?.vendedor?.name || null;
    const tagAnterior = antes?.tag_vendedor_nombre || null;
    const montoOperacional = Number(solicitud?.monto_aplicado_al_cliente || 0);

    return {
        asignaTag,
        retiraTag: asignaTag && (muestraRegreso || (!aplicada && (cancelada || pendienteCancelacion))),
        cambiaLista,
        cambiaTipo,
        listaSolicitada,
        listaRegreso,
        listaCliente,
        tipoSolicitado,
        tipoRegreso,
        tagNombre,
        tagAnterior,
        cancelada,
        pendienteCancelacion,
        aplicada,
        muestraRegreso,
        montoOperacional,
        revierteBeneficios: aplicada && !cancelada,
    };
}

export function recorridoListaCancelacion(solicitud) {
    const efectos = efectosCancelacionSolicitud(solicitud);
    if (!efectos.cambiaLista) {
        return {
            origen: efectos.listaCliente || efectos.listaRegreso || efectos.listaSolicitada,
            destino: null,
            hayCambio: false,
            descenso: false,
            cancelada: efectos.cancelada || efectos.muestraRegreso,
        };
    }
    if (efectos.muestraRegreso) {
        return {
            origen: efectos.listaSolicitada,
            destino: efectos.listaRegreso,
            hayCambio: true,
            descenso: true,
            cancelada: true,
        };
    }
    return {
        origen: efectos.listaRegreso,
        destino: efectos.listaSolicitada,
        hayCambio: true,
        descenso: false,
        cancelada: efectos.cancelada,
    };
}

export function recorridoTipoCancelacion(solicitud) {
    const efectos = efectosCancelacionSolicitud(solicitud);
    if (!efectos.cambiaTipo) {
        return {
            origen: efectos.tipoRegreso || efectos.tipoSolicitado,
            destino: null,
            hayCambio: false,
            cancelada: efectos.cancelada || efectos.muestraRegreso,
        };
    }
    if (efectos.muestraRegreso) {
        return {
            origen: efectos.tipoSolicitado,
            destino: efectos.tipoRegreso,
            hayCambio: true,
            cancelada: true,
        };
    }
    return {
        origen: efectos.tipoRegreso,
        destino: efectos.tipoSolicitado,
        hayCambio: true,
        cancelada: efectos.cancelada,
    };
}
