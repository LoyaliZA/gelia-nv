import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, router } from '@inertiajs/react';
import { Edit2, Trash2, Plus, X, Save, AlertTriangle, MapPin } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import { THEME_INPUT, THEME_MODAL_OVERLAY } from '../../../../utils/geliaTheme';

/** Zonas de pedido BMA: nombre + costo de reexpedición configurable. */
export default function TablaZonasPedido({ datos = [], auth }) {
    const permisos = auth?.user?.permissions || [];
    const puedeEditar = permisos.includes('admin.catalogos') || auth?.user?.roles?.includes('Super Admin');
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [itemActual, setItemActual] = useState(null);

    const { data, setData, post, put, processing, reset, errors } = useForm({
        nombre: '',
        costo_adicional: '',
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
            nombre: item.nombre,
            costo_adicional: item.costo_adicional != null ? String(item.costo_adicional) : '',
            activo: item.activo,
        });
        setModalAbierto(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const accion = itemActual ? put : post;
        const ruta = itemActual
            ? route('admin.catalogos.zonas_pedido.update', itemActual.id)
            : route('admin.catalogos.zonas_pedido.store');
        accion(ruta, { onSuccess: () => { setModalAbierto(false); reset(); } });
    };

    const confirmDelete = () => {
        router.delete(route('admin.catalogos.zonas_pedido.destroy', itemActual.id), {
            onSuccess: () => { setModalEliminar(false); setItemActual(null); },
        });
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p className="text-sm font-black theme-text-main uppercase italic m-0 flex items-center gap-2">
                        <MapPin className="w-4 h-4" /> Zonas Pedido_
                    </p>
                    <p className="text-[10px] theme-text-muted font-bold m-0 mt-1">
                        El monto de «Con reexpedición» se aplica al elegir esa zona en el pedido (aunque el CP no esté en el catálogo CP).
                    </p>
                </div>
                {puedeEditar && (
                    <button type="button" onClick={abrirNuevo} className="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest theme-element border theme-border">
                        <Plus className="w-3.5 h-3.5" /> Nueva
                    </button>
                )}
            </div>

            <div className="space-y-2">
                {datos.map((item) => (
                    <div key={item.id} className="flex items-center justify-between gap-3 p-4 rounded-xl border theme-border theme-element">
                        <div className="min-w-0">
                            <p className="text-sm font-black theme-text-main uppercase italic m-0">{item.nombre}</p>
                            <p className="text-[10px] theme-text-muted font-bold m-0 mt-0.5">
                                Cargo reexpedición: {item.costo_adicional != null && Number(item.costo_adicional) > 0
                                    ? `$${Number(item.costo_adicional).toFixed(2)}`
                                    : '$0.00'}
                                {!item.activo ? ' · inactiva' : ''}
                            </p>
                        </div>
                        {puedeEditar && (
                            <div className="flex gap-2 shrink-0">
                                <button type="button" onClick={() => abrirEditar(item)} className="p-2 rounded-lg border theme-border outline-none" aria-label="Editar">
                                    <Edit2 className="w-4 h-4" />
                                </button>
                                <button type="button" onClick={() => { setItemActual(item); setModalEliminar(true); }} className="p-2 rounded-lg border theme-border outline-none text-red-500" aria-label="Eliminar">
                                    <Trash2 className="w-4 h-4" />
                                </button>
                            </div>
                        )}
                    </div>
                ))}
                {datos.length === 0 && (
                    <p className="text-xs theme-text-muted font-bold italic">Sin zonas registradas.</p>
                )}
            </div>

            {modalAbierto && createPortal(
                <div className={`${THEME_MODAL_OVERLAY} items-center`}>
                    <div className="theme-surface border theme-border rounded-2xl p-6 w-full max-w-md shadow-xl" onClick={(e) => e.stopPropagation()}>
                        <div className="flex justify-between items-center mb-4">
                            <p className="text-sm font-black uppercase italic m-0">{itemActual ? 'Editar zona' : 'Nueva zona'}</p>
                            <button type="button" onClick={() => setModalAbierto(false)} className="p-2 outline-none"><X className="w-4 h-4" /></button>
                        </div>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted">Nombre</label>
                                <input type="text" required value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} className={`${THEME_INPUT} w-full mt-2 text-sm font-bold`} />
                                {errors.nombre && <p className="text-xs text-red-500 mt-1">{errors.nombre}</p>}
                            </div>
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted">Costo reexpedición ($)</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.costo_adicional}
                                    onChange={(e) => setData('costo_adicional', e.target.value)}
                                    className={`${THEME_INPUT} w-full mt-2 text-sm font-bold`}
                                    placeholder="0.00"
                                />
                                <p className="text-[10px] theme-text-muted font-bold m-0 mt-1">En «Con reexpedición» suele ser 150. En «Sin…» dejar 0.</p>
                                {errors.costo_adicional && <p className="text-xs text-red-500 mt-1">{errors.costo_adicional}</p>}
                            </div>
                            <label className="flex items-center gap-2 text-xs font-bold">
                                <input type="checkbox" checked={data.activo} onChange={(e) => setData('activo', e.target.checked)} />
                                Activa
                            </label>
                            <button type="submit" disabled={processing} className="w-full inline-flex items-center justify-center gap-2 py-3 rounded-xl text-[10px] font-black uppercase tracking-widest theme-element border theme-border">
                                {processing ? <GeliaLoader message="Guardando_" /> : <><Save className="w-3.5 h-3.5" /> Guardar</>}
                            </button>
                        </form>
                    </div>
                </div>,
                document.body
            )}

            {modalEliminar && createPortal(
                <div className={`${THEME_MODAL_OVERLAY} items-center`}>
                    <div className="theme-surface border theme-border rounded-2xl p-6 w-full max-w-sm">
                        <AlertTriangle className="w-8 h-8 text-amber-500 mb-3" />
                        <p className="text-sm theme-text-muted mb-6">¿Eliminar «{itemActual?.nombre}»?</p>
                        <div className="flex gap-2 justify-end">
                            <button type="button" onClick={() => setModalEliminar(false)} className="px-4 py-2 rounded-xl text-xs font-bold border theme-border">Cancelar</button>
                            <button type="button" onClick={confirmDelete} className="px-4 py-2 rounded-xl text-xs font-bold bg-red-500 text-white">Eliminar</button>
                        </div>
                    </div>
                </div>,
                document.body
            )}
        </div>
    );
}
