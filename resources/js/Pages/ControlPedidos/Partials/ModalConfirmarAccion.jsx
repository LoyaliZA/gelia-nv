import React from 'react';
import { createPortal } from 'react-dom';
import { AlertTriangle } from 'lucide-react';
import {
    THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, BTN_PRIMARY, BTN_SECONDARY, deferModalAction,
} from './pedidosBmaStyles';

export default function ModalConfirmarAccion({
    abierto,
    titulo,
    mensaje,
    etiquetaConfirmar = 'Confirmar',
    etiquetaAlternativa = null,
    variante = 'danger',
    onClose,
    onConfirm,
    onAlternativa = null,
}) {
    if (!abierto) return null;

    const btnConfirmar = variante === 'danger'
        ? 'theme-btn-danger flex-1 py-3 rounded-xl justify-center'
        : `${BTN_PRIMARY} flex-1 py-3`;

    const cerrar = (e) => {
        e?.stopPropagation?.();
        deferModalAction(onClose);
    };

    return createPortal(
        <div
            className={`${THEME_MODAL_OVERLAY} items-center py-4`}
            style={{ zIndex: 'calc(var(--gelia-z-modal) + 20)' }}
            onClick={cerrar}
        >
            <div
                className={`${THEME_MODAL_SHELL} max-w-md w-full p-6 md:p-8 space-y-6`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-start gap-4">
                    <AlertTriangle className="w-8 h-8 text-orange-500 shrink-0" />
                    <div>
                        <h3 className="text-base font-black uppercase theme-text-main m-0">{titulo}</h3>
                        <p className="text-sm theme-text-muted mt-2 m-0 leading-relaxed">{mensaje}</p>
                    </div>
                </div>
                <div className="flex flex-col gap-3">
                    <button
                        type="button"
                        onClick={(e) => {
                            e.stopPropagation();
                            deferModalAction(onConfirm);
                        }}
                        className={btnConfirmar}
                    >
                        {etiquetaConfirmar}
                    </button>
                    {etiquetaAlternativa && onAlternativa && (
                        <button
                            type="button"
                            onClick={(e) => {
                                e.stopPropagation();
                                deferModalAction(onAlternativa);
                            }}
                            className={`${BTN_SECONDARY} flex-1 py-3 rounded-xl border theme-border theme-element`}
                        >
                            {etiquetaAlternativa}
                        </button>
                    )}
                    <button type="button" onClick={cerrar} className={`${BTN_SECONDARY} flex-1 py-3 rounded-xl border theme-border theme-element`}>
                        Cancelar
                    </button>
                </div>
            </div>
        </div>,
        document.body
    );
}
