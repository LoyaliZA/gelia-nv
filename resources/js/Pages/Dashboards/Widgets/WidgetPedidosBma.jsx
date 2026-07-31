import React from 'react';
import { Package, Clock } from 'lucide-react';
import DashboardAdaptiveWidget from '../../../Components/Dashboard/DashboardAdaptiveWidget';

const FASES = [
    { key: 'pendiente_auxiliar', label: 'Auxiliar', color: 'text-amber-600 bg-amber-500/10 border-amber-500/20' },
    { key: 'en_cedis', label: 'CEDIS', color: 'text-blue-600 bg-blue-500/10 border-blue-500/20' },
    { key: 'borradores', label: 'Borradores', color: 'text-slate-500 bg-slate-500/10 border-slate-500/20' },
    { key: 'enviados', label: 'Enviados', color: 'text-emerald-600 bg-emerald-500/10 border-emerald-500/20' },
    { key: 'rechazadas', label: 'Rechazadas', color: 'text-red-600 bg-red-500/10 border-red-500/20' },
];

export default function WidgetPedidosBma({ metricas = {}, variant = 'desktop' }) {
    const atascados = (metricas.pendiente_auxiliar ?? 0) + (metricas.en_cedis ?? 0);
    const total = metricas.todas ?? 0;

    return (
        <DashboardAdaptiveWidget
            variant={variant}
            title="Pedidos_"
            icon={Package}
            iconClassName="text-blue-500"
            href={route('control_pedidos.index')}
            ctaLabel="Ver pedidos"
            minimalCount={atascados}
            minimalCountLabel={atascados > 0 ? 'En cola' : 'Al día'}
            badge={atascados > 0 ? (
                <span className="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-amber-600 bg-amber-500/10 px-2 py-1 rounded-md border border-amber-500/20 shrink-0">
                    <Clock className="w-3 h-3" />
                    {atascados} en cola
                </span>
            ) : (
                <span className="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-emerald-500 bg-emerald-500/10 px-2 py-1 rounded-md border border-emerald-500/20 shrink-0">
                    <span className="w-1.5 h-1.5 bg-emerald-500 rounded-full" />
                    OK
                </span>
            )}
            summary={
                <span className="text-[9px] font-black uppercase px-2 py-1 rounded-lg theme-element border theme-border theme-text-muted">
                    {total} totales
                </span>
            }
        >
            <div className="flex flex-col gap-2">
                {FASES.map(({ key, label, color }) => (
                    <div
                        key={key}
                        className="flex items-center justify-between theme-element border theme-border p-3 rounded-2xl"
                    >
                        <span className={`text-[9px] font-black uppercase px-2 py-1 rounded-lg border ${color}`}>
                            {label}
                        </span>
                        <span className="text-sm font-black italic theme-text-main tabular-nums">
                            {metricas[key] ?? 0}
                        </span>
                    </div>
                ))}
            </div>
        </DashboardAdaptiveWidget>
    );
}
