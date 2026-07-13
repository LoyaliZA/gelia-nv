import React from 'react';
import { Search, RefreshCw } from 'lucide-react';
import { THEME_INPUT, THEME_LABEL } from '../../../../utils/geliaTheme';
import { BTN_SECONDARY, GELIA_SEGMENT_TABS_SCROLL, GELIA_SEGMENT_TABS_TRACK, TABS_CEDIS } from '../../Partials/pedidosBmaStyles';

export default function FiltrosCedis({ filtros = {}, tabActiva, onTabChange, onBuscar, onActualizar, metricas = {} }) {
    const conteoTab = (tabId) => {
        const map = {
            PENDIENTES: metricas.pendientes,
            INCIDENCIAS: metricas.incidencias,
            EMPACADOS: metricas.empacados,
            TODOS: metricas.total,
        };
        return map[tabId];
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-col sm:flex-row gap-3 sm:items-end">
                <div className="flex-1">
                    <label htmlFor="cedis-busqueda" className={`${THEME_LABEL} ml-1`}>Buscar</label>
                    <div className="theme-field-with-icon relative mt-1.5">
                        <Search className="theme-field-icon w-4 h-4" aria-hidden />
                        <input
                            id="cedis-busqueda"
                            type="text"
                            defaultValue={filtros.q || ''}
                            onChange={(e) => onBuscar(e.target.value)}
                            placeholder="Buscar folio, cliente o número..."
                            className={`${THEME_INPUT} w-full py-3.5 text-sm font-bold`}
                        />
                    </div>
                </div>
                <button type="button" onClick={onActualizar} className={`${BTN_SECONDARY} flex items-center gap-2 outline-none shrink-0`}>
                    <RefreshCw className="w-4 h-4" /> Actualizar
                </button>
            </div>
            <div className={GELIA_SEGMENT_TABS_SCROLL}>
                <div className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK} p-1 shadow-sm`} role="tablist" aria-label="Estado de empaque">
                    {TABS_CEDIS.map((tab) => {
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
