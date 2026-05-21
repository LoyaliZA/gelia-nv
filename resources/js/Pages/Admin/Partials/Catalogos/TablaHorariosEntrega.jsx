import React, { useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import { Clock, Edit2, Trash2, Check, X, PlusCircle, MapPin, Activity } from 'lucide-react';

export default function TablaHorariosEntrega({ datos = [], zonas_entrega = [], auth }) {
    // ----------------------------------------------------------------------
    // SEGURIDAD Y PERMISOS
    // ----------------------------------------------------------------------
    const can = (permiso) => auth?.user?.permissions?.includes(permiso) || auth?.user?.roles?.includes('Super Admin');
    const puedeEditar = can('entregas.configurar_zonas');

    // ----------------------------------------------------------------------
    // ESTADO DEL COMPONENTE
    // ----------------------------------------------------------------------
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modoEdicion, setModoEdicion] = useState(false);
    const [horarioSeleccionado, setHorarioSeleccionado] = useState(null);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        zona_id: '',
        hora_inicio: '',
        hora_fin: '',
        activo: true
    });

    // ----------------------------------------------------------------------
    // MANEJADORES DE EVENTOS
    // ----------------------------------------------------------------------
    const abrirModalNuevo = () => {
        setModoEdicion(false);
        setHorarioSeleccionado(null);
        reset();
        // Preseleccionar la primera zona si existe
        if (zonas_entrega.length > 0) setData('zona_id', zonas_entrega[0].id);
        setModalAbierto(true);
    };

    const abrirModalEdicion = (horario) => {
        setModoEdicion(true);
        setHorarioSeleccionado(horario);
        // Formatear hora de H:i:s a H:i para los inputs
        const formatHora = (h) => h ? h.substring(0, 5) : '';
        
        setData({
            zona_id: horario.zona_id,
            hora_inicio: formatHora(horario.hora_inicio),
            hora_fin: formatHora(horario.hora_fin),
            activo: horario.activo
        });
        setModalAbierto(true);
    };

    const cerrarModal = () => {
        setModalAbierto(false);
        reset();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const opciones = { preserveScroll: true, onSuccess: () => cerrarModal() };
        
        if (modoEdicion) {
            put(`/admin/catalogos/horarios-entrega/${horarioSeleccionado.id}`, opciones);
        } else {
            post(`/admin/catalogos/horarios-entrega`, opciones);
        }
    };

    const handleEliminar = (id) => {
        if (confirm('¿Estás seguro de eliminar este horario?')) {
            router.delete(`/admin/catalogos/horarios-entrega/${id}`, { preserveScroll: true });
        }
    };

    // ----------------------------------------------------------------------
    // RENDERIZADO
    // ----------------------------------------------------------------------
    return (
        <div className="p-6">
            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                <div className="flex items-center gap-3">
                    <Clock className="w-5 h-5 theme-text-muted" />
                    <h2 className="text-lg font-black uppercase tracking-widest theme-text-main">
                        Ventanas de Tiempo por Zona
                    </h2>
                </div>
                {puedeEditar && (
                    <button 
                        onClick={abrirModalNuevo}
                        className="px-6 py-3 rounded-xl text-white font-black uppercase tracking-widest text-[10px] transition-transform hover:scale-105 shadow-md flex items-center justify-center gap-2 outline-none" 
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        <PlusCircle className="w-4 h-4" /> Registrar Horario
                    </button>
                )}
            </div>

            <div className="overflow-x-auto rounded-2xl border theme-border">
                <table className="w-full text-left border-collapse">
                    <thead>
                        <tr className="theme-surface border-b theme-border">
                            <th className="p-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Zona Asignada</th>
                            <th className="p-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Ventana de Tiempo</th>
                            <th className="p-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Estado</th>
                            {puedeEditar && <th className="p-4 text-[10px] font-black uppercase tracking-widest theme-text-muted text-right">Acciones</th>}
                        </tr>
                    </thead>
                    <tbody>
                        {datos.length > 0 ? datos.map((horario) => (
                            <tr key={horario.id} className="border-b theme-border last:border-0 hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                                <td className="p-4">
                                    <div className="flex items-center gap-2">
                                        <div className="w-3 h-3 rounded-full shadow-sm" style={{ backgroundColor: horario.zona?.color_hex || '#ccc' }} />
                                        <span className="text-sm font-black theme-text-main uppercase">{horario.zona?.nombre || 'Zona Eliminada'}</span>
                                    </div>
                                </td>
                                <td className="p-4 text-sm font-bold theme-text-muted">
                                    {horario.hora_inicio.substring(0,5)} - {horario.hora_fin.substring(0,5)}
                                </td>
                                <td className="p-4">
                                    <span className={`px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest flex items-center gap-1.5 w-max ${horario.activo ? 'bg-emerald-500/10 text-emerald-500' : 'bg-red-500/10 text-red-500'}`}>
                                        <Activity className="w-3 h-3" /> {horario.activo ? 'Activo' : 'Inactivo'}
                                    </span>
                                </td>
                                {puedeEditar && (
                                    <td className="p-4 text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <button onClick={() => abrirModalEdicion(horario)} className="p-2 rounded-xl theme-element hover:bg-[var(--color-primario)] hover:text-white transition-colors outline-none"><Edit2 className="w-4 h-4" /></button>
                                            <button onClick={() => handleEliminar(horario.id)} className="p-2 rounded-xl theme-element hover:bg-red-500 hover:text-white transition-colors outline-none"><Trash2 className="w-4 h-4" /></button>
                                        </div>
                                    </td>
                                )}
                            </tr>
                        )) : (
                            <tr>
                                <td colSpan={puedeEditar ? 4 : 3} className="p-8 text-center text-sm font-bold theme-text-muted">
                                    No hay rangos de horarios registrados.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* MODAL DE REGISTRO/EDICIÓN */}
            {modalAbierto && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-md">
                    <div className="w-full max-w-md theme-surface border theme-border rounded-[2rem] shadow-2xl overflow-hidden relative">
                        <div className="flex justify-between items-center p-6 border-b theme-border">
                            <h3 className="text-lg font-black theme-text-main flex items-center gap-2 uppercase tracking-tight">
                                <Clock className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                                {modoEdicion ? 'Editar Horario' : 'Nuevo Horario'}
                            </h3>
                            <button onClick={cerrarModal} className="p-2 rounded-full hover:bg-black/5 dark:hover:bg-white/5 outline-none">
                                <X className="w-5 h-5 theme-text-main" />
                            </button>
                        </div>

                        <form onSubmit={handleSubmit} className="p-6 flex flex-col gap-5">
                            <div>
                                <label className="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">
                                    <MapPin className="w-4 h-4" /> Zona Vinculada
                                </label>
                                <select 
                                    value={data.zona_id} 
                                    onChange={e => setData('zona_id', e.target.value)} 
                                    className="w-full theme-element border theme-border rounded-xl p-3 text-sm font-bold theme-text-main outline-none focus:ring-2 focus:ring-[var(--color-primario)]"
                                    required
                                >
                                    <option value="" disabled>Selecciona una zona logística...</option>
                                    {zonas_entrega.map(zona => (
                                        <option key={zona.id} value={zona.id}>{zona.nombre}</option>
                                    ))}
                                </select>
                                {errors.zona_id && <p className="text-xs text-red-500 mt-1 font-bold">{errors.zona_id}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Hora de Inicio</label>
                                    <input 
                                        type="time" 
                                        value={data.hora_inicio} 
                                        onChange={e => setData('hora_inicio', e.target.value)} 
                                        className="w-full theme-element border theme-border rounded-xl p-3 text-sm font-bold theme-text-main outline-none focus:ring-2 focus:ring-[var(--color-primario)]"
                                        required
                                    />
                                    {errors.hora_inicio && <p className="text-xs text-red-500 mt-1 font-bold">{errors.hora_inicio}</p>}
                                </div>
                                <div>
                                    <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Hora de Término</label>
                                    <input 
                                        type="time" 
                                        value={data.hora_fin} 
                                        onChange={e => setData('hora_fin', e.target.value)} 
                                        className="w-full theme-element border theme-border rounded-xl p-3 text-sm font-bold theme-text-main outline-none focus:ring-2 focus:ring-[var(--color-primario)]"
                                        required
                                    />
                                    {errors.hora_fin && <p className="text-xs text-red-500 mt-1 font-bold">{errors.hora_fin}</p>}
                                </div>
                            </div>

                            <div className="flex items-center justify-between p-4 theme-element border theme-border rounded-2xl mt-2">
                                <div>
                                    <p className="text-sm font-bold theme-text-main">Habilitar Horario</p>
                                    <p className="text-xs theme-text-muted mt-0.5">Estará disponible para ventas.</p>
                                </div>
                                <button type="button" onClick={() => setData('activo', !data.activo)} className="gelia-switch flex-shrink-0" data-active={data.activo}>
                                    <div className="gelia-switch-thumb" />
                                </button>
                            </div>

                            <div className="mt-2 pt-4 border-t theme-border">
                                <button type="submit" disabled={processing} className="w-full py-4 rounded-full text-white font-black uppercase tracking-widest text-[11px] shadow-md flex justify-center items-center gap-2 outline-none disabled:opacity-70" style={{ backgroundColor: 'var(--color-primario)' }}>
                                    <Check className="w-5 h-5" /> {processing ? 'Guardando_' : 'Confirmar Horario'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}