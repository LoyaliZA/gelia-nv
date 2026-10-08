import React, { useEffect, useState } from 'react';
import { Search, RefreshCw, ChevronDown, Loader2, X, SlidersHorizontal } from 'lucide-react';
import { THEME_INPUT, THEME_LABEL } from '../../../../utils/geliaTheme';
import {
    BTN_SECONDARY,
    OPCIONES_ORDEN_DELEGADO,
    OPCIONES_SITUACION_DELEGADO,
} from '../../Partials/pedidosBmaStyles';

const STORAGE_KEY = 'control_pedidos.delegado.filtros_adicionales';

export default function FiltrosDelegado({
    busqueda = '',
    paqueteriaIds = [],
    situacion = '',
    ordenar = 'fecha_desc',
    paqueterias = [],
    onBuscar,
    onPaqueteriaIdsChange,
    onSituacionChange,
    onOrdenarChange,
    onLimpiarFiltros,
    onActualizar,
    buscando = false,
    toolbarAside = null,
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

    const idsSeleccionados = new Set((paqueteriaIds || []).map(String));
    const paqueteriasSel = paqueterias.filter((p) => idsSeleccionados.has(String(p.id)));
    const idsPaqueteriaInvalidos = (paqueteriaIds || []).filter(
        (id) => !paqueterias.some((p) => String(p.id) === String(id))
    );
    const situacionLabel = OPCIONES_SITUACION_DELEGADO.find((o) => o.id === situacion)?.label;
    const ordenLabel = OPCIONES_ORDEN_DELEGADO.find((o) => o.id === ordenar)?.label;

    const hayAdicionalesActivos = paqueteriasSel.length > 0
        || idsPaqueteriaInvalidos.length > 0
        || Boolean(situacion)
        || (ordenar && ordenar !== 'fecha_desc');

    const hayFiltrosExtra = Boolean(busqueda) || hayAdicionalesActivos;

    useEffect(() => {
        if (hayAdicionalesActivos) {
            setAdicionalesAbiertos(true);
        }
    }, [paqueteriasSel.length, situacion, ordenar]);

    const etiquetaBotonAdicionales = (() => {
        const partes = [];
        if (idsPaqueteriaInvalidos.length > 0) {
            partes.push('Paquetería no válida');
        } else if (paqueteriasSel.length > 0) {
            partes.push(
                paqueteriasSel.length === 1
                    ? paqueteriasSel[0].nombre
                    : `${paqueteriasSel.length} paqueterías`
            );
        }
        if (situacion && situacionLabel) partes.push(situacionLabel);
        if (ordenar && ordenar !== 'fecha_desc' && ordenLabel) partes.push(ordenLabel);
        if (partes.length === 0) return 'Filtros adicionales';
        return partes.join(' · ');
    })();

    const togglePaqueteria = (id) => {
        const key = String(id);
        const next = idsSeleccionados.has(key)
            ? paqueteriaIds.filter((x) => String(x) !== key)
            : [...paqueteriaIds, key];
        onPaqueteriaIdsChange?.(next);
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-col lg:flex-row lg:items-end gap-3 lg:gap-4 justify-between">
                <div className="flex flex-col sm:flex-row gap-3 sm:items-end flex-1 min-w-0">
                    <div className="flex-1 min-w-0 max-w-md">
                        <label htmlFor="delegado-busqueda" className={`${THEME_LABEL} ml-1`}>Buscar</label>
                        <div className="theme-field-with-icon relative mt-1.5">
                            <Search className="theme-field-icon w-4 h-4" aria-hidden />
                            <input
                                id="delegado-busqueda"
                                type="text"
                                value={busqueda}
                                onChange={(e) => onBuscar?.(e.target.value)}
                                placeholder="Folio, cliente o guía..."
                                className={`${THEME_INPUT} w-full py-3 text-sm font-bold pr-10 theme-text-main placeholder:theme-text-muted`}
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
                        className={`${BTN_SECONDARY} flex items-center justify-center gap-2 outline-none shrink-0 w-full sm:w-auto min-h-[44px] ${
                            hayAdicionalesActivos ? 'ring-2 ring-[var(--color-primario)]/40' : ''
                        }`}
                    >
                        <SlidersHorizontal className="w-4 h-4" aria-hidden />
                        <span className="truncate max-w-[14rem]">{etiquetaBotonAdicionales}</span>
                        <ChevronDown
                            className={`w-4 h-4 shrink-0 transition-transform ${adicionalesAbiertos ? 'rotate-180' : ''}`}
                            aria-hidden
                        />
                    </button>
                    <button
                        type="button"
                        onClick={onActualizar}
                        disabled={buscando}
                        className={`${BTN_SECONDARY} flex items-center justify-center gap-2 outline-none shrink-0 w-full sm:w-auto min-h-[44px] disabled:opacity-60`}
                    >
                        <RefreshCw className={`w-4 h-4 ${buscando ? 'animate-spin' : ''}`} aria-hidden />
                        Actualizar
                    </button>
                    {hayFiltrosExtra && onLimpiarFiltros && (
                        <button
                            type="button"
                            onClick={onLimpiarFiltros}
                            className={`${BTN_SECONDARY} flex items-center justify-center gap-2 outline-none shrink-0 w-full sm:w-auto min-h-[44px]`}
                        >
                            <X className="w-4 h-4" aria-hidden />
                            Limpiar
                        </button>
                    )}
                </div>
                {toolbarAside ? (
                    <div className="shrink-0 w-full lg:w-auto">{toolbarAside}</div>
                ) : null}
            </div>

            {adicionalesAbiertos && (
                <div className="space-y-3 p-3 rounded-xl border theme-border theme-element">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label htmlFor="delegado-ordenar" className={`${THEME_LABEL} ml-0.5`}>
                                Ordenar por
                            </label>
                            <select
                                id="delegado-ordenar"
                                className="theme-select theme-text-main w-full mt-1.5 py-3 text-sm font-bold min-h-[44px]"
                                value={ordenar || 'fecha_desc'}
                                onChange={(e) => onOrdenarChange?.(e.target.value)}
                            >
                                {OPCIONES_ORDEN_DELEGADO.map((o) => (
                                    <option key={o.id} value={o.id}>{o.label}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label htmlFor="delegado-situacion" className={`${THEME_LABEL} ml-0.5`}>
                                Situación operativa
                            </label>
                            <select
                                id="delegado-situacion"
                                className="theme-select theme-text-main w-full mt-1.5 py-3 text-sm font-bold min-h-[44px]"
                                value={situacion || ''}
                                onChange={(e) => onSituacionChange?.(e.target.value)}
                            >
                                {OPCIONES_SITUACION_DELEGADO.map((o) => (
                                    <option key={o.id || 'todas'} value={o.id}>{o.label}</option>
                                ))}
                            </select>
                        </div>
                    </div>
                    {paqueterias.length > 0 && (
                        <div role="group" aria-labelledby="delegado-paqueterias-label">
                            <p id="delegado-paqueterias-label" className={`${THEME_LABEL} ml-0.5 mb-2`}>
                                Paqueterías
                            </p>
                            {idsPaqueteriaInvalidos.length > 0 && (
                                <p className="text-xs font-bold theme-text-muted m-0 mb-2 ml-0.5" role="status">
                                    Hay identificadores de paquetería en la URL que no son válidos. Usa Limpiar para restablecer.
                                </p>
                            )}
                            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                {paqueterias.map((p) => {
                                    const activo = idsSeleccionados.has(String(p.id));
                                    const inputId = `delegado-paq-${p.id}`;
                                    return (
                                        <label
                                            key={p.id}
                                            htmlFor={inputId}
                                            className={`flex items-center gap-3 px-3 py-3 min-h-[44px] rounded-xl border cursor-pointer text-xs font-bold outline-none transition-colors focus-within:ring-2 focus-within:ring-[color-mix(in_srgb,var(--color-primario)_40%,transparent)] ${
                                                activo
                                                    ? 'border-[color-mix(in_srgb,var(--color-primario)_35%,var(--theme-border))] theme-text-main theme-surface'
                                                    : 'theme-border theme-element theme-text-muted'
                                            }`}
                                        >
                                            <input
                                                id={inputId}
                                                type="checkbox"
                                                className="w-4 h-4 shrink-0 rounded border theme-border accent-[var(--color-primario)]"
                                                checked={activo}
                                                onChange={() => togglePaqueteria(p.id)}
                                            />
                                            <span className="truncate">{p.nombre}</span>
                                        </label>
                                    );
                                })}
                            </div>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
