import React from 'react';
import { KPI_METRICAS_TIENDA } from './tiendaUi';
import { GELIA_ESTADO_VIVO_TONO } from '../../../../utils/geliaTheme';

export default function MetricasBandejaTienda({ metricas = {}, tabActiva, onTabChange }) {
    return (
        <div className="gelia-tienda-op-metricas" role="group" aria-label="Métricas por bandeja">
            {KPI_METRICAS_TIENDA.map(({ key, label, tab, icon: Icon, tono }) => {
                const activo = tabActiva === tab;
                const valor = metricas[key] ?? 0;
                const tonoClass = GELIA_ESTADO_VIVO_TONO[tono] || GELIA_ESTADO_VIVO_TONO.neutro;
                return (
                    <button
                        key={key}
                        type="button"
                        className={`gelia-tienda-op-metric ${activo ? '' : ''}`}
                        data-active={activo}
                        aria-pressed={activo}
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
