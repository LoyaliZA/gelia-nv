import React, { useMemo, useState } from 'react';
import { Search, Filter, X, Loader2, ChevronDown, ChevronUp } from 'lucide-react';
import { geliaCardClass, GELIA_CHIP } from '../../../../utils/geliaTheme';
import { BTN_SECONDARY, RESGUARDOS_FILTRO_LABEL, THEME_INPUT, THEME_SELECT } from './resguardosStyles';
import { antiguedadesVisiblesPorBandeja } from './resguardosUtils';
import BusquedaRapidaRecepcion from './BusquedaRapidaRecepcion';

const STORAGE_FILTROS_AVANZADOS = 'pdv_resguardos_filtros_avanzados_abiertos';

function estadoInicialAvanzados() {
    if (typeof window === 'undefined') return false;
    const guardado = window.localStorage?.getItem(STORAGE_FILTROS_AVANZADOS);
    if (guardado !== null) return guardado === 'true';
    return window.matchMedia('(min-width: 768px)').matches;
}

function ChipFiltroActivo({ etiqueta, onQuitar }) {
    return (
        <span className={`${GELIA_CHIP} gap-1.5 max-w-full min-h-[36px]`}>
            <span className="truncate">{etiqueta}</span>
            <button
                type="button"
                onClick={onQuitar}
                className="shrink-0 inline-flex items-center justify-center min-h-[28px] min-w-[28px] rounded-md hover:theme-element focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primario)]"
                aria-label={`Quitar filtro: ${etiqueta}`}
            >
                <X className="w-3.5 h-3.5" aria-hidden />
            </button>
        </span>
    );
}

