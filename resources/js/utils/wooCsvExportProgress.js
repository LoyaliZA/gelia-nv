const FASES_ANALISIS = ['iniciando', 'leyendo_excel', 'analisis'];

export function calcularPorcentajeExportCsv(log) {
    if (!log) return 0;
    if (log.estado === 'completado') return 100;

    const fase = log.payload?.fase;
    const total = Math.max(log.total_productos || 1, 1);
    const proc = log.procesados || 0;

    if (FASES_ANALISIS.includes(fase)) {
        return Math.min(40, Math.round((proc / total) * 40));
    }

    if (fase === 'aplicando' || fase === 'generando_csv') {
        return 40 + Math.round((proc / total) * 60);
    }

    return 5;
}

export function etiquetaFaseExportCsv(log) {
    if (!log) return 'Preparando…';
    if (log.estado === 'completado') return 'Exportación completada';

    const fase = log.payload?.fase;
    const cambios = log.payload?.cambios_detectados;

    switch (fase) {
        case 'iniciando':
            return 'Iniciando generación del CSV…';
        case 'leyendo_excel':
            return 'Leyendo lista de precios…';
        case 'analisis':
            return 'Detectando productos con cambio de precio…';
        case 'aplicando':
            return cambios
                ? `Actualizando ${cambios} producto(s) en GELIANV…`
                : 'Actualizando precios en GELIANV…';
        case 'generando_csv':
            return 'Generando archivo CSV…';
        default:
            return 'Procesando precios…';
    }
}

export function contadorExportCsv(log) {
    if (!log) return null;
    const fase = log.payload?.fase;

    if (FASES_ANALISIS.includes(fase)) {
        return {
            etiqueta: 'Catálogo revisado',
            actual: log.procesados ?? 0,
            total: log.total_productos ?? 0,
        };
    }

    if (fase === 'aplicando' || fase === 'generando_csv' || log.estado === 'completado') {
        const total = log.payload?.cambios_detectados ?? log.total_productos ?? 0;
        return {
            etiqueta: 'Productos con cambio',
            actual: log.procesados ?? 0,
            total,
        };
    }

    return null;
}
