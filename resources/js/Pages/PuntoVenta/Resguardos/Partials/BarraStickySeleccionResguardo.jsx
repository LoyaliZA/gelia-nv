import React from 'react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { RESGUARDOS_STICKY_SELECCION } from './resguardosStyles';

/**
 * Barra inferior fija para selección masiva (móvil/tablet: safe-area + ancho completo).
 */
export default function BarraStickySeleccionResguardo({ titulo, meta, acciones, children }) {
    return (
        <div className={`${geliaCardClass()} ${RESGUARDOS_STICKY_SELECCION}`}>
            <div className="px-3 py-2.5 sm:px-4 sm:py-3 flex flex-col gap-2 border-b theme-border bg-black/[0.02] dark:bg-white/[0.02] sm:flex-row sm:items-center sm:justify-between">
                <div className="min-w-0">
                    <p className="text-sm font-bold theme-text-main m-0 truncate">{titulo}</p>
                    {meta && (
                        <p className="text-xs theme-text-muted m-0 mt-0.5">{meta}</p>
                    )}
                </div>
                {acciones && (
                    <div className="flex flex-wrap gap-2 shrink-0">{acciones}</div>
                )}
            </div>
            {children && <div className="flex flex-col gap-2 p-3 sm:p-3">{children}</div>}
        </div>
    );
}
