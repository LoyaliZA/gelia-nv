import React, { useEffect, useState } from 'react';
import { ChevronDown, Layers } from 'lucide-react';
import {
    GELIA_SEGMENT_TABS_SCROLL,
    GELIA_SEGMENT_TABS_TRACK,
    GELIA_SEGMENT_TABS_TRACK_SCROLL,
    TABS_TIENDA,
    TABS_TIENDA_COLA,
    TABS_TIENDA_GRUPOS,
} from '../../Partials/pedidosBmaStyles';
import { BTN_SECONDARY } from '../../Partials/pedidosBmaStyles';

const STORAGE_KEY_BANDEJAS = 'control_pedidos.tienda.bandejas_abiertas';

function SegmentoTabs({ tabs, tabActiva, onTabChange, conteoTab, ariaLabel, trackClass = GELIA_SEGMENT_TABS_TRACK }) {
    return (
        <div className={GELIA_SEGMENT_TABS_SCROLL}>
            <div className={`gelia-segment ${trackClass} p-1 shadow-sm`} role="tablist" aria-label={ariaLabel}>
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
                                <span className="text-[9px] font-semibold px-1.5 py-0.5 rounded-md theme-element border theme-border tabular-nums shrink-0">
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
                        className={`flex flex-col items-start gap-1 px-3 py-3 min-h-[44px] rounded-xl text-xs font-semibold outline-none border transition-colors text-left ${
                            activo
                                ? 'border-transparent text-white'
                                : 'theme-border theme-element theme-text-muted'
                        }`}
                        style={activo ? { backgroundColor: 'var(--color-primario)' } : undefined}
                    >
                        <span className="leading-tight line-clamp-2">{tab.label}</span>
                        {conteo !== undefined && (
                            <span className={`text-sm font-bold tabular-nums ${activo ? 'opacity-95' : 'theme-text-main'}`}>
                                {conteo}
                            </span>
                        )}
                    </button>
                );
            })}
        </div>
    );
}

export default function BandejasTienda({ tabActiva, onTabChange, metricas = {} }) {
    const [masBandejasAbierto, setMasBandejasAbierto] = useState(() => {
        try {
            return sessionStorage.getItem(STORAGE_KEY_BANDEJAS) === '1';
        } catch {
            return false;
        }
    });
    const [movilAbierto, setMovilAbierto] = useState(false);

    useEffect(() => {
        try {
            sessionStorage.setItem(STORAGE_KEY_BANDEJAS, masBandejasAbierto ? '1' : '0');
        } catch {
            /* ignore */
        }
    }, [masBandejasAbierto]);

    const enColaPrincipal = TABS_TIENDA_COLA.some((t) => t.id === tabActiva);

    useEffect(() => {
        if (!enColaPrincipal) {
            setMasBandejasAbierto(true);
        }
    }, [tabActiva, enColaPrincipal]);

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
            DEVOLUCION_PENDIENTE: metricas.devolucion_pendiente,
            HISTORIAL_DEVUELTAS: metricas.historial_devueltas,
            HISTORIAL: metricas.historial,
        };
        return map[tabId];
    };

    const tabActual = TABS_TIENDA.find((t) => t.id === tabActiva) || TABS_TIENDA[0];
    const conteoActual = conteoTab(tabActual.id);

    const elegirTab = (id) => {
        onTabChange(id);
        setMovilAbierto(false);
    };

    return (
        <section className="space-y-3 min-w-0" aria-label="Bandejas de trabajo">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2 min-w-0">
                    <Layers className="w-4 h-4 shrink-0 theme-text-muted" aria-hidden />
                    <div className="min-w-0">
                        <p className="text-xs font-semibold theme-text-muted m-0">Bandeja activa</p>
                        <p className="text-sm font-bold theme-text-main m-0 truncate">
                            {tabActual.label}
                            {conteoActual !== undefined && (
                                <span className="theme-text-muted font-semibold tabular-nums">
                                    {' '}
                                    · {conteoActual}
                                </span>
                            )}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    onClick={() => setMasBandejasAbierto((v) => !v)}
                    aria-expanded={masBandejasAbierto}
                    className={`${BTN_SECONDARY} hidden md:inline-flex items-center gap-2 shrink-0 min-h-[40px] text-xs`}
                >
                    {masBandejasAbierto ? 'Menos bandejas' : 'Más bandejas'}
                    <ChevronDown
                        className={`w-4 h-4 transition-transform duration-200 ${masBandejasAbierto ? 'rotate-180' : ''}`}
                        aria-hidden
                    />
                </button>
            </div>

            <div className="hidden md:block space-y-3">
                <SegmentoTabs
                    tabs={TABS_TIENDA_COLA}
                    tabActiva={tabActiva}
                    onTabChange={onTabChange}
                    conteoTab={conteoTab}
                    ariaLabel="Cola de trabajo"
                />
                {masBandejasAbierto && (
                    <div className="space-y-3 pt-1 border-t theme-border gelia-tienda-op-refine">
                        {TABS_TIENDA_GRUPOS.filter((g) => g.key !== 'cola').map((grupo) => (
                            <div key={grupo.key}>
                                <p className="text-xs font-semibold theme-text-muted mb-1.5 m-0">{grupo.sectionLabel}</p>
                                <SegmentoTabs
                                    tabs={grupo.tabs}
                                    tabActiva={tabActiva}
                                    onTabChange={onTabChange}
                                    conteoTab={conteoTab}
                                    ariaLabel={grupo.sectionLabel}
                                    trackClass={GELIA_SEGMENT_TABS_TRACK_SCROLL}
                                />
                            </div>
                        ))}
                    </div>
                )}
            </div>

            <div className="md:hidden space-y-2">
                <button
                    type="button"
                    onClick={() => setMovilAbierto((v) => !v)}
                    aria-expanded={movilAbierto}
                    className="w-full flex items-center justify-between gap-2 px-3 py-2.5 min-h-[44px] rounded-xl border theme-border theme-element outline-none"
                >
                    <span className="min-w-0 text-left">
                        <span className="block text-xs font-semibold theme-text-muted">Cambiar bandeja</span>
                        <span className="block text-sm font-bold theme-text-main truncate mt-0.5">
                            {tabActual.label}
                            {conteoActual !== undefined ? ` · ${conteoActual}` : ''}
                        </span>
                    </span>
                    <ChevronDown
                        className={`w-4 h-4 theme-text-muted shrink-0 transition-transform duration-200 ${movilAbierto ? 'rotate-180' : ''}`}
                        aria-hidden
                    />
                </button>
                {movilAbierto && (
                    <div className="space-y-3 gelia-tienda-op-refine" role="tablist" aria-label="Bandejas de preparación">
                        {TABS_TIENDA_GRUPOS.map((grupo) => (
                            <div key={grupo.key}>
                                <p className="text-xs font-semibold theme-text-muted mb-2 m-0">{grupo.sectionLabel}</p>
                                <GridTabsMovil
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
        </section>
    );
}
