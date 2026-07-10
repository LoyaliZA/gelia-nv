import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { FileText, FileSpreadsheet, X } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';

export default function ModalReportesCobranza({ isOpen, onClose, clientes, onGenerar }) {
    const [filtros, setFiltros] = useState({
        cliente_id: '',
        fecha_inicio: '',
        fecha_fin: '',
    });

    if (!isOpen) return null;

    const handleGenerar = (formato) => {
        onGenerar({ ...filtros, formato });
        onClose();
    };

    return createPortal(
        <div className={THEME_MODAL_OVERLAY}>
            <div className={`${THEME_MODAL_SHELL} max-w-2xl`}>
                <div className="flex justify-between items-center p-6 border-b theme-border bg-black/5 dark:bg-white/5">
                    <div>
                        <h2 className="text-xl font-black italic tracking-tighter uppercase theme-text-main m-0">Generar Reporte de Cobranza</h2>
                        <p className="text-xs theme-text-muted mt-1">Configura los parámetros antes de generar el reporte.</p>
                    </div>
                    <button onClick={onClose} className="p-2 rounded-xl hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                        <X className="w-5 h-5 theme-text-muted" />
                    </button>
                </div>

                <div className="p-6 space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="col-span-full">
                            <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Cliente (Opcional)</label>
                            <select
                                className="w-full rounded-xl border theme-border bg-transparent px-4 py-3 text-sm focus:ring-2 focus:ring-[var(--color-primario)] theme-text-main"
                                value={filtros.cliente_id}
                                onChange={e => setFiltros({ ...filtros, cliente_id: e.target.value })}
                            >
                                <option value="">Todos los clientes</option>
                                {(Array.isArray(clientes) ? clientes : (clientes?.data || [])).map(c => (
                                    <option key={c.id} value={c.id}>{c.numero_cliente} - {c.nombre}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Fecha Inicio (Opcional)</label>
                            <input
                                type="date"
                                className="w-full rounded-xl border theme-border bg-transparent px-4 py-3 text-sm focus:ring-2 focus:ring-[var(--color-primario)] theme-text-main"
                                value={filtros.fecha_inicio}
                                onChange={e => setFiltros({ ...filtros, fecha_inicio: e.target.value })}
                            />
                        </div>
                        <div>
                            <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Fecha Fin (Opcional)</label>
                            <input
                                type="date"
                                className="w-full rounded-xl border theme-border bg-transparent px-4 py-3 text-sm focus:ring-2 focus:ring-[var(--color-primario)] theme-text-main"
                                value={filtros.fecha_fin}
                                onChange={e => setFiltros({ ...filtros, fecha_fin: e.target.value })}
                            />
                        </div>
                    </div>
                </div>

                <div className="p-6 border-t theme-border bg-black/5 dark:bg-white/5 flex gap-4 justify-end">
                    <button
                        type="button"
                        onClick={() => handleGenerar('pdf')}
                        className="flex items-center gap-2 px-6 py-3 rounded-xl bg-red-600 text-white font-bold hover:bg-red-700 transition-colors shadow-sm"
                    >
                        <FileText className="w-4 h-4" />
                        Generar PDF
                    </button>
                    <button
                        type="button"
                        onClick={() => handleGenerar('excel')}
                        className="flex items-center gap-2 px-6 py-3 rounded-xl bg-green-600 text-white font-bold hover:bg-green-700 transition-colors shadow-sm"
                    >
                        <FileSpreadsheet className="w-4 h-4" />
                        Generar Excel
                    </button>
                </div>
            </div>
        </div>,
        document.body
    );
}
