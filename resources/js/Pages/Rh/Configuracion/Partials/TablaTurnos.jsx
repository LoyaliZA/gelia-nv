import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import { Clock, Edit2, Trash2, Plus, X, Save, AlertTriangle } from 'lucide-react';
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
import { DIAS_TURNO, matrizHorarioDefecto, normalizarMatrizHorario } from '../../../../utils/matrizHorarioTurno';

export default function TablaTurnos({ datos = [] }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminar, setModalEliminar] = useState(false);
    const [itemActual, setItemActual] = useState(null);

    const matrizDefecto = matrizHorarioDefecto();

    const { data, setData, post, put, processing, reset, errors } = useForm({
        nombre: '',
        activo: true,
        matriz_horario: matrizDefecto,
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
            activo: item.activo,
            matriz_horario: normalizarMatrizHorario(item.matriz_horario),
        });
        setModalAbierto(true);
    };

    const actualizarDia = (dia, campo, valor) => {
        setData('matriz_horario', {
            ...data.matriz_horario,
            [dia]: {
                ...data.matriz_horario[dia],
                [campo]: valor,
            },
        });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const accion = itemActual ? put : post;
        const ruta = itemActual
            ? route('rh.catalogos.turnos.update', itemActual.id)
            : route('rh.catalogos.turnos.store');

        accion(ruta, { onSuccess: () => { setModalAbierto(false); reset(); } });
    };

    const confirmDelete = () => {
        post(route('rh.catalogos.turnos.destroy', itemActual.id), {
            _method: 'delete',
            onSuccess: () => { setModalEliminar(false); setItemActual(null); },
        });
    };

    return (
        <div>
            <GeliaLoader isVisible={processing} message="Guardando turno_" />

            <div className="p-6 md:p-8 border-b theme-border flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <div className="p-3 rounded-2xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                        <Clock className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                    </div>
                    <div>
                        <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0">Catálogo de Turnos</h2>
                        <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mt-0.5">{datos.length} registros</p>
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
                            <th className="px-6 py-4 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">Nombre</th>
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
                                    <span className={rhChipClass(item.activo ? 'active' : 'inactive')}>
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
                    <div className={`${THEME_MODAL_SHELL} max-w-2xl modal-pop`} onClick={(e) => e.stopPropagation()}>
                        <div className="p-6 border-b theme-border flex justify-between items-center">
                            <h2 className="text-lg font-black uppercase italic theme-text-main m-0">{itemActual ? 'Editar' : 'Nuevo'} Turno</h2>
                            <button type="button" onClick={() => setModalAbierto(false)} className={THEME_BTN_ICON}><X className="w-5 h-5" /></button>
                        </div>
                        <form onSubmit={handleSubmit} className="p-6 space-y-4">
                            <div>
                                <label className={THEME_LABEL}>Nombre del Turno *</label>
                                <input value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} required className={THEME_INPUT} placeholder="Ej. Estándar, Matutino, etc." />
                                {errors.nombre && <p className="text-red-500 text-[10px] font-bold mt-1">{errors.nombre}</p>}
                            </div>

                            <div>
                                <label className="flex items-center gap-2 cursor-pointer mb-4">
                                    <input type="checkbox" checked={!!data.activo} onChange={(e) => setData('activo', e.target.checked)} />
                                    <span className="text-[10px] font-black uppercase theme-text-main">Activo</span>
                                </label>
                            </div>

                            <div className="border theme-border rounded-2xl overflow-hidden">
                                <div className="bg-black/[0.02] dark:bg-white/[0.02] px-4 py-2 border-b theme-border">
                                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-main m-0">Matriz de Horarios (Asistencia)</p>
                                </div>
                                <div className="p-4 space-y-3">
                                    <div className="grid grid-cols-4 gap-2 text-[9px] font-black uppercase tracking-widest theme-text-muted mb-2">
                                        <div>Día</div>
                                        <div className="text-center">Descanso</div>
                                        <div>Entrada</div>
                                        <div>Salida</div>
                                    </div>
                                    {DIAS_TURNO.map((dia) => {
                                        const conf = data.matriz_horario[dia.clave] || { entrada: '', salida: '', descanso: false };
                                        return (
                                            <div key={dia.clave} className={`grid grid-cols-4 gap-2 items-center p-2 rounded-xl border ${conf.descanso ? 'border-transparent opacity-50 bg-black/[0.02] dark:bg-white/[0.02]' : 'theme-border'}`}>
                                                <div className="text-xs font-bold theme-text-main">{dia.nombre}</div>
                                                <div className="flex justify-center">
                                                    <input 
                                                        type="checkbox" 
                                                        checked={!!conf.descanso} 
                                                        onChange={(e) => actualizarDia(dia.clave, 'descanso', e.target.checked)} 
                                                    />
                                                </div>
                                                <div>
                                                    <input 
                                                        type="time" 
                                                        disabled={conf.descanso}
                                                        value={conf.entrada} 
                                                        onChange={(e) => actualizarDia(dia.clave, 'entrada', e.target.value)} 
                                                        className={THEME_INPUT + ' px-2 py-1 text-xs w-full'} 
                                                    />
                                                </div>
                                                <div>
                                                    <input 
                                                        type="time" 
                                                        disabled={conf.descanso}
                                                        value={conf.salida} 
                                                        onChange={(e) => actualizarDia(dia.clave, 'salida', e.target.value)} 
                                                        className={THEME_INPUT + ' px-2 py-1 text-xs w-full'} 
                                                    />
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>

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
                            <h3 className="text-lg font-black uppercase italic theme-text-main m-0">Eliminar turno</h3>
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
