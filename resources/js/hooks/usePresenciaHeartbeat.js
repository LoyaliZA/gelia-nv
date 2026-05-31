import { useEffect } from 'react';
import PresenciaService from '@/Services/PresenciaService';

const INTERVALO_MS = 5 * 60 * 1000;

export default function usePresenciaHeartbeat(activo = true) {
    useEffect(() => {
        if (!activo || typeof window === 'undefined') return undefined;

        const tick = () => {
            if (document.visibilityState !== 'visible') return;
            PresenciaService.heartbeat()
                .then((presencia) => {
                    window.dispatchEvent(new CustomEvent('gelia-presencia-propia', { detail: presencia }));
                })
                .catch(() => {});
        };

        tick();
        const id = window.setInterval(tick, INTERVALO_MS);

        const onVisible = () => {
            if (document.visibilityState === 'visible') tick();
        };
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            window.clearInterval(id);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [activo]);
}
