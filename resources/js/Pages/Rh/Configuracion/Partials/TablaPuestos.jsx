import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { Briefcase, Edit2, Trash2, Plus, X, Save, AlertTriangle } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../../utils/geliaTheme';

export default function TablaPuestos({ datos = [], bonosCatalogo = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [itemActual, setItemActual] = useState(null);

    const { data, setData, post, put, processing, reset, errors, transform } = useForm({
        nombre: '',
        activo: true,
        bono_ids: [],
    });

    transform((formData) => ({
        ...formData,
        bono_ids: (formData.bono_ids || []).map(Number),
    }));

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
            bono_ids: (item.bonos || []).map((b) => String(b.id)),
        });
        setModalAbierto(true);
    };

    const toggleBono = (bonoId) => {
        const sid = String(bonoId);
        setData('bono_ids', data.bono_ids.includes(sid)
            ? data.bono_ids.filter((id) => id !== sid)
            : [...data.bono_ids, sid]);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const accion = itemActual ? put : post;
        const ruta = itemActual
            ? route('rh.catalogos.puestos.update', itemActual.id)
            : route('rh.catalogos.puestos.store');

        accion(ruta, { onSuccess: () => { setModalAbierto(false); reset(); } });
    };

    const confirmDelete = () => {
        post(route('rh.catalogos.puestos.destroy', itemActual.id), {
            _method: 'delete',
            onSuccess: () => { setModalEliminar(false); setItemActual(null); },
        });
    };

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando puesto_" />

            <div className="p-6 md:p-8 border-b theme-border flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="p-3 rounded-2xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                        <Briefcase className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                    </div>
                    <div>
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0">Catálogo de Puestos</h2>
                        <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mt-0.5">{datos.length} registros</p>
                    </div>
                </div>
                <button
                    type="button"
                    onClick={abrirNuevo}
                    className="flex items-center gap-2 px-6 py-3 rounded-2xl font-black uppercase text-xs text-white"
                    style={{ backgroundColor: 'var(--color-primario)' }}
                >
                    <Plus className="w-4 h-4" /> Nuevo
                </button>
            </div>

            <div className="overflow-x-auto">
                <table className="w-full border-collapse">
                    <thead>
                        <tr className="border-b-2 border-[var(--color-primario)]/30">
                            <th className="px-6 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Nombre</th>
                            <th className="px-6 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Bonos elegibles</th>
                            <th className="px-6 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Estado</th>
                            <th className="px-6 py-4 text-right text-[9px] font-black uppercase tracking-widest theme-text-muted">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {datos.map((item) => (
                            <tr key={item.id} className="border-b theme-border last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/[0.02]">
                                <td className="px-6 py-5">
                                    <p className="text-sm font-black theme-text-main uppercase italic m-0">{item.nombre}</p>
                                </td>
                                <td className="px-6 py-5">
                                    <p className="text-[10px] theme-text-muted m-0">
                                        {(item.bonos || []).length > 0
                                            ? item.bonos.map((b) => b.nombre).join(', ')
                                            : 'Ninguno'}
                                    </p>
                                </td>
                                <td className="px-6 py-5">
                                    <span className={`inline-flex px-3 py-1.5 rounded-full text-[9px] font-black uppercase ${item.activo ? 'bg-emerald-500/10 text-emerald-500' : 'bg-red-500/10 text-red-500'}`}>
                                        {item.activo ? 'Activo' : 'Inactivo'}
                                    </span>
                                </td>
                                <td className="px-6 py-5 text-right">
                                    <div className="flex justify-end gap-2">
                                        <button type="button" onClick={() => abrirEditar(item)} className="p-2.5 theme-element border theme-border rounded-xl">
                                            <Edit2 className="w-4 h-4 theme-text-main" />
                                        </button>
                                        <button type="button" onClick={() => { setItemActual(item); setModalEliminar(true); }} className="p-2.5 theme-element border theme-border rounded-xl hover:bg-red-500 hover:border-red-500 group">
                                            <Trash2 className="w-4 h-4 theme-text-main group-hover:text-white" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {modalAbierto && createPortal(
                <div className={`${THEME_MODAL_OVERLAY} items-center p-4`} onClick={() => setModalAbierto(false)}>
                    <div className={`${THEME_MODAL_SHELL} max-w-lg modal-pop`} onClick={(e) => e.stopPropagation()}>
                        <div className="p-6 border-b theme-border flex justify-between items-center">
                            <h2 className="text-lg font-black uppercase italic theme-text-main m-0">{itemActual ? 'Editar' : 'Nuevo'} Puesto</h2>
                            <button type="button" onClick={() => setModalAbierto(false)}><X className="w-5 h-5 theme-text-muted" /></button>
                        </div>
                        <form onSubmit={handleSubmit} className="p-6 space-y-4">
                            <div>
                                <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Nombre *</label>
                                <input value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} required className="w-full mt-1 px-4 py-3 rounded-2xl theme-element theme-border border text-[11px] font-bold" />
                                {errors.nombre && <p className="text-red-500 text-[10px] font-bold mt-1">{errors.nombre}</p>}
                            </div>
                            <label className="flex items-center gap-2">
                                <input type="checkbox" checked={!!data.activo} onChange={(e) => setData('activo', e.target.checked)} />
                                <span className="text-[10px] font-black uppercase">Activo</span>
                            </label>
                            {bonosCatalogo.length > 0 && (
                                <div>
                                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-2">Bonos elegibles para este puesto</p>
                                    <div className="space-y-2 max-h-40 overflow-y-auto p-3 rounded-2xl border theme-border">
                                        {bonosCatalogo.filter((b) => b.activo).map((b) => (
                                            <label key={b.id} className="flex items-center gap-2 text-xs cursor-pointer">
                                                <input type="checkbox" checked={data.bono_ids.includes(String(b.id))} onChange={() => toggleBono(b.id)} />
                                                {b.nombre}
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            )}
                            <button type="submit" className="w-full py-3 rounded-2xl text-[10px] font-black uppercase text-white flex items-center justify-center gap-2" style={{ backgroundColor: 'var(--color-primario)' }}>
                                <Save className="w-4 h-4" /> Guardar
                            </button>
                        </form>
                    </div>
                </div>,
                document.body,
            )}

            {modalEliminar && createPortal(
                <div className={`${THEME_MODAL_OVERLAY} items-center p-4`} onClick={() => setModalEliminar(false)}>
                    <div className={`${THEME_MODAL_SHELL} max-w-md modal-pop p-6`} onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center gap-3 mb-4">
                            <AlertTriangle className="w-6 h-6 text-red-500" />
                            <h3 className="text-lg font-black uppercase italic theme-text-main m-0">Eliminar puesto</h3>
                        </div>
                        <p className="text-sm theme-text-muted">¿Eliminar <strong>{itemActual?.nombre}</strong>?</p>
                        <div className="flex gap-3 mt-6">
                            <button type="button" onClick={() => setModalEliminar(false)} className="flex-1 py-3 rounded-2xl theme-element theme-border border text-[10px] font-black uppercase">Cancelar</button>
                            <button type="button" onClick={confirmDelete} className="flex-1 py-3 rounded-2xl bg-red-500 text-white text-[10px] font-black uppercase">Eliminar</button>
                        </div>
                    </div>
                </div>,
                document.body,
            )}
        </div>
    );
}
