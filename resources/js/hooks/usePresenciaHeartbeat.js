import { useEffect } from 'react';
import PresenciaService from '@/Services/PresenciaService';

const INTERVALO_MS = 45 * 1000;
const CLAVE_TAB = 'gelia-presencia-tab';

function idPestana() {
    try {
        let id = sessionStorage.getItem(CLAVE_TAB);
        if (!id) {
            id = (typeof crypto !== 'undefined' && crypto.randomUUID)
                ? crypto.randomUUID()
                : `${Date.now()}-${Math.random().toString(16).slice(2)}`;
            sessionStorage.setItem(CLAVE_TAB, id);
        }
        return id;
    } catch {
        return 'pestana';
    }
}

export default function usePresenciaHeartbeat(activo = true) {
    useEffect(() => {
        if (!activo || typeof window === 'undefined') return undefined;

        const tabId = idPestana();
        let ocultoEnviado = false;

        const enviar = (visible) => {
            PresenciaService.heartbeat({ tab_id: tabId, visible })
                .then((presencia) => {
                    if (visible && presencia) {
                        window.dispatchEvent(new CustomEvent('gelia-presencia-propia', { detail: presencia }));
                    }
                })
                .catch(() => {});
        };

        const tick = () => {
            if (document.visibilityState !== 'visible') return;
            ocultoEnviado = false;
            enviar(true);
        };

        const onVisible = () => {
            if (document.visibilityState === 'visible') {
                tick();
                return;
            }
            if (!ocultoEnviado) {
                ocultoEnviado = true;
                enviar(false);
            }
        };

        const onPageHide = () => {
            PresenciaService.senalAlCerrar(tabId);
        };

        tick();
        const id = window.setInterval(tick, INTERVALO_MS);
        document.addEventListener('visibilitychange', onVisible);
        window.addEventListener('pagehide', onPageHide);

        return () => {
            window.clearInterval(id);
            document.removeEventListener('visibilitychange', onVisible);
            window.removeEventListener('pagehide', onPageHide);
        };
    }, [activo]);
}
