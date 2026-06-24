function contabilidadRoute(name, fallback, params) {
    if (typeof route === 'function') {
        try {
            return route(name, params);
        } catch {
            return fallback;
        }
    }
    return fallback;
}

export const contabilidadRoutes = {
    index: () => contabilidadRoute('contabilidad.index', '/contabilidad'),
    retiros: () => contabilidadRoute('contabilidad.retiros', '/contabilidad/retiros'),
    dashboardData: () => contabilidadRoute('contabilidad.dashboard_data', '/contabilidad/dashboard-data'),
    procesarLista: () => contabilidadRoute('contabilidad.procesar_lista', '/contabilidad/procesar-lista'),
    pedidosStore: () => contabilidadRoute('contabilidad.pedidos.store', '/contabilidad/pedidos'),
    pedidosUpdate: (id) => contabilidadRoute('contabilidad.pedidos.update', `/contabilidad/pedidos/${id}`),
    pedidosDestroy: (id) => contabilidadRoute('contabilidad.pedidos.destroy', `/contabilidad/pedidos/${id}`),
    pedidosConfirmarRetiro: (id) => contabilidadRoute('contabilidad.pedidos.confirmar_retiro', `/contabilidad/pedidos/${id}/confirmar-retiro`),
    retirosConfirmarLote: () => contabilidadRoute('contabilidad.retiros.confirmar_lote', '/contabilidad/retiros/confirmar-lote'),
    plataformasComisiones: () => contabilidadRoute('contabilidad.plataformas.comisiones', '/contabilidad/plataformas/comisiones'),
    exportarPdf: (params) => contabilidadRoute('contabilidad.exportar_pdf', '/contabilidad/exportar-pdf', params),
    exportarCsv: (params) => contabilidadRoute('contabilidad.exportar_csv', '/contabilidad/exportar-csv', params),
};

export function montoEsperadoBanco(pedido) {
    const codigo = (pedido.tipo_transaccion?.codigo || 'venta').toLowerCase();
    const venta = Number(pedido.venta_total);
    const comision = Number(pedido.comision_plataforma || 0);
    if (codigo.includes('venta')) {
        return venta - comision;
    }
    return -Math.abs(venta + comision);
}

export const COLORES_PLATAFORMA = {
    paypal: '#3b82f6',
    stripe: '#8b5cf6',
    'mercado pago': '#eab308',
    kueskipay: '#22c55e',
    'open pay': '#14b8a6',
    openpay: '#14b8a6',
};

export function colorPlataforma(nombre) {
    return COLORES_PLATAFORMA[(nombre || '').toLowerCase()] || '#94a3b8';
}
