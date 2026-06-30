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

export default function Index({ auth, productos, marcas, categorias, filtros }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [showWizard, setShowWizard] = useState(false);
    const [itemActual, setItemActual] = useState(null);
    const [busqueda, setBusqueda] = useState(filtros?.q || '');
    const lista = productos?.data || [];
    const puedeGestionar = auth?.user?.permissions?.includes('almacenes.productos.gestionar') || auth?.user?.roles?.includes('Super Admin');

    const { data, setData, post, put, processing, reset, errors } = useForm({
        sku: '',
        descripcion: '',
        marca_id: '',
        categoria_id: '',
        codigo_barras: '',
        peso: '',
        activo: true,
    });

    const abrirNuevo = () => {
        setItemActual(null);
        reset();
        setModalAbierto(true);
    };

    const abrirEditar = (item) => {
        setItemActual(item);
        setData({
            sku: item.sku,
            descripcion: item.descripcion,
            marca_id: item.marca_id || '',
            categoria_id: item.categoria_id || '',
            codigo_barras: item.codigo_barras || '',
            peso: item.peso || '',
            activo: item.activo,
        });
        setModalAbierto(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const accion = itemActual ? put : post;
        const ruta = itemActual
            ? route('almacenes.productos.update', itemActual.id)
            : route('almacenes.productos.store');
        accion(ruta, { onSuccess: () => { setModalAbierto(false); reset(); } });
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
        router.get(route('almacenes.productos.index'), paramsBase(extra), { preserveState: true, replace: true });
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
            router.get(route('almacenes.productos.index'), paramsBase({ q: busqueda, page: 1 }), { preserveState: true, replace: true });
        }, 400);
        return () => clearTimeout(timer);
    }, [busqueda]);

    const irAPagina = (pagina) => {
        aplicarFiltros({ page: pagina });
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
                                                    <button onClick={() => router.delete(route('almacenes.productos.destroy', p.id))} className="p-2 theme-element border theme-border rounded-xl hover:bg-red-500 hover:text-white"><Trash2 className="w-4 h-4" /></button>
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
                    <div className={`${THEME_MODAL_SHELL} max-w-lg p-8 max-h-[90vh] overflow-y-auto modal-pop`} onClick={(e) => e.stopPropagation()}>
                        <div className="flex justify-between items-center mb-6">
                            <h3 className="text-xl font-black italic uppercase theme-text-main">{itemActual ? 'Editar' : 'Nuevo'} Producto</h3>
                            <button type="button" onClick={() => setModalAbierto(false)} className="p-2 hover:bg-black/5 dark:hover:bg-white/5 rounded-full">
                                <X className="w-5 h-5 theme-text-muted" />
                            </button>
                        </div>
                        <form onSubmit={handleSubmit} className="space-y-4">
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
                                    <select value={data.categoria_id} onChange={(e) => setData('categoria_id', e.target.value)} className="theme-input w-full mt-1 px-3 py-3 text-sm font-bold">
                                        <option value="">—</option>
                                        {categorias.map((c) => <option key={c.id} value={c.id}>{c.nombre}</option>)}
                                    </select>
                                </div>
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
                            <label className="flex items-center gap-2">
                                <input type="checkbox" checked={data.activo} onChange={(e) => setData('activo', e.target.checked)} />
                                <span className="text-sm font-bold theme-text-main">Activo</span>
                            </label>
                            <button type="submit" disabled={processing} className={`${THEME_BTN_PRIMARY} w-full py-3 flex justify-center gap-2`}>
                                <Save className="w-4 h-4" /> Guardar
                            </button>
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
