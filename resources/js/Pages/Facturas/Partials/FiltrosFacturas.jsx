import React from 'react';
import { router } from '@inertiajs/react';
import { Search, Filter } from 'lucide-react';

const TABS = ['TODAS', 'PENDIENTES', 'RESPONDIDAS', 'VERIFICADAS', 'INCORRECTAS'];

export default function FiltrosFacturas({ filtros, vendedores, tabActiva, onTabChange }) {
    const aplicar = (campo, valor) => {
        router.get(route('facturas.index'), { ...filtros, [campo]: valor, tab: tabActiva }, { preserveState: true, replace: true });
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap gap-2">
                {TABS.map(tab => (
                    <button
                        key={tab}
                        type="button"
                        onClick={() => onTabChange(tab)}
                        className={`px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest border transition-colors outline-none ${
                            tabActiva === tab
                                ? 'text-white border-transparent'
                                : 'theme-element theme-border theme-text-muted hover:border-[var(--color-primario)]'
                        }`}
                        style={tabActiva === tab ? { backgroundColor: 'var(--color-primario)', borderColor: 'var(--color-primario)' } : undefined}
                    >
                        {tab}
                    </button>
                ))}
            </div>

            <div className="flex flex-wrap gap-3 items-center">
                <div className="relative flex-1 min-w-[200px]">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                    <input
                        type="text"
                        defaultValue={filtros.q || ''}
                        placeholder="Buscar folio, razón social, RFC…"
                        className="w-full pl-10 pr-4 py-2.5 theme-surface border theme-border rounded-xl text-sm font-bold theme-text-main outline-none"
                        onKeyDown={e => { if (e.key === 'Enter') aplicar('q', e.target.value); }}
                    />
                </div>
                <select
                    value={filtros.vendedor_id || ''}
                    onChange={e => aplicar('vendedor_id', e.target.value || undefined)}
                    className="px-4 py-2.5 theme-surface border theme-border rounded-xl text-[10px] font-black uppercase theme-text-main outline-none"
                >
                    <option value="">Todos los vendedores</option>
                    {vendedores.map(v => (
                        <option key={v.id} value={v.id}>{v.name}</option>
                    ))}
                </select>
                <Filter className="w-4 h-4 theme-text-muted hidden sm:block" />
            </div>
        </div>
    );
}
