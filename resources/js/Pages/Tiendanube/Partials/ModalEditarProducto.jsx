import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { X, Save } from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';
import EditorDescripcionHtml from './EditorDescripcionHtml';

function textoIdioma(valor) {
    if (!valor) return '';
    if (typeof valor === 'string') return valor;
    return valor.es || valor.es_MX || Object.values(valor)[0] || '';
}

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const inputClass = 'w-full theme-element border theme-border rounded-xl px-4 py-3 text-sm theme-text-main';
const labelClass = 'block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2';

export default function ModalEditarProducto({ producto, categorias = [], onClose, onSaved }) {
    const variante = producto?.variantes?.[0] || {};
    const [form, setForm] = useState({
        name: producto?.nombre || textoIdioma(producto?.name) || '',
        description: textoIdioma(producto?.description) || '',
        brand: producto?.brand || '',
        published: !!producto?.published,
        free_shipping: !!producto?.free_shipping,
        requires_shipping: producto?.requires_shipping !== false,
        video_url: producto?.video_url || '',
        seo_title: producto?.seo_title || '',
        seo_description: producto?.seo_description || '',
        tags: producto?.tags || '',
        categories: (producto?.categoria_ids || producto?.categorias?.map((c) => c.id) || []).map(Number),
        sku: variante.sku || '',
        price: variante.price ?? '',
        promotional_price: variante.promotional_price ?? '',
        cost: variante.cost ?? '',
        stock: variante.stock ?? '',
    });
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [imageUrl, setImageUrl] = useState('');
    const [addingImage, setAddingImage] = useState(false);

    const setField = (key, value) => setForm((prev) => ({ ...prev, [key]: value }));

    const toggleCategory = (id) => {
        setForm((prev) => {
            const has = prev.categories.includes(id);
            return {
                ...prev,
                categories: has ? prev.categories.filter((c) => c !== id) : [...prev.categories, id],
            };
        });
    };

    const guardar = async (e) => {
        e.preventDefault();
        setSaving(true);
        setError(null);
        try {
            const body = {
                ...form,
                price: form.price === '' ? null : Number(form.price),
                promotional_price: form.promotional_price === '' ? null : Number(form.promotional_price),
                cost: form.cost === '' ? null : Number(form.cost),
                stock: form.stock === '' ? null : Number(form.stock),
                video_url: form.video_url || null,
            };
            const res = await fetch(route('tiendanube.productos.update', producto.id), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify(body),
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'No se pudo guardar.');
            onSaved?.(data.producto_id);
        } catch (err) {
            setError(err.message);
        } finally {
            setSaving(false);
        }
    };

    const agregarImagenUrl = async () => {
        if (!imageUrl.trim()) return;
        setAddingImage(true);
        setError(null);
        try {
            const res = await fetch(route('tiendanube.productos.imagenes.store', producto.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify({ src: imageUrl.trim() }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'No se pudo subir la imagen.');
            setImageUrl('');
            onSaved?.(producto.id);
        } catch (err) {
            setError(err.message);
        } finally {
            setAddingImage(false);
        }
    };

    return createPortal(
        <div className="fixed inset-0 z-[210] flex items-center justify-center p-4 md:p-8 bg-black/60 backdrop-blur-md">
            <GeliaLoader isVisible={saving || addingImage} message={addingImage ? 'Subiendo imagen_' : 'Guardando en Tiendanube_'} />
            <div className="w-full max-w-2xl theme-surface border theme-border rounded-[2.5rem] p-6 md:p-10 max-h-[90vh] overflow-y-auto relative">
                <button type="button" onClick={onClose} className="absolute top-6 right-6 p-3 theme-text-muted hover:theme-text-main">
                    <X className="w-5 h-5" />
                </button>

                <h2 className="text-xl font-black italic uppercase theme-text-main mb-6 pr-10">Editar producto #{producto.id}</h2>

                <form onSubmit={guardar} className="space-y-4">
                    <div>
                        <label className={labelClass}>Nombre</label>
                        <input className={inputClass} value={form.name} onChange={(e) => setField('name', e.target.value)} required />
                    </div>
                    <EditorDescripcionHtml
                        label="Descripción (HTML)"
                        value={form.description}
                        onChange={(v) => setField('description', v)}
                        minHeight={140}
                    />
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className={labelClass}>Marca</label>
                            <input className={inputClass} value={form.brand} onChange={(e) => setField('brand', e.target.value)} />
                        </div>
                        <div>
                            <label className={labelClass}>Tags</label>
                            <input className={inputClass} value={form.tags} onChange={(e) => setField('tags', e.target.value)} />
                        </div>
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className={labelClass}>SEO title</label>
                            <input className={inputClass} maxLength={70} value={form.seo_title} onChange={(e) => setField('seo_title', e.target.value)} />
                        </div>
                        <div>
                            <label className={labelClass}>Video URL</label>
                            <input className={inputClass} type="url" value={form.video_url} onChange={(e) => setField('video_url', e.target.value)} />
                        </div>
                    </div>
                    <div>
                        <label className={labelClass}>SEO description</label>
                        <textarea className={`${inputClass} min-h-[70px]`} maxLength={320} value={form.seo_description} onChange={(e) => setField('seo_description', e.target.value)} />
                    </div>

                    <div className="flex flex-wrap gap-4 text-xs font-bold theme-text-main">
                        <label className="inline-flex items-center gap-2">
                            <input type="checkbox" checked={form.published} onChange={(e) => setField('published', e.target.checked)} />
                            Publicado
                        </label>
                        <label className="inline-flex items-center gap-2">
                            <input type="checkbox" checked={form.free_shipping} onChange={(e) => setField('free_shipping', e.target.checked)} />
                            Envío gratis
                        </label>
                        <label className="inline-flex items-center gap-2">
                            <input type="checkbox" checked={form.requires_shipping} onChange={(e) => setField('requires_shipping', e.target.checked)} />
                            Requiere envío
                        </label>
                    </div>

                    {categorias.length > 0 && (
                        <div>
                            <label className={labelClass}>Categorías</label>
                            <div className="flex flex-wrap gap-2 max-h-32 overflow-y-auto">
                                {categorias.map((c) => (
                                    <button
                                        key={c.id}
                                        type="button"
                                        onClick={() => toggleCategory(c.id)}
                                        className={`text-[10px] font-bold px-2 py-1 rounded-lg border theme-border ${
                                            form.categories.includes(c.id) ? 'text-white' : 'theme-text-muted'
                                        }`}
                                        style={form.categories.includes(c.id) ? { backgroundColor: 'var(--color-primario)' } : {}}
                                    >
                                        {c.nombre}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="pt-2 border-t theme-border">
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-3">Variante virtual</p>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className={labelClass}>SKU</label>
                                <input className={inputClass} value={form.sku} onChange={(e) => setField('sku', e.target.value)} />
                            </div>
                            <div>
                                <label className={labelClass}>Stock (vacío = ilimitado)</label>
                                <input className={inputClass} type="number" min="0" value={form.stock} onChange={(e) => setField('stock', e.target.value)} />
                            </div>
                            <div>
                                <label className={labelClass}>Precio</label>
                                <input className={inputClass} type="number" step="0.01" min="0" value={form.price} onChange={(e) => setField('price', e.target.value)} />
                            </div>
                            <div>
                                <label className={labelClass}>Precio promo</label>
                                <input className={inputClass} type="number" step="0.01" min="0" value={form.promotional_price} onChange={(e) => setField('promotional_price', e.target.value)} />
                            </div>
                            <div>
                                <label className={labelClass}>Costo</label>
                                <input className={inputClass} type="number" step="0.01" min="0" value={form.cost} onChange={(e) => setField('cost', e.target.value)} />
                            </div>
                        </div>
                    </div>

                    <div className="pt-2 border-t theme-border">
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-3">Agregar imagen (URL)</p>
                        <div className="flex gap-2">
                            <input className={inputClass} type="url" placeholder="https://…" value={imageUrl} onChange={(e) => setImageUrl(e.target.value)} />
                            <button type="button" onClick={agregarImagenUrl} className="px-4 py-2 rounded-xl text-[10px] font-black uppercase border theme-border theme-text-main shrink-0">
                                Subir
                            </button>
                        </div>
                    </div>

                    {error && <p className="text-xs font-bold text-red-500">{error}</p>}

                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" onClick={onClose} className="px-5 py-3 rounded-xl text-xs font-black uppercase border theme-border theme-text-main">
                            Cancelar
                        </button>
                        <button type="submit" className="inline-flex items-center gap-2 px-5 py-3 rounded-xl text-xs font-black uppercase text-white" style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Save className="w-4 h-4" /> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body
    );
}
