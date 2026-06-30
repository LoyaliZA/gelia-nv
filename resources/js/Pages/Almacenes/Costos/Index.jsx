import React, { useState, useMemo, useEffect, useCallback } from 'react';
import { createPortal } from 'react-dom';
import { Head, router, useForm } from '@inertiajs/react';
import { DollarSign, Plus, Edit2, Trash2, Save, Search } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import GeliaPaginacion from '@/Components/GeliaPaginacion';
import GeliaLoader from '@/Components/GeliaLoader';
import EncabezadoOrdenable from '@/Components/Almacenes/EncabezadoOrdenable';
import SelectorProducto from '@/Components/Almacenes/SelectorProducto';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '@/utils/geliaTheme';

export default function Index({ auth, costos, sucursales, almacenes, filtros }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [itemActual, setItemActual] = useState(null);
    const [sucursalId, setSucursalId] = useState(filtros?.sucursal_id || '');
    const [almacenId, setAlmacenId] = useState(filtros?.almacen_id || '');
    const [busqueda, setBusqueda] = useState(filtros?.q || '');
    const lista = costos?.data || [];
    const puedeGestionar = auth?.user?.permissions?.includes('almacenes.costos.gestionar') || auth?.user?.roles?.includes('Super Admin');

    const almacenesFiltrados = useMemo(() => {
        if (!sucursalId) return almacenes;
        return almacenes.filter((a) => String(a.sucursal_id) === String(sucursalId));
    }, [almacenes, sucursalId]);

    const { data, setData, post, put, processing, reset } = useForm({
        producto_id: '',
        almacen_id: '',
        costo: 0,
        costo_reposicion: '',
        precio_venta: '',
    });

    const paramsBase = useCallback((extra = {}) => ({
        sucursal_id: sucursalId,
        almacen_id: almacenId,
        q: busqueda,
        sort: filtros?.sort,
        dir: filtros?.dir,
        ...extra,
    }), [sucursalId, almacenId, busqueda, filtros?.sort, filtros?.dir]);

    const aplicarFiltros = (extra = {}) => {
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
        const timer = setTimeout(() => {
            router.get(route('almacenes.costos.index'), paramsBase({ q: busqueda, page: 1 }), { preserveState: true, replace: true });
        }, 400);
        return () => clearTimeout(timer);
    }, [busqueda]);

    const abrirNuevo = () => {
        setItemActual(null);
        reset();
        setData('almacen_id', almacenId || '');
        setModalAbierto(true);
    };

    const abrirEditar = (item) => {
        setItemActual(item);
        setData({
            producto_id: item.producto_id,
            almacen_id: item.almacen_id,
            costo: item.costo,
            costo_reposicion: item.costo_reposicion ?? '',
            precio_venta: item.precio_venta ?? '',
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

    const fmt = (v) => v != null ? `$${parseFloat(v).toFixed(2)}` : '—';

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
                                Costos por Almacén
                            </h1>
                        </div>
                        {puedeGestionar && (
                            <button onClick={abrirNuevo} className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact`}>
                                <Plus className="w-4 h-4" /> Nuevo Costo
                            </button>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <select value={sucursalId} onChange={(e) => { setSucursalId(e.target.value); setAlmacenId(''); }} className="theme-input px-3 py-2 text-[11px] font-bold uppercase">
                            <option value="">Todas las sucursales</option>
                            {sucursales.map((s) => <option key={s.id} value={s.id}>{s.nombre}</option>)}
                        </select>
                        <select value={almacenId} onChange={(e) => setAlmacenId(e.target.value)} className="theme-input px-3 py-2 text-[11px] font-bold uppercase">
                            <option value="">Todos los almacenes</option>
                            {almacenesFiltrados.map((a) => <option key={a.id} value={a.id}>{a.codigo} - {a.nombre}</option>)}
                        </select>
                        <form onSubmit={(e) => { e.preventDefault(); aplicarFiltros({ page: 1 }); }} className="flex gap-2 flex-1 min-w-[200px]">
                            <input value={busqueda} onChange={(e) => setBusqueda(e.target.value)} placeholder="Buscar por nombre o código..." className="theme-input flex-1 px-3 py-2 text-[11px] font-bold" />
                            <button type="submit" className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact`}><Search className="w-4 h-4" /></button>
                        </form>
                        <button onClick={() => aplicarFiltros({ page: 1 })} className="px-4 py-2 text-[10px] font-black uppercase theme-element border theme-border rounded-xl">Filtrar</button>
                    </div>
                </header>

                <div className={geliaCardClass('overflow-hidden')}>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left min-w-[800px]">
                            <thead>
                                <tr className="border-b theme-border text-[10px] font-black uppercase tracking-widest theme-text-muted">
                                    <EncabezadoOrdenable columna="producto" etiqueta="Producto" sortActual={filtros?.sort} dirActual={filtros?.dir} onOrdenar={handleOrdenar} />
                                    <EncabezadoOrdenable columna="almacen" etiqueta="Almacén" sortActual={filtros?.sort} dirActual={filtros?.dir} onOrdenar={handleOrdenar} />
                                    <EncabezadoOrdenable columna="costo" etiqueta="Costo" sortActual={filtros?.sort} dirActual={filtros?.dir} onOrdenar={handleOrdenar} alineacion="right" />
                                    <EncabezadoOrdenable columna="costo_reposicion" etiqueta="Costo Reposición" sortActual={filtros?.sort} dirActual={filtros?.dir} onOrdenar={handleOrdenar} alineacion="right" />
                                    <EncabezadoOrdenable columna="precio_venta" etiqueta="Precio Venta" sortActual={filtros?.sort} dirActual={filtros?.dir} onOrdenar={handleOrdenar} alineacion="right" />
                                    {puedeGestionar && <th className="px-4 py-4 text-right">Acciones</th>}
                                </tr>
                            </thead>
                            <tbody>
                                {lista.length === 0 ? (
                                    <tr><td colSpan={6} className="px-4 py-16 text-center theme-text-muted text-sm font-bold uppercase">Sin costos registrados</td></tr>
                                ) : lista.map((c) => (
                                    <tr key={c.id} className="border-b theme-border hover:bg-black/5 dark:hover:bg-white/5">
                                        <td className="px-4 py-3">
                                            <span className="font-black text-sm block">{c.producto?.descripcion}</span>
                                            <span className="text-[10px] font-bold theme-text-muted">SKU {c.producto?.sku}</span>
                                        </td>
                                        <td className="px-4 py-3 text-[11px] font-bold uppercase">{c.almacen?.nombre}</td>
                                        <td className="px-4 py-3 text-right font-bold text-red-500">{fmt(c.costo)}</td>
                                        <td className="px-4 py-3 text-right font-bold">{fmt(c.costo_reposicion)}</td>
                                        <td className="px-4 py-3 text-right font-bold text-emerald-600 dark:text-emerald-400">{fmt(c.precio_venta)}</td>
                                        {puedeGestionar && (
                                            <td className="px-4 py-3 text-right">
                                                <div className="flex justify-end gap-2">
                                                    <button onClick={() => abrirEditar(c)} className="p-2 theme-element border theme-border rounded-xl"><Edit2 className="w-4 h-4" /></button>
                                                    <button onClick={() => router.delete(route('almacenes.costos.destroy', c.id))} className="p-2 theme-element border theme-border rounded-xl hover:bg-red-500 hover:text-white"><Trash2 className="w-4 h-4" /></button>
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
                        <h3 className="text-xl font-black italic uppercase theme-text-main mb-6">{itemActual ? 'Editar' : 'Nuevo'} Costo</h3>
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
                                <label className="text-[10px] font-black uppercase theme-text-muted">Almacén *</label>
                                <select required value={data.almacen_id} onChange={(e) => setData('almacen_id', e.target.value)} className="theme-input w-full mt-1 px-3 py-3 text-sm font-bold">
                                    <option value="">Seleccionar...</option>
                                    {almacenes.map((a) => <option key={a.id} value={a.id}>{a.codigo} - {a.nombre}</option>)}
                                </select>
                            </div>
                            {['costo', 'costo_reposicion', 'precio_venta'].map((field) => (
                                <div key={field}>
                                    <label className="text-[10px] font-black uppercase theme-text-muted">{field.replace('_', ' ')}</label>
                                    <input type="number" step="0.01" min="0" value={data[field]} onChange={(e) => setData(field, e.target.value)} className="theme-input w-full mt-1 px-4 py-3 text-sm font-bold" />
                                </div>
                            ))}
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
