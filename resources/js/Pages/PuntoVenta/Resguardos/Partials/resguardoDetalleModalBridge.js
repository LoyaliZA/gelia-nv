import { useEffect } from 'react';

const EVENT_NAME = 'gelia:pdv-resguardo-detalle';

/**
 * Abre el modal de detalle en la bandeja de resguardos (Index escucha este evento).
 */
export function abrirDetalleResguardoModal(resguardoId, resumen = null) {
    const id = Number.parseInt(String(resguardoId ?? ''), 10);
    if (!Number.isFinite(id) || id < 1) {
        return;
    }

    window.dispatchEvent(new CustomEvent(EVENT_NAME, {
        detail: { resguardoId: id, resumen },
    }));
}

export function useDetalleResguardoModalBridge(onAbrir) {
    useEffect(() => {
        const handler = (event) => {
            const { resguardoId, resumen } = event.detail || {};
            onAbrir?.(resguardoId, resumen ?? null);
        };

        window.addEventListener(EVENT_NAME, handler);

        return () => window.removeEventListener(EVENT_NAME, handler);
    }, [onAbrir]);
}
