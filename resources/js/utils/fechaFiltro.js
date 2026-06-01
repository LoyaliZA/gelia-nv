/** Fecha ISO (YYYY-MM-DD) válida para filtros. */
export function esFechaIsoValida(valor) {
    if (!valor || typeof valor !== 'string') return false;
    if (!/^\d{4}-\d{2}-\d{2}$/.test(valor)) return false;
    const [y, m, d] = valor.split('-').map(Number);
    const fecha = new Date(y, m - 1, d);
    return fecha.getFullYear() === y && fecha.getMonth() === m - 1 && fecha.getDate() === d;
}

/**
 * Al salir del input o confirmar con Enter: acepta vacío o ISO válido; rechaza incompletos.
 * @returns {{ ok: boolean, valor: string }}
 */
export function normalizarFechaAlConfirmar(valor) {
    const texto = (valor ?? '').trim();
    if (!texto) {
        return { ok: true, valor: '' };
    }
    if (esFechaIsoValida(texto)) {
        return { ok: true, valor: texto };
    }
    return { ok: false, valor: texto };
}
