import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { deferModalAction, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../../utils/geliaTheme';
import { reportarMensajeOperacion } from '../../../../utils/geliaToast';
import { THEME_INPUT } from '../../Resguardos/Partials/resguardosStyles';

export default function ModalCerrarAtencionTurno({
    abierto,
    catalogos = {},
    procesando = false,
    onClose,
    onConfirmar,
}) {
    const [motivo, setMotivo] = useState('venta');
    const [motivoDetalle, setMotivoDetalle] = useState('');
    const motivos = catalogos?.motivos_cierre || [];
    const requiereDetalle = motivo === 'otro';

    const confirmar = () => {
        if (requiereDetalle && !motivoDetalle.trim()) {
            reportarMensajeOperacion('Indica el detalle cuando el motivo es "otro".');
            return;
        }
        onConfirmar?.({ motivo, motivoDetalle: motivoDetalle.trim() || null });
    };

    if (!abierto) return null;

    const cerrar = (event) => {
        event?.stopPropagation?.();
        deferModalAction(onClose);
    };

    return createPortal(
        <div
            className={`${THEME_MODAL_OVERLAY} items-end sm:items-center py-4`}
            style={{ zIndex: 'calc(var(--gelia-z-modal) + 15)' }}
            onClick={cerrar}
        >
            <div
                className={`${THEME_MODAL_SHELL} max-w-md w-full p-6 space-y-5 modal-pop`}
                onClick={(e) => e.stopPropagation()}
                role="dialog"
                aria-modal="true"
                aria-labelledby="modal-cerrar-titulo"
            >
                <h3 id="modal-cerrar-titulo" className="text-base font-black uppercase theme-text-main m-0">
                    Cerrar atención
                </h3>

                <div className="space-y-3">
                    <label className="block space-y-2">
                        <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Motivo</span>
                        <select
                            className={`${THEME_INPUT} w-full min-h-[44px]`}
                            value={motivo}
                            onChange={(e) => setMotivo(e.target.value)}
                            disabled={procesando}
                        >
                            {motivos.map((item) => (
                                <option key={item.valor} value={item.valor}>{item.etiqueta}</option>
                            ))}
                        </select>
                    </label>

                    {requiereDetalle && (
                        <label className="block space-y-2">
                            <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Detalle</span>
                            <textarea
                                className={`${THEME_INPUT} w-full min-h-[88px]`}
                                value={motivoDetalle}
                                onChange={(e) => setMotivoDetalle(e.target.value)}
                                disabled={procesando}
                            />
                        </label>
                    )}

                </div>

                <div className="flex flex-col gap-3">
                    <button
                        type="button"
                        className="theme-btn-primary min-h-[44px] rounded-2xl text-[10px] font-black uppercase"
                        disabled={procesando}
                        onClick={confirmar}
                    >
                        Confirmar cierre
                    </button>
                    <button
                        type="button"
                        className="min-h-[44px] rounded-2xl text-[10px] font-black uppercase theme-border theme-element theme-text-muted"
                        disabled={procesando}
                        onClick={cerrar}
                    >
                        Cancelar
                    </button>
                </div>
            </div>
        </div>,
        document.body,
    );
}
