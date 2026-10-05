import { useEffect } from 'react';

const INTERVALO_MS = 8 * 60 * 1000;

function versionInicial() {
    if (typeof document === 'undefined') return '';
    return document.querySelector('meta[name="gelia-build"]')?.getAttribute('content') || '';
}

export default function useGeliaDeployWatch({ habilitado = true } = {}) {
    useEffect(() => {
        if (!habilitado || typeof window === 'undefined') return undefined;

        const inicial = versionInicial();
        if (!inicial) return undefined;

        let cancelado = false;

        const revisar = async () => {
            try {
                const respuesta = await fetch('/api/deploy-version', {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                });
                if (!respuesta.ok || cancelado) return;
                const data = await respuesta.json();
                if (data?.version && data.version !== inicial) {
                    window.location.reload();
                }
            } catch {
                // ponytail: un fallo de red no recarga el kiosco
            }
        };

        const id = window.setInterval(revisar, INTERVALO_MS);
        return () => {
            cancelado = true;
            window.clearInterval(id);
        };
    }, [habilitado]);
}
