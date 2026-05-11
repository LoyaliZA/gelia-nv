import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { Head, useForm } from '@inertiajs/react';
import { animate } from 'animejs/animation';
import { 
    Settings, Plus, Edit2, Trash2, 
    X, Tags, ListTree, Activity, AlertTriangle, Save, CheckCircle2, XCircle,
    Building2, MapPin // <-- Nuevos Iconos
} from 'lucide-react';
import AppLayout from '../js/Layouts/AppLayout';
import GeliaLoader from '../js/Components/GeliaLoader';

export default function Catalogos({ auth, procesos = [], listas = [], estados = [], departamentos = [], areas = [] }) {
    const [tabActiva, setTabActiva]               = useState('procesos');
    const [modalAbierto, setModalAbierto]         = useState(false);
    const [modalEliminarAbierto, setModalEliminarAbierto] = useState(false);
    const [itemAEditar, setItemAEditar]           = useState(null);
    const [itemAEliminar, setItemAEliminar]       = useState(null);
    const [saveStatus, setSaveStatus]             = useState(null);

    const [glassEffect] = useState(() => localStorage.getItem('theme_glass') !== 'false');

    const { data, setData, post, put, processing, reset, errors } = useForm({
        nombre: '',
        descripcion: '',
        monto_requerido: '',
        departamento_id: '', // <-- Nuevo campo
        activo: true
    });

    const baseCardClass  = "fade-up theme-surface rounded-[2.5rem] relative z-10 transition-all duration-300";
    const glassCardClass = "bg-white/75 dark:bg-[#121212]/75 backdrop-blur-[24px] border-[1.5px] border-white/80 dark:border-zinc-700/60 shadow-[0_12px_40px_rgba(0,0,0,0.12)] dark:shadow-[0_12px_40px_rgba(0,0,0,0.6)]";
    const solidCardClass = "bg-white dark:bg-[#121212] border border-zinc-200 dark:border-zinc-800 shadow-[0_12px_40px_rgba(0,0,0,0.08)] dark:shadow-[0_12px_40px_rgba(0,0,0,0.5)]";
    const activeCardClass = `${baseCardClass} ${glassEffect ? glassCardClass : solidCardClass}`;

    useEffect(() => {
        animate('.tabla-row', {
            translateY: [12, 0],
            opacity: [0, 1],
            easing: 'easeOutExpo',
            duration: 500,
            delay: (el, i) => i * 30
        });
    }, [tabActiva]);

    // ─── LÓGICA DINÁMICA ───
    const getItemsActuales = () => {
        switch(tabActiva) {
            case 'procesos': return procesos;
            case 'listas':   return listas;
            case 'estados':  return estados;
            case 'departamentos': return departamentos;
            case 'areas':    return areas;
            default:         return [];
        }
    };

    const getTituloTab = () => {
        switch(tabActiva) {
            case 'procesos': return 'Procesos de Venta';
            case 'listas':   return 'Listas de Precios';
            case 'estados':  return 'Estados de Solicitud';
            case 'departamentos': return 'Departamentos Globales';
            case 'areas':    return 'Áreas Operativas';
            default:         return '';
        }
    };

    const getSingular = () => {
        switch(tabActiva) {
            case 'procesos': return 'Proceso';
            case 'listas':   return 'Lista';
            case 'estados':  return 'Estado';
            case 'departamentos': return 'Departamento';
            case 'areas':    return 'Área';
            default:         return 'Registro';
        }
    };

    // ─── SUBMITS ───
    const handleSubmit = (e) => {
        e.preventDefault();
        setSaveStatus(null);

        const ruta = tabActiva; // El ID de la tab coincide con los prefijos de rutas
        const metodo = itemAEditar ? put : post;
        const url = itemAEditar
            ? route(`catalogos.${ruta}.update`, itemAEditar.id)
            : route(`catalogos.${ruta}.store`);

        metodo(url, {
            preserveScroll: true,
            onSuccess: () => {
                setSaveStatus('success');
                setModalAbierto(false);
                reset();
                setItemAEditar(null);
                setTimeout(() => setSaveStatus(null), 4000);
            },
            onError: () => {
                setSaveStatus('error');
                setTimeout(() => setSaveStatus(null), 5000);
            }
        });
    };

    const confirmDelete = () => {
        if (!itemAEliminar) return;
        const ruta = tabActiva;
        
        post(route(`catalogos.${ruta}.destroy`, itemAEliminar.id), {
            preserveScroll: true,
            _method: 'delete',
            onSuccess: () => {
                setModalEliminarAbierto(false);
                setItemAEliminar(null);
            }
        });
    };

    const abrirEditar = (item) => {
        setItemAEditar(item);
        setData({
            nombre:           item.nombre,
            descripcion:      item.descripcion || '',
            monto_requerido:  item.monto_requerido || '',
            departamento_id:  item.departamento_id || '',
            activo:           item.activo !== undefined ? item.activo : true
        });
        setModalAbierto(true);
    };

    const abrirNuevo = () => {
        setItemAEditar(null);
        reset();
        setModalAbierto(true);
    };

    const tabs = [
        { id: 'departamentos', label: 'Departamentos', icon: Building2 },
        { id: 'areas',         label: 'Áreas',         icon: MapPin    },
        { id: 'procesos',      label: 'Procesos',      icon: ListTree  },
        { id: 'listas',        label: 'Listas',        icon: Tags      },
        { id: 'estados',       label: 'Estados',       icon: Activity  }
    ];

    const items = getItemsActuales();

    return (
        <AppLayout auth={auth}>
            <Head title="Catálogos | GELIANV" />
            <GeliaLoader isVisible={processing} message="Procesando cambios_" />

            {/* ── FEEDBACK MODAL ── */}
            {saveStatus && createPortal(
                <div className="fixed inset-0 z-[99998] flex items-center justify-center p-4 bg-black/50 backdrop-blur-md animate-fade-in" onClick={() => setSaveStatus(null)}>
                    <div className={`relative w-full max-w-sm sm:max-w-md flex flex-col items-center gap-6 p-8 sm:p-12 rounded-[2.5rem] shadow-[0_0_60px_rgba(0,0,0,0.4)] border-2 backdrop-blur-xl animate-fade-in ${saveStatus === 'success' ? 'bg-white dark:bg-[#111] border-green-400/40' : 'bg-white dark:bg-[#111] border-red-400/40'}`} onClick={e => e.stopPropagation()}>
                        <div className={`absolute inset-0 rounded-[2.5rem] opacity-10 pointer-events-none ${saveStatus === 'success' ? 'bg-green-400' : 'bg-red-400'}`} />
                        <div className={`relative z-10 w-20 h-20 sm:w-24 sm:h-24 rounded-full flex items-center justify-center shadow-xl ${saveStatus === 'success' ? 'bg-green-500/15' : 'bg-red-500/15'}`}>
                            {saveStatus === 'success' ? <CheckCircle2 className="w-10 h-10 sm:w-12 sm:h-12 text-green-500" /> : <XCircle className="w-10 h-10 sm:w-12 sm:h-12 text-red-500" />}
                        </div>
                        <div className="relative z-10 text-center space-y-2">
                            <h3 className={`text-xl sm:text-2xl font-black uppercase italic tracking-tighter m-0 ${saveStatus === 'success' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                                {saveStatus === 'success' ? '¡Guardado!' : 'Algo salió mal'}
                            </h3>
                            <p className="text-sm font-bold theme-text-muted leading-snug">
                                {saveStatus === 'success' ? 'El registro fue guardado correctamente en el catálogo.' : 'No se pudo guardar el registro. Revisa los campos e intenta de nuevo.'}
                            </p>
                        </div>
                        <button onClick={() => setSaveStatus(null)} className={`relative z-10 w-full py-4 rounded-2xl text-white font-black uppercase tracking-widest text-[11px] transition-all hover:scale-105 shadow-lg outline-none ${saveStatus === 'success' ? 'bg-green-500 hover:bg-green-600' : 'bg-red-500 hover:bg-red-600'}`}>
                            {saveStatus === 'success' ? 'Perfecto_' : 'Entendido_'}
                        </button>
                        <button onClick={() => setSaveStatus(null)} className="absolute top-4 right-4 z-10 p-2 theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 rounded-full transition-colors outline-none">
                            <X className="w-5 h-5" />
                        </button>
                    </div>
                </div>,
                document.body
            )}

            <div className="max-w-[1400px] w-full mx-auto p-4 md:p-8 space-y-8 relative">

                {/* ── HEADER ── */}
                <header className={`${activeCardClass} p-8 md:p-12 flex flex-col md:flex-row justify-between items-start md:items-center gap-8`}>
                    <div className="space-y-3">
                        <div className="flex items-center gap-3">
                            <div className="w-8 h-1.5 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <span className="text-[10px] font-black tracking-[0.2em] uppercase theme-text-muted drop-shadow-sm">
                                Estructura de Datos_
                            </span>
                        </div>
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-none m-0 p-0">
                            GESTIÓN DE <span style={{ color: 'var(--color-primario)' }}>CATÁLOGOS</span>
                        </h1>
                    </div>

                    <button
                        onClick={abrirNuevo}
                        className="flex items-center gap-3 px-8 py-4 rounded-2xl font-black uppercase text-xs italic tracking-widest transition-all hover:scale-[1.03] hover:shadow-2xl active:scale-[0.98] shadow-lg text-white border border-black/10 outline-none"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        <Plus className="w-5 h-5" /> Nuevo {getSingular()}
                    </button>
                </header>

                {/* ── TABS ── */}
                <div className={`${activeCardClass} p-2 flex flex-wrap gap-2`}>
                    {tabs.map((tab) => (
                        <button
                            key={tab.id}
                            onClick={() => setTabActiva(tab.id)}
                            className={`flex-1 flex items-center justify-center gap-2 py-4 rounded-[1.5rem] text-[10px] font-black uppercase tracking-widest transition-all outline-none min-w-[120px]
                                ${tabActiva === tab.id
                                    ? 'text-white shadow-lg'
                                    : 'theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5'}`}
                            style={tabActiva === tab.id ? { backgroundColor: 'var(--color-primario)' } : {}}
                        >
                            <tab.icon className="w-4 h-4" />
                            {tab.label}
                        </button>
                    ))}
                </div>

                {/* ── TABLA ── */}
                <section className={`${activeCardClass} overflow-hidden`}>
                    <div className="p-6 md:p-8 border-b theme-border flex items-center gap-3">
                        <div className="p-3 rounded-2xl shrink-0" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                            <Settings className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                        </div>
                        <div>
                            <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">
                                {getTituloTab()}_
                            </h2>
                            <p className="text-[10px] theme-text-muted font-bold uppercase tracking-widest mt-0.5">
                                {items.length} {items.length === 1 ? 'registro' : 'registros'} encontrados
                            </p>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        {items.length === 0 ? (
                            <div className="flex flex-col items-center justify-center py-20 gap-4">
                                <div className="p-5 rounded-3xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 10%, transparent)' }}>
                                    <AlertTriangle className="w-10 h-10" style={{ color: 'var(--color-primario)' }} />
                                </div>
                                <p className="text-sm font-black uppercase italic tracking-tighter theme-text-muted">
                                    Sin registros en este catálogo
                                </p>
                                <button
                                    onClick={abrirNuevo}
                                    className="mt-2 px-6 py-3 rounded-2xl text-white text-[11px] font-black uppercase tracking-widest transition-all hover:scale-105 shadow-md outline-none"
                                    style={{ backgroundColor: 'var(--color-primario)' }}
                                >
                                    + Crear primero
                                </button>
                            </div>
                        ) : (
                            <table className="w-full border-collapse">
                                <thead>
                                    <tr className="border-b-2 border-[var(--color-primario)]/30">
                                        <th className="px-6 py-4 text-left text-[9px] font-black text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Nombre / ID_</th>
                                        
                                        {['procesos', 'estados'].includes(tabActiva) && (
                                            <th className="px-6 py-4 text-left text-[9px] font-black text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Descripción_</th>
                                        )}
                                        
                                        {tabActiva === 'areas' && (
                                            <th className="px-6 py-4 text-left text-[9px] font-black text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Departamento_</th>
                                        )}

                                        {tabActiva === 'procesos' && (
                                            <th className="px-6 py-4 text-left text-[9px] font-black text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Monto Req._</th>
                                        )}
                                        
                                        {tabActiva !== 'areas' && (
                                            <th className="px-6 py-4 text-left text-[9px] font-black text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Status_</th>
                                        )}
                                        
                                        <th className="px-6 py-4 text-right text-[9px] font-black text-zinc-500 dark:text-zinc-400 uppercase tracking-widest">Acciones_</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {items.map((item) => (
                                        <tr key={item.id} className="tabla-row group transition-all duration-150 border-b theme-border last:border-0 hover:ring-2 hover:ring-inset hover:ring-[var(--color-primario)]/30">
                                            {/* Nombre */}
                                            <td className="px-6 py-5">
                                                <p className="text-sm font-black theme-text-main uppercase italic leading-tight">{item.nombre}</p>
                                                <p className="text-[9px] font-bold theme-text-muted uppercase tracking-tighter mt-0.5">ID: #{item.id}</p>
                                            </td>

                                            {/* Descripción */}
                                            {['procesos', 'estados'].includes(tabActiva) && (
                                                <td className="px-6 py-5 max-w-xs">
                                                    <p className="text-xs font-bold theme-text-muted truncate">{item.descripcion || '—'}</p>
                                                </td>
                                            )}

                                            {/* Departamento (solo áreas) */}
                                            {tabActiva === 'areas' && (
                                                <td className="px-6 py-5">
                                                    <span className="px-3 py-1.5 rounded-xl theme-element border theme-border text-[10px] font-black uppercase tracking-widest theme-text-main shadow-sm">
                                                        {item.departamento?.nombre || 'Huérfano'}
                                                    </span>
                                                </td>
                                            )}

                                            {/* Monto (solo procesos) */}
                                            {tabActiva === 'procesos' && (
                                                <td className="px-6 py-5">
                                                    <span className="text-xs font-black theme-text-main">
                                                        ${Number(item.monto_requerido || 0).toLocaleString()}
                                                    </span>
                                                </td>
                                            )}

                                            {/* Status (oculto en áreas) */}
                                            {tabActiva !== 'areas' && (
                                                <td className="px-6 py-5">
                                                    <span className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[9px] font-black uppercase tracking-widest ${item.activo ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-red-500/10 text-red-600 dark:text-red-400'}`}>
                                                        <span className={`w-1.5 h-1.5 rounded-full ${item.activo ? 'bg-emerald-500 animate-pulse' : 'bg-red-500'}`} />
                                                        {item.activo ? 'Activo' : 'Inactivo'}
                                                    </span>
                                                </td>
                                            )}

                                            {/* Acciones */}
                                            <td className="px-6 py-5 text-right">
                                                <div className="flex justify-end gap-2">
                                                    <button onClick={() => abrirEditar(item)} className="p-2.5 theme-element border theme-border rounded-xl hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] transition-all group/btn outline-none">
                                                        <Edit2 className="w-4 h-4 theme-text-main group-hover/btn:text-[var(--color-primario)] transition-colors" />
                                                    </button>
                                                    <button onClick={() => { setItemAEliminar(item); setModalEliminarAbierto(true); }} className="p-2.5 theme-element border theme-border rounded-xl hover:bg-red-500 hover:border-red-500 transition-all group/del outline-none">
                                                        <Trash2 className="w-4 h-4 theme-text-main group-hover/del:text-white transition-colors" />
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                </section>
            </div>

            {/* ══════════════════════════════════════════
                MODAL CREAR / EDITAR
            ══════════════════════════════════════════ */}
            {modalAbierto && createPortal(
                <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xl animate-fade-in" onClick={() => setModalAbierto(false)}>
                    <div className="w-full max-w-lg theme-surface theme-border border shadow-2xl rounded-[2.5rem] relative modal-pop flex flex-col max-h-[90vh]" onClick={e => e.stopPropagation()}>
                        
                        <div className="p-6 md:p-8 border-b theme-border flex items-center justify-between shrink-0">
                            <h2 className="text-xl font-black italic uppercase tracking-tighter theme-text-main flex items-center gap-3 m-0 leading-none">
                                <div className="p-2 rounded-xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                                    <Settings className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                                </div>
                                {itemAEditar ? `Editar ${getSingular()}` : `Nuevo ${getSingular()}`}_
                            </h2>
                            <button onClick={() => setModalAbierto(false)} className="p-2 theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 rounded-full transition-colors outline-none">
                                <X className="w-6 h-6" />
                            </button>
                        </div>

                        <form onSubmit={handleSubmit} className="p-8 space-y-6 overflow-y-auto">
                            
                            {/* Selector de Departamento (Solo visible al crear Áreas) */}
                            {tabActiva === 'areas' && (
                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">
                                        Departamento Base_
                                    </label>
                                    <div className="relative">
                                        <Building2 className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                        <select
                                            value={data.departamento_id}
                                            onChange={e => setData('departamento_id', e.target.value)}
                                            className="w-full pl-12 pr-10 py-4 text-sm font-bold theme-surface border border-zinc-300 dark:border-zinc-700 rounded-xl theme-text-main outline-none cursor-pointer focus:ring-2 transition-all appearance-none shadow-sm"
                                            style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                        >
                                            <option value="">-- Selecciona el Departamento --</option>
                                            {departamentos.map(depto => (
                                                <option key={depto.id} value={depto.id}>{depto.nombre}</option>
                                            ))}
                                        </select>
                                    </div>
                                    {errors.departamento_id && <p className="text-xs text-red-500 mt-1 px-1">{errors.departamento_id}</p>}
                                </div>
                            )}

                            {/* Nombre Común */}
                            <div className="space-y-2">
                                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">
                                    Nombre del {getSingular()}_
                                </label>
                                <input type="text" value={data.nombre} onChange={e => setData('nombre', e.target.value)}
                                    className="w-full px-5 py-4 theme-surface border border-zinc-300 dark:border-zinc-700 rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md"
                                    style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                    placeholder={`Ej. Soporte, Operaciones...`}
                                />
                                {errors.nombre && <p className="text-xs text-red-500 mt-1 px-1">{errors.nombre}</p>}
                            </div>

                            {/* Descripción (Solo procesos y estados) */}
                            {['procesos', 'estados'].includes(tabActiva) && (
                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">
                                        Descripción Detallada_
                                    </label>
                                    <textarea rows="3" value={data.descripcion} onChange={e => setData('descripcion', e.target.value)}
                                        className="w-full px-5 py-4 theme-surface border border-zinc-300 dark:border-zinc-700 rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md resize-none"
                                        style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                    />
                                </div>
                            )}

                            {/* Monto (solo procesos) */}
                            {tabActiva === 'procesos' && (
                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">
                                        Monto Requerido (Venta)_
                                    </label>
                                    <input type="number" value={data.monto_requerido} onChange={e => setData('monto_requerido', e.target.value)}
                                        className="w-full px-5 py-4 theme-surface border border-zinc-300 dark:border-zinc-700 rounded-xl theme-text-main text-sm font-black outline-none focus:ring-2 transition-all shadow-sm hover:shadow-md"
                                        style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                    />
                                </div>
                            )}

                            {/* Activo (Oculto para Áreas porque la tabla SQL no lo soporta) */}
                            {tabActiva !== 'areas' && (
                                <div className={`flex items-center justify-between p-4 rounded-2xl border ${glassEffect ? 'bg-black/5 dark:bg-white/5 border-zinc-300 dark:border-zinc-700' : 'bg-zinc-100 dark:bg-zinc-900/60 border-zinc-300 dark:border-zinc-700'}`}>
                                    <div>
                                        <p className="text-sm font-black theme-text-main">Estado Operativo_</p>
                                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-0.5">
                                            {data.activo ? 'Activo en el sistema' : 'Inactivo / deshabilitado'}
                                        </p>
                                    </div>
                                    <button type="button" onClick={() => setData('activo', !data.activo)} className="gelia-switch shrink-0 scale-125 origin-right shadow-sm" data-active={data.activo}>
                                        <div className="gelia-switch-thumb shadow-md" />
                                    </button>
                                </div>
                            )}

                            <button type="submit" disabled={processing} className="w-full py-4 rounded-2xl text-white font-black uppercase tracking-widest text-[11px] transition-all hover:scale-[1.02] shadow-xl flex justify-center items-center gap-3 disabled:opacity-60 outline-none" style={{ backgroundColor: 'var(--color-primario)' }}>
                                <Save className="w-5 h-5" /> {processing ? 'Guardando...' : 'Guardar cambios'}
                            </button>
                        </form>
                    </div>
                </div>,
                document.body
            )}

            {/* ══════════════════════════════════════════
                MODAL ELIMINAR
            ══════════════════════════════════════════ */}
            {modalEliminarAbierto && itemAEliminar && createPortal(
                <div className="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/70 backdrop-blur-xl animate-fade-in" onClick={() => setModalEliminarAbierto(false)}>
                    <div className="w-full max-w-sm theme-surface border-2 border-red-500/30 shadow-2xl rounded-[2.5rem] p-8 flex flex-col items-center gap-6 relative modal-pop" onClick={e => e.stopPropagation()}>
                        <div className="absolute inset-0 rounded-[2.5rem] bg-red-500/5 pointer-events-none" />
                        <button onClick={() => setModalEliminarAbierto(false)} className="absolute top-4 right-4 p-2 theme-text-muted hover:theme-text-main hover:bg-black/5 rounded-full transition-colors outline-none"><X className="w-5 h-5" /></button>
                        
                        <div className="w-20 h-20 rounded-full bg-red-500/10 flex items-center justify-center shadow-xl relative z-10">
                            <Trash2 className="w-9 h-9 text-red-500" />
                        </div>
                        <div className="text-center space-y-2 relative z-10">
                            <h3 className="text-xl font-black uppercase italic tracking-tighter text-red-600 dark:text-red-400 m-0">¿Eliminar registro?</h3>
                            <p className="text-sm font-bold theme-text-muted leading-snug">Estás a punto de eliminar <span className="font-black theme-text-main">"{itemAEliminar.nombre}"</span>. Esta acción no se puede deshacer.</p>
                        </div>
                        <div className="flex w-full gap-3 relative z-10">
                            <button type="button" onClick={() => setModalEliminarAbierto(false)} className="flex-1 py-3 px-4 theme-element border theme-border rounded-2xl text-xs font-bold theme-text-main transition-transform hover:scale-105 shadow-sm outline-none">Cancelar</button>
                            <button type="button" onClick={confirmDelete} className="flex-1 py-3 px-4 bg-red-500 hover:bg-red-600 text-white rounded-2xl text-xs font-black uppercase tracking-widest transition-all hover:scale-105 shadow-md flex items-center justify-center gap-2 outline-none"><Trash2 className="w-4 h-4" /> Eliminar</button>
                        </div>
                    </div>
                </div>,
                document.body
            )}
        </AppLayout>
    );
}