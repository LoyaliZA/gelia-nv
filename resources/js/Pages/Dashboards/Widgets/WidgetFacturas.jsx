import React from 'react';
import { Receipt, Clock } from 'lucide-react';
import DashboardAdaptiveWidget from '../../../Components/Dashboard/DashboardAdaptiveWidget';

export default function WidgetFacturas({ metricas = {}, variant = 'desktop' }) {
    const pendientes = metricas.pendientes ?? 0;
    const respondidasHoy = metricas.respondidas_hoy ?? 0;
    const incorrectas = metricas.incorrectas ?? 0;
    const borradores = metricas.borradores ?? 0;
    const hayCola = pendientes > 0 || incorrectas > 0;

    return (
        <DashboardAdaptiveWidget
            variant={variant}
            title="Facturación_"
            icon={Receipt}
            iconClassName="text-cyan-500"
            href={route('facturas.index')}
            ctaLabel="Ver facturas"
            minimalCount={pendientes}
            minimalCountLabel={hayCola ? 'Pendientes' : 'Al día'}
            badge={hayCola ? (
                <span className="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-amber-600 bg-amber-500/10 px-2 py-1 rounded-md border border-amber-500/20 shrink-0">
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
                        <span className="text-[9px] font-black uppercase px-2 py-1 rounded-lg bg-amber-500/10 text-amber-600 border border-amber-500/20">
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
            <div className="grid grid-cols-2 gap-2">
                {[
                    { label: 'Borradores', value: borradores },
                    { label: 'Pendientes', value: pendientes },
                    { label: 'Hoy', value: respondidasHoy },
                    { label: 'Incorrectas', value: incorrectas },
                ].map(({ label, value }) => (
                    <div key={label} className="theme-element border theme-border p-3 rounded-2xl">
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">{label}</p>
                        <p className="text-xl font-black italic theme-text-main m-0 mt-1 tabular-nums">{value}</p>
                    </div>
                ))}
            </div>
        </DashboardAdaptiveWidget>
    );
}
