import React from 'react';
import { Search } from 'lucide-react';
import { THEME_INPUT, THEME_LABEL } from '../../../utils/geliaTheme';
import { GELIA_SEGMENT_TABS_SCROLL, GELIA_SEGMENT_TABS_TRACK, TABS_PEDIDOS } from './pedidosBmaStyles';

export default function FiltrosPedidos({ filtros = {}, tabActiva, onTabChange, onBuscar, metricas = {} }) {
    const conteoTab = (tabId) => {
        const map = {
            TODAS: metricas.todas,
            BORRADORES: metricas.borradores,
            PENDIENTE_AUXILIAR: metricas.pendiente_auxiliar,
            EN_CEDIS: metricas.en_cedis,
            ENVIADOS: metricas.enviados,
            RECHAZADAS: metricas.rechazadas,
        };
        return map[tabId];
    };

    return (
        <div className="space-y-4">
            <div>
                <label htmlFor="pedidos-busqueda" className={`${THEME_LABEL} ml-1`}>Buscar</label>
                <div className="theme-field-with-icon relative mt-1.5">
                    <Search className="theme-field-icon w-4 h-4" aria-hidden />
                    <input
                        id="pedidos-busqueda"
                        type="text"
                        defaultValue={filtros.q || ''}
                        onChange={(e) => onBuscar(e.target.value)}
                        placeholder="Buscar folio o cliente..."
                        className={`${THEME_INPUT} w-full py-3.5 text-sm font-bold`}
                    />
                </div>
            </div>
            <div className={GELIA_SEGMENT_TABS_SCROLL}>
                <div className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK} p-1 shadow-sm`} role="tablist" aria-label="Estado de pedidos">
                    {TABS_PEDIDOS.map((tab) => {
                        const conteo = conteoTab(tab.id);
                        return (
                            <button
                                key={tab.id}
                                type="button"
                                role="tab"
                                aria-selected={tabActiva === tab.id}
                                onClick={() => onTabChange(tab.id)}
                                className="gelia-segment-btn whitespace-nowrap gap-1.5"
                                data-active={tabActiva === tab.id}
                            >
                                {tab.label}
                                {conteo !== undefined && (
                                    <span className="text-[9px] font-black px-1.5 py-0.5 rounded-md theme-element border theme-border">
                                        {conteo}
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
