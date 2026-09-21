import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { deferModalAction, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../../utils/geliaTheme';
import { reportarMensajeOperacion } from '../../../../utils/geliaToast';
import { THEME_INPUT } from '../../Resguardos/Partials/resguardosStyles';

export default function ModalTransferirTurno({
    abierto,
    personas = [],
    procesando = false,
    onClose,
    onConfirmar,
}) {
    const [destinoId, setDestinoId] = useState('');
    const confirmar = () => {
        if (!destinoId) {
            reportarMensajeOperacion('Selecciona la persona destino.');
            return;
        }
        onConfirmar?.(Number(destinoId));
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
                aria-labelledby="modal-transferir-titulo"
            >
                <h3 id="modal-transferir-titulo" className="text-base font-black uppercase theme-text-main m-0">
                    Transferir turno
                </h3>
                <p className="text-sm theme-text-muted m-0">
                    Elige a quién pasará la atención en la sucursal activa.
                </p>

                <label className="block space-y-2">
                    <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Persona destino</span>
                    <select
                        className={`${THEME_INPUT} w-full min-h-[44px]`}
                        value={destinoId}
                        onChange={(e) => setDestinoId(e.target.value)}
                        disabled={procesando}
                    >
                        <option value="">Seleccionar…</option>
                        {personas.map((persona) => (
                            <option key={persona.id} value={persona.id}>{persona.primer_nombre}</option>
                        ))}
                    </select>
                </label>

                <div className="flex flex-col gap-3">
                    <button
                        type="button"
                        className="theme-btn-primary min-h-[44px] rounded-2xl text-[10px] font-black uppercase"
                        disabled={procesando || personas.length === 0}
                        onClick={confirmar}
                    >
                        Confirmar transferencia
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
