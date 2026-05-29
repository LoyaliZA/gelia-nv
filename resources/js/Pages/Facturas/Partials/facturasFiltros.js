export const FACTURAS_TABS = [
    { id: 'TODAS', label: 'Todas' },
    { id: 'PENDIENTES', label: 'Pendientes' },
    { id: 'RESPONDIDAS', label: 'Respondidas' },
    { id: 'VERIFICADAS', label: 'Verificadas' },
    { id: 'INCORRECTAS', label: 'Incorrectas' },
];

const TAB_ESTADO_ID = {
    PENDIENTES: 1,
    RESPONDIDAS: 2,
    VERIFICADAS: 3,
    INCORRECTAS: 4,
};

/** Filtra solicitudes por pestaña (misma lógica que el backend). */
export function facturaCoincideTab(factura, tab) {
    if (!tab || tab === 'TODAS') return true;

    const estadoId = factura.catalogo_estado_solicitud_id ?? factura.estado?.id;
    const estadoNombre = (factura.estado?.nombre || '').toLowerCase();

    if (tab === 'CANCELADAS') {
        return estadoNombre === 'cancelada';
    }

    const esperado = TAB_ESTADO_ID[tab];
    return esperado != null && Number(estadoId) === esperado;
}

export function filtrarFacturasPorTab(facturas, tab) {
    const lista = Array.isArray(facturas) ? facturas : facturas?.data ?? [];
    return lista.filter((f) => facturaCoincideTab(f, tab));
}
