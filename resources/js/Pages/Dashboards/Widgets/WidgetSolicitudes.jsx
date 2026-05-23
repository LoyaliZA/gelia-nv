import React from 'react';
import { Link } from '@inertiajs/react';
import { FileSignature, CheckCircle2, AlertOctagon, CheckSquare, Clock, ArrowRight } from 'lucide-react';

export default function WidgetSolicitudes({ ultimas_solicitudes = [] }) {
    const obtenerEstiloLive = (nombreEstado) => {
        switch (nombreEstado?.toLowerCase()) {
            case 'respondida':
                return { icon: CheckCircle2, iconColor: 'text-emerald-500', bg: 'bg-emerald-500/10 border-emerald-500/20' };
            case 'incorrecta':
                return { icon: AlertOctagon, iconColor: 'text-red-500', bg: 'bg-red-500/10 border-red-500/20' };
            case 'verificada':
                return { icon: CheckSquare, iconColor: 'text-blue-500', bg: 'bg-blue-500/10 border-blue-500/20' };
            default:
                return { icon: Clock, iconColor: 'text-amber-500', bg: 'bg-amber-500/10 border-amber-500/20' };
        }
    };

    return (
        <div className="h-full w-full min-h-0 flex flex-col overflow-hidden theme-surface border-2 theme-border rounded-[2rem] md:rounded-[2.5rem] p-4 sm:p-6 md:p-8 shadow-sm group transition-all duration-300 hover:border-[var(--color-primario)] relative">
            <div
                className="absolute inset-0 opacity-0 group-hover:opacity-10 transition-opacity duration-500 pointer-events-none rounded-[inherit]"
                style={{ background: 'linear-gradient(180deg, var(--color-primario) 0%, transparent 100%)' }}
            />

            <div className="flex items-center justify-between mb-4 sm:mb-6 shrink-0 gap-2 min-w-0 relative z-10">
                <div className="flex items-center gap-3 min-w-0 flex-1">
                    <FileSignature className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main truncate min-w-0 group-hover:text-[var(--color-primario)] transition-colors">
                        Solicitudes_
                    </h2>
                </div>
                <span className="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-emerald-500 bg-emerald-500/10 px-2 py-1 rounded-md border border-emerald-500/20 shrink-0">
                    <span className="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse" />
                    Live
                </span>
            </div>

            <div className="flex-1 min-h-0 overflow-y-auto overflow-x-hidden space-y-3 sm:space-y-4 relative z-10 custom-scrollbar">
                {ultimas_solicitudes.length > 0 ? (
                    ultimas_solicitudes.slice(0, 4).map((sol) => {
                        const uiEstado = obtenerEstiloLive(sol.estado?.nombre);
                        const StatusIcon = uiEstado.icon;

                        return (
                            <div
                                key={sol.id}
                                className="flex items-center justify-between gap-2 theme-element border theme-border p-3 sm:p-4 rounded-2xl shadow-sm bg-white/50 dark:bg-zinc-900/50 min-w-0"
                            >
                                <div className="flex items-center gap-3 sm:gap-4 overflow-hidden min-w-0 flex-1">
                                    <div
                                        className={`w-9 h-9 sm:w-10 sm:h-10 shrink-0 rounded-xl border flex items-center justify-center ${uiEstado.bg}`}
                                    >
                                        <StatusIcon className={`w-4 h-4 ${uiEstado.iconColor}`} />
                                    </div>
                                    <div className="overflow-hidden min-w-0">
                                        <h4 className="font-black text-[11px] uppercase theme-text-main truncate">
                                            FOL-{sol.id}
                                        </h4>
                                        <p className="text-[10px] font-bold theme-text-muted truncate mt-0.5">
                                            {sol.cliente?.nombre || 'Nuevo Prospecto'}
                                        </p>
                                    </div>
                                </div>
                                <div className="text-right shrink-0 pl-1">
                                    <div className="font-black italic text-[10px] sm:text-[11px] theme-text-main whitespace-nowrap">
                                        ${Number(sol.monto_cotizado || 0).toLocaleString('en-US')}
                                    </div>
                                </div>
                            </div>
                        );
                    })
                ) : (
                    <div className="text-center py-8 italic theme-text-muted text-xs font-bold uppercase tracking-widest opacity-50">
                        Sin actividad reciente_
                    </div>
                )}
            </div>

            <div className="mt-4 sm:mt-6 pt-4 border-t theme-border shrink-0 relative z-10">
                <Link
                    href={route('solicitudes.index')}
                    className="w-full py-3 sm:py-4 rounded-2xl font-black uppercase tracking-widest text-[9px] sm:text-[10px] transition-all duration-300 shadow-sm flex justify-center items-center gap-2 outline-none theme-element border theme-border text-zinc-500 dark:text-zinc-400 group-hover:bg-[var(--color-primario)] group-hover:text-white group-hover:border-[var(--color-primario)] text-center px-2"
                >
                    <span className="truncate">Explorar solicitudes</span>
                    <ArrowRight className="w-4 h-4 shrink-0" />
                </Link>
            </div>
        </div>
    );
}
