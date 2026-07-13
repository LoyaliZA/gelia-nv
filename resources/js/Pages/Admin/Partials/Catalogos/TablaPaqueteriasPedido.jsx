import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, router } from '@inertiajs/react';
import { Edit2, Trash2, Plus, X, Save, AlertTriangle } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import { THEME_INPUT, THEME_MODAL_OVERLAY } from '../../../../utils/geliaTheme';

const ETIQUETAS_CATEGORIA = {
    comercial: 'Comercial',
    local_regional: 'Local / Regional',
};

export default function TablaPaqueteriasPedido({ datos = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [itemActual, setItemActual] = useState(null);

    const { data, setData, post, put, processing, reset, errors } = useForm({
        nombre: '',
        categoria: 'local_regional',
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
            categoria: item.categoria || 'local_regional',
            activo: item.activo,
        });
        setModalAbierto(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const accion = itemActual ? put : post;
        const ruta = itemActual
            ? route('admin.catalogos.paqueterias_pedido.update', itemActual.id)
            : route('admin.catalogos.paqueterias_pedido.store');
        accion(ruta, { onSuccess: () => { setModalAbierto(false); reset(); } });
    };

    const confirmDelete = () => {
        router.delete(route('admin.catalogos.paqueterias_pedido.destroy', itemActual.id), {
            onSuccess: () => { setModalEliminar(false); setItemActual(null); },
        });
    };

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando Paquetería_" />
            <div className="p-6 md:p-8 border-b theme-border flex items-center justify-between">
                <div>
                    <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0">Paqueterías_</h2>
                    <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mt-0.5">{datos.length} registros</p>
                </div>
                <button type="button" onClick={abrirNuevo} className="flex items-center gap-2 px-6 py-3 rounded-2xl font-black uppercase text-xs text-white outline-none" style={{ backgroundColor: 'var(--color-primario)' }}>
                    <Plus className="w-4 h-4" /> Nuevo
                </button>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full border-collapse">
                    <thead>
                        <tr className="border-b-2 border-[var(--color-primario)]/30">
                            <th className="px-6 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Nombre_</th>
                            <th className="px-6 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Categoría_</th>
                            <th className="px-6 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Status_</th>
                            <th className="px-6 py-4 text-right text-[9px] font-black theme-text-muted uppercase tracking-widest">Acciones_</th>
                        </tr>
                    </thead>
                    <tbody>
                        {datos.map((item) => (
                            <tr key={item.id} className="border-b theme-border last:border-0">
                                <td className="px-6 py-5 text-sm font-black theme-text-main uppercase italic">{item.nombre}</td>
                                <td className="px-6 py-5 text-sm font-bold theme-text-muted">{ETIQUETAS_CATEGORIA[item.categoria] || item.categoria}</td>
                                <td className="px-6 py-5">
                                    <span className={`inline-flex px-3 py-1.5 rounded-full text-[9px] font-black uppercase ${item.activo ? 'bg-emerald-500/10 text-emerald-600' : 'bg-red-500/10 text-red-600'}`}>
                                        {item.activo ? 'Activo' : 'Inactivo'}
                                    </span>
                                </td>
                                <td className="px-6 py-5 text-right">
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
                <div className={`${THEME_MODAL_OVERLAY} z-[200]`} onClick={() => setModalAbierto(false)}>
                    <div className="w-full max-w-md theme-surface border theme-border rounded-[2rem] p-8 shadow-2xl relative" onClick={(e) => e.stopPropagation()}>
                        <button type="button" onClick={() => setModalAbierto(false)} className="absolute top-4 right-4 p-2 rounded-full outline-none"><X className="w-5 h-5" /></button>
                        <h3 className="text-xl font-black italic theme-text-main uppercase mb-6">{itemActual ? 'Editar_' : 'Nuevo_'}</h3>
                        <form onSubmit={handleSubmit} className="space-y-5">
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">Nombre_</label>
                                <input type="text" required value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} className={`${THEME_INPUT} w-full mt-2 text-sm font-bold`} />
                                {errors.nombre && <p className="text-xs text-red-500 mt-1">{errors.nombre}</p>}
                            </div>
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">Categoría_</label>
                                <select value={data.categoria} onChange={(e) => setData('categoria', e.target.value)} className={`${THEME_INPUT} w-full mt-2 text-sm font-bold`}>
                                    <option value="comercial">Comercial</option>
                                    <option value="local_regional">Local / Regional</option>
                                </select>
                            </div>
                            <label className="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" checked={data.activo} onChange={(e) => setData('activo', e.target.checked)} className="w-4 h-4" />
                                <span className="text-sm font-bold theme-text-main">Activo</span>
                            </label>
                            <button type="submit" disabled={processing} className="w-full py-4 text-white rounded-xl font-black uppercase text-[11px] flex items-center justify-center gap-2 outline-none" style={{ backgroundColor: 'var(--color-primario)' }}>
                                <Save className="w-4 h-4" /> Guardar
                            </button>
                        </form>
                    </div>
                </div>,
                document.body
            )}
            {modalEliminar && createPortal(
                <div className={`${THEME_MODAL_OVERLAY} z-[200]`} onClick={() => setModalEliminar(false)}>
                    <div className="w-full max-w-sm theme-surface border theme-border rounded-[2rem] p-8 shadow-2xl text-center" onClick={(e) => e.stopPropagation()}>
                        <AlertTriangle className="w-12 h-12 text-red-500 mx-auto mb-4" />
                        <p className="text-sm theme-text-muted mb-6">¿Eliminar «{itemActual?.nombre}»?</p>
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
