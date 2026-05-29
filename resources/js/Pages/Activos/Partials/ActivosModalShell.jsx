import React from 'react';
import { X } from 'lucide-react';
import {
    MODAL_OVERLAY_CLASS,
    MODAL_SHELL_CLASS,
    BTN_ICON_CLASS,
    renderActivosModal,
} from './activosFormStyles';

const MODAL_OVERLAY_SCROLL = `${MODAL_OVERLAY_CLASS} items-start sm:items-center py-4 sm:py-6 overflow-y-auto`;

/**
 * Contenedor estándar: cabecera fija, cuerpo con scroll, pie fijo (gelia-modal-body / gelia-modal-footer).
 */
export default function ActivosModalShell({ title, onClose, size = 'max-w-lg', loader = null, children }) {
    return renderActivosModal(
        <div className={MODAL_OVERLAY_SCROLL} role="dialog" aria-modal="true" aria-labelledby="activos-modal-title">
            {loader}
            <div
                className={`${MODAL_SHELL_CLASS} ${size} w-full flex flex-col text-left modal-pop`}
                style={{ maxHeight: 'min(90vh, calc(100dvh - 2rem))' }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center justify-between gap-3 p-5 md:p-6 border-b theme-border shrink-0">
                    <h2 id="activos-modal-title" className="text-lg md:text-xl font-black italic uppercase theme-text-main m-0 leading-tight">
                        {title}
                    </h2>
                    <button type="button" onClick={onClose} className={BTN_ICON_CLASS} aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>
                <div className="flex flex-col flex-1 min-h-0 overflow-hidden">
                    {children}
                </div>
            </div>
        </div>
    );
}
