import { useEffect, useState } from 'react';
import { DownloadIcon } from '@animateicons/react/lucide/download-icon';
import useIconoEnControl from '@/hooks/useIconoEnControl';
import PantallaActualizacionGelia from '@/Components/PantallaActualizacionGelia';
import useAvisoVersionNueva from '@/hooks/useAvisoVersionNueva';
import {
    ESPERA_ACTUALIZACION_MS,
    ROTACION_ACTUALIZACION_MS,
    antesDeRecargar,
    fraseActualizacion,
} from '@/utils/avisoVersion';

export default function AvisoVersionNueva() {
    const visible = useAvisoVersionNueva();
    const descarga = useIconoEnControl();
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
        const shell = document.querySelector('.gelia-app-shell');
        shell?.setAttribute('inert', '');

        return () => {
            shell?.removeAttribute('inert');
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
                <div role="status" aria-live="polite">
                    <button
                        type="button"
                        className="gelia-aviso-version pointer-events-auto flex w-full min-h-11 items-start gap-4 rounded-2xl border theme-surface theme-no-blur p-4 text-left shadow-2xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primario)]"
                        {...descarga.triggerProps}
                        onClick={() => setActualizando(true)}
                    >
                        <span
                            className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                            style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}
                            aria-hidden="true"
                        >
                            <DownloadIcon
                                ref={descarga.ref}
                                size={20}
                                duration={1}
                                isAnimated={false}
                                color="var(--color-primario)"
                            />
                        </span>
                        <span className="min-w-0 flex-1">
                            <span className="block text-sm font-semibold theme-text-primario">Nueva versión</span>
                            <span className="mt-0.5 block text-xs font-bold leading-snug theme-text-main">
                                Hay una nueva actualización de <span translate="no">GELIA</span> lista para instalar.
                            </span>
                        </span>
                    </button>
                </div>
            ) : null}
            <PantallaActualizacionGelia
                isVisible={actualizando}
                nota={fraseActualizacion(indice)}
            />
        </>
    );
}
