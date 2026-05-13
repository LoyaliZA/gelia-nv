import React, { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { animate } from 'animejs/animation';
import {
    Clock, Plus, MoreVertical, Edit2, CheckCircle2, AlertOctagon, 
    Search, History, CheckSquare, CreditCard, User, Copy, Check, Tag, TrendingUp, ShieldAlert, Users
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaLoader from '../../Components/GeliaLoader';

// Modales extraídos
import ModalFormSolicitud from './Partials/ModalFormSolicitud';
import ModalRespuestaSolicitud from './Partials/ModalRespuestaSolicitud';
import ModalBitacoraSolicitud from './Partials/ModalBitacoraSolicitud';

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

export default function Index({ solicitudes = { total: 0, data: [] }, procesos = [], listas = [], tipos_cliente = [], auth }) {

    const [modalForm, setModalForm] = useState({ abierto: false, modoEdicion: false, solicitud: null });
    const [modalRespuesta, setModalRespuesta] = useState({ abierto: false, solicitud: null, estadoId: null });
    const [modalBitacora, setModalBitacora] = useState({ abierto: false, solicitud: null });

    const [menuAbierto, setMenuAbierto] = useState(null);
    const [tabActiva, setTabActiva] = useState('TODAS');
    const [busqueda, setBusqueda] = useState('');
    const [copiadoId, setCopiadoId] = useState(null);
    const [procesandoAccion, setProcesandoAccion] = useState(false);

    const can = (permiso) => auth?.user?.permissions?.includes(permiso) || auth?.user?.roles?.includes('Admin') || auth?.user?.roles?.includes('Super admin (admin)');

    useEffect(() => {
        const interval = setInterval(() => {
            router.reload({ only: ['solicitudes'], preserveState: true, preserveScroll: true });
        }, 10000);
        return () => clearInterval(interval);
    }, []);

    useEffect(() => { animate('.page-reveal-solicitudes', { translateY: [15, 0], opacity: [0, 1] }, { easing: 'easeOutExpo', duration: 600, delay: (el, i) => i * 80 }); }, []);

    const copiarAlPortapapeles = (e, texto, id) => {
        e.stopPropagation();
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(texto);
        } else {
            const textArea = document.createElement("textarea");
            textArea.value = texto; textArea.style.position = "fixed"; textArea.style.left = "-999999px"; textArea.style.top = "-999999px";
            document.body.appendChild(textArea); textArea.focus(); textArea.select();
            try { document.execCommand('copy'); } catch (error) { console.error('Error al copiar:', error); }
            textArea.remove();
        }
        setCopiadoId(id); setTimeout(() => setCopiadoId(null), 2000);
    };

    const confirmarPagoManual = (id) => { 
        setMenuAbierto(null); setProcesandoAccion(true);
        router.put(route('solicitudes.confirmar_pago', id), {}, { onFinish: () => setProcesandoAccion(false) }); 
    };

    const solicitudesFiltradas = (solicitudes.data || []).filter(solicitud => { 
        const cumpleTab = tabActiva === 'TODAS' || (tabActiva === 'PENDIENTES' && solicitud.estado?.nombre === 'Pendiente') || (tabActiva === 'RESPONDIDAS' && solicitud.estado?.nombre === 'Respondida') || (tabActiva === 'INCORRECTAS' && solicitud.estado?.nombre === 'Incorrecta'); 
        const idString = solicitud.id ? solicitud.id.toString() : ''; const nombreCliente = solicitud.cliente?.nombre || ''; const numeroCliente = solicitud.cliente?.numero_cliente || ''; 
        const cumpleBusqueda = busqueda === '' || idString.includes(busqueda) || nombreCliente.toLowerCase().includes(busqueda.toLowerCase()) || numeroCliente.includes(busqueda); 
        return cumpleTab && cumpleBusqueda; 
    });

    const obtenerEstiloEstado = (nombreEstado) => { 
        switch (nombreEstado?.toLowerCase()) { 
            case 'respondida': return { clase: 'status-aprobado', icon: CheckCircle2, label: 'Aprobado (TAGS)' }; 
            case 'incorrecta': return { clase: 'status-incidencia', icon: AlertOctagon, label: 'Reporte (Error)' }; 
            case 'verificada': return { clase: 'status-verificado', icon: CheckSquare, label: 'Verificada (Auxiliar)' }; 
            default: return { clase: 'status-revision', icon: Clock, label: 'Pendiente' }; 
        } 
    };

    // Componente reutilizable para el Menú de 3 puntos
    const MenuAcciones = ({ solicitud }) => (
        <div className="absolute right-4 lg:right-10 top-12 lg:top-14 theme-surface border theme-border shadow-2xl rounded-2xl p-2 z-50 w-56 flex flex-col gap-1 text-left animate-fade-in backdrop-blur-xl">
            {solicitud.vendedor_id === auth.user.id && solicitud.estado?.nombre === 'Incorrecta' && (
                <button onClick={() => { setMenuAbierto(null); setModalForm({ abierto: true, modoEdicion: true, solicitud: solicitud }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-orange-50 dark:hover:bg-orange-500/10 text-orange-600 dark:text-orange-400 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none transition-colors border-b theme-border mb-1 pb-3"><Edit2 className="w-4 h-4" /> Reparar Solicitud</button>
            )}
            {(can('solicitudes.confirmar_pago') || solicitud.vendedor_id === auth.user.id) && !solicitud.pago_confirmado && solicitud.estado?.nombre !== 'Incorrecta' && (
                <button onClick={() => confirmarPagoManual(solicitud.id)} className="flex items-center gap-3 px-4 py-3 hover:bg-blue-50 dark:hover:bg-blue-500/10 text-blue-600 dark:text-blue-400 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none transition-colors border-b theme-border mb-1 pb-3"><CreditCard className="w-4 h-4" /> Confirmar Pago</button>
            )}
            {can('solicitudes.verificar') && (<button onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud: solicitud, estadoId: 3 }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none transition-colors"><CheckSquare className="w-4 h-4" /> Verificado</button>)}
            {can('solicitudes.reportar') && (
                <>
                    <button onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud: solicitud, estadoId: 2 }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-black/5 dark:hover:bg-white/5 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none transition-colors" style={{ color: 'var(--color-primario)' }}><CheckCircle2 className="w-4 h-4" /> Aprobar Proceso</button>
                    <button onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud: solicitud, estadoId: 4 }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-red-50 dark:hover:bg-red-500/10 text-red-600 dark:text-red-400 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none transition-colors"><AlertOctagon className="w-4 h-4" /> Reportar Error</button>
                </>
            )}
            {can('configuracion.ver_auditoria') && (<button onClick={() => { setMenuAbierto(null); setModalBitacora({ abierto: true, solicitud: solicitud }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-purple-50 dark:hover:bg-purple-500/10 text-purple-600 dark:text-purple-400 rounded-xl text-[10px] font-black uppercase tracking-widest outline-none transition-colors border-t theme-border mt-1 pt-3"><History className="w-4 h-4" /> Ver Bitácora</button>)}
        </div>
    );

    const activeCardClass = "page-reveal-solicitudes theme-surface rounded-[2.5rem] relative z-10 transition-all duration-300 border theme-border shadow-[0_12px_40px_rgba(0,0,0,0.08)] dark:shadow-[0_12px_40px_rgba(0,0,0,0.5)] bg-white/75 dark:bg-[#121212]/75 backdrop-blur-[24px]";

    return (
        <AppLayout auth={auth}>
            <Head title="Panel de Solicitudes | GELIANV" />
            <style>{ESTILOS_GLOBALES}</style>
            <GeliaLoader isVisible={procesandoAccion} message="Procesando Acción_" />

            <div className="max-w-[1400px] w-full mx-auto p-4 md:p-8 space-y-8 relative">
                
                {/* --- HEADER --- */}
                <header className={`${activeCardClass} p-8 md:p-12 flex flex-col md:flex-row items-center justify-between gap-6`}>
                    <div>
                        <div className="flex items-center space-x-3 mb-2"><span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }}></span><p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>Central Infrastructure</p></div>
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main m-0 p-0">PANEL DE <span style={{ color: 'var(--color-primario)' }}>SOLICITUDES</span></h1>
                    </div>
                    {can('solicitudes.crear') && (
                        <button onClick={() => setModalForm({ abierto: true, modoEdicion: false, solicitud: null })} className="flex items-center justify-center gap-2 px-8 py-4 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] shadow-xl hover:scale-105 transition-all w-full md:w-auto outline-none" style={{ backgroundColor: 'var(--color-primario)' }}><Plus className="w-5 h-5" /> Nueva Solicitud</button>
                    )}
                </header>

                {/* --- FILTROS --- */}
                <div className="page-reveal-solicitudes flex flex-col lg:flex-row gap-6 items-start lg:items-center justify-between">
                    <div className="gelia-segment w-full lg:w-auto p-1 h-14 shadow-sm overflow-x-auto">
                        {['TODAS', 'PENDIENTES', 'RESPONDIDAS', 'INCORRECTAS'].map(tab => (
                            <button key={tab} type="button" onClick={() => setTabActiva(tab)} className="gelia-segment-btn px-6 min-w-max" data-active={tabActiva === tab}>{tab}</button>
                        ))}
                    </div>
                    <div className="relative w-full lg:w-96 shrink-0">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted z-10 pointer-events-none" />
                        <input type="text" placeholder="Buscar folio o No. cliente..." value={busqueda} onChange={e => setBusqueda(e.target.value)} className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 focus:ring-[var(--color-primario)] transition-all shadow-sm hover:shadow-md" />
                    </div>
                </div>

                {/* --- CONTENEDOR DUAL (ESCRITORIO = TABLA | MÓVIL = TARJETAS) --- */}
                <div className={`${activeCardClass} p-4 lg:p-8 w-full`}>
                    
                    {solicitudesFiltradas.length === 0 && (
                        <div className="text-center py-16 text-zinc-500 font-bold uppercase text-[11px] tracking-widest italic border-b theme-border mb-8">No se encontraron registros en esta frecuencia.</div>
                    )}

                    {/* VISTA ESCRITORIO: TABLA HTML CLÁSICA */}
                    <div className="hidden lg:block overflow-x-auto pb-32 custom-scrollbar">
                        <table className="w-full text-left border-collapse min-w-[900px]">
                            <thead>
                                <tr className="border-b-2 theme-border relative">
                                    <th className="pb-6 pl-6 pt-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Folio & Asesor_</th>
                                    <th className="pb-6 pt-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Cliente_</th>
                                    <th className="pb-6 pt-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Proceso y Detalles_</th>
                                    <th className="pb-6 pt-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Cotización_</th>
                                    <th className="pb-6 pt-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">Estado_</th>
                                    {/* Columna Sticky para asegurar visibilidad en pantallas chicas */}
                                    <th className="pb-6 pt-4 pr-6 text-[10px] font-black uppercase tracking-widest theme-text-muted text-center sticky right-0 bg-white/90 dark:bg-[#121212]/90 backdrop-blur-sm shadow-[-10px_0_15px_-3px_rgba(0,0,0,0.05)] z-20">Acciones_</th>
                                </tr>
                            </thead>
                            <tbody>
                                {solicitudesFiltradas.map((solicitud) => {
                                    const estatus = obtenerEstiloEstado(solicitud.estado?.nombre);
                                    const StatusIcon = estatus.icon;
                                    const nombreProceso = solicitud.proceso?.nombre || '';
                                    const esTag = nombreProceso.toUpperCase().includes('TAG');
                                    const esCambioLista = nombreProceso.toUpperCase().includes('LISTA');
                                    const esHeredado = solicitud.cliente?.es_heredado;
                                    const nombreVendedoraCorto = solicitud.vendedor?.name?.split(' ').slice(0, 2).join(' ') || 'Vendedora';
                                    
                                    // Normalización de relaciones (Soporte para snake_case o camelCase según tu API)
                                    const objLista = solicitud.lista_descuento || solicitud.listaDescuento;
                                    const objTipoCliente = solicitud.tipo_cliente || solicitud.tipoCliente;

                                    return (
                                        <tr key={`desk-${solicitud.id}`} className="border-b theme-border transition-colors hover:bg-black/5 dark:hover:bg-white/5 relative">
                                            <td className="py-6 pl-6">
                                                <div className="font-black text-sm theme-text-main drop-shadow-sm" style={{ color: 'var(--color-primario)' }}>FOL-{solicitud.id}</div>
                                                <div className="text-[10px] font-bold theme-text-muted mt-1 uppercase truncate max-w-[150px]"><User className="w-3 h-3 inline mr-1 opacity-70" /> {solicitud.vendedor?.name}</div>
                                            </td>
                                            <td className="py-6 min-w-[250px] pr-4">
                                                <div className="flex items-center gap-2 mb-1">
                                                    <span className="text-[10px] font-black px-2 py-0.5 rounded bg-black/5 dark:bg-white/5 border theme-border theme-text-main">{solicitud.cliente?.numero_cliente || 'N/A'}</span>
                                                    {solicitud.cliente?.numero_cliente && (
                                                        <button onClick={(e) => copiarAlPortapapeles(e, solicitud.cliente.numero_cliente, solicitud.id)} className="p-1 theme-text-muted hover:text-[var(--color-primario)] transition-colors outline-none" title="Copiar ID">{copiadoId === solicitud.id ? <Check className="w-3 h-3 text-emerald-500" /> : <Copy className="w-3 h-3" />}</button>
                                                    )}
                                                    {esHeredado && <span className="text-[9px] font-black uppercase bg-purple-500/10 text-purple-500 border border-purple-500/20 px-2 py-0.5 rounded flex items-center gap-1"><ShieldAlert className="w-3 h-3" /> Heredado</span>}
                                                </div>
                                                <div className="font-bold text-sm theme-text-main uppercase italic truncate lg:max-w-xs">{solicitud.cliente?.nombre || 'Nuevo Prospecto'}</div>
                                            </td>
                                            <td className="py-6 pr-4">
                                                <div className="inline-block px-3 py-1.5 rounded-lg theme-element border theme-border text-[9px] font-black uppercase tracking-widest theme-text-main shadow-sm mb-2">{nombreProceso}</div>
                                                <div className="flex flex-wrap gap-1.5">
                                                    {esCambioLista && objLista && <span className="text-[9px] font-black uppercase px-2 py-1 rounded-md bg-blue-500/10 text-blue-500 border border-blue-500/20 flex items-center gap-1"><TrendingUp className="w-3 h-3" /> Ascenso a: {objLista.nombre}</span>}
                                                    {esTag && <span className="text-[9px] font-black uppercase px-2 py-1 rounded-md bg-amber-500/10 text-amber-500 border border-amber-500/20 flex items-center gap-1"><Tag className="w-3 h-3" /> TAG: {nombreVendedoraCorto}</span>}
                                                    {objTipoCliente && <span className="text-[9px] font-black uppercase px-2 py-1 rounded-md bg-indigo-500/10 text-indigo-500 border border-indigo-500/20 flex items-center gap-1"><Users className="w-3 h-3" /> {objTipoCliente.nombre}</span>}
                                                </div>
                                            </td>
                                            <td className="py-6 pr-4">
                                                <div className="font-black italic theme-text-main text-sm bg-black/5 dark:bg-white/5 px-3 py-1.5 rounded-lg inline-block border theme-border">{new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(solicitud.monto_cotizado)}</div>
                                                {solicitud.pago_confirmado ? (
                                                    <div className="mt-2 flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-emerald-500 bg-emerald-500/10 px-2 py-1 rounded-md w-fit border border-emerald-500/20"><CheckCircle2 className="w-3 h-3" /> Pago Confirmado</div>
                                                ) : (
                                                    <div className="mt-2 flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-amber-500 bg-amber-500/10 px-2 py-1 rounded-md w-fit border border-amber-500/20"><Clock className="w-3 h-3" /> Pago Pendiente</div>
                                                )}
                                            </td>
                                            <td className="py-6 pr-4"><div className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border ${estatus.clase} whitespace-nowrap shadow-sm`}><StatusIcon className="w-4 h-4" /><span className="text-[10px] font-black uppercase tracking-wider italic">{estatus.label}</span></div></td>
                                            
                                            {/* Columna Sticky aplicada al TD */}
                                            <td className="py-6 pr-6 text-center sticky right-0 bg-white dark:bg-[#121212] shadow-[-10px_0_15px_-3px_rgba(0,0,0,0.05)] z-20">
                                                <button onClick={() => setMenuAbierto(menuAbierto === solicitud.id ? null : solicitud.id)} className="p-3 theme-element border theme-border hover:border-[var(--color-primario)] rounded-2xl transition-all shadow-sm outline-none relative z-30"><MoreVertical className="w-5 h-5 theme-text-main" /></button>
                                                {menuAbierto === solicitud.id && (
                                                    <>
                                                        <div className="fixed inset-0 z-40" onClick={() => setMenuAbierto(null)}></div>
                                                        <div className="absolute right-full top-1/2 -translate-y-1/2 mr-2">
                                                            <MenuAcciones solicitud={solicitud} />
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

                    {/* VISTA MÓVIL: TARJETAS FLEX */}
                    <div className="flex flex-col gap-4 lg:hidden pb-10">
                        {solicitudesFiltradas.map((solicitud) => {
                            const estatus = obtenerEstiloEstado(solicitud.estado?.nombre);
                            const StatusIcon = estatus.icon;
                            const nombreProceso = solicitud.proceso?.nombre || '';
                            const esTag = nombreProceso.toUpperCase().includes('TAG');
                            const esCambioLista = nombreProceso.toUpperCase().includes('LISTA');
                            const esHeredado = solicitud.cliente?.es_heredado;
                            const nombreVendedoraCorto = solicitud.vendedor?.name?.split(' ').slice(0, 2).join(' ') || 'Vendedora';

                            return (
                                <div key={`mob-${solicitud.id}`} className="flex flex-col gap-3 p-5 border theme-border rounded-2xl bg-black/5 dark:bg-[#18181b]/50 relative">
                                    <div className="flex justify-between items-start">
                                        <div>
                                            <div className="font-black text-sm theme-text-main drop-shadow-sm" style={{ color: 'var(--color-primario)' }}>FOL-{solicitud.id}</div>
                                            <div className="text-[10px] font-bold theme-text-muted mt-1 uppercase truncate"><User className="w-3 h-3 inline mr-1 opacity-70" /> {solicitud.vendedor?.name}</div>
                                        </div>
                                        <div className={`inline-flex items-center gap-1.5 px-2 py-1 rounded-lg border ${estatus.clase} shadow-sm`}><StatusIcon className="w-3 h-3" /><span className="text-[9px] font-black uppercase tracking-wider italic">{estatus.label}</span></div>
                                    </div>

                                    <div className="border-t theme-border pt-3">
                                        <div className="flex flex-wrap items-center gap-2 mb-1">
                                            <span className="text-[10px] font-black px-2 py-0.5 rounded bg-white dark:bg-black/50 border theme-border theme-text-main">{solicitud.cliente?.numero_cliente || 'N/A'}</span>
                                            {esHeredado && <span className="text-[9px] font-black uppercase bg-purple-500/10 text-purple-500 border border-purple-500/20 px-2 py-0.5 rounded flex items-center gap-1"><ShieldAlert className="w-3 h-3" /> Heredado</span>}
                                        </div>
                                        <div className="font-bold text-sm theme-text-main uppercase italic">{solicitud.cliente?.nombre || 'Nuevo Prospecto'}</div>
                                    </div>

                                    <div className="flex flex-col gap-2 border-t theme-border pt-3">
                                        <div className="inline-block px-3 py-1 rounded-md bg-white dark:bg-black/50 border theme-border text-[9px] font-black uppercase tracking-widest theme-text-main w-fit">{nombreProceso}</div>
                                        <div className="flex flex-wrap gap-1.5">
                                            {esCambioLista && solicitud.lista_descuento && <span className="text-[9px] font-black uppercase px-2 py-1 rounded-md bg-blue-500/10 text-blue-500 border border-blue-500/20 flex items-center gap-1"><TrendingUp className="w-3 h-3" /> A: {solicitud.lista_descuento.nombre}</span>}
                                            {esTag && <span className="text-[9px] font-black uppercase px-2 py-1 rounded-md bg-amber-500/10 text-amber-500 border border-amber-500/20 flex items-center gap-1"><Tag className="w-3 h-3" /> TAG: {nombreVendedoraCorto}</span>}
                                            {solicitud.tipo_cliente && <span className="text-[9px] font-black uppercase px-2 py-1 rounded-md bg-indigo-500/10 text-indigo-500 border border-indigo-500/20 flex items-center gap-1"><Users className="w-3 h-3" /> {solicitud.tipo_cliente.nombre}</span>}
                                        </div>
                                    </div>

                                    <div className="flex justify-between items-center border-t theme-border pt-3 mt-1">
                                        <div className="flex flex-col gap-1">
                                            <div className="font-black italic theme-text-main text-sm">{new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(solicitud.monto_cotizado)}</div>
                                            {solicitud.pago_confirmado ? (
                                                <div className="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-emerald-500"><CheckCircle2 className="w-3 h-3" /> Pago Confirmado</div>
                                            ) : (
                                                <div className="flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-amber-500"><Clock className="w-3 h-3" /> Pago Pendiente</div>
                                            )}
                                        </div>
                                        <div className="relative">
                                            <button onClick={() => setMenuAbierto(menuAbierto === solicitud.id ? null : solicitud.id)} className="p-2 theme-element border theme-border hover:border-[var(--color-primario)] rounded-xl transition-all shadow-sm outline-none bg-white dark:bg-[#18181b] relative z-20"><MoreVertical className="w-5 h-5 theme-text-main" /></button>
                                            {menuAbierto === solicitud.id && (
                                                <>
                                                    <div className="fixed inset-0 z-40" onClick={() => setMenuAbierto(null)}></div>
                                                    <MenuAcciones solicitud={solicitud} />
                                                </>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>

            {/* --- SECCIÓN 4: MODALES --- */}
            {modalForm.abierto && <ModalFormSolicitud onClose={() => setModalForm({ ...modalForm, abierto: false })} procesos={procesos} listas={listas} tiposCliente={tipos_cliente} modoEdicion={modalForm.modoEdicion} solicitudAEditar={modalForm.solicitud} />}
            {modalRespuesta.abierto && <ModalRespuestaSolicitud onClose={() => setModalRespuesta({ ...modalRespuesta, abierto: false })} solicitud={modalRespuesta.solicitud} estadoId={modalRespuesta.estadoId} />}
            {modalBitacora.abierto && <ModalBitacoraSolicitud onClose={() => setModalBitacora({ ...modalBitacora, abierto: false })} solicitud={modalBitacora.solicitud} />}

        </AppLayout>
    );
}