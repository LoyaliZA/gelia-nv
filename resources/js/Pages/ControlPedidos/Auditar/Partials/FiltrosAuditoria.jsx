import React, { useEffect, useState } from 'react';
import { Search, RefreshCw, ChevronDown, Loader2, X, SlidersHorizontal } from 'lucide-react';
import { THEME_INPUT, THEME_LABEL, THEME_SELECT } from '../../../../utils/geliaTheme';
import {
    BTN_SECONDARY,
    GELIA_SEGMENT_TABS_SCROLL,
    GELIA_SEGMENT_TABS_TRACK,
    TABS_AUDITORIA_PRINCIPALES,
    TABS_AUDITORIA_SUBFILTROS,
} from '../../Partials/pedidosBmaStyles';
import GeliaPaginacion from '../../../../Components/GeliaPaginacion';

const STORAGE_KEY = 'control_pedidos.auditar.filtros_adicionales';

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
    pedidos = null,
    onIrAPagina,
    buscando = false,
}) {
    const [adicionalesAbiertos, setAdicionalesAbiertos] = useState(() => {
        try {
            return sessionStorage.getItem(STORAGE_KEY) === '1';
        } catch {
            return false;
        }
    });

    useEffect(() => {
        try {
            sessionStorage.setItem(STORAGE_KEY, adicionalesAbiertos ? '1' : '0');
        } catch {
            /* ignore */
        }
    }, [adicionalesAbiertos]);

    const conteoTab = (tabId) => {
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

    const esSubfiltro = TABS_AUDITORIA_SUBFILTROS.some((t) => t.id === tabActiva);
    const subfiltroActivo = TABS_AUDITORIA_SUBFILTROS.find((t) => t.id === tabActiva);
    const paqNombre = paqueterias.find((p) => String(p.id) === String(paqueteriaId))?.nombre;
    const deptoNombre = departamentos.find((d) => String(d.id) === String(departamentoId))?.nombre;
    const ordenLabel = OPCIONES_ORDEN_AUDITORIA.find((o) => o.id === ordenar)?.label;
    const hayAdicionalesActivos = esSubfiltro
        || Boolean(paqueteriaId)
        || Boolean(departamentoId)
        || Boolean(clienteFiltro)
        || (ordenar && ordenar !== 'fecha_desc');
    const hayFiltrosExtra = Boolean(busqueda) || hayAdicionalesActivos || (tabActiva && tabActiva !== 'PENDIENTES' && tabActiva !== 'TODAS');

    useEffect(() => {
        if (hayAdicionalesActivos) {
            setAdicionalesAbiertos(true);
        }
    }, [esSubfiltro, paqueteriaId, departamentoId, clienteFiltro, ordenar]);

    const etiquetaBotonAdicionales = (() => {
        const partes = [];
        if (subfiltroActivo) partes.push(subfiltroActivo.label);
        if (deptoNombre) partes.push(deptoNombre);
        if (clienteFiltro) partes.push(`Cliente: ${clienteFiltro}`);
        if (paqNombre) partes.push(paqNombre);
        if (ordenar && ordenar !== 'fecha_desc' && ordenLabel) partes.push(ordenLabel);
        if (partes.length === 0) return 'Filtros adicionales';
        return partes.join(' · ');
    })();

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
                            placeholder="Folio, cliente, vendedor o número..."
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
                    onClick={() => setAdicionalesAbiertos((v) => !v)}
                    aria-expanded={adicionalesAbiertos}
                    className={`${BTN_SECONDARY} flex items-center justify-center gap-2 outline-none shrink-0 w-full sm:w-auto ${
                        hayAdicionalesActivos ? 'ring-2 ring-[var(--color-primario)]/40' : ''
                    }`}
                >
                    <SlidersHorizontal className="w-4 h-4" />
                    <span className="truncate max-w-[14rem]">{etiquetaBotonAdicionales}</span>
                    <ChevronDown className={`w-4 h-4 shrink-0 transition-transform ${adicionalesAbiertos ? 'rotate-180' : ''}`} aria-hidden />
                </button>
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
                        <X className="w-4 h-4" /> Limpiar
                    </button>
                )}
            </div>

            <div>
                <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1.5 ml-0.5">
                    Estado
                </p>
                <div className="md:hidden">
                    <GridTabsMovil
                        tabs={TABS_AUDITORIA_PRINCIPALES}
                        tabActiva={tabActiva}
                        onElegir={onTabChange}
                        conteoTab={conteoTab}
                    />
                </div>
                <div className="hidden md:block">
                    <SegmentoTabs
                        tabs={TABS_AUDITORIA_PRINCIPALES}
                        tabActiva={tabActiva}
                        onTabChange={onTabChange}
                        conteoTab={conteoTab}
                        ariaLabel="Estado del pedido"
                    />
                </div>
            </div>

            {adicionalesAbiertos && (
                <div className="space-y-3 p-3 rounded-xl border theme-border theme-element">
                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">
                        Filtros adicionales
                    </p>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        <div>
                            <label htmlFor="auditoria-ordenar" className={`${THEME_LABEL} ml-0.5`}>
                                Ordenar por
                            </label>
                            <select
                                id="auditoria-ordenar"
                                className={`${THEME_SELECT} w-full mt-1.5 py-3 text-sm font-bold`}
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
                                className={`${THEME_SELECT} w-full mt-1.5 py-3 text-sm font-bold`}
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
                                className={`${THEME_INPUT} w-full mt-1.5 py-3 text-sm font-bold`}
                                autoComplete="off"
                            />
                        </div>
                        <div>
                            <label htmlFor="auditoria-paqueteria" className={`${THEME_LABEL} ml-0.5`}>
                                Paquetería / transporte
                            </label>
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
                    </div>
                    <div>
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-1.5 ml-0.5">
                            Colas operativas
                        </p>
                        <div className="md:hidden">
                            <GridTabsMovil
                                tabs={TABS_AUDITORIA_SUBFILTROS}
                                tabActiva={tabActiva}
                                onElegir={onTabChange}
                                conteoTab={conteoTab}
                            />
                        </div>
                        <div className="hidden md:block">
                            <SegmentoTabs
                                tabs={TABS_AUDITORIA_SUBFILTROS}
                                tabActiva={tabActiva}
                                onTabChange={onTabChange}
                                conteoTab={conteoTab}
                                ariaLabel="Filtros adicionales de colas"
                            />
                        </div>
                    </div>
                </div>
            )}

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
