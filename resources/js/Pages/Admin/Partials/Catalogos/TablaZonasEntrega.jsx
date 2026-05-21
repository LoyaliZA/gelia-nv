import React, { useState } from 'react';
import { useForm, router } from '@inertiajs/react';
import { Map, Edit2, Trash2, Check, X, DollarSign, Activity } from 'lucide-react';

export default function TablaZonasEntrega({ datos = [], auth }) {
    // ----------------------------------------------------------------------
    // SEGURIDAD Y PERMISOS
    // ----------------------------------------------------------------------
    const can = (permiso) => auth?.user?.permissions?.includes(permiso) || auth?.user?.roles?.includes('Super Admin');
    const puedeEditar = can('entregas.configurar_zonas');

    // ----------------------------------------------------------------------
    // ESTADO DEL COMPONENTE
    // ----------------------------------------------------------------------
    const [modalAbierto, setModalAbierto] = useState(false);
    const [zonaSeleccionada, setZonaSeleccionada] = useState(null);

    const { data, setData, put, processing, errors, reset } = useForm({
        nombre: '',
        color_hex: '#000000',
        costo_base: '',
        activo: true
    });

    // ----------------------------------------------------------------------
    // MANEJADORES DE EVENTOS
    // ----------------------------------------------------------------------
    const abrirModalEdicion = (zona) => {
        setZonaSeleccionada(zona);
        setData({
            nombre: zona.nombre,
            color_hex: zona.color_hex,
            costo_base: zona.costo_base,
            activo: zona.activo
        });
        setModalAbierto(true);
    };

    const cerrarModal = () => {
        setModalAbierto(false);
        setZonaSeleccionada(null);
        reset();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        put(`/admin/catalogos/zonas-entrega/${zonaSeleccionada.id}`, {
            preserveScroll: true,
            onSuccess: () => cerrarModal(),
        });
    };

    const handleDesactivar = (id) => {
        if (confirm('¿Estás seguro de desactivar esta zona logística? Dejará de aparecer en el mapa de entregas.')) {
            router.delete(`/admin/catalogos/zonas-entrega/${id}`, {
                preserveScroll: true
            });
        }
    };

    // ----------------------------------------------------------------------
    // RENDERIZADO
    // ----------------------------------------------------------------------
    return (
        <div className="p-6">
            <div className="flex items-center gap-3 mb-6">
                <Map className="w-5 h-5 theme-text-muted" />
                <h2 className="text-lg font-black uppercase tracking-widest theme-text-main">
                    Tarifas por Zona Logística
                </h2>
            </div>

            <div className="overflow-x-auto rounded-2xl border theme-border">
                <table className="w-full text-left border-collapse">
                    <thead>
                        <tr className="theme-surface border-b theme-border">
                            <th className="p-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">ID</th>
                            <th className="p-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Identificador de Zona</th>
                            <th className="p-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Color en Mapa</th>
                            <th className="p-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Tarifa Base (MXN)</th>
                            <th className="p-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Estado</th>
                            {puedeEditar && (
                                <th className="p-4 text-[10px] font-black uppercase tracking-widest theme-text-muted text-right">Acciones</th>
                            )}
                        </tr>
                    </thead>
                    <tbody>
                        {datos.length > 0 ? datos.map((zona) => (
                            <tr key={zona.id} className="border-b theme-border last:border-0 hover:bg-black/5 dark:hover:bg-white/5 transition-colors">
                                <td className="p-4 text-xs font-bold theme-text-muted">{zona.id}</td>
                                <td className="p-4 text-sm font-black theme-text-main">{zona.nombre}</td>
                                <td className="p-4">
                                    <div className="flex items-center gap-2">
                                        <div
                                            className="w-4 h-4 rounded-full border border-black/10 dark:border-white/10 shadow-sm"
                                            style={{ backgroundColor: zona.color_hex }}
                                        />
                                        <span className="text-xs font-bold theme-text-muted uppercase">{zona.color_hex}</span>
                                    </div>
                                </td>
                                <td className="p-4 text-sm font-black theme-text-main">
                                    ${parseFloat(zona.costo_base).toFixed(2)}
                                </td>
                                <td className="p-4">
                                    <span className={`px-3 py-1 rounded-full text-[9px] font-black uppercase tracking-widest flex items-center gap-1.5 w-max ${zona.activo ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20' : 'bg-red-500/10 text-red-500 border border-red-500/20'}`}>
                                        <Activity className="w-3 h-3" />
                                        {zona.activo ? 'Activa' : 'Inactiva'}
                                    </span>
                                </td>

                                {/* Ocultamos las acciones si el usuario no tiene permisos */}
                                {puedeEditar && (
                                    <td className="p-4 text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            <button
                                                onClick={() => abrirModalEdicion(zona)}
                                                className="p-2 rounded-xl theme-element hover:bg-[var(--color-primario)] hover:text-white transition-colors outline-none"
                                                title="Editar Costos"
                                            >
                                                <Edit2 className="w-4 h-4" />
                                            </button>
                                            <button
                                                onClick={() => handleDesactivar(zona.id)}
                                                className="p-2 rounded-xl theme-element hover:bg-red-500 hover:text-white transition-colors outline-none"
                                                title="Desactivar Zona"
                                            >
                                                <Trash2 className="w-4 h-4" />
                                            </button>
                                        </div>
                                    </td>
                                )}
                            </tr>
                        )) : (
                            <tr>
                                <td colSpan={puedeEditar ? 6 : 5} className="p-8 text-center text-sm font-bold theme-text-muted">
                                    No hay zonas logísticas registradas.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {/* MODAL DE EDICIÓN (Restringido lógicamente al renderizado) */}
            {modalAbierto && (
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/50 backdrop-blur-md">
                    <div className="w-full max-w-md theme-surface border theme-border rounded-[2rem] shadow-2xl overflow-hidden relative">

                        <div className="flex justify-between items-center p-6 border-b theme-border">
                            <h3 className="text-lg font-black theme-text-main flex items-center gap-2 uppercase tracking-tight">
                                <Map className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                                Editar Zona
                            </h3>
                            <button onClick={cerrarModal} className="p-2 rounded-full hover:bg-black/5 dark:hover:bg-white/5 transition-colors outline-none">
                                <X className="w-5 h-5 theme-text-main" />
                            </button>
                        </div>

                        <form onSubmit={handleSubmit} className="p-6 flex flex-col gap-5">
                            <div>
                                <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">
                                    Nombre Comercial de la Zona
                                </label>
                                <input
                                    type="text"
                                    value={data.nombre}
                                    onChange={e => setData('nombre', e.target.value)}
                                    className="w-full theme-element border theme-border rounded-xl p-3 text-sm font-bold theme-text-main outline-none focus:ring-2 focus:ring-[var(--color-primario)]"
                                    required
                                />
                                {errors.nombre && <p className="text-xs text-red-500 mt-1 font-bold">{errors.nombre}</p>}
                            </div>
                            {/* Selector de Color Cartográfico */}
                            <div>
                                <label className="block text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">
                                    Color en Mapa
                                </label>
                                <div className="flex items-center gap-4 theme-element border theme-border rounded-xl p-2">
                                    <input
                                        type="color"
                                        value={data.color_hex}
                                        onChange={e => setData('color_hex', e.target.value)}
                                        className="w-10 h-10 rounded cursor-pointer border-0 bg-transparent p-0"
                                    />
                                    <span className="text-sm font-bold theme-text-main uppercase">
                                        {data.color_hex}
                                    </span>
                                </div>
                                {errors.color_hex && <p className="text-xs text-red-500 mt-1 font-bold">{errors.color_hex}</p>}
                            </div>


                            <div>
                                <label className="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">
                                    <DollarSign className="w-4 h-4" /> Tarifa Base (Obligatoria)
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.costo_base}
                                    onChange={e => setData('costo_base', e.target.value)}
                                    className="w-full theme-element border theme-border rounded-xl p-3 text-sm font-bold theme-text-main outline-none focus:ring-2 focus:ring-[var(--color-primario)]"
                                    required
                                />
                                {errors.costo_base && <p className="text-xs text-red-500 mt-1 font-bold">{errors.costo_base}</p>}
                            </div>

                            <div className="flex items-center justify-between p-4 theme-element border theme-border rounded-2xl mt-2">
                                <div>
                                    <p className="text-sm font-bold theme-text-main">Habilitar Zona</p>
                                    <p className="text-xs theme-text-muted mt-0.5">Permite calcular envíos hacia esta área.</p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => setData('activo', !data.activo)}
                                    className="gelia-switch flex-shrink-0"
                                    data-active={data.activo}
                                >
                                    <div className="gelia-switch-thumb" />
                                </button>
                            </div>

                            <div className="mt-2 pt-4 border-t theme-border">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="w-full py-4 rounded-full text-white font-black uppercase tracking-widest text-[11px] transition-transform hover:scale-[1.02] active:scale-95 shadow-md flex justify-center items-center gap-2 outline-none disabled:opacity-70"
                                    style={{ backgroundColor: 'var(--color-primario)' }}
                                >
                                    <Check className="w-5 h-5" />
                                    {processing ? 'Guardando_' : 'Actualizar Tarifa'}
                                </button>
                            </div>
                        </form>

                    </div>
                </div>
            )}
        </div>
    );
}