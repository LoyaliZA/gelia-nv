import React, { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { AlertTriangle, X } from 'lucide-react';
import ListadoAlertasActivos, { totalAlertasResumen } from './ListadoAlertasActivos';

export default function DrawerAlertasActivos({ abierto, onCerrar, alertas, alertasResumen = {} }) {
    const total = totalAlertasResumen(alertasResumen);

    useEffect(() => {
        if (abierto) document.body.style.overflow = 'hidden';
        else document.body.style.overflow = 'unset';
        return () => { document.body.style.overflow = 'unset'; };
    }, [abierto]);

    if (!abierto) return null;

    return createPortal(
        <div className="fixed inset-0 z-[9999] flex justify-end">
            <div
                className="absolute inset-0 bg-black/60 backdrop-blur-sm animate-fade-in"
                onClick={onCerrar}
                aria-hidden
            />
            <div
                className="relative w-full max-w-md theme-surface border-l theme-border shadow-2xl h-full flex flex-col animate-slide-in-right"
                role="dialog"
                aria-modal="true"
                aria-labelledby="drawer-alertas-activos-titulo"
            >
                <div className="p-6 md:p-8 border-b theme-border flex justify-between items-center bg-black/5 dark:bg-white/5 shrink-0">
                    <div className="flex items-center gap-3">
                        <div
                            className="p-2 rounded-xl"
                            style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}
                        >
                            <AlertTriangle className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                        </div>
                        <div>
                            <h3 id="drawer-alertas-activos-titulo" className="text-lg font-black uppercase tracking-tighter theme-text-main italic m-0">
                                Alertas activas
                            </h3>
                            <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-0.5">
                                {total > 0 ? `${total} por revisar` : 'Sin novedades'}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onCerrar}
                        className="p-2 theme-text-muted hover:theme-text-main bg-white/10 rounded-full transition-transform hover:scale-110 outline-none"
                        aria-label="Cerrar alertas"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="flex-1 overflow-y-auto custom-scrollbar p-4 md:p-6">
                    <ListadoAlertasActivos alertas={alertas} compacto />
                </div>
            </div>
        </div>,
        document.body,
    );
}
