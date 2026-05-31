import React from 'react';
import { Link } from '@inertiajs/react';
import { Package, AlertTriangle, Clock, Wrench } from 'lucide-react';
import DashboardAdaptiveWidget from '../../../Components/Dashboard/DashboardAdaptiveWidget';

export default function WidgetActivos({ alertas_resumen = {}, alertas_destacadas = [], variant = 'desktop' }) {
    const total = (alertas_resumen.vencidos || 0)
        + (alertas_resumen.proximos_7 || 0)
        + (alertas_resumen.mantenimiento || 0);

    const iconoAlerta = (tipo) => {
        if (tipo === 'mantenimiento' || tipo === 'mantenimiento_programado') return Wrench;
        if (tipo === 'vencimiento') return AlertTriangle;
        return Clock;
    };

    const colorAlerta = (tipo) => {
        if (tipo === 'mantenimiento' || tipo === 'mantenimiento_programado') return 'text-blue-500 bg-blue-500/10 border-blue-500/20';
        if (tipo === 'vencimiento') return 'text-red-500 bg-red-500/10 border-red-500/20';
        return 'text-amber-500 bg-amber-500/10 border-amber-500/20';
    };

    const lista = alertas_destacadas.slice(0, 4);

    return (
        <DashboardAdaptiveWidget
            variant={variant}
            title="Activos_"
            icon={Package}
            iconClassName="text-emerald-500"
            href={route('activos.index')}
            ctaLabel="Explorar activos"
            minimalCount={total}
            minimalCountLabel={total > 0 ? 'Alertas' : 'Sin alertas'}
            badge={total > 0 ? (
                <span className="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-amber-600 bg-amber-500/10 px-2 py-1 rounded-md border border-amber-500/20 shrink-0">
                    <AlertTriangle className="w-3 h-3" />
                    {total} alertas
                </span>
            ) : (
                <span className="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-emerald-500 bg-emerald-500/10 px-2 py-1 rounded-md border border-emerald-500/20 shrink-0">
                    <span className="w-1.5 h-1.5 bg-emerald-500 rounded-full" />
                    OK
                </span>
            )}
            summary={total > 0 ? (
                <>
                    {alertas_resumen.vencidos > 0 && (
                        <span className="text-[9px] font-black uppercase px-2 py-1 rounded-lg bg-red-500/10 text-red-600 border border-red-500/20">
                            {alertas_resumen.vencidos} vencidos
                        </span>
                    )}
                    {alertas_resumen.proximos_7 > 0 && (
                        <span className="text-[9px] font-black uppercase px-2 py-1 rounded-lg bg-amber-500/10 text-amber-600 border border-amber-500/20">
                            {alertas_resumen.proximos_7} · 7 días
                        </span>
                    )}
                </>
            ) : null}
        >
            {lista.length > 0 ? (
                lista.map((item, index) => {
                    const Icon = iconoAlerta(item.alerta_tipo);
                    const colors = colorAlerta(item.alerta_tipo);
                    const esExtra = index >= 2;

                    return (
                        <Link
                            key={`${item.id}-${item.alerta_tipo}`}
                            href={route('activos.show', item.id)}
                            className={`flex items-center justify-between gap-2 theme-element border theme-border p-3 sm:p-4 rounded-2xl shadow-sm bg-white/50 dark:bg-zinc-900/50 min-w-0 mb-3 last:mb-0 hover:border-[var(--color-primario)] transition-colors outline-none ${esExtra ? 'dashboard-widget__item--extra' : ''}`}
                        >
                            <div className="flex items-center gap-3 sm:gap-4 overflow-hidden min-w-0 flex-1">
                                <div className={`w-9 h-9 sm:w-10 sm:h-10 shrink-0 rounded-xl border flex items-center justify-center ${colors}`}>
                                    <Icon className="w-4 h-4" />
                                </div>
                                <div className="overflow-hidden min-w-0">
                                    <h4 className="font-black text-[11px] uppercase theme-text-main truncate">
                                        {item.folio}
                                    </h4>
                                    <p className="text-[10px] font-bold theme-text-muted truncate mt-0.5">
                                        {item.nombre}
                                    </p>
                                </div>
                            </div>
                            {item.fecha && (
                                <span className="text-[9px] font-bold theme-text-muted shrink-0">
                                    {item.fecha}
                                </span>
                            )}
                        </Link>
                    );
                })
            ) : (
                <div className="text-center py-8 italic theme-text-muted text-xs font-bold uppercase tracking-widest opacity-50">
                    Sin alertas pendientes_
                </div>
            )}
        </DashboardAdaptiveWidget>
    );
}
