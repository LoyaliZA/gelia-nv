import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { decidirAvisoVersion } from '@/utils/avisoVersion';
import { DEPLOY_INTERVALO_MS, versionSuperficieCargada } from '@/utils/deployWatch';

function pantallaConVigilanciaPdv() {
    return typeof document !== 'undefined'
        && document.documentElement.hasAttribute('data-gelia-deploy-watch');
}

export default function useAvisoVersionNueva() {
    const { url } = usePage();
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        if (pantallaConVigilanciaPdv()) {
            setVisible(false);
            return undefined;
        }

        const cargada = versionSuperficieCargada('shell');
        if (!cargada) {
            setVisible(false);
            return undefined;
        }

        let cancelado = false;

        const revisar = async () => {
            if (pantallaConVigilanciaPdv()) {
                if (!cancelado) {
                    setVisible(false);
                }
                return;
            }

            try {
                const respuesta = await fetch('/api/deploy-version', {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                    credentials: 'same-origin',
                });
                if (!respuesta.ok || cancelado) {
                    return;
                }
                const data = await respuesta.json();
                if (cancelado) {
                    return;
                }
                setVisible(decidirAvisoVersion({
                    versionCargada: cargada,
                    versionRemota: data?.shell || '',
                }) === 'avisar');
            } catch {
                // La alerta no bloquea el trabajo si la consulta falla.
            }
        };

        revisar();
        const id = window.setInterval(revisar, DEPLOY_INTERVALO_MS);
        const alVolver = () => {
            if (document.visibilityState === 'visible') {
                revisar();
            }
        };
        window.addEventListener('focus', revisar);
        document.addEventListener('visibilitychange', alVolver);

        return () => {
            cancelado = true;
            window.clearInterval(id);
            window.removeEventListener('focus', revisar);
            document.removeEventListener('visibilitychange', alVolver);
        };
    }, [url]);

    return visible;
}
