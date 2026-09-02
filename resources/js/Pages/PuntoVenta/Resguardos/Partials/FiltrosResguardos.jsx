import React from 'react';
import { Search, Filter, X, Loader2 } from 'lucide-react';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import { BTN_SECONDARY, THEME_INPUT, THEME_SELECT } from './resguardosStyles';
import { antiguedadesVisiblesPorBandeja } from './resguardosUtils';

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
    antiguedadConfigurada = false,
    cargando = false,
    hayFiltrosActivos = false,
    onLimpiar,
}) {
    const estados = catalogos.estados || {};
    const antiguedades = antiguedadesVisiblesPorBandeja(
        bandeja,
        catalogos.antiguedades || {},
        puedeVerVencidos,
    );

    const mostrarEstado = bandeja !== 'por_recibir';
    const mostrarAntiguedad = antiguedadConfigurada && antiguedades.length > 0;

    const columnasFiltro = 1 + (mostrarEstado ? 1 : 0) + (mostrarAntiguedad ? 1 : 0) + (hayFiltrosActivos ? 1 : 0);
    const gridCols = columnasFiltro >= 3 ? 'sm:grid-cols-2 lg:grid-cols-3' : columnasFiltro === 2 ? 'sm:grid-cols-2' : '';

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
                        aria-label="Buscar resguardos"
                    />
                </div>
                {cargando && (
                    <div className="flex items-center gap-2 text-[10px] font-black uppercase theme-text-muted shrink-0">
                        <Loader2 className="w-4 h-4 animate-spin" /> Actualizando
                    </div>
                )}
            </div>

            {(mostrarEstado || mostrarAntiguedad || hayFiltrosActivos) && (
                <div className={`grid grid-cols-1 ${gridCols} gap-3`}>
                    {mostrarEstado && (
                        <label className="space-y-1.5">
                            <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted flex items-center gap-1">
                                <Filter className="w-3 h-3" /> Estado
                            </span>
                            <select
                                value={estado}
                                onChange={(e) => onEstado(e.target.value)}
                                className={THEME_SELECT}
                            >
                                <option value="">Todos</option>
                                {Object.entries(estados).map(([valor, etiqueta]) => (
                                    <option key={valor} value={valor}>{etiqueta}</option>
                                ))}
                            </select>
                        </label>
                    )}

                    {mostrarAntiguedad && (
                        <label className="space-y-1.5">
                            <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Antigüedad</span>
                            <select
                                value={antiguedad}
                                onChange={(e) => onAntiguedad(e.target.value)}
                                className={THEME_SELECT}
                            >
                                <option value="">Todas</option>
                                {antiguedades.map(([valor, etiqueta]) => (
                                    <option key={valor} value={valor}>{etiqueta}</option>
                                ))}
                            </select>
                        </label>
                    )}

                    {hayFiltrosActivos && onLimpiar && (
                        <div className="flex items-end">
                            <button type="button" onClick={onLimpiar} className={`${BTN_SECONDARY} w-full inline-flex items-center justify-center gap-2`}>
                                <X className="w-4 h-4" /> Limpiar filtros
                            </button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
