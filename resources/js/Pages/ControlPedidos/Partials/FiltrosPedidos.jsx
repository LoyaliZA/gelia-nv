import React, { useMemo, useState } from 'react';
import { Search, RefreshCw, SlidersHorizontal, Loader2 } from 'lucide-react';
import { THEME_INPUT, THEME_LABEL, THEME_SELECT } from '../../../utils/geliaTheme';
import {
    BTN_SECONDARY,
    TABS_PEDIDOS,
    TABS_PEDIDOS_PRINCIPALES,
    TABS_PEDIDOS_SUBFILTROS,
    TABS_PEDIDOS_ADMIN,
    esTabPedidoEnMetricasKpi,
} from './pedidosBmaStyles';

const BTN_TOOLBAR_MOVIL =
    'inline-flex items-center justify-center rounded-xl border theme-border theme-element shrink-0 '
    + 'min-h-[44px] min-w-[44px] px-2.5 theme-text-main hover:bg-black/5 dark:hover:bg-white/5 '
    + 'outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primario)] '
    + 'disabled:opacity-60';

function useConteoTab(metricas) {
    return (tabId) => {
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
}

function tabsFueraDeMetricas(tabs) {
    return tabs.filter((t) => !esTabPedidoEnMetricasKpi(t.id));
}

function ListaBandejasMovil({ tabs, tabActiva, onElegir, conteoTab }) {
    if (tabs.length === 0) return null;

    return (
        <div className="grid grid-cols-1 gap-1.5">
            {tabs.map((tab) => {
                const conteo = conteoTab(tab.id);
                const activo = tabActiva === tab.id;
                return (
                    <button
                        key={tab.id}
                        type="button"
                        aria-selected={activo}
                        onClick={() => onElegir(tab.id)}
                        className={`flex items-center justify-between gap-2 px-3 py-2.5 rounded-lg text-left text-sm font-semibold outline-none border transition-colors ${
                            activo
                                ? 'border-[color:color-mix(in_srgb,var(--color-primario)_50%,var(--theme-border))] bg-[color:color-mix(in_srgb,var(--color-primario)_10%,var(--theme-element-bg))] theme-text-main'
                                : 'theme-border theme-element theme-text-muted hover:theme-text-main'
                        }`}
                    >
                        <span className="min-w-0 leading-snug pr-1">{tab.label}</span>
                        {conteo !== undefined && (
                            <span className="text-xs font-bold tabular-nums shrink-0">{conteo}</span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}

function IndicadorBandejaActiva({ tabActual, conteoActual, className = '', compact = false }) {
    if (compact) {
        return (
            <p className={`m-0 text-xs font-semibold theme-text-muted truncate ${className}`.trim()}>
                <span className="text-xs font-medium theme-text-muted mr-1">Bandeja</span>
                <span className="theme-text-main">{tabActual.label}</span>
                {conteoActual !== undefined && (
                    <span className="tabular-nums font-bold"> · {conteoActual}</span>
                )}
            </p>
        );
    }

    return (
        <p className={`m-0 text-xs font-medium theme-text-muted ${className}`.trim()}>
            <span className="text-xs font-medium mr-1.5">Bandeja activa</span>
            <span className="theme-text-main font-bold">{tabActual.label}</span>
            {conteoActual !== undefined && (
                <span className="tabular-nums font-bold"> · {conteoActual}</span>
            )}
        </p>
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
    buscando = false,
    can = () => false,
}) {
    const [filtrosAbiertos, setFiltrosAbiertos] = useState(false);
    const conteoTab = useConteoTab(metricas);

    const mostrarEliminadas = can('control_pedidos.eliminados');

    const tabActual = [...TABS_PEDIDOS, ...(mostrarEliminadas ? TABS_PEDIDOS_ADMIN : [])].find((t) => t.id === tabActiva)
        || TABS_PEDIDOS[0];
    const conteoActual = conteoTab(tabActual.id);

    const bandejasExtraEscritorio = useMemo(() => {
        const grupos = [
            { key: 'estado', label: 'Estado', tabs: tabsFueraDeMetricas(TABS_PEDIDOS_PRINCIPALES) },
            { key: 'envio', label: 'Envío y colas', tabs: tabsFueraDeMetricas(TABS_PEDIDOS_SUBFILTROS) },
        ];
        if (mostrarEliminadas) {
            grupos.push({ key: 'admin', label: 'Administración', tabs: TABS_PEDIDOS_ADMIN });
        }
        return grupos.filter((g) => g.tabs.length > 0);
    }, [mostrarEliminadas]);

    const gruposPanelMovil = useMemo(() => {
        const grupos = [
            { key: 'estado', label: 'Estado', tabs: TABS_PEDIDOS_PRINCIPALES },
            { key: 'envio', label: 'Envío y colas', tabs: TABS_PEDIDOS_SUBFILTROS },
        ];
        if (mostrarEliminadas) {
            grupos.push({ key: 'admin', label: 'Administración', tabs: TABS_PEDIDOS_ADMIN });
        }
        return grupos;
    }, [mostrarEliminadas]);

    const todasBandejasExtra = useMemo(
        () => bandejasExtraEscritorio.flatMap((g) => g.tabs),
        [bandejasExtraEscritorio],
    );

    const valorSelectorExtra = todasBandejasExtra.some((t) => t.id === tabActiva) ? tabActiva : '';

    const elegirTab = (id) => {
        onTabChange(id);
        setFiltrosAbiertos(false);
    };

    const onSelectorExtra = (e) => {
        const id = e.target.value;
        if (id) onTabChange(id);
    };

    return (
        <div className="space-y-2.5 md:space-y-3">
            {/* Escritorio */}
            <div className="hidden md:flex flex-wrap items-end gap-2 lg:gap-3">
                <div className="flex-1 min-w-[14rem]">
                    <label htmlFor="pedidos-busqueda" className={`${THEME_LABEL} ml-0.5`}>Buscar</label>
                    <div className="theme-field-with-icon relative mt-1">
                        <Search className="theme-field-icon w-4 h-4" aria-hidden />
                        <input
                            id="pedidos-busqueda"
                            type="search"
                            value={busqueda ?? filtros.q ?? ''}
                            onChange={(e) => onBuscar(e.target.value)}
                            placeholder="Folio, cliente o número..."
                            className={`${THEME_INPUT} w-full py-2.5 text-sm font-semibold pr-10`}
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

                <div className="w-full sm:w-auto min-w-[12rem] sm:max-w-[16rem] flex-1 sm:flex-none">
                    <label htmlFor="pedidos-bandeja-extra" className={`${THEME_LABEL} ml-0.5`}>
                        Otras bandejas
                    </label>
                    <select
                        id="pedidos-bandeja-extra"
                        value={valorSelectorExtra}
                        onChange={onSelectorExtra}
                        className={`${THEME_SELECT} mt-1 w-full py-2.5 text-sm font-semibold`}
                    >
                        <option value="">
                            {esTabPedidoEnMetricasKpi(tabActiva)
                                ? 'Elegir bandeja adicional…'
                                : 'Seleccionar bandeja…'}
                        </option>
                        {bandejasExtraEscritorio.map((grupo) => (
                            <optgroup key={grupo.key} label={grupo.label}>
                                {grupo.tabs.map((tab) => {
                                    const conteo = conteoTab(tab.id);
                                    const suffix = conteo !== undefined ? ` (${conteo})` : '';
                                    return (
                                        <option key={tab.id} value={tab.id}>
                                            {tab.label}{suffix}
                                        </option>
                                    );
                                })}
                            </optgroup>
                        ))}
                    </select>
                </div>

                {onActualizar && (
                    <button
                        type="button"
                        onClick={onActualizar}
                        disabled={buscando}
                        className={`${BTN_SECONDARY} !min-h-0 !py-2.5 !px-3 flex items-center justify-center gap-2 outline-none shrink-0 disabled:opacity-60`}
                    >
                        <RefreshCw className={`w-4 h-4 ${buscando ? 'animate-spin' : ''}`} aria-hidden />
                        Actualizar
                    </button>
                )}
            </div>

            <div className="hidden md:block">
                <IndicadorBandejaActiva tabActual={tabActual} conteoActual={conteoActual} />
            </div>

            {/* Móvil */}
            <div className="md:hidden space-y-2 min-w-0">
                <div className="flex items-center gap-1.5 min-w-0">
                    <div className="theme-field-with-icon relative flex-1 min-w-0">
                        <Search className="theme-field-icon w-4 h-4" aria-hidden />
                        <input
                            id="pedidos-busqueda-movil"
                            type="search"
                            value={busqueda ?? filtros.q ?? ''}
                            onChange={(e) => onBuscar(e.target.value)}
                            placeholder="Folio o cliente…"
                            aria-label="Buscar folio, cliente o número"
                            className={`${THEME_INPUT} w-full min-w-0 py-2.5 text-sm font-semibold pl-9 pr-9`}
                            aria-busy={buscando}
                            autoComplete="off"
                        />
                        {buscando && (
                            <Loader2
                                className="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 animate-spin theme-text-muted"
                                aria-label="Buscando"
                            />
                        )}
                    </div>
                    <button
                        type="button"
                        onClick={() => setFiltrosAbiertos((v) => !v)}
                        aria-expanded={filtrosAbiertos}
                        aria-controls="pedidos-panel-filtros-movil"
                        aria-label={`Filtros de bandeja, actual: ${tabActual.label}`}
                        className={`${BTN_TOOLBAR_MOVIL} gap-1 max-[400px]:!min-w-[42px] max-[400px]:!px-2`}
                    >
                        <SlidersHorizontal className="w-4 h-4 shrink-0" aria-hidden />
                        <span className="text-xs font-semibold hidden min-[400px]:inline">
                            Filtros
                        </span>
                    </button>
                    {onActualizar && (
                        <button
                            type="button"
                            onClick={onActualizar}
                            disabled={buscando}
                            aria-label="Actualizar listado"
                            className={BTN_TOOLBAR_MOVIL}
                        >
                            <RefreshCw className={`w-4 h-4 ${buscando ? 'animate-spin' : ''}`} aria-hidden />
                        </button>
                    )}
                </div>

                {!filtrosAbiertos && (
                    <IndicadorBandejaActiva
                        tabActual={tabActual}
                        conteoActual={conteoActual}
                        compact
                    />
                )}

                {filtrosAbiertos && (
                    <div
                        id="pedidos-panel-filtros-movil"
                        className="rounded-xl border theme-border theme-element p-3 space-y-3 max-h-[min(52vh,28rem)] overflow-y-auto overscroll-y-contain"
                        aria-label="Bandejas de pedidos"
                    >
                        <IndicadorBandejaActiva
                            tabActual={tabActual}
                            conteoActual={conteoActual}
                            className="pb-2 border-b theme-border sticky top-0 z-[1] theme-element -mx-3 px-3 -mt-3 pt-3"
                        />

                        {gruposPanelMovil.map((grupo) => (
                            <div key={grupo.key}>
                                <p className="text-xs font-semibold theme-text-muted mb-1.5 ml-0.5">
                                    {grupo.label}
                                </p>
                                <ListaBandejasMovil
                                    tabs={grupo.tabs}
                                    tabActiva={tabActiva}
                                    onElegir={elegirTab}
                                    conteoTab={conteoTab}
                                />
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
