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

const CARD_LAYOUT = 'min-w-0 w-full p-3 md:p-3.5';

function TarjetaMetrica({ tab, metricKey, icon: Icon, tono, metricas, tabActiva, onTabChange }) {
    const tabMeta = TABS_PEDIDOS.find((t) => t.id === tab);
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
            className={`gelia-pedidos-kpi ${CARD_BASE} ${activo ? CARD_ACTIVE : ''} ${CARD_LAYOUT}`}
            onClick={() => onTabChange(tab)}
        >
            <span className="flex items-start gap-1.5 min-w-0">
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
            <span className="mt-3 block text-2xl font-semibold tabular-nums leading-none theme-text-main">{new Intl.NumberFormat('es-MX').format(valor)}</span>
        </button>
    );
}

export default function MetricasBandejaPedidos({ metricas = {}, tabActiva, onTabChange }) {
    return (
        <div
            className="gelia-pedidos-bma-metricas min-w-0 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2"
            role="group"
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
