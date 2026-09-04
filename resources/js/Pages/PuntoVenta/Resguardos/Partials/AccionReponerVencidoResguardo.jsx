import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { RotateCcw, X } from 'lucide-react';
import FormularioReponerVencidoResguardo from './FormularioReponerVencidoResguardo';
import useReponerVencidoResguardo from './useReponerVencidoResguardo';
import { BTN_SECONDARY } from './resguardosStyles';
import { puedeReponerVencido } from './reponerVencidoResguardoUtils';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../../utils/geliaTheme';

export default function AccionReponerVencidoResguardo({ resguardo, permisos = {}, onExito }) {
    const [modalAbierto, setModalAbierto] = useState(false);

    const {
        enviando,
        error,
        reponerVencido,
        recargar,
    } = useReponerVencidoResguardo({
        resguardoId: resguardo.id,
        versionInicial: resguardo.version,
        onExito: (data) => {
            setModalAbierto(false);
            onExito?.(data);
            recargar();
        },
    });

    if (!puedeReponerVencido(permisos, resguardo)) {
        return null;
    }

    const cerrar = () => {
        if (enviando) return;
        setModalAbierto(false);
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setModalAbierto(true)}
                className={`${BTN_SECONDARY} inline-flex items-center gap-2 min-h-[44px] text-[10px] font-black uppercase tracking-widest`}
            >
                <RotateCcw className="w-4 h-4" /> Reponer
            </button>

            {modalAbierto && createPortal(
                <div
                    className={`${THEME_MODAL_OVERLAY} items-end sm:items-center p-0 sm:p-4`}
                    style={{ zIndex: 'calc(var(--gelia-z-modal) + 10)' }}
                    onClick={cerrar}
                >
                    <div
                        className={`${THEME_MODAL_SHELL} w-full sm:max-w-lg max-h-[92vh] overflow-y-auto rounded-t-3xl sm:rounded-3xl p-5 md:p-6 space-y-4`}
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <h3 className="text-base font-black uppercase theme-text-main m-0">
                                    Reponer vencido
                                </h3>
                                <p className="text-xs theme-text-muted m-0 mt-1">
                                    {resguardo.snapshot_folio || `Resguardo #${resguardo.id}`}
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={cerrar}
                                disabled={enviando}
                                className={`${BTN_SECONDARY} p-2 min-h-[44px] min-w-[44px] shrink-0`}
                                aria-label="Cerrar"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>

                        <FormularioReponerVencidoResguardo
                            resguardo={resguardo}
                            enviando={enviando}
                            error={error}
                            onEnviar={reponerVencido}
                            onCancelar={cerrar}
                            compacto
                        />
                    </div>
                </div>,
                document.body,
            )}
        </>
    );
}
