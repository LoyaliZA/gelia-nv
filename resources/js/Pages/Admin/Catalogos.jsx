import React, { useState, useEffect } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { animate } from 'animejs/animation';
import { 
    Settings, Plus, Edit2, Trash2, 
    X, Tags, ListTree, Activity, AlertTriangle, Save, CheckCircle2
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function Catalogos({ auth, procesos = [], listas = [], estados = [] }) {
    const [tabActiva, setTabActiva] = useState('procesos');
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalEliminarAbierto, setModalEliminarAbierto] = useState(false);
    const [itemAEditar, setItemAEditar] = useState(null);
    const [itemAEliminar, setItemAEliminar] = useState(null);
    
    const { data, setData, post, put, processing, reset, errors } = useForm({
        nombre: '',
        descripcion: '',
        monto_requerido: '',
        activo: true
    });

    const formEliminar = useForm({
        reubicar_en_id: ''
    });

    useEffect(() => {
        animate('.tabla-contenido', {
            translateY: [15, 0],
            opacity: [0, 1],
            easing: 'easeOutExpo',
            duration: 600,
            delay: (el, i) => i * 30
        });
    }, [tabActiva]);

    // --- LOGICA DE TABS ---
    const getItemsActuales = () => {
        if (tabActiva === 'procesos') return procesos;
        if (tabActiva === 'listas') return listas;
        return estados;
    };

    const getTituloTab = () => {
        if (tabActiva === 'procesos') return 'Procesos de Venta';
        if (tabActiva === 'listas') return 'Listas de Precios';
        return 'Estados de Solicitud';
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Configuración de Catálogos" />

            <div className="max-w-7xl mx-auto p-4 md:p-8 space-y-8 min-h-screen">
                
                {/* HEADER TIPO CARD */}
                <header className="theme-surface border-2 theme-border rounded-[2rem] md:rounded-[3rem] p-6 md:p-8 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                    <div className="space-y-4">
                        <div className="flex items-center space-x-3">
                            <span className="h-1.5 w-12 rounded-full transition-colors duration-300" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em] transition-colors duration-300" style={{ color: 'var(--color-primario)' }}>
                                Estructura de Datos
                            </p>
                        </div>
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-none m-0 p-0">
                            GESTIÓN DE <span className="transition-colors duration-300" style={{ color: 'var(--color-primario)' }}>CATÁLOGOS</span>
                        </h1>
                    </div>

                    <button 
                        onClick={() => { setItemAEditar(null); reset(); setModalAbierto(true); }}
                        className="flex items-center gap-3 px-6 py-4 rounded-2xl font-black uppercase text-xs italic tracking-widest transition-all hover:scale-[1.02] active:scale-[0.98] shadow-lg text-white dark:text-black" 
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        <Plus className="w-5 h-5" />
                        Registrar {tabActiva.slice(0, -1)}
                    </button>
                </header>

                {/* SELECTOR DE TABS TIPO CARD */}
                <div className="theme-surface border-2 theme-border p-2 rounded-[2rem] shadow-sm flex flex-wrap gap-2">
                    {[
                        { id: 'procesos', label: 'Procesos', icon: ListTree },
                        { id: 'listas', label: 'Listas de Precios', icon: Tags },
                        { id: 'estados', label: 'Estados', icon: Activity }
                    ].map((tab) => (
                        <button
                            key={tab.id}
                            onClick={() => setTabActiva(tab.id)}
                            className={`flex-1 flex items-center justify-center gap-3 py-4 rounded-[1.5rem] text-[10px] font-black uppercase tracking-widest transition-all ${tabActiva === tab.id ? 'text-white dark:text-black shadow-lg' : 'theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5'}`}
                            style={tabActiva === tab.id ? { backgroundColor: 'var(--color-primario)' } : {}}
                        >
                            <tab.icon className="w-4 h-4" />
                            {tab.label}
                        </button>
                    ))}
                </div>

                {/* CONTENEDOR DE TABLA */}
                <div className="theme-surface border-2 theme-border rounded-[2.5rem] shadow-sm overflow-hidden">
                    <div className="p-6 border-b theme-border flex items-center gap-3">
                        <div className="p-2 rounded-lg theme-element">
                            <Settings className="w-4 h-4 theme-text-main" />
                        </div>
                        <h2 className="text-xs font-black uppercase tracking-[0.2em] theme-text-main">{getTituloTab()}</h2>
                    </div>

                    <div className="overflow-x-auto tabla-contenido">
                        <table className="w-full border-collapse">
                            <thead>
                                <tr className="theme-element border-b theme-border">
                                    <th className="px-6 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Nombre / Identificador_</th>
                                    <th className="px-6 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Descripción_</th>
                                    {tabActiva === 'procesos' && <th className="px-6 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Monto Req._</th>}
                                    <th className="px-6 py-4 text-left text-[9px] font-black theme-text-muted uppercase tracking-widest">Status_</th>
                                    <th className="px-6 py-4 text-right text-[9px] font-black theme-text-muted uppercase tracking-widest">Acciones_</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y theme-border">
                                {getItemsActuales().map((item) => (
                                    <tr key={item.id} className="hover:bg-black/5 dark:hover:bg-white/5 transition-colors group">
                                        <td className="px-6 py-5">
                                            <p className="text-sm font-black theme-text-main uppercase italic">{item.nombre}</p>
                                            <p className="text-[9px] font-bold theme-text-muted uppercase tracking-tighter">ID: #{item.id}</p>
                                        </td>
                                        <td className="px-6 py-5">
                                            <p className="text-xs font-bold theme-text-muted max-w-xs truncate">{item.descripcion || 'Sin descripción'}</p>
                                        </td>
                                        {tabActiva === 'procesos' && (
                                            <td className="px-6 py-5">
                                                <span className="text-xs font-black theme-text-main">
                                                    ${Number(item.monto_requerido).toLocaleString()}
                                                </span>
                                            </td>
                                        )}
                                        <td className="px-6 py-5">
                                            <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[9px] font-black uppercase ${item.activo ? 'bg-emerald-500/10 text-emerald-500' : 'bg-red-500/10 text-red-500'}`}>
                                                <span className={`w-1 h-1 rounded-full ${item.activo ? 'bg-emerald-500 animate-pulse' : 'bg-red-500'}`}></span>
                                                {item.activo ? 'Activo' : 'Inactivo'}
                                            </span>
                                        </td>
                                        <td className="px-6 py-5 text-right">
                                            <div className="flex justify-end gap-2">
                                                <button 
                                                    onClick={() => { setItemAEditar(item); setData({ nombre: item.nombre, descripcion: item.descripcion || '', monto_requerido: item.monto_requerido || '', activo: item.activo }); setModalAbierto(true); }}
                                                    className="p-2.5 theme-element border theme-border rounded-xl hover:border-[var(--color-primario)] transition-colors group/btn"
                                                >
                                                    <Edit2 className="w-4 h-4 theme-text-main group-hover/btn:text-[var(--color-primario)]" />
                                                </button>
                                                <button 
                                                    onClick={() => { setItemAEliminar(item); setModalEliminarAbierto(true); }}
                                                    className="p-2.5 theme-element border theme-border rounded-xl hover:bg-red-500 hover:border-red-500 transition-colors group/del"
                                                >
                                                    <Trash2 className="w-4 h-4 theme-text-main group-hover/del:text-white" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* MODAL DE EDICIÓN / CREACIÓN */}
                {modalAbierto && (
                    <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 backdrop-blur-md bg-black/40">
                        <div className="absolute inset-0" onClick={() => setModalAbierto(false)}></div>
                        <div className="relative w-full max-w-lg theme-surface rounded-[2.5rem] border-2 theme-border shadow-2xl overflow-hidden modal-pop">
                            <div className="p-6 md:p-8 border-b theme-border flex justify-between items-center">
                                <h2 className="text-xl font-black italic uppercase tracking-tighter theme-text-main flex items-center gap-3 leading-none">
                                    <Settings className="w-6 h-6" style={{ color: 'var(--color-primario)' }} />
                                    {itemAEditar ? 'Editar Registro' : 'Nuevo Registro'}
                                </h2>
                                <button onClick={() => setModalAbierto(false)} className="theme-text-muted hover:theme-text-main p-2">
                                    <X className="w-6 h-6" />
                                </button>
                            </div>

                            <form className="p-8 space-y-6">
                                <div className="space-y-1.5">
                                    <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Nombre del {tabActiva.slice(0,-1)}_</label>
                                    <input 
                                        type="text" value={data.nombre} onChange={e => setData('nombre', e.target.value)}
                                        className="w-full px-6 py-4 rounded-2xl theme-element border theme-border text-xs font-bold theme-text-main outline-none focus:ring-1 focus:ring-transparent transition-all"
                                        style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                    />
                                </div>

                                <div className="space-y-1.5">
                                    <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Descripción Detallada_</label>
                                    <textarea 
                                        rows="3" value={data.descripcion} onChange={e => setData('descripcion', e.target.value)}
                                        className="w-full px-6 py-4 rounded-2xl theme-element border theme-border text-xs font-bold theme-text-main outline-none transition-all resize-none"
                                    ></textarea>
                                </div>

                                {tabActiva === 'procesos' && (
                                    <div className="space-y-1.5">
                                        <label className="text-[9px] font-black uppercase tracking-widest theme-text-muted ml-2">Monto Requerido (Venta)_</label>
                                        <input 
                                            type="number" value={data.monto_requerido} onChange={e => setData('monto_requerido', e.target.value)}
                                            className="w-full px-6 py-4 rounded-2xl theme-element border theme-border text-xs font-black theme-text-main outline-none focus:ring-1 focus:ring-transparent"
                                            style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                        />
                                    </div>
                                )}

                                {/* SWITCH CORREGIDO PARA USAR EL COLOR DE ACENTO */}
                                <div className="p-4 theme-element rounded-2xl border theme-border flex items-center justify-between">
                                    <span className="text-[10px] font-black uppercase tracking-widest theme-text-main">Estado Operativo_</span>
                                    <button 
                                        type="button" 
                                        onClick={() => setData('activo', !data.activo)}
                                        className={`w-12 h-6 rounded-full relative transition-colors ${data.activo ? '' : 'bg-zinc-400 dark:bg-zinc-600'}`}
                                        style={data.activo ? { backgroundColor: 'var(--color-primario)' } : {}}
                                    >
                                        <div className={`absolute top-1 w-4 h-4 bg-white rounded-full transition-all ${data.activo ? 'left-7' : 'left-1'}`}></div>
                                    </button>
                                </div>

                                <button 
                                    className="w-full py-4 rounded-[1.5rem] text-white dark:text-black text-[11px] font-black uppercase tracking-[0.2em] italic shadow-xl flex items-center justify-center gap-2 transition-transform hover:scale-[1.02]"
                                    style={{ backgroundColor: 'var(--color-primario)' }}
                                >
                                    <Save className="w-5 h-5" /> Guardar Cambios
                                </button>
                            </form>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}