import React from 'react';
import { Ban, CheckCircle2, AlertOctagon, CheckSquare, Clock } from 'lucide-react';
import DashboardAdaptiveWidget from '../../../Components/Dashboard/DashboardAdaptiveWidget';

export default function WidgetCancelacionesCotizaciones({ ultimas_operativas = [], metricas = {}, variant = 'desktop' }) {
    const obtenerEstiloLive = (nombreEstado) => {
        switch (nombreEstado?.toLowerCase()) {
            case 'respondida':
                return { icon: CheckCircle2, iconColor: 'text-emerald-500', bg: 'bg-emerald-500/10 border-emerald-500/20' };
            case 'incorrecta':
                return { icon: AlertOctagon, iconColor: 'text-red-500', bg: 'bg-red-500/10 border-red-500/20' };
            case 'verificada':
                return { icon: CheckSquare, iconColor: 'text-blue-500', bg: 'bg-blue-500/10 border-blue-500/20' };
            default:
                return { icon: Clock, iconColor: 'text-orange-500', bg: 'bg-orange-500/10 border-orange-500/20' };
        }
    };

    const pendientes = metricas.pendientes ?? 0;
    const respondidasHoy = metricas.respondidas_hoy ?? 0;
    const incorrectas = metricas.incorrectas ?? 0;
    const hayCola = pendientes > 0 || incorrectas > 0;
    const lista = ultimas_operativas.slice(0, 4);

    return (
        <DashboardAdaptiveWidget
            variant={variant}
            title="Cancel. y Cotiz._"
            icon={Ban}
            href={route('cancelaciones_cotizaciones.index')}
            ctaLabel="Ver solicitudes operativas"
            minimalCount={pendientes}
            minimalCountLabel={hayCola ? 'Pendientes' : 'Al día'}
            badge={hayCola ? (
                <span className="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-orange-600 bg-orange-500/10 px-2 py-1 rounded-md border border-orange-500/20 shrink-0">
                    <Clock className="w-3 h-3" />
                    {pendientes} pendientes
                </span>
            ) : (
                <span className="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-emerald-500 bg-emerald-500/10 px-2 py-1 rounded-md border border-emerald-500/20 shrink-0">
                    <span className="w-1.5 h-1.5 bg-emerald-500 rounded-full" />
                    OK
                </span>
            )}
            summary={(
                <div className="flex flex-wrap gap-2">
                    {pendientes > 0 && (
                        <span className="text-[9px] font-black uppercase px-2 py-1 rounded-lg bg-orange-500/10 text-orange-600 border border-orange-500/20">
                            {pendientes} pendientes
                        </span>
                    )}
                    {respondidasHoy > 0 && (
                        <span className="text-[9px] font-black uppercase px-2 py-1 rounded-lg bg-emerald-500/10 text-emerald-600 border border-emerald-500/20">
                            {respondidasHoy} hoy
                        </span>
                    )}
                    {incorrectas > 0 && (
                        <span className="text-[9px] font-black uppercase px-2 py-1 rounded-lg bg-red-500/10 text-red-600 border border-red-500/20">
                            {incorrectas} incorrectas
                        </span>
                    )}
                </div>
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
                                <div className="min-w-0 flex-1">
                                    <p className="text-[10px] font-mono font-black text-orange-500 uppercase tracking-widest m-0 mb-0.5">
                                        FOL-{sol.id}
                                    </p>
                                    <p className="text-xs font-black theme-text-main truncate m-0">
                                        {sol.proceso?.nombre || 'Operativa'}
                                    </p>
                                    <p className="text-[10px] font-bold theme-text-muted truncate m-0">
                                        {sol.cliente?.nombre || 'Sin cliente'}
                                    </p>
                                </div>
                            </div>
                            <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted shrink-0 hidden sm:inline">
                                {sol.estado?.nombre}
                            </span>
                        </div>
                    );
                })
            ) : (
                <p className="text-xs font-bold theme-text-muted italic text-center py-6 m-0">
                    Sin solicitudes operativas recientes.
                </p>
            )}
        </DashboardAdaptiveWidget>
    );
}
