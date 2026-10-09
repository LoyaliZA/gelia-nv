import React from 'react';
import {
    Clock,
    Sparkles,
    CheckCircle2,
    XCircle,
    LayoutGrid,
} from 'lucide-react';
import { GELIA_ESTADO_VIVO_TONO } from '../../../../utils/geliaTheme';
import { TABS_AUDITORIA_PRINCIPALES } from '../../Partials/pedidosBmaStyles';

const KPI_AUDITORIA = [
    { tab: 'PENDIENTES', metricKey: 'pendientes', icon: Clock, tono: 'aviso' },
    { tab: 'CORREGIDOS', metricKey: 'corregidos', icon: Sparkles, tono: 'info' },
    { tab: 'APROBADOS', metricKey: 'aprobados', icon: CheckCircle2, tono: 'exito' },
    { tab: 'RECHAZADOS', metricKey: 'rechazados', icon: XCircle, tono: 'error' },
    { tab: 'TODAS', metricKey: 'total', icon: LayoutGrid, tono: 'neutro' },
];

const CARD_BASE =
    'rounded-xl border text-left cursor-pointer outline-none transition-[border-color,box-shadow,background-color] duration-150 '
    + 'border-[color:var(--theme-border)] bg-[var(--theme-element-bg)] hover:border-[color:color-mix(in_srgb,var(--color-primario)_30%,var(--theme-border))] '
    + 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primario)]';

const CARD_ACTIVE =
    'border-[color:color-mix(in_srgb,var(--color-primario)_55%,var(--theme-border))] '
    + 'bg-[color:color-mix(in_srgb,var(--color-primario)_8%,var(--theme-element-bg))]';

const CARD_LAYOUT = 'gelia-pedidos-kpi min-w-0 p-3';

function TarjetaMetrica({ tab, metricKey, icon: Icon, tono, metricas, tabActiva, onTabChange }) {
    const tabMeta = TABS_AUDITORIA_PRINCIPALES.find((t) => t.id === tab);
    const label = tabMeta?.label || tab;
    const activo = tabActiva === tab;
    const valor = metricas[metricKey] ?? 0;
    const tonoClass = GELIA_ESTADO_VIVO_TONO[tono] || GELIA_ESTADO_VIVO_TONO.neutro;

    return (
        <button
            type="button"
            aria-pressed={activo}
            aria-label={`${label}, ${valor}`}
            data-active={activo ? 'true' : 'false'}
            className={`${CARD_BASE} ${activo ? CARD_ACTIVE : ''} ${CARD_LAYOUT}`}
            onClick={() => onTabChange(tab)}
        >
            <span className="flex items-center justify-between gap-2 min-w-0">
                <span className="text-xs font-medium theme-text-muted text-left">{label}</span>
                <span className={`gelia-estado-vivo gelia-estado-vivo--compacto inline-flex rounded shrink-0 ${tonoClass}`} aria-hidden="true"><Icon className="w-4 h-4" /></span>
            </span>
            <span className="block mt-2 text-2xl font-semibold tabular-nums theme-text-main">{new Intl.NumberFormat('es-MX').format(valor)}</span>
        </button>
    );
}

export default function MetricasBandejaAuditoria({ metricas = {}, tabActiva, onTabChange }) {
    return (
        <div
            className="gelia-pedidos-bma-metricas grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2 min-w-0"
            role="group"
            aria-label="Bandejas de revisión"
        >
            {KPI_AUDITORIA.map((kpi) => (
                <TarjetaMetrica
                    key={kpi.tab}
                    {...kpi}
                    metricas={metricas}
                    tabActiva={tabActiva}
                    onTabChange={onTabChange}
                />
            ))}
        </div>
    );
}
