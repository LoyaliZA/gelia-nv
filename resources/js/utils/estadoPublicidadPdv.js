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

export function formatearTamanoBytes(bytes) {
    const n = Number(bytes);
    if (!n || n < 0 || Number.isNaN(n)) return null;
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
    if (n < 1024 * 1024 * 1024) return `${(n / (1024 * 1024)).toFixed(1)} MB`;
    return `${(n / (1024 * 1024 * 1024)).toFixed(1)} GB`;
}
