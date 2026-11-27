import React from 'react';
import { Receipt, Clock, Check } from 'lucide-react';
import DashboardAdaptiveWidget from '../../../Components/Dashboard/DashboardAdaptiveWidget';
import { DashboardStatBlock } from '../../../Components/Dashboard/DashboardQueueRow';
import DashboardWidgetStatus from '../../../Components/Dashboard/DashboardWidgetStatus';
import DashboardWidgetSummaryChip from '../../../Components/Dashboard/DashboardWidgetSummaryChip';
import { DASHBOARD_WIDGET_TONO } from '../../../Components/Dashboard/dashboardModulesCatalog';

export default function WidgetFacturas({ metricas = {}, variant = 'desktop' }) {
    const pendientes = metricas.pendientes ?? 0;
    const respondidasHoy = metricas.respondidas_hoy ?? 0;
    const incorrectas = metricas.incorrectas ?? 0;
    const borradores = metricas.borradores ?? 0;
    const hayCola = pendientes > 0 || incorrectas > 0;

    const badge = hayCola ? (
        <DashboardWidgetStatus tono="aviso" icon={Clock}>
            {pendientes} pendientes
        </DashboardWidgetStatus>
    ) : (
        <DashboardWidgetStatus tono="exito" icon={Check}>Al día</DashboardWidgetStatus>
    );

    const summary =
        respondidasHoy > 0 || incorrectas > 0 ? (
            <div className="flex flex-wrap gap-2">
                {respondidasHoy > 0 && (
                    <DashboardWidgetSummaryChip tono="exito">{respondidasHoy} respondidas hoy</DashboardWidgetSummaryChip>
                )}
                {incorrectas > 0 && (
                    <DashboardWidgetSummaryChip tono="error">{incorrectas} incorrectas</DashboardWidgetSummaryChip>
                )}
            </div>
        ) : null;

    return (
        <DashboardAdaptiveWidget
            variant={variant}
            title="Facturación"
            icon={Receipt}
            tono={DASHBOARD_WIDGET_TONO.facturas}
            href={route('facturas.index')}
            ctaLabel="Ver facturas"
            minimalCount={pendientes}
            minimalCountLabel={hayCola ? 'Pendientes' : 'Al día'}
            badge={badge}
            summary={summary}
        >
            <div className="dashboard-stat-grid dashboard-stat-grid--quad">
                <DashboardStatBlock label="Borradores" value={borradores} />
                <DashboardStatBlock label="Pendientes" value={pendientes} />
                <DashboardStatBlock label="Hoy" value={respondidasHoy} />
                <DashboardStatBlock
                    label="Incorrectas"
                    value={incorrectas}
                    valueClassName={incorrectas > 0 ? 'theme-text-peligro' : ''}
                />
            </div>
        </DashboardAdaptiveWidget>
    );
}
