import React, { useEffect, useMemo, useState } from 'react';
import { Search, RefreshCw, ChevronDown, Loader2, X, SlidersHorizontal } from 'lucide-react';
import { THEME_INPUT, THEME_LABEL, THEME_SELECT } from '../../../../utils/geliaTheme';
import {
    BTN_SECONDARY,
    TABS_AUDITORIA,
    TABS_AUDITORIA_SUBFILTROS,
    esTabAuditoriaEnMetricasKpi,
} from '../../Partials/pedidosBmaStyles';

const STORAGE_KEY = 'control_pedidos.auditar.filtros_adicionales';

const BTN_TOOLBAR_MOVIL =
    'inline-flex items-center justify-center rounded-xl border theme-border theme-element shrink-0 '
    + 'min-h-[44px] min-w-[44px] px-2.5 theme-text-main hover:bg-black/5 dark:hover:bg-white/5 '
    + 'outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primario)] '
    + 'disabled:opacity-60';

export const OPCIONES_ORDEN_AUDITORIA = [
    { id: 'fecha_desc', label: 'Fecha (más reciente)' },
    { id: 'fecha_asc', label: 'Fecha (más antigua)' },
    { id: 'folio_asc', label: 'Folio A–Z' },
    { id: 'folio_desc', label: 'Folio Z–A' },
    { id: 'cliente_asc', label: 'Cliente A–Z' },
    { id: 'cliente_desc', label: 'Cliente Z–A' },
    { id: 'vendedor_asc', label: 'Vendedor A–Z' },
    { id: 'total_desc', label: 'Total (mayor)' },
    { id: 'total_asc', label: 'Total (menor)' },
];

