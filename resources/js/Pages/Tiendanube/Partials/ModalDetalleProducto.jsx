import React, { useCallback, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { Pencil, X } from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';
import ModalEditarProducto from './ModalEditarProducto';

function textoIdioma(valor) {
    if (!valor) return '—';
    if (typeof valor === 'string') return valor;
    return valor.es || valor.es_MX || Object.values(valor)[0] || '—';
}

export default function ModalDetalleProducto({ productoId, categorias = [], canEdit = false, onClose, onChanged }) {
    const [producto, setProducto] = useState(null);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(true);
    const [editing, setEditing] = useState(false);

    const load = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const res = await fetch(route('tiendanube.productos.show', productoId), {
                headers: { Accept: 'application/json' },
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'No se pudo cargar el producto.');
            setProducto(data);
        } catch (err) {
            setError(err.message);
        } finally {
            setLoading(false);
        }
    }, [productoId]);

    useEffect(() => {
        load();
    }, [load]);

    return createPortal(
        <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 md:p-8 bg-black/60 backdrop-blur-md">
            <GeliaLoader isVisible={loading} message="Cargando producto_" />
            <div className="w-full max-w-3xl theme-surface border theme-border rounded-[2.5rem] p-6 md:p-10 max-h-[90vh] overflow-y-auto relative">
                <button type="button" onClick={onClose} className="absolute top-6 right-6 p-3 theme-text-muted hover:theme-text-main">
                    <X className="w-5 h-5" />
                </button>

                {error && <p className="text-sm font-bold text-red-500">{error}</p>}

                {producto && (
                    <div className="space-y-6">
                        <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3 pr-10">
                            <div>
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Producto #{producto.id}</p>
                                <h2 className="text-2xl font-black italic uppercase theme-text-main mt-1">{producto.nombre}</h2>
                                <p className="text-xs theme-text-muted mt-1">{producto.brand || ''} · {producto.published ? 'Publicado' : 'Oculto'}</p>
                            </div>
                            {canEdit && (
                                <button
                                    type="button"
                                    onClick={() => setEditing(true)}
                                    className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-[10px] font-black uppercase border theme-border theme-text-main"
                                >
                                    <Pencil className="w-4 h-4" /> Editar
                                </button>
                            )}
                        </div>

                        <section>
                            <h3 className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">SEO</h3>
                            <p className="text-sm font-bold theme-text-main">{producto.seo_title || '—'}</p>
                            <p className="text-xs theme-text-muted mt-1">{producto.seo_description || '—'}</p>
                            <p className="text-[10px] theme-text-muted mt-2 font-mono">handle: {textoIdioma(producto.handle)}</p>
                        </section>

                        <section>
                            <h3 className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Descripción</h3>
                            <div
                                className="prose prose-sm dark:prose-invert max-w-none theme-text-muted"
                                dangerouslySetInnerHTML={{ __html: textoIdioma(producto.description) }}
                            />
                        </section>

                        {producto.attributes?.length > 0 && (
                            <section>
                                <h3 className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Atributos</h3>
                                <p className="text-sm theme-text-main">
                                    {producto.attributes.map((attr) => textoIdioma(attr)).filter(Boolean).join(', ')}
                                </p>
                            </section>
                        )}

                        {producto.categorias?.length > 0 && (
                            <section>
                                <h3 className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Categorías</h3>
                                <div className="flex flex-wrap gap-2">
                                    {producto.categorias.map((c) => (
                                        <span key={c.id} className="text-[10px] font-bold px-2 py-1 rounded-lg border theme-border theme-text-muted">
                                            {c.nombre}
                                        </span>
                                    ))}
                                </div>
                            </section>
                        )}

                        {producto.imagenes?.length > 0 && (
                            <section>
                                <h3 className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Imágenes</h3>
                                <div className="flex flex-wrap gap-3">
                                    {producto.imagenes.map((img) => {
                                        const motivos = [];
                                        if (img.alerta_pequena) motivos.push('<800px');
                                        if (img.alerta_no_cuadrada) motivos.push('no cuadrada');
                                        return (
                                            <div key={img.id} className="w-24 space-y-1">
                                                <a href={img.src} target="_blank" rel="noreferrer" className="block">
                                                    <img
                                                        src={img.src}
                                                        alt={img.alt || ''}
                                                        className={`w-20 h-20 object-cover rounded-xl border ${
                                                            img.requiere_revision ? 'border-amber-500' : 'theme-border'
                                                        }`}
                                                    />
                                                </a>
                                                <p className="text-[9px] font-mono theme-text-muted">
                                                    {img.width && img.height ? `${img.width}×${img.height}` : 'sin medida'}
                                                </p>
                                                {motivos.length > 0 && (
                                                    <p className="text-[9px] font-bold uppercase text-amber-600 leading-tight">
                                                        {motivos.join(' · ')}
                                                    </p>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </section>
                        )}

                        {producto.variantes?.length > 0 && (
                            <section>
                                <h3 className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Variantes</h3>
                                <div className="overflow-x-auto">
                                    <table className="w-full text-left text-xs">
                                        <thead>
                                            <tr className="border-b theme-border text-[10px] font-black uppercase tracking-widest theme-text-muted">
                                                <th className="py-2 pr-2">SKU</th>
                                                <th className="py-2 pr-2">Valores</th>
                                                <th className="py-2 pr-2">Precio</th>
                                                <th className="py-2">Stock</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {producto.variantes.map((v) => (
                                                <tr key={v.id} className="border-b theme-border/40">
                                                    <td className="py-2 pr-2 font-mono font-bold theme-text-main">{v.sku || '—'}</td>
                                                    <td className="py-2 pr-2 theme-text-muted">
                                                        {Array.isArray(v.values) && v.values.length
                                                            ? v.values.map((x) => textoIdioma(x)).filter((t) => t && t !== '—').join(' / ') || '—'
                                                            : '—'}
                                                    </td>
                                                    <td className="py-2 pr-2 font-bold theme-text-main">
                                                        {v.price != null ? `$${Number(v.price).toFixed(2)}` : '—'}
                                                    </td>
                                                    <td className="py-2 theme-text-muted">
                                                        {v.stock_management ? (v.stock ?? 0) : '∞'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        )}
                    </div>
                )}
            </div>

            {editing && producto && (
                <ModalEditarProducto
                    producto={producto}
                    categorias={categorias}
                    onClose={() => setEditing(false)}
                    onSaved={() => {
                        setEditing(false);
                        load();
                        onChanged?.();
                    }}
                />
            )}
        </div>,
        document.body
    );
}
