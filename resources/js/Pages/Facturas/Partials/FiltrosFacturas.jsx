import React, { useEffect, useState } from 'react';
import { Search, X } from 'lucide-react';
import { FACTURAS_TABS } from './facturasFiltros';
import { BTN_PRIMARY, BTN_SECONDARY } from './facturasStyles';

export default function FiltrosFacturas({ filtros = {}, vendedores = [], tabActiva, onTabChange, onAplicarFiltros, listaCargando = false }) {
    const [busqueda, setBusqueda] = useState(filtros.q || '');
    const [vendedorId, setVendedorId] = useState(filtros.vendedor_id || '');
    useEffect(() => { setBusqueda(filtros.q || ''); setVendedorId(filtros.vendedor_id || ''); }, [filtros.q, filtros.vendedor_id]);
    const limpiar = () => { setBusqueda(''); setVendedorId(''); onAplicarFiltros({ q: undefined, vendedor_id: undefined, page: 1 }); };
    return (
        <section className="theme-card theme-surface border theme-border p-4 space-y-4" aria-label="Filtros de facturas" aria-busy={listaCargando}>
            <form onSubmit={(event) => { event.preventDefault(); onAplicarFiltros({ q: busqueda.trim() || undefined, vendedor_id: vendedorId || undefined, page: 1 }); }} className="flex flex-wrap items-end gap-3">
                <div className="flex-1 min-w-[min(100%,15rem)]"><label htmlFor="facturas-busqueda" className="theme-label block mb-1.5">Buscar facturas</label><div className="theme-field-with-icon"><Search className="theme-field-icon" aria-hidden="true" /><input id="facturas-busqueda" name="q" type="search" value={busqueda} onChange={(event) => setBusqueda(event.target.value)} placeholder="Folio, razón social o RFC…" autoComplete="off" enterKeyHint="search" className="theme-input w-full" /></div></div>
                <div className="w-full sm:w-56"><label htmlFor="facturas-vendedor" className="theme-label block mb-1.5">Vendedor</label><select id="facturas-vendedor" name="vendedor_id" value={vendedorId} className="theme-select w-full" onChange={(event) => { setVendedorId(event.target.value); onAplicarFiltros({ q: filtros.q || undefined, vendedor_id: event.target.value || undefined, page: 1 }); }}><option value="">Todos</option>{vendedores.map((v) => <option key={v.id} value={String(v.id)}>{v.name}</option>)}</select></div>
                <button type="submit" disabled={listaCargando} className={BTN_PRIMARY}><Search className="w-4 h-4" aria-hidden="true" /> Buscar</button>
            </form>
            <div className="gelia-filtros-scroll"><div className="gelia-segment p-1" role="group" aria-label="Estado de solicitudes">{FACTURAS_TABS.map((tab) => <button key={tab.id} type="button" aria-pressed={tabActiva === tab.id} className="gelia-segment-btn whitespace-nowrap" data-active={tabActiva === tab.id} onClick={() => onTabChange(tab.id)}>{tab.label}</button>)}</div></div>
            {(filtros.q || filtros.vendedor_id) && <div className="flex flex-wrap items-center gap-2"><span className="text-xs theme-text-muted break-words min-w-0">{filtros.q ? `Búsqueda: ${filtros.q}` : 'Filtro por vendedor activo'}</span><button type="button" disabled={listaCargando} className={BTN_SECONDARY} onClick={limpiar}><X className="w-4 h-4" aria-hidden="true" /> Limpiar filtros</button></div>}
        </section>
    );
}
