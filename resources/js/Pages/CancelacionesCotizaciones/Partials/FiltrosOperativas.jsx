import React, { useState } from 'react';
import { Search, Calendar, SlidersHorizontal, X } from 'lucide-react';
import { TIPOS_OPERATIVO } from './operativasStyles';

const TABS = ['TODAS', 'PENDIENTES', 'RESPONDIDAS', 'VERIFICADAS', 'INCORRECTAS', 'CANCELADAS'];

export default function FiltrosOperativas({
    tabActiva,
    busqueda,
    tipoOperativo,
    fechaInicio,
    fechaFin,
    filtroVendedor,
    vendedores = [],
    filtrosActivos = 0,
    onCambiarTab,
    onCambiarBusqueda,
    onCambiarTipo,
    onAplicarFiltros,
    onLimpiarAdicionales,
}) {
    const [mostrarAdicionales, setMostrarAdicionales] = useState(false);
    const [busquedaLocal, setBusquedaLocal] = useState(busqueda);

    const enviarBusqueda = (valor) => {
        onCambiarBusqueda(valor);
        onAplicarFiltros({ q: valor });
    };

    return (
        <div className="space-y-4 animate-page-reveal">
            <div className="flex flex-col lg:flex-row gap-4 items-stretch lg:items-center justify-between">
                <div className="gelia-segment w-full lg:w-auto p-1 h-14 shadow-sm overflow-x-auto flex shrink-0">
                    {TABS.map(tab => (
                        <button
                            key={tab}
                            type="button"
                            onClick={() => onCambiarTab(tab)}
                            className="gelia-segment-btn px-4 md:px-6 min-w-max flex-1 text-center"
                            data-active={tabActiva === tab}
                        >
                            {tab}
                        </button>
                    ))}
                </div>

                <div className="flex flex-col sm:flex-row gap-3 w-full lg:flex-1 lg:max-w-2xl">
                    <div className="relative flex-1">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted" />
                        <input
                            type="text"
                            value={busquedaLocal}
                            onChange={e => setBusquedaLocal(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && enviarBusqueda(busquedaLocal)}
                            placeholder="Buscar folio, remisión, pedido o cliente..."
                            className="w-full pl-12 pr-4 py-3 theme-element border theme-border rounded-2xl theme-text-main text-sm font-bold outline-none focus:ring-2 shadow-sm"
                        />
                    </div>
                    <button
                        type="button"
                        onClick={() => setMostrarAdicionales(v => !v)}
                        className="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest theme-element border theme-border outline-none shrink-0"
                    >
                        <SlidersHorizontal className="w-4 h-4" />
                        Filtros{filtrosActivos > 0 ? ` (${filtrosActivos})` : ''}
                    </button>
                </div>
            </div>

            <div className="flex flex-wrap gap-2">
                {TIPOS_OPERATIVO.map(({ id, label }) => (
                    <button
                        key={id || 'all'}
                        type="button"
                        onClick={() => onCambiarTipo(id)}
                        className={`px-4 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest border transition-colors outline-none ${
                            tipoOperativo === id
                                ? 'bg-orange-500 text-white border-orange-500'
                                : 'theme-element theme-border theme-text-muted hover:border-orange-500'
                        }`}
                    >
                        {label}
                    </button>
                ))}
            </div>

            {mostrarAdicionales && (
                <div className="p-5 rounded-2xl theme-element border theme-border grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-2 block">Vendedora</label>
                        <select
                            value={filtroVendedor}
                            onChange={e => onAplicarFiltros({ vendedor_id: e.target.value })}
                            className="w-full px-3 py-2.5 theme-surface border theme-border rounded-xl text-xs font-bold outline-none"
                        >
                            <option value="">Todas</option>
                            {vendedores.map(v => <option key={v.id} value={v.id}>{v.name}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-2 block">Desde</label>
                        <div className="relative">
                            <Calendar className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                            <input
                                type="date"
                                value={fechaInicio}
                                onChange={e => onAplicarFiltros({ fecha_inicio: e.target.value, fecha_fin: fechaFin })}
                                className="w-full pl-10 pr-3 py-2.5 theme-surface border theme-border rounded-xl text-xs font-bold outline-none"
                            />
                        </div>
                    </div>
                    <div>
                        <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-2 block">Hasta</label>
                        <div className="relative">
                            <Calendar className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                            <input
                                type="date"
                                value={fechaFin}
                                onChange={e => onAplicarFiltros({ fecha_inicio: fechaInicio, fecha_fin: e.target.value })}
                                className="w-full pl-10 pr-3 py-2.5 theme-surface border theme-border rounded-xl text-xs font-bold outline-none"
                            />
                        </div>
                    </div>
                    {filtrosActivos > 0 && (
                        <div className="md:col-span-3 flex justify-end">
                            <button type="button" onClick={onLimpiarAdicionales} className="inline-flex items-center gap-2 text-[10px] font-black uppercase text-red-500 outline-none">
                                <X className="w-4 h-4" /> Limpiar filtros
                            </button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
