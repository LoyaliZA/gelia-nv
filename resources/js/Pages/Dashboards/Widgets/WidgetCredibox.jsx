import React from 'react';
import { CreditCard, AlertTriangle } from 'lucide-react';
import DashboardAdaptiveWidget from '../../../Components/Dashboard/DashboardAdaptiveWidget';
import { DashboardStatBlock } from '../../../Components/Dashboard/DashboardQueueRow';
import DashboardWidgetStatus from '../../../Components/Dashboard/DashboardWidgetStatus';
import DashboardWidgetSummaryChip from '../../../Components/Dashboard/DashboardWidgetSummaryChip';
import { formatoMoneda } from '../../../utils/formatoMoneda';

export default function WidgetCredibox({ metricas = {}, variant = 'desktop' }) {
    const alertas = metricas.alertas_pendientes ?? 0;
    const saldoVencido = metricas.saldo_vencido ?? 0;
    const hayCola = alertas > 0 || saldoVencido > 0;

    const badge = hayCola ? (
        <DashboardWidgetStatus tono="error" icon={AlertTriangle}>
            {alertas} alertas
        </DashboardWidgetStatus>
    ) : (
        <DashboardWidgetStatus tono="exito">Al día</DashboardWidgetStatus>
    );

    const summary =
        saldoVencido > 0 ? (
            <DashboardWidgetSummaryChip tono="aviso">
                Vencido {formatoMoneda(saldoVencido)}
            </DashboardWidgetSummaryChip>
        ) : null;

    return (
        <DashboardAdaptiveWidget
            variant={variant}
            title="Credibox"
            icon={CreditCard}
            href={route('auto-cobranza.index')}
            ctaLabel="Abrir Credibox"
            minimalCount={alertas}
            minimalCountLabel={hayCola ? 'Alertas' : 'Al día'}
            badge={badge}
            summary={summary}
        >
            <div className="dashboard-stat-grid">
                <DashboardStatBlock label="Alertas abiertas" value={alertas} />
                <DashboardStatBlock
                    label="Saldo vencido"
                    value={formatoMoneda(saldoVencido)}
                    valueClassName={saldoVencido > 0 ? 'theme-text-peligro' : ''}
                />
            </div>
        </DashboardAdaptiveWidget>
    );
}
