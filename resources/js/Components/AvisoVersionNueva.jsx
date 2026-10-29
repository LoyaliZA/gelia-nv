import { useEffect, useState } from 'react';
import { Download } from 'lucide-react';
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
                        onClick={() => setActualizando(true)}
                    >
                        <span
                            className="shrink-0 rounded-xl p-2"
                            style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}
                            aria-hidden="true"
                        >
                            <Download className="size-5" style={{ color: 'var(--color-primario)' }} />
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
