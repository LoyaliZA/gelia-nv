import React, { useEffect, useState, useRef } from 'react';
import { createPortal } from 'react-dom';
import { Head, useForm, router } from '@inertiajs/react';
import { animate } from 'animejs/animation';
import axios from 'axios';
import { 
    Clock, Sparkles, Send, ShieldCheck, Info, Plus, MoreVertical, Edit2, 
    CheckCircle2, XCircle, FileText, X, AlertOctagon, Search, History, 
    CheckSquare, CreditCard, Upload, FileSignature, AlertTriangle, User
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaLoader from '../../Components/GeliaLoader';

/* --- ESTILOS GLOBALES ESPECÍFICOS --- */
const ESTILOS_GLOBALES = `
    .status-aprobado { background-color: #ecfdf5; color: #059669; border-color: #a7f3d0; }
    .status-incidencia { background-color: #fef2f2; color: #b91c1c; border-color: #fca5a5; }
    .status-verificado { background-color: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
    .status-revision { background-color: #fffbeb; color: #d97706; border-color: #fde68a; }
    .dark .status-aprobado { background-color: rgba(16, 185, 129, 0.1); color: #34d399; border-color: rgba(52, 211, 153, 0.3); }
    .dark .status-incidencia { background-color: rgba(239, 68, 68, 0.1); color: #fca5a5; border-color: rgba(239, 68, 68, 0.3); }
    .dark .status-verificado { background-color: rgba(59, 130, 246, 0.1); color: #60a5fa; border-color: rgba(96, 165, 250, 0.3); }
    .dark .status-revision { background-color: rgba(245, 158, 11, 0.1); color: #fbbf24; border-color: rgba(251, 191, 36, 0.3); }
`;

export default function Index({ solicitudes = { total: 0, data: [] }, procesos = [], auth, filtros = {} }) {
    
    /* --- ESTADOS GLOBALES DEL COMPONENTE --- */
    const [modalAbierto, setModalAbierto] = useState(false);
    const [modalRespuestaAbierto, setModalRespuestaAbierto] = useState(false);
    const [modalBitacoraAbierto, setModalBitacoraAbierto] = useState(false);
    const [menuAbierto, setMenuAbierto] = useState(null);
    const [tabActiva, setTabActiva] = useState('TODAS');
    const [busqueda, setBusqueda] = useState('');
    const [solicitudAuditada, setSolicitudAuditada] = useState(null);

    /* --- ESTADOS DE BÚSQUEDA DE CLIENTES --- */
    const [infoCliente, setInfoCliente] = useState(null);
    const [listaClientes, setListaClientes] = useState([]);
    const [mostrarDropdown, setMostrarDropdown] = useState(false);
    const [buscandoCliente, setBuscandoCliente] = useState(false);
    const [alertaHeredado, setAlertaHeredado] = useState(false);

    const temporizadorBusqueda = useRef(null);

    /* --- FORMULARIOS (INERTIA) --- */
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
        _method: 'put', // SPOOFING PARA EVITAR ERROR 405 EN LARAVEL
    });

    /* --- EFECTOS Y LÓGICA DE NEGOCIO --- */
    const can = (permiso) => auth?.user?.permissions?.includes(permiso) || auth?.user?.roles?.includes('Super Admin');

    useEffect(() => {
        animate('.page-reveal-solicitudes', {
            translateY: [15, 0],
            opacity: [0, 1],
        }, {
            easing: 'easeOutExpo',
            duration: 600,
            delay: (el, i) => i * 80
        });
    }, []);

    useEffect(() => {
        if (modalAbierto || modalRespuestaAbierto || modalBitacoraAbierto) document.body.style.overflow = 'hidden';
        else document.body.style.overflow = 'unset';
        return () => { document.body.style.overflow = 'unset'; };
    }, [modalAbierto, modalRespuestaAbierto, modalBitacoraAbierto]);

    const fetchClientes = async (term = '') => {
        if (!term) return;
        setBuscandoCliente(true);
        setMostrarDropdown(true);
        try {
            const response = await axios.get(`/api/clientes?q=${term}`);
            setListaClientes(response.data);
        } catch (error) {
            console.error("Error consultando directorio", error);
            setListaClientes([]); 
        } finally {
            setBuscandoCliente(false);
        }
    };

    const manejarBusquedaCliente = (valor) => {
        setData('numero_cliente', valor);
        setInfoCliente(null);
        setAlertaHeredado(false);

        if (temporizadorBusqueda.current) clearTimeout(temporizadorBusqueda.current);

        if (valor.trim() === '') {
            setMostrarDropdown(false);
            setListaClientes([]);
            return;
        }

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
            evidencia_respuesta: null,
            _method: 'put'
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

    const confirmarPagoManual = (id) => {
        setMenuAbierto(null);
        router.put(route('solicitudes.confirmar_pago', id));
    };

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
            case 'incorrecta': return { clase: 'status-incidencia', icon: AlertOctagon, label: 'Reporte (Error)' };
            case 'verificada': return { clase: 'status-verificado', icon: CheckSquare, label: 'Verificada (Auxiliar)' };
            default: return { clase: 'status-revision', icon: Clock, label: 'Pendiente' };
        }
    };

    const activeCardClass = "page-reveal-solicitudes theme-surface rounded-[2.5rem] relative z-10 transition-all duration-300 border border-zinc-200 dark:border-zinc-800 shadow-[0_12px_40px_rgba(0,0,0,0.08)] dark:shadow-[0_12px_40px_rgba(0,0,0,0.5)] bg-white/75 dark:bg-[#121212]/75 backdrop-blur-[24px]";

    return (
        <AppLayout auth={auth}>
            <Head title="Panel de Solicitudes | GELIANV" />
            <style>{ESTILOS_GLOBALES}</style>

            <GeliaLoader isVisible={processing || formRespuesta.processing} message="Procesando datos en la matriz_" />

            <div className="max-w-[1400px] w-full mx-auto p-4 md:p-8 space-y-8 relative">
                
                {/* --- HEADER GELIA --- */}
                <header className={`${activeCardClass} p-8 md:p-10 flex flex-col md:flex-row items-center justify-between gap-6`}>
                    <div>
                        <div className="flex items-center space-x-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>Central Infrastructure</p>
                        </div>
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main m-0 p-0">
                            PANEL DE <span style={{ color: 'var(--color-primario)' }}>SOLICITUDES</span>
                        </h1>
                    </div>
                    {can('solicitudes.crear') && (
                        <button onClick={() => setModalAbierto(true)} className="flex items-center justify-center gap-2 px-8 py-4 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] shadow-xl hover:scale-105 transition-all w-full md:w-auto outline-none" style={{ backgroundColor: 'var(--color-primario)' }}>
                            <Plus className="w-5 h-5" /> Nueva Solicitud
                        </button>
                    )}
                </header>

                {/* --- CONTROLES Y FILTROS --- */}
                <div className="page-reveal-solicitudes flex flex-col lg:flex-row gap-6 items-start lg:items-center justify-between">
                    <div className="gelia-segment w-full lg:w-auto p-1 h-14 shadow-sm overflow-x-auto">
                        {['TODAS', 'PENDIENTES', 'RESPONDIDAS', 'INCORRECTAS'].map(tab => (
                            <button 
                                key={tab} 
                                type="button"
                                onClick={() => setTabActiva(tab)} 
                                className="gelia-segment-btn px-6 min-w-max" 
                                data-active={tabActiva === tab}
                            >
                                {tab}
                            </button>
                        ))}
                    </div>
                    
                    <div className="relative w-full lg:w-96 shrink-0">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                        <input 
                            type="text" 
                            placeholder="Buscar folio o cliente..." 
                            value={busqueda} 
                            onChange={e => setBusqueda(e.target.value)} 
                            className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all shadow-sm hover:shadow-md"
                            onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} 
                            onBlur={e => e.target.style.borderColor = ''}
                        />
                    </div>
                </div>

                {/* --- TABLA PRINCIPAL --- */}
                <div className={`${activeCardClass} p-2 sm:p-6 overflow-hidden w-full`}>
                    <div className="overflow-x-auto custom-scrollbar pb-32">
                        <table className="w-full text-left border-collapse min-w-[900px]">
                            <thead>
                                <tr className="border-b-2 theme-border">
                                    <th className="pb-6 pl-6 pt-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Folio & Asesor_</th>
                                    <th className="pb-6 pt-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Titular / Proceso_</th>
                                    <th className="pb-6 pt-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Cotización_</th>
                                    <th className="pb-6 pt-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Estado_</th>
                                    <th className="pb-6 pt-4 pr-6 text-[10px] font-black uppercase tracking-widest theme-text-muted text-center">Acciones_</th>
                                </tr>
                            </thead>
                            <tbody>
                                {solicitudesFiltradas.length === 0 ? (
                                    <tr><td colSpan="5" className="text-center py-16 text-zinc-500 font-bold uppercase text-[11px] tracking-widest italic">No se encontraron registros en esta frecuencia.</td></tr>
                                ) : solicitudesFiltradas.map((solicitud) => {
                                    const estatus = obtenerEstiloEstado(solicitud.estado?.nombre);
                                    const StatusIcon = estatus.icon;
                                    return (
                                        <tr key={solicitud.id} className="border-b theme-border transition-colors hover:bg-black/5 dark:hover:bg-white/5">
                                            <td className="py-6 pl-6">
                                                <div className="font-black text-sm theme-text-main drop-shadow-sm" style={{ color: 'var(--color-primario)' }}>FOL-{solicitud.id}</div>
                                                <div className="text-[10px] font-bold theme-text-muted mt-1 uppercase truncate max-w-[150px]"><User className="w-3 h-3 inline mr-1 opacity-70"/> {solicitud.vendedor?.name}</div>
                                            </td>
                                            <td className="py-6">
                                                <div className="font-bold text-sm theme-text-main uppercase italic truncate max-w-[200px] lg:max-w-xs">{solicitud.cliente?.nombre || 'Nuevo Prospecto'}</div>
                                                <div className="text-[10px] font-bold theme-text-muted mt-1">{solicitud.proceso?.nombre}</div>
                                            </td>
                                            <td className="py-6">
                                                <div className="font-black italic theme-text-main text-sm bg-black/5 dark:bg-white/5 px-3 py-1.5 rounded-lg inline-block border theme-border">
                                                    {new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(solicitud.monto_cotizado)}
                                                </div>
                                                
                                                {solicitud.pago_confirmado ? (
                                                    <div className="mt-2 flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-emerald-500 bg-emerald-500/10 px-2 py-1 rounded-md w-fit border border-emerald-500/20">
                                                        <CheckCircle2 className="w-3 h-3" /> Pago Confirmado
                                                    </div>
                                                ) : (
                                                    <div className="mt-2 flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-amber-500 bg-amber-500/10 px-2 py-1 rounded-md w-fit border border-amber-500/20">
                                                        <Clock className="w-3 h-3" /> Pago Pendiente
                                                    </div>
                                                )}
                                            </td>
                                            <td className="py-6">
                                                <div className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border ${estatus.clase} whitespace-nowrap shadow-sm`}>
                                                    <StatusIcon className="w-4 h-4" />
                                                    <span className="text-[10px] font-black uppercase tracking-wider italic">{estatus.label}</span>
                                                </div>
                                            </td>
                                            <td className="py-6 pr-6 text-center relative">
                                                <button onClick={() => setMenuAbierto(menuAbierto === solicitud.id ? null : solicitud.id)} className="p-3 bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 rounded-2xl transition-all shadow-sm outline-none">
                                                    <MoreVertical className="w-5 h-5 theme-text-main" />
                                                </button>
                                                
                                                {/* MENÚ DESPLEGABLE GELIA */}
                                                {menuAbierto === solicitud.id && (
                                                    <>
                                                        <div className="fixed inset-0 z-40" onClick={() => setMenuAbierto(null)}></div>
                                                        <div className="absolute right-16 top-4 theme-surface border border-zinc-200 dark:border-zinc-700 shadow-2xl rounded-2xl p-2 z-50 w-56 flex flex-col gap-1 text-left animate-fade-in backdrop-blur-xl">
                                                            
                                                            {can('solicitudes.editar') && !solicitud.pago_confirmado && (
                                                                <button onClick={() => confirmarPagoManual(solicitud.id)} className="flex items-center gap-3 px-4 py-3 hover:bg-blue-50 dark:hover:bg-blue-500/10 text-blue-600 dark:text-blue-400 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none transition-colors">
                                                                    <CreditCard className="w-4 h-4" /> Confirmar Pago
                                                                </button>
                                                            )}
                                                            
                                                            {can('solicitudes.verificar') && (
                                                                <button onClick={() => iniciarRespuesta(solicitud, 3)} className="flex items-center gap-3 px-4 py-3 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none transition-colors">
                                                                    <CheckSquare className="w-4 h-4" /> Verificado
                                                                </button>
                                                            )}
                                                            
                                                            {can('solicitudes.reportar') && (
                                                                <>
                                                                    <button onClick={() => iniciarRespuesta(solicitud, 2)} className="flex items-center gap-3 px-4 py-3 hover:bg-black/5 dark:hover:bg-white/5 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none transition-colors" style={{ color: 'var(--color-primario)' }}>
                                                                        <CheckCircle2 className="w-4 h-4" /> Aprobar Proceso
                                                                    </button>
                                                                    <button onClick={() => iniciarRespuesta(solicitud, 4)} className="flex items-center gap-3 px-4 py-3 hover:bg-red-50 dark:hover:bg-red-500/10 text-red-600 dark:text-red-400 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none transition-colors">
                                                                        <AlertOctagon className="w-4 h-4" /> Reportar Error
                                                                    </button>
                                                                </>
                                                            )}
                                                            
                                                            {can('configuracion.ver_auditoria') && (
                                                                <button 
                                                                    onClick={() => {
                                                                        setSolicitudAuditada(solicitud);
                                                                        setMenuAbierto(null);
                                                                        setModalBitacoraAbierto(true);
                                                                    }} 
                                                                    className="flex items-center gap-3 px-4 py-3 hover:bg-purple-50 dark:hover:bg-purple-500/10 text-purple-600 dark:text-purple-400 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none transition-colors border-t theme-border mt-1 pt-3"
                                                                >
                                                                    <History className="w-4 h-4" /> Ver Bitácora
                                                                </button>
                                                            )}
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

            {/* 1. MODAL NUEVA SOLICITUD */}
            {modalAbierto && createPortal(
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md animate-fade-in" onClick={() => setModalAbierto(false)}>
                    <div className="w-full max-w-4xl bg-white dark:bg-[#121212] border border-zinc-200 dark:border-[#222222] shadow-2xl rounded-[2.5rem] p-8 md:p-10 flex flex-col relative modal-pop max-h-[90vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
                        
                        <button onClick={() => setModalAbierto(false)} className="absolute top-5 right-5 p-3 theme-text-muted hover:theme-text-main bg-black/5 dark:bg-white/5 rounded-2xl transition-all outline-none hover:scale-110">
                            <X className="w-5 h-5" />
                        </button>

                        <div className="flex items-center gap-3 mb-8">
                            <Sparkles className="w-6 h-6 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                            <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">Nueva Solicitud_</h2>
                        </div>

                        {alertaHeredado && (
                            <div className="mb-6 p-4 rounded-2xl border-2 border-amber-400 bg-amber-50 dark:bg-amber-500/10 flex gap-4 items-center">
                                <AlertTriangle className="w-6 h-6 text-amber-500 shrink-0" />
                                <p className="text-xs font-bold text-amber-700 dark:text-amber-400 m-0 leading-tight">No puedes seleccionar este proceso porque el cliente está marcado como heredado por la administración.</p>
                            </div>
                        )}

                        <form onSubmit={guardarSolicitud} className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <div className="space-y-6">
                                <div className="space-y-2 relative">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Cliente (Buscador)</label>
                                    <div className="relative">
                                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                        <input 
                                            type="text" value={data.numero_cliente} 
                                            onChange={e => manejarBusquedaCliente(e.target.value)} 
                                            onFocus={() => { if(data.numero_cliente) setMostrarDropdown(true); }}
                                            placeholder="Ingresa nombre o folio..." 
                                            className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all shadow-sm hover:shadow-md" 
                                            autoComplete="off"
                                            onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} 
                                            onBlur={e => e.target.style.borderColor = ''}
                                        />
                                    </div>
                                    
                                    {mostrarDropdown && (
                                        <div className="absolute top-[100%] mt-2 left-0 right-0 theme-surface border theme-border rounded-2xl shadow-2xl z-50 max-h-60 overflow-y-auto custom-scrollbar p-2">
                                            {buscandoCliente ? (
                                                <div className="p-6 text-center text-xs font-bold theme-text-muted animate-pulse italic">Consultando directorio...</div>
                                            ) : listaClientes.map(c => (
                                                <div key={c.id} onClick={() => seleccionarCliente(c)} className="p-3 rounded-xl hover:bg-black/5 dark:hover:bg-white/5 cursor-pointer transition-colors flex justify-between items-center group mb-1">
                                                    <div>
                                                        <p className="text-xs font-black uppercase theme-text-main group-hover:text-[var(--color-primario)] transition-colors">{c.numero_cliente} - {c.nombre}</p>
                                                        <p className="text-[9px] theme-text-muted font-bold mt-1 uppercase tracking-widest">Lista: {c.lista_actual}</p>
                                                    </div>
                                                    {c.es_heredado && <ShieldCheck className="w-4 h-4 text-amber-500" title="Cliente Heredado" />}
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Cotización Autorizada</label>
                                    <div className="relative">
                                        <CreditCard className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                        <input 
                                            type="number" step="0.01" required value={data.monto_cotizado} 
                                            onChange={e => setData('monto_cotizado', e.target.value)} 
                                            className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all shadow-sm hover:shadow-md"
                                            onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} 
                                            onBlur={e => e.target.style.borderColor = ''}
                                        />
                                    </div>
                                </div>
                                
                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Proceso a Ejecutar</label>
                                    <div className="relative">
                                        <FileSignature className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                                        <select 
                                            value={data.catalogo_proceso_id} required
                                            onChange={e => setData('catalogo_proceso_id', e.target.value)} 
                                            className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none appearance-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all shadow-sm hover:shadow-md cursor-pointer"
                                            onFocus={e => e.target.style.borderColor = 'var(--color-primario)'} 
                                            onBlur={e => e.target.style.borderColor = ''}
                                        >
                                            <option value="">Seleccionar del catálogo...</option>
                                            {procesos.map(p => <option key={p.id} value={p.id}>{p.nombre}</option>)}
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-6 flex flex-col justify-between">
                                <div className="space-y-2 h-full flex flex-col">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Evidencia (Recibo/Ticket)</label>
                                    <label className="flex-1 flex flex-col items-center justify-center min-h-[150px] border-2 border-dashed theme-border rounded-xl cursor-pointer theme-element hover:bg-black/5 dark:hover:bg-white/5 transition-all group" style={{ borderColor: data.evidencia ? 'var(--color-primario)' : '' }}>
                                        <Upload className="w-8 h-8 mb-3 theme-text-muted group-hover:scale-110 transition-transform" style={{ color: data.evidencia ? 'var(--color-primario)' : '' }} />
                                        <p className="text-[10px] theme-text-main uppercase font-black px-6 text-center truncate w-full">{data.evidencia ? data.evidencia.name : 'Click para subir archivo_'}</p>
                                        <input type="file" className="hidden" accept="image/*,.pdf" onChange={e => setData('evidencia', e.target.files[0])} />
                                    </label>
                                </div>

                                <button type="submit" disabled={processing} className="w-full py-4 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] shadow-xl hover:scale-[1.03] transition-all disabled:opacity-50 disabled:cursor-not-allowed outline-none flex justify-center items-center gap-2" style={{ backgroundColor: 'var(--color-primario)' }}>
                                    <Send className="w-4 h-4" /> {processing ? 'Procesando...' : 'Transmitir Solicitud'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>,
                document.body
            )}

            {/* 2. MODAL ACTUALIZAR ESTADO */}
            {modalRespuestaAbierto && createPortal(
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md animate-fade-in" onClick={() => setModalRespuestaAbierto(false)}>
                    <div className="w-full max-w-lg bg-white dark:bg-[#121212] border border-zinc-200 dark:border-[#222222] shadow-2xl rounded-[2.5rem] p-8 flex flex-col relative modal-pop" onClick={e => e.stopPropagation()}>
                        
                        <button onClick={() => setModalRespuestaAbierto(false)} className="absolute top-5 right-5 p-3 theme-text-muted hover:theme-text-main bg-black/5 dark:bg-white/5 rounded-2xl transition-all outline-none hover:scale-110">
                            <X className="w-5 h-5" />
                        </button>

                        <div className="flex items-center gap-3 mb-6">
                            <Edit2 className="w-6 h-6 drop-shadow-sm" style={{ color: 'var(--color-primario)' }} />
                            <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">Actualizar Estado_</h2>
                        </div>

                        <form onSubmit={enviarRespuesta} className="space-y-6">
                            <div className="p-4 rounded-xl border flex items-start gap-3" 
                                style={{ 
                                    backgroundColor: formRespuesta.data.catalogo_estado_solicitud_id === 4 ? 'rgba(239, 68, 68, 0.1)' : 'rgba(16, 185, 129, 0.1)',
                                    borderColor: formRespuesta.data.catalogo_estado_solicitud_id === 4 ? 'rgba(239, 68, 68, 0.3)' : 'rgba(52, 211, 153, 0.3)'
                                }}>
                                {formRespuesta.data.catalogo_estado_solicitud_id === 4 ? <AlertOctagon className="w-5 h-5 text-red-500 mt-0.5" /> : <Info className="w-5 h-5 text-emerald-500 mt-0.5" />}
                                <div>
                                    <p className="text-xs font-black uppercase theme-text-main leading-tight" style={{ color: formRespuesta.data.catalogo_estado_solicitud_id === 4 ? '#ef4444' : '#10b981' }}>
                                        {formRespuesta.data.catalogo_estado_solicitud_id === 4 ? 'Reporte de Inconsistencia' : 'Aprobación / Verificación'}
                                    </p>
                                    <p className="text-[10px] font-bold theme-text-muted mt-1 uppercase">Esta acción quedará registrada en la bitácora.</p>
                                </div>
                            </div>

                            {formRespuesta.data.catalogo_estado_solicitud_id === 4 && (
                                <div className="space-y-2">
                                    <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1 text-red-500">Motivo del Reporte</label>
                                    <textarea 
                                        required rows="4" value={formRespuesta.data.motivo} onChange={e => formRespuesta.setData('motivo', e.target.value)}
                                        className="w-full p-4 theme-surface border border-red-300 dark:border-red-900/50 rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 focus:ring-red-500 transition-all shadow-sm custom-scrollbar"
                                    ></textarea>
                                </div>
                            )}

                            <button type="submit" disabled={formRespuesta.processing} className="w-full py-4 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] shadow-xl hover:scale-[1.03] transition-all disabled:opacity-50 outline-none" style={{ backgroundColor: formRespuesta.data.catalogo_estado_solicitud_id === 4 ? '#ef4444' : 'var(--color-primario)' }}>
                                {formRespuesta.processing ? 'Registrando...' : 'Confirmar Acción'}
                            </button>
                        </form>
                    </div>
                </div>,
                document.body
            )}

            {/* 3. MODAL DE BITÁCORA DE AUDITORÍA */}
            {modalBitacoraAbierto && createPortal(
                <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md animate-fade-in" onClick={() => setModalBitacoraAbierto(false)}>
                    <div className="w-full max-w-2xl bg-white dark:bg-[#121212] border border-zinc-200 dark:border-[#222222] shadow-2xl rounded-[2.5rem] p-8 flex flex-col relative modal-pop max-h-[85vh] overflow-hidden" onClick={e => e.stopPropagation()}>
                        
                        <button onClick={() => setModalBitacoraAbierto(false)} className="absolute top-5 right-5 p-3 theme-text-muted hover:theme-text-main bg-black/5 dark:bg-white/5 rounded-2xl transition-all outline-none hover:scale-110 z-10">
                            <X className="w-5 h-5" />
                        </button>

                        <div className="flex items-center gap-3 mb-6 shrink-0">
                            <History className="w-6 h-6 text-purple-500 drop-shadow-sm" />
                            <div>
                                <h2 className="text-xl font-black italic theme-text-main uppercase tracking-tighter m-0 drop-shadow-sm">Bitácora de Auditoría_</h2>
                                <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1">Folio de Solicitud: FOL-{solicitudAuditada?.id}</p>
                            </div>
                        </div>

                        <div className="overflow-y-auto custom-scrollbar flex-1 pr-2 space-y-4 relative before:absolute before:inset-0 before:ml-4 before:-translate-x-px md:before:mx-auto md:before:translate-x-0 before:h-full before:w-0.5 before:bg-gradient-to-b before:from-transparent before:via-zinc-300 dark:before:via-zinc-700 before:to-transparent">
                            {solicitudAuditada?.auditorias && solicitudAuditada.auditorias.length > 0 ? (
                                solicitudAuditada.auditorias.map((registro, idx) => (
                                    <div key={idx} className="relative flex items-center justify-between md:justify-normal md:odd:flex-row-reverse group is-active">
                                        <div className="flex items-center justify-center w-8 h-8 rounded-full border-4 border-white dark:border-[#121212] bg-purple-500 text-white shadow shrink-0 md:order-1 md:group-odd:-translate-x-1/2 md:group-even:translate-x-1/2 z-10">
                                            <ShieldCheck className="w-3 h-3" />
                                        </div>
                                        <div className="w-[calc(100%-4rem)] md:w-[calc(50%-2rem)] theme-surface border theme-border p-4 rounded-2xl shadow-sm">
                                            <div className="flex items-center justify-between mb-2">
                                                <span className="font-black text-[10px] text-purple-600 dark:text-purple-400 uppercase tracking-widest">{registro.usuario?.name || 'Sistema'}</span>
                                                <span className="text-[9px] font-bold theme-text-muted uppercase">{new Date(registro.created_at).toLocaleDateString()}</span>
                                            </div>
                                            <p className="text-xs font-bold theme-text-main mb-1">
                                                Cambio a: <span className="italic">{registro.estado_nuevo?.nombre || `Estado ID: ${registro.estado_nuevo_id}`}</span>
                                            </p>
                                            {registro.motivo_reporte && (
                                                <div className="mt-2 p-3 rounded-xl bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20">
                                                    <p className="text-[10px] font-bold text-red-700 dark:text-red-400 m-0 uppercase tracking-widest leading-tight">Motivo: {registro.motivo_reporte}</p>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="text-center py-10 relative z-10">
                                    <p className="text-xs font-bold uppercase text-zinc-500 italic tracking-widest bg-white dark:bg-[#121212] px-4 py-2 rounded-xl inline-block shadow-sm border theme-border">Sin registros en la matriz de auditoría_</p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>,
                document.body
            )}
            
        </AppLayout>
    );
}