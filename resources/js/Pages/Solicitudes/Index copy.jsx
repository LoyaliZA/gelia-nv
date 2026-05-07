/* --- SECCIÓN: IMPORTACIONES --- */
import React, { useEffect, useState, useRef } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { animate } from 'animejs/animation';
import axios from 'axios';
import { 
    Clock, Sparkles, Send, 
    ShieldCheck, Info, 
    Plus, MoreVertical, Edit2, 
    CheckCircle2, XCircle, FileText, X,
    AlertOctagon, Search,
    History, CheckSquare, CreditCard,
    FileSpreadsheet, Upload, UserPlus
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

/* --- SECCIÓN: ESTILOS GLOBALES --- */
// Extraído del render principal para evitar el repintado (Flash of Unstyled Content) en cada pulsación de tecla
const ESTILOS_GLOBALES = `
    .theme-surface { background-color: #ffffff; border-color: #f4f4f5; }
    .theme-element { background-color: #fafafa; border-color: #e4e4e7; }
    .theme-text-main { color: #18181b; }
    .theme-text-muted { color: #71717a; }
    .theme-border { border-color: #f4f4f5; }
    .theme-hover-bg:hover { background-color: #f8f8f8; }
    .theme-overlay { background-color: rgba(255, 255, 255, 0.4); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
    
    .dark .theme-surface { background-color: #121212; border-color: #222222; }
    .dark .theme-element { background-color: #1A1A1A; border-color: #2A2A2A; }
    .dark .theme-text-main { color: #ffffff; }
    .dark .theme-text-muted { color: #a1a1aa; }
    .dark .theme-border { border-color: #222222; }
    .dark .theme-hover-bg:hover { background-color: #1A1A1A; }
    .dark .theme-overlay { background-color: rgba(0, 0, 0, 0.6); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
    
    .status-aprobado { background-color: #ecfdf5; color: #059669; }
    .status-incidencia { background-color: #fef2f2; color: #b91c1c; border-color: #fca5a5; }
    .status-verificado { background-color: #eff6ff; color: #2563eb; }
    .status-revision { background-color: #fffbeb; color: #d97706; }

    .dark .status-aprobado { background-color: rgba(16, 185, 129, 0.1); color: #34d399; }
    .dark .status-incidencia { background-color: rgba(239, 68, 68, 0.1); color: #fca5a5; border-color: rgba(239, 68, 68, 0.3); }
    .dark .status-verificado { background-color: rgba(59, 130, 246, 0.1); color: #60a5fa; }
    .dark .status-revision { background-color: rgba(245, 158, 11, 0.1); color: #fbbf24; }

    @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
    .animate-fade-in { animation: fadeIn 0.3s ease-out forwards; }
    
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background-color: var(--color-primario); border-radius: 10px; opacity: 0.5; }
`;

export default function Index({ solicitudes = { total: 0, data: [] }, procesos = [], auth, filtros = {} }) {
    
    /* --- SECCIÓN: ESTADOS GLOBALES DEL COMPONENTE --- */
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalRespuestaAbierto, setModalRespuestaAbierto] = useState(false);
    const [menuAbierto, setMenuAbierto] = useState(null);
    const [tabActiva, setTabActiva] = useState('TODAS');
    const [busqueda, setBusqueda] = useState('');

    /* --- SECCIÓN: ESTADOS DE BÚSQUEDA DE CLIENTES --- */
    const [infoCliente, setInfoCliente] = useState(null);
    const [listaClientes, setListaClientes] = useState([]);
    const [mostrarDropdown, setMostrarDropdown] = useState(false);
    const [buscandoCliente, setBuscandoCliente] = useState(false);
    const [alertaHeredado, setAlertaHeredado] = useState(false);
    
    // Referencia para controlar el temporizador del debounce y evitar peticiones excesivas
    const temporizadorBusqueda = useRef(null);

    /* --- SECCIÓN: FORMULARIOS (INERTIA) --- */
    const { data, setData, post, processing, reset, errors } = useForm({
        numero_cliente: '',
        nombre_cliente: '',
        monto_cotizado: '',
        catalogo_proceso_id: '',
        observaciones_vendedor: '',
        evidencia: null,
    });

    const formRespuesta = useForm({
        solicitud_id: null,
        catalogo_estado_solicitud_id: '',
        motivo: '',
        evidencia_respuesta: null,
    });

    /* --- SECCIÓN: EFECTOS Y LÓGICA DE NEGOCIO --- */
    const can = (permiso) => auth?.user?.permissions?.includes(permiso);

    useEffect(() => {
        animate('.tab-content', {
            translateY: [10, 0],
            opacity: [0, 1],
        }, {
            easing: 'easeOutExpo',
            duration: 600,
            delay: (el, i) => i * 100
        });
    }, []);

    // Función pura para peticiones API
    const fetchClientes = async (term = '') => {
        if (!term) return;
        setBuscandoCliente(true);
        setMostrarDropdown(true);
        try {
            const response = await axios.get(`/api/clientes?q=${term}`);
            setListaClientes(response.data);
        } catch (error) {
            console.error("Error consultando directorio", error);
            setListaClientes([]); // Manejo seguro en caso de excepción
        } finally {
            setBuscandoCliente(false);
        }
    };

    // Función controladora (Debounce) para evitar saturación de la API al escribir
    const manejarBusquedaCliente = (valor) => {
        setData('numero_cliente', valor);
        setInfoCliente(null);
        setAlertaHeredado(false);

        // Limpiamos el temporizador anterior si el usuario sigue escribiendo
        if (temporizadorBusqueda.current) {
            clearTimeout(temporizadorBusqueda.current);
        }

        if (valor.trim() === '') {
            setMostrarDropdown(false);
            setListaClientes([]);
            return;
        }

        // Retrasamos la petición 400ms para asegurar que el usuario terminó de escribir
        temporizadorBusqueda.current = setTimeout(() => {
            fetchClientes(valor);
        }, 400);
    };

    const seleccionarCliente = (cliente) => {
        setData('numero_cliente', cliente.numero_cliente);
        setData('nombre_cliente', cliente.nombre);
        setInfoCliente(cliente);
        setMostrarDropdown(false);
        setAlertaHeredado(false);
    };

    const guardarSolicitud = (e) => {
        e.preventDefault();
        const procesoSeleccionado = procesos.find(p => p.id == data.catalogo_proceso_id);
        
        // Validación de lógica de negocio segura
        if (infoCliente?.es_heredado && (procesoSeleccionado?.nombre === 'ASIGNAR CLIENTE REACTIVADO' || procesoSeleccionado?.nombre === 'ASIGNAR CLIENTE REACTIVADO Y CAMBIO DE LISTA')) {
            setAlertaHeredado(true);
            return;
        }
        
        post(route('solicitudes.store'), {
            onSuccess: () => { setModalAbierto(false); reset(); }
        });
    };

    const iniciarRespuesta = (solicitud, estadoId) => {
        formRespuesta.setData({
            solicitud_id: solicitud.id,
            catalogo_estado_solicitud_id: estadoId,
            motivo: '',
            evidencia_respuesta: null
        });
        setMenuAbierto(null);
        setModalRespuestaAbierto(true);
    };

    const enviarRespuesta = (e) => {
        e.preventDefault();
        formRespuesta.post(route('solicitudes.actualizar_estado', formRespuesta.data.solicitud_id), {
            onSuccess: () => { setModalRespuestaAbierto(false); formRespuesta.reset(); }
        });
    };

    // Filtros de tabla
    const solicitudesFiltradas = (solicitudes.data || []).filter(solicitud => {
        const cumpleTab = tabActiva === 'TODAS' || 
                         (tabActiva === 'PENDIENTES' && solicitud.estado?.nombre === 'Pendiente') ||
                         (tabActiva === 'RESPONDIDAS' && solicitud.estado?.nombre === 'Respondida') ||
                         (tabActiva === 'INCORRECTAS' && solicitud.estado?.nombre === 'Incorrecta');

        const idString = solicitud.id ? solicitud.id.toString() : '';
        const nombreCliente = solicitud.cliente?.nombre || '';
        
        const cumpleBusqueda = busqueda === '' || 
                               idString.includes(busqueda) || 
                               nombreCliente.toLowerCase().includes(busqueda.toLowerCase());
        return cumpleTab && cumpleBusqueda;
    });

    const obtenerEstiloEstado = (nombreEstado) => {
        switch(nombreEstado?.toLowerCase()) {
            case 'respondida': return { clase: 'status-aprobado', icon: CheckCircle2, label: 'Aprobado (TAGS)' };
            case 'incorrecta': return { clase: 'status-incidencia', icon: AlertOctagon, label: 'Reporte (Inconsistencia)' };
            case 'verificada': return { clase: 'status-verificado', icon: CheckSquare, label: 'Verificada (Auxiliar)' };
            default: return { clase: 'status-revision', icon: Clock, label: 'Pendiente' };
        }
    };

    /* --- SECCIÓN: RENDERIZADO (VISTA) --- */
    return (
        <AppLayout auth={auth}>
            <Head title="Panel de Solicitudes | GELIANV" />
            <style>{ESTILOS_GLOBALES}</style>

            {/* MODAL NUEVA SOLICITUD */}
            {modalAbierto && (
                <div className="fixed inset-0 z-[60] flex items-center justify-center theme-overlay p-4 overflow-y-auto">
                    <div className="theme-surface border-2 theme-border rounded-[3rem] p-8 md:p-12 shadow-2xl max-w-5xl w-full relative tab-content my-auto">
                        <button onClick={() => setModalAbierto(false)} className="absolute top-6 right-6 p-3 theme-element rounded-2xl hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-all" style={{ color: 'var(--color-primario)' }}>
                            <X className="w-5 h-5" />
                        </button>

                        <form onSubmit={guardarSolicitud} className="grid grid-cols-1 lg:grid-cols-5 gap-12">
                            <div className="lg:col-span-3 space-y-8">
                                <h2 className="text-2xl font-black italic theme-text-main uppercase tracking-tighter flex items-center">
                                    <Sparkles className="w-6 h-6 mr-3" style={{ color: 'var(--color-primario)' }} /> Nueva Solicitud
                                </h2>

                                <div className="space-y-3 relative">
                                    <label className="text-xs font-black uppercase theme-text-muted ml-2 tracking-widest italic">Número o Nombre de Cliente_</label>
                                    <div className="relative">
                                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                                        <input 
                                            type="text" 
                                            value={data.numero_cliente} 
                                            onChange={e => manejarBusquedaCliente(e.target.value)} 
                                            onFocus={() => { if(data.numero_cliente) setMostrarDropdown(true); }}
                                            placeholder="Buscar cliente..." 
                                            // Clases Tailwind de Focus en lugar de manipular DOM directamente
                                            className="w-full pl-12 p-4 theme-element border-2 theme-border rounded-2xl theme-text-main font-bold outline-none transition-all focus:border-[var(--color-primario)] focus:ring-1 focus:ring-[var(--color-primario)]" 
                                            autoComplete="off"
                                        />
                                    </div>

                                    {mostrarDropdown && (
                                        <div className="absolute top-[85px] left-0 right-0 theme-surface border-2 theme-border rounded-2xl shadow-2xl z-50 max-h-64 overflow-y-auto custom-scrollbar">
                                            <div className="sticky top-0 bg-white/90 dark:bg-zinc-900/90 backdrop-blur-md px-4 py-2 border-b theme-border flex justify-between items-center z-10">
                                                <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">Directorio Wizerp</span>
                                                <button type="button" onClick={() => setMostrarDropdown(false)} className="text-[10px] font-black uppercase" style={{ color: 'var(--color-primario)' }}>Cerrar</button>
                                            </div>
                                            {buscandoCliente ? (
                                                <div className="p-6 text-center text-xs font-bold theme-text-muted animate-pulse italic">Consultando matriz de datos...</div>
                                            ) : listaClientes.map(c => (
                                                <div key={c.id} onClick={() => seleccionarCliente(c)} className="p-4 border-b theme-border last:border-b-0 hover:bg-zinc-50 dark:hover:bg-zinc-800/50 cursor-pointer transition-colors flex justify-between items-center group">
                                                    <div>
                                                        <p className="text-xs font-black uppercase theme-text-main group-hover:text-[var(--color-primario)] transition-colors">
                                                            {c.numero_cliente} <span className="opacity-30 mx-1">|</span> {c.nombre}
                                                        </p>
                                                        <p className="text-[9px] theme-text-muted font-bold mt-1 uppercase tracking-tighter">Lista: {c.lista_actual}</p>
                                                    </div>
                                                    {c.es_heredado && <ShieldCheck className="w-4 h-4 text-amber-500" />}
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div className="space-y-3">
                                        <label className="text-xs font-black uppercase theme-text-muted ml-2 tracking-widest italic">Cotización ($)_</label>
                                        <input 
                                            type="number" 
                                            step="0.01" 
                                            value={data.monto_cotizado} 
                                            onChange={e => setData('monto_cotizado', e.target.value)} 
                                            required 
                                            className="w-full p-4 theme-element border-2 theme-border rounded-2xl theme-text-main font-bold outline-none transition-all focus:border-[var(--color-primario)] focus:ring-1 focus:ring-[var(--color-primario)]" 
                                        />
                                    </div>
                                    <div className="space-y-3">
                                        <label className="text-xs font-black uppercase theme-text-muted ml-2 tracking-widest italic">Tipo Solicitud_</label>
                                        <select 
                                            value={data.catalogo_proceso_id} 
                                            onChange={e => setData('catalogo_proceso_id', e.target.value)} 
                                            required 
                                            className="w-full p-4 theme-element border-2 theme-border rounded-2xl theme-text-main font-bold outline-none cursor-pointer appearance-none transition-all focus:border-[var(--color-primario)] focus:ring-1 focus:ring-[var(--color-primario)]"
                                        >
                                            <option value="">Seleccionar...</option>
                                            {procesos.map(p => <option key={p.id} value={p.id}>{p.nombre}</option>)}
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div className="lg:col-span-2 space-y-8">
                                <div className="space-y-3">
                                    <label className="text-xs font-black uppercase theme-text-muted ml-2 tracking-widest italic">Evidencia (Ticket/Imagen)_</label>
                                    <label className="flex flex-col items-center justify-center h-48 border-2 border-dashed theme-border rounded-3xl cursor-pointer theme-element hover:bg-zinc-50 dark:hover:bg-zinc-800/30 transition-all" style={{ borderColor: data.evidencia ? 'var(--color-primario)' : '' }}>
                                        <Upload className="w-8 h-8 mb-2 theme-text-muted" style={{ color: data.evidencia ? 'var(--color-primario)' : '' }} />
                                        <p className="text-[10px] theme-text-muted uppercase font-black">{data.evidencia ? data.evidencia.name : 'Vincular archivo_'}</p>
                                        <input type="file" className="hidden" accept="image/*" onChange={e => setData('evidencia', e.target.files[0])} />
                                    </label>
                                </div>
                                <button type="submit" disabled={processing} className="w-full py-5 text-white rounded-2xl font-black uppercase tracking-widest text-[10px] shadow-xl hover:scale-[1.02] transition-all disabled:opacity-50 disabled:cursor-not-allowed" style={{ backgroundColor: 'var(--color-primario)' }}>
                                    {processing ? 'Enviando...' : 'Transmitir Solicitud_'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {/* VISTA PRINCIPAL */}
            <div className="max-w-[1440px] mx-auto p-6 md:p-12 space-y-12">
                <header className="flex flex-col md:flex-row md:items-center justify-between gap-6 tab-content">
                    <div>
                        <div className="flex items-center space-x-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>Central Infrastructure</p>
                        </div>
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main">PANEL DE <span style={{ color: 'var(--color-primario)' }}>SOLICITUDES</span></h1>
                    </div>
                    {can('crear_solicitud') && (
                        <button onClick={() => { setModalAbierto(true); }} className="flex items-center gap-2 px-8 py-4 text-white rounded-2xl font-black uppercase tracking-widest text-[10px] shadow-xl hover:scale-105 transition-all" style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Plus className="w-4 h-4" /> Nueva Solicitud
                        </button>
                    )}
                </header>

                <div className="tab-content flex flex-col lg:flex-row gap-6 items-center justify-between">
                    <div className="flex gap-2 p-1.5 theme-surface border theme-border rounded-2xl w-full lg:w-fit overflow-x-auto">
                        {['TODAS', 'PENDIENTES', 'RESPONDIDAS', 'INCORRECTAS'].map(tab => (
                            <button 
                                key={tab} 
                                onClick={() => setTabActiva(tab)} 
                                className={`px-5 py-2.5 rounded-xl font-black uppercase tracking-widest text-[9px] transition-all whitespace-nowrap ${tabActiva === tab ? 'text-white dark:text-black shadow-md' : 'theme-text-muted hover:theme-text-main hover:theme-element'}`}
                                style={{ backgroundColor: tabActiva === tab ? 'var(--color-primario)' : '' }}
                            >
                                {tab}
                            </button>
                        ))}
                    </div>
                    <div className="relative w-full lg:w-80">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                        <input 
                            type="text" 
                            placeholder="Buscar folio o cliente..." 
                            value={busqueda} 
                            onChange={e => setBusqueda(e.target.value)} 
                            className="w-full pl-12 pr-4 py-3 theme-element border theme-border rounded-xl text-xs font-bold theme-text-main outline-none focus:border-[var(--color-primario)] focus:ring-1 focus:ring-[var(--color-primario)] transition-all" 
                        />
                    </div>
                </div>

                <div className="tab-content theme-surface border-2 theme-border rounded-[3rem] p-8 md:p-12 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto pb-32">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="border-b-2 theme-border">
                                    <th className="pb-6 pl-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">ID & Vendedora_</th>
                                    <th className="pb-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Titular / Proceso_</th>
                                    <th className="pb-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Cotización_</th>
                                    <th className="pb-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Operación_</th>
                                    <th className="pb-6 text-[10px] font-black uppercase tracking-widest theme-text-muted text-center">Acciones_</th>
                                </tr>
                            </thead>
                            <tbody>
                                {solicitudesFiltradas.length === 0 ? (
                                    <tr><td colSpan="5" className="text-center py-12 text-zinc-500 font-bold uppercase text-[10px] italic">No hay registros en esta frecuencia.</td></tr>
                                ) : solicitudesFiltradas.map((solicitud) => {
                                    const estatus = obtenerEstiloEstado(solicitud.estado?.nombre);
                                    const StatusIcon = estatus.icon;
                                    return (
                                        <tr key={solicitud.id} className="border-b theme-border theme-hover-bg transition-colors">
                                            <td className="py-5 pl-4">
                                                <div className="font-black text-xs theme-text-main" style={{ color: 'var(--color-primario)' }}>FOL-{solicitud.id}</div>
                                                <div className="text-[9px] font-bold theme-text-muted mt-1 uppercase">{solicitud.vendedor?.name}</div>
                                            </td>
                                            <td className="py-5">
                                                <div className="font-bold text-sm theme-text-main uppercase italic">{solicitud.cliente?.nombre || 'Nuevo Prospecto'}</div>
                                                <div className="text-[10px] font-bold theme-text-muted mt-1">{solicitud.proceso?.nombre}</div>
                                            </td>
                                            <td className="py-5 font-black italic theme-text-main text-sm">
                                                {new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(solicitud.monto_cotizado)}
                                            </td>
                                            <td className="py-5">
                                                <div className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-transparent ${estatus.clase}`}>
                                                    <StatusIcon className="w-3.5 h-3.5" />
                                                    <span className="text-[9px] font-black uppercase tracking-wider italic">{estatus.label}</span>
                                                </div>
                                            </td>
                                            <td className="py-5 text-center relative">
                                                <button onClick={() => setMenuAbierto(menuAbierto === solicitud.id ? null : solicitud.id)} className="p-2 theme-hover-bg rounded-xl transition-colors inline-flex border border-transparent hover:border-zinc-300 dark:hover:border-zinc-700">
                                                    <MoreVertical className="w-4 h-4 theme-text-main" />
                                                </button>
                                                {menuAbierto === solicitud.id && (
                                                    <>
                                                        <div className="fixed inset-0 z-10" onClick={() => setMenuAbierto(null)}></div>
                                                        <div className="absolute right-10 top-0 theme-surface border-2 theme-border shadow-2xl rounded-2xl p-2 z-20 w-56 flex flex-col gap-1 text-left animate-fade-in">
                                                            {can('confirmar_pago') && !solicitud.pago_confirmado && <button onClick={() => router.put(route('solicitudes.confirmar_pago', solicitud.id))} className="flex items-center gap-3 px-3 py-2 hover:bg-blue-50 dark:hover:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-lg text-[10px] font-bold uppercase"><CreditCard className="w-3.5 h-3.5" /> Confirmar Pago</button>}
                                                            {can('verificar_auxiliar') && <button onClick={() => iniciarRespuesta(solicitud, 3)} className="flex items-center gap-3 px-3 py-2 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 text-emerald-600 dark:text-emerald-400 rounded-lg text-[10px] font-bold uppercase"><CheckSquare className="w-3.5 h-3.5" /> Marcar Verificado</button>}
                                                            {can('ejecutar_tags') && (
                                                                <>
                                                                    <button onClick={() => iniciarRespuesta(solicitud, 2)} className="flex items-center gap-3 px-3 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-800 rounded-lg text-[10px] font-bold uppercase" style={{ color: 'var(--color-primario)' }}><CheckCircle2 className="w-3.5 h-3.5" /> Aprobar Proceso</button>
                                                                    <button onClick={() => iniciarRespuesta(solicitud, 4)} className="flex items-center gap-3 px-3 py-2 hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 dark:text-red-400 rounded-lg text-[10px] font-bold uppercase"><AlertOctagon className="w-3.5 h-3.5" /> Reportar Error</button>
                                                                </>
                                                            )}
                                                            {can('ver_auditoria') && <button className="flex items-center gap-3 px-3 py-2 hover:bg-purple-50 dark:hover:bg-purple-900/20 text-purple-600 dark:text-purple-400 rounded-lg text-[10px] font-bold uppercase"><History className="w-3.5 h-3.5" /> Ver Bitácora</button>}
                                                        </div>
                                                    </>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}