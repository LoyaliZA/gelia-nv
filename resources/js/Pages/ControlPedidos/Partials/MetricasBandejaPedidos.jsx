import React from 'react';
import {
    LayoutGrid,
    Scale,
    ClipboardCheck,
    Package,
    AlertOctagon,
    AlertTriangle,
} from 'lucide-react';
import { GELIA_ESTADO_VIVO_TONO } from '../../../utils/geliaTheme';
import { TABS_PEDIDOS } from './pedidosBmaStyles';

const KPI_PEDIDOS = [
    { tab: 'PESAJE_PENDIENTE', metricKey: 'pesaje_pendiente', icon: Scale, tono: 'aviso' },
    { tab: 'PENDIENTE_AUXILIAR', metricKey: 'pendiente_auxiliar', icon: ClipboardCheck, tono: 'info' },
    { tab: 'EN_CEDIS', metricKey: 'en_cedis', icon: Package, tono: 'aviso' },
    { tab: 'RECHAZADAS', metricKey: 'rechazadas', icon: AlertOctagon, tono: 'error' },
    { tab: 'OBS_CEDIS', metricKey: 'obs_cedis', icon: AlertTriangle, tono: 'aviso' },
    { tab: 'TODAS', metricKey: 'todas', icon: LayoutGrid, tono: 'neutro' },
];

const CARD_BASE =
    'rounded-xl border text-left cursor-pointer outline-none transition-[border-color,box-shadow,background-color] duration-150 '
    + 'border-[color:var(--theme-border)] bg-[var(--theme-element-bg)] hover:border-[color:color-mix(in_srgb,var(--color-primario)_30%,var(--theme-border))] '
    + 'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primario)]';

const CARD_ACTIVE =
    'border-[color:color-mix(in_srgb,var(--color-primario)_55%,var(--theme-border))] '
    + 'bg-[color:color-mix(in_srgb,var(--color-primario)_8%,var(--theme-element-bg))]';

const CARD_LAYOUT =
    'flex-[0_0_44%] min-w-[9.5rem] max-w-[11rem] snap-start shrink-0 p-3 '
    + 'sm:flex-[0_0_48%] sm:max-w-[12.5rem] '
    + 'md:flex-none md:w-full md:min-w-0 md:max-w-none md:snap-align-none md:p-2 lg:p-2.5';

function TarjetaMetrica({ tab, metricKey, icon: Icon, tono, metricas, tabActiva, onTabChange }) {
    const tabMeta = TABS_PEDIDOS.find((t) => t.id === tab);
    const label = tabMeta?.label || tab;
    const activo = tabActiva === tab;
    const valor = metricas[metricKey] ?? 0;
    const tonoClass = GELIA_ESTADO_VIVO_TONO[tono] || GELIA_ESTADO_VIVO_TONO.neutro;

    return (
        <button
            type="button"
            role="tab"
            aria-selected={activo}
            aria-label={`${label}, ${valor}`}
            data-active={activo ? 'true' : 'false'}
            className={`${CARD_BASE} ${activo ? CARD_ACTIVE : ''} ${CARD_LAYOUT}`}
            onClick={() => onTabChange(tab)}
        >
            <span className="block text-xl md:text-2xl font-bold tabular-nums leading-none theme-text-main">
                {valor}
            </span>
            <span className="mt-1.5 flex items-start gap-1.5 min-w-0">
                <span
                    className={`gelia-estado-vivo gelia-estado-vivo--compacto inline-flex p-0.5 rounded shrink-0 opacity-70 ${tonoClass}`}
                    aria-hidden
                >
                    <Icon className="w-3 h-3" strokeWidth={2.25} />
                </span>
                <span className="text-xs md:text-[0.8125rem] font-semibold leading-snug theme-text-muted text-left">
                    {label}
                </span>
            </span>
        </button>
    );
}

export default function MetricasBandejaPedidos({ metricas = {}, tabActiva, onTabChange }) {
    return (
        <div
            className="gelia-pedidos-bma-metricas min-w-0 flex gap-2 overflow-x-auto overscroll-x-contain snap-x snap-proximity pb-0.5 -mx-0.5 px-0.5 [scrollbar-width:thin] md:overflow-visible md:grid md:grid-cols-3 lg:grid-cols-6 md:gap-2 md:mx-0 md:px-0 md:snap-none"
            role="tablist"
            aria-label="Colas operativas de pedidos"
        >
            {KPI_PEDIDOS.map((kpi) => (
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
