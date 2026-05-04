import React, { useEffect, useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { animate } from 'animejs';
import { 
    Clock, Sparkles, Send, 
    AlertCircle, ShieldCheck, Info, 
    Plus, MoreVertical, Edit2, AlertTriangle, 
    CheckCircle2, XCircle, FileText, X,
    DollarSign, AlertOctagon, Wallet, Search,
    Filter, History, CheckSquare, CreditCard,
    FileSpreadsheet, Download
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function Index({ solicitudes = { total: 0, data: [] }, procesos = [], auth, filtros = {} }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [menuAbierto, setMenuAbierto] = useState(null);
    const [solicitudAEditar, setSolicitudAEditar] = useState(null);

    const { data, setData, post, processing, reset } = useForm({
        numero_cliente: '',
        monto_cotizado: '',
        catalogo_proceso_id: '',
        observaciones_vendedor: '',
    });

    // Helper para verificar permisos granulares
    const can = (permiso) => auth?.user?.permissions?.includes(permiso);

    useEffect(() => {
        animate('.tab-content', {
            translateY: [10, 0],
            opacity: [0, 1],
            easing: 'easeOutExpo',
            duration: 600,
            delay: (el, i) => i * 100
        });
    }, []);

    const abrirModalNuevo = () => {
        reset();
        setModalAbierto(true);
    };

    const cerrarModal = () => {
        reset();
        setModalAbierto(false);
    };

    // Funciones de acción conectadas al backend
    const confirmarPago = (solicitudId) => {
        if(confirm("¿Estás seguro de confirmar el pago para esta solicitud?")) {
            router.put(route('solicitudes.confirmar_pago', solicitudId), {}, {
                preserveScroll: true,
                onSuccess: () => setMenuAbierto(null),
            });
        }
    };

    const cambiarEstado = (solicitudId, estadoId, motivo = null) => {
        const payload = { catalogo_estado_solicitud_id: estadoId };
        if (motivo) payload.motivo = motivo;

        router.put(route('solicitudes.actualizar_estado', solicitudId), payload, {
            preserveScroll: true,
            onSuccess: () => setMenuAbierto(null),
        });
    };

    const reportarInconsistencia = (solicitudId) => {
        const motivo = prompt("Describe la inconsistencia encontrada:");
        if (motivo) {
            // Suponiendo que el ID 4 es "Incorrecta" en tu BD
            cambiarEstado(solicitudId, 4, motivo);
        }
    };

    const iniciarEdicion = (solicitud) => {
        setMenuAbierto(null);
        setSolicitudAEditar(solicitud);
    };

    const confirmarEdicion = () => {
        setData({
            numero_cliente: solicitudAEditar.cliente?.numero_cliente || '',
            monto_cotizado: solicitudAEditar.monto_cotizado,
            catalogo_proceso_id: solicitudAEditar.catalogo_proceso_id,
            observaciones_vendedor: solicitudAEditar.observaciones_vendedor || '',
        });
        setSolicitudAEditar(null);
        setModalAbierto(true);
    };

    const guardarSolicitud = (e) => {
        e.preventDefault();
        post(route('solicitudes.store'), {
            onSuccess: () => cerrarModal()
        });
    }

    const exportarReporte = (formato) => {
        // Redirige al endpoint de exportación con los filtros actuales
        const params = new URLSearchParams(filtros).toString();
        window.location.href = `${route('solicitudes.exportar')}?${params}&formato=${formato}`;
    };


    const obtenerEstiloEstado = (nombreEstado) => {
        switch(nombreEstado?.toLowerCase()) {
            case 'respondida': return { clase: 'status-aprobado', icon: CheckCircle2, label: 'Aprobado (TAGS)' };
            case 'incorrecta': return { clase: 'status-incidencia', icon: AlertOctagon, label: 'Reporte (Inconsistencia)' };
            case 'verificada': return { clase: 'status-verificado', icon: CheckSquare, label: 'Verificada (Auxiliar)' };
            default: return { clase: 'status-revision', icon: Clock, label: 'Pendiente' };
        }
    };

    // Usaremos los datos reales que vienen de la paginación del backend
    const listaSolicitudes = solicitudes.data || [];

    return (
        <AppLayout auth={auth}>
            <Head title="Panel de Solicitudes | GELIANV" />

            {/* --- MODAL 1: ALERTA DE AUDITORÍA --- */}
            {solicitudAEditar && (
                <div className="fixed inset-0 z-[60] flex items-center justify-center theme-overlay p-4 transition-all">
                    <div className="theme-surface border-2 theme-border p-8 rounded-[3rem] shadow-2xl max-w-md w-full tab-content relative overflow-hidden">
                        <div className="w-14 h-14 bg-amber-500/10 rounded-2xl flex items-center justify-center mb-6 border border-amber-500/20">
                            <AlertTriangle className="w-6 h-6 text-amber-500" />
                        </div>
                        <h3 className="text-2xl font-black italic theme-text-main uppercase tracking-tighter mb-3">Aviso de Seguridad</h3>
                        <p className="text-sm font-bold theme-text-muted italic mb-8 leading-relaxed">
                            Estás a punto de modificar la solicitud <span className="text-pink-500 font-black">{solicitudAEditar.id}</span>. Cualquier alteración a este folio quedará <span className="theme-text-main">registrada en la bitácora</span> para auditoría.
                        </p>
                        <div className="flex gap-4">
                            <button onClick={() => setSolicitudAEditar(null)} className="flex-1 py-4 theme-element theme-hover-bg theme-text-main border theme-border rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all">
                                Cancelar
                            </button>
                            <button onClick={confirmarEdicion} className="flex-1 py-4 bg-amber-500 hover:bg-amber-600 text-white rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all shadow-lg shadow-amber-500/20">
                                Entendido, Editar
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* --- MODAL 2: FORMULARIO DE CAPTURA --- */}
            {modalAbierto && (
                <div className="fixed inset-0 z-50 flex items-center justify-center theme-overlay p-4 md:p-6 overflow-y-auto transition-all">
                    <div className="theme-surface border-2 theme-border rounded-[3rem] p-8 md:p-12 shadow-2xl max-w-5xl w-full relative tab-content m-auto mt-10 mb-10">
                        <button onClick={cerrarModal} className="absolute top-6 right-6 p-3 theme-element theme-hover-bg theme-text-muted hover:text-pink-500 rounded-2xl transition-all z-20">
                            <X className="w-5 h-5" />
                        </button>

                        <div className="grid grid-cols-1 lg:grid-cols-5 gap-12 items-start relative z-10">
                            <div className="lg:col-span-3">
                                <form onSubmit={guardarSolicitud} className="space-y-8">
                                    <h2 className="text-2xl font-black italic theme-text-main uppercase tracking-tighter flex items-center">
                                        <Sparkles className="inline w-6 h-6 mr-3 text-pink-500" /> {data.numero_cliente ? 'Editar Entrada' : 'Nueva Entrada'}
                                    </h2>

                                    <div className="space-y-3">
                                        <label className="text-xs font-black uppercase theme-text-muted ml-2 tracking-widest italic">Número de Cliente_</label>
                                        <div className="relative">
                                            <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                                            <input 
                                                type="text" 
                                                value={data.numero_cliente} 
                                                onChange={e => setData('numero_cliente', e.target.value)} 
                                                placeholder="Opcional. Ej. CLI-089" 
                                                className="w-full pl-12 p-4 theme-element border-2 theme-border rounded-2xl theme-text-main font-bold focus:border-pink-500 outline-none transition-all theme-placeholder" 
                                            />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div className="space-y-3">
                                            <label className="text-xs font-black uppercase theme-text-muted ml-2 tracking-widest italic">Cotización ($)_</label>
                                            <input type="number" step="0.01" value={data.monto_cotizado} onChange={e => setData('monto_cotizado', e.target.value)} required placeholder="0.00" className="w-full p-4 theme-element border-2 theme-border rounded-2xl theme-text-main font-bold focus:border-pink-500 outline-none transition-all" />
                                        </div>
                                        <div className="space-y-3">
                                            <label className="text-xs font-black uppercase theme-text-muted ml-2 tracking-widest italic">Tipo Solicitud_</label>
                                            <select value={data.catalogo_proceso_id} onChange={e => setData('catalogo_proceso_id', e.target.value)} required className="w-full p-4 theme-element border-2 theme-border rounded-2xl theme-text-main font-bold focus:border-pink-500 outline-none appearance-none cursor-pointer transition-all">
                                                <option value="">Seleccionar...</option>
                                                {procesos.map(p => <option key={p.id} value={p.id}>{p.nombre}</option>)}
                                            </select>
                                        </div>
                                    </div>
                                    
                                     <div className="space-y-3">
                                        <label className="text-xs font-black uppercase theme-text-muted ml-2 tracking-widest italic">Observaciones_</label>
                                        <textarea value={data.observaciones_vendedor} onChange={e => setData('observaciones_vendedor', e.target.value)} className="w-full p-4 theme-element border-2 theme-border rounded-2xl theme-text-main font-bold focus:border-pink-500 outline-none transition-all resize-none"></textarea>
                                    </div>

                                    <div className="flex flex-col items-start pt-4">
                                        <button type="submit" disabled={processing} className="px-12 py-5 bg-pink-600 hover:bg-pink-500 text-white rounded-3xl font-black uppercase tracking-widest text-[10px] transition-all shadow-xl shadow-pink-500/20 flex items-center gap-3">
                                            <Send className="w-4 h-4" /> Guardar Solicitud
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div className="lg:col-span-2 space-y-6 mt-4 lg:mt-0">
                                <div className="p-8 theme-element border-2 theme-border rounded-[2.5rem] shadow-sm">
                                    <div className="flex items-center gap-2 mb-4">
                                        <Info className="w-4 h-4 theme-text-main" />
                                        <h4 className="text-[10px] font-black uppercase tracking-widest theme-text-main">Reglas de Sistema</h4>
                                    </div>
                                    <p className="text-xs theme-text-muted leading-relaxed font-bold italic">
                                        Toda solicitud es auditada en tiempo real. 
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* --- VISTA PRINCIPAL --- */}
            <div className="max-w-[1400px] mx-auto p-6 md:p-12 space-y-12">
                
                <header className="space-y-4 tab-content">
                    <div className="flex items-center space-x-3">
                        <span className="h-1.5 w-12 bg-pink-500 rounded-full"></span>
                        <p className="text-[10px] font-black uppercase tracking-[0.3em] text-pink-600">Central Infrastructure</p>
                    </div>
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-tight">
                            PANEL DE <span className="text-pink-500">SOLICITUDES</span>
                        </h1>
                        {can('crear_solicitud') && (
                            <button onClick={abrirModalNuevo} className="flex items-center justify-center gap-2 px-8 py-4 bg-pink-600 hover:bg-pink-500 text-white rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all shadow-xl shadow-pink-500/20">
                                <Plus className="w-4 h-4" /> Nueva Solicitud
                            </button>
                        )}
                    </div>
                </header>

                {/* FILTROS AVANZADOS */}
                <div className="tab-content theme-surface border-2 theme-border p-4 rounded-3xl shadow-sm flex flex-wrap gap-4 items-center">
                    <div className="flex items-center gap-2 pr-4 border-r theme-border">
                        <Filter className="w-4 h-4 theme-text-muted" />
                        <span className="text-[10px] font-black uppercase tracking-widest theme-text-main">Filtros_</span>
                    </div>
                    
                    {/* Botón simple de aplicar búsqueda/filtros: en un escenario real conectarías los inputs a router.get() */}
                    <div className="flex-1 min-w-[200px] relative">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                        <input type="text" placeholder="En construcción: Búsqueda dinámica..." className="w-full pl-10 pr-4 py-2 theme-element border theme-border rounded-xl text-xs font-bold theme-text-main outline-none" />
                    </div>
                </div>

                {/* --- BARRA DE EXPORTACIÓN Y TABLA EXTENDIDA --- */}
                <div className="tab-content theme-surface border-2 theme-border rounded-[3rem] p-8 md:p-12 shadow-sm overflow-hidden">
                    
                    <div className="flex flex-col md:flex-row justify-between items-center mb-8 gap-4 border-b theme-border pb-6">
                        <div>
                            <h3 className="text-lg font-black uppercase tracking-widest theme-text-main">Registros del Sistema</h3>
                            <p className="text-[10px] font-bold theme-text-muted mt-1">Mostrando página actual de resultados_</p>
                        </div>
                        <div className="flex items-center gap-3">
                            <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted mr-2">Descargar Reporte_</span>
                            <button onClick={() => exportarReporte('excel')} className="flex items-center gap-2 px-4 py-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-600 border border-emerald-100 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all hover:scale-105">
                                <FileSpreadsheet className="w-3.5 h-3.5" /> Excel
                            </button>
                        </div>
                    </div>

                    <div className="overflow-x-auto pb-32">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="border-b-2 theme-border">
                                    <th className="pb-6 pl-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">ID & Vendedora_</th>
                                    <th className="pb-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Titular / Proceso_</th>
                                    <th className="pb-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Cotización_</th>
                                    <th className="pb-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Finanzas_</th>
                                    <th className="pb-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Operación_</th>
                                    <th className="pb-6 text-[10px] font-black uppercase tracking-widest theme-text-muted text-center">Acciones_</th>
                                </tr>
                            </thead>
                            <tbody>
                                {listaSolicitudes.length === 0 ? (
                                    <tr>
                                        <td colSpan="6" className="text-center py-10 text-gray-500 font-bold">No hay solicitudes para mostrar.</td>
                                    </tr>
                                ) : listaSolicitudes.map((solicitud) => {
                                    const estatus = obtenerEstiloEstado(solicitud.estado?.nombre);
                                    const StatusIcon = estatus.icon;

                                    return (
                                        <tr key={solicitud.id} className="border-b theme-border theme-hover-bg transition-colors">
                                            <td className="py-5 pl-4">
                                                <div className="font-black text-xs theme-text-main uppercase">FOL-{solicitud.id}</div>
                                                <div className="text-[9px] font-bold theme-text-muted mt-1">{solicitud.vendedor?.name || 'Sistema'}</div>
                                            </td>
                                            
                                            <td className="py-5">
                                                <div className="font-bold text-sm theme-text-main">{solicitud.cliente?.nombre || 'Prospecto sin registro'}</div>
                                                <div className="text-[10px] font-bold theme-text-muted mt-1">{solicitud.proceso?.nombre}</div>
                                            </td>
                                            
                                            <td className="py-5 font-black italic theme-text-main text-sm">${solicitud.monto_cotizado}</td>
                                            
                                            <td className="py-5 space-y-2">
                                                <div className="flex items-center gap-2">
                                                    <div className={`w-2 h-2 rounded-full ${solicitud.pago_confirmado ? 'bg-emerald-500' : 'bg-amber-500 animate-pulse'}`}></div>
                                                    <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted">
                                                        {solicitud.pago_confirmado ? 'Pago Confirmado' : 'Esperando Pago'}
                                                    </span>
                                                </div>
                                            </td>

                                            <td className="py-5">
                                                <div className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-transparent ${estatus.clase}`}>
                                                    <StatusIcon className="w-3.5 h-3.5" />
                                                    <span className="text-[9px] font-black uppercase tracking-wider italic">{estatus.label}</span>
                                                </div>
                                            </td>

                                            <td className="py-5 text-center relative">
                                                <button onClick={() => setMenuAbierto(menuAbierto === solicitud.id ? null : solicitud.id)} className="p-2 theme-hover-bg rounded-xl transition-colors inline-flex border border-zinc-200">
                                                    <MoreVertical className="w-4 h-4 theme-text-main" />
                                                </button>
                                                
                                                {/* MENÚ DINÁMICO POR PERMISOS */}
                                                {menuAbierto === solicitud.id && (
                                                    <>
                                                        <div className="fixed inset-0 z-10" onClick={() => setMenuAbierto(null)}></div>
                                                        <div className="absolute right-10 top-0 theme-surface border-2 theme-border shadow-2xl rounded-2xl p-2 z-20 w-56 flex flex-col gap-1">
                                                            
                                                            {can('crear_solicitud') && (
                                                                <>
                                                                    <div className="px-2 py-1 text-[8px] font-black uppercase tracking-widest text-zinc-400 mb-1 border-b theme-border">Acciones Vendedora</div>
                                                                    <button onClick={() => iniciarEdicion(solicitud)} className="flex items-center gap-3 px-3 py-2 hover:bg-zinc-100 text-zinc-700 rounded-lg text-[10px] font-bold uppercase transition-colors text-left">
                                                                        <Edit2 className="w-3.5 h-3.5 text-zinc-500" /> Editar Solicitud
                                                                    </button>
                                                                </>
                                                            )}

                                                            {can('confirmar_pago') && !solicitud.pago_confirmado && (
                                                                <>
                                                                    <div className="px-2 py-1 text-[8px] font-black uppercase tracking-widest text-blue-400 mb-1 mt-1 border-b theme-border">Finanzas</div>
                                                                    <button onClick={() => confirmarPago(solicitud.id)} className="flex items-center gap-3 px-3 py-2 hover:bg-blue-50 text-blue-600 rounded-lg text-[10px] font-bold uppercase transition-colors text-left">
                                                                        <CreditCard className="w-3.5 h-3.5" /> Confirmar Pago
                                                                    </button>
                                                                </>
                                                            )}

                                                            {can('verificar_auxiliar') && (
                                                                <>
                                                                    <div className="px-2 py-1 text-[8px] font-black uppercase tracking-widest text-emerald-400 mb-1 mt-1 border-b theme-border">Auditoría</div>
                                                                    <button onClick={() => cambiarEstado(solicitud.id, 3)} className="flex items-center gap-3 px-3 py-2 hover:bg-emerald-50 text-emerald-600 rounded-lg text-[10px] font-bold uppercase transition-colors text-left">
                                                                        <CheckSquare className="w-3.5 h-3.5" /> Marcar Verificado
                                                                    </button>
                                                                </>
                                                            )}

                                                            {can('ejecutar_tags') && (
                                                                <>
                                                                    <div className="px-2 py-1 text-[8px] font-black uppercase tracking-widest text-pink-400 mb-1 mt-1 border-b theme-border">Operaciones</div>
                                                                    <button onClick={() => cambiarEstado(solicitud.id, 2)} className="flex items-center gap-3 px-3 py-2 hover:bg-pink-50 text-pink-600 rounded-lg text-[10px] font-bold uppercase transition-colors text-left">
                                                                        <CheckCircle2 className="w-3.5 h-3.5" /> Aprobar Proceso
                                                                    </button>
                                                                    <button onClick={() => reportarInconsistencia(solicitud.id)} className="flex items-center gap-3 px-3 py-2 hover:bg-red-50 text-red-600 rounded-lg text-[10px] font-bold uppercase transition-colors text-left">
                                                                        <AlertOctagon className="w-3.5 h-3.5" /> Reportar Inconsistencia
                                                                    </button>
                                                                </>
                                                            )}
                                                            
                                                            {can('ver_auditoria') && (
                                                                <>
                                                                    <div className="px-2 py-1 text-[8px] font-black uppercase tracking-widest text-purple-400 mb-1 mt-1 border-b theme-border">Sistema</div>
                                                                    <button className="flex items-center gap-3 px-3 py-2 hover:bg-purple-50 text-purple-600 rounded-lg text-[10px] font-bold uppercase transition-colors text-left">
                                                                        <History className="w-3.5 h-3.5" /> Ver Bitácora
                                                                    </button>
                                                                </>
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

            <style>{`
                /* TEMA FORZADO: SOLO BLANCO / CLARO */
                .theme-surface { background-color: #ffffff; border-color: #f4f4f5; }
                .theme-element { background-color: #fafafa; border-color: #e4e4e7; }
                .theme-text-main { color: #18181b; }
                .theme-text-muted { color: #71717a; }
                .theme-border { border-color: #f4f4f5; }
                .theme-hover-bg:hover { background-color: #f4f4f5; }
                .theme-placeholder::placeholder { color: #a1a1aa; }
                .theme-overlay { background-color: rgba(0, 0, 0, 0.4); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
                
                .status-aprobado { background-color: #ecfdf5; color: #059669; }
                .status-rechazado { background-color: #fef2f2; color: #dc2626; }
                .status-revision { background-color: #fffbeb; color: #d97706; }
                .status-incidencia { background-color: #fef2f2; color: #b91c1c; border-color: #fca5a5; }
                .status-verificado { background-color: #eff6ff; color: #2563eb; }
            `}</style>
        </AppLayout>
    );
}