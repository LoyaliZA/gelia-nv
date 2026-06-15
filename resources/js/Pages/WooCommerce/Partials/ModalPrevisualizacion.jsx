import React from 'react';
import { createPortal } from 'react-dom';
import { X, CloudUpload } from 'lucide-react';

export default function ModalPrevisualizacion({ detalles, onClose, onConfirm }) {
    const modal = (
        <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md">
            <div className="w-full max-w-4xl theme-surface border theme-border rounded-[2.5rem] p-6 md:p-8 max-h-[90vh] overflow-y-auto custom-scrollbar">
                <div className="flex justify-between items-center mb-6">
                    <h2 className="text-xl font-black italic uppercase theme-text-main">Previsualización de Cambios</h2>
                    <button onClick={onClose} className="p-2 theme-text-muted hover:theme-text-main"><X className="w-5 h-5" /></button>
                </div>

                {detalles.length === 0 ? (
                    <p className="text-center theme-text-muted font-bold py-8">No se detectaron diferencias con los precios actuales.</p>
                ) : (
                    <>
                        <p className="text-xs font-bold theme-text-muted mb-4">{detalles.length} productos requieren actualización.</p>
                        <div className="overflow-x-auto rounded-2xl border theme-border">
                            <table className="w-full text-xs">
                                <thead>
                                    <tr className="text-[10px] font-black uppercase tracking-widest theme-text-muted border-b theme-border">
                                        <th className="p-3 text-left">SKU</th>
                                        <th className="p-3 text-left">Nombre</th>
                                        <th className="p-3 text-center">Normal</th>
                                        <th className="p-3 text-center">Rebaja</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {detalles.map((item) => (
                                        <tr key={item.sku} className="border-b theme-border/50">
                                            <td className="p-3 font-bold theme-text-main">{item.sku}</td>
                                            <td className="p-3 theme-text-muted truncate max-w-[180px]">{item.nombre}</td>
                                            <td className="p-3 text-center">
                                                <span className="line-through theme-text-muted mr-2">${Number(item.precio_normal_anterior ?? 0).toFixed(2)}</span>
                                                <span className="font-bold theme-text-main">${Number(item.precio_normal_nuevo).toFixed(2)}</span>
                                            </td>
                                            <td className="p-3 text-center">
                                                <span className="line-through theme-text-muted mr-2">${Number(item.precio_rebaja_anterior ?? 0).toFixed(2)}</span>
                                                <span className="font-bold text-emerald-500">${Number(item.precio_rebaja_nuevo).toFixed(2)}</span>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <button onClick={onConfirm} className="mt-6 w-full py-3 rounded-xl font-black text-[11px] uppercase tracking-widest text-white flex items-center justify-center gap-2"
                            style={{ backgroundColor: 'var(--color-primario)' }}>
                            <CloudUpload className="w-4 h-4" /> Confirmar y Sincronizar
                        </button>
                    </>
                )}
            </div>
        </div>
    );
    return typeof window === 'undefined' ? modal : createPortal(modal, document.body);
}
