import React from 'react';
import { Search, X, Loader2 } from 'lucide-react';
import { geliaCardClass, GELIA_CHIP } from '../../../../utils/geliaTheme';
import { BTN_SECONDARY, RESGUARDOS_FILTRO_LABEL, THEME_INPUT } from './resguardosStyles';

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

export default function FiltrosHistorialEntregados({
    busqueda,
    onBusqueda,
    desde,
    onDesde,
    hasta,
    onHasta,
    cargando = false,
    hayFiltrosActivos = false,
    onLimpiar,
}) {
    const chips = [];
    const q = String(busqueda || '').trim();
    if (q) {
        chips.push({ key: 'q', etiqueta: `Búsqueda: ${q}`, onQuitar: () => onBusqueda('') });
    }
    if (desde) {
        chips.push({ key: 'desde', etiqueta: `Desde ${desde}`, onQuitar: () => onDesde('') });
    }
    if (hasta) {
        chips.push({ key: 'hasta', etiqueta: `Hasta ${hasta}`, onQuitar: () => onHasta('') });
    }

    return (
        <div className={`${geliaCardClass()} p-3 sm:p-4 md:p-5 space-y-3`}>
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                <div className="relative flex-1 min-w-0">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted pointer-events-none" aria-hidden />
                    <input
                        type="search"
                        name="q_historial_entregas"
                        autoComplete="off"
                        spellCheck={false}
                        value={busqueda}
                        onChange={(e) => onBusqueda(e.target.value)}
                        placeholder="Folio, remisión o cliente…"
                        className={`${THEME_INPUT} pl-10 min-h-[48px] text-base sm:text-sm`}
                        aria-label="Buscar resguardos entregados"
                    />
                </div>
                {cargando && (
                    <div className="flex items-center gap-2 text-xs font-semibold theme-text-muted shrink-0 min-h-[44px]" aria-live="polite">
                        <Loader2 className="w-4 h-4 animate-spin" aria-hidden />
                        Actualizando…
                    </div>
                )}
            </div>

            {chips.length > 0 && (
                <div className="flex flex-wrap gap-2 items-center">
                    {chips.map(({ key, etiqueta, onQuitar }) => (
                        <ChipFiltroActivo key={key} etiqueta={etiqueta} onQuitar={onQuitar} />
                    ))}
                    {hayFiltrosActivos && onLimpiar && (
                        <button
                            type="button"
                            onClick={onLimpiar}
                            className="text-xs font-semibold text-primario min-h-[36px] px-2 rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--color-primario)]"
                        >
                            Limpiar todo
                        </button>
                    )}
                </div>
            )}

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <label className="space-y-1.5 min-w-0">
                    <span className={RESGUARDOS_FILTRO_LABEL}>Entrega desde</span>
                    <input
                        type="date"
                        value={desde}
                        onChange={(e) => onDesde(e.target.value)}
                        className={`${THEME_INPUT} min-h-[48px] text-base sm:text-sm`}
                    />
                </label>
                <label className="space-y-1.5 min-w-0">
                    <span className={RESGUARDOS_FILTRO_LABEL}>Entrega hasta</span>
                    <input
                        type="date"
                        value={hasta}
                        onChange={(e) => onHasta(e.target.value)}
                        className={`${THEME_INPUT} min-h-[48px] text-base sm:text-sm`}
                    />
                </label>
            </div>

            {hayFiltrosActivos && onLimpiar && chips.length === 0 && (
                <button
                    type="button"
                    onClick={onLimpiar}
                    className={`${BTN_SECONDARY} w-full min-h-[48px] inline-flex items-center justify-center gap-2`}
                >
                    <X className="w-4 h-4" aria-hidden />
                    Limpiar filtros
                </button>
            )}
        </div>
    );
}
