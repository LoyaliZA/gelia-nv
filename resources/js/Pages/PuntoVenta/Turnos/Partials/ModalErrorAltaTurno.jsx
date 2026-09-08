import React from 'react';
import { createPortal } from 'react-dom';
import { AlertTriangle } from 'lucide-react';

export default function ModalErrorAltaTurno({
    abierto,
    mensaje = '',
    onClose,
}) {
    if (!abierto || !mensaje) return null;

    return createPortal(
        <div
            className="fixed inset-0 z-[calc(var(--gelia-z-modal)+10)] flex items-end sm:items-center justify-center p-4 bg-black/50"
            onClick={onClose}
        >
            <div
                className="theme-modal w-full max-w-md rounded-3xl p-6 space-y-5"
                onClick={(event) => event.stopPropagation()}
                role="alertdialog"
                aria-labelledby="modal-error-alta-turno-titulo"
                aria-describedby="modal-error-alta-turno-mensaje"
            >
                <div className="flex items-start gap-3">
                    <AlertTriangle className="w-6 h-6 text-red-500 shrink-0" aria-hidden />
                    <div className="space-y-2 min-w-0">
                        <h3
                            id="modal-error-alta-turno-titulo"
                            className="text-base font-black uppercase theme-text-main m-0"
                        >
                            No se pudo registrar el turno
                        </h3>
                        <p
                            id="modal-error-alta-turno-mensaje"
                            className="text-sm font-semibold theme-text-muted m-0"
                        >
                            {mensaje}
                        </p>
                    </div>
                </div>

                <button
                    type="button"
                    className="theme-btn-primary w-full min-h-[44px] rounded-2xl text-[10px] font-black uppercase"
                    onClick={onClose}
                >
                    Entendido
                </button>
            </div>
        </div>,
        document.body,
    );
}
