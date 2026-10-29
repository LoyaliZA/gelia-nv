import { useEffect, useState } from 'react';
import GeliaLoader from '@/Components/GeliaLoader';
import useAvisoVersionNueva from '@/hooks/useAvisoVersionNueva';
import {
    ESPERA_ACTUALIZACION_MS,
    ROTACION_ACTUALIZACION_MS,
    antesDeRecargar,
    fraseActualizacion,
} from '@/utils/avisoVersion';

export default function AvisoVersionNueva() {
    const visible = useAvisoVersionNueva();
    const [actualizando, setActualizando] = useState(false);
    const [indice, setIndice] = useState(0);

    useEffect(() => {
        if (!actualizando || typeof window === 'undefined') {
            return undefined;
        }

        const reducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const rotacion = reducido
            ? null
            : window.setInterval(() => setIndice((actual) => actual + 1), ROTACION_ACTUALIZACION_MS);
        const espera = window.setTimeout(() => {
            antesDeRecargar();
            window.location.reload();
        }, ESPERA_ACTUALIZACION_MS);

        return () => {
            if (rotacion) {
                window.clearInterval(rotacion);
            }
            window.clearTimeout(espera);
        };
    }, [actualizando]);

    if (!visible && !actualizando) {
        return null;
    }

    return (
        <>
            {visible && !actualizando ? (
                <div className="pointer-events-none fixed inset-x-0 z-[10000] flex justify-center px-4 top-[calc(var(--gelia-mobile-topbar-height)+0.75rem)] md:top-3">
                    <button
                        type="button"
                        className="pointer-events-auto min-h-11 max-w-full rounded-full border theme-border theme-surface theme-text-main px-4 py-2 text-sm font-semibold shadow-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primario)]"
                        onClick={() => setActualizando(true)}
                    >
                        Hay una nueva actualización de GELIA
                    </button>
                </div>
            ) : null}
            <GeliaLoader
                isVisible={actualizando}
                message="Actualizando tu GELIA"
                nota={fraseActualizacion(indice)}
            />
        </>
    );
}
