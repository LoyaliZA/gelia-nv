import React, { useCallback, useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Clock, Plus, MoreVertical, Edit2, CheckCircle2, AlertOctagon,
    History, CheckSquare, CreditCard, User, Copy, Check, Tag, TrendingUp, ShieldAlert, Users,
    ChevronLeft, ChevronRight, Trash2, FileImage, X, MessageSquare, AlertTriangle, Eye, Ban, XCircle,
    FileSpreadsheet, FileText, FolderOpen, Download, Calculator
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaLoader from '../../Components/GeliaLoader';

import ModalFormSolicitud from './Partials/ModalFormSolicitud';
import ModalRespuestaSolicitud from './Partials/ModalRespuestaSolicitud';
import ModalBitacoraSolicitud from './Partials/ModalBitacoraSolicitud';
import ModalConsultaSolicitud from './Partials/ModalConsultaSolicitud';
import ModalRespuestaConsulta from './Partials/ModalRespuestaConsulta';
import ModalEjercicioEscalonamiento from './Partials/ModalEjercicioEscalonamiento';
import FiltrosSolicitudes from '@/Components/Filtros/FiltrosSolicitudes';
import useFiltrosSolicitudesPage from '@/hooks/useFiltrosSolicitudesPage';
import { geliaCardClass } from '../../utils/geliaTheme';
import { badgeClaseEstadoSolicitud } from './Partials/solicitudesStyles';
import { recargarModuloInertia } from '../../utils/recargarModuloInertia';

const PROPS_LISTADO = ['solicitudes', 'filtros'];

// Función para calcular tiempo relativo y formatear lecturas de marcas de tiempo
const formatearTiempoRelativo = (fechaString) => {
    if (!fechaString) return '';
    const fecha = new Date(fechaString);
    const ahora = new Date();
    const diffMs = ahora - fecha;
    const diffMinutos = Math.floor(diffMs / 60000);
    const diffHoras = Math.floor(diffMinutos / 60);
    const diffDias = Math.floor(diffHoras / 24);

    const esHoy = fecha.getDate() === ahora.getDate() &&
        fecha.getMonth() === ahora.getMonth() &&
        fecha.getFullYear() === ahora.getFullYear();

    const ayer = new Date();
    ayer.setDate(ayer.getDate() - 1);
    const esAyer = fecha.getDate() === ayer.getDate() &&
        fecha.getMonth() === ayer.getMonth() &&
        fecha.getFullYear() === ayer.getFullYear();

    const horaFormateada = fecha.toLocaleTimeString('es-MX', { hour: 'numeric', minute: '2-digit', hour12: true });

    if (diffMinutos < 1) return 'Hace menos de un minuto';
    if (diffMinutos < 60) return `Hace ${diffMinutos} minutos`;
    if (diffHoras < 24 && esHoy) return `Hoy a las ${horaFormateada}`;
    if (esAyer) return `Ayer a las ${horaFormateada}`;
    if (diffDias < 7) return `Hace ${diffDias} días`;

    return `${fecha.toLocaleDateString('es-MX', { day: '2-digit', month: 'short' })} a las ${horaFormateada}`;
};

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
            {solicitud.compra_en_tienda && (
                <span className="text-[9px] font-black uppercase px-2 py-1 rounded-md bg-[#cd7f32]/15 text-[#b87333] dark:text-[#daa520] border border-[#cd7f32]/30 flex items-center gap-1">
                    Compra en tienda · Bronce
                </span>
            )}
            {solicitud.cancelacion_solicitada_at && solicitud.estado?.nombre !== 'Cancelada' && (
                <span className="text-[9px] font-black uppercase px-2 py-1 rounded-md bg-red-500/10 text-red-600 border border-red-500/20 flex items-center gap-1">
                    <Ban className="w-3 h-3" /> Cancelación solicitada
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
// COMPONENTE: RESPUESTA DE CONSULTA (VENDEDORA)
// =============================================
const RespuestaConsultaEncargada = ({ solicitud, auth, onMarcarLeido, procesando }) => {
    const consulta = (solicitud.consultas || [])
        .filter(c => c.estado === 'respondida' && !c.leido_vendedor_at)
        .sort((a, b) => new Date(b.updated_at) - new Date(a.updated_at))[0];

    if (!consulta || solicitud.vendedor_id !== auth?.user?.id) return null;

    const temas = [consulta.consulta_tag && 'TAG', consulta.consulta_lista && 'Lista'].filter(Boolean);
    const esPositiva = consulta.respuesta_positiva;

    return (
        <div className={`mt-3 p-4 rounded-2xl border flex flex-col gap-3 shadow-sm ${esPositiva ? 'bg-emerald-500/10 border-emerald-500/25' : 'bg-red-500/10 border-red-500/25'}`}>
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-start gap-2 flex-1">
                    <MessageSquare className={`w-4 h-4 shrink-0 mt-0.5 ${esPositiva ? 'text-emerald-500' : 'text-red-500'}`} />
                    <div className="flex-1">
                        <p className={`text-[9px] font-black uppercase tracking-widest mb-1 ${esPositiva ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'}`}>
                            Respuesta de encargada · {temas.join(' + ')}
                        </p>
                        <p className="text-[10px] font-bold theme-text-muted mb-1">
                            {consulta.encargada?.name || 'Encargada'} · {esPositiva ? 'Confirmada' : 'Rechazada'}
                        </p>
                        {consulta.comentario_encargada && (
                            <p className="text-xs font-bold theme-text-main italic leading-tight">{consulta.comentario_encargada}</p>
                        )}
                    </div>
                </div>
                <button
                    type="button"
                    disabled={procesando}
                    onClick={() => onMarcarLeido(solicitud.id, consulta.id)}
                    className="shrink-0 flex items-center gap-1.5 px-3 py-2 rounded-xl text-[9px] font-black uppercase tracking-widest text-white transition-transform hover:scale-105 disabled:opacity-50"
                    style={{ backgroundColor: 'var(--color-primario)' }}
                >
                    <Eye className="w-3.5 h-3.5" /> Leído
                </button>
            </div>
            {consulta.evidencia_respuesta_path && (
                <VisorImagenHover path={consulta.evidencia_respuesta_path} />
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
        ? 'bg-amber-500/10 border-amber-500/25'
        : (esError ? 'bg-red-500/10 border-red-500/25' : 'bg-emerald-500/10 border-emerald-500/25');

    const Icono = esAlertaPago ? AlertTriangle : (esError ? AlertOctagon : CheckCircle2);
    const colorIcono = esAlertaPago ? 'text-amber-500' : (esError ? 'text-red-500' : 'text-emerald-500');
    const colorTexto = esAlertaPago ? 'text-amber-600 dark:text-amber-400' : (esError ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400');
    const titulo = esAlertaPago ? 'ALERTA DE AJUSTE DE LISTA' : (esError ? 'Corrección Requerida' : 'Resolución Admin');

    return (
        <div className="mt-3 flex flex-col gap-2">
            {tieneObservacion && !esAlertaPago && (
                <div className="p-3 rounded-2xl border theme-element theme-border flex items-start gap-2 shadow-sm">
                    <MessageSquare className="w-4 h-4 theme-text-muted mt-0.5 shrink-0" />
                    <div>
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-0.5">Nota de Vendedora</p>
                        <p className="text-xs font-bold theme-text-main italic leading-snug">{solicitud.observaciones_vendedor}</p>
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

const esProcesoCambioLista = (solicitud) =>
    (solicitud?.proceso?.nombre || '').toUpperCase().includes('LISTA');

const obtenerListaRebajaNombre = (solicitud) =>
    solicitud?.lista_rebaja?.nombre || solicitud?.listaRebaja?.nombre || null;

const listasInferioresParaCancelacion = (listas, solicitud) => {
    const listaCliente = solicitud?.cliente?.lista_descuento || solicitud?.cliente?.listaDescuento;
    const listaSolicitud = solicitud?.lista_descuento || solicitud?.listaDescuento;
    const ref = Math.max(
        parseFloat(listaCliente?.monto_requerido ?? 0),
        parseFloat(listaSolicitud?.monto_requerido ?? 0),
    );
    if (ref <= 0) return [];

    return (listas || []).filter(l =>
        l.activo !== false &&
        !l.nombre?.toUpperCase().includes('COLABORADOR') &&
        !l.nombre?.toUpperCase().includes('PLATAFORMAS') &&
        parseFloat(l.monto_requerido) < ref
    ).sort((a, b) => parseFloat(b.monto_requerido) - parseFloat(a.monto_requerido));
};

const tieneMotivoCancelacionVisible = (solicitud) => {
    const motivo = solicitud?.motivo_cancelacion?.trim();
    if (!motivo) return false;
    return !!solicitud.cancelacion_solicitada_at || solicitud.estado?.nombre === 'Cancelada';
};

const MotivoCancelacionBloque = ({ solicitud, compacto = false }) => {
    if (!tieneMotivoCancelacionVisible(solicitud)) return null;

    const pendiente = solicitud.cancelacion_solicitada_at && solicitud.estado?.nombre !== 'Cancelada';
    const titulo = pendiente ? 'Motivo de cancelación solicitada' : 'Motivo de cancelación';
    const fechaSolicitud = solicitud.cancelacion_solicitada_at
        ? formatearTiempoRelativo(solicitud.cancelacion_solicitada_at)
        : null;
    const listaRebajaNombre = obtenerListaRebajaNombre(solicitud);

    return (
        <div
            className={`${compacto ? 'mt-2' : 'mt-3'} p-3 rounded-2xl border flex flex-col gap-1.5 shadow-sm ${
                pendiente
                    ? 'bg-red-500/10 border-red-500/25'
                    : 'theme-element theme-border'
            }`}
        >
            <div className="flex items-start gap-2">
                <Ban className={`w-4 h-4 shrink-0 mt-0.5 ${pendiente ? 'text-red-500' : 'theme-text-muted'}`} />
                <div className="flex-1 min-w-0">
                    <p className={`text-[9px] font-black uppercase tracking-widest mb-0.5 ${pendiente ? 'text-red-600 dark:text-red-400' : 'theme-text-muted'}`}>
                        {titulo}
                    </p>
                    {fechaSolicitud && pendiente && (
                        <p className="text-[9px] font-bold theme-text-muted mb-1">Solicitada {fechaSolicitud}</p>
                    )}
                    {listaRebajaNombre && (
                        <p className={`text-[10px] font-black uppercase tracking-widest mb-1 ${pendiente ? 'text-red-700 dark:text-red-300' : 'theme-text-muted'}`}>
                            Lista rebaja: {listaRebajaNombre}
                        </p>
                    )}
                    <p className={`text-xs font-bold leading-snug whitespace-pre-wrap break-words ${pendiente ? 'text-red-800 dark:text-red-200' : 'theme-text-main italic'}`}>
                        {solicitud.motivo_cancelacion}
                    </p>
                </div>
            </div>
        </div>
    );
};

const ModalConfirmarCancelacion = ({ onClose, onExito, solicitud, onProcesando }) => {
    const { put, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();
        onProcesando?.(true);
        put(route('solicitudes.cancelar', solicitud.id), {
            onSuccess: () => { onExito?.(); onClose(); },
            onFinish: () => onProcesando?.(false),
        });
    };

    return createPortal(
        <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md" onClick={onClose}>
            <div className="w-full max-w-md theme-surface border theme-border rounded-[2rem] p-8 shadow-2xl relative" onClick={e => e.stopPropagation()}>
                <button onClick={onClose} className="absolute top-4 right-4 p-2 theme-text-muted hover:theme-text-main rounded-xl outline-none"><X className="w-5 h-5" /></button>
                <div className="flex items-center gap-3 mb-4">
                    <XCircle className="w-6 h-6 text-red-500" />
                    <h3 className="text-xl font-black italic theme-text-main uppercase m-0">Confirmar Cancelación</h3>
                </div>
                <p className="text-sm theme-text-muted mb-4">
                    FOL-{solicitud.id} — Se revertirán los cambios al cliente si la solicitud ya fue aprobada.
                </p>
                <MotivoCancelacionBloque solicitud={solicitud} compacto />
                <form onSubmit={submit} className="mt-6 flex flex-col gap-3">
                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full py-4 text-white rounded-xl font-black uppercase text-[11px] tracking-widest bg-red-600 hover:bg-red-700 transition-all shadow-lg outline-none disabled:opacity-50"
                    >
                        Confirmar cancelación
                    </button>
                    <button
                        type="button"
                        onClick={onClose}
                        className="w-full py-3 rounded-xl font-black uppercase text-[10px] tracking-widest theme-element border theme-border theme-text-muted hover:theme-text-main transition-colors outline-none"
                    >
                        Volver
                    </button>
                </form>
            </div>
        </div>,
        document.body
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

const ModalSolicitarCancelacion = ({ onClose, onExito, solicitud, listas = [] }) => {
    const esCambioLista = esProcesoCambioLista(solicitud);
    const listasInferiores = listasInferioresParaCancelacion(listas, solicitud);
    const { data, setData, post, processing } = useForm({
        motivo_cancelacion: '',
        catalogo_lista_rebaja_id: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('solicitudes.solicitar_cancelacion', solicitud.id), {
            onSuccess: () => { onExito?.(); onClose(); },
        });
    };

    return createPortal(
        <div className="fixed inset-0 z-[200] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md" onClick={onClose}>
            <div className="w-full max-w-md theme-surface border theme-border rounded-[2rem] p-8 shadow-2xl relative" onClick={e => e.stopPropagation()}>
                <button onClick={onClose} className="absolute top-4 right-4 p-2 theme-text-muted hover:theme-text-main rounded-xl outline-none"><X className="w-5 h-5" /></button>
                <div className="flex items-center gap-3 mb-6">
                    <Ban className="w-6 h-6 text-red-500" />
                    <h3 className="text-xl font-black italic theme-text-main uppercase m-0">Solicitar Cancelación</h3>
                </div>
                <p className="text-sm theme-text-muted mb-4">FOL-{solicitud.id} — La encargada o administrador deberá confirmar la cancelación.</p>
                <form onSubmit={submit} className="space-y-4">
                    {esCambioLista && (
                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">
                                Lista a la que debe rebajarse el cliente
                            </label>
                            {listasInferiores.length > 0 ? (
                                <select
                                    required
                                    value={data.catalogo_lista_rebaja_id}
                                    onChange={e => setData('catalogo_lista_rebaja_id', e.target.value)}
                                    className="w-full px-4 py-3 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2"
                                >
                                    <option value="">Selecciona una lista inferior...</option>
                                    {listasInferiores.map(l => (
                                        <option key={l.id} value={l.id}>{l.nombre}</option>
                                    ))}
                                </select>
                            ) : (
                                <p className="text-xs font-bold text-red-600 dark:text-red-400 italic">
                                    No hay listas inferiores disponibles para este folio.
                                </p>
                            )}
                            <p className="text-[10px] theme-text-muted italic">Solo se permiten listas con nivel inferior al actual o al solicitado.</p>
                        </div>
                    )}
                    <textarea
                        required
                        minLength={10}
                        value={data.motivo_cancelacion}
                        onChange={e => setData('motivo_cancelacion', e.target.value)}
                        placeholder="Describe el motivo de la cancelación (mín. 10 caracteres)..."
                        rows={4}
                        className="w-full px-4 py-3 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none focus:ring-2 resize-none"
                    />
                    <button
                        type="submit"
                        disabled={processing || (esCambioLista && listasInferiores.length === 0)}
                        className="w-full py-4 text-white rounded-xl font-black uppercase text-[11px] tracking-widest bg-red-600 hover:bg-red-700 transition-all shadow-lg outline-none disabled:opacity-50"
                    >
                        Enviar Solicitud
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
const MenuAccionesPortal = ({ menuAbierto, menuSolicitud, menuPos, setMenuAbierto, setModalForm, setModalRespuesta, setModalBitacora, setModalConsulta, setModalRespuestaConsulta, abrirModalPago, confirmarCambioLista, confirmarRollback, eliminarSolicitud, abrirModalCancelacion, abrirModalConfirmarCancelacion, can, auth }) => {
    if (!menuAbierto || !menuSolicitud) return null;
    const solicitud = menuSolicitud;
    const esCancelada = solicitud.estado?.nombre === 'Cancelada';
    const estadosActivos = ['Pendiente', 'Respondida', 'Verificada'];

    const roles = auth?.user?.roles || [];
    const esGerente = roles.some(r => String(r).toLowerCase().includes('gerente'));
    const puedeReportarError = (can('solicitudes.reportar') || can('solicitudes.verificar') || esGerente || solicitud.vendedor_id === auth?.user?.id)
        && solicitud.estado?.nombre !== 'Incorrecta'
        && !esCancelada;

    const auditoriasOrdenadas = [...(solicitud.auditorias || [])].sort((a, b) => b.id - a.id);
    const ultimaAuditoria = auditoriasOrdenadas.find(a => !a.motivo_reporte?.toUpperCase().includes('AUTOMÁTICAMENTE'));
    const esAlertaPago = ultimaAuditoria?.motivo_reporte?.toUpperCase().includes('ALERTA DE PAGO');
    const esVencimiento = solicitud.motivo_incorrecta === 'vencimiento_pago';
    const consultaPendiente = (solicitud.consultas || []).find(c => c.estado === 'pendiente');
    const puedeConsultar = can('solicitudes.emitir_consulta')
        && solicitud.vendedor_id === auth.user.id
        && solicitud.estado?.nombre === 'Respondida'
        && !solicitud.pago_confirmado
        && !consultaPendiente
        && !esAlertaPago;

    const puedeSolicitarCancelacion = can('solicitudes.solicitar_cancelacion')
        && solicitud.vendedor_id === auth.user.id
        && estadosActivos.includes(solicitud.estado?.nombre)
        && !solicitud.cancelacion_solicitada_at
        && !esVencimiento
        && !esCancelada;

    const puedeConfirmarCancelacion = can('solicitudes.cancelar')
        && solicitud.cancelacion_solicitada_at
        && !esCancelada;

    return createPortal(
        <>
            <div className="fixed inset-0 z-[999]" onClick={() => setMenuAbierto(null)}></div>
            <div className={`fixed z-[1000] theme-surface border theme-border shadow-2xl rounded-2xl p-2 flex flex-col gap-1 backdrop-blur-xl animate-fade-in ${puedeConfirmarCancelacion && solicitud.motivo_cancelacion ? 'w-72' : 'w-56'}`} style={{ top: menuPos.top, left: menuPos.left }}>

                {/* Confirmar Cambio de Lista (Solo si hay alerta) */}
                {esAlertaPago && can('solicitudes.confirmar_cambio_lista') && (
                    <button onClick={() => { setMenuAbierto(null); confirmarCambioLista(solicitud.id); }} className="flex items-center gap-3 px-4 py-3 hover:bg-amber-50 dark:hover:bg-amber-500/10 text-amber-600 dark:text-amber-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors border-b theme-border mb-1 pb-3">
                        <TrendingUp className="w-4 h-4" /> Confirmar Ajuste
                    </button>
                )}

                {/* Reparar Solicitud (Solo vendedor, solo si está incorrecta y NO es vencimiento) */}
                {solicitud.vendedor_id === auth.user.id && solicitud.estado?.nombre === 'Incorrecta' && !esAlertaPago && !esVencimiento && (
                    <button onClick={() => { setMenuAbierto(null); setModalForm({ abierto: true, modoEdicion: true, solicitud }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-orange-50 dark:hover:bg-orange-500/10 text-orange-600 dark:text-orange-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors border-b theme-border mb-1 pb-3">
                        <Edit2 className="w-4 h-4" /> Reparar Solicitud
                    </button>
                )}

                {esVencimiento && solicitud.vendedor_id === auth.user.id && (
                    <div className="px-4 py-3 text-[9px] font-bold theme-text-muted uppercase tracking-widest border-b theme-border mb-1 pb-3 italic">
                        Pago vencido: debe iniciar una nueva solicitud
                    </div>
                )}

                {can('solicitudes.reportar') && esVencimiento && !solicitud.rollback_confirmado_at && (
                    <button onClick={() => { setMenuAbierto(null); confirmarRollback(solicitud.id); }} className="flex items-center gap-3 px-4 py-3 hover:bg-red-50 dark:hover:bg-red-500/10 text-red-600 dark:text-red-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors border-b theme-border mb-1 pb-3">
                        <ShieldAlert className="w-4 h-4" /> Confirmar Reversión
                    </button>
                )}

                {puedeConsultar && (
                    <button onClick={() => { setMenuAbierto(null); setModalConsulta({ abierto: true, solicitud }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-amber-50 dark:hover:bg-amber-500/10 text-amber-600 dark:text-amber-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors border-b theme-border mb-1 pb-3">
                        <MessageSquare className="w-4 h-4" /> Consultar TAG/Lista
                    </button>
                )}

                {can('solicitudes.responder_consulta') && consultaPendiente && (
                    <button onClick={() => { setMenuAbierto(null); setModalRespuestaConsulta({ abierto: true, solicitud, consulta: consultaPendiente }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-amber-50 dark:hover:bg-amber-500/10 text-amber-600 dark:text-amber-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors border-b theme-border mb-1 pb-3">
                        <MessageSquare className="w-4 h-4" /> Responder Consulta
                    </button>
                )}

                {/* Confirmar Pago - solo procesos financieros */}
                {(can('solicitudes.confirmar_pago') || solicitud.vendedor_id === auth.user.id) && !solicitud.pago_confirmado && solicitud.estado?.nombre === 'Respondida' && !esAlertaPago && (
                    <button onClick={() => { setMenuAbierto(null); abrirModalPago(solicitud); }} className="flex items-center gap-3 px-4 py-3 hover:bg-blue-50 dark:hover:bg-blue-500/10 text-blue-600 dark:text-blue-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors border-b theme-border mb-1 pb-3">
                        <CreditCard className="w-4 h-4" /> Confirmar Pago
                    </button>
                )}

                {puedeSolicitarCancelacion && (
                    <button onClick={() => { setMenuAbierto(null); abrirModalCancelacion(solicitud); }} className="flex items-center gap-3 px-4 py-3 hover:bg-red-50 dark:hover:bg-red-500/10 text-red-600 dark:text-red-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors border-b theme-border mb-1 pb-3">
                        <Ban className="w-4 h-4" /> Solicitar Cancelación
                    </button>
                )}

                {puedeConfirmarCancelacion && solicitud.motivo_cancelacion && (
                    <div className="px-3 py-2 mb-1 border-b theme-border">
                        <p className="text-[8px] font-black uppercase tracking-widest text-red-600 dark:text-red-400 mb-1">Motivo del vendedor</p>
                        {obtenerListaRebajaNombre(solicitud) && (
                            <p className="text-[9px] font-black uppercase tracking-widest text-red-700 dark:text-red-300 mb-1">
                                Lista rebaja: {obtenerListaRebajaNombre(solicitud)}
                            </p>
                        )}
                        <p className="text-[10px] font-bold theme-text-main leading-snug line-clamp-4 italic">
                            {solicitud.motivo_cancelacion}
                        </p>
                    </div>
                )}

                {puedeConfirmarCancelacion && (
                    <button onClick={() => { setMenuAbierto(null); abrirModalConfirmarCancelacion(solicitud); }} className="flex items-center gap-3 px-4 py-3 hover:bg-red-50 dark:hover:bg-red-500/10 text-red-600 dark:text-red-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors border-b theme-border mb-1 pb-3">
                        <XCircle className="w-4 h-4" /> Confirmar Cancelación
                    </button>
                )}

                {/* Verificado (Paso final de la auxiliar) */}
                {can('solicitudes.verificar') && !esAlertaPago && !esCancelada && solicitud.estado?.nombre !== 'Incorrecta' && (
                    <button onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud, estadoId: 3 }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors">
                        <CheckSquare className="w-4 h-4" /> Verificado
                    </button>
                )}

                {/* Aprobar (Encargada) — solo Pendiente */}
                {can('solicitudes.reportar') && !esAlertaPago && !esCancelada && solicitud.estado?.nombre === 'Pendiente' && (
                    <button onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud, estadoId: 2 }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-black/5 dark:hover:bg-white/5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors border-b theme-border mb-1 pb-3" style={{ color: 'var(--color-primario)' }}>
                        <CheckCircle2 className="w-4 h-4" /> Aprobar Proceso
                    </button>
                )}

                {/* Reportar error — encargadas, auxiliares y gerentes en cualquier etapa activa */}
                {puedeReportarError && !esAlertaPago && (
                    <button onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud, estadoId: 4 }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-red-50 dark:hover:bg-red-500/10 text-red-600 dark:text-red-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors border-b theme-border mb-1 pb-3">
                        <AlertOctagon className="w-4 h-4" /> Reportar Error
                    </button>
                )}

                {/* Bitácora */}
                {can('configuracion.ver_auditoria') && (
                    <button onClick={() => { setMenuAbierto(null); setModalBitacora({ abierto: true, solicitud }); }} className="flex items-center gap-3 px-4 py-3 hover:bg-purple-50 dark:hover:bg-purple-500/10 text-purple-600 dark:text-purple-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors border-t theme-border mt-1 pt-3">
                        <History className="w-4 h-4" /> Ver Bitácora
                    </button>
                )}

                {/* Eliminar */}
                {can('solicitudes.eliminar') && !solicitud.deleted_at && (
                    <button onClick={() => eliminarSolicitud(solicitud.id)} className="flex items-center gap-3 px-4 py-3 hover:bg-red-900/10 text-red-600 dark:text-red-500 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors border-t theme-border mt-1 pt-3">
                        <Trash2 className="w-4 h-4" /> Eliminar Registro
                    </button>
                )}

                {/* Restaurar */}
                {solicitud.deleted_at && can('solicitudes.eliminadas') && (
                    <button onClick={() => { setMenuAbierto(null); restaurarSolicitud(solicitud.id); }} className="flex items-center gap-3 px-4 py-3 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors border-t theme-border mt-1 pt-3">
                        <CheckCircle2 className="w-4 h-4" /> Restaurar Solicitud
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
        <div className={`${geliaCardClass('rounded-[2rem]')} p-4 flex flex-col sm:flex-row items-center justify-between gap-4`} style={{ animationDelay: '300ms' }}>
            <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Viendo {desde} al {hasta} de {totalRegistros.toLocaleString('es-MX')}</span>
            <div className="flex items-center gap-2">
                <button onClick={() => onIrAPagina(paginaActual - 1)} disabled={paginaActual === 1} className="paginacion-btn theme-surface border theme-border theme-text-muted hover:border-[var(--color-primario)] hover:text-[var(--color-primario)]"><ChevronLeft className="w-4 h-4" /></button>
                {generarPaginas().map((p, i) => p === '...' ? (<span key={`dots-${i}`} className="w-10 text-center text-[11px] font-black theme-text-muted">…</span>) : (<button key={p} onClick={() => onIrAPagina(p)} className={`paginacion-btn theme-border ${p === paginaActual ? '' : 'theme-surface theme-text-main hover:border-[var(--color-primario)] hover:text-[var(--color-primario)]'}`} style={p === paginaActual ? { backgroundColor: 'var(--color-primario)', color: '#fff', borderColor: 'var(--color-primario)' } : {}}>{p}</button>))}
                <button onClick={() => onIrAPagina(paginaActual + 1)} disabled={paginaActual === totalPaginas} className="paginacion-btn theme-surface border theme-border theme-text-muted hover:border-[var(--color-primario)] hover:text-[var(--color-primario)]"><ChevronRight className="w-4 h-4" /></button>
            </div>
        </div>
    );
};

export default function Index({
    solicitudes = { total: 0, data: [], current_page: 1, last_page: 1, per_page: 10, from: 1, to: 10 },
    procesos = [],
    listas = [],
    tipos_cliente = [],
    vendedores = [],
    bancos = [],
    filtros = {},
    auth
}) {

    const [modalForm, setModalForm] = useState({ abierto: false, modoEdicion: false, solicitud: null });
    const [modalRespuesta, setModalRespuesta] = useState({ abierto: false, solicitud: null, estadoId: null });
    const [modalBitacora, setModalBitacora] = useState({ abierto: false, solicitud: null });
    const [modalConsulta, setModalConsulta] = useState({ abierto: false, solicitud: null });
    const [modalRespuestaConsulta, setModalRespuestaConsulta] = useState({ abierto: false, solicitud: null, consulta: null });
    const [modalPago, setModalPago] = useState({ abierto: false, solicitud: null });
    const [modalCancelacion, setModalCancelacion] = useState({ abierto: false, solicitud: null });
    const [modalConfirmarCancelacion, setModalConfirmarCancelacion] = useState({ abierto: false, solicitud: null });
    const [modalEscalonamiento, setModalEscalonamiento] = useState(false);
    const [menuAbierto, setMenuAbierto] = useState(null);
    const [menuPos, setMenuPos] = useState({ top: 0, left: 0 });
    const [menuSolicitud, setMenuSolicitud] = useState(null);
    const [copiadoId, setCopiadoId] = useState(null);
    const [procesandoAccion, setProcesandoAccion] = useState(false);

    const {
        tabActiva,
        busqueda,
        tipoFecha,
        fechaInicio,
        fechaFin,
        filtroVendedor,
        filtroMotivo,
        filtrosAdicionalesActivos,
        construirParams,
        exportParams,
        aplicarFiltros,
        limpiarFiltrosAdicionales,
    } = useFiltrosSolicitudesPage({
        filtros,
        rutaIndex: route('solicitudes.index'),
        onInicioConsulta: () => setProcesandoAccion(true),
        onFinConsulta: () => setProcesandoAccion(false),
    });

    const can = (permiso) => auth?.user?.permissions?.includes(permiso) ?? false;
    const puedeExportar = can('solicitudes.exportar');

    const recargarTrasAccion = useCallback(() => {
        recargarModuloInertia(PROPS_LISTADO);
    }, []);

    const eliminarSolicitud = (id) => {
        const motivo = window.prompt("ATENCIÓN: Se eliminará este registro y se creará un respaldo en la auditoría.\n\nIngresa el motivo de la eliminación (Mínimo 10 caracteres):");
        if (motivo === null) return;
        if (motivo.trim().length < 10) { alert("Operación cancelada: El motivo debe tener al menos 10 caracteres."); return; }
        setMenuAbierto(null); setProcesandoAccion(true);
        router.delete(route('solicitudes.destroy', id), {
            data: { motivo: motivo.trim() },
            preserveScroll: true,
            onSuccess: recargarTrasAccion,
            onFinish: () => setProcesandoAccion(false),
        });
    };

    const marcarConsultaLeida = (solicitudId, consultaId) => {
        setProcesandoAccion(true);
        router.put(route('solicitudes.consultas.leer', [solicitudId, consultaId]), {}, {
            preserveScroll: true,
            onSuccess: recargarTrasAccion,
            onFinish: () => setProcesandoAccion(false),
        });
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
        router.put(route('solicitudes.confirmar_pago', id), formData, {
            preserveScroll: true,
            onSuccess: recargarTrasAccion,
            onFinish: () => setProcesandoAccion(false),
        });
    };

    const confirmarCambioLista = (id) => {
        if (window.confirm('¿Confirmar el ajuste de lista para este cliente?')) {
            setProcesandoAccion(true);
            router.put(route('solicitudes.confirmar_lista', id), {}, {
                preserveScroll: true,
                onSuccess: recargarTrasAccion,
                onFinish: () => setProcesandoAccion(false),
            });
        }
    };

    const confirmarRollback = (id) => {
        if (window.confirm('¿Confirmar la reversión de cambios por vencimiento de pago? La vendedora deberá iniciar una nueva solicitud.')) {
            setProcesandoAccion(true);
            router.put(route('solicitudes.confirmar_rollback', id), {}, {
                preserveScroll: true,
                onSuccess: recargarTrasAccion,
                onFinish: () => setProcesandoAccion(false),
            });
        }
    };

    const restaurarSolicitud = (id) => {
        if (window.confirm("ATENCIÓN: ¿Estás seguro de que deseas restaurar esta solicitud a modo informativo?")) {
            setMenuAbierto(null); setProcesandoAccion(true);
            router.put(route('solicitudes.restaurar', id), {}, {
                preserveScroll: true,
                onSuccess: recargarTrasAccion,
                onFinish: () => setProcesandoAccion(false),
            });
        }
    };

    const abrirMenu = (e, solicitud) => {
        const btn = e.currentTarget; const rect = btn.getBoundingClientRect(); const menuWidth = 224; const menuHeight = 220;
        const spaceBelow = window.innerHeight - rect.bottom; const openUpward = spaceBelow < menuHeight + 16;
        let top = openUpward ? rect.top - menuHeight - 8 : rect.bottom + 8; let left = rect.right - menuWidth; if (left < 8) left = 8;
        setMenuPos({ top, left }); setMenuSolicitud(solicitud); setMenuAbierto(menuAbierto === solicitud.id ? null : solicitud.id);
    };

    const solicitudesFiltradas = solicitudes.data || [];

    const obtenerEstiloEstado = (nombreEstado) => {
        switch (nombreEstado?.toLowerCase()) {
            case 'respondida': return { clase: badgeClaseEstadoSolicitud('respondida'), icon: CheckCircle2, label: 'Aprobado' };
            case 'incorrecta': return { clase: badgeClaseEstadoSolicitud('incorrecta'), icon: AlertOctagon, label: 'Reporte' };
            case 'verificada': return { clase: badgeClaseEstadoSolicitud('verificada'), icon: CheckSquare, label: 'Verificada' };
            case 'cancelada': return { clase: badgeClaseEstadoSolicitud('cancelada'), icon: XCircle, label: 'Cancelada' };
            default: return { clase: badgeClaseEstadoSolicitud('revision'), icon: Clock, label: 'Pendiente' };
        }
    };

    const irAPagina = (pagina) => {
        const totalPaginas = solicitudes.last_page || 1;
        if (pagina < 1 || pagina > totalPaginas) return;
        setProcesandoAccion(true);
        router.get(route('solicitudes.index'), construirParams({ page: pagina }), {
            preserveState: true,
            preserveScroll: false,
            onFinish: () => setProcesandoAccion(false),
        });
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Panel de Solicitudes" />
            <GeliaLoader isVisible={procesandoAccion} message="Sincronizando_" />

            <MenuAccionesPortal
                menuAbierto={menuAbierto}
                menuSolicitud={menuSolicitud}
                menuPos={menuPos}
                setMenuAbierto={setMenuAbierto}
                setModalForm={setModalForm}
                setModalRespuesta={setModalRespuesta}
                setModalBitacora={setModalBitacora}
                setModalConsulta={setModalConsulta}
                setModalRespuestaConsulta={setModalRespuestaConsulta}
                abrirModalPago={(s) => setModalPago({ abierto: true, solicitud: s })}
                confirmarCambioLista={confirmarCambioLista}
                confirmarRollback={confirmarRollback}
                abrirModalConfirmarCancelacion={(s) => setModalConfirmarCancelacion({ abierto: true, solicitud: s })}
                abrirModalCancelacion={(s) => setModalCancelacion({ abierto: true, solicitud: s })}
                eliminarSolicitud={eliminarSolicitud}
                restaurarSolicitud={restaurarSolicitud}
                can={can}
                auth={auth}
            />
            {modalPago.abierto && <ModalConfirmarPago onClose={() => setModalPago({ abierto: false, solicitud: null })} solicitud={modalPago.solicitud} onConfirmar={confirmarPagoConMonto} />}
            {modalCancelacion.abierto && (
                <ModalSolicitarCancelacion
                    onClose={() => setModalCancelacion({ abierto: false, solicitud: null })}
                    solicitud={modalCancelacion.solicitud}
                    listas={listas}
                    onExito={recargarTrasAccion}
                />
            )}
            {modalConfirmarCancelacion.abierto && (
                <ModalConfirmarCancelacion
                    onClose={() => setModalConfirmarCancelacion({ abierto: false, solicitud: null })}
                    solicitud={modalConfirmarCancelacion.solicitud}
                    onProcesando={setProcesandoAccion}
                    onExito={recargarTrasAccion}
                />
            )}
            <div className="max-w-[1440px] mx-auto p-4 md:p-8 space-y-6 md:space-y-8">
                <header className={`${geliaCardClass()} p-6 md:p-12 flex flex-col md:flex-row items-center justify-between gap-4 md:gap-6`}>
                    <div className="w-full md:w-auto text-center md:text-left">
                        <div className="flex items-center justify-center md:justify-start space-x-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                            <p className="text-[10px] font-black uppercase tracking-[0.3em]" style={{ color: 'var(--color-primario)' }}>Panel General</p>
                        </div>
                        <h1 className="text-3xl md:text-5xl font-black italic tracking-tighter uppercase theme-text-main m-0">GESTIÓN DE <span style={{ color: 'var(--color-primario)' }}>SOLICITUDES</span></h1>
                    </div>
                    <div className="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-2 w-full md:w-auto">
                        {puedeExportar && (
                            <>
                                <a
                                    href={route('reportes.solicitudes.exportar', { ...exportParams, format: 'pdf' })}
                                    className="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border theme-border theme-element theme-text-main text-[10px] font-black uppercase tracking-widest hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] transition-all"
                                >
                                    <FileText className="w-4 h-4 shrink-0" /> PDF
                                </a>
                                <a
                                    href={route('reportes.solicitudes.exportar', { ...exportParams, format: 'xlsx' })}
                                    className="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border theme-border theme-element theme-text-main text-[10px] font-black uppercase tracking-widest hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] transition-all"
                                >
                                    <FileSpreadsheet className="w-4 h-4 shrink-0" /> Excel
                                </a>
                                <a
                                    href={route('reportes.solicitudes.exportar', { ...exportParams, format: 'csv' })}
                                    className="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border theme-border theme-element theme-text-main text-[10px] font-black uppercase tracking-widest hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] transition-all"
                                >
                                    <Download className="w-4 h-4 shrink-0" /> CSV
                                </a>
                                <Link
                                    href={route('reportes.solicitudes.index', exportParams)}
                                    className="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border theme-border theme-element theme-text-muted text-[10px] font-black uppercase tracking-widest hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] transition-all"
                                >
                                    Reportes
                                </Link>
                            </>
                        )}
                        {can('ejercicio_escalonamiento.ver') && (
                            <button
                                type="button"
                                onClick={() => setModalEscalonamiento(true)}
                                className="inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl border theme-border theme-element theme-text-main text-[10px] font-black uppercase tracking-widest hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] transition-all"
                            >
                                <Calculator className="w-4 h-4 shrink-0" /> Escalonamiento
                            </button>
                        )}
                        {can('solicitudes.crear') && (
                            <button onClick={() => setModalForm({ abierto: true, modoEdicion: false, solicitud: null })} className="flex items-center justify-center gap-2 px-8 py-4 text-white rounded-2xl font-black uppercase tracking-widest text-[11px] shadow-xl hover:scale-105 transition-all w-full md:w-auto" style={{ backgroundColor: 'var(--color-primario)' }}><Plus className="w-5 h-5" /> Nueva Solicitud</button>
                        )}
                    </div>
                </header>

                <FiltrosSolicitudes
                    tabActiva={tabActiva}
                    busqueda={busqueda}
                    tipoFecha={tipoFecha}
                    fechaInicio={fechaInicio}
                    fechaFin={fechaFin}
                    filtroVendedor={filtroVendedor}
                    filtroMotivo={filtroMotivo}
                    vendedores={vendedores}
                    filtrosActivos={filtrosAdicionalesActivos}
                    idPrefixFechas="solicitud-fecha"
                    onCambiarTab={(tab) => aplicarFiltros({ tab })}
                    onAplicarFiltros={aplicarFiltros}
                    onLimpiarAdicionales={limpiarFiltrosAdicionales}
                    mostrarEliminadas={can('solicitudes.eliminadas')}
                />

                <div className="block lg:hidden space-y-4 animate-page-reveal" style={{ animationDelay: '200ms' }}>
                    {solicitudesFiltradas.length === 0 ? (<div className="theme-surface rounded-3xl p-8 text-center border theme-border theme-text-muted font-bold text-sm">No se encontraron solicitudes_</div>) : (
                        solicitudesFiltradas.map((solicitud) => {
                            const estatus = obtenerEstiloEstado(solicitud.estado?.nombre); const StatusIcon = estatus.icon; const nombreProceso = solicitud.proceso?.nombre || ''; const esHeredado = solicitud.cliente?.es_heredado;
                            return (
                                <div key={solicitud.id} className="theme-surface rounded-3xl border theme-border p-5 shadow-lg relative flex flex-col gap-4">
                                    <div className="flex items-start justify-between border-b theme-border pb-3">
                                        <div>
                                            <div className="font-black text-base" style={{ color: 'var(--color-primario)' }}>FOL-{solicitud.id}</div>
                                            <div className="text-[11px] font-bold theme-text-muted mt-0.5 uppercase flex items-center gap-1">
                                                <User className="w-3 h-3" /> {solicitud.vendedor?.name}
                                            </div>

                                            {/* Despliegue de indicadores de tiempo relativo */}
                                            <div className="mt-2 space-y-1">
                                                <div className="text-[9px] font-bold theme-text-muted flex items-center gap-1" title={solicitud.created_at}>
                                                    <Clock className="w-3 h-3" /> Emitida: {formatearTiempoRelativo(solicitud.created_at)}
                                                </div>

                                                {/* Se muestra solo si el registro ha sufrido modificaciones posteriores */}
                                                {solicitud.updated_at && solicitud.updated_at !== solicitud.created_at && (
                                                    <div className="text-[9px] font-bold text-blue-500/80 flex items-center gap-1" title={solicitud.updated_at}>
                                                        <History className="w-3 h-3" /> Actualizada: {formatearTiempoRelativo(solicitud.updated_at)}
                                                    </div>
                                                )}
                                            </div>
                                        </div>

                                        <div className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border ${estatus.clase}`}>
                                            <StatusIcon className="w-3.5 h-3.5" />
                                            <span className="text-[9px] font-black uppercase tracking-wider italic">{estatus.label}</span>
                                        </div>
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
                                    <RespuestaConsultaEncargada
                                        solicitud={solicitud}
                                        auth={auth}
                                        onMarcarLeido={marcarConsultaLeida}
                                        procesando={procesandoAccion}
                                    />
                                    <FeedbackYComentarios solicitud={solicitud} />
                                    <MotivoCancelacionBloque solicitud={solicitud} />
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

                <div className={`hidden lg:block ${geliaCardClass()} overflow-hidden`} style={{ animationDelay: '200ms' }}>
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
                                                <div className="text-[10px] font-bold theme-text-muted mt-1 uppercase">
                                                    <User className="w-3 h-3 inline mr-1" /> {solicitud.vendedor?.name}
                                                </div>

                                                {/* NUEVO: Despliegue de indicadores de tiempo relativo para Escritorio */}
                                                <div className="mt-2 space-y-1">
                                                    <div className="text-[9px] font-bold theme-text-muted flex items-center gap-1" title={solicitud.created_at}>
                                                        <Clock className="w-3 h-3" /> Emitida: {formatearTiempoRelativo(solicitud.created_at)}
                                                    </div>

                                                    {solicitud.updated_at && solicitud.updated_at !== solicitud.created_at && (
                                                        <div className="text-[9px] font-bold text-blue-500/80 flex items-center gap-1" title={solicitud.updated_at}>
                                                            <History className="w-3 h-3" /> Actualizada: {formatearTiempoRelativo(solicitud.updated_at)}
                                                        </div>
                                                    )}
                                                </div>
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
                                                {(solicitud.consultas || []).some(c => c.estado === 'pendiente') && (
                                                    <span className="inline-flex items-center gap-1 text-[9px] font-black uppercase px-2 py-1 rounded-md bg-amber-500/10 text-amber-500 border border-amber-500/20 mt-1">
                                                        <MessageSquare className="w-3 h-3" /> Consulta pendiente
                                                    </span>
                                                )}
                                                {solicitud.motivo_incorrecta && (
                                                    <span className="inline-flex items-center gap-1 text-[9px] font-black uppercase px-2 py-1 rounded-md bg-red-500/10 text-red-500 border border-red-500/20 mt-1">
                                                        <AlertOctagon className="w-3 h-3" /> {
                                                            solicitud.motivo_incorrecta === 'vencimiento_pago' ? 'Pago vencido'
                                                            : solicitud.motivo_incorrecta === 'error_reportado' ? 'Error reportado'
                                                            : solicitud.motivo_incorrecta === 'pago_insuficiente' ? 'Pago insuficiente'
                                                            : solicitud.motivo_incorrecta
                                                        }
                                                    </span>
                                                )}
                                                <RespuestaConsultaEncargada
                                                    solicitud={solicitud}
                                                    auth={auth}
                                                    onMarcarLeido={marcarConsultaLeida}
                                                    procesando={procesandoAccion}
                                                />
                                                <FeedbackYComentarios solicitud={solicitud} />
                                                <MotivoCancelacionBloque solicitud={solicitud} />
                                            </td>
                                            <td className="p-6 align-top">
                                                <div className="font-black italic theme-text-main text-sm bg-black/5 dark:bg-white/5 px-3 py-1.5 rounded-lg inline-block border theme-border">{new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(solicitud.monto_cotizado)}</div>
                                                <div className={`mt-2 flex items-center gap-1.5 text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded-md w-fit border ${solicitud.pago_confirmado ? 'text-emerald-500 bg-emerald-500/10 border-emerald-500/20' : 'text-amber-500 bg-amber-500/10 border-amber-500/20'}`}>{solicitud.pago_confirmado ? <CheckCircle2 className="w-3 h-3" /> : <Clock className="w-3 h-3" />} {solicitud.pago_confirmado ? 'Confirmado' : 'Pendiente'}</div>
                                            </td>
                                            <td className="p-6 align-top">
                                                <div className={`inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border ${estatus.clase} whitespace-nowrap shadow-sm`}><StatusIcon className="w-4 h-4" /><span className="text-[10px] font-black uppercase tracking-wider italic">{estatus.label}</span></div>
                                            </td>
                                            <td className="p-6 text-center sticky-actions group-hover:bg-black/5 dark:group-hover:bg-white/5 transition-colors align-top">
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

            {modalForm.abierto && <ModalFormSolicitud onClose={() => setModalForm({ ...modalForm, abierto: false })} onExito={recargarTrasAccion} procesos={procesos} listas={listas} tiposCliente={tipos_cliente} bancos={bancos} modoEdicion={modalForm.modoEdicion} solicitudAEditar={modalForm.solicitud} />}
            {modalRespuesta.abierto && <ModalRespuestaSolicitud onClose={() => setModalRespuesta({ ...modalRespuesta, abierto: false })} onExito={recargarTrasAccion} solicitud={modalRespuesta.solicitud} estadoId={modalRespuesta.estadoId} />}
            {modalBitacora.abierto && <ModalBitacoraSolicitud onClose={() => setModalBitacora({ ...modalBitacora, abierto: false })} solicitud={modalBitacora.solicitud} listas={listas} tiposCliente={tipos_cliente} />}
            {modalConsulta.abierto && <ModalConsultaSolicitud onClose={() => setModalConsulta({ ...modalConsulta, abierto: false })} onExito={recargarTrasAccion} solicitud={modalConsulta.solicitud} />}
            {modalRespuestaConsulta.abierto && <ModalRespuestaConsulta onClose={() => setModalRespuestaConsulta({ ...modalRespuestaConsulta, abierto: false })} onExito={recargarTrasAccion} solicitud={modalRespuestaConsulta.solicitud} consulta={modalRespuestaConsulta.consulta} />}
            {modalEscalonamiento && (
                <ModalEjercicioEscalonamiento
                    onClose={() => setModalEscalonamiento(false)}
                    listas={listas}
                />
            )}
        </AppLayout>
    );
}