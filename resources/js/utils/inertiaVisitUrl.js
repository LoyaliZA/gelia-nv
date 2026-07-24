/**
 * Convierte URLs absolutas del paginador (http/https/host) a path relativo
 * para visitas Inertia. Evita HttpNetworkError por mixed content cuando
 * Laravel emite http:// detrás de proxy/CDN mientras el browser está en https.
 */
export function inertiaVisitUrl(url) {
    if (!url) return null;
    try {
        const u = new URL(url, typeof window !== 'undefined' ? window.location.origin : 'http://localhost');
        return `${u.pathname}${u.search}${u.hash}`;
    } catch {
        return url;
    }
}
