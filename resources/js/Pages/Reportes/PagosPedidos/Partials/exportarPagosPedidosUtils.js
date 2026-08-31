/** Utilidades compartidas del modal de exportación. */

export const TIPOS_FECHA = [
    { value: 'validacion', label: 'Fecha de validación', hint: 'Cuando la Auxiliar validó el pago' },
    { value: 'reportada', label: 'Fecha reportada', hint: 'Registro de la exhibición en GELIA' },
    { value: 'pago', label: 'Fecha del pago', hint: 'Fecha declarada de la operación bancaria' },
    { value: 'pedido', label: 'Fecha del pedido', hint: 'Creación del pedido' },
];

export const PRESETS_RAPIDOS = [
    { id: 'HOY', label: 'Hoy' },
    { id: 'AYER', label: 'Ayer' },
    { id: 'ULTIMOS_7', label: 'Últimos 7 días' },
    { id: 'MES_ACTUAL', label: 'Mes actual' },
    { id: 'MES_ANTERIOR', label: 'Mes anterior' },
];

export const ESTADOS_EXHIBICION = [
    { value: 'verificado', label: 'Verificado' },
    { value: 'con_observaciones', label: 'Con observaciones' },
    { value: 'rechazado', label: 'Rechazado' },
    { value: 'sustituido', label: 'Sustituido' },
];

export const ESTADOS_COBERTURA = [
    { value: 'cubierto', label: 'Cubierto' },
    { value: 'parcial', label: 'Pendiente' },
    { value: 'con_excedente', label: 'Con excedente' },
    { value: 'sin_pago', label: 'Sin pagos recuperados' },
];

export const FORMATOS_EXPORT = [
    { value: 'pdf', label: 'PDF administrativo' },
    { value: 'csv_resumen', label: 'CSV resumen' },
    { value: 'csv_detalle', label: 'CSV por exhibición' },
];

export const AGRUPACIONES = [
    { value: 'dia', label: 'Por día' },
    { value: 'banco', label: 'Por banco' },
    { value: 'vendedora', label: 'Por vendedora' },
];

export const FORMATOS_VOUCHERS_EXPORT = [
    { value: 'pdf', label: 'PDF vouchers validados' },
    { value: 'csv_resumen', label: 'CSV por exhibición' },
];

export const AGRUPACIONES_VOUCHERS = [
    { value: 'movimiento', label: 'Por fecha del movimiento' },
    { value: 'banco', label: 'Por banco' },
    { value: 'forma_pago', label: 'Por forma de pago' },
];

function fmt(d) {
    return d.toISOString().slice(0, 10);
}

export function rangoPresetExport(presetId) {
    const hoy = new Date();
    const hoyStr = fmt(hoy);

    if (presetId === 'HOY') {
        return { fecha_desde: hoyStr, fecha_hasta: hoyStr };
    }
    if (presetId === 'AYER') {
        const a = new Date();
        a.setDate(a.getDate() - 1);
        const s = fmt(a);
        return { fecha_desde: s, fecha_hasta: s };
    }
    if (presetId === 'ULTIMOS_7') {
        const d = new Date();
        d.setDate(d.getDate() - 6);
        return { fecha_desde: fmt(d), fecha_hasta: hoyStr };
    }
    if (presetId === 'MES_ACTUAL') {
        const d = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
        return { fecha_desde: fmt(d), fecha_hasta: hoyStr };
    }
    if (presetId === 'MES_ANTERIOR') {
        const inicio = new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1);
        const fin = new Date(hoy.getFullYear(), hoy.getMonth(), 0);
        return { fecha_desde: fmt(inicio), fecha_hasta: fmt(fin) };
    }
    return null;
}

