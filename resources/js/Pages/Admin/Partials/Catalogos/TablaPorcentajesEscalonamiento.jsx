import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { TrendingUp, Edit2, Trash2, Plus, X, Save } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';

export default function TablaPorcentajesEscalonamiento({ datos = [], listas = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [itemActual, setItemActual] = useState(null);

    const { data, setData, post, put, delete: destroy, processing, reset, errors } = useForm({
        catalogo_lista_descuento_id: '',
        porcentaje_descuento: '',
        activo: true,
    });

    const listasDisponibles = listas.filter(l =>
        !l.nombre.toUpperCase().includes('COLABORADOR') &&
        !l.nombre.toUpperCase().includes('PLATAFORMAS')
    );

    const abrirNuevo = () => { setItemActual(null); reset(); setModalAbierto(true); };

    const abrirEditar = (item) => {
        setItemActual(item);
        setData({
            catalogo_lista_descuento_id: item.catalogo_lista_descuento_id,
            porcentaje_descuento: item.porcentaje_descuento,
            activo: item.activo,
        });
        setModalAbierto(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const accion = itemActual ? put : post;
        const ruta = itemActual
            ? route('admin.catalogos.porcentajes_escalonamiento.update', itemActual.id)
            : route('admin.catalogos.porcentajes_escalonamiento.store');
        accion(ruta, { onSuccess: () => { setModalAbierto(false); reset(); } });
    };

    const confirmDelete = () => {
        destroy(route('admin.catalogos.porcentajes_escalonamiento.destroy', itemActual.id), {
            onSuccess: () => { setModalEliminar(false); setItemActual(null); },
        });
    };

    const idsConPorcentaje = datos.map(d => d.catalogo_lista_descuento_id);
    const listasParaNuevo = listasDisponibles.filter(l => !idsConPorcentaje.includes(l.id) || itemActual?.catalogo_lista_descuento_id === l.id);

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Procesando Escalonamiento_" />

            <div className="p-6 md:p-8 border-b theme-border flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="p-3 rounded-2xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                        <TrendingUp className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                    </div>
                    <div>
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0">Escalonamiento (Solicitudes)_</h2>
                        <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mt-0.5">
                            Descuento simple sobre cotización · ej. PLATA 2%, ORO 4%, DIAMANTE 6%
                        </p>
                    </div>
                </div>
                <button onClick={abrirNuevo} className="flex items-center gap-2 px-6 py-3 rounded-2xl font-black uppercase text-xs text-white outline-none shadow-md hover:scale-105 transition-all" style={{ backgroundColor: 'var(--color-primario)' }}>
                    <Plus className="w-4 h-4" /> Nuevo
                </button>
            </div>

            <div className="overflow-x-auto">
                <table className="w-full border-collapse">
                    <thead>
                        <tr className="border-b-2 border-[var(--color-primario)]/30">
                            <th className="px-6 py-4 text-left text-[9px] font-black uppercase tracking-widest text-zinc-500">Lista_</th>
                            <th className="px-6 py-4 text-left text-[9px] font-black uppercase tracking-widest text-zinc-500">Descuento %_</th>
                            <th className="px-6 py-4 text-left text-[9px] font-black uppercase tracking-widest text-zinc-500">Status_</th>
                            <th className="px-6 py-4 text-right text-[9px] font-black uppercase tracking-widest text-zinc-500">Acciones_</th>
                        </tr>
                    </thead>
                    <tbody>
                        {datos.map((item) => (
                            <tr key={item.id} className="border-b theme-border hover:ring-2 hover:ring-inset hover:ring-[var(--color-primario)]/30">
                                <td className="px-6 py-5 text-sm font-black uppercase italic">{item.lista_descuento?.nombre || '—'}</td>
                                <td className="px-6 py-5 text-xs font-black">{Number(item.porcentaje_descuento).toFixed(2)}%</td>
                                <td className="px-6 py-5">
                                    <span className={`text-[9px] font-black uppercase px-3 py-1.5 rounded-full ${item.activo ? 'bg-emerald-500/10 text-emerald-600' : 'bg-red-500/10 text-red-600'}`}>
                                        {item.activo ? 'Activo' : 'Inactivo'}
                                    </span>
                                </td>
                                <td className="px-6 py-5 text-right">
                                    <div className="flex justify-end gap-2">
                                        <button onClick={() => abrirEditar(item)} className="p-2.5 theme-element border theme-border rounded-xl outline-none"><Edit2 className="w-4 h-4" /></button>
                                        <button onClick={() => { setItemActual(item); setModalEliminar(true); }} className="p-2.5 theme-element border theme-border rounded-xl outline-none hover:bg-red-500"><Trash2 className="w-4 h-4" /></button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {modalAbierto && createPortal(
                <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xl" onClick={() => setModalAbierto(false)}>
                    <div className="w-full max-w-lg theme-surface border shadow-2xl rounded-[2.5rem] p-8 modal-pop" onClick={e => e.stopPropagation()}>
                        <h2 className="text-xl font-black italic uppercase mb-6 flex items-center gap-3"><TrendingUp className="w-5 h-5" style={{ color: 'var(--color-primario)' }} /> Porcentaje Escalonamiento_</h2>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <select value={data.catalogo_lista_descuento_id} onChange={e => setData('catalogo_lista_descuento_id', e.target.value)} required className="w-full px-5 py-4 theme-surface border theme-border rounded-xl font-bold outline-none">
                                <option value="">Selecciona lista...</option>
                                {(itemActual ? listasDisponibles : listasParaNuevo).map(l => <option key={l.id} value={l.id}>{l.nombre}</option>)}
                            </select>
                            <input type="number" step="0.01" min="0" max="100" value={data.porcentaje_descuento} onChange={e => setData('porcentaje_descuento', e.target.value)} required placeholder="Ej. 2.00" className="w-full px-5 py-4 theme-surface border theme-border rounded-xl font-bold outline-none" />
                            <button type="button" onClick={() => setData('activo', !data.activo)} className="gelia-switch" data-active={data.activo}><div className="gelia-switch-thumb" /></button>
                            <button type="submit" disabled={processing} className="w-full py-4 rounded-2xl text-white font-black uppercase text-[11px] outline-none" style={{ backgroundColor: 'var(--color-primario)' }}><Save className="w-5 h-5 inline mr-2" /> Guardar</button>
                        </form>
                        <button onClick={() => setModalAbierto(false)} className="absolute top-4 right-4 p-2 outline-none"><X className="w-5 h-5" /></button>
                    </div>
                </div>, document.body
            )}

            {modalEliminar && createPortal(
                <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xl">
                    <div className="theme-surface border rounded-[2.5rem] p-8 max-w-md w-full text-center space-y-4">
                        <p className="font-black uppercase text-red-600">¿Eliminar porcentaje de escalonamiento?</p>
                        <div className="flex gap-3">
                            <button onClick={() => setModalEliminar(false)} className="flex-1 py-3 border rounded-2xl font-bold outline-none">Cancelar</button>
                            <button onClick={confirmDelete} className="flex-1 py-3 bg-red-500 text-white rounded-2xl font-black uppercase outline-none">Eliminar</button>
                        </div>
                    </div>
                </div>, document.body
            )}
        </div>
    );
}
