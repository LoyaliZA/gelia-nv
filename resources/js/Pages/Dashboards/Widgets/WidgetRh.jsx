import React from 'react';
import { Briefcase, Clock, AlertTriangle } from 'lucide-react';
import DashboardAdaptiveWidget from '../../../Components/Dashboard/DashboardAdaptiveWidget';
import DashboardQueueRow from '../../../Components/Dashboard/DashboardQueueRow';
import DashboardWidgetStatus from '../../../Components/Dashboard/DashboardWidgetStatus';
import DashboardWidgetSummaryChip from '../../../Components/Dashboard/DashboardWidgetSummaryChip';
import { formatoDeduccionEntera, formatoMoneda, nombreCompletoColaborador } from '../../../utils/formatoMoneda';

export default function WidgetRh({ rh_widget = {}, variant = 'desktop' }) {
    const pendientesHe = rh_widget.pendientes_he ?? rh_widget.pendientes ?? 0;
    const montoPendienteHe = rh_widget.monto_pendiente_he ?? rh_widget.monto_pendiente ?? 0;
    const pendientesInc = rh_widget.pendientes_incidencias ?? 0;
    const montoDeduccionInc = rh_widget.monto_deduccion_incidencias ?? 0;
    const destacadosHe = rh_widget.destacados_he ?? rh_widget.destacados ?? [];
    const destacadosInc = rh_widget.destacados_incidencias ?? [];

    const totalPendientes = pendientesHe + pendientesInc;
    const hayPendientes = totalPendientes > 0;

    const badge = hayPendientes ? (
        <DashboardWidgetStatus tono="aviso" icon={Clock}>
            {totalPendientes} pendientes
        </DashboardWidgetStatus>
    ) : (
        <DashboardWidgetStatus tono="exito">Al día</DashboardWidgetStatus>
    );

    const summary = hayPendientes ? (
        <div className="flex flex-wrap gap-2">
            {pendientesHe > 0 && (
                <DashboardWidgetSummaryChip tono="info">
                    HE {formatoMoneda(montoPendienteHe)}
                </DashboardWidgetSummaryChip>
            )}
            {pendientesInc > 0 && (
                <DashboardWidgetSummaryChip tono="aviso">
                    Ded. {formatoDeduccionEntera(montoDeduccionInc)}
                </DashboardWidgetSummaryChip>
            )}
        </div>
    ) : null;

    return (
        <DashboardAdaptiveWidget
            variant={variant}
            title="Recursos humanos"
            icon={Briefcase}
            href={route('rh.index')}
            ctaLabel="Abrir módulo RH"
            minimalCount={totalPendientes}
            minimalCountLabel={hayPendientes ? 'Pendientes' : 'Al día'}
            badge={badge}
            summary={summary}
        >
            {destacadosHe.length === 0 && destacadosInc.length === 0 ? (
                <p className="dashboard-widget-empty m-0">Sin pendientes de HE ni incidencias.</p>
            ) : (
                <div className="dashboard-queue-list" role="list">
                    {destacadosHe.map((reg) => (
                        <DashboardQueueRow
                            key={`he-${reg.id}`}
                            href={route('rh.horas_extra.show', reg.id)}
                            estadoIcon={Clock}
                            estadoTono="info"
                            primary={reg.folio}
                            secondary={nombreCompletoColaborador(reg.colaborador)}
                            trailing={formatoMoneda(reg.total_economico)}
                            trailingMuted="Horas extra"
                        />
                    ))}
                    {destacadosInc.map((reg) => (
                        <DashboardQueueRow
                            key={`inc-${reg.id}`}
                            href={route('rh.deducciones.show', reg.id)}
                            estadoIcon={AlertTriangle}
                            estadoTono="aviso"
                            primary={reg.folio}
                            secondary={nombreCompletoColaborador(reg.colaborador)}
                            trailing={formatoDeduccionEntera(reg.total_deduccion)}
                            trailingMuted="Incidencia"
                        />
                    ))}
                </div>
            )}
        </DashboardAdaptiveWidget>
    );
}
