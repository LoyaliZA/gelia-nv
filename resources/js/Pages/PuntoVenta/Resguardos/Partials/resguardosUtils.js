export function paramsLimpios(params) {
    return Object.fromEntries(
        Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== undefined)
    );
}

export function paramsListadoResguardos({
    bandeja,
    q,
    estado,
    antiguedad,
    page = 1,
}) {
    return paramsLimpios({
        bandeja,
        q: q || undefined,
        estado: estado || undefined,
        antiguedad: antiguedad || undefined,
        page,
    });
}

/** Referencia de cliente sin nombre completo. */
export function referenciaCliente(resguardo) {
    const numero = resguardo?.cliente?.numero_cliente;
    if (numero !== null && numero !== undefined && numero !== '') {
        return `#${numero}`;
    }
    return resguardo?.snapshot_folio || 'Sin referencia';
}

export function etiquetasClasificacionActivas(resguardo, catalogoAntiguedades = {}) {
    const clasificaciones = resguardo?.clasificaciones || {};
    return Object.entries(clasificaciones)
        .filter(([, activa]) => activa)
        .map(([clave]) => catalogoAntiguedades[clave] || clave);
}

const ANTIGUEDAD_POR_BANDEJA = {
    por_recibir: ['rezagado'],
    en_custodia: ['proximo_a_vencer', 'vencido'],
};

export function antiguedadValidaEnBandeja(bandeja, antiguedad) {
    if (!antiguedad) return true;
    const claves = ANTIGUEDAD_POR_BANDEJA[bandeja] || [];
    return claves.includes(antiguedad);
}

export function antiguedadesVisiblesPorBandeja(bandeja, catalogoAntiguedades = {}, puedeVerVencidos = false) {
    const claves = ANTIGUEDAD_POR_BANDEJA[bandeja] || [];
    return Object.entries(catalogoAntiguedades)
        .filter(([clave]) => claves.includes(clave))
        .filter(([clave]) => clave !== 'vencido' || puedeVerVencidos);
}

export function metricasAntiguedadClaves(bandeja, puedeVerVencidos = false) {
    const claves = ANTIGUEDAD_POR_BANDEJA[bandeja] || [];
    return claves.filter((clave) => clave !== 'vencido' || puedeVerVencidos);
}

/**
 * Plazos calculados por backend; la UI solo presenta fecha y categoría.
 * @returns {Array<{ id: string, etiqueta: string, fecha: string, clasificacion: string|null }>}
 */
export function plazosOperativosResguardo(resguardo) {
    const clasificaciones = resguardo?.clasificaciones || {};
    const items = [];

    if (resguardo?.fecha_limite_rezago && resguardo?.estado === 'pendiente_recepcion') {
        items.push({
            id: 'rezago',
            etiqueta: 'Límite recepción',
            fecha: resguardo.fecha_limite_rezago,
            clasificacion: clasificaciones.rezagado ? 'rezagado' : null,
        });
    }

    if (resguardo?.fecha_limite_custodia && resguardo?.estado === 'en_custodia') {
        const clasificacion = clasificaciones.vencido
            ? 'vencido'
            : (clasificaciones.proximo_a_vencer ? 'proximo_a_vencer' : null);
        items.push({
            id: 'custodia',
            etiqueta: 'Límite custodia',
            fecha: resguardo.fecha_limite_custodia,
            clasificacion,
        });
    }

    return items;
}

export function claseVistaTarjetas() {
    return 'lg:hidden';
}

export function claseVistaTabla() {
    return 'hidden lg:block';
}

const MENSAJES_VACIO_BANDEJA = {
    por_recibir: 'No hay resguardos pendientes de recepción en esta sucursal.',
    en_custodia: 'No hay resguardos en custodia en esta sucursal.',
    incidencias: 'No hay resguardos con incidencias abiertas en esta sucursal.',
};

export function mensajeVacioBandeja(bandeja, catalogoBandejas = {}, hayFiltrosActivos = false) {
    if (hayFiltrosActivos) {
        return 'No hay resguardos que coincidan con los filtros aplicados.';
    }
    return MENSAJES_VACIO_BANDEJA[bandeja]
        || `No hay resguardos en ${(catalogoBandejas[bandeja] || 'esta bandeja').toLowerCase()}`;
}
