import React, { useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, router } from '@inertiajs/react';
import { Package, Edit2, Trash2, Plus, X, Save, AlertTriangle, Upload } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import { formatoMoneda } from '../../../../utils/formatoMoneda';

const FORM_INICIAL = {
    sku: '',
    descripcion: '',
    existencia: '0',
    costo: '0',
    precio_venta: '',
    activo: true,
};

export default function TablaProductos({ datos = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [modalImportar, setModalImportar] = useState(false);
    const [itemActual, setItemActual] = useState(null);
    const archivoRef = useRef(null);

    const { data, setData, post, put, processing, reset, errors } = useForm({ ...FORM_INICIAL });
    const importForm = useForm({ archivo: null });

    const abrirNuevo = () => {
        setItemActual(null);
        reset();
        setModalAbierto(true);
    };

    const abrirEditar = (item) => {
        setItemActual(item);
        setData({
            sku: item.sku || '',
            descripcion: item.descripcion || '',
            existencia: String(item.existencia ?? 0),
            costo: String(item.costo ?? 0),
            precio_venta: item.precio_venta != null ? String(item.precio_venta) : '',
            activo: !!item.activo,
        });
        setModalAbierto(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const accion = itemActual ? put : post;
        const ruta = itemActual
            ? route('admin.catalogos.productos.update', itemActual.id)
            : route('admin.catalogos.productos.store');
        accion(ruta, { onSuccess: () => { setModalAbierto(false); reset(); } });
    };

    const confirmDelete = () => {
        router.delete(route('admin.catalogos.productos.destroy', itemActual.id), {
            onSuccess: () => { setModalEliminar(false); setItemActual(null); },
        });
    };

    const handleImportar = (e) => {
        e.preventDefault();
        importForm.post(route('admin.catalogos.productos.import'), {
            forceFormData: true,
            onSuccess: () => {
                setModalImportar(false);
                importForm.reset();
                if (archivoRef.current) archivoRef.current.value = '';
            },
        });
    };

    const inputClass = 'w-full mt-2 px-4 py-3 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2';

    return (
        <div>
            <GeliaLoader isVisible={processing || importForm.processing} message="Procesando productos_" />

            <div className="p-6 md:p-8 border-b theme-border flex flex-wrap items-center justify-between gap-4">
                <div className="flex items-center gap-3">
                    <div className="p-3 rounded-2xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                        <Package className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                    </div>
                    <div>
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0">Productos / Inventario</h2>
                        <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mt-0.5">{datos.length} registros</p>
                    </div>
                </div>
                <div className="flex gap-2">
                    <button type="button" onClick={() => setModalImportar(true)} className="flex items-center gap-2 px-5 py-3 rounded-2xl font-black uppercase text-xs theme-element border theme-border outline-none">
                        <Upload className="w-4 h-4" /> Importar CSV
                    </button>
                    <button type="button" onClick={abrirNuevo} className="flex items-center gap-2 px-6 py-3 rounded-2xl font-black uppercase text-xs text-white outline-none" style={{ backgroundColor: 'var(--color-primario)' }}>
                        <Plus className="w-4 h-4" /> Nuevo
                    </button>
                </div>
            </div>

            <div className="overflow-x-auto">
                <table className="w-full border-collapse">
                    <thead>
                        <tr className="border-b-2 border-[var(--color-primario)]/30">
                            <th className="px-4 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Folio / SKU</th>
                            <th className="px-4 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Descripción</th>
                            <th className="px-4 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Existencia</th>
                            <th className="px-4 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Costo</th>
                            <th className="px-4 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Precio venta</th>
                            <th className="px-4 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Status</th>
                            <th className="px-4 py-4 text-right text-[9px] font-black theme-text-muted uppercase tracking-widest">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {datos.map((item) => (
                            <tr key={item.id} className="border-b theme-border last:border-0 hover:ring-2 hover:ring-inset hover:ring-[var(--color-primario)]/20">
                                <td className="px-4 py-4">
                                    <p className="text-sm font-black theme-text-main m-0">{item.folio}</p>
                                    <p className="text-[9px] font-bold theme-text-muted m-0">SKU: {item.sku}</p>
                                </td>
                                <td className="px-4 py-4 text-sm font-bold theme-text-main">{item.descripcion}</td>
                                <td className="px-4 py-4 text-sm font-bold">{item.existencia}</td>
                                <td className="px-4 py-4 text-sm">{formatoMoneda(item.costo)}</td>
                                <td className="px-4 py-4 text-sm">{item.precio_venta != null ? formatoMoneda(item.precio_venta) : '—'}</td>
                                <td className="px-4 py-4">
                                    <span className={`inline-flex px-3 py-1 rounded-full text-[9px] font-black uppercase ${item.activo ? 'bg-emerald-500/10 text-emerald-600' : 'bg-red-500/10 text-red-600'}`}>
                                        {item.activo ? 'Activo' : 'Inactivo'}
                                    </span>
                                </td>
                                <td className="px-4 py-4 text-right">
                                    <div className="flex justify-end gap-2">
                                        <button type="button" onClick={() => abrirEditar(item)} className="p-2.5 theme-element border theme-border rounded-xl outline-none"><Edit2 className="w-4 h-4" /></button>
                                        <button type="button" onClick={() => { setItemActual(item); setModalEliminar(true); }} className="p-2.5 theme-element border theme-border rounded-xl outline-none"><Trash2 className="w-4 h-4" /></button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {modalAbierto && createPortal(
                <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md" onClick={() => setModalAbierto(false)}>
                    <div className="w-full max-w-lg theme-surface border theme-border rounded-[2rem] p-8 shadow-2xl relative" onClick={(e) => e.stopPropagation()}>
                        <button type="button" onClick={() => setModalAbierto(false)} className="absolute top-4 right-4 p-2 theme-text-muted rounded-xl outline-none"><X className="w-5 h-5" /></button>
                        <h3 className="text-xl font-black italic theme-text-main uppercase mb-6">{itemActual ? 'Editar producto' : 'Nuevo producto'}</h3>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted">SKU</label>
                                <input type="text" required value={data.sku} onChange={(e) => setData('sku', e.target.value)} className={inputClass} />
                                {errors.sku && <p className="text-xs text-red-500 mt-1">{errors.sku}</p>}
                            </div>
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted">Descripción</label>
                                <input type="text" required value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} className={inputClass} />
                                {errors.descripcion && <p className="text-xs text-red-500 mt-1">{errors.descripcion}</p>}
                            </div>
                            <div className="grid grid-cols-3 gap-3">
                                <div>
                                    <label className="text-[10px] font-black uppercase theme-text-muted">Existencia</label>
                                    <input type="number" min="0" value={data.existencia} onChange={(e) => setData('existencia', e.target.value)} className={inputClass} />
                                </div>
                                <div>
                                    <label className="text-[10px] font-black uppercase theme-text-muted">Costo</label>
                                    <input type="number" min="0" step="0.01" value={data.costo} onChange={(e) => setData('costo', e.target.value)} className={inputClass} />
                                </div>
                                <div>
                                    <label className="text-[10px] font-black uppercase theme-text-muted">Precio venta</label>
                                    <input type="number" min="0" step="0.01" value={data.precio_venta} onChange={(e) => setData('precio_venta', e.target.value)} className={inputClass} />
                                </div>
                            </div>
                            <label className="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" checked={data.activo} onChange={(e) => setData('activo', e.target.checked)} className="w-4 h-4" />
                                <span className="text-sm font-bold theme-text-main">Activo</span>
                            </label>
                            <button type="submit" disabled={processing} className="w-full py-4 text-white rounded-xl font-black uppercase text-[11px] outline-none disabled:opacity-50" style={{ backgroundColor: 'var(--color-primario)' }}>
                                <Save className="w-4 h-4 inline mr-2" /> Guardar
                            </button>
                        </form>
                    </div>
                </div>,
                document.body
            )}

            {modalImportar && createPortal(
                <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md" onClick={() => setModalImportar(false)}>
                    <div className="w-full max-w-md theme-surface border theme-border rounded-[2rem] p-8 shadow-2xl" onClick={(e) => e.stopPropagation()}>
                        <h3 className="text-xl font-black italic theme-text-main uppercase mb-2">Importar CSV</h3>
                        <p className="text-xs theme-text-muted mb-4">Columnas: sku, descripcion, existencia, costo, precio_venta</p>
                        <form onSubmit={handleImportar} className="space-y-4">
                            <input ref={archivoRef} type="file" accept=".csv,.txt" required onChange={(e) => importForm.setData('archivo', e.target.files[0])} className="w-full text-sm" />
                            {importForm.errors.archivo && <p className="text-xs text-red-500">{importForm.errors.archivo}</p>}
                            <div className="flex gap-3">
                                <button type="button" onClick={() => setModalImportar(false)} className="flex-1 py-3 theme-element border theme-border rounded-xl font-black uppercase text-[10px] outline-none">Cancelar</button>
                                <button type="submit" disabled={importForm.processing} className="flex-1 py-3 text-white rounded-xl font-black uppercase text-[10px] outline-none" style={{ backgroundColor: 'var(--color-primario)' }}>Importar</button>
                            </div>
                        </form>
                    </div>
                </div>,
                document.body
            )}

            {modalEliminar && createPortal(
                <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md" onClick={() => setModalEliminar(false)}>
                    <div className="w-full max-w-sm theme-surface border theme-border rounded-[2rem] p-8 shadow-2xl text-center" onClick={(e) => e.stopPropagation()}>
                        <AlertTriangle className="w-12 h-12 text-red-500 mx-auto mb-4" />
                        <h3 className="text-lg font-black theme-text-main uppercase mb-2">¿Eliminar producto?</h3>
                        <p className="text-sm theme-text-muted mb-6">Se eliminará «{itemActual?.descripcion}».</p>
                        <div className="flex gap-3">
                            <button type="button" onClick={() => setModalEliminar(false)} className="flex-1 py-3 theme-element border theme-border rounded-xl font-black uppercase text-[10px] outline-none">Cancelar</button>
                            <button type="button" onClick={confirmDelete} className="flex-1 py-3 bg-red-600 text-white rounded-xl font-black uppercase text-[10px] outline-none">Eliminar</button>
                        </div>
                    </div>
                </div>,
                document.body
            )}
        </div>
    );
}
