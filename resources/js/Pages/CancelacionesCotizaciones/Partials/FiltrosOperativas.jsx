import React, { useState, useEffect, useCallback } from 'react';
import { Search, Calendar, SlidersHorizontal, X, ChevronDown } from 'lucide-react';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { TIPOS_OPERATIVO, BTN_PRIMARY, BTN_SECONDARY, GELIA_SEGMENT_TABS_SCROLL, GELIA_SEGMENT_TABS_TRACK, GELIA_SEGMENT_TABS_TRACK_COMPACT } from './operativasStyles';

const TABS = ['TODAS', 'PENDIENTES', 'RESPONDIDAS', 'VERIFICADAS', 'INCORRECTAS', 'CANCELADAS'];

const TAB_LABELS = {
    TODAS: 'Todas',
    PENDIENTES: 'Pendientes',
    RESPONDIDAS: 'Respondidas',
    VERIFICADAS: 'Verificadas',
    INCORRECTAS: 'Incorrectas',
    CANCELADAS: 'Canceladas',
};

export default function FiltrosOperativas({
    tabActiva,
    busqueda = '',
    tipoOperativo,
    fechaInicio,
    fechaFin,
    filtroVendedor,
    vendedores = [],
    filtrosActivos = 0,
    onCambiarTab,
    onAplicarFiltros,
    onCambiarTipo,
    onLimpiarAdicionales,
    listaCargando = false,
}) {
    const [mostrarAdicionales, setMostrarAdicionales] = useState(filtrosActivos > 0);
    const [busquedaLocal, setBusquedaLocal] = useState(busqueda);

    useEffect(() => {
        setBusquedaLocal(busqueda);
    }, [busqueda]);

    useEffect(() => {
        if (filtrosActivos > 0) setMostrarAdicionales(true);
    }, [filtrosActivos]);

    const enviarBusqueda = useCallback(
        (valor) => {
            onAplicarFiltros?.({ q: valor.trim() || undefined, page: 1 });
        },
        [onAplicarFiltros]
    );

    const handleBusquedaKeyDown = (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        enviarBusqueda(busquedaLocal);
    };

    return (
        <section
            className={`${geliaCardClass('overflow-hidden')} ${listaCargando ? 'opacity-95' : ''}`}
            aria-label="Filtros operativas"
            aria-busy={listaCargando}
        >
            <div className="p-4 md:p-5 border-b theme-border flex items-center gap-3">
                <div className="p-2 rounded-xl theme-element border theme-border shrink-0">
                    <SlidersHorizontal className="w-4 h-4 theme-text-muted" aria-hidden />
                </div>
                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 min-w-0 flex-1">
                    Filtros
                </p>
                {filtrosActivos > 0 && (
                    <span className="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-lg border theme-border theme-element shrink-0" style={{ color: 'var(--color-primario)' }}>
                        {filtrosActivos} activo{filtrosActivos !== 1 ? 's' : ''}
                    </span>
                )}
            </div>

            <div className={`${GELIA_SEGMENT_TABS_SCROLL} p-3 md:p-4 border-b theme-border`}>
                <div className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK} p-1 shadow-sm`} role="tablist" aria-label="Estado de solicitudes">
                    {TABS.map((tab) => (
                        <button
                            key={tab}
                            type="button"
                            role="tab"
                            aria-selected={tabActiva === tab}
                            onClick={() => onCambiarTab(tab)}
                            className="gelia-segment-btn whitespace-nowrap"
                            data-active={tabActiva === tab}
                        >
                            {TAB_LABELS[tab] || tab}
                        </button>
                    ))}
                </div>
            </div>

            <div className={`${GELIA_SEGMENT_TABS_SCROLL} p-3 md:p-4 border-b theme-border`}>
                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted ml-1 mb-2 m-0">
                    Tipo de operación
                </p>
                <div className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK_COMPACT} p-1 shadow-sm`} role="tablist" aria-label="Tipo de operación">
                    {TIPOS_OPERATIVO.map(({ id, label }) => (
                        <button
                            key={id || 'all'}
                            type="button"
                            role="tab"
                            aria-selected={tipoOperativo === id}
                            onClick={() => onCambiarTipo(id)}
                            className="gelia-segment-btn whitespace-nowrap"
                            data-active={tipoOperativo === id}
                        >
                            {label}
                        </button>
                    ))}
                </div>
            </div>

            <div className="p-4 md:p-5 flex flex-col sm:flex-row gap-3 sm:gap-4 sm:items-end">
                <div className="min-w-0 flex-1">
                    <label htmlFor="operativas-busqueda" className="theme-label ml-1">
                        Buscar
                    </label>
                    <div className="flex flex-col sm:flex-row gap-2 mt-1.5">
                        <div className="theme-field-with-icon min-w-0 flex-1">
                            <Search className="theme-field-icon" aria-hidden />
                            <input
                                id="operativas-busqueda"
                                type="text"
                                value={busquedaLocal}
                                onChange={(e) => setBusquedaLocal(e.target.value)}
                                onKeyDown={handleBusquedaKeyDown}
                                placeholder="Folio, remisión, pedido o cliente"
                                autoComplete="off"
                                enterKeyHint="search"
                                className="theme-input w-full pr-4 py-3 normal-case tracking-normal font-bold text-sm"
                            />
                        </div>
                        <button
                            type="button"
                            onClick={() => enviarBusqueda(busquedaLocal)}
                            disabled={listaCargando}
                            className={`${BTN_PRIMARY} w-full sm:w-auto shrink-0 px-6`}
                            aria-label="Buscar solicitudes operativas"
                        >
                            <Search className="w-4 h-4 shrink-0" aria-hidden />
                            Buscar
                        </button>
                    </div>
                </div>
                <button
                    type="button"
                    onClick={() => setMostrarAdicionales((v) => !v)}
                    className={`${BTN_SECONDARY} w-full sm:w-auto shrink-0`}
                    aria-expanded={mostrarAdicionales}
                >
                    <SlidersHorizontal className="w-4 h-4 shrink-0" />
                    {mostrarAdicionales ? 'Ocultar' : 'Más filtros'}
                </button>
            </div>

            {mostrarAdicionales && (
                <div className="px-4 md:px-5 pb-5 pt-0 border-t theme-border">
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4">
                        <div>
                            <label htmlFor="operativas-vendedor" className="theme-label ml-1">
                                Vendedor
                            </label>
                            <div className="relative mt-1.5">
                                <select
                                    id="operativas-vendedor"
                                    value={filtroVendedor}
                                    onChange={(e) => onAplicarFiltros({ vendedor_id: e.target.value, page: 1 })}
                                    className="theme-select w-full py-3 pr-10"
                                >
                                    <option value="">Todos</option>
                                    {vendedores.map((v) => (
                                        <option key={v.id} value={v.id}>{v.name}</option>
                                    ))}
                                </select>
                                <ChevronDown className="theme-field-with-icon__trailing" aria-hidden />
                            </div>
                        </div>
                        <div>
                            <label htmlFor="operativas-desde" className="theme-label ml-1">
                                Desde
                            </label>
                            <div className="theme-field-with-icon mt-1.5">
                                <Calendar className="theme-field-icon" aria-hidden />
                                <input
                                    id="operativas-desde"
                                    type="date"
                                    value={fechaInicio}
                                    onChange={(e) => onAplicarFiltros({ fecha_inicio: e.target.value, fecha_fin: fechaFin, page: 1 })}
                                    className="theme-input w-full pr-4 py-3"
                                />
                            </div>
                        </div>
                        <div>
                            <label htmlFor="operativas-hasta" className="theme-label ml-1">
                                Hasta
                            </label>
                            <div className="theme-field-with-icon mt-1.5">
                                <Calendar className="theme-field-icon" aria-hidden />
                                <input
                                    id="operativas-hasta"
                                    type="date"
                                    value={fechaFin}
                                    onChange={(e) => onAplicarFiltros({ fecha_inicio: fechaInicio, fecha_fin: e.target.value, page: 1 })}
                                    className="theme-input w-full pr-4 py-3"
                                />
                            </div>
                        </div>
                    </div>
                    {filtrosActivos > 0 && (
                        <div className="flex justify-end mt-4">
                            <button
                                type="button"
                                onClick={() => {
                                    onLimpiarAdicionales?.();
                                    setMostrarAdicionales(false);
                                }}
                                className="inline-flex items-center gap-2 text-[10px] font-black uppercase text-red-500 outline-none"
                            >
                                <X className="w-4 h-4 shrink-0" /> Limpiar filtros avanzados
                            </button>
                        </div>
                    )}
                </div>
            )}
        </section>
    );
}
