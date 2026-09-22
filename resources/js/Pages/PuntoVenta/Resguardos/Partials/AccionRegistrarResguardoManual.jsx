import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { ClipboardPlus, X } from 'lucide-react';
import { THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../../utils/geliaTheme';
import FormularioRegistroManualResguardo from './FormularioRegistroManualResguardo';
import useRegistroManualResguardo from './useRegistroManualResguardo';

export default function AccionRegistrarResguardoManual({ habilitado = false, origenes = [], onExito }) {
    const [modalAbierto, setModalAbierto] = useState(false);

    const { enviando, error, registrar, setError } = useRegistroManualResguardo({
        onExito: (data) => {
            setModalAbierto(false);
            onExito?.(data);
        },
    });

    if (!habilitado) {
        return null;
    }

    const cerrar = () => {
        if (enviando) return;
        setError(null);
        setModalAbierto(false);
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setModalAbierto(true)}
                className={`${THEME_BTN_PRIMARY} inline-flex items-center justify-center gap-2 min-h-[44px] px-3 text-[10px] font-black uppercase tracking-widest`}
            >
                <ClipboardPlus className="w-4 h-4 shrink-0" aria-hidden />
                Registrar resguardo
            </button>

            {modalAbierto && createPortal(
                <div
                    className={`${THEME_MODAL_OVERLAY} items-end sm:items-center p-0 sm:p-4 overflow-hidden`}
                    style={{ zIndex: 'calc(var(--gelia-z-modal) + 10)' }}
                    onClick={cerrar}
                >
                    <div
                        className={`${THEME_MODAL_SHELL} w-full sm:max-w-2xl max-h-[92dvh] min-h-0 flex flex-col rounded-t-3xl sm:rounded-3xl overflow-hidden`}
                        onClick={(event) => event.stopPropagation()}
                    >
                        <div className="p-5 md:p-6 border-b theme-border flex items-start justify-between gap-3 shrink-0">
                            <div className="min-w-0">
                                <h3 className="text-base font-black uppercase theme-text-main m-0">
                                    Registrar resguardo
                                </h3>
                                <p className="text-xs theme-text-muted m-0 mt-1">
                                    Alta sin pedido. Quedará en recepción para confirmar bultos y entregar.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={cerrar}
                                disabled={enviando}
                                className={`${THEME_BTN_SECONDARY} p-2 min-h-[44px] min-w-[44px] shrink-0`}
                                aria-label="Cerrar"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        <div className="p-5 md:p-6 overflow-y-auto flex-1 min-h-0 overscroll-contain pb-[max(1.25rem,env(safe-area-inset-bottom))]">
                            <FormularioRegistroManualResguardo
                                enviando={enviando}
                                error={error}
                                origenes={origenes}
                                onEnviar={registrar}
                                onCancelar={cerrar}
                            />
                        </div>
                    </div>
                </div>,
                document.body,
            )}
        </>
    );
}
