export const TIPO_REPORTE_PEDIDO = 'pedido';
export const TIPO_REPORTE_VOUCHERS = 'vouchers';

/** Filtros con el mismo significado en ambos reportes. */
export const FILTROS_COMPATIBLES_ENTRE_TIPOS = [
    'busqueda',
    'forma_pago',
    'formas_pago',
    'fecha_validacion_desde',
    'fecha_validacion_hasta',
];

/** Solo Pagos por pedido. */
export const FILTROS_SOLO_PEDIDO = [
    'fecha_pedido_desde',
    'fecha_pedido_hasta',
    'estado_cierre',
    'estado_cobertura',
    'estados_cobertura',
    'con_remision',
    'con_evidencia',
    'fecha_incompleta',
    'tipo_fecha',
    'fecha_desde',
    'fecha_hasta',
    'departamento_id',
    'vendedor_id',
    'cliente_id',
    'almacen_id',
    'origen_pedido',
];

/** Solo Vouchers validados. */
export const FILTROS_SOLO_VOUCHERS = [
    'fecha_pago_desde',
    'fecha_pago_hasta',
    'fecha_reportada_desde',
    'fecha_reportada_hasta',
    'agrupar_por',
    'reportado_posteriormente',
    'posible_duplicado',
    'con_saf_relacionado',
    'con_observaciones',
    'capturado_por_id',
    'validado_por_id',
    'monto_desde',
    'monto_hasta',
    'folio_pedido',
    'folio_remision',
    'estado_exhibicion',
    'banco_id',
];

export const FILTROS_LIMPIOS_VOUCHERS = {
    tipo_reporte: TIPO_REPORTE_VOUCHERS,
    busqueda: null,
    fecha_pago_desde: null,
    fecha_pago_hasta: null,
    fecha_reportada_desde: null,
    fecha_reportada_hasta: null,
    fecha_validacion_desde: null,
    fecha_validacion_hasta: null,
    forma_pago: null,
    estado_exhibicion: null,
    banco_id: null,
    capturado_por_id: null,
    validado_por_id: null,
    con_evidencia: null,
    reportado_posteriormente: false,
    posible_duplicado: false,
    con_saf_relacionado: false,
    con_observaciones: false,
    monto_desde: null,
    monto_hasta: null,
    folio_pedido: null,
    folio_remision: null,
    agrupar_por: 'movimiento',
};

/**
 * Conserva filtros compatibles al cambiar de tipo de reporte.
 * @param {Record<string, unknown>} filtrosActuales
 * @param {string} nuevoTipo
 * @returns {Record<string, unknown>}
 */
export function filtrosAlCambiarTipoReporte(filtrosActuales, nuevoTipo) {
    const base = { tipo_reporte: nuevoTipo, page: 1 };
    const compatibles = {};

    for (const key of FILTROS_COMPATIBLES_ENTRE_TIPOS) {
        const val = filtrosActuales?.[key];
        if (val !== null && val !== undefined && val !== '' && val !== []) {
            compatibles[key] = val;
        }
    }

    if (nuevoTipo === TIPO_REPORTE_PEDIDO) {
        return {
            ...base,
            ...compatibles,
            estado_cierre: filtrosActuales?.estado_cierre ?? 'vigente',
        };
    }

    return {
        ...base,
        ...compatibles,
        agrupar_por: 'movimiento',
    };
}

export const OPCIONES_TIPO_REPORTE = [
    {
        id: TIPO_REPORTE_PEDIDO,
        titulo: 'Pagos por pedido',
        descripcion: 'Consulta la composición, cobertura, saldo a favor, remisión y evidencias de cada pedido.',
        disponible: true,
    },
    {
        id: TIPO_REPORTE_VOUCHERS,
        titulo: 'Vouchers validados',
        descripcion: 'Consulta los pagos validados por fecha, banco y forma de pago para apoyar la revisión de ingresos.',
        disponible: true,
    },
];

export function subtituloAgrupacionVouchers(agruparPor) {
    switch (agruparPor) {
        case 'banco':
            return 'Agrupado por banco';
        case 'forma_pago':
            return 'Agrupado por forma de pago';
        default:
            return 'Agrupado por fecha del movimiento';
    }
}
