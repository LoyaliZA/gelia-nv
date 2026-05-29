import React, { useState, useEffect, useCallback } from 'react';
import { Search, SlidersHorizontal, ChevronDown } from 'lucide-react';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { FACTURAS_TABS } from './facturasFiltros';

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
            onAplicarFiltros?.({ q: valor.trim() || undefined, vendedor_id: vendedorId || undefined, page: 1 });
        },
        [onAplicarFiltros, vendedorId]
    );

    useEffect(() => {
        const t = setTimeout(() => {
            if (busqueda !== (filtros.q || '')) {
                enviarBusqueda(busqueda);
            }
        }, 400);
        return () => clearTimeout(t);
    }, [busqueda, filtros.q, enviarBusqueda]);

    const handleVendedor = (valor) => {
        setVendedorId(valor);
        onAplicarFiltros?.({
            q: busqueda.trim() || undefined,
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

            <div className="p-3 md:p-4 border-b theme-border overflow-x-auto">
                <div className="gelia-segment inline-flex flex-nowrap min-w-full sm:min-w-0 w-full sm:w-auto p-1 gap-0.5 shadow-sm">
                    {FACTURAS_TABS.map((tab) => (
                        <button
                            key={tab.id}
                            type="button"
                            onClick={() => onTabChange(tab.id)}
                            className="gelia-segment-btn flex-1 sm:flex-none min-w-[4.5rem] px-3 sm:px-4 py-2.5 text-[10px] font-black uppercase tracking-widest whitespace-nowrap"
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
                    <div className="theme-field-with-icon mt-1.5">
                        <Search className="theme-field-icon" aria-hidden />
                        <input
                            id="facturas-busqueda"
                            type="search"
                            value={busqueda}
                            onChange={(e) => setBusqueda(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') enviarBusqueda(e.target.value);
                            }}
                            placeholder="Folio, razón social, RFC…"
                            className="theme-input w-full pr-4 py-3 normal-case tracking-normal font-bold text-sm"
                        />
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
