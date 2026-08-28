/** @param {string} clave */
export function wrapCampoError(clave, camposIncorrectos = []) {
    if (!Array.isArray(camposIncorrectos) || !camposIncorrectos.includes(clave)) return '';
    return 'rounded-xl ring-2 ring-[color-mix(in_srgb,var(--color-peligro)_70%,transparent)] bg-[color-mix(in_srgb,var(--color-peligro)_10%,transparent)] p-2';
}

export function esCampoPendienteEnlace(clave, factura) {
    const pendiente = Boolean(factura?.formulario_enviado_at && !factura?.formulario_respondido_at);
    const solicitados = factura?.campos_fiscales_solicitados || [];
    return pendiente && solicitados.includes(clave);
}

export function esCampoCorregidoEnlace(clave, camposIncorrectos, factura) {
    if (!factura?.formulario_respondido_at) return false;
    if (!Array.isArray(camposIncorrectos) || !camposIncorrectos.includes(clave)) return false;
    const solicitados = factura?.campos_fiscales_solicitados || [];
    return solicitados.length === 0 || solicitados.includes(clave);
}

/**
 * @param {string} clave fiscal field key
 * @param {{ formulario_enviado_at?: string|null, formulario_respondido_at?: string|null, campos_fiscales_solicitados?: string[] }} factura
 */
export function wrapCampoPendienteLink(clave, factura) {
    if (!esCampoPendienteEnlace(clave, factura)) return '';
    return 'rounded-xl ring-2 ring-[color-mix(in_srgb,var(--color-amber-500,#f59e0b)_70%,transparent)] bg-[color-mix(in_srgb,var(--color-amber-500,#f59e0b)_10%,transparent)] p-2';
}

/** Cliente respondió el enlace: campo marcado como corregido vía formulario. */
export function wrapCampoCorregidoEnlace(clave, camposIncorrectos, factura) {
    if (!esCampoCorregidoEnlace(clave, camposIncorrectos, factura)) return '';
    return 'rounded-xl ring-2 ring-[color-mix(in_srgb,var(--color-exito)_70%,transparent)] bg-[color-mix(in_srgb,var(--color-exito)_10%,transparent)] p-2';
}

/** @returns {'pendiente'|'corregido'|'error'|null} */
export function estadoWrapCampoFactura(clave, camposIncorrectos, factura, claveLink = clave) {
    if (esCampoPendienteEnlace(claveLink, factura)) return 'pendiente';
    if (esCampoCorregidoEnlace(clave, camposIncorrectos, factura)) return 'corregido';
    if (Array.isArray(camposIncorrectos) && camposIncorrectos.includes(clave)) return 'error';
    return null;
}

/** @param {'pendiente'|'corregido'|'error'|null} estado */
export function estiloWrapCampoFactura(estado) {
    const base = { borderRadius: '0.75rem', padding: '0.5rem' };
    switch (estado) {
        case 'corregido':
            return {
                ...base,
                border: '2px solid #4ade80',
                background: 'color-mix(in srgb, #22c55e 30%, transparent)',
                boxShadow: '0 0 18px color-mix(in srgb, #4ade80 60%, transparent)',
            };
        case 'pendiente':
            return {
                ...base,
                border: '2px solid color-mix(in srgb, var(--color-amber-500, #f59e0b) 70%, transparent)',
                background: 'color-mix(in srgb, var(--color-amber-500, #f59e0b) 12%, transparent)',
            };
        case 'error':
            return {
                ...base,
                border: '2px solid color-mix(in srgb, var(--color-peligro) 70%, transparent)',
                background: 'color-mix(in srgb, var(--color-peligro) 12%, transparent)',
            };
        default:
            return undefined;
    }
}

export function wrapCampoFactura(clave, camposIncorrectos, factura, claveLink = clave) {
    const pendiente = wrapCampoPendienteLink(claveLink, factura);
    if (pendiente) return pendiente;
    const corregido = wrapCampoCorregidoEnlace(clave, camposIncorrectos, factura);
    if (corregido) return corregido;
    return wrapCampoError(clave, camposIncorrectos);
}
