import React from 'react';
import { Search, Filter, X, Loader2 } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { BTN_SECONDARY, THEME_INPUT } from './resguardosStyles';

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
    return (
        <div className={`${geliaCardClass()} p-4 md:p-5 space-y-4`}>
            <div className="flex flex-col md:flex-row gap-3 md:items-center">
                <div className="relative flex-1">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted pointer-events-none" />
                    <input
                        type="search"
                        value={busqueda}
                        onChange={(e) => onBusqueda(e.target.value)}
                        placeholder="Folio, remisión o cliente…"
                        className={`${THEME_INPUT} pl-10`}
                        aria-label="Buscar resguardos entregados"
                    />
                </div>
                {cargando && (
                    <div className="flex items-center gap-2 text-[10px] font-black uppercase theme-text-muted shrink-0">
                        <Loader2 className="w-4 h-4 animate-spin" /> Actualizando
                    </div>
                )}
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                <label className="space-y-1.5">
                    <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted flex items-center gap-1">
                        <Filter className="w-3 h-3" /> Entrega desde
                    </span>
                    <input
                        type="date"
                        value={desde}
                        onChange={(e) => onDesde(e.target.value)}
                        className={THEME_INPUT}
                    />
                </label>
                <label className="space-y-1.5">
                    <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Entrega hasta</span>
                    <input
                        type="date"
                        value={hasta}
                        onChange={(e) => onHasta(e.target.value)}
                        className={THEME_INPUT}
                    />
                </label>
                {hayFiltrosActivos && (
                    <div className="flex items-end">
                        <button
                            type="button"
                            onClick={onLimpiar}
                            className={`${BTN_SECONDARY} w-full min-h-[44px] inline-flex items-center justify-center gap-2 text-[10px] font-black uppercase tracking-widest`}
                        >
                            <X className="w-4 h-4" /> Limpiar filtros
                        </button>
                    </div>
                )}
            </div>
        </div>
    );
}