/** @param {Record<string, unknown>} filtrosConsulta */
export function estadoInicialExport(filtrosConsulta = {}) {
    const tipoFecha = filtrosConsulta.tipo_fecha
        || (filtrosConsulta.fecha_pedido_desde || filtrosConsulta.fecha_pedido_hasta ? 'pedido'
            : (filtrosConsulta.fecha_reportada_desde || filtrosConsulta.fecha_reportada_hasta ? 'reportada'
                : (filtrosConsulta.fecha_pago_desde || filtrosConsulta.fecha_pago_hasta ? 'pago' : 'validacion')));

    const fechaDesde = filtrosConsulta.fecha_desde
        || filtrosConsulta.fecha_validacion_desde
        || filtrosConsulta.fecha_pedido_desde
        || filtrosConsulta.fecha_reportada_desde
        || filtrosConsulta.fecha_pago_desde
        || '';
    const fechaHasta = filtrosConsulta.fecha_hasta
        || filtrosConsulta.fecha_validacion_hasta
        || filtrosConsulta.fecha_pedido_hasta
        || filtrosConsulta.fecha_reportada_hasta
        || filtrosConsulta.fecha_pago_hasta
        || '';

    const bancoIds = Array.isArray(filtrosConsulta.banco_ids)
        ? filtrosConsulta.banco_ids.map(String)
        : (filtrosConsulta.banco_id ? [String(filtrosConsulta.banco_id)] : []);

    const formasPago = Array.isArray(filtrosConsulta.formas_pago)
        ? [...filtrosConsulta.formas_pago]
        : (filtrosConsulta.forma_pago ? [filtrosConsulta.forma_pago] : []);

    const estadosExhibicion = Array.isArray(filtrosConsulta.estados_exhibicion)
        ? [...filtrosConsulta.estados_exhibicion]
        : (filtrosConsulta.estado_exhibicion ? [filtrosConsulta.estado_exhibicion] : []);

    const estadosCobertura = Array.isArray(filtrosConsulta.estados_cobertura)
        ? [...filtrosConsulta.estados_cobertura]
        : (filtrosConsulta.estado_cobertura ? [filtrosConsulta.estado_cobertura] : []);

    const tipoReporte = filtrosConsulta.tipo_reporte || 'pedido';

    return {
        tipo_reporte: tipoReporte,
        tipo_fecha: tipoFecha,
        fecha_desde: fechaDesde,
        fecha_hasta: fechaHasta,
        banco_ids: bancoIds,
        sin_banco: Boolean(filtrosConsulta.sin_banco),
        formas_pago: formasPago,
        estados_exhibicion: estadosExhibicion,
        estados_cobertura: estadosCobertura,
        referencia_bancaria: filtrosConsulta.referencia_bancaria || '',
        departamento_id: filtrosConsulta.departamento_id ? String(filtrosConsulta.departamento_id) : '',
        vendedor_id: filtrosConsulta.vendedor_id ? String(filtrosConsulta.vendedor_id) : '',
        almacen_id: filtrosConsulta.almacen_id ? String(filtrosConsulta.almacen_id) : '',
        cliente_busqueda: filtrosConsulta.busqueda || '',
        origen_pedido: filtrosConsulta.origen_pedido || '',
        con_remision: filtrosConsulta.con_remision ?? '',
        con_evidencia: filtrosConsulta.con_evidencia ?? '',
        estado_cierre: filtrosConsulta.estado_cierre || 'vigente',
        formato: 'pdf',
        incluir_vouchers: filtrosConsulta.incluir_vouchers !== false,
        incluir_evidencias_rechazadas_sustituidas: filtrosConsulta.incluir_evidencias_rechazadas_sustituidas !== false,
        incluir_referencias_remision: filtrosConsulta.incluir_referencias_remision !== false,
        incluir_observaciones_historial: filtrosConsulta.incluir_observaciones_historial !== false,
        orden: filtrosConsulta.orden || 'desc',
        agrupar_por: filtrosConsulta.agrupar_por || (tipoReporte === 'vouchers' ? 'movimiento' : 'dia'),
        reportado_posteriormente: Boolean(filtrosConsulta.reportado_posteriormente),
        posible_duplicado: Boolean(filtrosConsulta.posible_duplicado),
        con_saf_relacionado: Boolean(filtrosConsulta.con_saf_relacionado),
        con_observaciones: Boolean(filtrosConsulta.con_observaciones),
        calidad_imagen: filtrosConsulta.calidad_imagen || 'normal',
        incluir_desglose_financiero: filtrosConsulta.incluir_desglose_financiero !== false,
        incluir_remisiones_completas: Boolean(filtrosConsulta.incluir_remisiones_completas),
    };
}

/** @param {ReturnType<typeof estadoInicialExport>} estado */
export function payloadExport(estado) {
    const params = {
        tipo_reporte: estado.tipo_reporte || 'pedido',
        tipo_fecha: estado.tipo_fecha,
        fecha_desde: estado.fecha_desde || null,
        fecha_hasta: estado.fecha_hasta || null,
        banco_ids: estado.banco_ids.map(Number).filter(Boolean),
        sin_banco: estado.sin_banco ? '1' : null,
        formas_pago: estado.formas_pago,
        estados_exhibicion: estado.estados_exhibicion,
        estados_cobertura: estado.estados_cobertura,
        referencia_bancaria: estado.referencia_bancaria.trim() || null,
        departamento_id: estado.departamento_id || null,
        vendedor_id: estado.vendedor_id || null,
        almacen_id: estado.almacen_id || null,
        busqueda: estado.cliente_busqueda.trim() || null,
        origen_pedido: estado.origen_pedido.trim() || null,
        con_remision: estado.con_remision || null,
        con_evidencia: estado.con_evidencia || null,
        estado_cierre: estado.estado_cierre,
        incluir_vouchers: estado.incluir_vouchers ? '1' : '0',
        incluir_evidencias_rechazadas_sustituidas: estado.incluir_evidencias_rechazadas_sustituidas ? '1' : '0',
        incluir_referencias_remision: estado.incluir_referencias_remision ? '1' : '0',
        incluir_observaciones_historial: estado.incluir_observaciones_historial ? '1' : '0',
        orden: estado.orden,
        agrupar_por: estado.agrupar_por,
        formato: estado.formato,
        reportado_posteriormente: estado.reportado_posteriormente ? '1' : null,
        posible_duplicado: estado.posible_duplicado ? '1' : null,
        con_saf_relacionado: estado.con_saf_relacionado ? '1' : null,
        con_observaciones: estado.con_observaciones ? '1' : null,
        calidad_imagen: estado.calidad_imagen || null,
        incluir_desglose_financiero: estado.incluir_desglose_financiero ? '1' : '0',
        incluir_remisiones_completas: estado.incluir_remisiones_completas ? '1' : null,
    };

    return Object.fromEntries(
        Object.entries(params).filter(([, v]) => v != null && v !== '' && !(Array.isArray(v) && v.length === 0))
    );
}

export function queryStringExport(payload) {
    const parts = [];
    for (const [key, value] of Object.entries(payload)) {
        if (Array.isArray(value)) {
            value.forEach((item) => parts.push(`${encodeURIComponent(key)}[]=${encodeURIComponent(item)}`));
        } else {
            parts.push(`${encodeURIComponent(key)}=${encodeURIComponent(value)}`);
        }
    }
    return parts.join('&');
}
