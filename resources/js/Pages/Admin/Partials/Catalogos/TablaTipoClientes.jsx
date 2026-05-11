import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { UserCheck, Edit2, Trash2, Plus, X, Save } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';

export default function TablaTipoClientes({ datos = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [itemActual, setItemActual] = useState(null);

    const { data, setData, post, put, processing, reset, errors } = useForm({
        nombre: '',
        activo: true
    });

    const abrirNuevo = () => { setItemActual(null); reset(); setModalAbierto(true); };
    
    const abrirEditar = (item) => {
        setItemActual(item);
        setData({ nombre: item.nombre, activo: item.activo });
        setModalAbierto(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const accion = itemActual ? put : post;
        const ruta = itemActual ? route('admin.catalogos.tipo_clientes.update', itemActual.id) : route('admin.catalogos.tipo_clientes.store');
        accion(ruta, { onSuccess: () => { setModalAbierto(false); reset(); } });
    };

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Sincronizando Tipos_" />
            
            <div className="p-6 md:p-8 border-b theme-border flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="p-3 rounded-2xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                        <UserCheck className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                    </div>
                    <div>
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0">Tipos de Cliente_</h2>
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
                            <th className="px-6 py-4 text-left text-[9px] font-black text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Identificador_</th>
                            <th className="px-6 py-4 text-left text-[9px] font-black text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Estado_</th>
                            <th className="px-6 py-4 text-right text-[9px] font-black text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Acciones_</th>
                        </tr>
                    </thead>
                    <tbody>
                        {datos.map((item) => (
                            <tr key={item.id} className="group border-b theme-border hover:ring-2 hover:ring-inset hover:ring-[var(--color-primario)]/30">
                                <td className="px-6 py-5">
                                    <p className="text-sm font-black theme-text-main uppercase italic leading-tight">{item.nombre}</p>
                                </td>
                                <td className="px-6 py-5">
                                    <span className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[9px] font-black uppercase tracking-widest ${item.activo ? 'bg-emerald-500/10 text-emerald-600' : 'bg-red-500/10 text-red-600'}`}>
                                        <span className={`w-1.5 h-1.5 rounded-full ${item.activo ? 'bg-emerald-500 animate-pulse' : 'bg-red-500'}`} />
                                        {item.activo ? 'Activo' : 'Inactivo'}
                                    </span>
                                </td>
                                <td className="px-6 py-5 text-right">
                                    <button onClick={() => abrirEditar(item)} className="p-2.5 theme-element border theme-border rounded-xl hover:border-[var(--color-primario)] transition-all outline-none">
                                        <Edit2 className="w-4 h-4 theme-text-main hover:text-[var(--color-primario)]" />
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Modal CRUD Simplificado */}
            {modalAbierto && createPortal(
                <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xl animate-fade-in" onClick={() => setModalAbierto(false)}>
                    <div className="w-full max-w-lg theme-surface theme-border border shadow-2xl rounded-[2.5rem] modal-pop flex flex-col" onClick={e => e.stopPropagation()}>
                        <div className="p-6 md:p-8 border-b theme-border flex items-center justify-between">
                            <h2 className="text-xl font-black italic uppercase theme-text-main m-0 leading-none">Tipo de Cliente_</h2>
                            <button onClick={() => setModalAbierto(false)} className="p-2 theme-text-muted hover:theme-text-main rounded-full outline-none"><X className="w-6 h-6" /></button>
                        </div>
                        <form onSubmit={handleSubmit} className="p-8 space-y-6">
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Nombre Descriptivo_</label>
                                <input type="text" value={data.nombre} onChange={e => setData('nombre', e.target.value)} required className="w-full px-5 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2" style={{ '--tw-ring-color': 'var(--color-primario)' }} />
                            </div>
                            <button type="submit" disabled={processing} className="w-full py-4 rounded-2xl text-white font-black uppercase tracking-widest text-[11px] transition-all shadow-xl flex justify-center items-center gap-3" style={{ backgroundColor: 'var(--color-primario)' }}><Save className="w-5 h-5" /> Guardar cambios</button>
                        </form>
                    </div>
                </div>, document.body
            )}
        </div>
    );
}