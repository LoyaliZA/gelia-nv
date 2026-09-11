import React, { useRef, useState } from 'react';
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

function etiquetaValores(values) {
    if (!Array.isArray(values) || values.length === 0) return '';
    return values.map((x) => textoIdioma(x)).filter((t) => t && t !== '—').join(' / ');
}

function camposDesdeVariante(v) {
    if (!v) {
        return { sku: '', price: '', promotional_price: '', cost: '', stock: '', stock_unlimited: false, location_id: '', stock_management: false };
    }
    const managed = !!v.stock_management;
    const nivel = v.stock_resumen?.niveles?.[0];
    return {
        sku: v.sku || '',
        price: v.price ?? '',
        promotional_price: v.promotional_price ?? '',
        cost: v.cost ?? '',
        stock: managed ? (v.stock ?? '') : '',
        stock_unlimited: !managed,
        location_id: nivel?.location_id || '',
        stock_management: managed,
    };
}

function varianteDirty(actual, baseline) {
    if (!baseline) return false;
    return ['sku', 'price', 'promotional_price', 'cost', 'stock'].some(
        (k) => String(actual[k] ?? '') !== String(baseline[k] ?? '')
    ) || Boolean(actual.stock_unlimited) !== Boolean(baseline.stock_unlimited);
}

function idsCategoriasOrdenados(ids) {
    return [...ids].map(Number).sort((a, b) => a - b);
}

function categoriesChanged(current, baseline) {
    const a = idsCategoriasOrdenados(current);
    if (a.length !== baseline.length) return true;
    return a.some((id, i) => id !== baseline[i]);
}

function etiquetaVariante(v) {
    const valores = etiquetaValores(v.values);
    return `#${v.id} · ${v.sku || 'sin SKU'}${valores ? ` · ${valores}` : ''}`;
}

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const inputClass = 'w-full theme-element border theme-border rounded-xl px-4 py-3 text-sm theme-text-main';
const labelClass = 'block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2';

