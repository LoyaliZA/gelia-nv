import React, { useState } from 'react';
import { Search, RefreshCw, ChevronDown } from 'lucide-react';
import { THEME_INPUT, THEME_LABEL } from '../../../../utils/geliaTheme';
import {
    BTN_SECONDARY,
    GELIA_SEGMENT_TABS_SCROLL,
    GELIA_SEGMENT_TABS_TRACK,
    TABS_CEDIS,
} from '../../Partials/pedidosBmaStyles';
import GeliaPaginacion from '../../../../Components/GeliaPaginacion';

export default function FiltrosCedis({
    filtros = {},
    tabActiva,
    onTabChange,
    onBuscar,
    onActualizar,
    metricas = {},
    pedidos = null,
    onIrAPagina,
}) {
    const [filtrosAbiertos, setFiltrosAbiertos] = useState(false);

    const conteoTab = (tabId) => {
        const map = {
            TODOS: metricas.total,
            EMPACADOS: metricas.empacados,
            PENDIENTES_ENVIO: metricas.pendientes_envio,
            PENDIENTES_GUIA: metricas.pendientes_guia,
            ENVIADOS: metricas.enviados,
            INCORRECTAS: metricas.incorrectas,
        };
        return map[tabId];
    };

    const tabActual = TABS_CEDIS.find((t) => t.id === tabActiva) || TABS_CEDIS[0];
    const conteoActual = conteoTab(tabActual.id);

    const elegirTab = (id) => {
        onTabChange(id);
        setFiltrosAbiertos(false);
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-col sm:flex-row gap-3 sm:items-end">
                <div className="flex-1 min-w-0">
                    <label htmlFor="cedis-busqueda" className={`${THEME_LABEL} ml-1`}>Buscar</label>
                    <div className="theme-field-with-icon relative mt-1.5">
                        <Search className="theme-field-icon w-4 h-4" aria-hidden />
                        <input
                            id="cedis-busqueda"
                            type="text"
                            defaultValue={filtros.q || ''}
                            onChange={(e) => onBuscar(e.target.value)}
                            placeholder="Folio, cliente o número..."
                            className={`${THEME_INPUT} w-full py-3 text-sm font-bold`}
                        />
                    </div>
                </div>
                <button
                    type="button"
                    onClick={onActualizar}
                    className={`${BTN_SECONDARY} flex items-center justify-center gap-2 outline-none shrink-0 w-full sm:w-auto`}
                >
                    <RefreshCw className="w-4 h-4" /> Actualizar
                </button>
            </div>

            {/* Mobile: filtros contraídos; se expanden al tocar */}
            <div className="md:hidden space-y-2">
                <button
                    type="button"
                    onClick={() => setFiltrosAbiertos((v) => !v)}
                    aria-expanded={filtrosAbiertos}
                    className="w-full flex items-center justify-between gap-2 px-3 py-2.5 rounded-xl border theme-border theme-element outline-none"
                >
                    <span className="min-w-0 text-left">
                        <span className="block text-[9px] font-black uppercase tracking-widest theme-text-muted">Filtro</span>
                        <span className="block text-xs font-black uppercase theme-text-main truncate mt-0.5">
                            {tabActual.label}
                            {conteoActual !== undefined ? ` · ${conteoActual}` : ''}
                        </span>
                    </span>
                    <ChevronDown
                        className={`w-4 h-4 theme-text-muted shrink-0 transition-transform ${filtrosAbiertos ? 'rotate-180' : ''}`}
                        aria-hidden
                    />
                </button>

                {filtrosAbiertos && (
                    <div className="grid grid-cols-2 gap-2" role="tablist" aria-label="Estado de empaque">
                        {TABS_CEDIS.map((tab) => {
                            const conteo = conteoTab(tab.id);
                            const activo = tabActiva === tab.id;
                            return (
                                <button
                                    key={tab.id}
                                    type="button"
                                    role="tab"
                                    aria-selected={activo}
                                    onClick={() => elegirTab(tab.id)}
                                    className={`flex items-center justify-between gap-1 px-3 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-wide outline-none border transition-colors ${
                                        activo
                                            ? 'border-transparent text-white'
                                            : 'theme-border theme-element theme-text-muted'
                                    }`}
                                    style={activo ? { backgroundColor: 'var(--color-primario)' } : undefined}
                                >
                                    <span className="truncate text-left leading-tight">{tab.label}</span>
                                    {conteo !== undefined && (
                                        <span className={`text-[10px] font-black tabular-nums shrink-0 ${activo ? 'opacity-90' : ''}`}>
                                            {conteo}
                                        </span>
                                    )}
                                </button>
                            );
                        })}
                    </div>
                )}
            </div>

            {/* Desktop: segment tabs */}
            <div className={`hidden md:block ${GELIA_SEGMENT_TABS_SCROLL}`}>
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

            {pedidos && (
                <div className="pt-1 border-t theme-border">
                    <GeliaPaginacion
                        paginator={pedidos}
                        onIrAPagina={onIrAPagina}
                        embedded
                        className="!border-0 !p-0 !pt-3"
                    />
                </div>
            )}
        </div>
    );
}
