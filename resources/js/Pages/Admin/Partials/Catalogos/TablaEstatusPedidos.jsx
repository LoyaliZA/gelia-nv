import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, router } from '@inertiajs/react';
import { Activity, Edit2, Trash2, Plus, X, Save, AlertTriangle } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import { THEME_INPUT, THEME_MODAL_OVERLAY } from '../../../../utils/geliaTheme';

export default function TablaEstatusPedidos({ datos = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [itemActual, setItemActual] = useState(null);

    const { data, setData, post, put, processing, reset, errors } = useForm({
        codigo_interno: '',
        nombre_visual: '',
        color_hex: '#3B82F6',
        fase_ciclo: '',
        orden: 0,
        activo: true,
    });

    const abrirNuevo = () => {
        setItemActual(null);
        reset();
        setData('color_hex', '#3B82F6');
        setModalAbierto(true);
    };

    const abrirEditar = (item) => {
        setItemActual(item);
        setData({
            codigo_interno: item.codigo_interno,
            nombre_visual: item.nombre_visual,
            color_hex: item.color_hex,
            fase_ciclo: item.fase_ciclo,
            orden: item.orden ?? 0,
            activo: item.activo,
        });
        setModalAbierto(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const accion = itemActual ? put : post;
        const ruta = itemActual
            ? route('admin.catalogos.estatus_pedidos.update', itemActual.id)
            : route('admin.catalogos.estatus_pedidos.store');
        accion(ruta, { onSuccess: () => { setModalAbierto(false); reset(); } });
    };

    const confirmDelete = () => {
        router.delete(route('admin.catalogos.estatus_pedidos.destroy', itemActual.id), {
            onSuccess: () => { setModalEliminar(false); setItemActual(null); },
        });
    };

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando Estatus_" />
            <div className="p-6 md:p-8 border-b theme-border flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="p-3 rounded-2xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                        <Activity className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                    </div>
                    <div>
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0">Estatus Pedidos_</h2>
                        <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mt-0.5">{datos.length} registros</p>
                    </div>
                </div>
                <button type="button" onClick={abrirNuevo} className="flex items-center gap-2 px-6 py-3 rounded-2xl font-black uppercase text-xs text-white outline-none" style={{ backgroundColor: 'var(--color-primario)' }}>
                    <Plus className="w-4 h-4" /> Nuevo
                </button>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full border-collapse">
                    <thead>
                        <tr className="border-b-2 border-[var(--color-primario)]/30">
                            <th className="px-6 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Estatus_</th>
                            <th className="px-6 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Fase_</th>
                            <th className="px-6 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Color_</th>
                            <th className="px-6 py-4 text-right text-[9px] font-black theme-text-muted uppercase tracking-widest">Acciones_</th>
                        </tr>
                    </thead>
                    <tbody>
                        {datos.map((item) => (
                            <tr key={item.id} className="border-b theme-border last:border-0">
                                <td className="px-6 py-5">
                                    <p className="text-sm font-black theme-text-main uppercase italic">{item.nombre_visual}</p>
                                    <p className="text-[9px] theme-text-muted uppercase">{item.codigo_interno}</p>
                                </td>
                                <td className="px-6 py-5 text-[10px] font-bold theme-text-muted uppercase">{item.fase_ciclo}</td>
                                <td className="px-6 py-5">
                                    <span className="inline-flex items-center gap-2 px-3 py-1 rounded-full text-[9px] font-black uppercase text-white" style={{ backgroundColor: item.color_hex }}>
                                        {item.color_hex}
                                    </span>
                                </td>
                                <td className="px-6 py-5 text-right">
                                    <div className="flex justify-end gap-2">
                                        <button type="button" onClick={() => abrirEditar(item)} className="p-2.5 theme-element border theme-border rounded-xl outline-none"><Edit2 className="w-4 h-4 theme-text-main" /></button>
                                        <button type="button" onClick={() => { setItemActual(item); setModalEliminar(true); }} className="p-2.5 theme-element border theme-border rounded-xl outline-none"><Trash2 className="w-4 h-4 theme-text-main" /></button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {modalAbierto && createPortal(
                <div className={`${THEME_MODAL_OVERLAY} z-[200]`} onClick={() => setModalAbierto(false)}>
                    <div className="w-full max-w-lg theme-surface border theme-border rounded-[2rem] p-8 shadow-2xl relative" onClick={(e) => e.stopPropagation()}>
                        <button type="button" onClick={() => setModalAbierto(false)} className="absolute top-4 right-4 p-2 rounded-full theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 outline-none"><X className="w-5 h-5" /></button>
                        <h3 className="text-xl font-black italic theme-text-main uppercase mb-6">{itemActual ? 'Editar Estatus_' : 'Nuevo Estatus_'}</h3>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted">Código interno_</label>
                                <input type="text" required disabled={!!itemActual} value={data.codigo_interno} onChange={(e) => setData('codigo_interno', e.target.value)} className={`${THEME_INPUT} w-full mt-1 text-sm font-bold disabled:opacity-50`} />
                            </div>
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted">Nombre visual_</label>
                                <input type="text" required value={data.nombre_visual} onChange={(e) => setData('nombre_visual', e.target.value)} className={`${THEME_INPUT} w-full mt-1 text-sm font-bold`} />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="text-[10px] font-black uppercase theme-text-muted">Fase ciclo_</label>
                                    <input type="text" required value={data.fase_ciclo} onChange={(e) => setData('fase_ciclo', e.target.value)} className={`${THEME_INPUT} w-full mt-1 text-sm font-bold`} />
                                </div>
                                <div>
                                    <label className="text-[10px] font-black uppercase theme-text-muted">Orden_</label>
                                    <input type="number" min="0" value={data.orden} onChange={(e) => setData('orden', parseInt(e.target.value, 10) || 0)} className={`${THEME_INPUT} w-full mt-1 text-sm font-bold`} />
                                </div>
                            </div>
                            <div>
                                <label className="text-[10px] font-black uppercase theme-text-muted">Color_</label>
                                <div className="flex gap-3 mt-1">
                                    <input type="color" value={data.color_hex} onChange={(e) => setData('color_hex', e.target.value)} className="w-12 h-12 rounded-xl border theme-border cursor-pointer" />
                                    <input type="text" value={data.color_hex} onChange={(e) => setData('color_hex', e.target.value)} className={`${THEME_INPUT} flex-1 text-sm font-bold`} />
                                </div>
                            </div>
                            <label className="flex items-center gap-3 cursor-pointer">
                                <input type="checkbox" checked={data.activo} onChange={(e) => setData('activo', e.target.checked)} />
                                <span className="text-sm font-bold theme-text-main">Activo</span>
                            </label>
                            <button type="submit" disabled={processing} className="w-full py-4 text-white rounded-xl font-black uppercase text-[11px] outline-none" style={{ backgroundColor: 'var(--color-primario)' }}>
                                <Save className="w-4 h-4 inline mr-2" /> Guardar
                            </button>
                        </form>
                    </div>
                </div>,
                document.body
            )}
            {modalEliminar && createPortal(
                <div className={`${THEME_MODAL_OVERLAY} z-[200]`} onClick={() => setModalEliminar(false)}>
                    <div className="w-full max-w-sm theme-surface border theme-border rounded-[2rem] p-8 text-center" onClick={(e) => e.stopPropagation()}>
                        <AlertTriangle className="w-12 h-12 text-red-500 mx-auto mb-4" />
                        <p className="text-sm theme-text-muted mb-6">¿Eliminar estatus «{itemActual?.nombre_visual}»?</p>
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
