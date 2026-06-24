let chartPromise = null;

/**
 * Carga Chart.js desde /public/vendor (sin import npm) para evitar fallos de resolución en Vite.
 */
export function cargarChartJs() {
    if (typeof window !== 'undefined' && window.Chart) {
        return Promise.resolve(window.Chart);
    }

    if (!chartPromise) {
        chartPromise = new Promise((resolve, reject) => {
            const existente = document.querySelector('script[data-chartjs-vendor="1"]');
            if (existente) {
                existente.addEventListener('load', () => resolve(window.Chart));
                existente.addEventListener('error', reject);
                return;
            }

            const script = document.createElement('script');
            script.src = '/vendor/chart.js/chart.umd.min.js';
            script.async = true;
            script.dataset.chartjsVendor = '1';
            script.onload = () => {
                if (window.Chart) {
                    resolve(window.Chart);
                } else {
                    reject(new Error('Chart.js no expuso window.Chart'));
                }
            };
            script.onerror = () => reject(new Error('No se pudo cargar Chart.js'));
            document.head.appendChild(script);
        });
    }

    return chartPromise;
}
