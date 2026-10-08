import React from 'react';
import { LayoutGrid, Package, Clock, Truck, Send } from 'lucide-react';
import { GELIA_ESTADO_VIVO_TONO } from '../../../../utils/geliaTheme';
import { TABS_DELEGADO } from '../../Partials/pedidosBmaStyles';

const KPI_DELEGADO = [
    { tab: 'TODOS', key: 'total', icon: LayoutGrid, tono: 'neutro' },
    { tab: 'PENDIENTES_GUIA', key: 'pendientes_guia', icon: Package, tono: 'neutro' },
    { tab: 'EN_CEDIS', key: 'pendiente_empaque', icon: Clock, tono: 'aviso' },
    { tab: 'PENDIENTES_ENVIO', key: 'pendientes_envio', icon: Truck, tono: 'info' },
    { tab: 'ENVIADOS', key: 'enviados', icon: Send, tono: 'exito' },
];

export default function MetricasBandejaDelegado({ metricas = {}, tabActiva, onTabChange }) {
    return (
        <div className="gelia-tienda-op-metricas" role="tablist" aria-label="Filtro por fase de guía">
            {KPI_DELEGADO.map(({ tab, key, icon: Icon, tono }) => {
                const tabMeta = TABS_DELEGADO.find((t) => t.id === tab);
                const label = tabMeta?.label || tab;
                const activo = tabActiva === tab;
                const valor = metricas[key] ?? 0;
                const tonoClass = GELIA_ESTADO_VIVO_TONO[tono] || GELIA_ESTADO_VIVO_TONO.neutro;

                return (
                    <button
                        key={tab}
                        type="button"
                        role="tab"
                        aria-selected={activo}
                        className="gelia-tienda-op-metric"
                        data-active={activo}
                        onClick={() => onTabChange(tab)}
                    >
                        <span className="flex items-center gap-1.5 min-w-0">
                            <span
                                className={`gelia-estado-vivo gelia-estado-vivo--compacto inline-flex p-1 rounded-md ${tonoClass}`}
                                aria-hidden
                            >
                                <Icon className="w-3.5 h-3.5" />
                            </span>
                            <span className="gelia-tienda-op-metric__valor tabular-nums">{valor}</span>
                        </span>
                        <span className="gelia-tienda-op-metric__label line-clamp-2">{label}</span>
                    </button>
                );
            })}
        </div>
    );
}
