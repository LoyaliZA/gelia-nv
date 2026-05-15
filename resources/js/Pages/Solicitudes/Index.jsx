import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { Head, router, useForm } from '@inertiajs/react';
import {
    Clock, Plus, MoreVertical, Edit2, CheckCircle2, AlertOctagon,
    Search, History, CheckSquare, CreditCard, User, Copy, Check, Tag, TrendingUp, ShieldAlert, Users,
    ChevronLeft, ChevronRight, Trash2, FileImage, X, MessageSquare, AlertTriangle
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaLoader from '../../Components/GeliaLoader';

import ModalFormSolicitud from './Partials/ModalFormSolicitud';
import ModalRespuestaSolicitud from './Partials/ModalRespuestaSolicitud';
import ModalBitacoraSolicitud from './Partials/ModalBitacoraSolicitud';

const ESTILOS_ADICIONALES = `
    .status-aprobado { background-color: #ecfdf5; color: #059669; border-color: #a7f3d0; }
    .status-incidencia { background-color: #fef2f2; color: #b91c1c; border-color: #fca5a5; }
    .status-verificado { background-color: #eff6ff; color: #2563eb; border-color: #bfdbfe; }
    .status-revision { background-color: #fffbeb; color: #d97706; border-color: #fde68a; }
    .dark .status-aprobado { background-color: rgba(16, 185, 129, 0.1); color: #34d399; border-color: rgba(52, 211, 153, 0.3); }
    .dark .status-incidencia { background-color: rgba(239, 68, 68, 0.1); color: #fca5a5; border-color: rgba(239, 68, 68, 0.3); }
    .dark .status-verificado { background-color: rgba(59, 130, 246, 0.1); color: #60a5fa; border-color: rgba(96, 165, 250, 0.3); }
    .dark .status-revision { background-color: rgba(245, 158, 11, 0.1); color: #fbbf24; border-color: rgba(251, 191, 36, 0.3); }

    .sticky-actions { position: sticky; right: 0; z-index: 30; background-color: #ffffff; }
    .dark .sticky-actions { background-color: #121212; }
    .sticky-actions::before { content: ''; position: absolute; left: -15px; top: 0; bottom: 0; width: 15px; background: linear-gradient(to right, transparent, rgba(0,0,0,0.05)); pointer-events: none; }
    .dark .sticky-actions::before { background: linear-gradient(to right, transparent, rgba(0,0,0,0.3)); }

    @keyframes slideUpFade { 0% { opacity: 0; transform: translateY(20px); } 100% { opacity: 1; transform: translateY(0); } }
    .animate-page-reveal { opacity: 0; animation: slideUpFade 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
    .paginacion-btn { display: flex; align-items: center; justify-content: center; width: 2.5rem; height: 2.5rem; border-radius: 0.75rem; font-size: 0.75rem; font-weight: 900; border: 1px solid; transition: all 0.15s; cursor: pointer; }
    .paginacion-btn:disabled { opacity: 0.3; cursor: not-allowed; }
`;

const EtiquetasOperacion = ({ solicitud, listas }) => {
    const nombreProceso = solicitud.proceso?.nombre || '';
    const esTag = nombreProceso.toUpperCase().includes('TAG');
    const esCambioLista = nombreProceso.toUpperCase().includes('LISTA');
    const objLista = solicitud.lista_descuento || solicitud.listaDescuento;
    const objTipo = solicitud.tipo_cliente || solicitud.tipoCliente;
    const vendedoraTag = solicitud.vendedor?.name?.split(' ').slice(0, 2).join(' ') || 'Asesor';

    const listaActual = solicitud.cliente?.lista_descuento?.nombre || solicitud.cliente?.lista_actual || 'Público General';

    return (
        <div className="flex flex-wrap gap-1.5">
            <span className="text-[9px] font-black uppercase px-2 py-1 rounded-md bg-slate-500/10 text-slate-500 border border-slate-500/20 flex items-center gap-1">
                Lista actual: {listaActual}
            </span>
            {esCambioLista && objLista && (
                <span className="text-[9px] font-black uppercase px-2 py-1 rounded-md bg-blue-500/10 text-blue-500 border border-blue-500/20 flex items-center gap-1">
                    <TrendingUp className="w-3 h-3" /> Ascenso a: {objLista.nombre}
                </span>
            )}
            {esTag && (
                <span className="text-[9px] font-black uppercase px-2 py-1 rounded-md bg-amber-500/10 text-amber-500 border border-amber-500/20 flex items-center gap-1">
                    <Tag className="w-3 h-3" /> TAG: {vendedoraTag}
                </span>
            )}
            {objTipo && (
                <span className="text-[9px] font-black uppercase px-2 py-1 rounded-md bg-indigo-500/10 text-indigo-500 border border-indigo-500/20 flex items-center gap-1">
                    <Users className="w-3 h-3" /> {objTipo.nombre}
                </span>
            )}
        </div>
    );
};

const VisorImagenHover = ({ path }) => {
    const [isHovered, setIsHovered] = useState(false);
    if (!path) return null;
    const imageUrl = `/storage/${path}`;

    return (
        <div className="inline-block" onMouseEnter={() => setIsHovered(true)} onMouseLeave={() => setIsHovered(false)}>
            <div className="flex items-center gap-2 px-3 py-1.5 rounded-lg border bg-black/5 dark:bg-white/5 border-black/10 dark:border-white/10 cursor-pointer hover:border-emerald-500 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors">
                <img src={imageUrl} className="w-5 h-5 object-cover rounded shadow-sm" alt="Miniatura" />
                <span className="text-[9px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400">Ver Resolución</span>
            </div>
            {isHovered && createPortal(
                <div className="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 backdrop-blur-md pointer-events-none animate-fade-in p-4 md:p-8">
                    <img src={imageUrl} alt="Evidencia Expandida" className="max-w-full max-h-full object-contain rounded-2xl shadow-[0_0_50px_rgba(0,0,0,0.5)] transform scale-100" />
                </div>,
                document.body
            )}
        </div>
    );
};

// =============================================
// COMPONENTE: COMENTARIOS Y FEEDBACK
// =============================================
const FeedbackYComentarios = ({ solicitud }) => {
    const esError = solicitud.estado?.nombre === 'Incorrecta';
    const esAprobada = solicitud.estado?.nombre === 'Respondida' || solicitud.estado?.nombre === 'Verificada';

    const auditoriasOrdenadas = [...(solicitud.auditorias || [])].sort((a, b) => b.id - a.id);

    // Buscar la última auditoría real
    const ultimaAuditoria = auditoriasOrdenadas.find(a => !a.motivo_reporte?.toUpperCase().includes('AUTOMÁTICAMENTE'));

    // Variable clave para renderizar Alerta en lugar de Error
    const esAlertaPago = ultimaAuditoria?.motivo_reporte?.toUpperCase().includes('ALERTA DE PAGO');

    const tieneObservacion = solicitud.observaciones_vendedor && solicitud.observaciones_vendedor.trim() !== '';
    const tieneFeedback = (esError || esAprobada || esAlertaPago) && ultimaAuditoria?.motivo_reporte;

    let evidenciaAdmin = solicitud.evidencia_respuesta_path;
    if (!evidenciaAdmin && ultimaAuditoria?.datos_snapshot) {
        const snap = typeof ultimaAuditoria.datos_snapshot === 'string' ? JSON.parse(ultimaAuditoria.datos_snapshot) : ultimaAuditoria.datos_snapshot;
        if (snap?.evidencia_respuesta_path) evidenciaAdmin = snap.evidencia_respuesta_path;
    }

    if (!tieneObservacion && !tieneFeedback && !evidenciaAdmin) return null;

    // Configuración visual dinámica
    const colorContenedor = esAlertaPago
        ? 'bg-amber-50 dark:bg-amber-500/5 border-amber-200 dark:border-amber-500/20'
        : (esError ? 'bg-red-50 dark:bg-red-500/5 border-red-200 dark:border-red-500/20' : 'bg-emerald-50 dark:bg-emerald-500/5 border-emerald-200 dark:border-emerald-500/20');

    const Icono = esAlertaPago ? AlertTriangle : (esError ? AlertOctagon : CheckCircle2);
    const colorIcono = esAlertaPago ? 'text-amber-500' : (esError ? 'text-red-500' : 'text-emerald-500');
    const colorTexto = esAlertaPago ? 'text-amber-600 dark:text-amber-400' : (esError ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400');
    const titulo = esAlertaPago ? 'ALERTA DE AJUSTE DE LISTA' : (esError ? 'Corrección Requerida' : 'Resolución Admin');

    return (
        <div className="mt-3 flex flex-col gap-2">
            {tieneObservacion && !esAlertaPago && (
                <div className="p-3 rounded-2xl border bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 flex items-start gap-2 shadow-sm">
                    <MessageSquare className="w-4 h-4 text-slate-500 mt-0.5 shrink-0" />
                    <div>
                        <p className="text-[9px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-400 mb-0.5">Nota de Vendedora</p>
                        <p className="text-xs font-bold theme-text-main italic leading-tight">{solicitud.observaciones_vendedor}</p>
                    </div>
                </div>
            )}

            {(esError || esAprobada || esAlertaPago) && (
                <div className={`p-3 rounded-2xl border flex flex-col gap-2 shadow-sm ${colorContenedor}`}>
                    <div className="flex items-start gap-2">
                        <Icono className={`w-4 h-4 shrink-0 mt-0.5 ${colorIcono}`} />
                        <div className="flex-1">
                            <p className={`text-[9px] font-black uppercase tracking-widest mb-0.5 ${colorTexto}`}>
                                {titulo}
                            </p>
                            {ultimaAuditoria?.motivo_reporte && (
                                <p className={`text-xs font-bold italic leading-tight ${esAlertaPago ? 'text-amber-700 dark:text-amber-500' : 'theme-text-main'}`}>
                                    {ultimaAuditoria.motivo_reporte}
                                </p>
                            )}
                        </div>
                    </div>
                    {evidenciaAdmin && <div className="mt-1"><VisorImagenHover path={evidenciaAdmin} /></div>}
                </div>
            )}
        </div>
    );
};

const ModalConfirmarPago = ({ onClose, solicitud, onConfirmar }) => {
    const { data, setData, processing } = useForm({ monto_final_pagado: solicitud?.monto_cotizado || '' });
    const submit = (e) => { e.preventDefault(); onConfirmar(solicitud.id, data); };

    return createPortal(
        <div className="fixed inset-0 z-[1000] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md animate-fade-in" onClick={onClose}>
            <div className="w-full max-w-sm theme-surface border theme-border shadow-2xl rounded-3xl p-8 relative modal-pop" onClick={e => e.stopPropagation()}>
                <button onClick={onClose} className="absolute top-4 right-4 p-2 theme-text-muted hover:theme-text-main rounded-xl outline-none transition-transform hover:scale-110"><X className="w-5 h-5" /></button>
                <div className="flex items-center gap-3 mb-6">
                    <CreditCard className="w-6 h-6 text-blue-500" />
                    <h3 className="text-xl font-black italic theme-text-main uppercase m-0">Confirmar Pago</h3>
                </div>
                <form onSubmit={submit} className="space-y-6">
                    <div className="space-y-2">
                        <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">Monto Final Cobrado_</label>
                        <input type="number" step="0.01" required value={data.monto_final_pagado} onChange={e => setData('monto_final_pagado', e.target.value)} className="w-full px-4 py-3 theme-surface border theme-border rounded-xl theme-text-main text-sm font-black outline-none focus:ring-2 shadow-sm transition-all" />
                        <p className="text-[10px] theme-text-muted mt-2 italic">* Si el monto cobrado con descuento es inferior a la meta de la lista, el sistema lo alertará automáticamente a la encargada.</p>
                    </div>
                    <button type="submit" disabled={processing} className="w-full py-4 text-white rounded-xl font-black uppercase text-[11px] tracking-widest bg-blue-600 hover:bg-blue-700 transition-all shadow-lg outline-none disabled:opacity-50">
                        Confirmar Operación
                    </button>
                </form>
            </div>
        </div>,
        document.body
    );
};

// =============================================
// MENÚ ACCIONES — Portal
// =============================================
const MenuAccionesPortal = ({ menuAbierto, menuSolicitud, menuPos, setMenuAbierto, setModalForm, setModalRespuesta, setModalBitacora, abrirModalPago, confirmarCambioLista, eliminarSolicitud, can, auth }) => {
    if (!menuAbierto || !menuSolicitud) return null;
    const solicitud = menuSolicitud;

    // Detectamos si es una alerta de pago para mostrar la opción de confirmar ajuste
    const auditoriasOrdenadas = [...(solicitud.auditorias || [])].sort((a, b) => b.id - a.id);
    const ultimaAuditoria = auditoriasOrdenadas.find(a => !a.motivo_reporte?.toUpperCase().includes('AUTOMÁTICAMENTE'));
    const esAlertaPago = ultimaAuditoria?.motivo_reporte?.toUpperCase().includes('ALERTA DE PAGO');

    return createPortal(
        <>
            <div className="fixed inset-0 z-[999]" onClick={() => setMenuAbierto(null)}></div>
            <div className="fixed z-[1000] theme-surface border theme-border shadow-2xl rounded-2xl p-2 w-56 flex flex-col gap-1 backdrop-blur-xl animate-fade-in" style={{ top: menuPos.top, left: menuPos.left }}>

                {/* Confirmar Cambio de Lista (Solo si hay alerta) */}
                {esAlertaPago && can('solicitudes.confirmar_cambio_lista') && (
                    <button onClick={() => { setMenuAbierto(null); confirmarCambioLista(solicitud.id); }} className="flex items-center gap-3 px-4 py-3 hover:bg-amber-50 dark:hover:bg-amber-500/10 text-amber-600 dark:text-amber-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors border-b theme-border mb-1 pb-3">
                        <TrendingUp className="w-4 h-4" /> Confirmar Ajuste
                    </button>
                )}

                {/* Reparar Solicitud (Solo vendedor, solo si está incorrecta) */}
                {solicitud.vendedor_id === auth.user.id && solicitud.estado?.nombre === 'Incorrecta' && !esAlertaPago && (
                    <button onClick={() => { setMenuAbierto(null); setModalForm({ abierto: true, modoEdicion: true, solicitud }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-orange-50 dark:hover:bg-orange-500/10 text-orange-600 dark:text-orange-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors border-b theme-border mb-1 pb-3">
                        <Edit2 className="w-4 h-4" /> Reparar Solicitud
                    </button>
                )}

                {/* Confirmar Pago - CORREGIDO: Ahora exige estrictamente que el estado sea 'Respondida' (Estado 2) */}
                {(can('solicitudes.confirmar_pago') || solicitud.vendedor_id === auth.user.id) && !solicitud.pago_confirmado && solicitud.estado?.nombre === 'Respondida' && !esAlertaPago && (
                    <button onClick={() => { setMenuAbierto(null); abrirModalPago(solicitud); }} className="flex items-center gap-3 px-4 py-3 hover:bg-blue-50 dark:hover:bg-blue-500/10 text-blue-600 dark:text-blue-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors border-b theme-border mb-1 pb-3">
                        <CreditCard className="w-4 h-4" /> Confirmar Pago
                    </button>
                )}

                {/* Verificado (Paso final de la auxiliar) */}
                {can('solicitudes.verificar') && !esAlertaPago && (
                    <button onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud, estadoId: 3 }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors">
                        <CheckSquare className="w-4 h-4" /> Verificado
                    </button>
                )}

                {/* Aprobar / Reportar (Encargada) */}
                {can('solicitudes.reportar') && !esAlertaPago && (
                    <>
                        <button onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud, estadoId: 2 }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-black/5 dark:hover:bg-white/5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors" style={{ color: 'var(--color-primario)' }}>
                            <CheckCircle2 className="w-4 h-4" /> Aprobar Proceso
                        </button>
                        <button onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud, estadoId: 4 }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-red-50 dark:hover:bg-red-500/10 text-red-600 dark:text-red-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors">
                            <AlertOctagon className="w-4 h-4" /> Reportar Error
                        </button>
                    </>
                )}

                {/* Bitácora */}
                {can('configuracion.ver_auditoria') && (
                    <button onClick={() => { setMenuAbierto(null); setModalBitacora({ abierto: true, solicitud }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-purple-50 dark:hover:bg-purple-500/10 text-purple-600 dark:text-purple-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors border-t theme-border mt-1 pt-3">
                        <History className="w-4 h-4" /> Ver Bitácora
                    </button>
                )}

                {/* Eliminar */}
                {can('solicitudes.eliminar') && (
                    <button onClick={() => eliminarSolicitud(solicitud.id)} className="flex items-center gap-3 px-4 py-3 hover:bg-red-900/10 text-red-600 dark:text-red-500 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors border-t theme-border mt-1 pt-3">
                        <Trash2 className="w-4 h-4" /> Eliminar Registro
                    </button>
                )}
            </div>
        </>, document.body
    );
};

const Paginacion = ({ solicitudes, onIrAPagina }) => {
    const paginaActual = solicitudes.current_page || 1; const totalPaginas = solicitudes.last_page || 1; const totalRegistros = solicitudes.total || 0; const desde = solicitudes.from || 1; const hasta = solicitudes.to || 10;
    if (totalPaginas <= 1) return null;
    const generarPaginas = () => {
        const paginas = [];
        if (totalPaginas <= 7) { for (let i = 1; i <= totalPaginas; i++) paginas.push(i); }
        else {
            paginas.push(1); if (paginaActual > 3) paginas.push('...');
            for (let i = Math.max(2, paginaActual - 1); i <= Math.min(totalPaginas - 1, paginaActual + 1); i++) paginas.push(i);
            if (paginaActual < totalPaginas - 2) paginas.push('...'); paginas.push(totalPaginas);
        }
        return paginas;
    };
    return (
        <div className="animate-page-reveal theme-surface rounded-[2rem] border theme-border shadow-xl p-4 flex flex-col sm:flex-row items-center justify-between gap-4" style={{ animationDelay: '300ms' }}>
            <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Viendo {desde} al {hasta} de {totalRegistros.toLocaleString('es-MX')}</span>
            <div className="flex items-center gap-2">
                <button onClick={() => onIrAPagina(paginaActual - 1)} disabled={paginaActual === 1} className="paginacion-btn theme-surface border theme-border theme-text-muted hover:border-[var(--color-primario)] hover:text-[var(--color-primario)]"><ChevronLeft className="w-4 h-4" /></button>
                {generarPaginas().map((p, i) => p === '...' ? (<span key={`dots-${i}`} className="w-10 text-center text-[11px] font-black theme-text-muted">…</span>) : (<button key={p} onClick={() => onIrAPagina(p)} className={`paginacion-btn theme-border ${p === paginaActual ? '' : 'theme-surface theme-text-main hover:border-[var(--color-primario)] hover:text-[var(--color-primario)]'}`} style={p === paginaActual ? { backgroundColor: 'var(--color-primario)', color: '#fff', borderColor: 'var(--color-primario)' } : {}}>{p}</button>))}
                <button onClick={() => onIrAPagina(paginaActual + 1)} disabled={paginaActual === totalPaginas} className="paginacion-btn theme-surface border theme-border theme-text-muted hover:border-[var(--color-primario)] hover:text-[var(--color-primario)]"><ChevronRight className="w-4 h-4" /></button>
            </div>
        </div>
    );
};

export default function Index({ solicitudes = { total: 0, data: [], current_page: 1, last_page: 1, per_page: 10, from: 1, to: 10 }, procesos = [], listas = [], tipos_cliente = [], auth }) {

    const [modalForm, setModalForm] = useState({ abierto: false, modoEdicion: false, solicitud: null });
    const [modalRespuesta, setModalRespuesta] = useState({ abierto: false, solicitud: null, estadoId: null });
    const [modalBitacora, setModalBitacora] = useState({ abierto: false, solicitud: null });
    const [modalPago, setModalPago] = useState({ abierto: false, solicitud: null });
    const [menuAbierto, setMenuAbierto] = useState(null);
    const [menuPos, setMenuPos] = useState({ top: 0, left: 0 });
    const [menuSolicitud, setMenuSolicitud] = useState(null);
    const [tabActiva, setTabActiva] = useState('TODAS');
    const [busqueda, setBusqueda] = useState('');
    const [copiadoId, setCopiadoId] = useState(null);
    const [procesandoAccion, setProcesandoAccion] = useState(false);

    const can = (permiso) => { const roles = auth?.user?.roles || []; const isAdmin = roles.includes('Admin') || roles.includes('Super admin (admin)'); return auth?.user?.permissions?.includes(permiso) || isAdmin; };

    const eliminarSolicitud = (id) => {
        const motivo = window.prompt("ATENCIÓN: Se eliminará este registro y se creará un respaldo en la auditoría.\n\nIngresa el motivo de la eliminación (Mínimo 10 caracteres):");
        if (motivo === null) return;
        if (motivo.trim().length < 10) { alert("Operación cancelada: El motivo debe tener al menos 10 caracteres."); return; }
        setMenuAbierto(null); setProcesandoAccion(true);
        router.delete(route('solicitudes.destroy', id), { data: { motivo: motivo.trim() }, onFinish: () => setProcesandoAccion(false) });
    };

    useEffect(() => {
        const interval = setInterval(() => { router.reload({ only: ['solicitudes'], preserveState: true, preserveScroll: true, showProgress: false }); }, 15000);
        return () => clearInterval(interval);
    }, []);

    useEffect(() => {
        const handleScroll = () => setMenuAbierto(null);
        window.addEventListener('scroll', handleScroll, true);
        return () => window.removeEventListener('scroll', handleScroll, true);
    }, []);

    // FALLBACK DE PORTAPAPELES PARA HTTP LOCALHOST
    const copiarAlPortapapeles = (e, texto, id) => {
        e.preventDefault(); e.stopPropagation();
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(texto);
        } else {
            const textArea = document.createElement("textarea");
            textArea.value = texto;
            document.body.appendChild(textArea);
            textArea.focus(); textArea.select();
            try { document.execCommand('copy'); } catch (err) { }
            document.body.removeChild(textArea);
        }
        setCopiadoId(id); setTimeout(() => setCopiadoId(null), 2000);
    };

    const confirmarPagoConMonto = (id, formData) => {
        setModalPago({ abierto: false, solicitud: null });
        setProcesandoAccion(true);
        router.put(route('solicitudes.confirmar_pago', id), formData, { onFinish: () => setProcesandoAccion(false) });
    };

    const confirmarCambioLista = (id) => {
        if (window.confirm('¿Confirmar el ajuste de lista para este cliente?')) {
            setProcesandoAccion(true);
            router.put(route('solicitudes.confirmar_lista', id), {}, {
                onFinish: () => setProcesandoAccion(false)
            });
        }
    };

    const abrirMenu = (e, solicitud) => {
        const btn = e.currentTarget; const rect = btn.getBoundingClientRect(); const menuWidth = 224; const menuHeight = 220;
        const spaceBelow = window.innerHeight - rect.bottom; const openUpward = spaceBelow < menuHeight + 16;
        let top = openUpward ? rect.top - menuHeight - 8 : rect.bottom + 8; let left = rect.right - menuWidth; if (left < 8) left = 8;
        setMenuPos({ top, left }); setMenuSolicitud(solicitud); setMenuAbierto(menuAbierto === solicitud.id ? null : solicitud.id);
    };

    const solicitudesFiltradas = (solicitudes.data || []).filter(solicitud => {
        const cumpleTab = tabActiva === 'TODAS' || (tabActiva === 'PENDIENTES' && solicitud.estado?.nombre === 'Pendiente') || (tabActiva === 'RESPONDIDAS' && solicitud.estado?.nombre === 'Respondida') || (tabActiva === 'INCORRECTAS' && solicitud.estado?.nombre === 'Incorrecta');
        const search = busqueda.toLowerCase();
        return cumpleTab && ((solicitud.id?.toString() || '').includes(search) || (solicitud.cliente?.nombre?.toLowerCase() || '').includes(search) || (solicitud.cliente?.numero_cliente?.toLowerCase() || '').includes(search));
    });

    const obtenerEstiloEstado = (nombreEstado) => {
        switch (nombreEstado?.toLowerCase()) {
            case 'respondida': return { clase: 'status-aprobado', icon: CheckCircle2, label: 'Aprobado' };
            case 'incorrecta': return { clase: 'status-incidencia', icon: AlertOctagon, label: 'Reporte' };
            case 'verificada': return { clase: 'status-verificado', icon: CheckSquare, label: 'Verificada' };
            default: return { clase: 'status-revision', icon: Clock, label: 'Pendiente' };
        }
    };

    const irAPagina = (pagina) => {
        const totalPaginas = solicitudes.last_page || 1;
        if (pagina < 1 || pagina > totalPaginas) return;
        router.get(route('solicitudes.index'), { page: pagina }, { preserveState: true, preserveScroll: false });
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Panel de Solicitudes" />
            <style>{ESTILOS_ADICIONALES}</style>
            <GeliaLoader isVisible={procesandoAccion} message="Sincronizando_" />

            <MenuAccionesPortal
                menuAbierto={menuAbierto}
                menuSolicitud={menuSolicitud}
                menuPos={menuPos}
                setMenuAbierto={setMenuAbierto}
                setModalForm={setModalForm}
                setModalRespuesta={setModalRespuesta}
                setModalBitacora={setModalBitacora}
                abrirModalPago={(s) => setModalPago({ abierto: true, solicitud: s })}
                confirmarCambioLista={confirmarCambioLista}
                eliminarSolicitud={eliminarSolicitud}
                can={can}
                auth={auth}
            />
            {modalPago.abierto && <ModalConfirmarPago onClose={() => setModalPago({ abierto: false, solicitud: null })} solicitud={modalPago.solicitud} onConfirmar={confirmarPagoConMonto} />}

            <div className="max-w-[1440px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">
                <header className="animate-page-reveal theme-surface rounded-3xl md:rounded-[2.5rem] p-6 md:p-12 flex flex-col md:flex-row items-center justify-between gap-4 md:gap-6 border theme-border shadow-xl">
                    <div className="w-full md:w-auto text-center md:text-left">
                        <div className="flex items-center justify-center md:justify-start space-x-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>Panel General</p>
                        </div>
                        <h1 className="text-3xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main m-0">GESTIÓN DE <span style={{ color: 'var(--color-primario)' }}>SOLICITUDES</span></h1>
                    </div>
                    {can('solicitudes.crear') && (
                        <button onClick={() => setModalForm({ abierto: true, modoEdicion: false, solicitud: null })} className="flex items-center justify-center gap-2 px-8 py-4 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] shadow-xl hover:scale-105 transition-all w-full md:w-auto" style={{ backgroundColor: 'var(--color-primario)' }}><Plus className="w-5 h-5" /> Nueva Solicitud</button>
                    )}
                </header>

                <div className="animate-page-reveal flex flex-col lg:flex-row gap-4 items-center justify-between" style={{ animationDelay: '100ms' }}>
                    <div className="gelia-segment w-full lg:w-auto p-1 h-14 shadow-sm overflow-x-auto flex">
                        {['TODAS', 'PENDIENTES', 'RESPONDIDAS', 'INCORRECTAS'].map(tab => (<button key={tab} type="button" onClick={() => setTabActiva(tab)} className="gelia-segment-btn px-4 md:px-6 min-w-max flex-1 text-center" data-active={tabActiva === tab}>{tab}</button>))}
                    </div>
                    <div className="relative w-full lg:w-96">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-5 h-5 theme-text-muted" />
                        <input type="text" placeholder="Buscar folio o cliente..." value={busqueda} onChange={e => setBusqueda(e.target.value)} className="w-full px-12 py-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 transition-all shadow-sm" />
                    </div>
                </div>

                <div className="block lg:hidden space-y-4 animate-page-reveal" style={{ animationDelay: '200ms' }}>
                    {solicitudesFiltradas.length === 0 ? (<div className="theme-surface rounded-3xl p-8 text-center border theme-border theme-text-muted font-bold text-sm">No se encontraron solicitudes_</div>) : (
                        solicitudesFiltradas.map((solicitud) => {
                            const estatus = obtenerEstiloEstado(solicitud.estado?.nombre); const StatusIcon = estatus.icon; const nombreProceso = solicitud.proceso?.nombre || ''; const esHeredado = solicitud.cliente?.es_heredado;
                            return (
                                <div key={solicitud.id} className="theme-surface rounded-3xl border theme-border p-5 shadow-lg relative flex flex-col gap-4">
                                    <div className="flex items-start justify-between border-b theme-border pb-3">
                                        <div>
                                            <div className="font-black text-base" style={{ color: 'var(--color-primario)' }}>FOL-{solicitud.id}</div>
                                            <div className="text-[11px] font-bold theme-text-muted mt-0.5 uppercase flex items-center gap-1"><User className="w-3 h-3" /> {solicitud.vendedor?.name}</div>
                                        </div>
                                        <div className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border ${estatus.clase}`}><StatusIcon className="w-3.5 h-3.5" /><span className="text-[9px] font-black uppercase tracking-wider italic">{estatus.label}</span></div>
                                    </div>
                                    <div>
                                        <div className="flex items-center gap-2 mb-1.5">
                                            <span className="text-[10px] font-black px-2 py-0.5 rounded bg-black/5 dark:bg-white/5 border theme-border theme-text-main">{solicitud.cliente?.numero_cliente || 'N/A'}</span>
                                            {solicitud.cliente?.numero_cliente && (<button onClick={(e) => copiarAlPortapapeles(e, solicitud.cliente.numero_cliente, solicitud.id)} className="p-1 theme-text-muted hover:text-[var(--color-primario)] transition-colors outline-none" title="Copiar ID">{copiadoId === solicitud.id ? <Check className="w-3 h-3 text-emerald-500" /> : <Copy className="w-3 h-3" />}</button>)}
                                            {esHeredado && <span className="text-[9px] font-black uppercase bg-purple-500/10 text-purple-500 border border-purple-500/20 px-2 py-0.5 rounded flex items-center gap-1"><ShieldAlert className="w-3 h-3" /> Heredado</span>}
                                        </div>
                                        <div className="font-bold text-base theme-text-main uppercase italic leading-tight">{solicitud.cliente?.nombre || 'Nuevo Prospecto'}</div>
                                    </div>
                                    <div className="bg-black/5 dark:bg-white/5 p-3 rounded-2xl border theme-border flex flex-col gap-2">
                                        <span className="text-[10px] font-black uppercase tracking-widest theme-text-main block">{nombreProceso}</span>
                                        <EtiquetasOperacion solicitud={solicitud} listas={listas} />
                                    </div>
                                    <FeedbackYComentarios solicitud={solicitud} />
                                    <div className="flex items-center justify-between pt-2 border-t theme-border">
                                        <div>
                                            <div className="font-black italic theme-text-main text-sm">{new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(solicitud.monto_cotizado)}</div>
                                            <div className={`mt-1 flex items-center gap-1 text-[9px] font-black uppercase tracking-widest ${solicitud.pago_confirmado ? 'text-emerald-500' : 'text-amber-500'}`}>{solicitud.pago_confirmado ? <CheckCircle2 className="w-3 h-3" /> : <Clock className="w-3 h-3" />} {solicitud.pago_confirmado ? 'Pago Confirmado' : 'Pago Pendiente'}</div>
                                        </div>
                                        <button onClick={(e) => abrirMenu(e, solicitud)} className="p-2.5 theme-element border theme-border hover:border-[var(--color-primario)] rounded-xl transition-all shadow-sm outline-none"><MoreVertical className="w-5 h-5 theme-text-main" /></button>
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>

                <div className="hidden lg:block animate-page-reveal theme-surface rounded-[2.5rem] border theme-border shadow-2xl overflow-hidden bg-white/70 dark:bg-[#121212]/70 backdrop-blur-xl" style={{ animationDelay: '200ms' }}>
                    <div className="overflow-x-auto pb-4 custom-scrollbar">
                        <table className="w-full text-left border-collapse min-w-[1000px]">
                            <thead>
                                <tr className="border-b theme-border">
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Folio & Asesor_</th>
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Cliente_</th>
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Detalles de Operación_</th>
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Cotización_</th>
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted">Estado_</th>
                                    <th className="p-6 text-[10px] font-black uppercase tracking-widest theme-text-muted text-center sticky-actions">Acciones_</th>
                                </tr>
                            </thead>
                            <tbody>
                                {solicitudesFiltradas.map((solicitud) => {
                                    const estatus = obtenerEstiloEstado(solicitud.estado?.nombre); const StatusIcon = estatus.icon; const nombreProceso = solicitud.proceso?.nombre || ''; const esHeredado = solicitud.cliente?.es_heredado;
                                    return (
                                        <tr key={solicitud.id} className="border-b theme-border transition-colors hover:bg-black/5 dark:hover:bg-white/5 group">
                                            <td className="p-6 align-top">
                                                <div className="font-black text-sm theme-text-main" style={{ color: 'var(--color-primario)' }}>FOL-{solicitud.id}</div>
                                                <div className="text-[10px] font-bold theme-text-muted mt-1 uppercase"><User className="w-3 h-3 inline mr-1" /> {solicitud.vendedor?.name}</div>
                                            </td>
                                            <td className="p-6 align-top">
                                                <div className="flex items-center gap-2 mb-1">
                                                    <span className="text-[10px] font-black px-2 py-0.5 rounded bg-black/5 dark:bg-white/5 border theme-border theme-text-main">{solicitud.cliente?.numero_cliente || 'N/A'}</span>
                                                    {solicitud.cliente?.numero_cliente && (<button onClick={(e) => copiarAlPortapapeles(e, solicitud.cliente.numero_cliente, solicitud.id)} className="p-1 theme-text-muted hover:text-[var(--color-primario)] transition-colors outline-none" title="Copiar ID">{copiadoId === solicitud.id ? <Check className="w-3 h-3 text-emerald-500" /> : <Copy className="w-3 h-3" />}</button>)}
                                                    {esHeredado && <span className="text-[9px] font-black uppercase bg-purple-500/10 text-purple-500 border border-purple-500/20 px-2 py-0.5 rounded flex items-center gap-1"><ShieldAlert className="w-3 h-3" /> Heredado</span>}
                                                </div>
                                                <div className="font-bold text-sm theme-text-main uppercase italic truncate max-w-[200px]">{solicitud.cliente?.nombre || 'Nuevo Prospecto'}</div>
                                            </td>
                                            <td className="p-6 align-top">
                                                <div className="inline-block px-3 py-1 rounded-lg theme-element border theme-border text-[9px] font-black uppercase tracking-widest theme-text-main mb-2">{nombreProceso}</div>
                                                <EtiquetasOperacion solicitud={solicitud} listas={listas} />
                                                <FeedbackYComentarios solicitud={solicitud} />
                                            </td>
                                            <td className="p-6 align-top">
                                                <div className="font-black italic theme-text-main text-sm bg-black/5 dark:bg-white/5 px-3 py-1.5 rounded-lg inline-block border theme-border">{new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(solicitud.monto_cotizado)}</div>
                                                <div className={`mt-2 flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-md w-fit border ${solicitud.pago_confirmado ? 'text-emerald-500 bg-emerald-500/10 border-emerald-500/20' : 'text-amber-500 bg-amber-500/10 border-amber-500/20'}`}>{solicitud.pago_confirmado ? <CheckCircle2 className="w-3 h-3" /> : <Clock className="w-3 h-3" />} {solicitud.pago_confirmado ? 'Confirmado' : 'Pendiente'}</div>
                                            </td>
                                            <td className="p-6 align-top">
                                                <div className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border ${estatus.clase} whitespace-nowrap shadow-sm`}><StatusIcon className="w-4 h-4" /><span className="text-[10px] font-black uppercase tracking-wider italic">{estatus.label}</span></div>
                                            </td>
                                            <td className="p-6 text-center sticky-actions group-hover:bg-slate-50 dark:group-hover:bg-white/5 transition-colors align-top">
                                                <button onClick={(e) => abrirMenu(e, solicitud)} className="p-3 theme-element border theme-border hover:border-[var(--color-primario)] rounded-2xl transition-all shadow-sm outline-none"><MoreVertical className="w-5 h-5 theme-text-main" /></button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>

                <Paginacion solicitudes={solicitudes} onIrAPagina={irAPagina} />
            </div>

            {modalForm.abierto && <ModalFormSolicitud onClose={() => setModalForm({ ...modalForm, abierto: false })} procesos={procesos} listas={listas} tiposCliente={tipos_cliente} modoEdicion={modalForm.modoEdicion} solicitudAEditar={modalForm.solicitud} />}
            {modalRespuesta.abierto && <ModalRespuestaSolicitud onClose={() => setModalRespuesta({ ...modalRespuesta, abierto: false })} solicitud={modalRespuesta.solicitud} estadoId={modalRespuesta.estadoId} />}
            {modalBitacora.abierto && <ModalBitacoraSolicitud onClose={() => setModalBitacora({ ...modalBitacora, abierto: false })} solicitud={modalBitacora.solicitud} listas={listas} tiposCliente={tipos_cliente} />}
        </AppLayout>
    );
}