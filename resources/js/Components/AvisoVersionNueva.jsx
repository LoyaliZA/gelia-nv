import { useEffect, useState } from 'react';
import { DownloadIcon } from '@animateicons/react/lucide/download-icon';
import useIconoEnControl from '@/hooks/useIconoEnControl';
import PantallaActualizacionGelia from '@/Components/PantallaActualizacionGelia';
import useAvisoVersionNueva from '@/hooks/useAvisoVersionNueva';
import {
    ESPERA_ACTUALIZACION_MS,
    ESPERA_AVISO_MS,
    ROTACION_ACTUALIZACION_MS,
    antesDeRecargar,
    fraseActualizacion,
} from '@/utils/avisoVersion';

function IconoDescarga({ descarga }) {
    return (
        <DownloadIcon
            ref={descarga.ref}
            size={20}
            duration={1}
            isAnimated={false}
            color="var(--color-primario)"
        />
    );
}

/** Aviso completo; se reutiliza en Pruebas de componentes. */
export function AvisoVersionTarjeta({ onActivar }) {
    const descarga = useIconoEnControl();

    return (
        <button
            type="button"
            className="gelia-aviso-version pointer-events-auto flex w-full min-h-11 items-start gap-4 rounded-2xl border theme-surface theme-no-blur p-4 text-left shadow-2xl focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primario)]"
            {...descarga.triggerProps}
            onClick={onActivar}
        >
            <span
                className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}
                aria-hidden="true"
            >
                <IconoDescarga descarga={descarga} />
            </span>
            <span className="min-w-0 flex-1">
                <span className="block text-sm font-semibold theme-text-primario">Nueva versión</span>
                <span className="mt-0.5 block text-xs font-bold leading-snug theme-text-main">
                    Hay una nueva actualización de <span translate="no">GELIA</span> lista para instalar.
                </span>
            </span>
        </button>
    );
}

/** Aviso plegado a un costado. */
export function AvisoVersionMini({ onActivar }) {
    const descarga = useIconoEnControl();

    return (
        <button
            type="button"
            className="gelia-aviso-version gelia-aviso-version--mini pointer-events-auto inline-flex h-11 w-11 items-center justify-center rounded-full border theme-surface theme-no-blur shadow-lg focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primario)]"
            aria-label="Nueva versión de GELIA lista. Actualizar ahora"
            title="Nueva versión lista. Actualizar"
            {...descarga.triggerProps}
            onClick={onActivar}
        >
            <IconoDescarga descarga={descarga} />
        </button>
    );
}

export default function AvisoVersionNueva() {
    const visible = useAvisoVersionNueva();
    const [actualizando, setActualizando] = useState(false);
    const [plegado, setPlegado] = useState(false);
    const [indice, setIndice] = useState(0);

    // El aviso completo se pliega solo; queda a un costado hasta actualizar.
    useEffect(() => {
        if (!visible) {
            setPlegado(false);
            return undefined;
        }
        const id = window.setTimeout(() => setPlegado(true), ESPERA_AVISO_MS);

        return () => window.clearTimeout(id);
    }, [visible]);

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
                <div role="status" aria-live="polite" className="flex justify-end">
                    {plegado
                        ? <AvisoVersionMini onActivar={() => setActualizando(true)} />
                        : <AvisoVersionTarjeta onActivar={() => setActualizando(true)} />}
                </div>
            ) : null}
            <PantallaActualizacionGelia
                isVisible={actualizando}
                nota={fraseActualizacion(indice)}
            />
        </>
    );
}
