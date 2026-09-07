export const PDV_REPORTE_STORAGE_KEY = 'gelia_pdv_reporte_job_id';

export const PDV_REPORTE_STARTED_EVENT = 'pdv-reporte-started';
export const PDV_REPORTE_DISMISSED_EVENT = 'pdv-reporte-dismissed';

export function startPdvReporteTracking(jobId) {
    if (typeof window === 'undefined' || !jobId) return;
    localStorage.setItem(PDV_REPORTE_STORAGE_KEY, String(jobId));
    window.dispatchEvent(new CustomEvent(PDV_REPORTE_STARTED_EVENT, { detail: { jobId: String(jobId) } }));
}

export function clearPdvReporteTracking() {
    if (typeof window === 'undefined') return;
    localStorage.removeItem(PDV_REPORTE_STORAGE_KEY);
}

export function dismissPdvReporteTracking() {
    clearPdvReporteTracking();
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent(PDV_REPORTE_DISMISSED_EVENT));
    }
}

export function getStoredPdvReporteJobId() {
    if (typeof window === 'undefined') return null;
    return localStorage.getItem(PDV_REPORTE_STORAGE_KEY);
}

export async function fetchEstadoPdvReporte(jobId) {
    const res = await fetch(route('punto_venta.reportes.exportaciones.show', { exportacion: jobId }), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });
    if (!res.ok) throw new Error('No se pudo consultar el estado de la exportación');
    const data = await res.json();
    return {
        progress: data.estado === 'completed' ? 100 : data.estado === 'processing' ? 50 : 10,
        status: data.estado === 'completed' ? 'completed' : data.estado === 'failed' ? 'failed' : 'processing',
        etapa_label: data.estado_label,
        exportacion: data,
    };
}

export async function solicitarPdvReporteExportacion(payload) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const res = await fetch(route('punto_venta.reportes.exportaciones.store'), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(payload),
        credentials: 'same-origin',
    });

    if (res.status === 200 && res.headers.get('content-disposition')) {
        const blob = await res.blob();
        const url = URL.createObjectURL(blob);
        const anchor = document.createElement('a');
        anchor.href = url;
        anchor.download = res.headers.get('content-disposition')?.split('filename=')[1]?.replace(/"/g, '') || 'reporte_pdv.csv';
        anchor.click();
        URL.revokeObjectURL(url);
        return { modo: 'sincrono' };
    }

    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || 'No se pudo solicitar la exportación');
    return data;
}

export async function reintentarPdvReporteExportacion(exportacionId) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const res = await fetch(route('punto_venta.reportes.exportaciones.reintentar', { exportacion: exportacionId }), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || 'No se pudo reintentar la exportación');
    return data;
}

export function urlDescargaPdvReporte(exportacionId) {
    return route('punto_venta.reportes.exportaciones.descargar', { exportacion: exportacionId });
}
