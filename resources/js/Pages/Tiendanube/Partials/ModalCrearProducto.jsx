import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { X, Plus } from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';
import EditorDescripcionHtml from './EditorDescripcionHtml';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
const inputClass = 'w-full theme-element border theme-border rounded-xl px-4 py-3 text-sm theme-text-main';
const labelClass = 'block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2';

export default function ModalCrearProducto({ categorias = [], inventario = {}, ubicaciones = [], onClose, onCreated }) {
    const escrituraHabilitada = !!inventario.escritura_habilitada;
    const stockPlano = !escrituraHabilitada && inventario.locations_probe !== 'ok' && !inventario.multi_activo;
    const defaultLoc = ubicaciones.length === 1 ? ubicaciones[0].id : '';
    const [form, setForm] = useState({
        name: '',
        description: '',
        brand: '',
        published: true,
        free_shipping: false,
        requires_shipping: true,
        video_url: '',
        seo_title: '',
        seo_description: '',
        tags: '',
        categories: [],
        sku: '',
        price: '',
        promotional_price: '',
        cost: '',
        stock: '',
        location_id: defaultLoc,
        image_url: '',
    });
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);

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

    const crear = async (e) => {
        e.preventDefault();
        setSaving(true);
        setError(null);
        try {
            const body = {
                name: form.name,
                description: form.description || null,
                brand: form.brand || null,
                published: form.published,
                free_shipping: form.free_shipping,
                requires_shipping: form.requires_shipping,
                video_url: form.video_url || null,
                seo_title: form.seo_title || null,
                seo_description: form.seo_description || null,
                tags: form.tags || null,
                categories: form.categories,
                sku: form.sku || null,
                price: form.price === '' ? null : Number(form.price),
                promotional_price: form.promotional_price === '' ? null : Number(form.promotional_price),
                cost: form.cost === '' ? null : Number(form.cost),
                image_urls: form.image_url.trim() ? [form.image_url.trim()] : [],
            };
            if (escrituraHabilitada) {
                if (form.location_id) {
                    body.location_id = form.location_id;
                    body.stock = form.stock === '' ? null : Number(form.stock);
                }
            } else if (stockPlano) {
                body.stock = form.stock === '' ? null : Number(form.stock);
            }
            const res = await fetch(route('tiendanube.productos.store'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
                body: JSON.stringify(body),
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'No se pudo crear.');
            onCreated?.(data.producto_id);
        } catch (err) {
            setError(err.message);
        } finally {
            setSaving(false);
        }
    };

    return createPortal(
        <div className="fixed inset-0 z-[210] flex items-center justify-center p-4 md:p-8 bg-black/60 backdrop-blur-md">
            <GeliaLoader isVisible={saving} message="Creando producto en Tiendanube_" />
            <div className="w-full max-w-2xl theme-surface border theme-border rounded-[2.5rem] p-6 md:p-10 max-h-[90vh] overflow-y-auto relative">
                <button type="button" onClick={onClose} className="absolute top-6 right-6 p-3 theme-text-muted hover:theme-text-main">
                    <X className="w-5 h-5" />
                </button>

                <h2 className="text-xl font-black italic uppercase theme-text-main mb-6 pr-10">Nuevo producto simple</h2>

                <form onSubmit={crear} className="space-y-4">
                    <div>
                        <label className={labelClass}>Nombre *</label>
                        <input className={inputClass} value={form.name} onChange={(e) => setField('name', e.target.value)} required />
                    </div>
                    <EditorDescripcionHtml
                        label="Descripción"
                        value={form.description}
                        onChange={(v) => setField('description', v)}
                        minHeight={100}
                    />
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className={labelClass}>Marca</label>
                            <input className={inputClass} value={form.brand} onChange={(e) => setField('brand', e.target.value)} />
                        </div>
                        <div>
                            <label className={labelClass}>SKU</label>
                            <input className={inputClass} value={form.sku} onChange={(e) => setField('sku', e.target.value)} />
                        </div>
                        <div>
                            <label className={labelClass}>Precio</label>
                            <input className={inputClass} type="number" step="0.01" min="0" value={form.price} onChange={(e) => setField('price', e.target.value)} />
                        </div>
                        <div>
                            <label className={labelClass}>Stock{escrituraHabilitada ? ' (ubicación)' : ''}</label>
                            {escrituraHabilitada && (
                                <select className={`${inputClass} mb-2`} value={form.location_id} onChange={(e) => setField('location_id', e.target.value)}>
                                    <option value="">Seleccionar ubicación</option>
                                    {ubicaciones.map((u) => (
                                        <option key={u.id} value={u.id}>{u.nombre}</option>
                                    ))}
                                </select>
                            )}
                            <input
                                className={inputClass}
                                type="number"
                                min="0"
                                value={form.stock}
                                onChange={(e) => setField('stock', e.target.value)}
                                disabled={!escrituraHabilitada && !stockPlano}
                                placeholder={escrituraHabilitada ? 'Vacío = ilimitado' : ''}
                            />
                            {!escrituraHabilitada && !stockPlano && (
                                <p className="mt-1 text-[10px] font-bold theme-text-muted">Location no disponible: no se escribe stock plano.</p>
                            )}
                            {escrituraHabilitada && (
                                <p className="mt-1 text-[10px] font-bold theme-text-muted">Vacío = ilimitado en esa ubicación. No se envía el total a la primera ubicación.</p>
                            )}
                        </div>
                        <div>
                            <label className={labelClass}>SEO title</label>
                            <input className={inputClass} maxLength={70} value={form.seo_title} onChange={(e) => setField('seo_title', e.target.value)} />
                        </div>
                        <div>
                            <label className={labelClass}>Imagen (URL)</label>
                            <input className={inputClass} type="url" value={form.image_url} onChange={(e) => setField('image_url', e.target.value)} />
                        </div>
                    </div>
                    <div>
                        <label className={labelClass}>SEO description</label>
                        <textarea className={`${inputClass} min-h-[60px]`} maxLength={320} value={form.seo_description} onChange={(e) => setField('seo_description', e.target.value)} />
                    </div>

                    <label className="inline-flex items-center gap-2 text-xs font-bold theme-text-main">
                        <input type="checkbox" checked={form.published} onChange={(e) => setField('published', e.target.checked)} />
                        Publicado
                    </label>

                    {categorias.length > 0 && (
                        <div>
                            <label className={labelClass}>Categorías</label>
                            <div className="flex flex-wrap gap-2 max-h-28 overflow-y-auto">
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

                    {error && <p className="text-xs font-bold text-red-500">{error}</p>}

                    <div className="flex justify-end gap-2 pt-2">
                        <button type="button" onClick={onClose} className="px-5 py-3 rounded-xl text-xs font-black uppercase border theme-border theme-text-main">
                            Cancelar
                        </button>
                        <button type="submit" className="inline-flex items-center gap-2 px-5 py-3 rounded-xl text-xs font-black uppercase text-white" style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Plus className="w-4 h-4" /> Crear
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body
    );
}
