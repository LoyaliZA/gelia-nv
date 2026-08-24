import React, { useEffect, useState } from 'react';
import { Search, RefreshCw, ChevronDown, Loader2, X } from 'lucide-react';
import { THEME_INPUT, THEME_LABEL, THEME_SELECT } from '../../../../utils/geliaTheme';
import {
    BTN_SECONDARY,
    GELIA_SEGMENT_TABS_SCROLL,
    GELIA_SEGMENT_TABS_TRACK,
    TABS_AUDITORIA,
    TABS_AUDITORIA_PRINCIPALES,
    TABS_AUDITORIA_SUBFILTROS,
} from '../../Partials/pedidosBmaStyles';
import GeliaPaginacion from '../../../../Components/GeliaPaginacion';

const STORAGE_KEY = 'control_pedidos.auditar.filtros_abiertos';

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

export default function FiltrosAuditoria({
    filtros = {},
    tabActiva,
    busqueda = '',
    paqueteriaId = '',
    paqueterias = [],
    onTabChange,
    onBuscar,
    onPaqueteriaChange,
    onLimpiarFiltros,
    onActualizar,
    metricas = {},
    pedidos = null,
    onIrAPagina,
    buscando = false,
}) {
    const [filtrosAbiertos, setFiltrosAbiertos] = useState(() => {
        try {
            return sessionStorage.getItem(STORAGE_KEY) === '1';
        } catch {
            return false;
        }
    });

    useEffect(() => {
        try {
            sessionStorage.setItem(STORAGE_KEY, filtrosAbiertos ? '1' : '0');
        } catch {
            /* ignore */
        }
    }, [filtrosAbiertos]);

    const conteoTab = (tabId) => {
        const map = {
            PENDIENTES: metricas.pendientes,
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

    const tabActual = TABS_AUDITORIA.find((t) => t.id === tabActiva) || TABS_AUDITORIA[0];
    const conteoActual = conteoTab(tabActual.id);
    const esSubfiltro = TABS_AUDITORIA_SUBFILTROS.some((t) => t.id === tabActiva);
    const paqNombre = paqueterias.find((p) => String(p.id) === String(paqueteriaId))?.nombre;
    const hayFiltrosExtra = Boolean(busqueda) || Boolean(paqueteriaId) || (tabActiva && tabActiva !== 'PENDIENTES' && tabActiva !== 'TODAS');

    const elegirTab = (id) => {
        onTabChange(id);
        setFiltrosAbiertos(false);
    };

    const resumenContraido = [
        tabActual.label,
        paqNombre ? `Paquetería: ${paqNombre}` : null,
        busqueda ? `Buscar: ${busqueda}` : null,
    ].filter(Boolean).join(' · ');

    return (
        <div className="space-y-4">
            <div className="flex flex-col sm:flex-row gap-3 sm:items-end">
                <div className="flex-1 min-w-0">
                    <label htmlFor="auditoria-busqueda" className={`${THEME_LABEL} ml-1`}>Buscar</label>
                    <div className="theme-field-with-icon relative mt-1.5">
                        <Search className="theme-field-icon w-4 h-4" aria-hidden />
                        <input
                            id="auditoria-busqueda"
                            type="text"
                            value={busqueda}
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
                <div className="sm:w-56 shrink-0">
                    <label htmlFor="auditoria-paqueteria" className={`${THEME_LABEL} ml-1`}>Paquetería</label>
                    <select
                        id="auditoria-paqueteria"
                        className={`${THEME_SELECT} w-full mt-1.5 py-3 text-sm font-bold`}
                        value={paqueteriaId || ''}
                        onChange={(e) => onPaqueteriaChange?.(e.target.value)}
                    >
                        <option value="">Todas</option>
                        {paqueterias.map((p) => (
                            <option key={p.id} value={p.id}>{p.nombre}</option>
                        ))}
                    </select>
                </div>
                <button
                    type="button"
                    onClick={onActualizar}
                    disabled={buscando}
                    className={`${BTN_SECONDARY} flex items-center justify-center gap-2 outline-none shrink-0 w-full sm:w-auto disabled:opacity-60`}
                >
                    <RefreshCw className={`w-4 h-4 ${buscando ? 'animate-spin' : ''}`} /> Actualizar
                </button>
                {hayFiltrosExtra && onLimpiarFiltros && (
                    <button
                        type="button"
                        onClick={onLimpiarFiltros}
                        className={`${BTN_SECONDARY} flex items-center justify-center gap-2 outline-none shrink-0 w-full sm:w-auto`}
                    >
                        <X className="w-4 h-4" /> Limpiar filtros
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
                            {filtrosAbiertos
                                ? `${tabActual.label}${conteoActual !== undefined ? ` · ${conteoActual}` : ''}`
                                : resumenContraido}
                        </span>
                    </span>
                    <ChevronDown
                        className={`w-4 h-4 theme-text-muted shrink-0 transition-transform ${filtrosAbiertos ? 'rotate-180' : ''}`}
                        aria-hidden
                    />
                </button>

                {filtrosAbiertos && (
                    <div className="space-y-3" role="tablist" aria-label="Filtros de auditoría">
                        <div>
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-2 ml-0.5">
                                Estado
                            </p>
                            <GridTabsMovil
                                tabs={TABS_AUDITORIA_PRINCIPALES}
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
                                tabs={TABS_AUDITORIA_SUBFILTROS}
                                tabActiva={tabActiva}
                                onElegir={elegirTab}
                                conteoTab={conteoTab}
                            />
                        </div>
                    </div>
                )}
            </div>

            <div className="hidden md:block space-y-3">
                <div>
                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1.5 ml-0.5">
                        Estado
                    </p>
                    <SegmentoTabs
                        tabs={TABS_AUDITORIA_PRINCIPALES}
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
                        tabs={TABS_AUDITORIA_SUBFILTROS}
                        tabActiva={tabActiva}
                        onTabChange={onTabChange}
                        conteoTab={conteoTab}
                        ariaLabel="Subfiltros de envío y colas"
                    />
                </div>
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
