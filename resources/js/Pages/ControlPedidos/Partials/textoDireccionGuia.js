/**
 * Textos de dirección para captura de guía (sin React).
 * Self-check: node resources/js/Pages/ControlPedidos/Partials/textoDireccionGuia.js
 */

/** Calle + número + colonia + ciudad + estado (sin CP). */
export function textoDireccionParaGuia(dir, domicilioLegacy = null) {
    if (dir) {
        const numero = [dir.numero_exterior, dir.numero_interior ? `Int. ${dir.numero_interior}` : null]
            .filter(Boolean)
            .join(' / ');
        const partes = [
            dir.calle,
            numero || null,
            dir.colonia,
            dir.ciudad || dir.municipio,
            dir.estado,
        ].filter(Boolean);
        if (partes.length) return partes.join(', ');
    }
    return domicilioLegacy || null;
}

export function textoDomicilioCompleto(dir, domicilioLegacy = null, codigoPostal = null) {
    if (dir) {
        const base = textoDireccionParaGuia(dir);
        const cp = dir.codigo_postal || codigoPostal;
        if (base && cp) return `${base}, ${cp}`;
        if (base) return base;
        if (cp) return String(cp);
    }
    return domicilioLegacy || null;
}

if (typeof process !== 'undefined' && process.argv?.[1]?.endsWith('textoDireccionGuia.js')) {
    const assert = (cond, msg) => {
        if (!cond) throw new Error(msg);
    };
    const dir = {
        calle: 'Av. Reforma',
        numero_exterior: '100',
        numero_interior: '2',
        colonia: 'Centro',
        municipio: 'Puebla',
        estado: 'Puebla',
        codigo_postal: '72000',
    };
    assert(
        textoDireccionParaGuia(dir) === 'Av. Reforma, 100 / Int. 2, Centro, Puebla, Puebla',
        'dirección estructurada'
    );
    assert(
        textoDomicilioCompleto(dir) === 'Av. Reforma, 100 / Int. 2, Centro, Puebla, Puebla, 72000',
        'domicilio con CP'
    );
    assert(textoDireccionParaGuia(null, 'Legacy #1') === 'Legacy #1', 'legacy');
    console.log('textoDireccionGuia ok');
}
