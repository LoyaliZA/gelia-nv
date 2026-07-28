/** Régimen 605 (Sueldos y Salarios) exige uso S01 (Sin efectos fiscales). */
export const REGIMEN_SUELDOS_SALARIOS = '605';
export const USO_SIN_EFECTOS_FISCALES = 'S01';

export function esRegimenSueldosSalarios(codigo) {
    return String(codigo || '') === REGIMEN_SUELDOS_SALARIOS;
}

export function usoCfdiParaRegimen(regimenCodigo) {
    return esRegimenSueldosSalarios(regimenCodigo) ? USO_SIN_EFECTOS_FISCALES : null;
}

export function normalizarRfc(rfc) {
    return String(rfc || '')
        .toUpperCase()
        .replace(/[^A-ZÑ&0-9]/gi, '')
        .slice(0, 13);
}

/**
 * Persona moral: 12 chars. Persona física: 13.
 * Tipo por el 4.º carácter (letra → física, dígito → moral).
 * @returns {string|null} mensaje de error o null si válido/vacío
 */
export function errorRfc(rfc) {
    const limpio = normalizarRfc(rfc);
    if (!limpio) return null;

    const len = limpio.length;
    if (len < 12 || len > 13) {
        return 'El RFC debe tener 12 caracteres (persona moral) o 13 (persona fisica).';
    }

    const cuarto = limpio[3] || '';
    const esFisica = /^[A-ZÑ&]$/i.test(cuarto);

    if (esFisica) {
        if (len !== 13) {
            return 'Persona física: el RFC debe tener 13 caracteres.';
        }
        if (!/^[A-ZÑ&]{4}\d{6}[A-Z0-9]{3}$/i.test(limpio)) {
            return 'El RFC no tiene un formato válido para persona física.';
        }
        return null;
    }

    if (len !== 12) {
        return 'Empresa: el RFC debe tener 12 caracteres.';
    }
    if (!/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/i.test(limpio)) {
        return 'El RFC no tiene un formato válido para empresa.';
    }
    return null;
}

/**
 * Razón social factura-safe: trim, sin acentos (conserva Ñ), MAYÚSCULAS.
 */
export function normalizarRazonSocial(valor) {
    let s = String(valor || '').trim();
    if (!s) return '';

    s = s.replace(/[Ññ]/g, '\u0000');
    s = s.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    s = s.replace(/\u0000/g, 'Ñ');
    s = s.toUpperCase();
    s = s.replace(/[^A-Z0-9Ñ&.\-' ]+/g, '');
    s = s.replace(/\s+/g, ' ').trim();

    return s;
}
