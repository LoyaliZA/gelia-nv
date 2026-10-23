import React, { useState, useMemo, useEffect, useCallback, useRef } from 'react';
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

export default function Index({ auth, costos, sucursales, almacenes, filtros, sin_sucursales_operables }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [itemActual, setItemActual] = useState(null);
    const [sucursalId, setSucursalId] = useState(filtros?.sucursal_id || '');
    const [almacenId, setAlmacenId] = useState(filtros?.almacen_id || '');
    const [busqueda, setBusqueda] = useState(filtros?.q || '');
    const lista = costos?.data || [];
    const puedeGestionar = auth?.user?.permissions?.includes('almacenes.costos.gestionar') || auth?.user?.roles?.includes('Super Admin');
    const puedeBorrar = puedeGestionar || auth?.user?.permissions?.includes('catalogos.gestionar');
    const puedeImportar = auth?.user?.permissions?.includes('almacenes.costos.importar')
        || auth?.user?.permissions?.includes('catalogos.gestionar')
        || auth?.user?.roles?.includes('Super Admin');

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

    const paramsBase = useCallback((extra = {}) => {
        const params = {
            sucursal_id: sucursalId,
            almacen_id: almacenId,
            q: busqueda,
            sort: filtros?.sort,
            dir: filtros?.dir,
            filtro: filtros?.filtro,
            ...extra,
        };

        const texto = (valor, permitidos) => (typeof valor === 'string' && permitidos.includes(valor) ? valor : undefined);

        return {
            sucursal_id: params.sucursal_id || undefined,
            almacen_id: params.almacen_id || undefined,
            q: params.q || undefined,
            sort: texto(params.sort, ['producto', 'almacen', 'costo', 'costo_reposicion', 'precio_venta']),
            dir: texto(params.dir, ['asc', 'desc']),
            filtro: texto(params.filtro, ['costo_cero', 'sin_precio']),
            page: params.page,
        };
    }, [sucursalId, almacenId, busqueda, filtros?.sort, filtros?.dir, filtros?.filtro]);

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
            <Head title="Costos y precios" />
            <GeliaLoader isVisible={processing} message="Guardando costo_" />

            <div className="max-w-[1400px] mx-auto p-4 md:p-8 space-y-6">
                <header className={geliaCardClass('p-6 space-y-4')}>
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div>
                            <h1 className="text-2xl font-black italic uppercase theme-text-main flex items-center gap-3">
                                <DollarSign className="w-7 h-7" style={{ color: 'var(--color-primario)' }} />
                                Costos y precios
                            </h1>
                            <p className="text-[11px] font-bold theme-text-muted mt-2 max-w-2xl">
                                El precio de venta aquí es referencia del almacén; no actualiza automáticamente TiendaNube ni listas comerciales.
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
                                        <Plus className="w-4 h-4" /> Nuevo Costo
                                    </button>
                                )}
                            </div>
                        )}
                    </div>
                    {sin_sucursales_operables ? (
                        <div className="text-center">
                            <p className="text-sm font-bold theme-text-main">Sin sucursales operables asignadas a tu usuario.</p>
                            <p className="text-[11px] font-bold theme-text-muted mt-2">Solicita acceso a una sucursal para consultar costos y precios en este módulo.</p>
                        </div>
                    ) : (
                    <div className="space-y-3">
                    <EnlacesContextoAlmacen auth={auth} sucursalId={sucursalId} almacenId={almacenId} q={busqueda} actual="costos" />
                    <div className="flex flex-wrap gap-2">
                        <select value={sucursalId} onChange={(e) => { const valor = e.target.value; setSucursalId(valor); setAlmacenId(''); aplicarFiltros({ sucursal_id: valor, almacen_id: '', page: 1 }); }} className="theme-input px-3 py-2 text-[11px] font-bold uppercase">
                            <option value="">Todas las sucursales</option>
                            {sucursales.map((s) => <option key={s.id} value={s.id}>{s.nombre}</option>)}
                        </select>
                        <select value={almacenId} onChange={(e) => { const valor = e.target.value; setAlmacenId(valor); aplicarFiltros({ almacen_id: valor, page: 1 }); }} className="theme-input px-3 py-2 text-[11px] font-bold uppercase">
                            <option value="">Todos los almacenes</option>
                            {almacenesFiltrados.map((a) => <option key={a.id} value={a.id}>{a.codigo} - {a.nombre}</option>)}
                        </select>
                        <form onSubmit={(e) => { e.preventDefault(); aplicarFiltros({ page: 1 }); }} className="flex gap-2 flex-1 min-w-[200px]">
                            <input value={busqueda} onChange={(e) => setBusqueda(e.target.value)} placeholder="Buscar por nombre o código..." className="theme-input flex-1 px-3 py-2 text-[11px] font-bold" />
                            <button type="submit" className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact`}><Search className="w-4 h-4" /></button>
                        </form>
                        <button onClick={() => aplicarFiltros({ page: 1 })} className="px-4 py-2 text-[10px] font-black uppercase theme-element border theme-border rounded-xl">Filtrar</button>
                        <button type="button" onClick={() => aplicarFiltros({ filtro: 'costo_cero', page: 1 })} className="px-4 py-2 text-[10px] font-black uppercase theme-element border theme-border rounded-xl">Costo cero</button>
                        {puedeVerProductosPorAlmacen(auth) && (
                            <Link href={route('almacenes.cobertura.index', { costo: 'sin', sucursal_id: sucursalId || undefined, almacen_id: almacenId || undefined, q: busqueda || undefined })} className="px-4 py-2 text-[10px] font-black uppercase theme-element border theme-border rounded-xl">Productos sin costo</Link>
                        )}
                    </div>
                    </div>
                    )}
                </header>

                {!sin_sucursales_operables && (
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
                                    {puedeBorrar && <th className="px-4 py-4 text-right">Acciones</th>}
                                </tr>
                            </thead>
                            <tbody>
                                {lista.length === 0 ? (
                                    <tr>
                                        <td colSpan={puedeBorrar ? 6 : 5} className="px-4 py-16 text-center theme-text-muted text-sm">
                                            <p className="font-bold uppercase m-0 mb-2">Sin costos registrados</p>
                                            <p className="text-[11px] m-0 max-w-md mx-auto">
                                                El archivo identifica el producto por folio, que debe existir. Puede modificar el costo, el costo de reposición y el precio de ese almacén. Conserva la ficha y la existencia. Simular no aplica cambios al catálogo.
                                            </p>
                                        </td>
                                    </tr>
                                ) : lista.map((c) => (
                                    <tr key={c.id} className="border-b theme-border hover:bg-black/5 dark:hover:bg-white/5">
                                        <td className="px-4 py-3">
                                            <span className="font-black text-sm block">{c.producto?.descripcion}</span>
                                            <span className="text-[10px] font-bold theme-text-muted">SKU {c.producto?.sku}</span>
                                        </td>
                                        <td className="px-4 py-3 text-[11px] font-bold uppercase">{c.almacen?.nombre}</td>
                                        <td className="px-4 py-3 text-right font-bold theme-text-peligro">{fmt(c.costo)}</td>
                                        <td className="px-4 py-3 text-right font-bold">{fmt(c.costo_reposicion)}</td>
                                        <td className="px-4 py-3 text-right font-bold theme-text-exito">{fmt(c.precio_venta)}</td>
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
                )}
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