export default function ModalEditarProducto({ producto, categorias = [], onClose, onSaved }) {
    const variantes = producto?.variantes || [];
    const unica = variantes.length === 1 ? variantes[0] : null;
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
    });
    const [categoriesBaseline] = useState(() =>
        idsCategoriasOrdenados(producto?.categoria_ids || producto?.categorias?.map((c) => c.id) || [])
    );
    const [selectedVariantId, setSelectedVariantId] = useState(unica ? unica.id : null);
    const [variantForm, setVariantForm] = useState(() => camposDesdeVariante(unica));
    const [variantBaseline, setVariantBaseline] = useState(() => camposDesdeVariante(unica));
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const [imageUrl, setImageUrl] = useState('');
    const [imageFile, setImageFile] = useState(null);
    const [imageMeta, setImageMeta] = useState(null);
    const [permitirVarias, setPermitirVarias] = useState(false);
    const [addingImage, setAddingImage] = useState(false);
    const [parcialOperacion, setParcialOperacion] = useState(null);
    const solicitudClaveFileRef = useRef(null);
    const solicitudClaveUrlRef = useRef(null);

    const varianteSeleccionada = variantes.find((v) => v.id === selectedVariantId) || unica || null;

    const aplicarVariante = (v) => {
        const campos = camposDesdeVariante(v);
        setSelectedVariantId(v?.id ?? null);
        setVariantForm(campos);
        setVariantBaseline(campos);
    };

    const onCambiarVariante = (idRaw) => {
        const id = idRaw === '' ? null : Number(idRaw);
        if (id === selectedVariantId) return;
        if (varianteDirty(variantForm, variantBaseline)) {
            const ok = window.confirm('Hay cambios de variante sin guardar. ¿Descartarlos y cambiar de variante?');
            if (!ok) return;
        }
        const v = variantes.find((item) => item.id === id) || null;
        aplicarVariante(v);
    };

    const claveSolicitud = (ref) => {
        if (!ref.current) {
            ref.current = crypto.randomUUID();
        }
        return ref.current;
    };

    const aplicarRespuestaImagen = (data, claveRef, onOk) => {
        if (data.parcial && data.operacion?.id) {
            setParcialOperacion(data.operacion);
            setError(data.message || 'Reemplazo incompleto: puede completar sin volver a subir la imagen.');
            onSaved?.(false);
            return;
        }
        claveRef.current = null;
        setParcialOperacion(null);
        onOk?.();
        onSaved?.(false);
    };

    const completarReemplazo = async () => {
        if (!parcialOperacion?.id) return;
        setAddingImage(true);
        setError(null);
        try {
            const res = await fetch(route('tiendanube.imagen_operaciones.reconciliar', parcialOperacion.id), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'No se pudo completar el reemplazo.');
            if (data.parcial) {
                setParcialOperacion(data.operacion);
                setError(data.message || 'Aún faltan imágenes anteriores por retirar.');
                return;
            }
            setParcialOperacion(null);
            solicitudClaveFileRef.current = null;
            solicitudClaveUrlRef.current = null;
            onSaved?.(false);
        } catch (err) {
            setError(err.message);
        } finally {
            setAddingImage(false);
        }
    };

    const setField = (key, value) => setForm((prev) => ({ ...prev, [key]: value }));
    const setVariantField = (key, value) => setVariantForm((prev) => ({ ...prev, [key]: value }));

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
            const body = {
                ...form,
                video_url: form.video_url || null,
            };
            if (categoriesChanged(form.categories, categoriesBaseline)) {
                body.replace_categories = true;
            } else {
                delete body.categories;
            }
            if (selectedVariantId) {
                body.variant_id = selectedVariantId;
            }
            if (variantes.length > 1 && varianteDirty(variantForm, variantBaseline) && !selectedVariantId) {
                throw new Error('Selecciona una variante para guardar cambios de SKU, precio, promoción, costo o stock.');
            }
            if (selectedVariantId && varianteDirty(variantForm, variantBaseline)) {
                body.sku = variantForm.sku || null;
                body.price = variantForm.price === '' ? null : Number(variantForm.price);
                body.promotional_price = variantForm.promotional_price === '' ? null : Number(variantForm.promotional_price);
                body.cost = variantForm.cost === '' ? null : Number(variantForm.cost);
                if (variantForm.stock_unlimited) {
                    body.stock_unlimited = true;
                } else if (String(variantForm.stock) !== String(variantBaseline.stock) || variantBaseline.stock_unlimited) {
                    if (variantForm.stock !== '') {
                        body.stock = Number(variantForm.stock);
                    }
                }
                if (producto?.inventario?.escritura_habilitada && variantForm.location_id) {
                    body.location_id = variantForm.location_id;
                    delete body.stock_unlimited;
                    if (variantForm.stock_management !== undefined) {
                        body.stock_management = !!variantForm.stock_management;
                    }
                }
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
            if (data.parcial && data.producto_actualizado) {
                setError(data.message || 'El producto se actualizó, pero la variante no.');
                onSaved?.(true);
                return;
            }
            if (!res.ok || !data.success) throw new Error(data.message || 'No se pudo guardar.');
            onSaved?.(false);
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
                    solicitud_clave: claveSolicitud(solicitudClaveUrlRef),
                }),
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.message || 'No se pudo subir la imagen.');
            aplicarRespuestaImagen(data, solicitudClaveUrlRef, () => setImageUrl(''));
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
            body.append('solicitud_clave', claveSolicitud(solicitudClaveFileRef));
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
            aplicarRespuestaImagen(data, solicitudClaveFileRef, () => {
                if (imageMeta?.preview) URL.revokeObjectURL(imageMeta.preview);
                setImageFile(null);
                setImageMeta(null);
            });
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

                    {variantes.length > 0 && (
                        <div className="pt-2 border-t theme-border space-y-4">
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">
                                {variantes.length > 1 ? 'Variante' : 'Variante virtual'}
                            </p>
                            {variantes.length > 1 && (
                                <div>
                                    <label className={labelClass}>Seleccionar variante</label>
                                    <select
                                        className={inputClass}
                                        value={selectedVariantId ?? ''}
                                        onChange={(e) => onCambiarVariante(e.target.value)}
                                    >
                                        <option value="">Elegir variante…</option>
                                        {variantes.map((v) => (
                                            <option key={v.id} value={v.id}>
                                                {etiquetaVariante(v)}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}
                            {varianteSeleccionada ? (
                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label className={labelClass}>SKU</label>
                                        <input className={inputClass} value={variantForm.sku} onChange={(e) => setVariantField('sku', e.target.value)} />
                                    </div>
                                    <div>
                                        <label className={labelClass}>Stock</label>
                                        <input
                                            className={inputClass}
                                            type="number"
                                            min="0"
                                            disabled={variantForm.stock_unlimited}
                                            value={variantForm.stock_unlimited ? '' : variantForm.stock}
                                            onChange={(e) => setVariantField('stock', e.target.value)}
                                        />
                                        <label className="mt-2 inline-flex items-center gap-2 text-[10px] font-bold theme-text-main">
                                            <input
                                                type="checkbox"
                                                checked={variantForm.stock_unlimited}
                                                onChange={(e) => setVariantField('stock_unlimited', e.target.checked)}
                                            />
                                            Stock ilimitado
                                        </label>
                                    </div>
                                    <div>
                                        <label className={labelClass}>Precio</label>
                                        <input className={inputClass} type="number" step="0.01" min="0" value={variantForm.price} onChange={(e) => setVariantField('price', e.target.value)} />
                                    </div>
                                    <div>
                                        <label className={labelClass}>Precio promo</label>
                                        <input className={inputClass} type="number" step="0.01" min="0" value={variantForm.promotional_price} onChange={(e) => setVariantField('promotional_price', e.target.value)} />
                                    </div>
                                    <div>
                                        <label className={labelClass}>Costo</label>
                                        <input className={inputClass} type="number" step="0.01" min="0" value={variantForm.cost} onChange={(e) => setVariantField('cost', e.target.value)} />
                                    </div>
                                    {!!producto?.inventario?.escritura_habilitada && (
                                        <div className="sm:col-span-2 space-y-3">
                                            <label className={labelClass}>Ubicación de inventario</label>
                                            <select
                                                className={inputClass}
                                                value={variantForm.location_id || ''}
                                                onChange={(e) => setVariantField('location_id', e.target.value)}
                                            >
                                                <option value="">Seleccionar ubicación</option>
                                                {(producto?.ubicaciones || []).map((u) => (
                                                    <option key={u.id} value={u.id}>{u.nombre} ({u.id})</option>
                                                ))}
                                            </select>
                                            <label className="flex items-center gap-2 text-xs font-bold theme-text-muted">
                                                <input
                                                    type="checkbox"
                                                    checked={!!variantForm.stock_management}
                                                    onChange={(e) => setVariantField('stock_management', e.target.checked)}
                                                />
                                                Control de stock
                                            </label>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                variantes.length > 1 && (
                                    <p className="text-xs theme-text-muted">Selecciona una variante para editar SKU, precio, promoción, costo o stock. Los datos del producto se pueden guardar sin selección.</p>
                                )
                            )}
                        </div>
                    )}

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
                    {parcialOperacion && (
                        <button
                            type="button"
                            onClick={completarReemplazo}
                            disabled={addingImage}
                            className="px-4 py-2 rounded-xl text-[10px] font-black uppercase border theme-border theme-text-main disabled:opacity-50"
                        >
                            Completar reemplazo
                        </button>
                    )}

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
