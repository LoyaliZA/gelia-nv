import React, { useEffect, useState } from 'react';
import { Search, X } from 'lucide-react';
import CampoBusqueda from '@/Components/CampoBusqueda';
import { FACTURAS_TABS } from './facturasFiltros';
import { BTN_PRIMARY, BTN_SECONDARY } from './facturasStyles';

const claves = ['q', 'vendedor_id', 'departamento_id', 'area_id', 'fecha_inicio', 'fecha_fin'];
const valores = (filtros) => Object.fromEntries(claves.map((key) => [key, filtros[key] || '']));
export default function FiltrosFacturas({ filtros = {}, vendedores = [], departamentos = [], areas = [], tabActiva, onTabChange, onAplicarFiltros, listaCargando = false }) {
    const [local, setLocal] = useState(() => valores(filtros));
    const [errorFecha, setErrorFecha] = useState('');
    useEffect(() => { setLocal(valores(filtros)); }, [filtros.q, filtros.vendedor_id, filtros.departamento_id, filtros.area_id, filtros.fecha_inicio, filtros.fecha_fin]);
    const cambiar = (key, value) => { setLocal((prev) => ({ ...prev, [key]: value })); setErrorFecha(''); };
    const aplicar = (event) => {
        event.preventDefault();
        if (local.fecha_inicio && local.fecha_fin && local.fecha_inicio > local.fecha_fin) { setErrorFecha('La fecha inicial debe ser anterior o igual a la fecha final.'); return; }
        onAplicarFiltros({ ...Object.fromEntries(claves.map((key) => [key, local[key].toString().trim() || undefined])), page: 1 });
    };
    const activos = claves.filter((key) => filtros[key]);
    const etiquetaFiltro = (key) => {
        if (key === 'q') return `Búsqueda: ${filtros.q}`;
        const catalogo = { vendedor_id: vendedores, departamento_id: departamentos, area_id: areas }[key];
        if (catalogo) { const op = catalogo.find((item) => String(item.id) === String(filtros[key])); return `${{ vendedor_id: 'Vendedor', departamento_id: 'Departamento', area_id: 'Área principal' }[key]}: ${op?.nombre || op?.name || filtros[key]}`; }
        return `${key === 'fecha_inicio' ? 'Desde' : 'Hasta'}: ${filtros[key]}`;
    };
    return (
        <section className="theme-card theme-surface border theme-border p-4 md:p-5 space-y-4" aria-label="Filtros de facturas" aria-busy={listaCargando}>
            <div className="gelia-filtros-scroll"><div className="gelia-segment p-1" role="group" aria-label="Estado de solicitudes">{FACTURAS_TABS.map((tab) => <button key={tab.id} type="button" aria-pressed={tabActiva === tab.id} className="gelia-segment-btn whitespace-nowrap" data-active={tabActiva === tab.id} onClick={() => onTabChange(tab.id)}>{tab.label}</button>)}</div></div>
            <form onSubmit={aplicar} className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                <div className="sm:col-span-2"><label htmlFor="facturas-q" className="theme-label block mb-1.5">Buscar facturas</label><CampoBusqueda id="facturas-q" name="q" type="search" value={local.q} onChange={(event) => cambiar('q', event.target.value)} placeholder="Folio, razón social o RFC…" autoComplete="off" enterKeyHint="search" className="theme-input w-full" /></div>
                {[{ key: 'departamento_id', label: 'Departamento', opciones: departamentos }, { key: 'area_id', label: 'Área principal', opciones: areas }, { key: 'vendedor_id', label: 'Vendedor', opciones: vendedores }].map(({ key, label, opciones }) => (
                    <div key={key}><label htmlFor={`facturas-${key}`} className="theme-label block mb-1.5">{label}</label><select id={`facturas-${key}`} name={key} value={local[key]} className="theme-select w-full" onChange={(event) => cambiar(key, event.target.value)}><option value="">Todos</option>{opciones.map((op) => <option key={op.id} value={String(op.id)}>{op.nombre || op.name}</option>)}</select></div>
                ))}
                {['fecha_inicio', 'fecha_fin'].map((key) => <div key={key}><label htmlFor={`facturas-${key}`} className="theme-label block mb-1.5">{key === 'fecha_inicio' ? 'Desde' : 'Hasta'}</label><input id={`facturas-${key}`} name={key} type="date" value={local[key]} onChange={(event) => cambiar(key, event.target.value)} aria-invalid={Boolean(errorFecha)} aria-describedby={errorFecha ? 'facturas-error-fecha' : undefined} className="theme-input w-full" /></div>)}
                <button type="submit" disabled={listaCargando} className={`${BTN_PRIMARY} w-full`}><Search className="w-4 h-4" aria-hidden="true" />{listaCargando ? 'Buscando…' : 'Aplicar filtros'}</button>
                {errorFecha && <p id="facturas-error-fecha" role="alert" className="text-sm theme-text-peligro sm:col-span-2 lg:col-span-4 m-0">{errorFecha}</p>}
            </form>
            {activos.length > 0 && <div className="flex flex-wrap items-center gap-2" aria-label="Filtros aplicados">{activos.map((key) => <button key={key} type="button" disabled={listaCargando} onClick={() => onAplicarFiltros({ [key]: undefined, page: 1 })} className="gelia-facturas-filter-chip" aria-label={`Quitar ${etiquetaFiltro(key)}`}>{etiquetaFiltro(key)}<X className="w-3.5 h-3.5 shrink-0" aria-hidden="true" /></button>)}<button type="button" disabled={listaCargando} className={BTN_SECONDARY} onClick={() => { setLocal(valores({})); setErrorFecha(''); onAplicarFiltros({ ...Object.fromEntries(claves.map((key) => [key, undefined])), page: 1 }); }}>Limpiar filtros</button></div>}
        </section>
    );
}
