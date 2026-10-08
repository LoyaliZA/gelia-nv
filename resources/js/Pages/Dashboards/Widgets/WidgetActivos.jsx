import React from 'react';
import { Package, AlertTriangle, Clock, Wrench } from 'lucide-react';
import DashboardAdaptiveWidget from '../../../Components/Dashboard/DashboardAdaptiveWidget';
import DashboardQueueRow from '../../../Components/Dashboard/DashboardQueueRow';
import DashboardWidgetStatus from '../../../Components/Dashboard/DashboardWidgetStatus';
import DashboardWidgetSummaryChip from '../../../Components/Dashboard/DashboardWidgetSummaryChip';

function iconoAlerta(tipo) {
    if (tipo === 'mantenimiento' || tipo === 'mantenimiento_programado') return Wrench;
    if (tipo === 'vencimiento') return AlertTriangle;
    return Clock;
}

function tonoAlerta(tipo) {
    if (tipo === 'mantenimiento' || tipo === 'mantenimiento_programado') return 'info';
    if (tipo === 'vencimiento') return 'error';
    return 'aviso';
}

export default function WidgetActivos({ alertas_resumen = {}, alertas_destacadas = [], variant = 'desktop' }) {
    const total = (alertas_resumen.vencidos || 0)
        + (alertas_resumen.proximos_7 || 0)
        + (alertas_resumen.mantenimiento || 0);

    const lista = alertas_destacadas.slice(0, 4);

    const badge = total > 0 ? (
        <DashboardWidgetStatus tono="aviso" icon={AlertTriangle}>
            {total} alertas
        </DashboardWidgetStatus>
    ) : (
        <DashboardWidgetStatus tono="exito">Sin alertas</DashboardWidgetStatus>
    );

    const summary =
        total > 0 ? (
            <div className="flex flex-wrap gap-2">
                {alertas_resumen.vencidos > 0 && (
                    <DashboardWidgetSummaryChip tono="error">
                        {alertas_resumen.vencidos} vencidos
                    </DashboardWidgetSummaryChip>
                )}
                {alertas_resumen.proximos_7 > 0 && (
                    <DashboardWidgetSummaryChip tono="aviso">
                        {alertas_resumen.proximos_7} en 7 días
                    </DashboardWidgetSummaryChip>
                )}
            </div>
        ) : null;

    return (
        <DashboardAdaptiveWidget
            variant={variant}
            title="Activos"
            icon={Package}
            href={route('activos.index')}
            ctaLabel="Explorar activos"
            minimalCount={total}
            minimalCountLabel={total > 0 ? 'Alertas' : 'Sin alertas'}
            badge={badge}
            summary={summary}
        >
            {lista.length > 0 ? (
                <div className="dashboard-queue-list" role="list">
                    {lista.map((item, index) => {
                        const Icon = iconoAlerta(item.alerta_tipo);
                        const esExtra = index >= 2;

                        return (
                            <DashboardQueueRow
                                key={`${item.id}-${item.alerta_tipo}`}
                                href={route('activos.show', item.id)}
                                extraClass={esExtra ? 'dashboard-widget__item--extra' : ''}
                                estadoIcon={Icon}
                                estadoTono={tonoAlerta(item.alerta_tipo)}
                                primary={item.folio}
                                secondary={item.nombre}
                                trailingMuted={item.fecha || undefined}
                            />
                        );
                    })}
                </div>
            ) : (
                <p className="dashboard-widget-empty m-0">Sin alertas pendientes.</p>
            )}
        </DashboardAdaptiveWidget>
    );
}
