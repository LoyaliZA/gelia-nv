import React from 'react';
import { Package, Clock } from 'lucide-react';
import DashboardAdaptiveWidget from '../../../Components/Dashboard/DashboardAdaptiveWidget';
import DashboardWidgetStatus from '../../../Components/Dashboard/DashboardWidgetStatus';
import DashboardWidgetSummaryChip from '../../../Components/Dashboard/DashboardWidgetSummaryChip';
import { GELIA_ESTADO_VIVO_TONO } from '../../../utils/geliaTheme';

const FASES = [
    { key: 'pendiente_auxiliar', label: 'Auxiliar', tono: 'aviso' },
    { key: 'en_cedis', label: 'CEDIS', tono: 'info' },
    { key: 'borradores', label: 'Borradores', tono: 'neutro' },
    { key: 'enviados', label: 'Enviados', tono: 'exito' },
    { key: 'rechazadas', label: 'Rechazadas', tono: 'error' },
];

export default function WidgetPedidosBma({ metricas = {}, variant = 'desktop' }) {
    const atascados = (metricas.pendiente_auxiliar ?? 0) + (metricas.en_cedis ?? 0);
    const total = metricas.todas ?? 0;

    const badge = atascados > 0 ? (
        <DashboardWidgetStatus tono="aviso" icon={Clock}>
            {atascados} en cola
        </DashboardWidgetStatus>
    ) : (
        <DashboardWidgetStatus tono="exito">Al día</DashboardWidgetStatus>
    );

    return (
        <DashboardAdaptiveWidget
            variant={variant}
            title="Pedidos"
            icon={Package}
            href={route('control_pedidos.index')}
            ctaLabel="Ver pedidos"
            minimalCount={atascados}
            minimalCountLabel={atascados > 0 ? 'En cola' : 'Al día'}
            badge={badge}
            summary={<DashboardWidgetSummaryChip tono="neutro">{total} totales</DashboardWidgetSummaryChip>}
        >
            <div className="dashboard-phase-table" role="table" aria-label="Pedidos por fase">
                {FASES.map(({ key, label, tono }) => (
                    <div key={key} className="dashboard-phase-table__row" role="row">
                        <span
                            className={`gelia-estado-vivo gelia-estado-vivo--compacto text-[10px] font-semibold ${GELIA_ESTADO_VIVO_TONO[tono]}`}
                            role="cell"
                        >
                            {label}
                        </span>
                        <span className="dashboard-phase-table__value tabular-nums theme-text-main" role="cell">
                            {metricas[key] ?? 0}
                        </span>
                    </div>
                ))}
            </div>
        </DashboardAdaptiveWidget>
    );
}
