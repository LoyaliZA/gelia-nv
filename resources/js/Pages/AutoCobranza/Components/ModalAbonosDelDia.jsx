import React from 'react';
import { createPortal } from 'react-dom';
import { X, ArrowDownRight, Clock } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';
import { formatoMoneda } from '../../../utils/formatoMoneda';

function extraerFolioAbono(descripcion) {
    const match = descripcion?.match(/folio\s+([^\s(]+)/i);
    return match ? match[1] : null;
}

export default function ModalAbonosDelDia({ abonos, onClose }) {
    if (typeof document === 'undefined') return null;

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} z-[9999] p-4`} onClick={onClose}>
            <div className={`${THEME_MODAL_SHELL} w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden`} onClick={e => e.stopPropagation()}>
                <div className="p-6 md:p-8 flex justify-between items-center border-b theme-border bg-black/5 dark:bg-white/5">
                    <div>
                        <h3 className="text-lg font-black uppercase italic tracking-tighter theme-text-main flex items-center gap-2">
                            <ArrowDownRight className="w-5 h-5 text-emerald-500" />
                            Abonos Registrados Hoy
                        </h3>
                        <p className="text-xs theme-text-muted mt-1 font-bold">
                            Pagos parciales detectados durante las importaciones del día.
                        </p>
                    </div>
                    <button onClick={onClose} className="p-2 rounded-full theme-element theme-text-muted hover:theme-text-main">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="flex-1 overflow-y-auto p-6 md:p-8">
                    {abonos.length === 0 ? (
                        <p className="text-center text-xs theme-text-muted uppercase tracking-widest font-bold py-12">
                            No se han registrado abonos parciales el día de hoy.
                        </p>
                    ) : (
                        <div className="space-y-4">
                            {abonos.map((abono) => {
                                const montoAbonado = Number(abono.monto_anterior) - Number(abono.monto_nuevo);
                                const folio = extraerFolioAbono(abono.descripcion);
                                return (
                                    <div key={abono.id} className="p-4 rounded-2xl border theme-border bg-white dark:bg-zinc-900 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4 transition-transform hover:scale-[1.01]">
                                        <div className="flex items-center gap-4">
                                            <div className="w-10 h-10 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center shrink-0 border border-emerald-200 dark:border-emerald-800">
                                                <ArrowDownRight className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                                            </div>
                                            <div>
                                                <h4 className="font-bold theme-text-main text-sm">
                                                    {abono.cliente?.nombre || 'Cliente Desconocido'}
                                                </h4>
                                                <div className="flex flex-wrap items-center gap-2 mt-1">
                                                    {folio && (
                                                        <>
                                                            <span className="text-[10px] uppercase font-black tracking-widest theme-text-muted">
                                                                Folio {folio}
                                                            </span>
                                                            <span className="text-zinc-300 dark:text-zinc-700">•</span>
                                                        </>
                                                    )}
                                                    <span className="text-[10px] uppercase font-black tracking-widest text-emerald-600 dark:text-emerald-400">
                                                        Abonó {formatoMoneda(montoAbonado)}
                                                    </span>
                                                    <span className="text-zinc-300 dark:text-zinc-700">•</span>
                                                    <span className="text-[10px] theme-text-muted font-bold flex items-center gap-1">
                                                        <Clock className="w-3 h-3" />
                                                        {new Date(abono.created_at).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-3 bg-black/5 dark:bg-white/5 p-3 rounded-xl">
                                            <div className="text-right">
                                                <p className="text-[9px] uppercase font-black theme-text-muted tracking-widest mb-1">Anterior</p>
                                                <p className="text-xs font-bold theme-text-main line-through opacity-70">{formatoMoneda(abono.monto_anterior)}</p>
                                            </div>
                                            <div className="w-px h-6 bg-zinc-200 dark:bg-zinc-800"></div>
                                            <div className="text-right">
                                                <p className="text-[9px] uppercase font-black theme-text-muted tracking-widest mb-1">Actual</p>
                                                <p className="text-xs font-bold text-emerald-600 dark:text-emerald-400">{formatoMoneda(abono.monto_nuevo)}</p>
                                            </div>
                                        </div>
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
