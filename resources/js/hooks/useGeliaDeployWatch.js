import { useEffect } from 'react';
import { mutacionesRecargaEnCurso } from '@/utils/bloqueoRecargaDeploy';
import {
    DEPLOY_INTERVALO_MS,
    decidirRecargaDeploy,
    notificarConsultaDeploy,
    versionSuperficieCargada,
    vistaEnReposo,
} from '@/utils/deployWatch';

function claveIntento(superficie) {
    return `gelia-deploy-intento:${superficie}`;
}

function claveEspera(superficie) {
    return `gelia-deploy-espera:${superficie}`;
}

async function informarEvento(payload) {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    await fetch('/api/deploy-eventos', {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
        },
        body: JSON.stringify(payload),
        credentials: 'same-origin',
    });
}

export default function useGeliaDeployWatch({
    superficie,
    politica = 'en_reposo',
    habilitado = true,
} = {}) {
    useEffect(() => {
        if (!habilitado || !superficie || typeof window === 'undefined') return undefined;

        const cargada = versionSuperficieCargada(superficie);
        if (!cargada) return undefined;

        let cancelado = false;

        const revisar = async () => {
            try {
                const respuesta = await fetch('/api/deploy-version', {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                    credentials: 'same-origin',
                });
                if (!respuesta.ok || cancelado) return;
                const data = await respuesta.json();
                const remota = data?.surfaces?.[superficie];
                if (!remota || cancelado) return;

                notificarConsultaDeploy();

                const enReposo = politica === 'inmediata'
                    || vistaEnReposo(mutacionesRecargaEnCurso());
                const intentoPrevio = window.sessionStorage.getItem(claveIntento(superficie)) || '';
                const decision = decidirRecargaDeploy({
                    versionCargada: cargada,
                    versionRemota: remota,
                    intentoPrevio,
                    enReposo,
                });

                if (decision === 'esperar') {
                    const espera = window.sessionStorage.getItem(claveEspera(superficie));
                    if (espera !== remota) {
                        window.sessionStorage.setItem(claveEspera(superficie), remota);
                        informarEvento({
                            accion: 'recarga_en_espera',
                            superficie,
                            version: remota,
                            detalle: 'Hay un modal o un envío en curso.',
                        }).catch(() => {});
                    }
                    return;
                }

                if (decision !== 'recargar') return;

                window.sessionStorage.setItem(claveIntento(superficie), remota);
                informarEvento({
                    accion: 'recarga_iniciada',
                    superficie,
                    version: remota,
                }).catch(() => {});
                window.location.reload();
            } catch {
                informarEvento({
                    accion: 'consulta_fallida',
                    superficie,
                    detalle: 'La pantalla no pudo consultar la versión.',
                }).catch(() => {});
            }
        };

        const id = window.setInterval(revisar, DEPLOY_INTERVALO_MS);
        return () => {
            cancelado = true;
            window.clearInterval(id);
        };
    }, [habilitado, politica, superficie]);
}
