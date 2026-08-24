import React, { useState } from 'react';
import { Search, RefreshCw, ChevronDown, Loader2 } from 'lucide-react';
import { THEME_INPUT, THEME_LABEL } from '../../../../utils/geliaTheme';
import { BTN_SECONDARY, GELIA_SEGMENT_TABS_SCROLL, GELIA_SEGMENT_TABS_TRACK } from '../../Partials/pedidosBmaStyles';
import GeliaPaginacion from '../../../../Components/GeliaPaginacion';

export const TABS_TIENDA = [
    { id: 'PENDIENTES', label: 'Pendientes' },
    { id: 'EN_ATENCION', label: 'En atención' },
    { id: 'CON_INCIDENCIA', label: 'Con incidencia' },
    { id: 'LISTAS_TRASLADO', label: 'Listas para traslado' },
    { id: 'LISTAS_CARATULA', label: 'Listas para carátula' },
    { id: 'EN_TRASLADO', label: 'En traslado' },
    { id: 'RECHAZADAS_CEDIS', label: 'Rechazadas CEDIS' },
    { id: 'RESPONDIDAS_HOY', label: 'Respondidas hoy' },
    { id: 'PENDIENTES_LIBERACION', label: 'Pendientes de liberación' },
];

export default function FiltrosTienda({
    tabActiva,
    busqueda,
    onTabChange,
    onBuscar,
    onActualizar,
    metricas = {},
    tareas = null,
    onIrAPagina,
    buscando = false,
}) {
    const [filtrosAbiertos, setFiltrosAbiertos] = useState(false);

    const conteoTab = (tabId) => {
        const map = {
            PENDIENTES: metricas.pendientes,
            EN_ATENCION: metricas.en_atencion,
            CON_INCIDENCIA: metricas.con_incidencia,
            LISTAS_TRASLADO: metricas.listas_traslado,
            LISTAS_CARATULA: metricas.listas_caratula,
            EN_TRASLADO: metricas.en_traslado,
            RECHAZADAS_CEDIS: metricas.rechazadas_cedis,
            RESPONDIDAS_HOY: metricas.respondidas_hoy,
            PENDIENTES_LIBERACION: metricas.pendientes_liberacion,
        };
        return map[tabId];
    };

    const tabActual = TABS_TIENDA.find((t) => t.id === tabActiva) || TABS_TIENDA[0];
    const conteoActual = conteoTab(tabActual.id);

    const elegirTab = (id) => {
        onTabChange(id);
        setFiltrosAbiertos(false);
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-col sm:flex-row gap-3 sm:items-end">
                <div className="flex-1 min-w-0">
                    <label htmlFor="tienda-busqueda" className={`${THEME_LABEL} ml-1`}>Buscar</label>
                    <div className="theme-field-with-icon relative mt-1.5">
                        <Search className="theme-field-icon w-4 h-4" aria-hidden />
                        <input
                            id="tienda-busqueda"
                            type="text"
                            value={busqueda ?? ''}
                            onChange={(e) => onBuscar(e.target.value)}
                            placeholder="Folio, cliente o número..."
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
                    className={`${BTN_SECONDARY} flex items-center justify-center gap-2 outline-none shrink-0 w-full sm:w-auto min-h-[44px]`}
                >
                    <RefreshCw className="w-4 h-4" /> Actualizar
                </button>
            </div>

            <div className="md:hidden space-y-2">
                <button
                    type="button"
                    onClick={() => setFiltrosAbiertos((v) => !v)}
                    aria-expanded={filtrosAbiertos}
                    className="w-full flex items-center justify-between gap-2 px-3 py-2.5 min-h-[44px] rounded-xl border theme-border theme-element outline-none"
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
                    <div className="grid grid-cols-2 gap-2" role="tablist" aria-label="Estado de preparación">
                        {TABS_TIENDA.map((tab) => {
                            const conteo = conteoTab(tab.id);
                            const activo = tabActiva === tab.id;
                            return (
                                <button
                                    key={tab.id}
                                    type="button"
                                    role="tab"
                                    aria-selected={activo}
                                    onClick={() => elegirTab(tab.id)}
                                    className={`flex items-center justify-between gap-1 px-3 py-3 min-h-[44px] rounded-xl text-[10px] font-black uppercase tracking-wide outline-none border transition-colors ${
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

            <div className={`hidden md:block ${GELIA_SEGMENT_TABS_SCROLL}`}>
                <div className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK} p-1 shadow-sm`} role="tablist" aria-label="Estado de preparación">
                    {TABS_TIENDA.map((tab) => {
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

            {tareas && onIrAPagina && (
                <div className="pt-1 border-t theme-border">
                    <GeliaPaginacion
                        paginator={tareas}
                        onIrAPagina={onIrAPagina}
                        embedded
                        className="!border-0 !p-0 !pt-3"
                    />
                </div>
            )}
        </div>
    );
}
