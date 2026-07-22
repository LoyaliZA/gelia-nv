import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, router } from '@inertiajs/react';
import { Clock, Edit2, Trash2, Plus, Save, AlertTriangle } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '@/utils/geliaTheme';

const formatHora = (h) => (h ? String(h).substring(0, 5) : '');

export default function TablaHorariosTraspaso({ datos = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [itemActual, setItemActual] = useState(null);
    const { data, setData, post, put, processing, reset, errors } = useForm({
        nombre: '',
        hora_inicio: '00:00',
        hora_fin: '12:00',
        dias_para_entrega: 0,
        descripcion: '',
        activo: true,
        orden: 0,
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
            hora_inicio: formatHora(item.hora_inicio),
            hora_fin: formatHora(item.hora_fin),
            dias_para_entrega: item.dias_para_entrega ?? 0,
            descripcion: item.descripcion || '',
            activo: item.activo ?? true,
            orden: item.orden ?? 0,
        });
        setModalAbierto(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const accion = itemActual ? put : post;
        const ruta = itemActual
            ? route('admin.catalogos.horarios_traspaso.update', itemActual.id)
            : route('admin.catalogos.horarios_traspaso.store');
        accion(ruta, { onSuccess: () => { setModalAbierto(false); reset(); } });
    };

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando horario_" />
            <div className="p-6 border-b theme-border flex justify-between flex-wrap gap-4">
                <div>
                    <h2 className="text-xl font-black italic uppercase m-0 flex items-center gap-2 theme-text-main">
                        <Clock className="w-5 h-5" /> Horarios Traspaso_
                    </h2>
                    <p className="text-[10px] theme-text-muted mt-1 m-0">Informativo: estima cuándo podría entregarse el traspaso según la hora de solicitud.</p>
                </div>
                <button type="button" onClick={abrirNuevo} className="px-6 py-3 rounded-2xl text-white font-black uppercase text-xs" style={{ backgroundColor: 'var(--color-primario)' }}>
                    <Plus className="w-4 h-4 inline" /> Nuevo
                </button>
            </div>
            <table className="w-full">
                <thead>
                    <tr className="border-b theme-border text-[9px] font-black uppercase theme-text-muted">
                        <th className="px-6 py-3 text-left">Nombre</th>
                        <th className="px-6 py-3 text-left">Ventana</th>
                        <th className="px-6 py-3 text-left">Días entrega</th>
                        <th className="px-6 py-3 text-left">Estado</th>
                        <th className="px-6 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    {datos.map((item) => (
                        <tr key={item.id} className="border-b theme-border">
                            <td className="px-6 py-4">
                                <p className="font-black theme-text-main m-0">{item.nombre}</p>
                                <p className="text-[9px] theme-text-muted m-0">{item.descripcion || '—'}</p>
                            </td>
                            <td className="px-6 py-4 text-sm font-bold theme-text-main font-mono">
                                {formatHora(item.hora_inicio)} – {formatHora(item.hora_fin)}
                            </td>
                            <td className="px-6 py-4 text-sm font-bold theme-text-main">
                                {item.dias_para_entrega === 0 ? 'Mismo día' : `+${item.dias_para_entrega} día(s)`}
                            </td>
                            <td className="px-6 py-4">
                                <span className={`inline-flex px-2 py-1 rounded-lg text-[9px] font-black uppercase ${item.activo ? 'bg-emerald-500/10 text-emerald-600' : 'theme-text-muted'}`}>
                                    {item.activo ? 'Activo' : 'Inactivo'}
                                </span>
                            </td>
                            <td className="px-6 py-4 text-right">
                                <button type="button" onClick={() => abrirEditar(item)} className="p-2 theme-element border theme-border rounded-xl mr-2"><Edit2 className="w-4 h-4" /></button>
                                <button type="button" onClick={() => { setItemActual(item); setModalEliminar(true); }} className="p-2 theme-element border theme-border rounded-xl"><Trash2 className="w-4 h-4" /></button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            {modalAbierto && createPortal(
                <div className={THEME_MODAL_OVERLAY} onClick={() => setModalAbierto(false)}>
                    <div className={`${THEME_MODAL_SHELL} max-w-md p-8 max-h-[90vh] overflow-y-auto modal-pop`} onClick={(e) => e.stopPropagation()}>
                        <h3 className="text-xl font-black italic uppercase theme-text-main mb-6">{itemActual ? 'Editar' : 'Nuevo'} Horario</h3>
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <input required value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} placeholder="Nombre *" className="theme-input w-full px-4 py-3 font-bold" />
                            <div className="grid grid-cols-2 gap-3">
                                <label className="text-[9px] font-black uppercase theme-text-muted">Inicio
                                    <input type="time" required value={data.hora_inicio} onChange={(e) => setData('hora_inicio', e.target.value)} className="theme-input w-full px-4 py-3 font-bold mt-1" />
                                </label>
                                <label className="text-[9px] font-black uppercase theme-text-muted">Fin
                                    <input type="time" required value={data.hora_fin} onChange={(e) => setData('hora_fin', e.target.value)} className="theme-input w-full px-4 py-3 font-bold mt-1" />
                                </label>
                            </div>
                            <label className="text-[9px] font-black uppercase theme-text-muted">Días para entrega
                                <input type="number" min="0" max="30" value={data.dias_para_entrega} onChange={(e) => setData('dias_para_entrega', Number(e.target.value))} className="theme-input w-full px-4 py-3 font-bold mt-1" />
                            </label>
                            <input value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} placeholder="Descripción" className="theme-input w-full px-4 py-3 font-bold" />
                            <input type="number" min="0" value={data.orden} onChange={(e) => setData('orden', Number(e.target.value))} placeholder="Orden" className="theme-input w-full px-4 py-3 font-bold" />
                            <label className="flex gap-2 items-center"><input type="checkbox" checked={data.activo} onChange={(e) => setData('activo', e.target.checked)} /><span className="font-bold text-sm theme-text-main">Activo</span></label>
                            {errors.nombre && <p className="text-xs text-red-500">{errors.nombre}</p>}
                            <button type="submit" className="w-full py-3 text-white rounded-xl font-black uppercase" style={{ backgroundColor: 'var(--color-primario)' }}><Save className="w-4 h-4 inline" /> Guardar</button>
                        </form>
                    </div>
                </div>,
                document.body
            )}

            {modalEliminar && createPortal(
                <div className={THEME_MODAL_OVERLAY} onClick={() => setModalEliminar(false)}>
                    <div className={`${THEME_MODAL_SHELL} p-8 text-center modal-pop`} onClick={(e) => e.stopPropagation()}>
                        <AlertTriangle className="w-10 h-10 text-red-500 mx-auto mb-4" />
                        <p className="theme-text-main mb-4">¿Eliminar «{itemActual?.nombre}»?</p>
                        <button type="button" onClick={() => router.delete(route('admin.catalogos.horarios_traspaso.destroy', itemActual.id), { onSuccess: () => setModalEliminar(false) })} className="px-6 py-3 bg-red-600 text-white rounded-xl font-black uppercase text-[10px]">Eliminar</button>
                    </div>
                </div>,
                document.body
            )}
        </div>
    );
}
