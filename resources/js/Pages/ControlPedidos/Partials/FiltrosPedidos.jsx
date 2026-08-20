import React, { useState } from 'react';
import { Search, RefreshCw, ChevronDown, Loader2 } from 'lucide-react';
import { THEME_INPUT, THEME_LABEL } from '../../../utils/geliaTheme';
import {
    BTN_SECONDARY,
    GELIA_SEGMENT_TABS_SCROLL,
    GELIA_SEGMENT_TABS_TRACK,
    TABS_PEDIDOS,
    TABS_PEDIDOS_PRINCIPALES,
    TABS_PEDIDOS_SUBFILTROS,
    TABS_PEDIDOS_ADMIN,
} from './pedidosBmaStyles';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';

function SegmentoTabs({ tabs, tabActiva, onTabChange, conteoTab, ariaLabel }) {
    return (
        <div className={GELIA_SEGMENT_TABS_SCROLL}>
            <div className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK} p-1 shadow-sm`} role="tablist" aria-label={ariaLabel}>
                {tabs.map((tab) => {
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
    );
}

function GridTabsMovil({ tabs, tabActiva, onElegir, conteoTab }) {
    return (
        <div className="grid grid-cols-2 gap-2">
            {tabs.map((tab) => {
                const conteo = conteoTab(tab.id);
                const activo = tabActiva === tab.id;
                return (
                    <button
                        key={tab.id}
                        type="button"
                        role="tab"
                        aria-selected={activo}
                        onClick={() => onElegir(tab.id)}
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
    );
}

export default function FiltrosPedidos({
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
    can = () => false,
}) {
    const [filtrosAbiertos, setFiltrosAbiertos] = useState(false);

    const conteoTab = (tabId) => {
        const map = {
            TODAS: metricas.todas,
            BORRADORES: metricas.borradores,
            PESAJE_PENDIENTE: metricas.pesaje_pendiente,
            PESAJE_RESPONDIDO: metricas.pesaje_respondido,
            OBS_CEDIS: metricas.obs_cedis,
            SIN_EXISTENCIA: metricas.sin_existencia,
            PENDIENTE_AUXILIAR: metricas.pendiente_auxiliar,
            EN_CEDIS: metricas.en_cedis,
            PENDIENTE_GUIA_CLIENTE: metricas.pendiente_guia_cliente,
            ENVIADOS: metricas.enviados,
            RECHAZADAS: metricas.rechazadas,
            ELIMINADAS: metricas.eliminadas,
        };
        return map[tabId];
    };

    const mostrarEliminadas = can('control_pedidos.eliminados');
    const tabActual = [...TABS_PEDIDOS, ...(mostrarEliminadas ? TABS_PEDIDOS_ADMIN : [])].find((t) => t.id === tabActiva) || TABS_PEDIDOS[0];
    const conteoActual = conteoTab(tabActual.id);
    const esSubfiltro = TABS_PEDIDOS_SUBFILTROS.some((t) => t.id === tabActiva);
    const esPapelera = tabActiva === 'ELIMINADAS';

    const elegirTab = (id) => {
        onTabChange(id);
        setFiltrosAbiertos(false);
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-col sm:flex-row gap-3 sm:items-end">
                <div className="flex-1 min-w-0">
                    <label htmlFor="pedidos-busqueda" className={`${THEME_LABEL} ml-1`}>Buscar</label>
                    <div className="theme-field-with-icon relative mt-1.5">
                        <Search className="theme-field-icon w-4 h-4" aria-hidden />
                        <input
                            id="pedidos-busqueda"
                            type="text"
                            value={busqueda ?? filtros.q ?? ''}
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
                {onActualizar && (
                    <button
                        type="button"
                        onClick={onActualizar}
                        disabled={buscando}
                        className={`${BTN_SECONDARY} flex items-center justify-center gap-2 outline-none shrink-0 w-full sm:w-auto disabled:opacity-60`}
                    >
                        <RefreshCw className={`w-4 h-4 ${buscando ? 'animate-spin' : ''}`} /> Actualizar
                    </button>
                )}
            </div>

            <div className="md:hidden space-y-2">
                <button
                    type="button"
                    onClick={() => setFiltrosAbiertos((v) => !v)}
                    aria-expanded={filtrosAbiertos}
                    className="w-full flex items-center justify-between gap-2 px-3 py-2.5 rounded-xl border theme-border theme-element outline-none"
                >
                    <span className="min-w-0 text-left">
                        <span className="block text-[9px] font-black uppercase tracking-widest theme-text-muted">
                            {esSubfiltro ? 'Subfiltro' : 'Filtro'}
                        </span>
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
                    <div className="space-y-3" role="tablist" aria-label="Filtros de pedidos">
                        <div>
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-2 ml-0.5">
                                Estado
                            </p>
                            <GridTabsMovil
                                tabs={TABS_PEDIDOS_PRINCIPALES}
                                tabActiva={tabActiva}
                                onElegir={elegirTab}
                                conteoTab={conteoTab}
                            />
                        </div>
                        <div>
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-2 ml-0.5">
                                Envío y colas
                            </p>
                            <GridTabsMovil
                                tabs={TABS_PEDIDOS_SUBFILTROS}
                                tabActiva={tabActiva}
                                onElegir={elegirTab}
                                conteoTab={conteoTab}
                            />
                        </div>
                        {mostrarEliminadas && (
                            <div>
                                <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-2 ml-0.5">
                                    Administración
                                </p>
                                <GridTabsMovil
                                    tabs={TABS_PEDIDOS_ADMIN}
                                    tabActiva={tabActiva}
                                    onElegir={elegirTab}
                                    conteoTab={conteoTab}
                                />
                            </div>
                        )}
                    </div>
                )}
            </div>

            <div className="hidden md:block space-y-3">
                <div>
                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1.5 ml-0.5">
                        Estado
                    </p>
                    <SegmentoTabs
                        tabs={TABS_PEDIDOS_PRINCIPALES}
                        tabActiva={tabActiva}
                        onTabChange={onTabChange}
                        conteoTab={conteoTab}
                        ariaLabel="Estado del pedido"
                    />
                </div>
                <div>
                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1.5 ml-0.5">
                        Envío y colas
                    </p>
                    <SegmentoTabs
                        tabs={TABS_PEDIDOS_SUBFILTROS}
                        tabActiva={tabActiva}
                        onTabChange={onTabChange}
                        conteoTab={conteoTab}
                        ariaLabel="Subfiltros de envío y colas"
                    />
                </div>
                {mostrarEliminadas && (
                    <div>
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1.5 ml-0.5">
                            Administración
                        </p>
                        <SegmentoTabs
                            tabs={TABS_PEDIDOS_ADMIN}
                            tabActiva={tabActiva}
                            onTabChange={onTabChange}
                            conteoTab={conteoTab}
                            ariaLabel="Papelera administrativa"
                        />
                    </div>
                )}
            </div>

            {pedidos && onIrAPagina && (
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
