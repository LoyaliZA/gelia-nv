import React, { useState } from 'react';
import { Search, RefreshCw, ChevronDown, Loader2 } from 'lucide-react';
import { THEME_INPUT, THEME_LABEL } from '../../../../utils/geliaTheme';
import {
    BTN_SECONDARY,
    GELIA_SEGMENT_TABS_SCROLL,
    TABS_CEDIS,
} from '../../Partials/pedidosBmaStyles';
import GeliaPaginacion from '../../../../Components/GeliaPaginacion';

export default function FiltrosCedis({
    filtros = {},
    tabActiva,
    busqueda,
    onTabChange,
    onBuscar,
    onActualizar,
    metricas = {},
    pedidos = null,
    onIrAPagina,
    buscando = false,
}) {
    const [filtrosAbiertos, setFiltrosAbiertos] = useState(false);

    const conteoTab = (tabId) => {
        const map = {
            TODOS: metricas.total,
            PENDIENTES_PESAJE: metricas.pendientes_pesaje,
            EMPACADOS: metricas.empacados,
            PENDIENTES_ENVIO: metricas.pendientes_envio,
            PENDIENTES_GUIA: metricas.pendientes_guia,
            ENVIADOS: metricas.enviados,
            INCORRECTAS: metricas.incorrectas,
            LIBERACIONES: metricas.liberaciones_pendientes,
        };
        return map[tabId];
    };

    const tabs = [...TABS_CEDIS, { id: 'LIBERACIONES', label: 'Liberaciones' }];
    const tabActual = tabs.find((t) => t.id === tabActiva) || TABS_CEDIS[0];
    const conteoActual = conteoTab(tabActual.id);

    const elegirTab = (id) => {
        onTabChange(id);
        setFiltrosAbiertos(false);
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-row gap-2 items-end">
                <div className="flex-1 min-w-0">
                    <label htmlFor="cedis-busqueda" className={`${THEME_LABEL} ml-1`}>Buscar</label>
                    <div className="theme-field-with-icon relative mt-1.5">
                        <Search className="theme-field-icon w-4 h-4" aria-hidden />
                        <input
                            id="cedis-busqueda"
                            type="search" name="q" enterKeyHint="search"
                            value={busqueda ?? filtros.q ?? ''}
                            onChange={(e) => onBuscar(e.target.value)}
                            placeholder="Folio, cliente o número…"
                            className={`${THEME_INPUT} w-full py-3 text-sm font-bold pr-10`}
                            aria-busy={buscando}
                            autoComplete="off"
                        />
                        {buscando && (
                            <Loader2
                                className="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 animate-spin theme-text-muted"
                                aria-label="Buscando"
                            />
                        )}
                    </div>
                </div>
                <button
                    type="button"
                    onClick={onActualizar}
                    aria-label="Actualizar pedidos"
                    disabled={buscando}
                    className={`${BTN_SECONDARY} flex items-center justify-center gap-2 outline-none shrink-0 !px-3 min-w-[44px] sm:w-auto min-h-[44px]`}
                >
                    <RefreshCw className="w-4 h-4" aria-hidden="true" /> <span className="hidden sm:inline">Actualizar</span>
                </button>
            </div>

            {/* Mobile: filtros contraídos; se expanden al tocar */}
            <div className="md:hidden space-y-2">
                <button
                    type="button"
                    onClick={() => setFiltrosAbiertos((v) => !v)}
                    aria-expanded={filtrosAbiertos}
                    aria-controls="cedis-filtros-estados"
                    className="w-full flex items-center justify-between gap-2 px-3 py-2.5 min-h-[44px] rounded-xl border theme-border theme-element outline-none"
                >
                    <span className="min-w-0 text-left">
                        <span className="block text-xs font-semibold theme-text-muted">Filtro</span>
                        <span className="block text-xs font-semibold theme-text-main truncate mt-0.5">
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
                    <div id="cedis-filtros-estados" className="grid grid-cols-2 gap-2" role="group" aria-label="Estado de empaque">
                        {tabs.map((tab) => {
                            const conteo = conteoTab(tab.id);
                            const activo = tabActiva === tab.id;
                            return (
                                <button
                                    key={tab.id}
                                    type="button"
                                        aria-pressed={activo}
                                    onClick={() => elegirTab(tab.id)}
                                    className={`flex items-center justify-between gap-1 px-3 py-3 min-h-[44px] rounded-xl text-xs font-semibold outline-none border transition-colors ${
                                        activo
                                            ? 'border-[var(--color-primario)] theme-text-main'
                                            : 'theme-border theme-element theme-text-muted'
                                    }`}
                                    style={activo ? { backgroundColor: 'color-mix(in srgb, var(--color-primario) 8%, var(--theme-element-bg))' } : undefined}
                                >
                                    <span className="text-left leading-snug">{tab.label}</span>
                                    {conteo !== undefined && (
                                        <span className={`text-xs font-semibold tabular-nums shrink-0 ${activo ? 'opacity-90' : ''}`}>
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
                <div className="gelia-segment gelia-pedidos-filtros-track p-1" role="group" aria-label="Estado de empaque">
                    {tabs.map((tab) => {
                        const conteo = conteoTab(tab.id);
                        return (
                            <button
                                key={tab.id}
                                type="button"
                                aria-pressed={tabActiva === tab.id}
                                onClick={() => onTabChange(tab.id)}
                                className="gelia-segment-btn whitespace-nowrap gap-1.5"
                                data-active={tabActiva === tab.id}
                            >
                                {tab.label}
                                {conteo !== undefined && (
                                    <span className="text-xs font-semibold px-1.5 py-0.5 rounded-md theme-element border theme-border">
                                        {conteo}
                                    </span>
                                )}
                            </button>
                        );
                    })}
                </div>
            </div>

            <div className="hidden md:flex items-center justify-between gap-2 text-xs theme-text-muted">
                <p className="m-0">Bandeja activa: <strong className="theme-text-main">{tabActual.label}</strong></p>
                {busqueda && <button type="button" onClick={() => onBuscar('')} className="font-semibold theme-text-main underline underline-offset-4">Limpiar búsqueda</button>}
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
