import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { Volume2, Edit2, Trash2, Plus, X, Save, AlertTriangle, Play } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';

const thLeft = 'px-6 py-4 text-[9px] font-black uppercase tracking-widest theme-text-muted text-left';
const thRight = 'px-6 py-4 text-[9px] font-black uppercase tracking-widest theme-text-muted text-right';
const inputClass =
    'w-full px-4 py-3 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all shadow-sm hover:shadow-md';

export default function TablaTonos({ datos = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [itemActual, setItemActual] = useState(null);

    const { data, setData, post, processing, reset, errors } = useForm({
        nombre: '',
        slug: '',
        archivo: null,
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
            slug: item.slug,
            archivo: null,
            activo: item.activo,
            orden: item.orden ?? 0,
        });
        setModalAbierto(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const ruta = itemActual
            ? route('admin.personalizacion.tonos.update', itemActual.id)
            : route('admin.personalizacion.tonos.store');

        post(ruta, {
            forceFormData: true,
            onSuccess: () => { setModalAbierto(false); reset(); },
        });
    };

    const confirmDelete = () => {
        post(route('admin.personalizacion.tonos.destroy', itemActual.id), {
            _method: 'delete',
            onSuccess: () => { setModalEliminar(false); setItemActual(null); },
        });
    };

    const previewTono = (path) => {
        if (!path) return;
        const audio = new Audio(path);
        audio.play().catch(() => {});
    };

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando tono_" />

            <div className="p-8 md:p-10 border-b theme-border flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div className="flex items-center gap-3">
                    <Volume2 className="w-6 h-6 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                    <div>
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">
                            Tonos de Notificación_
                        </h2>
                        <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mt-1">{datos.length} tonos registrados</p>
                    </div>
                </div>
                <button
                    type="button"
                    onClick={abrirNuevo}
                    className="flex items-center gap-2 px-6 py-3 rounded-2xl font-black uppercase text-[11px] tracking-widest transition-all hover:scale-105 shadow-xl text-white outline-none border border-black/10"
                    style={{ backgroundColor: 'var(--color-primario)' }}
                >
                    <Plus className="w-4 h-4" /> Subir tono
                </button>
            </div>

            <div className="overflow-x-auto">
                <table className="w-full border-collapse">
                    <thead>
                        <tr className="border-b-2 border-[var(--color-primario)]/30">
                            <th className={thLeft}>Nombre / Slug_</th>
                            <th className={thLeft}>Archivo_</th>
                            <th className={thLeft}>Status_</th>
                            <th className={thRight}>Acciones_</th>
                        </tr>
                    </thead>
                    <tbody>
                        {datos.map((item) => (
                            <tr key={item.id} className="group border-b theme-border last:border-0 hover:ring-2 hover:ring-inset hover:ring-[var(--color-primario)]/30 transition-all">
                                <td className="px-6 py-5">
                                    <p className="text-sm font-black theme-text-main uppercase italic leading-tight">{item.nombre}</p>
                                    <p className="text-[9px] font-bold theme-text-muted uppercase tracking-tighter mt-0.5">{item.slug}</p>
                                </td>
                                <td className="px-6 py-5 text-xs font-bold theme-text-muted">{item.archivo}</td>
                                <td className="px-6 py-5">
                                    <span className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[9px] font-black uppercase ${item.activo ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-red-500/10 text-red-600 dark:text-red-400'}`}>
                                        {item.activo ? 'Activo' : 'Inactivo'}
                                    </span>
                                </td>
                                <td className="px-6 py-5 text-right">
                                    <div className="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            onClick={() => previewTono(item.path)}
                                            className="p-2.5 theme-element border theme-border rounded-xl transition-all outline-none shadow-sm hover:scale-105 hover:border-[var(--color-primario)] group/btn"
                                            title="Probar"
                                        >
                                            <Play className="w-4 h-4 theme-text-main group-hover/btn:text-[var(--color-primario)] transition-colors" />
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => abrirEditar(item)}
                                            className="p-2.5 theme-element border theme-border rounded-xl transition-all outline-none shadow-sm hover:scale-105 hover:border-[var(--color-primario)] group/btn"
                                        >
                                            <Edit2 className="w-4 h-4 theme-text-main group-hover/btn:text-[var(--color-primario)] transition-colors" />
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => { setItemActual(item); setModalEliminar(true); }}
                                            className="p-2.5 theme-element border theme-border rounded-xl transition-all outline-none shadow-sm hover:bg-red-500 hover:border-red-500 group/del"
                                        >
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
                    <div className="w-full max-w-lg theme-surface border theme-border shadow-2xl rounded-[2.5rem] modal-pop" onClick={(e) => e.stopPropagation()}>
                        <div className="p-6 border-b theme-border flex justify-between items-center">
                            <h2 className="text-xl font-black italic uppercase theme-text-main m-0">{itemActual ? 'Editar' : 'Nuevo'} Tono_</h2>
                            <button type="button" onClick={() => setModalAbierto(false)} className="p-2 rounded-full theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 transition-colors outline-none">
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <form onSubmit={handleSubmit} className="p-8 space-y-5">
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Nombre</label>
                                <input value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} className={inputClass} required />
                                {errors.nombre && <p className="text-xs text-red-500">{errors.nombre}</p>}
                            </div>
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Slug (opcional)</label>
                                <input value={data.slug} onChange={(e) => setData('slug', e.target.value)} className={inputClass} placeholder="campana-clasica" />
                            </div>
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">{itemActual ? 'Reemplazar archivo (opcional)' : 'Archivo de audio'}</label>
                                <input
                                    type="file"
                                    accept=".mp3,.wav,.ogg,audio/*"
                                    onChange={(e) => setData('archivo', e.target.files[0])}
                                    className="w-full text-xs theme-text-main file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-[10px] file:font-black file:uppercase file:bg-[var(--color-primario)] file:text-white file:cursor-pointer"
                                    required={!itemActual}
                                />
                                {errors.archivo && <p className="text-xs text-red-500">{errors.archivo}</p>}
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Orden</label>
                                    <input type="number" min="0" value={data.orden} onChange={(e) => setData('orden', Number(e.target.value))} className={inputClass} />
                                </div>
                                <div className="flex items-end pb-2">
                                    <button type="button" className="gelia-switch shrink-0 scale-110 shadow-sm" data-active={data.activo} onClick={() => setData('activo', !data.activo)}>
                                        <div className="gelia-switch-thumb shadow-md" />
                                    </button>
                                    <span className="ml-3 text-[10px] font-black uppercase theme-text-muted">Activo</span>
                                </div>
                            </div>
                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full py-4 rounded-2xl font-black uppercase tracking-widest text-[11px] text-white flex items-center justify-center gap-2 transition-all hover:scale-105 shadow-xl disabled:opacity-60 disabled:scale-100 outline-none border border-black/10"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                <Save className="w-4 h-4" /> Guardar
                            </button>
                        </form>
                    </div>
                </div>,
                document.body
            )}

            {modalEliminar && createPortal(
                <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xl animate-fade-in" onClick={() => setModalEliminar(false)}>
                    <div className="w-full max-w-md theme-surface border theme-border shadow-2xl rounded-[2.5rem] p-8 space-y-6 modal-pop" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center gap-4">
                            <AlertTriangle className="w-8 h-8 text-red-500 shrink-0" />
                            <div>
                                <h3 className="text-lg font-black uppercase theme-text-main m-0">Eliminar tono_</h3>
                                <p className="text-sm theme-text-muted mt-1">Se eliminará «{itemActual?.nombre}» y su archivo del servidor.</p>
                            </div>
                        </div>
                        <div className="flex gap-3">
                            <button
                                type="button"
                                onClick={() => setModalEliminar(false)}
                                className="flex-1 py-3 rounded-xl font-black uppercase text-xs theme-element border theme-border theme-text-main hover:border-[var(--color-primario)] transition-colors outline-none shadow-sm"
                            >
                                Cancelar
                            </button>
                            <button type="button" onClick={confirmDelete} className="flex-1 py-3 rounded-xl font-black uppercase text-xs text-white bg-red-500 hover:bg-red-600 transition-colors outline-none shadow-sm">
                                Eliminar
                            </button>
                        </div>
                    </div>
                </div>,
                document.body
            )}
        </div>
    );
}
