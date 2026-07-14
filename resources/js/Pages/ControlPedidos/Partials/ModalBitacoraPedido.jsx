import React from 'react';
import { createPortal } from 'react-dom';
import { X, History } from 'lucide-react';
import {
    badgeEstatusPedido,
    formatearFechaHoraAuditoria,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
} from './pedidosBmaStyles';
import EncabezadoFolioPedido from './EncabezadoFolioPedido';

export default function ModalBitacoraPedido({ abierto, onClose, pedido }) {
    if (!abierto || !pedido) return null;

    const historial = pedido.historial || [];

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-lg w-full flex flex-col`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <div className="flex items-center gap-2 min-w-0">
                        <History className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        <div className="min-w-0">
                            <h2 className="text-lg font-black italic uppercase theme-text-main m-0">Bitácora</h2>
                            <EncabezadoFolioPedido pedido={pedido} size="sm" className="mt-1" />
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-2 rounded-full theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 transition-colors outline-none shrink-0"
                        aria-label="Cerrar"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>
                <div className="gelia-modal-body p-5 md:p-6">
                    {historial.length === 0 ? (
                        <p className="text-sm theme-text-muted font-bold uppercase m-0">Sin movimientos registrados_</p>
                    ) : (
                        <div className="space-y-4">
                            {historial.map((h) => {
                                const badge = badgeEstatusPedido(h.estatus_nuevo || h.estatusNuevo);
                                return (
                                    <div key={h.id} className="p-4 rounded-xl border theme-border theme-element">
                                        <div className="flex justify-between items-start gap-2">
                                            <span className={badge.className} style={badge.style}>{badge.label}</span>
                                            <span className="text-[9px] theme-text-muted font-bold shrink-0 font-mono">
                                                {formatearFechaHoraAuditoria(h.created_at)}
                                            </span>
                                        </div>
                                        <p className="text-[10px] font-bold theme-text-muted mt-2 m-0">{h.usuario?.name}</p>
                                        {h.comentarios && <p className="text-xs theme-text-main mt-1 m-0">{h.comentarios}</p>}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </div>,
        document.body
    );
}
