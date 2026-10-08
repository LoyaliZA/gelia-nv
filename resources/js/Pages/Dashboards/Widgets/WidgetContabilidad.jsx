import React from 'react';
import { Calculator } from 'lucide-react';
import DashboardAdaptiveWidget from '../../../Components/Dashboard/DashboardAdaptiveWidget';
import { DashboardStatBlock } from '../../../Components/Dashboard/DashboardQueueRow';
import DashboardWidgetStatus from '../../../Components/Dashboard/DashboardWidgetStatus';
import DashboardWidgetSummaryChip from '../../../Components/Dashboard/DashboardWidgetSummaryChip';
import { formatoMoneda } from '../../../utils/formatoMoneda';
import { contabilidadRoutes } from '../../Contabilidad/contabilidadRoutes';

export default function WidgetContabilidad({ metricas = {}, variant = 'desktop' }) {
    const ventas = metricas.ventas ?? 0;
    const margen = metricas.margen ?? 0;
    const utilidad = metricas.utilidad ?? (metricas.ganancias ?? 0) + (metricas.perdidas ?? 0);

    return (
        <DashboardAdaptiveWidget
            variant={variant}
            title="Contabilidad"
            icon={Calculator}
            href={contabilidadRoutes.index()}
            ctaLabel="Abrir contabilidad"
            minimalCount={null}
            minimalCountLabel=""
            badge={<DashboardWidgetStatus tono="info">Mes actual</DashboardWidgetStatus>}
            summary={
                <DashboardWidgetSummaryChip tono="info">
                    Margen {Number(margen).toFixed(1)}%
                </DashboardWidgetSummaryChip>
            }
        >
            <div className="dashboard-stat-grid">
                <DashboardStatBlock label="Ventas del mes" value={formatoMoneda(ventas)} />
                <DashboardStatBlock
                    label="Utilidad neta"
                    value={formatoMoneda(utilidad)}
                    valueClassName={utilidad >= 0 ? 'theme-text-exito' : 'theme-text-peligro'}
                />
            </div>
        </DashboardAdaptiveWidget>
    );
}
