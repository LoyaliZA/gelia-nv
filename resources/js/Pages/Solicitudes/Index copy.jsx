import React, { useEffect, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { animate } from 'animejs';
import { 
    Users, Clock, Sparkles, Send, 
    AlertCircle, ShieldCheck, Info, 
    Plus, MoreVertical, Edit2, AlertTriangle, 
    CheckCircle2, XCircle, FileText, X
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';

export default function Index({ solicitudes = { total: 0 }, procesos = [], auth }) {
    const [modalAbierto, setModalAbierto] = useState(false);
    const [menuAbierto, setMenuAbierto] = useState(null);
    const [solicitudAEditar, setSolicitudAEditar] = useState(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        numero_cliente: '',
        nombre_cliente: '',
        monto_cotizado: '',
        catalogo_proceso_id: '',
        observaciones_vendedor: '',
    });

    const listaSolicitudes = [
        { id: 'CLI-089', titular: 'Mariana Ríos', monto: '1,500.00', proceso: 'Reactivación', estado: 'aprobado', fecha: '03 May 2026' },
        { id: 'CLI-090', titular: 'Carlos Mendoza', monto: '3,200.00', proceso: 'Cambio de Lista', estado: 'pendiente', fecha: '03 May 2026' },
        { id: 'CLI-091', titular: 'Ana Victoria', monto: '850.00', proceso: 'Asignación TAG', estado: 'rechazado', fecha: '02 May 2026' },
    ];

    useEffect(() => {
        animate('.tab-content', {
            translateY: [10, 0],
            opacity: [0, 1],
            easing: 'easeOutExpo',
            duration: 600
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

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('solicitudes.store'), { 
            onSuccess: () => {
                reset();
                setModalAbierto(false);
            },
            preserveScroll: true 
        });
    };

    const iniciarEdicion = (solicitud) => {
        setMenuAbierto(null);
        setSolicitudAEditar(solicitud);
    };

    const confirmarEdicion = () => {
        setData({
            numero_cliente: solicitudAEditar.id,
            nombre_cliente: solicitudAEditar.titular,
            monto_cotizado: solicitudAEditar.monto.replace(',', ''),
            catalogo_proceso_id: '',
            observaciones_vendedor: '',
        });
        setSolicitudAEditar(null);
        setModalAbierto(true);
    };

    const obtenerEstiloEstado = (estado) => {
        switch(estado) {
            case 'aprobado': return { clase: 'status-aprobado', icon: CheckCircle2, label: 'Aprobado' };
            case 'rechazado': return { clase: 'status-rechazado', icon: XCircle, label: 'Rechazado' };
            default: return { clase: 'status-revision', icon: Clock, label: 'En Revisión' };
        }
    };

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
                            Estás a punto de modificar la solicitud <span className="text-pink-500 font-black">{solicitudAEditar.id}</span>. Cualquier alteración a este folio quedará <span className="theme-text-main">registrada en la bitácora</span>.
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
                                <form onSubmit={handleSubmit} className="space-y-8">
                                    <h2 className="text-2xl font-black italic theme-text-main uppercase tracking-tighter flex items-center">
                                        <Sparkles className="inline w-6 h-6 mr-3 text-pink-500" /> {data.numero_cliente ? 'Editar Entrada' : 'Nueva Entrada'}
                                    </h2>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div className="space-y-3">
                                            <label className="text-xs font-black uppercase theme-text-muted ml-2 tracking-widest italic">ID Cliente_</label>
                                            <input type="text" value={data.numero_cliente} onChange={e => setData('numero_cliente', e.target.value)} placeholder="CLI-000" className="w-full p-4 theme-element border-2 theme-border rounded-2xl theme-text-main font-bold focus:border-pink-500 outline-none transition-all theme-placeholder" />
                                        </div>
                                        <div className="space-y-3">
                                            <label className="text-xs font-black uppercase theme-text-muted ml-2 tracking-widest italic">Nombre Titular_</label>
                                            <input type="text" value={data.nombre_cliente} onChange={e => setData('nombre_cliente', e.target.value)} placeholder="Nombre Completo" className="w-full p-4 theme-element border-2 theme-border rounded-2xl theme-text-main font-bold focus:border-pink-500 outline-none transition-all theme-placeholder" />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div className="space-y-3">
                                            <label className="text-xs font-black uppercase theme-text-muted ml-2 tracking-widest italic">Presupuesto ($)_</label>
                                            <input type="number" value={data.monto_cotizado} onChange={e => setData('monto_cotizado', e.target.value)} className="w-full p-4 theme-element border-2 theme-border rounded-2xl theme-text-main font-bold focus:border-pink-500 outline-none transition-all" />
                                        </div>
                                        <div className="space-y-3">
                                            <label className="text-xs font-black uppercase theme-text-muted ml-2 tracking-widest italic">Naturaleza Proceso_</label>
                                            <select value={data.catalogo_proceso_id} onChange={e => setData('catalogo_proceso_id', e.target.value)} className="w-full p-4 theme-element border-2 theme-border rounded-2xl theme-text-main font-bold focus:border-pink-500 outline-none appearance-none cursor-pointer transition-all">
                                                <option value="">Seleccionar...</option>
                                                {procesos?.map(p => <option key={p.id} value={p.id}>{p.nombre.toUpperCase()}</option>)}
                                            </select>
                                        </div>
                                    </div>

                                    <div className="flex flex-col items-start pt-4">
                                        <button type="submit" disabled={processing} className="px-12 py-5 bg-pink-600 hover:bg-pink-500 text-white rounded-3xl font-black uppercase tracking-widest text-[10px] transition-all shadow-xl shadow-pink-500/20 flex items-center gap-3">
                                            <Send className="w-4 h-4" /> {processing ? 'Procesando...' : (data.numero_cliente ? 'Guardar Cambios' : 'Enviar Solicitud')}
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <div className="lg:col-span-2 space-y-6 mt-4 lg:mt-0">
                                <div className="p-8 theme-element border-2 theme-border rounded-[2.5rem] shadow-sm">
                                    <div className="flex items-center gap-2 mb-4">
                                        <Info className="w-4 h-4 theme-text-main" />
                                        <h4 className="text-[10px] font-black uppercase tracking-widest theme-text-main">Protocolo de Venta</h4>
                                    </div>
                                    <p className="text-xs theme-text-muted leading-relaxed font-bold italic">
                                        Toda solicitud es auditada en tiempo real. Asegúrese de que el monto coincida con el tabulador vigente para evitar rechazos automáticos.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* --- VISTA PRINCIPAL (Fondo y Tabla) --- */}
            <div className="max-w-7xl mx-auto p-6 md:p-12 space-y-12">
                
                <header className="space-y-4 tab-content">
                    <div className="flex items-center space-x-3">
                        <span className="h-1.5 w-12 bg-pink-500 rounded-full"></span>
                        <p className="text-[10px] font-black uppercase tracking-[0.3em] text-pink-600">Central Infrastructure</p>
                    </div>
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
                        <h1 className="text-4xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main leading-tight">
                            PANEL DE <span className="text-pink-500">SOLICITUDES</span>
                        </h1>
                        <button onClick={abrirModalNuevo} className="flex items-center justify-center gap-2 px-8 py-4 bg-pink-600 hover:bg-pink-500 text-white rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all shadow-xl shadow-pink-500/20">
                            <Plus className="w-4 h-4" /> Nueva Solicitud
                        </button>
                    </div>
                </header>

                <div className="tab-content grid grid-cols-1 md:grid-cols-3 gap-8">
                    <div className="theme-surface border-2 theme-border p-10 rounded-[3rem] shadow-sm hover:border-pink-500 transition-all group">
                        <div className="w-14 h-14 theme-element border theme-border rounded-2xl flex items-center justify-center mb-8 shadow-inner group-hover:bg-pink-500 transition-colors">
                            <Users className="w-7 h-7 text-pink-500 group-hover:text-white transition-colors" />
                        </div>
                        <h3 className="text-5xl font-black uppercase italic mb-1 tracking-tighter theme-text-main">{solicitudes?.total || 0}</h3>
                        <p className="text-xs font-black uppercase tracking-widest theme-text-muted">Mis Solicitudes_</p>
                    </div>
                    <div className="theme-surface border-2 theme-border p-10 rounded-[3rem] shadow-sm hover:border-pink-500 transition-all group">
                        <div className="w-14 h-14 theme-element border theme-border rounded-2xl flex items-center justify-center mb-8 shadow-inner group-hover:bg-pink-500 transition-colors">
                            <Clock className="w-7 h-7 text-pink-500 group-hover:text-white transition-colors" />
                        </div>
                        <h3 className="text-5xl font-black uppercase italic mb-1 tracking-tighter theme-text-main">15</h3>
                        <p className="text-xs font-black uppercase tracking-widest theme-text-muted">Pendientes_</p>
                    </div>
                    <div className="theme-surface border-2 theme-border p-10 rounded-[3rem] shadow-sm hover:border-emerald-500 transition-all group flex flex-col justify-center">
                        <div className="flex items-center gap-4">
                            <div className="w-14 h-14 theme-element border theme-border rounded-2xl flex items-center justify-center shadow-inner group-hover:bg-emerald-500 transition-colors">
                                <ShieldCheck className="w-7 h-7 text-emerald-500 group-hover:text-white transition-colors" />
                            </div>
                            <div>
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Estado Sistema_</p>
                                <p className="text-sm font-bold text-emerald-500 uppercase italic mt-1">Operativo</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="tab-content theme-surface border-2 theme-border rounded-[3rem] p-8 md:p-12 shadow-sm overflow-hidden">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="border-b-2 theme-border">
                                    <th className="pb-6 pl-4 text-[10px] font-black uppercase tracking-widest theme-text-muted">ID Folio_</th>
                                    <th className="pb-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Titular_</th>
                                    <th className="pb-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Proceso_</th>
                                    <th className="pb-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Monto_</th>
                                    <th className="pb-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Estatus_</th>
                                    <th className="pb-6 text-[10px] font-black uppercase tracking-widest theme-text-muted text-center">Acción_</th>
                                </tr>
                            </thead>
                            <tbody>
                                {listaSolicitudes.map((solicitud, index) => {
                                    const estatus = obtenerEstiloEstado(solicitud.estado);
                                    const StatusIcon = estatus.icon;

                                    return (
                                        <tr key={index} className="border-b theme-border theme-hover-bg transition-colors">
                                            <td className="py-6 pl-4">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-8 h-8 theme-element rounded-lg flex items-center justify-center border theme-border"><FileText className="w-3.5 h-3.5 theme-text-muted" /></div>
                                                    <span className="font-bold text-xs theme-text-main uppercase">{solicitud.id}</span>
                                                </div>
                                            </td>
                                            <td className="py-6 font-bold text-sm theme-text-main">{solicitud.titular}</td>
                                            <td className="py-6 text-xs font-bold theme-text-muted">{solicitud.proceso}</td>
                                            <td className="py-6 font-black italic theme-text-main">${solicitud.monto}</td>
                                            <td className="py-6">
                                                <div className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-transparent ${estatus.clase}`}>
                                                    <StatusIcon className="w-3.5 h-3.5" />
                                                    <span className="text-[9px] font-black uppercase tracking-wider italic">{estatus.label}</span>
                                                </div>
                                            </td>
                                            <td className="py-6 text-center relative">
                                                <button onClick={() => setMenuAbierto(menuAbierto === solicitud.id ? null : solicitud.id)} className="p-2 theme-hover-bg rounded-xl transition-colors inline-flex">
                                                    <MoreVertical className="w-4 h-4 theme-text-muted" />
                                                </button>
                                                {menuAbierto === solicitud.id && (
                                                    <>
                                                        <div className="fixed inset-0 z-10" onClick={() => setMenuAbierto(null)}></div>
                                                        <div className="absolute right-10 top-1/2 -translate-y-1/2 theme-surface border theme-border shadow-xl rounded-2xl p-2 z-20 w-36">
                                                            <button onClick={() => iniciarEdicion(solicitud)} className="flex items-center gap-3 px-3 py-2.5 bg-amber-500/10 text-amber-500 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors w-full text-left">
                                                                <Edit2 className="w-3.5 h-3.5" /> Editar
                                                            </button>
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

            {/* ESTILOS MAESTROS - Bypass de Tailwind */}
            <style>{`
                /* Modo Claro (Por Defecto) */
                .theme-surface { background-color: #ffffff; border-color: #f4f4f5; }
                .theme-element { background-color: #fafafa; border-color: #e4e4e7; }
                .theme-text-main { color: #18181b; }
                .theme-text-muted { color: #71717a; }
                .theme-border { border-color: #f4f4f5; }
                .theme-hover-bg:hover { background-color: #f4f4f5; }
                .theme-placeholder::placeholder { color: #a1a1aa; }
                .theme-overlay { background-color: rgba(255, 255, 255, 0.4); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
                
                .status-aprobado { background-color: #ecfdf5; color: #059669; }
                .status-rechazado { background-color: #fef2f2; color: #dc2626; }
                .status-revision { background-color: #fffbeb; color: #d97706; }

                /* Modo Oscuro (Cuando AppLayout agrega la clase .dark al HTML) */
                .dark .theme-surface { background-color: #141414; border-color: #2A2A2A; }
                .dark .theme-element { background-color: #1A1A1A; border-color: #333333; }
                .dark .theme-text-main { color: #ffffff; }
                .dark .theme-text-muted { color: #a1a1aa; }
                .dark .theme-border { border-color: #2A2A2A; }
                .dark .theme-hover-bg:hover { background-color: #2A2A2A; }
                .dark .theme-placeholder::placeholder { color: #52525b; }
                .dark .theme-overlay { background-color: rgba(0, 0, 0, 0.6); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
                
                .dark .status-aprobado { background-color: rgba(16, 185, 129, 0.1); color: #34d399; }
                .dark .status-rechazado { background-color: rgba(239, 68, 68, 0.1); color: #f87171; }
                .dark .status-revision { background-color: rgba(245, 158, 11, 0.1); color: #fbbf24; }
            `}</style>
        </AppLayout>
    );
}