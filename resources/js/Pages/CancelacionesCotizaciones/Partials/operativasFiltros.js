const TAB_ESTADO_ID = {
    PENDIENTES: 1,
    RESPONDIDAS: 2,
    VERIFICADAS: 3,
    INCORRECTAS: 4,
};

/** Misma lógica que ListarSolicitudesOperativasService::aplicarFiltroTab (página actual, vista optimista). */
export function solicitudCoincideTab(solicitud, tab) {
    if (!tab || tab === 'TODAS') return true;

    const estadoId = Number(solicitud.catalogo_estado_solicitud_id ?? solicitud.estado?.id);
    const estadoNombre = (solicitud.estado?.nombre || '').toLowerCase();

    if (tab === 'CANCELADAS') {
        return estadoNombre === 'cancelada';
    }

    if (tab === 'PENDIENTES') {
        if (estadoId === 1) return true;
        if (estadoNombre === 'cancelada') return false;
        return Boolean(solicitud.cancelacion_solicitada_at);
    }

    const esperado = TAB_ESTADO_ID[tab];
    return esperado != null && estadoId === esperado;
}

export function filtrarSolicitudesPorTab(solicitudes, tab) {
    const lista = Array.isArray(solicitudes) ? solicitudes : solicitudes?.data ?? [];
    return lista.filter((s) => solicitudCoincideTab(s, tab));
}
