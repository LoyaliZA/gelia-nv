import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { Gift, Edit2, Trash2, Plus, X, Save, AlertTriangle } from 'lucide-react';
import GeliaLoader from '../../../../Components/GeliaLoader';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_INPUT,
    THEME_LABEL,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_BTN_ICON,
} from '../../../../utils/geliaTheme';
import { rhChipClass } from '../../rhModuleStyles';

const FORM_INICIAL = {
    nombre: '',
    codigo: '',
    activo: true,
};

export default function TablaBonos({ datos = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [itemActual, setItemActual] = useState(null);

    const { data, setData, post, put, processing, reset, errors } = useForm({ ...FORM_INICIAL });

    const abrirNuevo = () => {
        setItemActual(null);
        reset();
        setModalAbierto(true);
    };

    const abrirEditar = (item) => {
        setItemActual(item);
        setData({
            nombre: item.nombre,
            codigo: item.codigo || '',
            activo: !!item.activo,
        });
        setModalAbierto(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const accion = itemActual ? put : post;
        const ruta = itemActual
            ? route('rh.catalogos.bonos.update', itemActual.id)
            : route('rh.catalogos.bonos.store');
        accion(ruta, { onSuccess: () => { setModalAbierto(false); reset(); } });
    };

    const confirmDelete = () => {
        post(route('rh.catalogos.bonos.destroy', itemActual.id), {
            _method: 'delete',
            onSuccess: () => { setModalEliminar(false); setItemActual(null); },
        });
    };

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando bono_" />

            <div className="p-6 md:p-8 border-b theme-border flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="p-3 rounded-2xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                        <Gift className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                    </div>
                    <div>
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0">Catálogo de Bonos</h2>
                        <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mt-0.5">{datos.length} bonos configurados</p>
                    </div>
                </div>
                <button type="button" onClick={abrirNuevo} className={THEME_BTN_PRIMARY}>
                    <Plus className="w-4 h-4" /> Nuevo
                </button>
            </div>

            <div className="overflow-x-auto">
                <table className="w-full border-collapse">
                    <thead>
                        <tr className="border-b-2 border-[var(--color-primario)]/30">
                            <th className="px-4 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Nombre</th>
                            <th className="px-4 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Código</th>
                            <th className="px-4 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Colaboradores</th>
                            <th className="px-4 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Estado</th>
                            <th className="px-4 py-4 text-right text-[9px] font-black theme-text-muted uppercase tracking-widest">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {datos.map((item) => (
                            <tr key={item.id} className="border-b theme-border last:border-0">
                                <td className="px-4 py-4 text-sm font-black theme-text-main">{item.nombre}</td>
                                <td className="px-4 py-4 text-xs font-mono theme-text-muted">{item.codigo || '—'}</td>
                                <td className="px-4 py-4 text-sm font-bold">{item.colaboradores_count ?? 0}</td>
                                <td className="px-4 py-4">
                                    <span className={rhChipClass(item.activo ? 'active' : 'inactive', 'md')}>
                                        {item.activo ? 'Activo' : 'Inactivo'}
                                    </span>
                                </td>
                                <td className="px-4 py-4 text-right">
                                    <div className="flex justify-end gap-2">
                                        <button type="button" onClick={() => abrirEditar(item)} className={THEME_BTN_ICON}><Edit2 className="w-4 h-4" /></button>
                                        <button type="button" onClick={() => { setItemActual(item); setModalEliminar(true); }} className={THEME_BTN_ICON}><Trash2 className="w-4 h-4" /></button>
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
                            <h2 className="text-lg font-black uppercase italic theme-text-main m-0">{itemActual ? 'Editar' : 'Nuevo'} Bono</h2>
                            <button type="button" onClick={() => setModalAbierto(false)} className={THEME_BTN_ICON}><X className="w-5 h-5 theme-text-muted" /></button>
                        </div>
                        <form onSubmit={handleSubmit} className="p-6 space-y-4">
                            <div>
                                <label className={THEME_LABEL}>Nombre *</label>
                                <input value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} required className={THEME_INPUT} />
                                {errors.nombre && <p className="text-red-500 text-[10px] font-bold mt-1">{errors.nombre}</p>}
                            </div>
                            <div>
                                <label className={THEME_LABEL}>Código interno</label>
                                <input value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} placeholder="bono_caja" className={THEME_INPUT} />
                                {errors.codigo && <p className="text-red-500 text-[10px] font-bold mt-1">{errors.codigo}</p>}
                            </div>
                            <label className="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" checked={!!data.activo} onChange={(e) => setData('activo', e.target.checked)} />
                                <span className="text-[10px] font-black uppercase theme-text-main">Activo</span>
                            </label>
                            <button type="submit" className={`${THEME_BTN_PRIMARY} w-full`}>
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
                        <AlertTriangle className="w-8 h-8 text-red-500 mb-3" />
                        <p className="text-sm theme-text-muted mb-4">¿Eliminar «{itemActual?.nombre}»?</p>
                        <div className="flex gap-3">
                            <button type="button" onClick={() => setModalEliminar(false)} className={`${THEME_BTN_SECONDARY} flex-1`}>Cancelar</button>
                            <button type="button" onClick={confirmDelete} className="theme-btn-danger flex-1">Eliminar</button>
                        </div>
                    </div>
                </div>,
                document.body,
            )}
        </div>
    );
}
