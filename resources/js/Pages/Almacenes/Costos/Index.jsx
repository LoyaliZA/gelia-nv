import React, { useState, useEffect, useCallback, useRef } from 'react';
import { createPortal } from 'react-dom';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { DollarSign, Plus, Edit2, Trash2, Save, Search, UploadCloud } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaPaginacion from '@/Components/GeliaPaginacion';
import GeliaLoader from '@/Components/GeliaLoader';
import EncabezadoOrdenable from '@/Components/Almacenes/EncabezadoOrdenable';
import EnlacesContextoAlmacen, { puedeVerProductosPorAlmacen } from '@/Components/Almacenes/EnlacesContextoAlmacen';
import SelectorProducto from '@/Components/Almacenes/SelectorProducto';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '@/utils/geliaTheme';

export default function Index({ auth, costos, filtros }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [itemActual, setItemActual] = useState(null);
    const [busqueda, setBusqueda] = useState(filtros?.q || '');
    const lista = costos?.data || [];
    const puedeGestionar = auth?.user?.permissions?.includes('almacenes.costos.gestionar') || auth?.user?.roles?.includes('Super Admin');
    const puedeBorrar = puedeGestionar || auth?.user?.permissions?.includes('catalogos.gestionar');
    const puedeImportar = auth?.user?.permissions?.includes('almacenes.costos.importar')
        || auth?.user?.permissions?.includes('catalogos.gestionar')
        || auth?.user?.roles?.includes('Super Admin');

    const { data, setData, post, put, processing, reset } = useForm({
        producto_id: '',
        costo: 0,
        costo_reposicion: '',
    });

    const paramsBase = useCallback((extra = {}) => {
        const params = {
            q: busqueda,
            sort: filtros?.sort,
            dir: filtros?.dir,
            filtro: filtros?.filtro,
            ...extra,
        };
        const texto = (valor, permitidos) => (typeof valor === 'string' && permitidos.includes(valor) ? valor : undefined);

        return {
            q: params.q || undefined,
            sort: texto(params.sort, ['producto', 'costo', 'costo_reposicion']),
            dir: texto(params.dir, ['asc', 'desc']),
            filtro: texto(params.filtro, ['costo_cero']),
            page: params.page,
        };
    }, [busqueda, filtros?.sort, filtros?.dir, filtros?.filtro]);

    const paramsRef = useRef(paramsBase);
    paramsRef.current = paramsBase;
    const busquedaTimer = useRef(null);
    const cancelarBusquedaPendiente = () => {
        clearTimeout(busquedaTimer.current);
        busquedaTimer.current = null;
    };

    const aplicarFiltros = (extra = {}) => {
        cancelarBusquedaPendiente();
        router.get(route('almacenes.costos.index'), paramsBase(extra), { preserveState: true, replace: true });
    };

    const handleOrdenar = (columna) => {
        const sortActual = filtros?.sort;
        const dirActual = filtros?.dir || 'asc';
        const nuevaDir = sortActual === columna && dirActual === 'asc' ? 'desc' : 'asc';
        aplicarFiltros({ sort: columna, dir: nuevaDir, page: 1 });
    };

    useEffect(() => {
        if (busqueda === (filtros?.q ?? '')) return undefined;
        const q = busqueda;
        busquedaTimer.current = setTimeout(() => {
            busquedaTimer.current = null;
            router.get(route('almacenes.costos.index'), paramsRef.current({ q, page: 1 }), { preserveState: true, replace: true });
        }, 400);
        return cancelarBusquedaPendiente;
    }, [busqueda, filtros?.q]);

    const abrirNuevo = () => {
        setItemActual(null);
        reset();
        setModalAbierto(true);
    };

    const abrirEditar = (item) => {
        setItemActual(item);
        setData({
            producto_id: item.producto_id,
            costo: item.costo,
            costo_reposicion: item.costo_reposicion ?? '',
        });
        setModalAbierto(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const accion = itemActual ? put : post;
        const ruta = itemActual
            ? route('almacenes.costos.update', itemActual.id)
            : route('almacenes.costos.store');
        accion(ruta, { onSuccess: () => { setModalAbierto(false); reset(); } });
    };

    const fmt = (v) => v != null && v !== '' ? `$${parseFloat(v).toFixed(2)}` : '—';

    return (
        <AppLayout auth={auth}>
            <Head title="Costos" />
            <GeliaLoader isVisible={processing} message="Guardando costo_" />

            <div className="max-w-[1400px] mx-auto p-4 md:p-8 space-y-6">
                <header className={geliaCardClass('p-6 space-y-4')}>
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div>
                            <h1 className="text-2xl font-black italic uppercase theme-text-main flex items-center gap-3">
                                <DollarSign className="w-7 h-7" style={{ color: 'var(--color-primario)' }} />
                                Costos
                            </h1>
                            <p className="text-[11px] font-bold theme-text-muted mt-2 max-w-2xl">
                                Un costo promedio y un costo de reposición por producto, para toda la empresa. El precio de Público General se calcula en Productos con el margen.
                            </p>
                        </div>
                        {(puedeGestionar || puedeImportar) && (
                            <div className="flex flex-wrap gap-2">
                                {puedeImportar && (
                                    <Link href={route('almacenes.importaciones.index', { preset: 'costos' })} className="px-4 py-2 text-[10px] font-black uppercase theme-element border theme-border rounded-xl flex items-center gap-2 theme-text-main">
                                        <UploadCloud className="w-4 h-4" /> Importar
                                    </Link>
                                )}
                                {puedeGestionar && (
                                    <button onClick={abrirNuevo} className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact`}>
                                        <Plus className="w-4 h-4" /> Nuevo costo
                                    </button>
                                )}
                            </div>
                        )}
                    </div>
                    <div className="space-y-3">
                        <EnlacesContextoAlmacen auth={auth} q={busqueda} actual="costos" />
                        <div className="flex flex-wrap gap-2">
                            <form onSubmit={(e) => { e.preventDefault(); aplicarFiltros({ page: 1 }); }} className="flex gap-2 flex-1 min-w-[200px]">
                                <input value={busqueda} onChange={(e) => setBusqueda(e.target.value)} placeholder="Buscar por nombre o código..." className="theme-input flex-1 px-3 py-2 text-[11px] font-bold" />
                                <button type="submit" className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact`}><Search className="w-4 h-4" /></button>
                            </form>
                            <button type="button" onClick={() => aplicarFiltros({ filtro: 'costo_cero', page: 1 })} className="px-4 py-2 text-[10px] font-black uppercase theme-element border theme-border rounded-xl">Costo cero</button>
                            {puedeVerProductosPorAlmacen(auth) && (
                                <Link href={route('almacenes.cobertura.index', { costo: 'sin', q: busqueda || undefined })} className="px-4 py-2 text-[10px] font-black uppercase theme-element border theme-border rounded-xl">Productos sin costo</Link>
                            )}
                        </div>
                    </div>
                </header>

                <div className={geliaCardClass('overflow-hidden')}>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left min-w-[720px]">
                            <thead>
                                <tr className="border-b theme-border text-[10px] font-black uppercase tracking-widest theme-text-muted">
                                    <EncabezadoOrdenable columna="producto" etiqueta="Producto" sortActual={filtros?.sort} dirActual={filtros?.dir} onOrdenar={handleOrdenar} />
                                    <EncabezadoOrdenable columna="costo" etiqueta="Costo promedio" sortActual={filtros?.sort} dirActual={filtros?.dir} onOrdenar={handleOrdenar} alineacion="right" />
                                    <EncabezadoOrdenable columna="costo_reposicion" etiqueta="Costo reposición" sortActual={filtros?.sort} dirActual={filtros?.dir} onOrdenar={handleOrdenar} alineacion="right" />
                                    {puedeBorrar && <th className="px-4 py-4 text-right">Acciones</th>}
                                </tr>
                            </thead>
                            <tbody>
                                {lista.length === 0 ? (
                                    <tr>
                                        <td colSpan={puedeBorrar ? 4 : 3} className="px-4 py-16 text-center theme-text-muted text-sm">
                                            <p className="font-bold uppercase m-0 mb-2">Sin costos registrados</p>
                                            <p className="text-[11px] m-0 max-w-md mx-auto">
                                                El archivo identifica el producto por folio o SKU, que debe existir. Puede modificar el costo promedio y el costo de reposición de la empresa. Conserva la ficha y la existencia. Simular no aplica cambios al catálogo.
                                            </p>
                                        </td>
                                    </tr>
                                ) : lista.map((c) => (
                                    <tr key={c.id} className="border-b theme-border hover:bg-black/5 dark:hover:bg-white/5">
                                        <td className="px-4 py-3">
                                            <span className="font-black text-sm block">{c.producto?.descripcion}</span>
                                            <span className="text-[10px] font-bold theme-text-muted">SKU {c.producto?.sku}</span>
                                        </td>
                                        <td className="px-4 py-3 text-right font-bold theme-text-peligro">{fmt(c.costo)}</td>
                                        <td className="px-4 py-3 text-right font-bold">{fmt(c.costo_reposicion)}</td>
                                        {puedeBorrar && (
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex justify-end gap-2">
                                                    {puedeGestionar && (
                                                        <button type="button" aria-label="Editar costo" onClick={() => abrirEditar(c)} className="p-2 theme-element border theme-border rounded-xl"><Edit2 className="w-4 h-4" /></button>
                                                    )}
                                                    <button type="button" aria-label="Eliminar costo" onClick={() => router.delete(route('almacenes.costos.destroy', c.id))} className="p-2 theme-element border theme-border rounded-xl theme-text-peligro"><Trash2 className="w-4 h-4" /></button>
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {lista.length > 0 && <GeliaPaginacion paginator={costos} onIrAPagina={(p) => aplicarFiltros({ page: p })} embedded />}
                </div>
            </div>

            {modalAbierto && createPortal(
                <div className={THEME_MODAL_OVERLAY} onClick={() => setModalAbierto(false)}>
                    <div className={`${THEME_MODAL_SHELL} max-w-md p-8 modal-pop`} onClick={(e) => e.stopPropagation()}>
                        <h3 className="text-xl font-black italic uppercase theme-text-main mb-6">{itemActual ? 'Editar' : 'Nuevo'} costo</h3>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted">Producto *</label>
                                <SelectorProducto
                                    value={data.producto_id}
                                    productoInicial={itemActual?.producto}
                                    required
                                    onChange={(id) => setData('producto_id', id)}
                                />
                            </div>
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted">Costo promedio</label>
                                <input type="number" step="0.01" min="0" value={data.costo} onChange={(e) => setData('costo', e.target.value)} className="theme-input w-full mt-1 px-4 py-3 text-sm font-bold" />
                            </div>
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted">Costo de reposición</label>
                                <input type="number" step="0.01" min="0" value={data.costo_reposicion} onChange={(e) => setData('costo_reposicion', e.target.value)} className="theme-input w-full mt-1 px-4 py-3 text-sm font-bold" />
                            </div>
                            <button type="submit" disabled={processing || !data.producto_id} className={`${THEME_BTN_PRIMARY} w-full py-3 flex justify-center gap-2`}>
                                <Save className="w-4 h-4" /> Guardar
                            </button>
                        </form>
                    </div>
                </div>,
                document.body
            )}
        </AppLayout>
    );
}
