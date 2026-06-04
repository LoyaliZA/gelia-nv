import { router } from '@inertiajs/react';

/** Recarga solo las props indicadas sin refrescar toda la página. */
export function recargarModuloInertia(props, options = {}) {
    router.reload({
        only: props,
        preserveScroll: true,
        preserveState: true,
        showProgress: false,
        ...options,
    });
}
