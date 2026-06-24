/**
 * Formatea fechas ISO / Laravel (YYYY-MM-DD o con hora) a dd/mm/yyyy sin desfase de zona horaria.
 */
export function formatoFechaCorta(valor) {
    if (!valor) return '—';

    const iso = String(valor).split('T')[0];
    const partes = iso.split('-');

    if (partes.length !== 3) return '—';

    const [anio, mes, dia] = partes;
    if (!anio || !mes || !dia) return '—';

    return `${dia.padStart(2, '0')}/${mes.padStart(2, '0')}/${anio}`;
}
