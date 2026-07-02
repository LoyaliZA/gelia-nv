import { useEffect } from 'react';

/**
 * Escucha el canal compartido de Ejecución Operativa y notifica cambios entre sesiones.
 */
export default function useCobranzaRealtime(onEvent) {
    useEffect(() => {
        if (typeof window === 'undefined' || !window.Echo || !onEvent) return;

        const channel = window.Echo.private('cobranza.ejecucion');

        channel.listen('.cobranza-ejecucion.actualizada', (payload) => {
            onEvent(payload);
        });

        return () => {
            window.Echo.leave('cobranza.ejecucion');
        };
    }, [onEvent]);
}
