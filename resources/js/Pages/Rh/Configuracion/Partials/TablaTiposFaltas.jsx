import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { ClipboardList, Edit2, Trash2, Plus, X, Save, AlertTriangle, Info } from 'lucide-react';
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
    factor_penalizacion_puntualidad: '0',
    factor_penalizacion_productividad: '0',
    aplica_deduccion_salario_base: false,
    activo: true,
};

export default function TablaTiposFaltas({ datos = [] }) {
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
            factor_penalizacion_puntualidad: String(item.factor_penalizacion_puntualidad ?? 0),
            factor_penalizacion_productividad: String(item.factor_penalizacion_productividad ?? 0),
            aplica_deduccion_salario_base: !!item.aplica_deduccion_salario_base,
            activo: !!item.activo,
        });
        setModalAbierto(true);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const accion = itemActual ? put : post;
        const ruta = itemActual
            ? route('rh.catalogos.tipos_faltas.update', itemActual.id)
            : route('rh.catalogos.tipos_faltas.store');

        accion(ruta, { onSuccess: () => { setModalAbierto(false); reset(); } });
    };

    const confirmDelete = () => {
        post(route('rh.catalogos.tipos_faltas.destroy', itemActual.id), {
            _method: 'delete',
            onSuccess: () => { setModalEliminar(false); setItemActual(null); },
        });
    };

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando tipo de falta_" />

            <div className="p-6 md:p-8 border-b theme-border flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="p-3 rounded-2xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                        <ClipboardList className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                    </div>
                    <div>
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0">Tipos de Faltas y Retardos</h2>
                        <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mt-0.5">{datos.length} reglas configuradas</p>
                    </div>
                </div>
                <button
                    type="button"
                    onClick={abrirNuevo}
                    className={THEME_BTN_PRIMARY}
                >
                    <Plus className="w-4 h-4" /> Nuevo
                </button>
            </div>

            <div className="overflow-x-auto">
                <table className="w-full border-collapse">
                    <thead>
                        <tr className="border-b-2 border-[var(--color-primario)]/30">
                            <th className="px-4 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Incidencia</th>
                            <th className="px-4 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Factor punt.</th>
                            <th className="px-4 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Factor prod.</th>
                            <th className="px-4 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Deduce salario</th>
                            <th className="px-4 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Incidencias</th>
                            <th className="px-4 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Estado</th>
                            <th className="px-4 py-4 text-right text-[9px] font-black uppercase tracking-widest theme-text-muted">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        {datos.length === 0 ? (
                            <tr>
                                <td colSpan={7} className="px-6 py-12 text-center theme-text-muted text-sm italic">
                                    No hay tipos de falta configurados.
                                </td>
                            </tr>
                        ) : (
                            datos.map((item) => (
                                <tr key={item.id} className="border-b theme-border last:border-0 hover:bg-black/[0.02] dark:hover:bg-white/[0.02]">
                                    <td className="px-4 py-4">
                                        <p className="text-sm font-black theme-text-main m-0">{item.nombre}</p>
                                    </td>
                                    <td className="px-4 py-4 text-sm font-bold">×{Number(item.factor_penalizacion_puntualidad).toFixed(2)}</td>
                                    <td className="px-4 py-4 text-sm font-bold">×{Number(item.factor_penalizacion_productividad).toFixed(2)}</td>
                                    <td className="px-4 py-4">
                                        <span className={rhChipClass(item.aplica_deduccion_salario_base ? 'negative' : 'positive', 'sm')}>
                                            {item.aplica_deduccion_salario_base ? 'Sí' : 'No'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-4 text-sm font-bold">{item.incidencias_count ?? 0}</td>
                                    <td className="px-4 py-4">
                                        <span className={rhChipClass(item.activo ? 'active' : 'inactive')}>
                                            {item.activo ? 'Activo' : 'Inactivo'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-4 text-right">
                                        <div className="flex justify-end gap-2">
                                            <button type="button" onClick={() => abrirEditar(item)} className={THEME_BTN_ICON}>
                                                <Edit2 className="w-4 h-4" />
                                            </button>
                                            <button type="button" onClick={() => { setItemActual(item); setModalEliminar(true); }} className={THEME_BTN_ICON}>
                                                <Trash2 className="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {modalAbierto && createPortal(
                <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6 overflow-y-auto`} onClick={() => setModalAbierto(false)}>
                    <div className={`${THEME_MODAL_SHELL} max-w-lg modal-pop`} onClick={(e) => e.stopPropagation()}>
                        <div className="p-6 border-b theme-border flex justify-between items-center">
                            <h2 className="text-lg font-black uppercase italic theme-text-main m-0">
                                {itemActual ? 'Editar' : 'Nuevo'} Tipo de Falta
                            </h2>
                            <button type="button" onClick={() => setModalAbierto(false)} className={THEME_BTN_ICON}><X className="w-5 h-5" /></button>
                        </div>
                        <form onSubmit={handleSubmit} className="p-6 space-y-4">
                            <div>
                                <label className={THEME_LABEL}>Nombre de la incidencia *</label>
                                <input value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} required className={THEME_INPUT} placeholder="Ej. Falta Injustificada" />
                                {errors.nombre && <p className="text-red-500 text-[10px] font-bold mt-1">{errors.nombre}</p>}
                            </div>

                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label className={THEME_LABEL}>Factor penalización puntualidad *</label>
                                    <input type="number" min="0" max="999.99" step="0.01" value={data.factor_penalizacion_puntualidad} onChange={(e) => setData('factor_penalizacion_puntualidad', e.target.value)} required className={THEME_INPUT} />
                                    {errors.factor_penalizacion_puntualidad && <p className="text-red-500 text-[10px] font-bold mt-1">{errors.factor_penalizacion_puntualidad}</p>}
                                </div>
                                <div>
                                    <label className={THEME_LABEL}>Factor penalización productividad *</label>
                                    <input type="number" min="0" max="999.99" step="0.01" value={data.factor_penalizacion_productividad} onChange={(e) => setData('factor_penalizacion_productividad', e.target.value)} required className={THEME_INPUT} />
                                    {errors.factor_penalizacion_productividad && <p className="text-red-500 text-[10px] font-bold mt-1">{errors.factor_penalizacion_productividad}</p>}
                                </div>
                            </div>

                            <div className="p-3 rounded-xl border theme-border bg-black/[0.02] dark:bg-white/[0.02] space-y-1">
                                <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted flex items-center gap-1.5 m-0">
                                    <Info className="w-3 h-3" /> Reglas de negocio
                                </p>
                                <p className="text-[10px] theme-text-muted m-0">Factor 15 en puntualidad = pierde 15× el bono diario de puntualidad.</p>
                                <p className="text-[10px] theme-text-muted m-0">Factor 0 = la incidencia no afecta ese bono.</p>
                                <p className="text-[10px] theme-text-muted m-0">Deduce salario base = resta 1 día de salario diario en nómina.</p>
                            </div>

                            <label className="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" checked={!!data.aplica_deduccion_salario_base} onChange={(e) => setData('aplica_deduccion_salario_base', e.target.checked)} />
                                <span className="text-[10px] font-black uppercase theme-text-main">Aplica deducción de salario base</span>
                            </label>

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
                        <div className="flex items-center gap-3 mb-4">
                            <AlertTriangle className="w-6 h-6 text-red-500" />
                            <h3 className="text-lg font-black uppercase italic theme-text-main m-0">Eliminar tipo de falta</h3>
                        </div>
                        <p className="text-sm theme-text-muted">¿Eliminar <strong>{itemActual?.nombre}</strong>?</p>
                        <div className="flex gap-3 mt-6">
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
