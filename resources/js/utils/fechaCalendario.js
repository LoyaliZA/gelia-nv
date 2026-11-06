export const ZONA_FECHA_NEGOCIO = 'America/Mexico_City';

/** Fechas civiles ISO; nunca convertir un YYYY-MM-DD a medianoche UTC. */
export function fechaCalendario(valor, zonaHoraria = ZONA_FECHA_NEGOCIO) {
    if (!valor) return '';
    if (typeof valor === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(valor)) return valor;
    const fecha = new Date(valor);
    if (Number.isNaN(fecha.getTime())) return '';
    const partes = Object.fromEntries(new Intl.DateTimeFormat('en-US', {
        timeZone: zonaHoraria, year: 'numeric', month: '2-digit', day: '2-digit',
    }).formatToParts(fecha).map(({ type, value }) => [type, value]));
    return `${partes.year}-${partes.month}-${partes.day}`;
}

export function fechaLocal(valor) {
    const [anio, mes, dia] = valor.split('-').map(Number);
    return new Date(anio, mes - 1, dia, 12);
}

export function fechaIsoLocal(fecha) {
    return `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, '0')}-${String(fecha.getDate()).padStart(2, '0')}`;
}
