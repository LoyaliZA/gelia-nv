import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, router } from '@inertiajs/react';
import { Landmark, Edit2, Trash2, Plus, X, Save, AlertTriangle } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';

export default function TablaBancos({ datos = [] }) {
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
            ? route('admin.catalogos.bancos.update', itemActual.id)
            : route('admin.catalogos.bancos.store');
        accion(ruta, { onSuccess: () => { setModalAbierto(false); reset(); } });
    };

    const confirmDelete = () => {
        router.delete(route('admin.catalogos.bancos.destroy', itemActual.id), {
            onSuccess: () => { setModalEliminar(false); setItemActual(null); },
        });
    };

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando Banco_" />

            <div className="p-6 md:p-8 border-b theme-border flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="p-3 rounded-2xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                        <Landmark className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                    </div>
                    <div>
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0">Bancos_</h2>
                        <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mt-0.5">{datos.length} registros</p>
                    </div>
                </div>
                <button onClick={abrirNuevo} className="flex items-center gap-2 px-6 py-3 rounded-2xl font-black uppercase text-xs transition-all hover:scale-105 shadow-md text-white outline-none" style={{ backgroundColor: 'var(--color-primario)' }}>
                    <Plus className="w-4 h-4" /> Nuevo
                </button>
            </div>

            <div className="overflow-x-auto">
                <table className="w-full border-collapse">
                    <thead>
                        <tr className="border-b-2 border-[var(--color-primario)]/30">
                            <th className="px-6 py-4 text-left text-[9px] font-black text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Nombre / ID_</th>
                            <th className="px-6 py-4 text-left text-[9px] font-black text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Status_</th>
                            <th className="px-6 py-4 text-right text-[9px] font-black text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Acciones_</th>
                        </tr>
                    </thead>
                    <tbody>
                        {datos.map((item) => (
                            <tr key={item.id} className="group transition-all duration-150 border-b theme-border last:border-0 hover:ring-2 hover:ring-inset hover:ring-[var(--color-primario)]/30">
                                <td className="px-6 py-5">
                                    <p className="text-sm font-black theme-text-main uppercase italic leading-tight">{item.nombre}</p>
                                    <p className="text-[9px] font-bold theme-text-muted uppercase tracking-tighter mt-0.5">ID: #{item.id}</p>
                                </td>
                                <td className="px-6 py-5">
                                    <span className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[9px] font-black uppercase tracking-widest ${item.activo ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-red-500/10 text-red-600 dark:text-red-400'}`}>
                                        <span className={`w-1.5 h-1.5 rounded-full ${item.activo ? 'bg-emerald-500 animate-pulse' : 'bg-red-500'}`} />
                                        {item.activo ? 'Activo' : 'Inactivo'}
                                    </span>
                                </td>
                                <td className="px-6 py-5 text-right">
                                    <div className="flex justify-end gap-2">
                                        <button onClick={() => abrirEditar(item)} className="p-2.5 theme-element border theme-border rounded-xl hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] transition-all group/btn outline-none">
                                            <Edit2 className="w-4 h-4 theme-text-main group-hover/btn:text-[var(--color-primario)] transition-colors" />
                                        </button>
                                        <button onClick={() => { setItemActual(item); setModalEliminar(true); }} className="p-2.5 theme-element border theme-border rounded-xl hover:bg-red-500 hover:border-red-500 transition-all group/del outline-none">
                                            <Trash2 className="w-4 h-4 theme-text-main group-hover/del:text-white transition-colors" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {modalAbierto && createPortal(
                <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md" onClick={() => setModalAbierto(false)}>
                    <div className="w-full max-w-md theme-surface border theme-border rounded-[2rem] p-8 shadow-2xl relative" onClick={e => e.stopPropagation()}>
                        <button onClick={() => setModalAbierto(false)} className="absolute top-4 right-4 p-2 theme-text-muted hover:theme-text-main rounded-xl outline-none"><X className="w-5 h-5" /></button>
                        <h3 className="text-xl font-black italic theme-text-main uppercase mb-6">{itemActual ? 'Editar Banco_' : 'Nuevo Banco_'}</h3>
                        <form onSubmit={handleSubmit} className="space-y-5">
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">Nombre_</label>
                                <input type="text" required value={data.nombre} onChange={e => setData('nombre', e.target.value)} className="w-full mt-2 px-4 py-3 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2" />
                                {errors.nombre && <p className="text-xs text-red-500 mt-1">{errors.nombre}</p>}
                            </div>
                            <label className="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" checked={data.activo} onChange={e => setData('activo', e.target.checked)} className="w-4 h-4" />
                                <span className="text-sm font-bold theme-text-main">Activo</span>
                            </label>
                            <button type="submit" disabled={processing} className="w-full py-4 text-white rounded-xl font-black uppercase text-[11px] tracking-widest flex items-center justify-center gap-2 outline-none disabled:opacity-50" style={{ backgroundColor: 'var(--color-primario)' }}>
                                <Save className="w-4 h-4" /> Guardar
                            </button>
                        </form>
                    </div>
                </div>,
                document.body
            )}

            {modalEliminar && createPortal(
                <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md" onClick={() => setModalEliminar(false)}>
                    <div className="w-full max-w-sm theme-surface border theme-border rounded-[2rem] p-8 shadow-2xl text-center" onClick={e => e.stopPropagation()}>
                        <AlertTriangle className="w-12 h-12 text-red-500 mx-auto mb-4" />
                        <h3 className="text-lg font-black theme-text-main uppercase mb-2">¿Eliminar banco?</h3>
                        <p className="text-sm theme-text-muted mb-6">Se eliminará «{itemActual?.nombre}».</p>
                        <div className="flex gap-3">
                            <button onClick={() => setModalEliminar(false)} className="flex-1 py-3 theme-element border theme-border rounded-xl font-black uppercase text-[10px] outline-none">Cancelar</button>
                            <button onClick={confirmDelete} className="flex-1 py-3 bg-red-600 text-white rounded-xl font-black uppercase text-[10px] outline-none">Eliminar</button>
                        </div>
                    </div>
                </div>,
                document.body
            )}
        </div>
    );
}
