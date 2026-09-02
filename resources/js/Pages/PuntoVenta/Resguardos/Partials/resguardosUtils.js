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

export function claseVistaTarjetas() {
    return 'lg:hidden';
}

export function claseVistaTabla() {
    return 'hidden lg:block';
}

export function mensajeVacioBandeja(bandeja, catalogoBandejas = {}) {
    const etiqueta = catalogoBandejas[bandeja] || 'esta bandeja';
    return `No hay resguardos en ${etiqueta.toLowerCase()}`;
}
