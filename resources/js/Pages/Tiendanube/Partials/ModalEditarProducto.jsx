import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { X, Save } from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';
import EditorDescripcionHtml from './EditorDescripcionHtml';
import { formatBytes, readImageDimensions } from '../../../utils/tiendanubeImageSku';

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
    const ubicaciones = producto?.ubicaciones || [];
    const niveles = variante.stock_resumen?.niveles || [];
    const locInicial = ubicaciones.length === 1
        ? ubicaciones[0].id
        : (niveles.length === 1 ? niveles[0].location_id : (ubicaciones.find((u) => u.is_default)?.id || ''));
    const nivelIni = niveles.find((n) => n.location_id === locInicial);
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
        stock: nivelIni ? (nivelIni.stock == null ? '' : String(nivelIni.stock)) : '',
        location_id: locInicial,
        stock_management: !!variante.stock_management,
    });
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [imageUrl, setImageUrl] = useState('');
    const [imageFile, setImageFile] = useState(null);
    const [imageMeta, setImageMeta] = useState(null);
    const [permitirVarias, setPermitirVarias] = useState(false);
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

    const onPickFile = async (e) => {
        const file = e.target.files?.[0] || null;
        setImageFile(file);
        setImageMeta(null);
        if (!file) return;
        const dims = await readImageDimensions(file);
        setImageMeta({
            size: file.size,
            width: dims.width,
            height: dims.height,
            preview: URL.createObjectURL(file),
        });
    };

    const guardar = async (e) => {
        e.preventDefault();
        setSaving(true);
        setError(null);
        try {
            const { stock, location_id, stock_management, ...rest } = form;
            const body = {
                ...rest,
                price: form.price === '' ? null : Number(form.price),
                promotional_price: form.promotional_price === '' ? null : Number(form.promotional_price),
                cost: form.cost === '' ? null : Number(form.cost),
                video_url: form.video_url || null,
            };
            if (producto?.inventario?.escritura_habilitada && location_id) {
                body.location_id = location_id;
                body.stock = stock === '' ? null : Number(stock);
                body.stock_management = !!stock_management;
            }
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
                body: JSON.stringify({
                    src: imageUrl.trim(),
                    reemplazar: !permitirVarias,
                }),
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

    const agregarImagenFile = async () => {
        if (!imageFile) return;
        setAddingImage(true);
        setError(null);
        try {
            const body = new FormData();
            body.append('file', imageFile);
            body.append('reemplazar', permitirVarias ? '0' : '1');
            const res = await fetch(route('tiendanube.productos.imagenes.store', producto.id), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
                body,
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'No se pudo subir la imagen.');
            if (imageMeta?.preview) URL.revokeObjectURL(imageMeta.preview);
            setImageFile(null);
            setImageMeta(null);
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
                        <InventarioEdicion producto={producto} form={form} setField={setField} setForm={setForm} />
                    </div>

                    <div className="pt-2 border-t theme-border space-y-4">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Imagen del producto</p>
                            <label className="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest theme-text-muted cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={permitirVarias}
                                    onChange={(e) => setPermitirVarias(e.target.checked)}
                                    className="rounded border theme-border"
                                />
                                Permitir varias
                            </label>
                        </div>
                        <p className="text-[10px] theme-text-muted">
                            {permitirVarias
                                ? 'Se agregará sin borrar las existentes.'
                                : 'Reemplaza todas las imágenes actuales del producto.'}
                        </p>
                        <div>
                            <label className={labelClass}>Archivo</label>
                            <input
                                type="file"
                                accept=".jpg,.jpeg,.png,.gif,.webp,image/*"
                                onChange={onPickFile}
                                className="w-full text-xs theme-text-main file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-[10px] file:font-black file:uppercase file:bg-zinc-100 dark:file:bg-zinc-800"
                            />
                            {imageMeta && (
                                <div className="mt-2 flex items-center gap-3">
                                    {imageMeta.preview && (
                                        <img src={imageMeta.preview} alt="" className="w-14 h-14 rounded-lg object-cover border theme-border" />
                                    )}
                                    <p className="text-[10px] font-mono theme-text-muted">
                                        {formatBytes(imageMeta.size)}
                                        {imageMeta.width && imageMeta.height
                                            ? ` · ${imageMeta.width}×${imageMeta.height}`
                                            : ''}
                                    </p>
                                    <button
                                        type="button"
                                        onClick={agregarImagenFile}
                                        disabled={!imageFile || addingImage}
                                        className="ml-auto px-4 py-2 rounded-xl text-[10px] font-black uppercase border theme-border theme-text-main shrink-0 disabled:opacity-50"
                                    >
                                        Subir archivo
                                    </button>
                                </div>
                            )}
                        </div>
                        <div>
                            <label className={labelClass}>O por URL</label>
                            <div className="flex gap-2">
                                <input className={inputClass} type="url" placeholder="https://…" value={imageUrl} onChange={(e) => setImageUrl(e.target.value)} />
                                <button type="button" onClick={agregarImagenUrl} className="px-4 py-2 rounded-xl text-[10px] font-black uppercase border theme-border theme-text-main shrink-0">
                                    Subir
                                </button>
                            </div>
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

function InventarioEdicion({ producto, form, setField, setForm }) {
    const variante = producto?.variantes?.[0] || {};
    const resumen = variante.stock_resumen || {};
    const ubicaciones = producto?.ubicaciones || [];
    const niveles = resumen.niveles || [];
    const escritura = !!producto?.inventario?.escritura_habilitada;
    const total = resumen.ilimitado ? '∞' : (resumen.total != null ? String(resumen.total) : '—');

    const onChangeLocation = (id) => {
        const nivelSel = niveles.find((n) => n.location_id === id);
        setForm((prev) => ({
            ...prev,
            location_id: id,
            stock: nivelSel ? (nivelSel.stock == null ? '' : String(nivelSel.stock)) : '',
        }));
    };

    return (
        <div className="mt-4 space-y-3">
            {!escritura && (
                <p className="text-[10px] font-bold theme-text-muted">
                    Location no disponible o sin lectura reciente: no se escribe stock plano.
                </p>
            )}
            <div>
                <label className={labelClass}>Stock total (resumen)</label>
                <input className={inputClass} value={total} disabled readOnly />
            </div>
            <div>
                <label className={labelClass}>Ubicación</label>
                <select
                    className={inputClass}
                    disabled={!escritura}
                    value={form.location_id}
                    onChange={(e) => onChangeLocation(e.target.value)}
                >
                    <option value="">Seleccionar ubicación</option>
                    {ubicaciones.map((u) => (
                        <option key={u.id} value={u.id}>{u.nombre} ({u.id})</option>
                    ))}
                    {form.location_id && !ubicaciones.some((u) => u.id === form.location_id) && (
                        <option value={form.location_id}>{form.location_id}</option>
                    )}
                </select>
            </div>
            <div>
                <label className={labelClass}>Stock en ubicación (vacío = ilimitado)</label>
                <input
                    className={inputClass}
                    type="number"
                    min="0"
                    value={form.stock}
                    disabled={!escritura}
                    onChange={(e) => setField('stock', e.target.value)}
                />
            </div>
            <label className="flex items-center gap-2 text-xs font-bold theme-text-muted">
                <input
                    type="checkbox"
                    disabled={!escritura}
                    checked={!!form.stock_management}
                    onChange={(e) => setField('stock_management', e.target.checked)}
                />
                Control de stock (intención explícita)
            </label>
        </div>
    );
}
