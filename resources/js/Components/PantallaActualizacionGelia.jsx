import { useEffect, useRef } from 'react';
import { createPortal } from 'react-dom';
import { PIEZAS_LOGO } from '@/utils/piezasLogoGelia';
import ProgresoActualizacion from '@/Components/ProgresoActualizacion';
import CampoParticulasActualizacion from '@/Components/CampoParticulasActualizacion';
import { DESTELLO_ACTUALIZACION_MS, ESPERA_ACTUALIZACION_MS } from '@/utils/avisoVersion';

/**
 * Capa de actualización. En `vistaPrevia` (Pruebas de componentes) no recarga:
 * se cierra sola al terminar el destello o con Escape / el botón de salida.
 */
export default function PantallaActualizacionGelia({ isVisible, nota, vistaPrevia = false, alCerrar }) {
    const raiz = useRef(null);
    const reducido = typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    useEffect(() => {
        if (!isVisible || !vistaPrevia) {
            return undefined;
        }
        const fin = window.setTimeout(() => alCerrar?.(), ESPERA_ACTUALIZACION_MS + 150);
        const tecla = (evento) => {
            if (evento.key === 'Escape') {
                alCerrar?.();
            }
        };
        window.addEventListener('keydown', tecla);

        return () => {
            window.clearTimeout(fin);
            window.removeEventListener('keydown', tecla);
        };
    }, [isVisible, vistaPrevia, alCerrar]);

    useEffect(() => {
        if (isVisible) {
            raiz.current?.focus();
        }
    }, [isVisible]);

    if (!isVisible || typeof document === 'undefined') {
        return null;
    }

    const estilo = {
        background: 'var(--bg-app)',
        '--gelia-act-total': `${ESPERA_ACTUALIZACION_MS}ms`,
        '--gelia-act-destello': `${DESTELLO_ACTUALIZACION_MS}ms`,
    };

    return createPortal(
        <div
            ref={raiz}
            tabIndex={-1}
            className="gelia-actualizacion fixed inset-0 z-[100000] flex flex-col items-center justify-center gap-10 overflow-hidden overscroll-contain px-6"
            style={estilo}
            role="status"
            aria-live="polite"
            aria-busy="true"
        >
            <div className="gelia-actualizacion-halo" aria-hidden="true" />
            {reducido ? null : <CampoParticulasActualizacion duracionMs={ESPERA_ACTUALIZACION_MS} />}

            <div className="gelia-actualizacion-orbita" aria-hidden="true">
                <span className="gelia-actualizacion-anillo gelia-actualizacion-anillo--1">
                    <i className="gelia-actualizacion-punto" />
                </span>
                <span className="gelia-actualizacion-anillo gelia-actualizacion-anillo--2">
                    <i className="gelia-actualizacion-punto" />
                </span>
                <span className="gelia-actualizacion-anillo gelia-actualizacion-anillo--3">
                    <i className="gelia-actualizacion-punto" />
                </span>
                <span className="gelia-actualizacion-brillo" />
                <svg viewBox="0 0 100 100" className="gelia-actualizacion-logo relative z-10 h-20 w-20" xmlns="http://www.w3.org/2000/svg">
                    <g fill="var(--color-primario)">
                        {PIEZAS_LOGO.map((pieza) => (
                            <polygon key={pieza.puntos} points={pieza.puntos} opacity={pieza.opacidad} />
                        ))}
                    </g>
                </svg>
            </div>

            <div className="gelia-actualizacion-texto relative z-10 flex w-full max-w-sm flex-col items-center gap-4 text-center">
                <h2 className="m-0 text-balance text-xl font-semibold theme-text-main">
                    Actualizando tu <span translate="no">GELIA</span>
                </h2>
                <ProgresoActualizacion duracionMs={ESPERA_ACTUALIZACION_MS} nota={nota} />
            </div>

            <div className="gelia-actualizacion-destello" aria-hidden="true" />
            {vistaPrevia ? (
                <button
                    type="button"
                    className="theme-surface absolute right-4 top-4 z-30 inline-flex min-h-11 items-center rounded-xl border px-4 text-sm font-semibold theme-text-main focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primario)]"
                    onClick={() => alCerrar?.()}
                >
                    Cerrar vista previa
                </button>
            ) : null}
        </div>,
        document.body
    );
}
