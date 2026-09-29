export const ESTADOS_PUBLICIDAD = {
    programada: 'Programada',
    activa: 'Activa',
    expirada: 'Expirada',
    deshabilitada: 'Deshabilitada',
};

export function estadoPublicidad({ activa = true, vigente_desde: desde = null, vigente_hasta: hasta = null }, ahora = new Date()) {
    if (!activa) return 'deshabilitada';
    const t = ahora instanceof Date ? ahora.getTime() : new Date(ahora).getTime();
    if (desde) {
        const ini = new Date(desde).getTime();
        if (!Number.isNaN(ini) && t < ini) return 'programada';
    }
    if (hasta) {
        const fin = new Date(hasta).getTime();
        if (!Number.isNaN(fin) && t > fin) return 'expirada';
    }
    return 'activa';
}

export function etiquetaEstadoPublicidad(estado) {
    return ESTADOS_PUBLICIDAD[estado] || estado;
}

export function formatearDuracionSeg(segundos) {
    const n = Number(segundos);
    if (!n || n < 0 || Number.isNaN(n)) return null;
    const m = Math.floor(n / 60);
    const s = Math.floor(n % 60);
    if (m <= 0) return `${s}s`;
    return `${m}:${String(s).padStart(2, '0')}`;
}

export function formatearFechaPublicidad(valor) {
    if (!valor) return null;
    const fecha = new Date(valor);
    if (Number.isNaN(fecha.getTime())) return String(valor);
    return fecha.toLocaleString('es-MX', { dateStyle: 'medium', timeStyle: 'short' });
}

export function textoProgramacion(item, ahora = new Date()) {
    const estado = item.estado || estadoPublicidad(item, ahora);
    if (estado === 'programada' && item.vigente_desde) {
        return `Comienza el ${formatearFechaPublicidad(item.vigente_desde)}`;
    }
    if (estado === 'expirada' && item.vigente_hasta) {
        return `Finalizó el ${formatearFechaPublicidad(item.vigente_hasta)}`;
    }
    const inicio = item.vigente_desde ? formatearFechaPublicidad(item.vigente_desde) : 'Desde ahora';
    const fin = item.vigente_hasta ? formatearFechaPublicidad(item.vigente_hasta) : 'Sin fecha de fin';
    return `${inicio} · ${fin}`;
}

export function textoEliminacion(item) {
    if (!item.eliminar_automaticamente || !item.eliminar_programado_at) return null;
    return `Se eliminará el ${formatearFechaPublicidad(item.eliminar_programado_at)}`;
}

export function duracionVueltaSegundos(items, ahora = new Date()) {
    return (items || []).reduce((total, item) => {
        const estado = item.estado || estadoPublicidad(item, ahora);
        if (estado !== 'activa') return total;
        const segundos = Number(item.duracion_seg);
        return total + (Number.isFinite(segundos) && segundos > 0 ? segundos : 0);
    }, 0);
}

export function contarEstados(items) {
    const cuentas = { todas: (items || []).length, activa: 0, programada: 0, deshabilitada: 0, expirada: 0 };
    (items || []).forEach((item) => {
        const estado = item.estado || estadoPublicidad(item);
        if (cuentas[estado] !== undefined) cuentas[estado] += 1;
    });
    return cuentas;
}

export function formatearTamanoBytes(bytes) {
    const n = Number(bytes);
    if (!n || n < 0 || Number.isNaN(n)) return null;
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
    if (n < 1024 * 1024 * 1024) return `${(n / (1024 * 1024)).toFixed(1)} MB`;
    return `${(n / (1024 * 1024 * 1024)).toFixed(1)} GB`;
}
