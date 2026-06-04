export const FACTURAS_TABS = [
    { id: 'TODAS', label: 'Todas' },
    { id: 'PENDIENTES', label: 'Pendientes' },
    { id: 'RESPONDIDAS', label: 'Respondidas' },
    { id: 'VERIFICADAS', label: 'Verificadas' },
    { id: 'INCORRECTAS', label: 'Incorrectas' },
];

const TAB_ESTADO_NOMBRE = {
    PENDIENTES: 'Pendiente',
    RESPONDIDAS: 'Respondida',
    VERIFICADAS: 'Verificada',
    INCORRECTAS: 'Incorrecta',
};

export function nombreEstadoFactura(factura) {
    return factura?.estado?.nombre || '';
}

/** Filtra solicitudes por pestaña (misma lógica que el backend). */
export function facturaCoincideTab(factura, tab) {
    if (!tab || tab === 'TODAS') return true;

    const estadoNombre = nombreEstadoFactura(factura);

    if (tab === 'CANCELADAS') {
        return estadoNombre.toLowerCase() === 'cancelada';
    }

    const esperado = TAB_ESTADO_NOMBRE[tab];
    return esperado != null && estadoNombre === esperado;
}

export function filtrarFacturasPorTab(facturas, tab) {
    const lista = Array.isArray(facturas) ? facturas : facturas?.data ?? [];
    return lista.filter((f) => facturaCoincideTab(f, tab));
}

/** Resuelve id de catálogo por nombre (props estados del backend). */
export function idEstadoPorNombre(estados, nombre) {
    return estados?.find((e) => e.nombre === nombre)?.id ?? null;
}
