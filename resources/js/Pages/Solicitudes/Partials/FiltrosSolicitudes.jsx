import React, { useState } from 'react';
import { Search, Calendar, Filter, AlertOctagon, SlidersHorizontal, X } from 'lucide-react';

const TABS = ['TODAS', 'PENDIENTES', 'RESPONDIDAS', 'INCORRECTAS', 'CANCELADAS'];

export default function FiltrosSolicitudes({
    tabActiva,
    busqueda,
    tipoFecha,
    fechaInicio,
    fechaFin,
    filtroVendedor,
    filtroMotivo,
    vendedores = [],
    filtrosActivos = 0,
    onCambiarTab,
    onCambiarBusqueda,
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
        <div className="space-y-4 animate-page-reveal" style={{ animationDelay: '100ms' }}>
            {/* Filtros estándar */}
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
                            placeholder="Buscar folio o cliente..."
                            value={busquedaLocal}
                            onChange={e => setBusquedaLocal(e.target.value)}
                            onKeyDown={e => e.key === 'Enter' && enviarBusqueda(busquedaLocal)}
                            className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 transition-all shadow-sm"
                        />
                    </div>
                    <div className="flex gap-2 shrink-0">
                        <select
                            value={tipoFecha}
                            onChange={e => onAplicarFiltros({ tipo_fecha: e.target.value })}
                            className="flex-1 sm:flex-none sm:min-w-[180px] px-4 py-3 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 cursor-pointer"
                        >
                            <option value="TODAS">Histórico completo</option>
                            <option value="HOY">Solo hoy</option>
                            <option value="AYER">Ayer</option>
                            <option value="SEMANA">Esta semana</option>
                            <option value="MES">Este mes</option>
                            <option value="PERSONALIZADO">Rango personalizado</option>
                        </select>
                        <button
                            type="button"
                            onClick={() => setMostrarAdicionales(v => !v)}
                            className={`flex items-center gap-2 px-4 py-3 rounded-xl border text-[10px] font-black uppercase tracking-widest transition-all shrink-0 ${mostrarAdicionales || filtrosActivos > 0 ? 'border-[var(--color-primario)] text-[var(--color-primario)] bg-[color-mix(in_srgb,var(--color-primario)_10%,transparent)]' : 'theme-border theme-element theme-text-muted hover:border-[var(--color-primario)]'}`}
                        >
                            <SlidersHorizontal className="w-4 h-4" />
                            <span className="hidden sm:inline">Filtros</span>
                            {filtrosActivos > 0 && (
                                <span className="w-5 h-5 rounded-full text-white text-[9px] flex items-center justify-center" style={{ backgroundColor: 'var(--color-primario)' }}>
                                    {filtrosActivos}
                                </span>
                            )}
                        </button>
                    </div>
                </div>
            </div>

            {/* Rango personalizado (estándar visible cuando aplica) */}
            {tipoFecha === 'PERSONALIZADO' && (
                <div className="theme-surface rounded-2xl border theme-border p-4 flex flex-col sm:flex-row gap-3 items-end shadow-sm">
                    <div className="flex-1 w-full flex flex-col gap-1.5">
                        <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted flex items-center gap-1">
                            <Calendar className="w-3 h-3" /> Desde
                        </label>
                        <input
                            type="date"
                            value={fechaInicio}
                            onChange={e => onAplicarFiltros({ fecha_inicio: e.target.value, fecha_fin: fechaFin })}
                            className="w-full px-4 py-2.5 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2"
                        />
                    </div>
                    <div className="flex-1 w-full flex flex-col gap-1.5">
                        <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Hasta</label>
                        <input
                            type="date"
                            value={fechaFin}
                            onChange={e => onAplicarFiltros({ fecha_fin: e.target.value, fecha_inicio: fechaInicio })}
                            className="w-full px-4 py-2.5 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2"
                        />
                    </div>
                    <button
                        type="button"
                        onClick={() => onAplicarFiltros({ tipo_fecha: 'PERSONALIZADO', fecha_inicio: fechaInicio, fecha_fin: fechaFin })}
                        className="w-full sm:w-auto px-6 py-2.5 rounded-xl font-black uppercase text-[10px] tracking-widest text-white hover:scale-105 transition-all shadow-md"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        Aplicar rango
                    </button>
                </div>
            )}

            {/* Filtros adicionales (colapsables) */}
            {mostrarAdicionales && (
                <div className="theme-surface rounded-2xl border theme-border p-4 md:p-5 shadow-sm space-y-4">
                    <div className="flex items-center justify-between">
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted flex items-center gap-2">
                            <Filter className="w-3.5 h-3.5" /> Filtros adicionales
                        </p>
                        {filtrosActivos > 0 && (
                            <button
                                type="button"
                                onClick={onLimpiarAdicionales}
                                className="text-[9px] font-black uppercase tracking-widest theme-text-muted hover:text-red-500 flex items-center gap-1 transition-colors"
                            >
                                <X className="w-3 h-3" /> Limpiar
                            </button>
                        )}
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="flex flex-col gap-1.5">
                            <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Asesor / Vendedor</label>
                            <select
                                value={filtroVendedor}
                                onChange={e => onAplicarFiltros({ vendedor_id: e.target.value })}
                                className="w-full px-4 py-2.5 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 cursor-pointer"
                            >
                                <option value="">Todos los asesores</option>
                                {vendedores.map(v => (
                                    <option key={v.id} value={v.id}>{v.name}</option>
                                ))}
                            </select>
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted flex items-center gap-1">
                                <AlertOctagon className="w-3 h-3" /> Motivo incidencia
                            </label>
                            <select
                                value={filtroMotivo}
                                onChange={e => onAplicarFiltros({ motivo_incorrecta: e.target.value, tab: 'INCORRECTAS' })}
                                className="w-full px-4 py-2.5 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 cursor-pointer"
                            >
                                <option value="">Todos los motivos</option>
                                <option value="error_reportado">Reportadas (error)</option>
                                <option value="vencimiento_pago">Pago vencido</option>
                                <option value="pago_insuficiente">Pago insuficiente</option>
                            </select>
                            <p className="text-[9px] font-bold theme-text-muted italic">
                                Al filtrar por motivo se muestran solicitudes incorrectas, incluyendo registros anteriores sin motivo asignado.
                            </p>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
