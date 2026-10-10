import React, { useEffect, useState } from 'react';
import { Search, SlidersHorizontal, X } from 'lucide-react';
import { TIPOS_OPERATIVO, BTN_PRIMARY, BTN_SECONDARY } from './operativasStyles';

const TABS = [
    ['TODAS', 'Todas'], ['PENDIENTES', 'Pendientes'], ['RESPONDIDAS', 'Respondidas'],
    ['VERIFICADAS', 'Verificadas'], ['INCORRECTAS', 'Incorrectas'], ['CANCELADAS', 'Canceladas'],
];

export default function FiltrosOperativas({ tabActiva, busqueda = '', tipoOperativo, fechaInicio = '', fechaFin = '', filtroVendedor = '', vendedores = [], filtrosActivos = 0, onCambiarTab, onAplicarFiltros, onCambiarTipo, onLimpiarAdicionales, listaCargando = false }) {
    const [mostrarAdicionales, setMostrarAdicionales] = useState(filtrosActivos > 0);
    const [busquedaLocal, setBusquedaLocal] = useState(busqueda);
    useEffect(() => setBusquedaLocal(busqueda), [busqueda]);
    useEffect(() => { if (filtrosActivos > 0) setMostrarAdicionales(true); }, [filtrosActivos]);
    return (
        <section className="theme-card theme-surface border theme-border p-4 space-y-4" aria-label="Filtros de solicitudes operativas" aria-busy={listaCargando}>
            <form onSubmit={(event) => { event.preventDefault(); onAplicarFiltros({ q: busquedaLocal.trim() || undefined, page: 1 }); }} className="flex flex-wrap items-end gap-2">
                <div className="flex-1 min-w-[min(100%,15rem)]">
                    <label htmlFor="operativas-busqueda" className="theme-label block mb-1.5">Buscar solicitudes</label>
                    <div className="theme-field-with-icon"><Search className="theme-field-icon" aria-hidden="true" /><input id="operativas-busqueda" name="q" type="search" value={busquedaLocal} onChange={(event) => setBusquedaLocal(event.target.value)} placeholder="Folio, remisión, pedido o cliente…" autoComplete="off" enterKeyHint="search" className="theme-input w-full" /></div>
                </div>
                <button type="submit" disabled={listaCargando} className={BTN_PRIMARY}><Search className="w-4 h-4" aria-hidden="true" /> Buscar</button>
                <button type="button" className={BTN_SECONDARY} aria-expanded={mostrarAdicionales} aria-controls="operativas-filtros-extra" onClick={() => setMostrarAdicionales((valor) => !valor)}><SlidersHorizontal className="w-4 h-4" aria-hidden="true" />{mostrarAdicionales ? 'Ocultar filtros' : 'Más filtros'}{filtrosActivos > 0 ? ` (${filtrosActivos})` : ''}</button>
            </form>
            <div className="gelia-operativas-filtros-grupos">
                <div className="min-w-0"><p className="text-xs theme-text-muted mb-1.5 mt-0">Estado</p><div className="gelia-filtros-scroll"><div className="gelia-segment p-1" role="group" aria-label="Estado de solicitudes">{TABS.map(([id, label]) => <button key={id} type="button" className="gelia-segment-btn whitespace-nowrap" aria-pressed={tabActiva === id} data-active={tabActiva === id} onClick={() => onCambiarTab(id)}>{label}</button>)}</div></div></div>
                <div className="min-w-0"><p className="text-xs theme-text-muted mb-1.5 mt-0">Tipo de operación</p><div className="gelia-filtros-scroll"><div className="gelia-segment p-1" role="group" aria-label="Tipo de operación">{TIPOS_OPERATIVO.map(({ id, label }) => <button key={id || 'todos'} type="button" className="gelia-segment-btn whitespace-nowrap" aria-pressed={tipoOperativo === id} data-active={tipoOperativo === id} onClick={() => onCambiarTipo(id)}>{label}</button>)}</div></div></div>
            </div>
            {mostrarAdicionales && <div id="operativas-filtros-extra" className="grid grid-cols-1 sm:grid-cols-3 gap-3 border-t theme-border pt-4">
                <div><label htmlFor="operativas-vendedor" className="theme-label block mb-1.5">Vendedor</label><select id="operativas-vendedor" name="vendedor_id" value={filtroVendedor} className="theme-select w-full" onChange={(event) => onAplicarFiltros({ vendedor_id: event.target.value || undefined, page: 1 })}><option value="">Todos</option>{vendedores.map((v) => <option key={v.id} value={v.id}>{v.name}</option>)}</select></div>
                <div><label htmlFor="operativas-desde" className="theme-label block mb-1.5">Desde</label><input id="operativas-desde" name="fecha_inicio" type="date" value={fechaInicio} max={fechaFin || undefined} className="theme-input w-full" onChange={(event) => onAplicarFiltros({ fecha_inicio: event.target.value, fecha_fin: fechaFin, page: 1 })} /></div>
                <div><label htmlFor="operativas-hasta" className="theme-label block mb-1.5">Hasta</label><input id="operativas-hasta" name="fecha_fin" type="date" value={fechaFin} min={fechaInicio || undefined} className="theme-input w-full" onChange={(event) => onAplicarFiltros({ fecha_inicio: fechaInicio, fecha_fin: event.target.value, page: 1 })} /></div>
                {filtrosActivos > 0 && <button type="button" className={`${BTN_SECONDARY} sm:col-span-3 justify-self-end`} onClick={() => onLimpiarAdicionales?.()}><X className="w-4 h-4" aria-hidden="true" /> Limpiar filtros avanzados</button>}
            </div>}
        </section>
    );
}
