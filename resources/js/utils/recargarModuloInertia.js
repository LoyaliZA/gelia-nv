import { router } from '@inertiajs/react';

/** Indica si la URL actual es un endpoint de acción (POST/PUT) sin handler GET. */
function esSubrutaAccion(pathname) {
    return /\/\d+\/[^/]+/.test(pathname);
}

/** Recarga solo las props indicadas sin refrescar toda la página. */
export function recargarModuloInertia(props, options = {}) {
    const { url, ...reloadOptions } = options;
    const pathname = typeof window !== 'undefined' ? window.location.pathname : '';
    const enSubruta = esSubrutaAccion(pathname);
    const target = url ?? (enSubruta ? pathname.replace(/\/\d+\/.*$/, '') || '/' : null);

    if (target) {
        router.visit(target, {
            only: props,
            preserveScroll: true,
            preserveState: true,
            showProgress: false,
            ...reloadOptions,
        });
        return;
    }

    router.reload({
        only: props,
        preserveScroll: true,
        preserveState: true,
        showProgress: false,
        ...reloadOptions,
    });
}
