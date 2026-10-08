import React, { useEffect, useState } from 'react';
import {
    Search, RefreshCw, ChevronDown, Loader2, X, SlidersHorizontal,
} from 'lucide-react';
import { THEME_INPUT, THEME_LABEL, THEME_SELECT } from '../../../../utils/geliaTheme';
import {
    BTN_SECONDARY,
    MODALIDADES_TIENDA_FILTRO,
} from '../../Partials/pedidosBmaStyles';

export { TABS_TIENDA } from '../../Partials/pedidosBmaStyles';

const STORAGE_KEY_ADICIONALES = 'control_pedidos.tienda.filtros_adicionales';

const ORIGEN_LABELS = {
    CALL_CENTER: 'Call Center',
    BELLAROMA: 'Bellaroma',
    SIN_ORIGEN: 'Sin origen',
};

function ChipFiltro({ children, onQuitar }) {
    return (
        <span className="inline-flex items-center gap-1.5 max-w-full px-2.5 py-1 rounded-lg border theme-border theme-element text-xs font-semibold theme-text-main">
            <span className="truncate">{children}</span>
            {onQuitar && (
                <button
                    type="button"
                    onClick={onQuitar}
                    className="shrink-0 p-0.5 rounded-md outline-none theme-text-muted hover:theme-text-main"
                    aria-label="Quitar filtro"
                >
                    <X className="w-3 h-3" />
                </button>
            )}
        </span>
    );
}

