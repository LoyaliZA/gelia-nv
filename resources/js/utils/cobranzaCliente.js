export function facturasActivasCliente(cliente) {
    return cliente?.facturas_cobranza_activas ?? [];
}

export function parseFechaCobranza(fechaIso) {
    if (!fechaIso) return null;
    const datePart = String(fechaIso).split('T')[0];
    const [y, m, d] = datePart.split('-').map(Number);
    if (!y || !m || !d) return null;
    const fecha = new Date(y, m - 1, d);
    fecha.setHours(0, 0, 0, 0);
    return fecha;
}

export function esFacturaVencida(factura, hoy = new Date()) {
    const vencimiento = parseFechaCobranza(factura?.fecha_vencimiento);
    if (!vencimiento) return false;
    const referencia = new Date(hoy);
    referencia.setHours(0, 0, 0, 0);
    return vencimiento < referencia;
}

export function diasHastaVencimiento(fechaIso, hoy = new Date()) {
    const vencimiento = parseFechaCobranza(fechaIso);
    if (!vencimiento) return null;
    const referencia = new Date(hoy);
    referencia.setHours(0, 0, 0, 0);
    return Math.ceil((vencimiento.getTime() - referencia.getTime()) / (1000 * 60 * 60 * 24));
}

export function saldoTotalCliente(cliente) {
    if (cliente?.saldo_total_pendiente != null && Number(cliente.saldo_total_pendiente) > 0) {
        return Number(cliente.saldo_total_pendiente);
    }
    const facturas = facturasActivasCliente(cliente);
    const total = facturas.reduce((sum, f) => sum + Number(f.monto || 0), 0);
    return total > 0 ? total : null;
}

export function saldoVencidoCliente(cliente) {
    if (cliente?.saldo_vencido != null) {
        return Number(cliente.saldo_vencido);
    }
    const hoy = new Date();
    return facturasActivasCliente(cliente)
        .filter((f) => esFacturaVencida(f, hoy))
        .reduce((sum, f) => sum + Number(f.monto || 0), 0);
}

export function facturasOrdenadasCliente(cliente) {
    const hoy = new Date();
    return [...facturasActivasCliente(cliente)].sort((a, b) => {
        const aVencida = esFacturaVencida(a, hoy);
        const bVencida = esFacturaVencida(b, hoy);
        if (aVencida !== bVencida) return aVencida ? -1 : 1;
        return (parseFechaCobranza(a.fecha_vencimiento)?.getTime() ?? 0)
            - (parseFechaCobranza(b.fecha_vencimiento)?.getTime() ?? 0);
    });
}

export function todasFacturasCliente(cliente) {
    if (cliente?.facturas_cobranza != null) {
        return cliente.facturas_cobranza;
    }

    return cliente?.facturas_cobranza_activas ?? [];
}

export function facturasOrdenadasHistorialCliente(cliente) {
    const hoy = new Date();
    return [...todasFacturasCliente(cliente)].sort((a, b) => {
        if (Boolean(a.pagada) !== Boolean(b.pagada)) {
            return a.pagada ? 1 : -1;
        }

        const aVencida = !a.pagada && esFacturaVencida(a, hoy);
        const bVencida = !b.pagada && esFacturaVencida(b, hoy);
        if (aVencida !== bVencida) return aVencida ? -1 : 1;

        return (parseFechaCobranza(b.fecha_vencimiento)?.getTime() ?? 0)
            - (parseFechaCobranza(a.fecha_vencimiento)?.getTime() ?? 0);
    });
}

export function cantidadFoliosHistorialCliente(cliente) {
    return todasFacturasCliente(cliente).length;
}

export function facturaCriticaCliente(cliente) {
    const ordenadas = facturasOrdenadasCliente(cliente);
    if (ordenadas.length === 0) {
        return cliente?.factura_cobranza_activa ?? null;
    }
    const hoy = new Date();
    const vencidas = ordenadas.filter((f) => esFacturaVencida(f, hoy));
    return vencidas[0] ?? ordenadas[0];
}
