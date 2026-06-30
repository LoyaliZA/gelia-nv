import React from 'react';
import { createPortal } from 'react-dom';
import { X, Download, CheckCircle2, AlertTriangle, FileWarning } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_BTN_PRIMARY } from '@/utils/geliaTheme';

export default function ImportacionResumenModal({ data, onClose }) {
    if (!data) return null;

    const importados = data.importados ?? 0;
    const actualizados = data.actualizados ?? 0;
    const omitidos = data.omitidos ?? 0;
    const errores = data.errores_detalle ?? data.errores ?? [];
    const reporteUrl = data.reporte_url ?? null;

    const descargarReporte = () => {
        if (reporteUrl) {
            window.location.href = reporteUrl;
        }
    };

    return createPortal(
        <div className={THEME_MODAL_OVERLAY} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-lg w-full max-h-[90vh] overflow-y-auto text-left modal-pop`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-6 border-b theme-border flex justify-between items-center sticky top-0 theme-surface z-10">
                    <h2 className="text-xl font-black italic uppercase theme-text-main m-0 flex items-center gap-2">
                        <CheckCircle2 className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                        Resumen de importación
                    </h2>
                    <button type="button" onClick={onClose} className="p-2 hover:bg-black/5 dark:hover:bg-white/5 rounded-full">
                        <X className="w-5 h-5 theme-text-muted" />
                    </button>
                </div>

                <div className="p-6 space-y-4">
                    <div className="grid grid-cols-3 gap-3">
                        <div className="theme-element border theme-border rounded-xl p-3 text-center">
                            <p className="text-2xl font-black theme-text-main m-0">{importados}</p>
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0 mt-1">Nuevos</p>
                        </div>
                        <div className="theme-element border theme-border rounded-xl p-3 text-center">
                            <p className="text-2xl font-black theme-text-main m-0">{actualizados}</p>
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0 mt-1">Actualizados</p>
                        </div>
                        <div className="theme-element border theme-border rounded-xl p-3 text-center">
                            <p className={`text-2xl font-black m-0 ${omitidos > 0 ? 'text-amber-600 dark:text-amber-400' : 'theme-text-main'}`}>{omitidos}</p>
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0 mt-1">Omitidos</p>
                        </div>
                    </div>

                    {omitidos > 0 && (
                        <div className="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 flex gap-2 items-start">
                            <AlertTriangle className="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                            <p className="text-[11px] font-bold text-amber-700 dark:text-amber-300 m-0">
                                Algunas filas no se importaron. Revisa el reporte de errores para corregirlas.
                            </p>
                        </div>
                    )}

                    {errores.length > 0 && (
                        <div className="space-y-2">
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 flex items-center gap-1">
                                <FileWarning className="w-3.5 h-3.5" /> Errores ({errores.length})
                            </p>
                            <ul className="max-h-40 overflow-y-auto space-y-1 m-0 p-0 list-none">
                                {errores.slice(0, 8).map((msg, i) => (
                                    <li key={i} className="text-[10px] theme-text-muted font-bold px-2 py-1 theme-element rounded-lg border theme-border">
                                        {msg}
                                    </li>
                                ))}
                                {errores.length > 8 && (
                                    <li className="text-[10px] theme-text-muted italic px-2">
                                        … y {errores.length - 8} errores más en el reporte CSV.
                                    </li>
                                )}
                            </ul>
                        </div>
                    )}

                    <div className="flex flex-col gap-2 pt-2">
                        {reporteUrl && (
                            <button
                                type="button"
                                onClick={descargarReporte}
                                className={`${THEME_BTN_PRIMARY} w-full py-3 flex items-center justify-center gap-2`}
                            >
                                <Download className="w-4 h-4" />
                                Descargar reporte de errores
                            </button>
                        )}
                        <button
                            type="button"
                            onClick={onClose}
                            className="w-full py-3 text-[11px] font-black uppercase rounded-xl theme-element border theme-border theme-text-main"
                        >
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>,
        document.body
    );
}