export default function FiltrosTienda({
    busqueda,
    origenSolicitud = '',
    prioridadMd = '',
    modalidad = '',
    almacenId = '',
    almacenes = [],
    origenesSolicitud = [],
    onBuscar,
    onOrigenChange,
    onPrioridadMdChange,
    onModalidadChange,
    onAlmacenChange,
    onLimpiarFiltros,
    onActualizar,
    buscando = false,
    tabActiva = 'PENDIENTES',
}) {
    const [filtrosAdicionalesAbiertos, setFiltrosAdicionalesAbiertos] = useState(() => {
        try {
            return sessionStorage.getItem(STORAGE_KEY_ADICIONALES) === '1';
        } catch {
            return false;
        }
    });

    useEffect(() => {
        try {
            sessionStorage.setItem(STORAGE_KEY_ADICIONALES, filtrosAdicionalesAbiertos ? '1' : '0');
        } catch {
            /* ignore */
        }
    }, [filtrosAdicionalesAbiertos]);

    const almacenNombre = almacenes.find((a) => String(a.id) === String(almacenId))?.nombre;
    const modalidadLabel = MODALIDADES_TIENDA_FILTRO.find((m) => m.id === modalidad)?.label;
    const prioridadLabel = prioridadMd === '1' ? 'Mismo día' : prioridadMd === '0' ? 'Sin prioridad MD' : null;
    const origenLabel = origenSolicitud ? (ORIGEN_LABELS[origenSolicitud] || origenSolicitud) : null;

    const hayFiltrosRefinamiento = Boolean(origenSolicitud) || prioridadMd !== '' || Boolean(modalidad) || Boolean(almacenId);
    const hayFiltrosExtra = Boolean(busqueda) || hayFiltrosRefinamiento || tabActiva !== 'PENDIENTES';

    useEffect(() => {
        if (hayFiltrosRefinamiento) {
            setFiltrosAdicionalesAbiertos(true);
        }
    }, [origenSolicitud, prioridadMd, modalidad, almacenId]);

    const etiquetaFiltrosAdicionales = (() => {
        const partes = [];
        if (almacenNombre) partes.push(almacenNombre);
        if (modalidadLabel && modalidad) partes.push(modalidadLabel);
        if (origenLabel) partes.push(origenLabel);
        if (prioridadLabel) partes.push(prioridadLabel);
        if (partes.length === 0) return 'Refinar listado';
        return partes.join(' · ');
    })();

    const conteoRefinamiento = [almacenNombre, modalidad && modalidadLabel, origenLabel, prioridadLabel].filter(Boolean).length;

    return (
        <div className="gelia-tienda-op-toolbar space-y-3">
            <div className="flex flex-col lg:flex-row gap-3 lg:items-end">
                <div className="flex-1 min-w-0">
                    <label htmlFor="tienda-busqueda" className={`${THEME_LABEL}`}>Buscar pedido</label>
                    <div className="theme-field-with-icon relative mt-1.5">
                        <Search className="theme-field-icon w-4 h-4" aria-hidden />
                        <input
                            id="tienda-busqueda"
                            type="search"
                            value={busqueda ?? ''}
                            onChange={(e) => onBuscar(e.target.value)}
                            placeholder="Folio, cliente o número"
                            className={`${THEME_INPUT} w-full py-3 text-sm font-semibold pr-10`}
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
                <div className="flex flex-wrap gap-2 shrink-0">
                    <button
                        type="button"
                        onClick={() => setFiltrosAdicionalesAbiertos((v) => !v)}
                        aria-expanded={filtrosAdicionalesAbiertos}
                        className={`${BTN_SECONDARY} flex items-center justify-center gap-2 outline-none min-h-[44px] flex-1 sm:flex-none ${
                            hayFiltrosRefinamiento ? 'ring-2 ring-[color-mix(in_srgb,var(--color-primario)_35%,transparent)]' : ''
                        }`}
                    >
                        <SlidersHorizontal className="w-4 h-4 shrink-0" />
                        <span className="truncate max-w-[14rem] text-left text-xs font-semibold normal-case tracking-normal">
                            {etiquetaFiltrosAdicionales}
                        </span>
                        {conteoRefinamiento > 0 && (
                            <span className="tabular-nums text-[10px] font-bold px-1.5 py-0.5 rounded-md theme-element border theme-border">
                                {conteoRefinamiento}
                            </span>
                        )}
                        <ChevronDown
                            className={`w-4 h-4 shrink-0 transition-transform duration-200 ${filtrosAdicionalesAbiertos ? 'rotate-180' : ''}`}
                            aria-hidden
                        />
                    </button>
                    <button
                        type="button"
                        onClick={onActualizar}
                        disabled={buscando}
                        className={`${BTN_SECONDARY} flex items-center justify-center gap-2 outline-none min-h-[44px] px-4 disabled:opacity-60`}
                        aria-label="Actualizar listado"
                    >
                        <RefreshCw className={`w-4 h-4 ${buscando ? 'animate-spin' : ''}`} />
                        <span className="hidden sm:inline text-xs font-semibold normal-case tracking-normal">Actualizar</span>
                    </button>
                    {hayFiltrosExtra && onLimpiarFiltros && (
                        <button
                            type="button"
                            onClick={onLimpiarFiltros}
                            className={`${BTN_SECONDARY} flex items-center justify-center gap-2 outline-none min-h-[44px] px-4`}
                        >
                            <X className="w-4 h-4" />
                            <span className="text-xs font-semibold normal-case tracking-normal">Limpiar</span>
                        </button>
                    )}
                </div>
            </div>

            {filtrosAdicionalesAbiertos && (
                <div className="gelia-tienda-op-refine space-y-3 p-4 rounded-xl border theme-border theme-element">
                    <p className="text-xs font-semibold theme-text-muted m-0">
                        Almacén, modalidad, origen y prioridad
                    </p>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div className="min-w-0">
                            <label htmlFor="tienda-almacen" className={THEME_LABEL}>Almacén</label>
                            <select
                                id="tienda-almacen"
                                value={almacenId}
                                onChange={(e) => onAlmacenChange?.(e.target.value)}
                                className={`${THEME_SELECT} w-full mt-1.5 py-3 text-sm font-semibold`}
                            >
                                <option value="">Todos</option>
                                {almacenes.map((a) => (
                                    <option key={a.id} value={a.id}>{a.nombre || a.codigo}</option>
                                ))}
                            </select>
                        </div>
                        <div className="min-w-0">
                            <label htmlFor="tienda-modalidad" className={THEME_LABEL}>Modalidad</label>
                            <select
                                id="tienda-modalidad"
                                value={modalidad}
                                onChange={(e) => onModalidadChange?.(e.target.value)}
                                className={`${THEME_SELECT} w-full mt-1.5 py-3 text-sm font-semibold`}
                            >
                                {MODALIDADES_TIENDA_FILTRO.map((m) => (
                                    <option key={m.id || 'todas'} value={m.id}>{m.label}</option>
                                ))}
                            </select>
                        </div>
                        <div className="min-w-0">
                            <label htmlFor="tienda-origen" className={THEME_LABEL}>Origen</label>
                            <select
                                id="tienda-origen"
                                value={origenSolicitud}
                                onChange={(e) => onOrigenChange?.(e.target.value)}
                                className={`${THEME_SELECT} w-full mt-1.5 py-3 text-sm font-semibold`}
                            >
                                <option value="">Todos</option>
                                {origenesSolicitud.map((o) => (
                                    <option key={o} value={o}>{ORIGEN_LABELS[o] || o}</option>
                                ))}
                                <option value="SIN_ORIGEN">{ORIGEN_LABELS.SIN_ORIGEN}</option>
                            </select>
                        </div>
                        <div className="min-w-0">
                            <label htmlFor="tienda-md" className={THEME_LABEL}>Prioridad mismo día</label>
                            <select
                                id="tienda-md"
                                value={prioridadMd}
                                onChange={(e) => onPrioridadMdChange?.(e.target.value)}
                                className={`${THEME_SELECT} w-full mt-1.5 py-3 text-sm font-semibold`}
                            >
                                <option value="">Todas</option>
                                <option value="1">Solo mismo día</option>
                                <option value="0">Sin prioridad MD</option>
                            </select>
                        </div>
                    </div>
                </div>
            )}

            {(hayFiltrosRefinamiento || Boolean(busqueda)) && (
                <div className="flex flex-wrap gap-2" aria-label="Filtros activos">
                    {busqueda && (
                        <ChipFiltro onQuitar={() => onBuscar('')}>
                            Búsqueda: {busqueda}
                        </ChipFiltro>
                    )}
                    {almacenNombre && (
                        <ChipFiltro onQuitar={() => onAlmacenChange?.('')}>
                            {almacenNombre}
                        </ChipFiltro>
                    )}
                    {modalidad && modalidadLabel && (
                        <ChipFiltro onQuitar={() => onModalidadChange?.('')}>
                            {modalidadLabel}
                        </ChipFiltro>
                    )}
                    {origenLabel && (
                        <ChipFiltro onQuitar={() => onOrigenChange?.('')}>
                            Origen: {origenLabel}
                        </ChipFiltro>
                    )}
                    {prioridadLabel && (
                        <ChipFiltro onQuitar={() => onPrioridadMdChange?.('')}>
                            {prioridadLabel}
                        </ChipFiltro>
                    )}
                </div>
            )}
        </div>
    );
}