export default function FiltrosResguardos({
    bandeja = 'por_recibir',
    busqueda,
    onBusqueda,
    estado,
    onEstado,
    antiguedad,
    onAntiguedad,
    catalogos = {},
    puedeVerVencidos = false,
    puedeVerRezagados = false,
    antiguedadConfigurada = false,
    cargando = false,
    hayFiltrosActivos = false,
    onLimpiar,
    puedeConfirmarEscaneo = false,
    ocultarAntiguedad = false,
    onRecepcionExito,
    origenId = '',
    onOrigen,
    alta = '',
    onAlta,
    evidencia = '',
    onEvidencia,
    altaHoy = false,
    onAltaHoy,
}) {
    const estados = catalogos.estados || {};
    const origenes = catalogos.origenes_pedido || [];
    const antiguedades = antiguedadesVisiblesPorBandeja(
        bandeja,
        catalogos.antiguedades || {},
        puedeVerVencidos,
        puedeVerRezagados,
    );

    const mostrarEstado = bandeja !== 'por_recibir';
    const mostrarAntiguedad = !ocultarAntiguedad && antiguedadConfigurada && antiguedades.length > 0;
    const [avanzadosAbiertos, setAvanzadosAbiertos] = useState(estadoInicialAvanzados);

    const toggleAvanzados = () => {
        setAvanzadosAbiertos((prev) => {
            const next = !prev;
            try {
                window.localStorage?.setItem(STORAGE_FILTROS_AVANZADOS, String(next));
            } catch {
                // ponytail: preferencia no crítica
            }
            return next;
        });
    };

    const chips = useMemo(() => {
        const items = [];
        const q = String(busqueda || '').trim();
        if (q) {
            items.push({
                key: 'q',
                etiqueta: `Búsqueda: ${q}`,
                onQuitar: () => onBusqueda(''),
            });
        }
        if (estado) {
            items.push({
                key: 'estado',
                etiqueta: estados[estado] || estado,
                onQuitar: () => onEstado(''),
            });
        }
        if (antiguedad && mostrarAntiguedad) {
            const etiqueta = catalogos.antiguedades?.[antiguedad] || antiguedad;
            items.push({
                key: 'antiguedad',
                etiqueta,
                onQuitar: () => onAntiguedad(''),
            });
        }
        if (origenId) {
            const origen = origenes.find((o) => String(o.id) === String(origenId));
            items.push({
                key: 'origen',
                etiqueta: origen?.nombre ? `Origen: ${origen.nombre}` : 'Origen filtrado',
                onQuitar: () => onOrigen?.(''),
            });
        }
        if (alta) {
            const mapa = { manual: 'Alta manual', pedido: 'Con pedido' };
            items.push({
                key: 'alta',
                etiqueta: mapa[alta] || alta,
                onQuitar: () => onAlta?.(''),
            });
        }
        if (evidencia) {
            const mapa = {
                incompleta: 'Evidencia incompleta',
                completa: 'Evidencia completa',
            };
            items.push({
                key: 'evidencia',
                etiqueta: mapa[evidencia] || evidencia,
                onQuitar: () => onEvidencia?.(''),
            });
        }
        if (altaHoy) {
            items.push({
                key: 'altaHoy',
                etiqueta: 'Registrados hoy',
                onQuitar: () => onAltaHoy?.(false),
            });
        }
        return items;
    }, [
        busqueda,
        estado,
        antiguedad,
        origenId,
        alta,
        evidencia,
        altaHoy,
        estados,
        origenes,
        catalogos.antiguedades,
        mostrarAntiguedad,
        onBusqueda,
        onEstado,
        onAntiguedad,
        onOrigen,
        onAlta,
        onEvidencia,
        onAltaHoy,
    ]);

    const filtrosAvanzadosActivos = chips.filter((c) => c.key !== 'q').length;

    return (
        <div className={`${geliaCardClass()} p-3 sm:p-4 md:p-5 space-y-3 min-w-0 max-w-full overflow-hidden`}>
            {puedeConfirmarEscaneo ? (
                <div className="space-y-2">
                    <BusquedaRapidaRecepcion
                        variante="embebida"
                        puedeRecibir
                        valor={busqueda}
                        onValor={onBusqueda}
                        onAplicarFiltro={onBusqueda}
                        onRecepcionExito={onRecepcionExito}
                    />
                    <p className="text-xs theme-text-muted m-0 leading-relaxed">
                        Escribe o escanea para filtrar. Si hay un solo pendiente, se confirma la recepción.
                    </p>
                </div>
            ) : (
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div className="relative flex-1 min-w-0">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted pointer-events-none" aria-hidden />
                        <input
                            type="search"
                            name="q_resguardos"
                            autoComplete="off"
                            spellCheck={false}
                            value={busqueda}
                            onChange={(e) => onBusqueda(e.target.value)}
                            placeholder="Folio, remisión o cliente…"
                            className={`${THEME_INPUT} pl-10 min-h-[48px] text-base sm:text-sm`}
                            aria-label="Buscar resguardos"
                        />
                    </div>
                    {cargando && (
                        <div
                            className="flex items-center justify-center gap-2 text-xs font-semibold theme-text-muted shrink-0 min-h-[44px]"
                            aria-live="polite"
                        >
                            <Loader2 className="w-4 h-4 animate-spin" aria-hidden />
                            Actualizando…
                        </div>
                    )}
                </div>
            )}
            {puedeConfirmarEscaneo && cargando && (
                <div className="flex items-center gap-2 text-xs font-semibold theme-text-muted" aria-live="polite">
                    <Loader2 className="w-4 h-4 animate-spin" aria-hidden />
                    Actualizando…
                </div>
            )}

            {chips.length > 0 && (
                <div className="flex flex-wrap gap-2 items-center" role="list" aria-label="Filtros activos">
                    {chips.map(({ key, etiqueta, onQuitar }) => (
                        <ChipFiltroActivo key={key} etiqueta={etiqueta} onQuitar={onQuitar} />
                    ))}
                    {hayFiltrosActivos && onLimpiar && (
                        <button
                            type="button"
                            onClick={onLimpiar}
                            className="text-xs font-semibold text-primario min-h-[36px] px-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primario)] rounded-lg"
                        >
                            Limpiar todo
                        </button>
                    )}
                </div>
            )}

            <div className="border-t theme-border pt-3">
                <button
                    type="button"
                    onClick={toggleAvanzados}
                    aria-expanded={avanzadosAbiertos}
                    className="w-full flex items-center justify-between gap-3 min-h-[48px] px-1 rounded-lg text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primario)]"
                >
                    <span className="inline-flex items-center gap-2 text-sm font-semibold theme-text-main">
                        <Filter className="w-4 h-4 shrink-0 theme-text-muted" aria-hidden />
                        Más filtros
                        {filtrosAvanzadosActivos > 0 && (
                            <span className="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-md text-[11px] font-bold tabular-nums bg-[var(--color-primario)]/15 text-[var(--color-primario)]">
                                {filtrosAvanzadosActivos}
                            </span>
                        )}
                    </span>
                    {avanzadosAbiertos ? (
                        <ChevronUp className="w-5 h-5 shrink-0 theme-text-muted" aria-hidden />
                    ) : (
                        <ChevronDown className="w-5 h-5 shrink-0 theme-text-muted" aria-hidden />
                    )}
                </button>

                {avanzadosAbiertos && (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 pt-2 pb-1">
                        {mostrarEstado && (
                            <label className="space-y-1.5 min-w-0">
                                <span className={RESGUARDOS_FILTRO_LABEL}>Estado</span>
                                <select
                                    value={estado}
                                    onChange={(e) => onEstado(e.target.value)}
                                    className={`${THEME_SELECT} min-h-[48px] text-base sm:text-sm`}
                                >
                                    <option value="">Todos</option>
                                    {Object.entries(estados).map(([valor, etiqueta]) => (
                                        <option key={valor} value={valor}>{etiqueta}</option>
                                    ))}
                                </select>
                            </label>
                        )}

                        {mostrarAntiguedad && (
                            <label className="space-y-1.5 min-w-0">
                                <span className={RESGUARDOS_FILTRO_LABEL}>Antigüedad</span>
                                <select
                                    value={antiguedad}
                                    onChange={(e) => onAntiguedad(e.target.value)}
                                    className={`${THEME_SELECT} min-h-[48px] text-base sm:text-sm`}
                                >
                                    <option value="">Todas</option>
                                    {antiguedades.map(([valor, etiqueta]) => (
                                        <option key={valor} value={valor}>{etiqueta}</option>
                                    ))}
                                </select>
                            </label>
                        )}

                        <label className="space-y-1.5 min-w-0">
                            <span className={RESGUARDOS_FILTRO_LABEL}>Área de origen</span>
                            <select
                                value={origenId}
                                onChange={(e) => onOrigen?.(e.target.value)}
                                className={`${THEME_SELECT} min-h-[48px] text-base sm:text-sm`}
                            >
                                <option value="">Todas</option>
                                {origenes.map((origen) => (
                                    <option key={origen.id} value={origen.id}>{origen.nombre}</option>
                                ))}
                            </select>
                        </label>
                        <label className="space-y-1.5 min-w-0">
                            <span className={RESGUARDOS_FILTRO_LABEL}>Alta</span>
                            <select
                                value={alta}
                                onChange={(e) => onAlta?.(e.target.value)}
                                className={`${THEME_SELECT} min-h-[48px] text-base sm:text-sm`}
                            >
                                <option value="">Todas</option>
                                <option value="manual">Manual</option>
                                <option value="pedido">Con pedido</option>
                            </select>
                        </label>
                        <label className="space-y-1.5 min-w-0">
                            <span className={RESGUARDOS_FILTRO_LABEL}>Evidencia</span>
                            <select
                                value={evidencia}
                                onChange={(e) => onEvidencia?.(e.target.value)}
                                className={`${THEME_SELECT} min-h-[48px] text-base sm:text-sm`}
                            >
                                <option value="">Todas</option>
                                <option value="incompleta">Falta ticket o foto</option>
                                <option value="completa">Ticket y foto listos</option>
                            </select>
                        </label>
                        <label className="flex items-center gap-3 min-h-[48px] cursor-pointer sm:col-span-2 lg:col-span-1 rounded-xl border theme-border px-3 theme-element">
                            <input
                                type="checkbox"
                                checked={altaHoy}
                                onChange={(e) => onAltaHoy?.(e.target.checked)}
                                className="w-5 h-5 rounded border theme-border shrink-0"
                            />
                            <span className="text-sm font-semibold theme-text-main">Registrados hoy</span>
                        </label>

                        {hayFiltrosActivos && onLimpiar && chips.length === 0 && (
                            <div className="flex items-end sm:col-span-2 lg:col-span-3">
                                <button
                                    type="button"
                                    onClick={onLimpiar}
                                    className={`${BTN_SECONDARY} w-full min-h-[48px] inline-flex items-center justify-center gap-2`}
                                >
                                    <X className="w-4 h-4" aria-hidden />
                                    Limpiar filtros
                                </button>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </div>
    );
}
