import React from 'react';
import { router } from '@inertiajs/react';
import { inertiaVisitUrl } from '../../../utils/inertiaVisitUrl';

export default function TablaProductos({ productos, onSelect }) {
    const rows = productos?.data || [];

    if (!rows.length) {
        return (
            <p className="text-sm theme-text-muted py-8 text-center">
                No hay productos en el catálogo. Configura la API y sincroniza desde Herramientas.
            </p>
        );
    }

    const irAPagina = (url) => {
        const href = inertiaVisitUrl(url);
        if (!href) return;
        router.get(href, {}, { preserveState: true, preserveScroll: true });
    };

    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
                <thead>
                    <tr className="border-b theme-border text-[10px] font-black uppercase tracking-widest theme-text-muted">
                        <th className="py-3 pr-3">ID</th>
                        <th className="py-3 pr-3">Producto</th>
                        <th className="py-3 pr-3">SKU</th>
                        <th className="py-3 pr-3">SEO</th>
                        <th className="py-3 pr-3">Estado</th>
                        <th className="py-3" />
                    </tr>
                </thead>
                <tbody>
                    {rows.map((p) => (
                        <tr key={p.id} className="border-b theme-border/50 hover:bg-black/[0.02] dark:hover:bg-white/[0.02]">
                            <td className="py-3 pr-3 font-mono text-xs theme-text-muted">{p.id}</td>
                            <td className="py-3 pr-3">
                                <div className="flex items-center gap-3">
                                    {p.imagen ? (
                                        <img src={p.imagen} alt="" className="w-10 h-10 rounded-lg object-cover border theme-border" />
                                    ) : (
                                        <div className="w-10 h-10 rounded-lg bg-zinc-100 dark:bg-zinc-800" />
                                    )}
                                    <div>
                                        <p className="font-bold theme-text-main line-clamp-1">{p.nombre || '—'}</p>
                                        <p className="text-[10px] theme-text-muted">{p.brand || ''}</p>
                                    </div>
                                </div>
                            </td>
                            <td className="py-3 pr-3 font-mono text-xs">{p.sku || '—'}</td>
                            <td className="py-3 pr-3 text-xs theme-text-muted line-clamp-1 max-w-[200px]">{p.seo_title || '—'}</td>
                            <td className="py-3 pr-3">
                                <div className="flex flex-wrap items-center gap-1.5">
                                    <span className={`text-[10px] font-black uppercase px-2 py-1 rounded-lg ${
                                        p.published
                                            ? 'bg-emerald-500/10 text-emerald-600'
                                            : 'bg-zinc-500/10 theme-text-muted'
                                    }`}>
                                        {p.published ? 'Publicado' : 'Oculto'}
                                    </span>
                                    {p.tiene_alerta_imagenes && (
                                        <span className="text-[10px] font-black uppercase px-2 py-1 rounded-lg bg-amber-500/10 text-amber-700 dark:text-amber-400">
                                            Revisar imagen
                                        </span>
                                    )}
                                </div>
                            </td>
                            <td className="py-3 text-right">
                                <button
                                    type="button"
                                    onClick={() => onSelect(p.id)}
                                    className="text-[10px] font-black uppercase tracking-widest"
                                    style={{ color: 'var(--color-primario)' }}
                                >
                                    Ver
                                </button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            {productos?.links && (
                <div className="flex flex-wrap gap-2 justify-center pt-4">
                    {productos.links.map((link, i) => (
                        <button
                            key={i}
                            type="button"
                            disabled={!link.url}
                            onClick={() => irAPagina(link.url)}
                            className={`px-3 py-1.5 rounded-lg text-xs font-bold border theme-border ${
                                link.active ? 'text-white' : 'theme-text-muted'
                            } ${!link.url ? 'opacity-40 cursor-not-allowed' : ''}`}
                            style={link.active ? { backgroundColor: 'var(--color-primario)' } : {}}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}
