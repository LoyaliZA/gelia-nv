import React from 'react';
import { router } from '@inertiajs/react';
import { geliaCardClass } from '../../../utils/geliaTheme';

export default function TablaProductos({ productos, filters, permisos }) {
    const handleSearch = (e) => {
        e.preventDefault();
        const q = e.target.search.value;
        router.get(route('woocommerce.index'), { ...filters, search: q }, { preserveState: true });
    };

    return (
        <div className={`${geliaCardClass()} p-6 md:p-8`}>
            <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
                <h2 className="text-lg font-black uppercase theme-text-main">Inventario Local ({productos.total})</h2>
                <form onSubmit={handleSearch} className="flex gap-2 w-full md:w-auto">
                    <input name="search" defaultValue={filters.search || ''} placeholder="Buscar SKU o nombre..."
                        className="flex-1 md:w-64 px-4 py-2 text-sm theme-surface border theme-border rounded-xl theme-text-main" />
                    <button type="submit" className="px-4 py-2 rounded-xl text-[10px] font-black uppercase text-white" style={{ backgroundColor: 'var(--color-primario)' }}>Buscar</button>
                </form>
            </div>
            <div className="overflow-x-auto rounded-2xl border theme-border">
                <table className="w-full text-xs">
                    <thead>
                        <tr className="text-[10px] font-black uppercase tracking-widest theme-text-muted border-b theme-border">
                            <th className="p-3 text-left">SKU</th>
                            <th className="p-3 text-left">Nombre</th>
                            <th className="p-3 text-right">Normal</th>
                            <th className="p-3 text-right">Rebaja</th>
                            <th className="p-3 text-center">Tipo</th>
                        </tr>
                    </thead>
                    <tbody>
                        {productos.data.map((p) => (
                            <tr key={p.id} className="border-b theme-border/50 hover:bg-black/5 dark:hover:bg-white/5">
                                <td className="p-3 font-bold theme-text-main">{p.sku}</td>
                                <td className="p-3 theme-text-muted truncate max-w-[200px]">{p.nombre}</td>
                                <td className="p-3 text-right theme-text-main">${Number(p.precio_normal ?? 0).toFixed(2)}</td>
                                <td className="p-3 text-right text-emerald-500">${Number(p.precio_rebajado ?? 0).toFixed(2)}</td>
                                <td className="p-3 text-center"><span className="px-2 py-0.5 rounded text-[10px] font-black uppercase theme-element">{p.tipo}</span></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {productos.last_page > 1 && (
                <div className="flex justify-center gap-2 mt-4">
                    {productos.links?.map((link, i) => (
                        link.url ? (
                            <button key={i} onClick={() => router.get(link.url)} dangerouslySetInnerHTML={{ __html: link.label }}
                                className={`px-3 py-1 rounded text-xs font-bold ${link.active ? 'text-white' : 'theme-text-muted border theme-border'}`}
                                style={link.active ? { backgroundColor: 'var(--color-primario)' } : {}} />
                        ) : null
                    ))}
                </div>
            )}
        </div>
    );
}