function useConteoTab(metricas) {
    return (tabId) => {
        const map = {
            PENDIENTES: metricas.pendientes,
            CORREGIDOS: metricas.corregidos,
            PAGO_EN_REVISION: metricas.pago_en_revision,
            PENDIENTE_REMISION: metricas.pendiente_remision,
            PAGO_VALIDADO: metricas.pago_validado,
            ENVIO_PENDIENTE: metricas.envio_pendiente,
            PENDIENTE_LIBERACION: metricas.pendiente_liberacion,
            ANEXO_POR_VERIFICAR: metricas.anexo_por_verificar,
            ANEXO_RECHAZADO: metricas.anexo_rechazado,
            CONSOLIDADOS: metricas.consolidados,
            RESGUARDOS: metricas.resguardos,
            APROBADOS: metricas.aprobados,
            RECHAZADOS: metricas.rechazados,
            TODAS: metricas.total,
        };
        return map[tabId];
    };
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

export default function FiltrosAuditoria({
    tabActiva,
    busqueda = '',
    paqueteriaId = '',
    departamentoId = '',
    clienteFiltro = '',
    ordenar = 'fecha_desc',
    paqueterias = [],
    departamentos = [],
    onTabChange,
    onBuscar,
    onPaqueteriaChange,
    onDepartamentoChange,
    onClienteFiltroChange,
    onOrdenarChange,
    onLimpiarFiltros,
    onActualizar,
    metricas = {},
    buscando = false,
}) {
    const [adicionalesAbiertos, setAdicionalesAbiertos] = useState(() => {
        try {
            return sessionStorage.getItem(STORAGE_KEY) === '1';
        } catch {
            return false;
        }
    });
    const [filtrosMovilAbiertos, setFiltrosMovilAbiertos] = useState(false);

    const conteoTab = useConteoTab(metricas);

    useEffect(() => {
        try {
            sessionStorage.setItem(STORAGE_KEY, adicionalesAbiertos ? '1' : '0');
        } catch {
            /* ignore */
        }
    }, [adicionalesAbiertos]);

    const tabActual = TABS_AUDITORIA.find((t) => t.id === tabActiva) || TABS_AUDITORIA[0];
    const conteoActual = conteoTab(tabActual.id);

    const esSubfiltro = TABS_AUDITORIA_SUBFILTROS.some((t) => t.id === tabActiva);
    const paqNombre = paqueterias.find((p) => String(p.id) === String(paqueteriaId))?.nombre;
    const deptoNombre = departamentos.find((d) => String(d.id) === String(departamentoId))?.nombre;
    const ordenLabel = OPCIONES_ORDEN_AUDITORIA.find((o) => o.id === ordenar)?.label;
    const hayAdicionalesActivos = esSubfiltro
        || Boolean(paqueteriaId)
        || Boolean(departamentoId)
        || Boolean(clienteFiltro)
        || (ordenar && ordenar !== 'fecha_desc');
    const hayFiltrosExtra = Boolean(busqueda) || hayAdicionalesActivos
        || (tabActiva && tabActiva !== 'PENDIENTES' && tabActiva !== 'TODAS');

    useEffect(() => {
        if (hayAdicionalesActivos) {
            setAdicionalesAbiertos(true);
        }
    }, [esSubfiltro, paqueteriaId, departamentoId, clienteFiltro, ordenar]);

    const valorSelectorCola = TABS_AUDITORIA_SUBFILTROS.some((t) => t.id === tabActiva) ? tabActiva : '';

    const elegirTab = (id) => {
        onTabChange(id);
        setFiltrosMovilAbiertos(false);
    };

    const onSelectorCola = (e) => {
        const id = e.target.value;
        if (id) onTabChange(id);
    };

    const etiquetaBotonAdicionales = useMemo(() => {
        const partes = [];
        const subfiltro = TABS_AUDITORIA_SUBFILTROS.find((t) => t.id === tabActiva);
        if (subfiltro) partes.push(subfiltro.label);
        if (deptoNombre) partes.push(deptoNombre);
        if (clienteFiltro) partes.push(`Cliente: ${clienteFiltro}`);
        if (paqNombre) partes.push(paqNombre);
        if (ordenar && ordenar !== 'fecha_desc' && ordenLabel) partes.push(ordenLabel);
        if (partes.length === 0) return 'Filtros avanzados';
        return partes.join(' · ');
    }, [tabActiva, deptoNombre, clienteFiltro, paqNombre, ordenar, ordenLabel]);

    const gruposPanelMovil = useMemo(() => ([
        { key: 'colas', label: 'Colas operativas', tabs: TABS_AUDITORIA_SUBFILTROS },
    ]), []);

    return (
        <div className="space-y-2.5 md:space-y-3">
            <div className="hidden md:flex flex-wrap items-end gap-2 lg:gap-3">
                <div className="flex-1 min-w-[14rem]">
                    <label htmlFor="auditoria-busqueda" className={`${THEME_LABEL} ml-0.5`}>Buscar</label>
                    <div className="theme-field-with-icon relative mt-1">
                        <Search className="theme-field-icon w-4 h-4" aria-hidden />
                        <input
                            id="auditoria-busqueda"
                            type="search"
                            value={busqueda}
                            onChange={(e) => onBuscar(e.target.value)}
                            placeholder="Folio, cliente, vendedor o número..."
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
                    <label htmlFor="auditoria-cola-extra" className={`${THEME_LABEL} ml-0.5`}>
                        Colas operativas
                    </label>
                    <select
                        id="auditoria-cola-extra"
                        value={valorSelectorCola}
                        onChange={onSelectorCola}
                        className={`${THEME_SELECT} mt-1 w-full py-2.5 text-sm font-semibold`}
                    >
                        <option value="">
                            {esTabAuditoriaEnMetricasKpi(tabActiva)
                                ? 'Elegir cola adicional…'
                                : 'Seleccionar cola…'}
                        </option>
                        {TABS_AUDITORIA_SUBFILTROS.map((tab) => {
                            const conteo = conteoTab(tab.id);
                            const suffix = conteo !== undefined ? ` (${conteo})` : '';
                            return (
                                <option key={tab.id} value={tab.id}>
                                    {tab.label}{suffix}
                                </option>
                            );
                        })}
                    </select>
                </div>

                <button
                    type="button"
                    onClick={() => setAdicionalesAbiertos((v) => !v)}
                    aria-expanded={adicionalesAbiertos}
                    className={`${BTN_SECONDARY} !min-h-0 !py-2.5 !px-3 flex items-center justify-center gap-2 outline-none shrink-0 ${
                        hayAdicionalesActivos ? 'ring-2 ring-[color-mix(in_srgb,var(--color-primario)_40%,transparent)]' : ''
                    }`}
                >
                    <SlidersHorizontal className="w-4 h-4" aria-hidden />
                    <span className="truncate max-w-[12rem]">{etiquetaBotonAdicionales}</span>
                    <ChevronDown className={`w-4 h-4 shrink-0 transition-transform ${adicionalesAbiertos ? 'rotate-180' : ''}`} aria-hidden />
                </button>

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

                {hayFiltrosExtra && onLimpiarFiltros && (
                    <button
                        type="button"
                        onClick={onLimpiarFiltros}
                        className={`${BTN_SECONDARY} !min-h-0 !py-2.5 !px-3 flex items-center justify-center gap-2 outline-none shrink-0`}
                    >
                        <X className="w-4 h-4" aria-hidden />
                        Limpiar
                    </button>
                )}
            </div>

            <div className="hidden md:block">
                <IndicadorBandejaActiva tabActual={tabActual} conteoActual={conteoActual} />
            </div>

            <div className="md:hidden space-y-2 min-w-0">
                <div className="flex items-center gap-1.5 min-w-0">
                    <div className="theme-field-with-icon relative flex-1 min-w-0">
                        <Search className="theme-field-icon w-4 h-4" aria-hidden />
                        <input
                            id="auditoria-busqueda-movil"
                            type="search"
                            value={busqueda}
                            onChange={(e) => onBuscar(e.target.value)}
                            placeholder="Folio o cliente…"
                            aria-label="Buscar folio, cliente o vendedor"
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
                        onClick={() => setFiltrosMovilAbiertos((v) => !v)}
                        aria-expanded={filtrosMovilAbiertos}
                        aria-controls="auditoria-panel-filtros-movil"
                        aria-label={`Filtros de bandeja, actual: ${tabActual.label}`}
                        className={`${BTN_TOOLBAR_MOVIL} gap-1 max-[400px]:!min-w-[42px] max-[400px]:!px-2`}
                    >
                        <SlidersHorizontal className="w-4 h-4 shrink-0" aria-hidden />
                        <span className="text-xs font-semibold hidden min-[400px]:inline">Filtros</span>
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

                {!filtrosMovilAbiertos && (
                    <IndicadorBandejaActiva tabActual={tabActual} conteoActual={conteoActual} compact />
                )}

                {filtrosMovilAbiertos && (
                    <div
                        id="auditoria-panel-filtros-movil"
                        className="rounded-xl border theme-border theme-element p-3 space-y-3 max-h-[min(52vh,28rem)] overflow-y-auto overscroll-y-contain"
                        aria-label="Colas y filtros de revisión"
                    >
                        <IndicadorBandejaActiva
                            tabActual={tabActual}
                            conteoActual={conteoActual}
                            className="pb-2 border-b theme-border sticky top-0 z-[1] theme-element -mx-3 px-3 -mt-3 pt-3"
                        />
                        {gruposPanelMovil.map((grupo) => (
                            <div key={grupo.key}>
                                <p className="text-xs font-semibold theme-text-muted mb-1.5 ml-0.5">{grupo.label}</p>
                                <ListaBandejasMovil
                                    tabs={grupo.tabs}
                                    tabActiva={tabActiva}
                                    onElegir={elegirTab}
                                    conteoTab={conteoTab}
                                />
                            </div>
                        ))}
                        {hayFiltrosExtra && onLimpiarFiltros && (
                            <button
                                type="button"
                                onClick={onLimpiarFiltros}
                                className={`${BTN_SECONDARY} w-full text-xs outline-none`}
                            >
                                Limpiar filtros
                            </button>
                        )}
                    </div>
                )}
            </div>

            {adicionalesAbiertos && (
                <div className="space-y-3 p-3 rounded-xl border theme-border theme-element">
                    <p className="text-xs font-semibold theme-text-muted m-0">Filtros avanzados</p>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div>
                            <label htmlFor="auditoria-ordenar" className={`${THEME_LABEL} ml-0.5`}>
                                Ordenar por
                            </label>
                            <select
                                id="auditoria-ordenar"
                                className={`${THEME_SELECT} w-full mt-1.5 py-2.5 text-sm font-semibold`}
                                value={ordenar || 'fecha_desc'}
                                onChange={(e) => onOrdenarChange?.(e.target.value)}
                            >
                                {OPCIONES_ORDEN_AUDITORIA.map((o) => (
                                    <option key={o.id} value={o.id}>{o.label}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label htmlFor="auditoria-departamento" className={`${THEME_LABEL} ml-0.5`}>
                                Departamento
                            </label>
                            <select
                                id="auditoria-departamento"
                                className={`${THEME_SELECT} w-full mt-1.5 py-2.5 text-sm font-semibold`}
                                value={departamentoId || ''}
                                onChange={(e) => onDepartamentoChange?.(e.target.value)}
                            >
                                <option value="">Todos</option>
                                {departamentos.map((d) => (
                                    <option key={d.id} value={d.id}>{d.nombre}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label htmlFor="auditoria-cliente" className={`${THEME_LABEL} ml-0.5`}>
                                Cliente
                            </label>
                            <input
                                id="auditoria-cliente"
                                type="text"
                                value={clienteFiltro}
                                onChange={(e) => onClienteFiltroChange?.(e.target.value)}
                                placeholder="Nombre o n° cliente..."
                                className={`${THEME_INPUT} w-full mt-1.5 py-2.5 text-sm font-semibold`}
                                autoComplete="off"
                            />
                        </div>
                        <div>
                            <label htmlFor="auditoria-paqueteria" className={`${THEME_LABEL} ml-0.5`}>
                                Paquetería / transporte
                            </label>
                            <select
                                id="auditoria-paqueteria"
                                className={`${THEME_SELECT} w-full mt-1.5 py-2.5 text-sm font-semibold`}
                                value={paqueteriaId || ''}
                                onChange={(e) => onPaqueteriaChange?.(e.target.value)}
                            >
                                <option value="">Todas</option>
                                {paqueterias.map((p) => (
                                    <option key={p.id} value={p.id}>{p.nombre}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div className="md:hidden">
                        <p className="text-xs font-semibold theme-text-muted mb-1.5 ml-0.5">Colas operativas</p>
                        <ListaBandejasMovil
                            tabs={TABS_AUDITORIA_SUBFILTROS}
                            tabActiva={tabActiva}
                            onElegir={onTabChange}
                            conteoTab={conteoTab}
                        />
                    </div>
                </div>
            )}
        </div>
    );
}
