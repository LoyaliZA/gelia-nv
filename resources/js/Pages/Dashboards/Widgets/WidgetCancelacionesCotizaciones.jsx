import React from 'react';
import { Ban, Clock } from 'lucide-react';
import DashboardAdaptiveWidget from '../../../Components/Dashboard/DashboardAdaptiveWidget';
import DashboardQueueRow from '../../../Components/Dashboard/DashboardQueueRow';
import DashboardWidgetStatus from '../../../Components/Dashboard/DashboardWidgetStatus';
import DashboardWidgetSummaryChip from '../../../Components/Dashboard/DashboardWidgetSummaryChip';
import { estiloEstadoSolicitud } from '../../../Components/Dashboard/dashboardEstadoSolicitud';

export default function WidgetCancelacionesCotizaciones({ ultimas_operativas = [], metricas = {}, variant = 'desktop' }) {
    const pendientes = metricas.pendientes ?? 0;
    const respondidasHoy = metricas.respondidas_hoy ?? 0;
    const incorrectas = metricas.incorrectas ?? 0;
    const hayCola = pendientes > 0 || incorrectas > 0;
    const lista = ultimas_operativas.slice(0, 4);

    const badge = hayCola ? (
        <DashboardWidgetStatus tono="aviso" icon={Clock}>
            {pendientes} pendientes
        </DashboardWidgetStatus>
    ) : (
        <DashboardWidgetStatus tono="exito">Al día</DashboardWidgetStatus>
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
            title="Cancelaciones y cotizaciones"
            icon={Ban}
            href={route('cancelaciones_cotizaciones.index')}
            ctaLabel="Ver solicitudes operativas"
            minimalCount={pendientes}
            minimalCountLabel={hayCola ? 'Pendientes' : 'Al día'}
            badge={badge}
            summary={summary}
        >
            {lista.length > 0 ? (
                <div className="dashboard-queue-list" role="list">
                    {lista.map((sol, index) => {
                        const { icon: StatusIcon, tono } = estiloEstadoSolicitud(sol.estado?.nombre);
                        const esExtra = index >= 2;

                        return (
                            <DashboardQueueRow
                                key={sol.id}
                                extraClass={esExtra ? 'dashboard-widget__item--extra' : ''}
                                estadoIcon={StatusIcon}
                                estadoTono={tono}
                                primary={sol.proceso?.nombre || 'Operativa'}
                                secondary={`FOL-${sol.id} · ${sol.cliente?.nombre || 'Sin cliente'}`}
                                trailingMuted={sol.estado?.nombre}
                            />
                        );
                    })}
                </div>
            ) : (
                <p className="dashboard-widget-empty m-0">Sin solicitudes operativas recientes.</p>
            )}
        </DashboardAdaptiveWidget>
    );
}
