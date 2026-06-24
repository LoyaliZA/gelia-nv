import React from 'react';
import { createPortal } from 'react-dom';
import { Package, X } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';
import { formatoMoneda } from '../../../utils/formatoMoneda';

function badgeEstado(tipo) {
    if (tipo === 'devuelto') {
        return <span className="text-[9px] font-black uppercase px-2 py-0.5 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">Devuelto</span>;
    }
    if (tipo === 'perdido_danado') {
        return <span className="text-[9px] font-black uppercase px-2 py-0.5 rounded bg-red-500/10 text-red-600 dark:text-red-400 border border-red-500/20">Perdido</span>;
    }
    return <span className="text-[9px] font-black uppercase px-2 py-0.5 rounded bg-slate-500/10 theme-text-muted border theme-border">Vendido</span>;
}

export default function ModalVerProductos({ pedido, onCerrar }) {
    if (!pedido) return null;

    const lineas = pedido.lineas || [];

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md`} onClick={onCerrar}>
            <div className={`${THEME_MODAL_SHELL} w-full max-w-xl rounded-[2rem] shadow-2xl p-6 md:p-8 relative`} onClick={(e) => e.stopPropagation()}>
                <button type="button" onClick={onCerrar} className="absolute top-4 right-4 p-2 theme-text-muted hover:theme-text-main rounded-xl outline-none">
                    <X className="w-5 h-5" />
                </button>
                <div className="flex items-center gap-3 mb-6 border-b theme-border pb-4">
                    <Package className="w-6 h-6 text-[var(--color-primario)]" />
                    <h3 className="text-xl font-black italic theme-text-main uppercase m-0">
                        Productos · {pedido.numero_pedido}
                    </h3>
                </div>
                <div className="overflow-y-auto max-h-[360px] custom-scrollbar">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b theme-border text-[10px] font-black uppercase tracking-widest theme-text-muted">
                                <th className="py-2 text-left">SKU</th>
                                <th className="py-2 text-left">Producto</th>
                                <th className="py-2 text-center">Pzas</th>
                                <th className="py-2 text-right">Precio</th>
                                <th className="py-2 text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y theme-border">
                             {lineas.length === 0 ? (
                                <tr>
                                    <td colSpan={5} className="py-8 text-center theme-text-muted text-sm font-bold">
                                        Sin productos registrados
                                    </td>
                                </tr>
                            ) : (
                                lineas.map((linea) => (
                                    <tr key={linea.id}>
                                        <td className="py-2 font-bold theme-text-main">{linea.sku}</td>
                                        <td className="py-2 theme-text-main">{linea.nombre_producto}</td>
                                        <td className="py-2 text-center font-black">{linea.piezas}</td>
                                        <td className="py-2 text-right font-black italic">{formatoMoneda(linea.precio_unitario)}</td>
                                        <td className="py-2 text-center">{badgeEstado(linea.tipo_devolucion)}</td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>,
        document.body
    );
}
