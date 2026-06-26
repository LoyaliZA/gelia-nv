export const contabilidadRoutes = {
    index: () => route('contabilidad.index'),
    retiros: () => route('contabilidad.retiros'),
    dashboardData: () => route('contabilidad.dashboard_data'),
    procesarLista: () => route('contabilidad.procesar_lista'),
    pedidosStore: () => route('contabilidad.pedidos.store'),
    pedidosUpdate: (id) => route('contabilidad.pedidos.update', id),
    pedidosDestroy: (id) => route('contabilidad.pedidos.destroy', id),
    pedidosConfirmarRetiro: (id) => route('contabilidad.pedidos.confirmar_retiro', id),
    retirosConfirmarLote: () => route('contabilidad.retiros.confirmar_lote'),
    plataformasComisiones: () => route('contabilidad.plataformas.comisiones'),
    exportarPdf: (params) => route('contabilidad.exportar_pdf', params),
    exportarCsv: (params) => route('contabilidad.exportar_csv', params),
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
