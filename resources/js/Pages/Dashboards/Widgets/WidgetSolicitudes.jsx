import React from 'react';
import { FileSignature, CheckCircle2, AlertOctagon, CheckSquare, Clock } from 'lucide-react';
import DashboardAdaptiveWidget from '../../../Components/Dashboard/DashboardAdaptiveWidget';

export default function WidgetSolicitudes({ ultimas_solicitudes = [], variant = 'desktop' }) {
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

    const lista = ultimas_solicitudes.slice(0, 4);

    return (
        <DashboardAdaptiveWidget
            variant={variant}
            title="Solicitudes_"
            icon={FileSignature}
            href={route('solicitudes.index')}
            ctaLabel="Explorar solicitudes"
            minimalCount={lista.length}
            minimalCountLabel="Recientes"
            badge={(
                <span className="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-emerald-500 bg-emerald-500/10 px-2 py-1 rounded-md border border-emerald-500/20 shrink-0">
                    <span className="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse" />
                    Live
                </span>
            )}
        >
            {lista.length > 0 ? (
                lista.map((sol, index) => {
                    const uiEstado = obtenerEstiloLive(sol.estado?.nombre);
                    const StatusIcon = uiEstado.icon;
                    const esExtra = index >= 2;

                    return (
                        <div
                            key={sol.id}
                            className={`flex items-center justify-between gap-2 theme-element border theme-border p-3 sm:p-4 rounded-2xl shadow-sm bg-white/50 dark:bg-zinc-900/50 min-w-0 mb-3 last:mb-0 ${esExtra ? 'dashboard-widget__item--extra' : ''}`}
                        >
                            <div className="flex items-center gap-3 sm:gap-4 overflow-hidden min-w-0 flex-1">
                                <div className={`w-9 h-9 sm:w-10 sm:h-10 shrink-0 rounded-xl border flex items-center justify-center ${uiEstado.bg}`}>
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
        </DashboardAdaptiveWidget>
    );
}
