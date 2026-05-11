import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, router } from '@inertiajs/react'; // <-- Asegúrate de importar router
import { ListTree, Edit2, Trash2, Plus, X, Save, AlertTriangle } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';

export default function TablaProcesos({ datos = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [itemActual, setItemActual] = useState(null);

    // 1. Estado limpio, sin _method
    const { data, setData, post, put, processing, reset, errors, clearErrors } = useForm({
        nombre: '',
        descripcion: '',
        monto_requerido: '',
        activo: true
    });

    const abrirNuevo = () => {
        setItemActual(null);
        reset();
        clearErrors();
        setModalAbierto(true);
    };

    const abrirEditar = (item) => {
        setItemActual(item);
        clearErrors();
        setData({ 
            nombre: item.nombre, 
            descripcion: item.descripcion || '',
            monto_requerido: item.monto_requerido || '',
            activo: item.activo
        });
        setModalAbierto(true);
    };

    // 2. Usamos put() nativo si estamos editando, post() si estamos creando
    const handleSubmit = (e) => {
        e.preventDefault();
        
        if (itemActual) {
            put(route('admin.catalogos.procesos.update', itemActual.id), { 
                onSuccess: () => { setModalAbierto(false); reset(); } 
            });
        } else {
            post(route('admin.catalogos.procesos.store'), { 
                onSuccess: () => { setModalAbierto(false); reset(); } 
            });
        }
    };

    // 3. Usamos router.delete() nativo para eliminar
    const confirmDelete = () => {
        router.delete(route('admin.catalogos.procesos.destroy', itemActual.id), {
            onSuccess: () => { setModalEliminar(false); setItemActual(null); }
        });
    };

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando Proceso_" />
            
            <div className="p-6 md:p-8 border-b theme-border flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="p-3 rounded-2xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                        <ListTree className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                    </div>
                    <div>
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0">Procesos de Venta_</h2>
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
                            <th className="px-6 py-4 text-left text-[9px] font-black text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Descripción_</th>
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
                                <td className="px-6 py-5 max-w-xs">
                                    <p className="text-xs font-bold theme-text-muted truncate">{item.descripcion || '—'}</p>
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
                <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xl animate-fade-in" onClick={() => setModalAbierto(false)}>
                    <div className="w-full max-w-lg theme-surface theme-border border shadow-2xl rounded-[2.5rem] relative modal-pop flex flex-col max-h-[90vh]" onClick={e => e.stopPropagation()}>
                        <div className="p-6 md:p-8 border-b theme-border flex items-center justify-between shrink-0">
                            <h2 className="text-xl font-black italic uppercase tracking-tighter theme-text-main flex items-center gap-3 m-0 leading-none">
                                <div className="p-2 rounded-xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                                    <ListTree className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                                </div>
                                {itemActual ? 'Editar' : 'Nuevo'} Proceso_
                            </h2>
                            <button onClick={() => setModalAbierto(false)} className="p-2 theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 rounded-full transition-colors outline-none">
                                <X className="w-6 h-6" />
                            </button>
                        </div>
                        
                        <form onSubmit={handleSubmit} className="p-8 space-y-6 overflow-y-auto">
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Nombre del Proceso_</label>
                                <input 
                                    type="text" 
                                    value={data.nombre} 
                                    onChange={e => setData('nombre', e.target.value)} 
                                    required 
                                    className="w-full px-5 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md" 
                                    style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                />
                                {errors.nombre && <p className="text-xs text-red-500 mt-1 px-1">{errors.nombre}</p>}
                            </div>
                            
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Descripción_</label>
                                <textarea 
                                    rows="3" 
                                    value={data.descripcion} 
                                    onChange={e => setData('descripcion', e.target.value)} 
                                    className="w-full px-5 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md resize-none" 
                                    style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                />
                            </div>
                            
                            <div className="flex items-center justify-between p-4 rounded-2xl border theme-border bg-black/5 dark:bg-white/5">
                                <div>
                                    <p className="text-sm font-black theme-text-main">Estado Operativo_</p>
                                    <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-0.5">
                                        {data.activo ? 'Activo en el sistema' : 'Inactivo / deshabilitado'}
                                    </p>
                                </div>
                                <button type="button" onClick={() => setData('activo', !data.activo)} className="gelia-switch shrink-0 scale-125 origin-right shadow-sm" data-active={data.activo}>
                                    <div className="gelia-switch-thumb shadow-md" />
                                </button>
                            </div>
                            
                            <button type="submit" disabled={processing} className="w-full py-4 rounded-2xl text-white font-black uppercase tracking-widest text-[11px] transition-all hover:scale-[1.02] shadow-xl flex justify-center items-center gap-3 disabled:opacity-60 outline-none" style={{ backgroundColor: 'var(--color-primario)' }}>
                                <Save className="w-5 h-5" /> Guardar
                            </button>
                        </form>
                    </div>
                </div>, document.body
            )}

            {modalEliminar && createPortal(
                <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xl animate-fade-in" onClick={() => setModalEliminar(false)}>
                    <div className="w-full max-w-sm theme-surface border-2 border-red-500/30 shadow-2xl rounded-[2.5rem] p-8 flex flex-col items-center gap-6 relative modal-pop" onClick={e => e.stopPropagation()}>
                        <div className="absolute inset-0 rounded-[2.5rem] bg-red-500/5 pointer-events-none" />
                        <button onClick={() => setModalEliminar(false)} className="absolute top-4 right-4 p-2 theme-text-muted hover:theme-text-main hover:bg-black/5 rounded-full transition-colors outline-none"><X className="w-5 h-5" /></button>
                        
                        <div className="w-20 h-20 rounded-full bg-red-500/10 flex items-center justify-center shadow-xl relative z-10">
                            <Trash2 className="w-9 h-9 text-red-500" />
                        </div>
                        <div className="text-center space-y-2 relative z-10">
                            <h3 className="text-xl font-black uppercase italic tracking-tighter text-red-600 dark:text-red-400 m-0">¿Eliminar Proceso?</h3>
                            <p className="text-sm font-bold theme-text-muted leading-snug">Estás a punto de eliminar <span className="font-black theme-text-main">"{itemActual?.nombre}"</span>. Esta acción no se puede deshacer.</p>
                        </div>
                        <div className="flex w-full gap-3 relative z-10">
                            <button type="button" onClick={() => setModalEliminar(false)} className="flex-1 py-3 px-4 theme-element border theme-border rounded-2xl text-xs font-bold theme-text-main transition-transform hover:scale-105 shadow-sm outline-none">Cancelar</button>
                            <button type="button" onClick={confirmDelete} className="flex-1 py-3 px-4 bg-red-500 hover:bg-red-600 text-white rounded-2xl text-xs font-black uppercase tracking-widest transition-all hover:scale-105 shadow-md flex items-center justify-center gap-2 outline-none"><Trash2 className="w-4 h-4" /> Eliminar</button>
                        </div>
                    </div>
                </div>, document.body
            )}
        </div>
    );
}