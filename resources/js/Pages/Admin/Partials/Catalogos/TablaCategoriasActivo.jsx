import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, router } from '@inertiajs/react';
import { Tags, Edit2, Trash2, Plus, X, Save, AlertTriangle } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';

export default function TablaCategoriasActivo({ datos = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [itemActual, setItemActual] = useState(null);

    const { data, setData, post, put, processing, reset, errors } = useForm({
        nombre: '',
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
            activo: item.activo,
        });
        setModalAbierto(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const accion = itemActual ? put : post;
        const ruta = itemActual
            ? route('admin.catalogos.categorias_activo.update', itemActual.id)
            : route('admin.catalogos.categorias_activo.store');
        accion(ruta, { onSuccess: () => { setModalAbierto(false); reset(); } });
    };

    const confirmDelete = () => {
        router.delete(route('admin.catalogos.categorias_activo.destroy', itemActual.id), {
            onSuccess: () => { setModalEliminar(false); setItemActual(null); },
        });
    };

    return (
        <div className="p-6">
            <GeliaLoader isVisible={processing} message="Guardando_" />

            <div className="flex justify-between items-center mb-6">
                <div className="flex items-center gap-3">
                    <Tags className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                    <h3 className="text-lg font-black uppercase italic theme-text-main">Categorías de Activo</h3>
                </div>
                <button type="button" onClick={abrirNuevo} className="flex items-center gap-2 px-4 py-2 rounded-xl text-[10px] font-black uppercase text-white" style={{ backgroundColor: 'var(--color-primario)' }}>
                    <Plus className="w-4 h-4" /> Nueva categoría
                </button>
            </div>

            <table className="w-full text-left">
                <thead>
                    <tr className="border-b theme-border text-[10px] font-black uppercase theme-text-muted">
                        <th className="py-3">Nombre</th>
                        <th className="py-3">Estado</th>
                        <th className="py-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    {datos.map((item) => (
                        <tr key={item.id} className="border-b theme-border">
                            <td className="py-3 font-bold theme-text-main">{item.nombre}</td>
                            <td className="py-3 text-xs">{item.activo ? 'Activo' : 'Inactivo'}</td>
                            <td className="py-3 flex gap-2">
                                <button type="button" onClick={() => abrirEditar(item)}><Edit2 className="w-4 h-4" /></button>
                                <button type="button" onClick={() => { setItemActual(item); setModalEliminar(true); }}><Trash2 className="w-4 h-4 text-red-500" /></button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            {modalAbierto && createPortal(
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50">
                    <div className="theme-surface rounded-3xl border theme-border w-full max-w-md">
                        <div className="flex justify-between p-6 border-b theme-border">
                            <h2 className="font-black uppercase italic">{itemActual ? 'Editar categoría' : 'Nueva categoría'}</h2>
                            <button type="button" onClick={() => setModalAbierto(false)}><X /></button>
                        </div>
                        <form onSubmit={handleSubmit} className="p-6 space-y-4">
                            <input value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} placeholder="Nombre (ej. Computadora)" className="w-full rounded-xl px-3 py-2 theme-element border theme-border" />
                            {errors.nombre && <p className="text-red-500 text-xs">{errors.nombre}</p>}
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={data.activo} onChange={(e) => setData('activo', e.target.checked)} />
                                Activa
                            </label>
                            <button type="submit" className="w-full flex items-center justify-center gap-2 py-3 rounded-xl text-white font-black uppercase text-[10px]" style={{ backgroundColor: 'var(--color-primario)' }}>
                                <Save className="w-4 h-4" /> Guardar
                            </button>
                        </form>
                    </div>
                </div>,
                document.body,
            )}

            {modalEliminar && createPortal(
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50">
                    <div className="theme-surface rounded-3xl border theme-border w-full max-w-sm p-6 text-center">
                        <AlertTriangle className="w-10 h-10 text-amber-500 mx-auto mb-3" />
                        <p className="font-bold mb-4">¿Eliminar categoría «{itemActual?.nombre}»?</p>
                        <div className="flex gap-3 justify-center">
                            <button type="button" onClick={() => setModalEliminar(false)} className="px-4 py-2 rounded-xl border theme-border">Cancelar</button>
                            <button type="button" onClick={confirmDelete} className="px-4 py-2 rounded-xl bg-red-500 text-white font-bold">Eliminar</button>
                        </div>
                    </div>
                </div>,
                document.body,
            )}
        </div>
    );
}
