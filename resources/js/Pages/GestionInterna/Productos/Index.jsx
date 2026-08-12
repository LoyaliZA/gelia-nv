import React, { useState, useEffect, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { Head, router, useForm } from '@inertiajs/react';
import { Package, Plus, Edit2, Trash2, X, Save, Search, Upload } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaPaginacion from '@/Components/GeliaPaginacion';
import GeliaLoader from '@/Components/GeliaLoader';
import WizardImportacionCatalogo from '@/Components/Almacenes/WizardImportacionCatalogo';
import EncabezadoOrdenable from '@/Components/Almacenes/EncabezadoOrdenable';
import InputConEscanner from '@/Components/Escanner/InputConEscanner';
import { IMPORTACION_CATALOGOS } from '@/config/importacionCatalogos';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '@/utils/geliaTheme';

function normalizarSku(valor) {
    const limpio = String(valor || '').trim().replace(/^0+/, '');
    return limpio || '0';
}

export default function Index({ auth, productos, marcas, categorias, tipos = [], atributos = [], atributos_por_categoria = {}, extensiones_por_categoria = {}, unidades = [], fases_olfativas = [], notas_olfativas = [], canales = [], filtros }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [showWizard, setShowWizard] = useState(false);
    const [itemActual, setItemActual] = useState(null);
    const [busqueda, setBusqueda] = useState(filtros?.q || '');
    const [relacionSku, setRelacionSku] = useState('');
    const [avisoNotasOcultas, setAvisoNotasOcultas] = useState(false);
    const [borradorNota, setBorradorNota] = useState({ salida: '', corazon: '', fondo: '' });
    const lista = productos?.data || [];
    const puedeGestionar = auth?.user?.permissions?.includes('gestion_interna.productos.gestionar')
        || auth?.user?.permissions?.includes('almacenes.productos.gestionar')
        || auth?.user?.roles?.includes('Super Admin');

    const vacioPerfumeria = () => ({ salida: [], corazon: [], fondo: [] });

    const { data, setData, post, put, processing, reset, errors } = useForm({
        sku: '',
        descripcion: '',
        descripcion_corta: '',
        marca_id: '',
        categoria_id: '',
        tipo_producto_id: '',
        codigo_barras: '',
        peso: '',
        activo: true,
        atributos: {},
        extensiones: { perfumeria: vacioPerfumeria() },
        relacionados: [],
        contenido: { pitch_venta: '', descripcion_larga: '', seo_titulo: '', seo_descripcion: '' },
    });

    const codigosExtCategoria = data.categoria_id
        ? (extensiones_por_categoria[String(data.categoria_id)] || extensiones_por_categoria[data.categoria_id] || [])
        : [];
    const muestraPerfumeria = codigosExtCategoria.includes('perfumeria');
    const idsAtributosCategoria = data.categoria_id
        ? (atributos_por_categoria[String(data.categoria_id)] || atributos_por_categoria[data.categoria_id] || [])
        : [];
    const atributosVisibles = data.categoria_id
        ? atributos.filter((a) => idsAtributosCategoria.map(Number).includes(Number(a.id)))
        : [];

    const formBase = () => ({
        sku: '',
        descripcion: '',
        descripcion_corta: '',
        marca_id: '',
        categoria_id: '',
        tipo_producto_id: '',
        codigo_barras: '',
        peso: '',
        activo: true,
        atributos: {},
        extensiones: { perfumeria: vacioPerfumeria() },
        relacionados: [],
        contenido: { pitch_venta: '', descripcion_larga: '', seo_titulo: '', seo_descripcion: '' },
    });

    const abrirNuevo = () => {
        setItemActual(null);
        setAvisoNotasOcultas(false);
        reset();
        setData(formBase());
        setModalAbierto(true);
    };

    const abrirEditar = async (item) => {
        setItemActual(item);
        setAvisoNotasOcultas(false);
        setData({
            ...formBase(),
            sku: item.sku,
            descripcion: item.descripcion,
            descripcion_corta: item.descripcion_corta || '',
            marca_id: item.marca_id || '',
            categoria_id: item.categoria_id || '',
            tipo_producto_id: item.tipo_producto_id || '',
            codigo_barras: item.codigo_barras || '',
            peso: item.peso || '',
            activo: item.activo,
        });
        setModalAbierto(true);
        try {
            const resp = await fetch(route('gestion_interna.productos.ficha', item.id), { headers: { Accept: 'application/json' } });
            const json = await resp.json();
            const f = json?.ficha;
            if (!f) return;
            const attrs = {};
            Object.entries(f.atributos || {}).forEach(([slug, val]) => {
                const def = atributos.find((a) => a.slug === slug);
                if (!def) return;
                if (def.tipo_dato === 'opcion') {
                    const nombres = Array.isArray(val) ? val : [val];
                    attrs[def.id] = (def.opciones || [])
                        .filter((o) => nombres.includes(o.nombre))
                        .map((o) => o.id);
                    if (!def.permite_multiples) attrs[def.id] = attrs[def.id][0] || '';
                } else if (def.tipo_dato === 'medida') {
                    attrs[def.id] = {
                        valor: val?.valor ?? '',
                        unidad_id: (unidades.find((u) => u.simbolo === val?.unidad)?.id) || '',
                    };
                } else {
                    attrs[def.id] = val;
                }
            });
            const notasSrc = f.extensiones?.perfumeria?.notas || {};
            const notas = { salida: [], corazon: [], fondo: [] };
            Object.entries(notasSrc).forEach(([fase, listaNotas]) => {
                notas[fase] = (listaNotas || []).map((nombre) => {
                    const n = notas_olfativas.find((x) => x.nombre === nombre);
                    return n ? n.id : nombre;
                });
            });
            const tieneNotas = Object.values(notas).some((arr) => arr.length > 0);
            const catExt = extensiones_por_categoria[String(item.categoria_id)] || extensiones_por_categoria[item.categoria_id] || [];
            if (tieneNotas && !catExt.includes('perfumeria')) {
                setAvisoNotasOcultas(true);
            }
            setData({
                sku: item.sku,
                descripcion: item.descripcion,
                descripcion_corta: item.descripcion_corta || '',
                marca_id: item.marca_id || '',
                categoria_id: item.categoria_id || '',
                tipo_producto_id: item.tipo_producto_id || '',
                codigo_barras: item.codigo_barras || '',
                peso: item.peso || '',
                activo: item.activo,
                atributos: attrs,
                extensiones: { perfumeria: notas },
                relacionados: (f.relacionados || []).map((r) => ({
                    producto_id: r.id,
                    tipo: r.tipo || 'presentacion',
                    _label: `${r.nombre} (${r.sku})`,
                })),
                contenido: {
                    pitch_venta: f.contenido?.pitch_venta || '',
                    descripcion_larga: f.contenido?.descripcion_larga || '',
                    seo_titulo: f.contenido?.seo_titulo || '',
                    seo_descripcion: f.contenido?.seo_descripcion || '',
                },
            });
        } catch {
            // ficha opcional
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const transform = (form) => {
            const next = { ...form };
            if (!muestraPerfumeria) {
                const { perfumeria, ...rest } = next.extensiones || {};
                next.extensiones = rest;
                if (Object.keys(next.extensiones).length === 0) {
                    delete next.extensiones;
                }
            }
            return next;
        };
        const opts = { onSuccess: () => { setModalAbierto(false); reset(); }, transform };
        if (itemActual) {
            put(route('gestion_interna.productos.update', itemActual.id), opts);
        } else {
            post(route('gestion_interna.productos.store'), opts);
        }
    };

    const buscar = (e) => {
        e.preventDefault();
        aplicarFiltros({ page: 1 });
    };

    const paramsBase = useCallback((extra = {}) => ({
        q: busqueda,
        sort: filtros?.sort,
        dir: filtros?.dir,
        ...extra,
    }), [busqueda, filtros?.sort, filtros?.dir]);

    const aplicarFiltros = (extra = {}) => {
        router.get(route('gestion_interna.productos.index'), paramsBase(extra), { preserveState: true, replace: true });
    };

    const handleOrdenar = (columna) => {
        const sortActual = filtros?.sort;
        const dirActual = filtros?.dir || 'asc';
        const nuevaDir = sortActual === columna && dirActual === 'asc' ? 'desc' : 'asc';
        aplicarFiltros({ sort: columna, dir: nuevaDir, page: 1 });
    };

    useEffect(() => {
        if (busqueda === (filtros?.q ?? '')) return undefined;
        const timer = setTimeout(() => {
            router.get(route('gestion_interna.productos.index'), paramsBase({ q: busqueda, page: 1 }), { preserveState: true, replace: true });
        }, 400);
        return () => clearTimeout(timer);
    }, [busqueda]);

    const irAPagina = (pagina) => {
        aplicarFiltros({ page: pagina });
    };

    const agregarRelacion = async () => {
        const q = relacionSku.trim();
        if (!q) return;
        try {
            const resp = await fetch(`${route('gestion_interna.productos.buscar')}?q=${encodeURIComponent(q)}&per_page=5`, { headers: { Accept: 'application/json' } });
            const json = await resp.json();
            const hit = (json?.data || [])[0];
            if (!hit || hit.id === itemActual?.id) return;
            if ((data.relacionados || []).some((r) => Number(r.producto_id) === Number(hit.id))) return;
            setData('relacionados', [...(data.relacionados || []), {
                producto_id: hit.id,
                tipo: 'presentacion',
                _label: `${hit.descripcion} (${hit.sku})`,
            }]);
            setRelacionSku('');
        } catch {
            // ignore
        }
    };

    const setAttr = (atributoId, value) => {
        setData('atributos', { ...(data.atributos || {}), [atributoId]: value });
    };

    const nombresFase = (fase) => (data.extensiones?.perfumeria?.[fase] || []).map((x) => {
        if (typeof x === 'number') {
            return notas_olfativas.find((n) => n.id === x)?.nombre || String(x);
        }
        return String(x);
    });

    const agregarNotaFase = (fase, nombreRaw) => {
        const nombre = String(nombreRaw || '').trim();
        if (!nombre) return;
        const actual = nombresFase(fase);
        if (actual.some((n) => n.toLowerCase() === nombre.toLowerCase())) {
            setBorradorNota((prev) => ({ ...prev, [fase]: '' }));
            return;
        }
        setData('extensiones', {
            ...(data.extensiones || {}),
            perfumeria: {
                ...(data.extensiones?.perfumeria || vacioPerfumeria()),
                [fase]: [...actual, nombre],
            },
        });
        setBorradorNota((prev) => ({ ...prev, [fase]: '' }));
    };

    const quitarNotaFase = (fase, nombre) => {
        const actual = nombresFase(fase).filter((n) => n !== nombre);
        setData('extensiones', {
            ...(data.extensiones || {}),
            perfumeria: {
                ...(data.extensiones?.perfumeria || vacioPerfumeria()),
                [fase]: actual,
            },
        });
    };

    const onCambioCategoria = (valor) => {
        const prevExt = extensiones_por_categoria[String(data.categoria_id)] || [];
        const nextExt = extensiones_por_categoria[String(valor)] || extensiones_por_categoria[valor] || [];
        const teniaNotas = Object.values(data.extensiones?.perfumeria || {}).some((arr) => (arr || []).length > 0);
        if (teniaNotas && prevExt.includes('perfumeria') && !nextExt.includes('perfumeria')) {
            setAvisoNotasOcultas(true);
        } else if (nextExt.includes('perfumeria')) {
            setAvisoNotasOcultas(false);
        }
        setData('categoria_id', valor);
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Productos" />
            <GeliaLoader isVisible={processing} message="Guardando producto_" />

            <div className="max-w-[1400px] mx-auto p-4 md:p-8 space-y-6">
                <header className={geliaCardClass('p-6 space-y-4')}>
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div>
                            <h1 className="text-2xl font-black italic uppercase theme-text-main flex items-center gap-3">
                                <Package className="w-7 h-7" style={{ color: 'var(--color-primario)' }} />
                                Catálogo de Productos
                            </h1>
                        </div>
                        {puedeGestionar && (
                            <div className="flex gap-2">
                                <button onClick={() => setShowWizard(true)} className="theme-element border theme-border theme-btn-primary--compact px-4 py-2 rounded-xl font-black uppercase text-xs flex items-center gap-2 theme-text-main">
                                    <Upload className="w-4 h-4" /> Importar
                                </button>
                                <button onClick={abrirNuevo} className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact`}>
                                    <Plus className="w-4 h-4" /> Nuevo Producto
                                </button>
                            </div>
                        )}
                    </div>
                    <form onSubmit={buscar} className="flex gap-2 max-w-md">
                        <div className="relative flex-1">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                            <input value={busqueda} onChange={(e) => setBusqueda(e.target.value)} placeholder="Buscar por nombre o código..." className="theme-input w-full pl-10 py-2 text-[11px] font-bold" />
                        </div>
                        <button type="submit" className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact`}>Buscar</button>
                    </form>
                </header>

                <div className={geliaCardClass('overflow-hidden')}>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left min-w-[900px]">
                            <thead>
                                <tr className="border-b theme-border text-[10px] font-black uppercase tracking-widest theme-text-muted">
                                    <EncabezadoOrdenable columna="folio" etiqueta="Folio" sortActual={filtros?.sort} dirActual={filtros?.dir} onOrdenar={handleOrdenar} />
                                    <EncabezadoOrdenable columna="producto" etiqueta="SKU / Producto" sortActual={filtros?.sort} dirActual={filtros?.dir} onOrdenar={handleOrdenar} />
                                    <EncabezadoOrdenable columna="marca" etiqueta="Marca" sortActual={filtros?.sort} dirActual={filtros?.dir} onOrdenar={handleOrdenar} />
                                    <EncabezadoOrdenable columna="categoria" etiqueta="Categoría" sortActual={filtros?.sort} dirActual={filtros?.dir} onOrdenar={handleOrdenar} />
                                    <EncabezadoOrdenable columna="codigo_barras" etiqueta="Cód. Barras" sortActual={filtros?.sort} dirActual={filtros?.dir} onOrdenar={handleOrdenar} />
                                    <EncabezadoOrdenable columna="peso" etiqueta="Peso" sortActual={filtros?.sort} dirActual={filtros?.dir} onOrdenar={handleOrdenar} alineacion="right" />
                                    {puedeGestionar && <th className="px-4 py-4 text-right">Acciones</th>}
                                </tr>
                            </thead>
                            <tbody>
                                {lista.length === 0 ? (
                                    <tr><td colSpan={7} className="px-4 py-16 text-center theme-text-muted text-sm font-bold uppercase">Sin productos registrados</td></tr>
                                ) : lista.map((p) => (
                                    <tr key={p.id} className={`border-b theme-border hover:bg-black/5 dark:hover:bg-white/5 ${!p.activo ? 'opacity-50' : ''}`}>
                                        <td className="px-4 py-3 text-[11px] font-bold theme-text-muted">#{p.folio}</td>
                                        <td className="px-4 py-3">
                                            <span className="font-black text-sm theme-text-main block">{p.descripcion}</span>
                                            <span className="text-[10px] font-bold theme-text-muted">SKU: {p.sku}</span>
                                        </td>
                                        <td className="px-4 py-3 text-[11px] font-bold theme-text-main">{p.marca?.nombre || '—'}</td>
                                        <td className="px-4 py-3 text-[11px] font-bold theme-text-main">{p.categoria?.nombre || '—'}</td>
                                        <td className="px-4 py-3 text-[11px] font-bold theme-text-main">{p.codigo_barras || '—'}</td>
                                        <td className="px-4 py-3 text-right text-[11px] font-bold theme-text-main">{p.peso ? `${p.peso} kg` : '—'}</td>
                                        {puedeGestionar && (
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex justify-end gap-2">
                                                    <button onClick={() => abrirEditar(p)} className="p-2 theme-element border theme-border rounded-xl"><Edit2 className="w-4 h-4" /></button>
                                                    <button onClick={() => router.delete(route('gestion_interna.productos.destroy', p.id))} className="p-2 theme-element border theme-border rounded-xl hover:bg-red-500 hover:text-white"><Trash2 className="w-4 h-4" /></button>
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {lista.length > 0 && <GeliaPaginacion paginator={productos} onIrAPagina={irAPagina} embedded />}
                </div>
            </div>

            {modalAbierto && createPortal(
                <div className={THEME_MODAL_OVERLAY} onClick={() => setModalAbierto(false)}>
                    <div className={`${THEME_MODAL_SHELL} max-w-2xl w-full flex flex-col modal-pop`} onClick={(e) => e.stopPropagation()}>
                        <div className="flex justify-between items-center shrink-0 px-6 pt-6 pb-3 border-b theme-border">
                            <h3 className="text-xl font-black italic uppercase theme-text-main m-0">{itemActual ? 'Editar' : 'Nuevo'} Producto</h3>
                            <button type="button" onClick={() => setModalAbierto(false)} className="p-2 hover:bg-black/5 dark:hover:bg-white/5 rounded-full">
                                <X className="w-5 h-5 theme-text-muted" />
                            </button>
                        </div>
                        <form onSubmit={handleSubmit} className="flex flex-col min-h-0 flex-1 overflow-hidden">
                        <div className="gelia-modal-body px-6 py-4 space-y-4 custom-scrollbar">
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted">SKU *</label>
                                <div className="mt-1">
                                    <InputConEscanner
                                        value={data.sku}
                                        onChange={(e) => setData('sku', normalizarSku(e.target.value))}
                                        label="SKU"
                                        inputProps={{ required: true, className: 'theme-input w-full px-4 py-3 text-sm font-bold' }}
                                    />
                                </div>
                                {errors.sku && <p className="text-xs text-red-500 dark:text-red-400">{errors.sku}</p>}
                            </div>
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted">Descripción *</label>
                                <input required value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} className="theme-input w-full mt-1 px-4 py-3 text-sm font-bold" />
                            </div>
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted">Descripción corta</label>
                                <input value={data.descripcion_corta || ''} onChange={(e) => setData('descripcion_corta', e.target.value)} className="theme-input w-full mt-1 px-4 py-3 text-sm font-bold" />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="text-[10px] font-black uppercase theme-text-muted">Marca</label>
                                    <select value={data.marca_id} onChange={(e) => setData('marca_id', e.target.value)} className="theme-input w-full mt-1 px-3 py-3 text-sm font-bold">
                                        <option value="">—</option>
                                        {marcas.map((m) => <option key={m.id} value={m.id}>{m.nombre}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="text-[10px] font-black uppercase theme-text-muted">Categoría</label>
                                    <select value={data.categoria_id} onChange={(e) => onCambioCategoria(e.target.value)} className="theme-input w-full mt-1 px-3 py-3 text-sm font-bold">
                                        <option value="">—</option>
                                        {categorias.map((c) => <option key={c.id} value={c.id}>{c.nombre}</option>)}
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted">Tipo</label>
                                <select value={data.tipo_producto_id || ''} onChange={(e) => setData('tipo_producto_id', e.target.value)} className="theme-input w-full mt-1 px-3 py-3 text-sm font-bold">
                                    <option value="">—</option>
                                    {tipos.map((t) => <option key={t.id} value={t.id}>{t.nombre}</option>)}
                                </select>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="text-[10px] font-black uppercase theme-text-muted">Código de barras</label>
                                    <div className="mt-1">
                                        <InputConEscanner
                                            value={data.codigo_barras}
                                            onChange={(e) => setData('codigo_barras', e.target.value)}
                                            label="código de barras"
                                            inputProps={{ className: 'theme-input w-full px-4 py-3 text-sm font-bold' }}
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label className="text-[10px] font-black uppercase theme-text-muted">Peso (kg)</label>
                                    <input type="number" step="0.001" min="0" value={data.peso} onChange={(e) => setData('peso', e.target.value)} className="theme-input w-full mt-1 px-4 py-3 text-sm font-bold" />
                                </div>
                            </div>

                            <div className="border-t theme-border pt-4 space-y-3">
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Especificaciones</p>
                                {!data.categoria_id && (
                                    <p className="text-xs theme-text-muted m-0">Elige una categoría para ver las especificaciones configuradas para ella.</p>
                                )}
                                {data.categoria_id && atributosVisibles.length === 0 && (
                                    <p className="text-xs theme-text-muted m-0">
                                        Esta categoría no tiene atributos asignados. Configúralos en Admin → Catálogos → Atributos Producto / Categorías Producto.
                                    </p>
                                )}
                                {atributosVisibles.map((attr) => (
                                    <div key={attr.id}>
                                        <label className="text-[10px] font-black uppercase theme-text-muted">{attr.nombre}</label>
                                        {attr.tipo_dato === 'opcion' && attr.permite_multiples && (
                                            <select
                                                multiple
                                                value={(data.atributos?.[attr.id] || []).map(String)}
                                                onChange={(e) => setAttr(attr.id, Array.from(e.target.selectedOptions).map((o) => Number(o.value)))}
                                                className="theme-input w-full mt-1 px-3 py-2 text-sm font-bold min-h-[80px]"
                                            >
                                                {(attr.opciones || []).map((o) => <option key={o.id} value={o.id}>{o.nombre}</option>)}
                                            </select>
                                        )}
                                        {attr.tipo_dato === 'opcion' && !attr.permite_multiples && (
                                            <select
                                                value={data.atributos?.[attr.id] ?? ''}
                                                onChange={(e) => setAttr(attr.id, e.target.value ? Number(e.target.value) : '')}
                                                className="theme-input w-full mt-1 px-3 py-3 text-sm font-bold"
                                            >
                                                <option value="">—</option>
                                                {(attr.opciones || []).map((o) => <option key={o.id} value={o.id}>{o.nombre}</option>)}
                                            </select>
                                        )}
                                        {attr.tipo_dato === 'medida' && (
                                            <div className="grid grid-cols-2 gap-2 mt-1">
                                                <input
                                                    type="number"
                                                    step="0.01"
                                                    value={data.atributos?.[attr.id]?.valor ?? ''}
                                                    onChange={(e) => setAttr(attr.id, { ...(data.atributos?.[attr.id] || {}), valor: e.target.value })}
                                                    className="theme-input w-full px-3 py-3 text-sm font-bold"
                                                    placeholder="Valor"
                                                />
                                                <select
                                                    value={data.atributos?.[attr.id]?.unidad_id ?? ''}
                                                    onChange={(e) => setAttr(attr.id, { ...(data.atributos?.[attr.id] || {}), unidad_id: e.target.value ? Number(e.target.value) : '' })}
                                                    className="theme-input w-full px-3 py-3 text-sm font-bold"
                                                >
                                                    <option value="">Unidad</option>
                                                    {unidades.filter((u) => !attr.dimension_unidad || u.dimension === attr.dimension_unidad).map((u) => (
                                                        <option key={u.id} value={u.id}>{u.simbolo}</option>
                                                    ))}
                                                </select>
                                            </div>
                                        )}
                                        {['texto', 'texto_largo', 'entero', 'decimal'].includes(attr.tipo_dato) && (
                                            <input
                                                type={attr.tipo_dato === 'entero' || attr.tipo_dato === 'decimal' ? 'number' : 'text'}
                                                value={data.atributos?.[attr.id] ?? ''}
                                                onChange={(e) => setAttr(attr.id, e.target.value)}
                                                className="theme-input w-full mt-1 px-3 py-3 text-sm font-bold"
                                            />
                                        )}
                                        {attr.tipo_dato === 'booleano' && (
                                            <label className="flex items-center gap-2 mt-1">
                                                <input
                                                    type="checkbox"
                                                    checked={!!data.atributos?.[attr.id]}
                                                    onChange={(e) => setAttr(attr.id, e.target.checked)}
                                                />
                                                <span className="text-sm font-bold">Sí</span>
                                            </label>
                                        )}
                                    </div>
                                ))}
                            </div>

                            {muestraPerfumeria && (
                                <div className="border-t theme-border pt-4 space-y-4">
                                    <div>
                                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Pirámide olfativa</p>
                                        <p className="text-[10px] theme-text-muted m-0 mt-1">Elige notas del catálogo o escribe una nueva y pulsa Añadir.</p>
                                    </div>
                                    {(fases_olfativas.length ? fases_olfativas : [
                                        { codigo: 'salida', nombre: 'Salida' },
                                        { codigo: 'corazon', nombre: 'Corazón' },
                                        { codigo: 'fondo', nombre: 'Fondo' },
                                    ]).map((faseMeta) => {
                                        const fase = faseMeta.codigo || faseMeta;
                                        const label = faseMeta.nombre || fase;
                                        const seleccionadas = data.extensiones?.perfumeria?.[fase] || [];
                                        const nombresSel = seleccionadas.map((x) => {
                                            if (typeof x === 'number') {
                                                return notas_olfativas.find((n) => n.id === x)?.nombre || String(x);
                                            }
                                            return String(x);
                                        });
                                        return (
                                            <div key={fase} className="space-y-2 border theme-border rounded-xl p-3">
                                                <label className="text-[10px] font-black uppercase theme-text-muted">{label}</label>
                                                <div className="flex flex-wrap gap-1.5">
                                                    {nombresSel.map((nombre) => (
                                                        <button
                                                            key={`${fase}-${nombre}`}
                                                            type="button"
                                                            onClick={() => quitarNotaFase(fase, nombre)}
                                                            className="theme-element border theme-border px-2 py-1 rounded-lg text-[10px] font-bold theme-text-main"
                                                            title="Quitar"
                                                        >
                                                            {nombre} ×
                                                        </button>
                                                    ))}
                                                    {nombresSel.length === 0 && (
                                                        <span className="text-[10px] theme-text-muted">Sin notas</span>
                                                    )}
                                                </div>
                                                <div className="flex gap-2">
                                                    <input
                                                        list={`notas-datalist-${fase}`}
                                                        value={borradorNota[fase] || ''}
                                                        onChange={(e) => setBorradorNota((prev) => ({ ...prev, [fase]: e.target.value }))}
                                                        onKeyDown={(e) => {
                                                            if (e.key === 'Enter') {
                                                                e.preventDefault();
                                                                agregarNotaFase(fase, borradorNota[fase]);
                                                            }
                                                        }}
                                                        className="theme-input theme-placeholder flex-1 px-3 py-2 text-sm font-bold"
                                                        placeholder="Buscar o escribir nota…"
                                                    />
                                                    <datalist id={`notas-datalist-${fase}`}>
                                                        {notas_olfativas.map((n) => (
                                                            <option key={n.id} value={n.nombre} />
                                                        ))}
                                                    </datalist>
                                                    <button
                                                        type="button"
                                                        onClick={() => agregarNotaFase(fase, borradorNota[fase])}
                                                        className="theme-element border theme-border px-3 py-2 rounded-xl text-[10px] font-black uppercase shrink-0"
                                                    >
                                                        Añadir
                                                    </button>
                                                </div>
                                                {notas_olfativas.length > 0 && (
                                                    <div className="max-h-28 overflow-y-auto flex flex-wrap gap-1.5 pt-1">
                                                        {notas_olfativas.slice(0, 40).map((n) => {
                                                            const activa = nombresSel.includes(n.nombre);
                                                            return (
                                                                <button
                                                                    key={n.id}
                                                                    type="button"
                                                                    onClick={() => (activa ? quitarNotaFase(fase, n.nombre) : agregarNotaFase(fase, n.nombre))}
                                                                    className={`px-2 py-1 rounded-lg text-[10px] font-bold border theme-border ${activa ? 'theme-btn-primary' : 'theme-element theme-text-muted'}`}
                                                                >
                                                                    {n.nombre}
                                                                </button>
                                                            );
                                                        })}
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                    {errors['extensiones.perfumeria'] && (
                                        <p className="text-xs text-red-500 dark:text-red-400">{errors['extensiones.perfumeria']}</p>
                                    )}
                                </div>
                            )}
                            {!muestraPerfumeria && avisoNotasOcultas && (
                                <p className="text-xs theme-text-muted border-t theme-border pt-3 m-0">
                                    Este producto tiene notas olfativas guardadas, pero la categoría actual no tiene la extensión Perfumería: los datos quedan ocultos (no se borran).
                                </p>
                            )}

                            <div className="border-t theme-border pt-4 space-y-3">
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Productos relacionados</p>
                                <div className="flex gap-2">
                                    <input value={relacionSku} onChange={(e) => setRelacionSku(e.target.value)} placeholder="SKU hermano" className="theme-input flex-1 px-3 py-2 text-sm font-bold" />
                                    <button type="button" onClick={agregarRelacion} className="theme-element border theme-border px-3 py-2 rounded-xl text-xs font-black uppercase">Añadir</button>
                                </div>
                                <ul className="space-y-1">
                                    {(data.relacionados || []).map((r) => (
                                        <li key={r.producto_id} className="flex justify-between text-xs font-bold theme-text-main">
                                            <span>{r._label || r.producto_id}</span>
                                            <button type="button" className="text-red-500" onClick={() => setData('relacionados', data.relacionados.filter((x) => x.producto_id !== r.producto_id))}>Quitar</button>
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            <div className="border-t theme-border pt-4 space-y-3">
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Contenido comercial</p>
                                <div>
                                    <label className="text-[10px] font-black uppercase theme-text-muted">Pitch de venta</label>
                                    <textarea
                                        value={data.contenido?.pitch_venta || ''}
                                        onChange={(e) => setData('contenido', { ...data.contenido, pitch_venta: e.target.value })}
                                        className="theme-input w-full mt-1 px-3 py-3 text-sm font-bold min-h-[80px]"
                                    />
                                </div>
                                <div>
                                    <label className="text-[10px] font-black uppercase theme-text-muted">SEO título</label>
                                    <input
                                        value={data.contenido?.seo_titulo || ''}
                                        onChange={(e) => setData('contenido', { ...data.contenido, seo_titulo: e.target.value })}
                                        className="theme-input w-full mt-1 px-3 py-3 text-sm font-bold"
                                    />
                                </div>
                            </div>

                            <label className="flex items-center gap-2">
                                <input type="checkbox" checked={data.activo} onChange={(e) => setData('activo', e.target.checked)} />
                                <span className="text-sm font-bold theme-text-main">Activo</span>
                            </label>
                        </div>
                            <div className="gelia-modal-footer shrink-0 px-6 py-4">
                                <button type="submit" disabled={processing} className={`${THEME_BTN_PRIMARY} w-full py-3 flex justify-center gap-2`}>
                                    <Save className="w-4 h-4" /> Guardar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>,
                document.body
            )}

            {showWizard && (
                <WizardImportacionCatalogo
                    config={IMPORTACION_CATALOGOS.productos}
                    onClose={() => setShowWizard(false)}
                />
            )}
        </AppLayout>
    );
}
