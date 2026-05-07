import React, { useState, useEffect } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { animate } from 'animejs/animation';
import { 
    Settings, Plus, Edit2, Trash2, 
    X, Tags, ListTree, Activity, AlertTriangle
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function Catalogos({ auth, procesos = [], listas = [], estados = [] }) {
    const [tabActiva, setTabActiva] = useState('procesos');
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminarAbierto, setModalEliminarAbierto] = useState(false);
    const [itemAEditar, setItemAEditar] = useState(null);
    const [itemAEliminar, setItemAEliminar] = useState(null);
    
    // Formulario para Crear/Editar
    const { data, setData, post, put, processing, reset, errors } = useForm({
        nombre: '',
        descripcion: '',
        monto_requerido: '',
        activo: true
    });

    // Formulario para Eliminar/Revincular
    const formEliminar = useForm({
        reubicar_en_id: ''
    });

    useEffect(() => {
        animate('.tabla-contenido', {
            translateY: [15, 0],
            opacity: [0, 1],
            easing: 'easeOutExpo',
            duration: 600,
            delay: (el, i) => i * 100
        });
    }, [tabActiva]);

    const abrirModal = (item = null) => {
        setItemAEditar(item);
        if (item) {
            setData({
                nombre: item.nombre || '',
                descripcion: item.descripcion || '',
                monto_requerido: item.monto_requerido || '',
                activo: item.activo
            });
        } else {
            reset();
        }
        setModalAbierto(true);
    };

    const abrirModalEliminar = (item) => {
        setItemAEliminar(item);
        formEliminar.reset();
        setModalEliminarAbierto(true);
    };

    const guardarCatalogo = (e) => {
        e.preventDefault();
        const ruta = itemAEditar 
            ? route(`admin.catalogos.${tabActiva}.update`, itemAEditar.id) 
            : route(`admin.catalogos.${tabActiva}.store`);

        const accion = itemAEditar ? put : post;

        accion(ruta, {
            onSuccess: () => { setModalAbierto(false); reset(); setItemAEditar(null); },
            preserveScroll: true
        });
    };

    const confirmarEliminacion = (e) => {
        e.preventDefault();
        formEliminar.delete(route(`admin.catalogos.${tabActiva}.destroy`, itemAEliminar.id), {
            onSuccess: () => { setModalEliminarAbierto(false); setItemAEliminar(null); },
            preserveScroll: true
        });
    };

    const obtenerDatosActivos = () => {
        if (tabActiva === 'procesos') return procesos;
        if (tabActiva === 'listas') return listas;
        if (tabActiva === 'estados') return estados;
        return [];
    };

    const datosActivos = obtenerDatosActivos();

    return (
        <AppLayout auth={auth}>
            <Head title="Gestión de Catálogos | GELIANV" />

            {/* --- MODAL ELIMINAR Y REVINCULAR --- */}
            {modalEliminarAbierto && (
                <div className="fixed inset-0 z-50 flex items-center justify-center theme-overlay p-4">
                    <div className="theme-surface border-2 border-red-500/20 rounded-[2.5rem] p-8 shadow-2xl max-w-md w-full relative">
                        <form onSubmit={confirmarEliminacion} className="space-y-6">
                            <div className="flex items-center gap-4 text-red-500 mb-6">
                                <div className="p-3 bg-red-500/10 rounded-2xl"><AlertTriangle className="w-6 h-6" /></div>
                                <h2 className="text-xl font-black italic uppercase tracking-tighter">Eliminar Registro</h2>
                            </div>
                            
                            <p className="text-sm font-bold theme-text-main">
                                Estás a punto de eliminar: <span className="text-red-500">{itemAEliminar?.nombre}</span>
                            </p>

                            {/* Lógica exclusiva para Listas (Revinculación) */}
                            {tabActiva === 'listas' && (
                                <div className="p-4 bg-amber-500/10 border border-amber-500/20 rounded-2xl space-y-3 mt-4">
                                    <p className="text-[10px] font-black uppercase text-amber-600 tracking-widest">Protocolo de Seguridad_</p>
                                    <p className="text-xs font-bold text-amber-700/80 dark:text-amber-500">Si este registro tiene clientes asignados, selecciona a qué lista deseas moverlos antes de borrarla.</p>
                                    <select 
                                        value={formEliminar.data.reubicar_en_id}
                                        onChange={e => formEliminar.setData('reubicar_en_id', e.target.value)}
                                        className="w-full p-3 theme-element border-none rounded-xl text-xs font-bold outline-none focus:ring-2 focus:ring-amber-500 cursor-pointer"
                                        required
                                    >
                                        <option value="">-- Seleccionar Lista Destino --</option>
                                        {listas.filter(l => l.id !== itemAEliminar.id).map(lista => (
                                            <option key={lista.id} value={lista.id}>{lista.nombre}</option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            <div className="flex gap-3 pt-4">
                                <button type="button" onClick={() => setModalEliminarAbierto(false)} className="flex-1 py-4 theme-element rounded-xl font-black uppercase text-[10px] tracking-widest theme-text-muted hover:theme-text-main transition-all">Cancelar</button>
                                <button type="submit" disabled={formEliminar.processing} className="flex-1 py-4 bg-red-600 text-white rounded-xl font-black uppercase text-[10px] tracking-widest hover:bg-red-500 transition-all disabled:opacity-50">Confirmar Borrado</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* --- MODAL CREAR / EDITAR --- */}
            {modalAbierto && (
                <div className="fixed inset-0 z-[60] flex items-center justify-center theme-overlay p-4">
                    <div className="theme-surface border-2 theme-border rounded-[2.5rem] p-8 shadow-2xl max-w-md w-full relative">
                        <button onClick={() => {setModalAbierto(false); reset(); setItemAEditar(null);}} className="absolute top-6 right-6 p-2 theme-text-muted hover:text-[var(--color-primario)] transition-colors">
                            <X className="w-5 h-5" />
                        </button>
                        <form onSubmit={guardarCatalogo} className="space-y-6">
                            <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter flex items-center">
                                <Settings className="w-5 h-5 mr-3" style={{ color: 'var(--color-primario)' }} /> {itemAEditar ? 'Editar Registro' : 'Nuevo Registro'}
                            </h2>
                            <div className="space-y-4">
                                <div>
                                    <label className="text-[10px] font-black uppercase theme-text-muted italic ml-2">Nombre_</label>
                                    <input 
                                        type="text" 
                                        value={data.nombre} 
                                        onChange={e => setData('nombre', e.target.value)} 
                                        className="w-full p-4 mt-1 theme-element border theme-border rounded-xl theme-text-main font-bold outline-none focus:border-[var(--color-primario)] transition-colors" 
                                        required 
                                    />
                                </div>
                                {tabActiva === 'listas' && (
                                    <div>
                                        <label className="text-[10px] font-black uppercase theme-text-muted italic ml-2">Monto Requerido ($)_</label>
                                        <input 
                                            type="number" 
                                            step="0.01" 
                                            value={data.monto_requerido} 
                                            onChange={e => setData('monto_requerido', e.target.value)} 
                                            className="w-full p-4 mt-1 theme-element border theme-border rounded-xl theme-text-main font-bold outline-none focus:border-[var(--color-primario)] transition-colors" 
                                            required 
                                        />
                                    </div>
                                )}
                                {tabActiva !== 'listas' && (
                                    <div>
                                        <label className="text-[10px] font-black uppercase theme-text-muted italic ml-2">Descripción_</label>
                                        <textarea 
                                            value={data.descripcion} 
                                            onChange={e => setData('descripcion', e.target.value)} 
                                            className="w-full p-4 mt-1 theme-element border theme-border rounded-xl theme-text-main font-bold outline-none focus:border-[var(--color-primario)] transition-colors resize-none h-24"
                                        ></textarea>
                                    </div>
                                )}
                                <div className="flex items-center gap-3 ml-2 mt-2">
                                    <input 
                                        type="checkbox" 
                                        checked={data.activo} 
                                        onChange={e => setData('activo', e.target.checked)} 
                                        className="w-4 h-4 rounded border-zinc-300 cursor-pointer"
                                        style={{ color: 'var(--color-primario)' }}
                                    />
                                    <label className="text-[10px] font-black uppercase theme-text-main cursor-pointer" onClick={() => setData('activo', !data.activo)}>Activo en el sistema</label>
                                </div>
                            </div>
                            <button 
                                type="submit" 
                                disabled={processing} 
                                className="w-full py-4 text-white rounded-xl font-black uppercase text-[10px] tracking-widest transition-all shadow-lg hover:scale-[1.02] disabled:opacity-50"
                                style={{ backgroundColor: 'var(--color-primario)' }}
                            >
                                Guardar Cambios
                            </button>
                        </form>
                    </div>
                </div>
            )}

            {/* --- VISTA PRINCIPAL --- */}
            <div className="max-w-7xl mx-auto p-6 md:p-12 space-y-12 min-h-screen">
                <header className="flex flex-col md:flex-row justify-between md:items-end gap-6 tabla-contenido">
                    <div className="space-y-4">
                        <div className="flex items-center space-x-3">
                            <span className="h-1.5 w-12 rounded-full transition-colors duration-300" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em] transition-colors duration-300" style={{ color: 'var(--color-primario)' }}>Configuración Central</p>
                        </div>
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-tight transition-colors duration-300">
                            GESTIÓN DE <span style={{ color: 'var(--color-primario)' }}>CATÁLOGOS</span>
                        </h1>
                    </div>
                    <button 
                        onClick={() => abrirModal()} 
                        className="flex items-center gap-2 px-6 py-4 text-white rounded-2xl font-black uppercase tracking-widest text-[10px] shadow-xl hover:scale-[1.02] transition-all"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        <Plus className="w-4 h-4" /> Agregar Registro
                    </button>
                </header>

                <div className="flex flex-col md:flex-row gap-4 tabla-contenido">
                    <button 
                        onClick={() => setTabActiva('procesos')} 
                        className={`flex items-center gap-2 px-6 py-4 rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all flex-1 md:flex-none justify-center ${tabActiva === 'procesos' ? 'text-white shadow-lg' : 'theme-element theme-text-muted hover:theme-text-main border-2 theme-border'}`}
                        style={{ backgroundColor: tabActiva === 'procesos' ? 'var(--color-primario)' : '' }}
                    >
                        <Activity className="w-4 h-4" /> Procesos
                    </button>
                    <button 
                        onClick={() => setTabActiva('listas')} 
                        className={`flex items-center gap-2 px-6 py-4 rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all flex-1 md:flex-none justify-center ${tabActiva === 'listas' ? 'text-white shadow-lg' : 'theme-element theme-text-muted hover:theme-text-main border-2 theme-border'}`}
                        style={{ backgroundColor: tabActiva === 'listas' ? 'var(--color-primario)' : '' }}
                    >
                        <ListTree className="w-4 h-4" /> Listas Descuento
                    </button>
                    <button 
                        onClick={() => setTabActiva('estados')} 
                        className={`flex items-center gap-2 px-6 py-4 rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all flex-1 md:flex-none justify-center ${tabActiva === 'estados' ? 'text-white shadow-lg' : 'theme-element theme-text-muted hover:theme-text-main border-2 theme-border'}`}
                        style={{ backgroundColor: tabActiva === 'estados' ? 'var(--color-primario)' : '' }}
                    >
                        <Tags className="w-4 h-4" /> Estados Solicitud
                    </button>
                </div>

                <div className="theme-surface border-2 theme-border rounded-[3rem] overflow-hidden shadow-sm tabla-contenido transition-colors duration-300">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left">
                            <thead>
                                <tr className="border-b-2 theme-border">
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">ID_</th>
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Nombre_</th>
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Detalles_</th>
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted text-center">Estatus_</th>
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted text-right">Acciones_</th>
                                </tr>
                            </thead>
                            <tbody>
                                {datosActivos.length === 0 ? (
                                    <tr>
                                        <td colSpan="5" className="py-16 text-center">
                                            <Settings className="w-12 h-12 theme-text-muted mx-auto mb-4 opacity-50" />
                                            <h3 className="text-lg font-black italic uppercase theme-text-main">Sin registros</h3>
                                        </td>
                                    </tr>
                                ) : datosActivos.map((item) => (
                                    <tr key={item.id} className="border-b theme-border hover:bg-zinc-50 dark:hover:bg-zinc-800/50 transition-colors group/row">
                                        <td className="p-6 font-bold theme-text-muted">#{item.id}</td>
                                        <td className="p-6 font-black italic theme-text-main uppercase">{item.nombre}</td>
                                        <td className="p-6">
                                            <span className="text-xs font-bold theme-text-muted">
                                                {tabActiva === 'listas' ? `Requisito: ${new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(item.monto_requerido)}` : (item.descripcion || 'Sin descripción')}
                                            </span>
                                        </td>
                                        <td className="p-6 text-center">
                                            <span className={`inline-block px-3 py-1 rounded-md text-[9px] font-black uppercase tracking-widest ${item.activo ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20' : 'bg-red-500/10 text-red-500 border border-red-500/20'}`}>
                                                {item.activo ? 'Activo' : 'Inactivo'}
                                            </span>
                                        </td>
                                        <td className="p-6 text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                {/* AQUÍ ESTÁ LA SOLUCIÓN: Agregué theme-text-muted a los botones */}
                                                <button 
                                                    onClick={() => abrirModal(item)} 
                                                    className="p-3 theme-element border theme-border rounded-xl transition-all theme-text-muted hover:border-[var(--color-primario)] hover:text-[var(--color-primario)]"
                                                >
                                                    <Edit2 className="w-4 h-4" />
                                                </button>
                                                <button 
                                                    onClick={() => abrirModalEliminar(item)} 
                                                    className="p-3 theme-element border theme-border rounded-xl transition-all theme-text-muted hover:border-red-500 hover:text-red-500"
                                                >
                                                    <Trash2 className="w-4 h-4" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <style>{`
                .theme-surface { background-color: #ffffff; border-color: #f4f4f5; }
                .theme-element { background-color: #fafafa; border-color: #e4e4e7; }
                .theme-text-main { color: #18181b; }
                .theme-text-muted { color: #71717a; }
                .theme-border { border-color: #f4f4f5; }
                .theme-overlay { background-color: rgba(255, 255, 255, 0.4); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
                
                .dark .theme-surface { background-color: #141414; border-color: #2A2A2A; }
                .dark .theme-element { background-color: #1A1A1A; border-color: #333333; }
                .dark .theme-text-main { color: #ffffff; }
                .dark .theme-text-muted { color: #a1a1aa; }
                .dark .theme-border { border-color: #2A2A2A; }
                .dark .theme-overlay { background-color: rgba(0, 0, 0, 0.6); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
            `}</style>
        </AppLayout>
    );
}