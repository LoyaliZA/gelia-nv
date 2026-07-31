import React from 'react';
import { CreditCard, AlertTriangle } from 'lucide-react';
import DashboardAdaptiveWidget from '../../../Components/Dashboard/DashboardAdaptiveWidget';
import { formatoMoneda } from '../../../utils/formatoMoneda';

export default function WidgetCredibox({ metricas = {}, variant = 'desktop' }) {
    const alertas = metricas.alertas_pendientes ?? 0;
    const saldoVencido = metricas.saldo_vencido ?? 0;
    const hayCola = alertas > 0 || saldoVencido > 0;

    return (
        <DashboardAdaptiveWidget
            variant={variant}
            title="Credibox_"
            icon={CreditCard}
            iconClassName="text-red-500"
            href={route('auto-cobranza.index')}
            ctaLabel="Abrir Credibox"
            minimalCount={alertas}
            minimalCountLabel={hayCola ? 'Alertas' : 'Al día'}
            badge={hayCola ? (
                <span className="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-red-600 bg-red-500/10 px-2 py-1 rounded-md border border-red-500/20 shrink-0">
                    <AlertTriangle className="w-3 h-3" />
                    {alertas} alertas
                </span>
            ) : (
                <span className="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-emerald-500 bg-emerald-500/10 px-2 py-1 rounded-md border border-emerald-500/20 shrink-0">
                    <span className="w-1.5 h-1.5 bg-emerald-500 rounded-full" />
                    OK
                </span>
            )}
            summary={hayCola ? (
                <div className="flex flex-wrap gap-2">
                    {alertas > 0 && (
                        <span className="text-[9px] font-black uppercase px-2 py-1 rounded-lg bg-red-500/10 text-red-600 border border-red-500/20">
                            {alertas} pendientes
                        </span>
                    )}
                    {saldoVencido > 0 && (
                        <span className="text-[9px] font-black uppercase px-2 py-1 rounded-lg bg-amber-500/10 text-amber-600 border border-amber-500/20">
                            Vencido {formatoMoneda(saldoVencido)}
                        </span>
                    )}
                </div>
            ) : null}
        >
            <div className="space-y-3">
                <div className="theme-element border theme-border p-4 rounded-2xl">
                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Alertas abiertas</p>
                    <p className="text-2xl font-black italic theme-text-main m-0 mt-1 tabular-nums">{alertas}</p>
                </div>
                <div className="theme-element border theme-border p-4 rounded-2xl">
                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Saldo vencido</p>
                    <p className="text-xl font-black italic text-red-500 m-0 mt-1 tabular-nums">{formatoMoneda(saldoVencido)}</p>
                </div>
            </div>
        </DashboardAdaptiveWidget>
    );
}
