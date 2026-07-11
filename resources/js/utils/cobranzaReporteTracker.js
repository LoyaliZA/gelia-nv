export const COBRANZA_REPORTE_STORAGE_KEY = 'gelia_cobranza_reporte_job_id';

export const COBRANZA_REPORTE_STARTED_EVENT = 'cobranza-reporte-started';
export const COBRANZA_REPORTE_DISMISSED_EVENT = 'cobranza-reporte-dismissed';

export function startCobranzaReporteTracking(jobId) {
    if (typeof window === 'undefined' || !jobId) return;
    localStorage.setItem(COBRANZA_REPORTE_STORAGE_KEY, String(jobId));
    window.dispatchEvent(new CustomEvent(COBRANZA_REPORTE_STARTED_EVENT, { detail: { jobId: String(jobId) } }));
}

export function clearCobranzaReporteTracking() {
    if (typeof window === 'undefined') return;
    localStorage.removeItem(COBRANZA_REPORTE_STORAGE_KEY);
}

export function dismissCobranzaReporteTracking() {
    clearCobranzaReporteTracking();
    if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent(COBRANZA_REPORTE_DISMISSED_EVENT));
    }
}

export function getStoredCobranzaReporteJobId() {
    if (typeof window === 'undefined') return null;
    return localStorage.getItem(COBRANZA_REPORTE_STORAGE_KEY);
}
