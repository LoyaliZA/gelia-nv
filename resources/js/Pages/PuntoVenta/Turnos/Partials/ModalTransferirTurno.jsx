import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { THEME_INPUT } from '../../Resguardos/Partials/resguardosStyles';

export default function ModalTransferirTurno({
    abierto,
    personas = [],
    procesando = false,
    onClose,
    onConfirmar,
}) {
    const [destinoId, setDestinoId] = useState('');
    const [error, setError] = useState(null);

    const confirmar = () => {
        if (!destinoId) {
            setError('Selecciona la persona destino.');
            return;
        }
        setError(null);
        onConfirmar?.(Number(destinoId));
    };

    if (!abierto) return null;

    return createPortal(
        <div
            className="fixed inset-0 z-[calc(var(--gelia-z-modal)+10)] flex items-end sm:items-center justify-center p-4 bg-black/50"
            onClick={onClose}
        >
            <div
                className="theme-modal w-full max-w-md rounded-3xl p-6 space-y-5"
                onClick={(e) => e.stopPropagation()}
                role="dialog"
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

                {error && (
                    <p className="text-xs font-bold text-red-600 dark:text-red-400 m-0">{error}</p>
                )}

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
                        onClick={onClose}
                    >
                        Cancelar
                    </button>
                </div>
            </div>
        </div>,
        document.body,
    );
}
