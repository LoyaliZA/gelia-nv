import React, { useState, useEffect, useCallback } from 'react';
import { Search, SlidersHorizontal, ChevronDown } from 'lucide-react';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { FACTURAS_TABS } from './facturasFiltros';
import { BTN_PRIMARY } from './facturasStyles';
import { GELIA_SEGMENT_TABS_SCROLL, GELIA_SEGMENT_TABS_TRACK } from '../../../utils/geliaTheme';

export default function FiltrosFacturas({
    filtros = {},
    vendedores = [],
    tabActiva,
    onTabChange,
    onAplicarFiltros,
    listaCargando = false,
}) {
    const [busqueda, setBusqueda] = useState(filtros.q || '');
    const [vendedorId, setVendedorId] = useState(filtros.vendedor_id || '');

    useEffect(() => {
        setBusqueda(filtros.q || '');
        setVendedorId(filtros.vendedor_id || '');
    }, [filtros.q, filtros.vendedor_id]);

    const enviarBusqueda = useCallback(
        (valor) => {
            const termino = valor.trim();
            onAplicarFiltros?.({ q: termino || undefined, vendedor_id: vendedorId || undefined, page: 1 });
        },
        [onAplicarFiltros, vendedorId]
    );

    const handleBusquedaKeyDown = (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        enviarBusqueda(busqueda);
    };

    const handleVendedor = (valor) => {
        setVendedorId(valor);
        onAplicarFiltros?.({
            q: filtros.q || undefined,
            vendedor_id: valor || undefined,
            page: 1,
        });
    };

    return (
        <section
            className={`${geliaCardClass('overflow-hidden')} ${listaCargando ? 'opacity-90 pointer-events-none' : ''}`}
            aria-label="Filtros de facturas"
            aria-busy={listaCargando}
        >
            <div className="p-4 md:p-5 border-b theme-border flex items-center gap-3">
                <div className="p-2 rounded-xl theme-element border theme-border shrink-0">
                    <SlidersHorizontal className="w-4 h-4 theme-text-muted" aria-hidden />
                </div>
                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 min-w-0 flex-1">Filtros</p>
            </div>

            <div className={`${GELIA_SEGMENT_TABS_SCROLL} p-3 md:p-4 border-b theme-border`}>
                <div className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK} p-1 shadow-sm`} role="tablist" aria-label="Estado de solicitudes">
                    {FACTURAS_TABS.map((tab) => (
                        <button
                            key={tab.id}
                            type="button"
                            role="tab"
                            aria-selected={tabActiva === tab.id}
                            onClick={() => onTabChange(tab.id)}
                            className="gelia-segment-btn whitespace-nowrap"
                            data-active={tabActiva === tab.id}
                        >
                            {tab.label}
                        </button>
                    ))}
                </div>
            </div>

            <div className="p-4 md:p-5 grid grid-cols-1 md:grid-cols-[1fr_auto] gap-4 items-end">
                <div className="min-w-0">
                    <label htmlFor="facturas-busqueda" className="theme-label ml-1">
                        Buscar
                    </label>
                    <div className="flex flex-col sm:flex-row gap-2 mt-1.5">
                        <div className="theme-field-with-icon min-w-0 flex-1">
                            <Search className="theme-field-icon" aria-hidden />
                            <input
                                id="facturas-busqueda"
                                type="text"
                                value={busqueda}
                                onChange={(e) => setBusqueda(e.target.value)}
                                onKeyDown={handleBusquedaKeyDown}
                                placeholder="Folio, razón social o RFC"
                                autoComplete="off"
                                enterKeyHint="search"
                                className="theme-input w-full pr-4 py-3 normal-case tracking-normal font-bold text-sm"
                            />
                        </div>
                        <button
                            type="button"
                            onClick={() => enviarBusqueda(busqueda)}
                            disabled={listaCargando}
                            className={`${BTN_PRIMARY} w-full sm:w-auto shrink-0 px-6`}
                            aria-label="Buscar solicitudes de factura"
                        >
                            <Search className="w-4 h-4 shrink-0" aria-hidden />
                            Buscar
                        </button>
                    </div>
                </div>
                <div className="w-full md:w-56 shrink-0">
                    <label htmlFor="facturas-vendedor" className="theme-label ml-1">
                        Vendedor
                    </label>
                    <div className="relative mt-1.5">
                        <select
                            id="facturas-vendedor"
                            value={vendedorId}
                            onChange={(e) => handleVendedor(e.target.value)}
                            className="theme-select w-full py-3 pr-10"
                        >
                            <option value="">Todos</option>
                            {vendedores.map((v) => (
                                <option key={v.id} value={String(v.id)}>{v.name}</option>
                            ))}
                        </select>
                        <div className="pointer-events-none absolute inset-y-0 right-3 flex items-center" aria-hidden>
                            <ChevronDown className="w-4 h-4 theme-text-muted" />
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
