export const PAGOS_PEDIDOS_REPORTE_STORAGE_KEY = 'gelia_pagos_pedidos_reporte_job_id';

export const PAGOS_PEDIDOS_REPORTE_STARTED_EVENT = 'pagos-pedidos-reporte-started';
export const PAGOS_PEDIDOS_REPORTE_DISMISSED_EVENT = 'pagos-pedidos-reporte-dismissed';
export const PAGOS_PEDIDOS_REPORTE_ACTUALIZAR_EVENT = 'pagos-pedidos-reporte-actualizar';

export function startPagosPedidosReporteTracking(jobId) {
    if (typeof window === 'undefined' || !jobId) return;
    localStorage.setItem(PAGOS_PEDIDOS_REPORTE_STORAGE_KEY, String(jobId));
    window.dispatchEvent(new CustomEvent(PAGOS_PEDIDOS_REPORTE_STARTED_EVENT, { detail: { jobId: String(jobId) } }));
    window.dispatchEvent(new CustomEvent(PAGOS_PEDIDOS_REPORTE_ACTUALIZAR_EVENT));
}

export function clearPagosPedidosReporteTracking() {
    if (typeof window === 'undefined') return;
    localStorage.removeItem(PAGOS_PEDIDOS_REPORTE_STORAGE_KEY);
    window.dispatchEvent(new CustomEvent(PAGOS_PEDIDOS_REPORTE_ACTUALIZAR_EVENT));
}

export function dismissPagosPedidosReporteTracking() {
    clearPagosPedidosReporteTracking();
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent(PAGOS_PEDIDOS_REPORTE_DISMISSED_EVENT));
    }
}

export function getStoredPagosPedidosReporteJobId() {
    if (typeof window === 'undefined') return null;
    return localStorage.getItem(PAGOS_PEDIDOS_REPORTE_STORAGE_KEY);
}

export async function fetchEstadoPagosPedidosPdf(jobId) {
    const res = await fetch(route('reportes.pagos_pedidos.pdf.estado', { exportacion: jobId }), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });
    if (!res.ok) throw new Error('No se pudo consultar el estado del reporte');
    return res.json();
}

export async function cancelarPagosPedidosPdf(jobId) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const res = await fetch(route('reportes.pagos_pedidos.pdf.cancelar', { exportacion: jobId }), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.message || 'No se pudo cancelar la generación');
    return data;
}

export async function solicitarPagosPedidosExportacion(payload) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const res = await fetch(route('reportes.pagos_pedidos.exportar.solicitar'), {
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
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'No se pudo solicitar el reporte');
    return data;
}

/** @deprecated use solicitarPagosPedidosExportacion */
export async function solicitarPagosPedidosPdf(payload) {
    return solicitarPagosPedidosExportacion(payload);
}

export async function reintentarPagosPedidosExportacion(exportacionId) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const res = await fetch(route('reportes.pagos_pedidos.exportaciones.reintentar', { exportacion: exportacionId }), {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': token,
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message || 'No se pudo reintentar');
    startPagosPedidosReporteTracking(data.job_id);
    return data;
}

export function formatearTiempoTranscurrido(startedAt) {
    if (!startedAt) return '0:00';
    const inicio = new Date(startedAt).getTime();
    if (Number.isNaN(inicio)) return '0:00';
    const seg = Math.max(0, Math.floor((Date.now() - inicio) / 1000));
    const m = Math.floor(seg / 60);
    const s = seg % 60;
    return `${m}:${String(s).padStart(2, '0')}`;
}

export function etiquetaRegistros(progreso) {
    if (!progreso) return '—';
    const done = progreso.registros_procesados ?? 0;
    const total = progreso.registros_total ?? 0;
    const etapa = progreso.etapa || '';
    if (etapa === 'procesando_vouchers') {
        return `${done.toLocaleString('es-MX')} / ${total.toLocaleString('es-MX')} vouchers`;
    }
    if (etapa === 'calculando_totales' || etapa === 'preparando_datos') {
        return `${done.toLocaleString('es-MX')} / ${total.toLocaleString('es-MX')} pedidos`;
    }
    if (total > 0) {
        return `${done.toLocaleString('es-MX')} / ${total.toLocaleString('es-MX')} registros`;
    }
    return `${done.toLocaleString('es-MX')} registros procesados`;
}

export function urlDescargaPagosPedidos(jobId) {
    return route('reportes.pagos_pedidos.exportar.descargar', { exportacion: jobId });
}
