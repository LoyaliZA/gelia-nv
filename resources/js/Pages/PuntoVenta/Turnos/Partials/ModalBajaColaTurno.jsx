import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { THEME_INPUT } from '../../Resguardos/Partials/resguardosStyles';

export default function ModalBajaColaTurno({
    abierto,
    turno = null,
    catalogos = {},
    procesando = false,
    onClose,
    onConfirmar,
}) {
    const [motivo, setMotivo] = useState('se_fue');
    const [motivoDetalle, setMotivoDetalle] = useState('');
    const [error, setError] = useState(null);

    const motivos = catalogos?.motivos_baja || [];
    const requiereDetalle = motivo === 'otro';

    const confirmar = () => {
        if (requiereDetalle && !motivoDetalle.trim()) {
            setError('Indica el detalle cuando el motivo es "otro".');
            return;
        }
        setError(null);
        onConfirmar?.({ motivo, motivoDetalle: motivoDetalle.trim() || null });
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
                aria-labelledby="modal-baja-cola-titulo"
            >
                <h3 id="modal-baja-cola-titulo" className="text-base font-black uppercase theme-text-main m-0">
                    Baja de cola
                </h3>

                {turno?.folio && (
                    <p className="text-sm font-semibold theme-text-muted m-0">
                        Turno <span className="font-black theme-text-main">{turno.folio}</span>
                        {turno.snapshot_nombre_llamado ? ` — ${turno.snapshot_nombre_llamado}` : ''}
                    </p>
                )}

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

                    {error && (
                        <p className="text-xs font-bold text-red-600 dark:text-red-400 m-0">{error}</p>
                    )}
                </div>

                <div className="flex flex-col gap-3">
                    <button
                        type="button"
                        className="theme-btn-primary min-h-[44px] rounded-2xl text-[10px] font-black uppercase"
                        disabled={procesando}
                        onClick={confirmar}
                    >
                        Confirmar baja
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
