import React, { useEffect, useState } from 'react';
import { Search, Filter, AlertOctagon, SlidersHorizontal, X, Calendar } from 'lucide-react';
import RangoFechasPersonalizado from '@/Components/Filtros/RangoFechasPersonalizado';

const TABS = ['TODAS', 'PENDIENTES', 'RESPONDIDAS', 'INCORRECTAS', 'CANCELADAS'];

/**
 * Filtros compartidos: módulo Solicitudes y Reportes de solicitudes financieras.
 */
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
    onAplicarFiltros,
    onLimpiarAdicionales,
    idPrefixFechas = 'filtro-fecha',
    etiquetaBuscar = 'Buscar solicitudes',
}) {
    const [mostrarAdicionales, setMostrarAdicionales] = useState(
        filtrosActivos > 0 || tipoFecha !== 'TODAS'
    );
    const [busquedaLocal, setBusquedaLocal] = useState(busqueda);
    const [tipoFechaLocal, setTipoFechaLocal] = useState(tipoFecha);
    const [fechaInicioLocal, setFechaInicioLocal] = useState(fechaInicio);
    const [fechaFinLocal, setFechaFinLocal] = useState(fechaFin);
    const [vendedorLocal, setVendedorLocal] = useState(filtroVendedor);
    const [motivoLocal, setMotivoLocal] = useState(filtroMotivo);

    useEffect(() => {
        setBusquedaLocal(busqueda);
    }, [busqueda]);

    useEffect(() => {
        setTipoFechaLocal(tipoFecha);
        setFechaInicioLocal(fechaInicio);
        setFechaFinLocal(fechaFin);
        setVendedorLocal(filtroVendedor);
        setMotivoLocal(filtroMotivo);
    }, [tipoFecha, fechaInicio, fechaFin, filtroVendedor, filtroMotivo]);

    useEffect(() => {
        if (filtrosActivos > 0 || tipoFecha !== 'TODAS') {
            setMostrarAdicionales(true);
        }
    }, [filtrosActivos, tipoFecha]);

    const aplicarConsulta = () => {
        onAplicarFiltros({
            q: busquedaLocal,
            tipo_fecha: tipoFechaLocal,
            fecha_inicio: fechaInicioLocal,
            fecha_fin: fechaFinLocal,
            vendedor_id: vendedorLocal,
            motivo_incorrecta: motivoLocal,
            tab: motivoLocal ? 'INCORRECTAS' : undefined,
        });
    };

    const limpiarPanelAdicionales = () => {
        setTipoFechaLocal('TODAS');
        setFechaInicioLocal('');
        setFechaFinLocal('');
        setVendedorLocal('');
        setMotivoLocal('');
        onLimpiarAdicionales?.();
    };

    return (
        <div className="space-y-4 animate-page-reveal" style={{ animationDelay: '100ms' }}>
            <div className="flex flex-col lg:flex-row gap-4 items-stretch lg:items-center justify-between">
                <div className="gelia-segment w-full lg:w-auto p-1 h-14 shadow-sm overflow-x-auto flex shrink-0">
                    {TABS.map((tab) => (
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
                    <div className="flex flex-col sm:flex-row gap-2 flex-1 min-w-0">
                        <div className="relative flex-1 min-w-0">
                            <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted pointer-events-none" />
                            <input
                                type="text"
                                placeholder="Buscar folio o cliente..."
                                value={busquedaLocal}
                                onChange={(e) => setBusquedaLocal(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        aplicarConsulta();
                                    }
                                }}
                                enterKeyHint="search"
                                autoComplete="off"
                                className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 transition-all shadow-sm"
                            />
                        </div>
                        <button
                            type="button"
                            onClick={aplicarConsulta}
                            className="w-full sm:w-auto shrink-0 px-6 py-4 rounded-xl font-black uppercase text-[10px] tracking-widest text-white hover:scale-105 transition-all shadow-md flex items-center justify-center gap-2"
                            style={{ backgroundColor: 'var(--color-primario)' }}
                            aria-label={etiquetaBuscar}
                        >
                            <Search className="w-4 h-4 shrink-0" />
                            Buscar
                        </button>
                    </div>
                    <button
                        type="button"
                        onClick={() => setMostrarAdicionales((v) => !v)}
                        aria-expanded={mostrarAdicionales}
                        className={`flex items-center justify-center gap-2 px-4 py-3 rounded-xl border text-[10px] font-black uppercase tracking-widest transition-all shrink-0 w-full sm:w-auto ${mostrarAdicionales || filtrosActivos > 0 || tipoFecha !== 'TODAS' ? 'border-[var(--color-primario)] text-[var(--color-primario)] bg-[color-mix(in_srgb,var(--color-primario)_10%,transparent)]' : 'theme-border theme-element theme-text-muted hover:border-[var(--color-primario)]'}`}
                    >
                        <SlidersHorizontal className="w-4 h-4 shrink-0" />
                        <span>Más filtros</span>
                        {filtrosActivos > 0 && (
                            <span className="w-5 h-5 rounded-full text-white text-[9px] flex items-center justify-center" style={{ backgroundColor: 'var(--color-primario)' }}>
                                {filtrosActivos}
                            </span>
                        )}
                    </button>
                </div>
            </div>

            {mostrarAdicionales && (
                <div className="theme-surface rounded-2xl border theme-border p-4 md:p-5 shadow-sm space-y-4">
                    <div className="flex items-center justify-between gap-3">
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted flex items-center gap-2 m-0">
                            <Filter className="w-3.5 h-3.5" /> Más filtros
                        </p>
                        <p className="text-[9px] font-bold theme-text-muted m-0 hidden sm:block">
                            Los cambios se aplican al pulsar «Buscar»
                        </p>
                        {(filtrosActivos > 0 || tipoFecha !== 'TODAS') && (
                            <button
                                type="button"
                                onClick={limpiarPanelAdicionales}
                                className="text-[9px] font-black uppercase tracking-widest theme-text-muted hover:text-red-500 flex items-center gap-1 transition-colors shrink-0"
                            >
                                <X className="w-3 h-3" /> Limpiar
                            </button>
                        )}
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <label htmlFor={`${idPrefixFechas}-tipo`} className="text-[10px] font-black uppercase tracking-widest theme-text-muted flex items-center gap-1">
                            <Calendar className="w-3 h-3" /> Periodo
                        </label>
                        <select
                            id={`${idPrefixFechas}-tipo`}
                            value={tipoFechaLocal}
                            onChange={(e) => setTipoFechaLocal(e.target.value)}
                            className="w-full px-4 py-2.5 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 cursor-pointer"
                        >
                            <option value="TODAS">Histórico completo</option>
                            <option value="HOY">Solo hoy</option>
                            <option value="AYER">Ayer</option>
                            <option value="SEMANA">Esta semana</option>
                            <option value="MES">Este mes</option>
                            <option value="PERSONALIZADO">Rango personalizado</option>
                        </select>
                    </div>

                    {tipoFechaLocal === 'PERSONALIZADO' && (
                        <RangoFechasPersonalizado
                            idPrefix={idPrefixFechas}
                            fechaInicio={fechaInicioLocal}
                            fechaFin={fechaFinLocal}
                            mostrarBotonAplicar={false}
                            onCambio={({ fecha_inicio, fecha_fin }) => {
                                if (fecha_inicio !== undefined) setFechaInicioLocal(fecha_inicio);
                                if (fecha_fin !== undefined) setFechaFinLocal(fecha_fin);
                            }}
                        />
                    )}

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="flex flex-col gap-1.5">
                            <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Asesor / Vendedor</label>
                            <select
                                value={vendedorLocal}
                                onChange={(e) => setVendedorLocal(e.target.value)}
                                className="w-full px-4 py-2.5 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 cursor-pointer"
                            >
                                <option value="">Todos los asesores</option>
                                {vendedores.map((v) => (
                                    <option key={v.id} value={v.id}>{v.name}</option>
                                ))}
                            </select>
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted flex items-center gap-1">
                                <AlertOctagon className="w-3 h-3" /> Motivo incidencia
                            </label>
                            <select
                                value={motivoLocal}
                                onChange={(e) => setMotivoLocal(e.target.value)}
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

                    <div className="flex justify-end pt-1 sm:hidden">
                        <button
                            type="button"
                            onClick={aplicarConsulta}
                            className="w-full px-6 py-3 rounded-xl font-black uppercase text-[10px] tracking-widest text-white flex items-center justify-center gap-2"
                            style={{ backgroundColor: 'var(--color-primario)' }}
                        >
                            <Search className="w-4 h-4" />
                            Buscar
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
