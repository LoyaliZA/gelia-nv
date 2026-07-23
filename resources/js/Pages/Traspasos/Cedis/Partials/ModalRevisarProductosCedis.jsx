import React from 'react';
import { createPortal } from 'react-dom';
import { X, AlertTriangle, Package } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../../utils/geliaTheme';

export default function ModalRevisarProductosCedis({ traspaso, onClose, onReportarProducto }) {
    const productos = traspaso.productos || [];

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-end sm:items-center p-0 sm:p-4`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} w-full max-w-lg max-h-[92dvh] flex flex-col rounded-t-3xl sm:rounded-3xl`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <div className="min-w-0">
                        <h2 className="text-lg font-black italic uppercase theme-text-main m-0 flex items-center gap-2">
                            <Package className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                            Revisar productos
                        </h2>
                        <p className="text-xs theme-text-muted font-bold mt-1 m-0">
                            {traspaso.folio} · {productos.length} línea{productos.length === 1 ? '' : 's'}
                        </p>
                    </div>
                    <button type="button" onClick={onClose} className="p-3 rounded-2xl theme-element border theme-border outline-none" aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="flex-1 overflow-y-auto p-5 space-y-3 custom-scrollbar">
                    {productos.map((p) => {
                        const detalle = p.detalle_dano || p.detalleDano;
                        return (
                            <div key={p.id} className="rounded-2xl border theme-border px-4 py-3 flex flex-col gap-3">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0">
                                        <p className="text-sm font-black theme-text-main m-0 tabular-nums break-all">{p.sku}</p>
                                        <p className="text-sm font-bold theme-text-main m-0 mt-0.5 leading-snug break-words">{p.descripcion}</p>
                                        {detalle && (
                                            <p className="text-[10px] font-black uppercase tracking-widest text-amber-600 dark:text-amber-400 m-0 mt-1">
                                                Con detalle/daño
                                            </p>
                                        )}
                                    </div>
                                    <span className="text-lg font-black shrink-0 tabular-nums" style={{ color: 'var(--color-primario)' }}>
                                        ×{p.piezas}
                                    </span>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => onReportarProducto(p)}
                                    className="w-full py-3 rounded-xl text-[10px] font-black uppercase tracking-widest inline-flex items-center justify-center gap-1.5 outline-none border border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300"
                                >
                                    <AlertTriangle className="w-3.5 h-3.5" />
                                    {detalle ? 'Actualizar detalle/daño' : 'Reportar detalle/daño'}
                                </button>
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>,
        document.body,
    );
}
