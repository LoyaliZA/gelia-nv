import React, { useEffect, useState, useRef } from 'react';
import { createPortal } from 'react-dom';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Clock, Plus, MoreVertical, Edit2, CheckCircle2, AlertOctagon, Sparkles,
    History, CheckSquare, CreditCard, User, Copy, Check, Tag, TrendingUp, ShieldAlert, Users,
    ChevronLeft, ChevronRight, Trash2, FileImage, X, MessageSquare, AlertTriangle, Eye, Ban, XCircle,
    FileSpreadsheet, FileText, FolderOpen, Download, Calculator, ChevronDown
} from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaLoader from '../../Components/GeliaLoader';
import GeliaPageShell from '../../Components/GeliaPageShell';

import ModalFormSolicitud from './Partials/ModalFormSolicitud';
import ModalRespuestaSolicitud from './Partials/ModalRespuestaSolicitud';
import ModalBitacoraSolicitud from './Partials/ModalBitacoraSolicitud';
import ModalConsultaSolicitud from './Partials/ModalConsultaSolicitud';
import ModalRespuestaConsulta from './Partials/ModalRespuestaConsulta';
import ModalEjercicioEscalonamiento from './Partials/ModalEjercicioEscalonamiento';
import FiltrosSolicitudes from '@/Components/Filtros/FiltrosSolicitudes';
import useFiltrosSolicitudesPage from '@/hooks/useFiltrosSolicitudesPage';
import { GELIA_ESTADO_VIVO_TONO, THEME_BTN_PRIMARY } from '../../utils/geliaTheme';
import { badgeClaseEstadoSolicitud, claseEtiquetaLista, claseEtiquetaTipoCliente, esListaDiamante, PANEL_AVISO, PANEL_ERROR, PANEL_EXITO, PANEL_NOTA } from './Partials/solicitudesStyles';
import { puedeEmitirConsultaSolicitud, puedeResponderConsultaSolicitud } from '../../utils/permisos';
import { idEstadoPorNombre } from '../Facturas/Partials/facturasFiltros';

const BTN_SECUNDARIO = 'inline-flex items-center justify-center gap-2 rounded-xl border theme-border theme-element px-3 py-2 text-sm font-medium theme-text-main transition-colors duration-200 hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primario)]';
const ITEM_EXPORTAR = 'flex items-center gap-2 rounded-lg px-3 py-2 text-sm theme-text-main hover:bg-black/5 focus-visible:outline focus-visible:outline-2 dark:hover:bg-white/5';
const CHIP_AVISO = `gelia-estado-vivo gelia-estado-vivo--compacto gap-1 text-xs font-medium ${GELIA_ESTADO_VIVO_TONO.aviso}`;
const CHIP_ERROR = `gelia-estado-vivo gelia-estado-vivo--compacto gap-1 text-xs font-medium ${GELIA_ESTADO_VIVO_TONO.error}`;
const CHIP_INFO = `gelia-estado-vivo gelia-estado-vivo--compacto gap-1 text-xs font-medium ${GELIA_ESTADO_VIVO_TONO.info}`;

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
    const responsableTag = solicitud.vendedor?.name?.split(' ').slice(0, 2).join(' ') || 'Responsable';

    const listaActual = solicitud.cliente?.lista_descuento?.nombre || solicitud.cliente?.lista_actual || 'Público General';

    const etiquetaLista = (nombre, contenido) => (
        esListaDiamante(nombre) ? (
            <span className={claseEtiquetaLista(nombre)}>
                <Sparkles className="h-3 w-3 text-cyan-600 dark:text-cyan-300" aria-hidden="true" />
                <span className="text-cyan-700 drop-shadow-sm dark:text-cyan-300">{contenido}</span>
            </span>
        ) : (
            <span className={claseEtiquetaLista(nombre)}>{contenido}</span>
        )
    );

    return (
        <div className="flex flex-wrap gap-1.5">
            <span className="inline-flex items-center gap-1 rounded-md border theme-border bg-black/[0.04] px-2 py-1 text-[11px] font-medium uppercase tracking-wide theme-text-muted dark:bg-white/[0.06]">
                Lista actual: {listaActual}
            </span>
            {esCambioLista && objLista && etiquetaLista(objLista.nombre, <><TrendingUp className="h-3 w-3" aria-hidden="true" /> Ascenso a: {objLista.nombre}</>)}
            {esTag && (
                <span className={`${CHIP_AVISO} normal-case tracking-normal`}>
                    <Tag className="h-3 w-3" aria-hidden="true" /> TAG: {responsableTag}
                </span>
            )}
            {objTipo && (
                <span className={claseEtiquetaTipoCliente(objTipo.nombre)}>
                    <Users className="h-3 w-3" aria-hidden="true" /> {objTipo.nombre}
                </span>
            )}
            {solicitud.compra_en_tienda && (
                <span className={claseEtiquetaLista('Bronce')}>Compra en tienda</span>
            )}
            {solicitud.compra_en_tienda_solo_tag && (
                <span className={`${CHIP_INFO} normal-case tracking-normal`}>
                    <Tag className="h-3 w-3" aria-hidden="true" /> Compra realizada: solicitar tag
                </span>
            )}
            {solicitud.cancelacion_solicitada_at && solicitud.estado?.nombre !== 'Cancelada' && (
                <span className={CHIP_ERROR}>
                    <Ban className="h-3 w-3" aria-hidden="true" /> Cancelación solicitada
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
                <span className="text-[9px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400">Ver evidencia</span>
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

    const tono = esPositiva ? 'theme-text-exito' : 'theme-text-peligro';
    const panel = esPositiva ? PANEL_EXITO : PANEL_ERROR;

    return (
        <div className={`mt-2 flex flex-col gap-2 ${panel}`}>
            <div className="flex items-start justify-between gap-3">
                <div className="flex min-w-0 flex-1 items-start gap-2">
                    <MessageSquare className={`mt-0.5 h-4 w-4 shrink-0 ${tono}`} aria-hidden="true" />
                    <div className="min-w-0 flex-1">
                        <p className={`mb-0.5 text-xs font-medium ${tono}`}>
                            Respuesta de supervisión · {temas.join(' + ')}
                        </p>
                        <p className="mb-0.5 text-xs theme-text-muted">
                            {consulta.encargada?.name || 'Supervisión'} · {esPositiva ? 'Confirmada' : 'Rechazada'}
                        </p>
                        {consulta.comentario_encargada && (
                            <p className="text-xs leading-snug theme-text-main">{consulta.comentario_encargada}</p>
                        )}
                    </div>
                </div>
                <button
                    type="button"
                    disabled={procesando}
                    onClick={() => onMarcarLeido(solicitud.id, consulta.id)}
                    className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact shrink-0 disabled:opacity-50`}
                >
                    <Eye className="h-3.5 w-3.5" aria-hidden="true" /> Leído
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
const esMotivoSistema = (motivo) => {
    const m = (motivo || '').toUpperCase();
    return m.includes('SISTEMA AUTOMÁTICO') || m.includes('AUTOMÁTICAMENTE');
};

const esMotivoVencimientoPago = (motivo) => {
    const m = (motivo || '').toUpperCase();
    return m.includes('PLAZO DE PAGO') || m.includes('PAGO RECHAZADO') || m.includes('PAGO VENCIDO');
};

const FeedbackYComentarios = ({ solicitud }) => {
    const esVencimiento = solicitud.motivo_incorrecta === 'vencimiento_pago';
    const esErrorEstado = solicitud.estado?.nombre === 'Incorrecta';
    const esAprobada = solicitud.estado?.nombre === 'Respondida' || solicitud.estado?.nombre === 'Verificada';

    const auditoriasOrdenadas = [...(solicitud.auditorias || [])].sort((a, b) => b.id - a.id);
    const ultimaAuditoria = auditoriasOrdenadas[0] || null;
    const motivoUltimo = ultimaAuditoria?.motivo_reporte || '';
    const esSistema = esMotivoSistema(motivoUltimo) || esMotivoVencimientoPago(motivoUltimo) || esVencimiento;
    const esAlertaPago = !esSistema && motivoUltimo.toUpperCase().includes('ALERTA DE PAGO');
    const esError = esErrorEstado || esVencimiento || esMotivoVencimientoPago(motivoUltimo);

    const tieneObservacion = solicitud.observaciones_vendedor && solicitud.observaciones_vendedor.trim() !== '';
    const tieneFeedback = (esError || esAprobada || esAlertaPago) && motivoUltimo;

    let evidenciaAdmin = solicitud.evidencia_respuesta_path;
    if (!evidenciaAdmin && ultimaAuditoria?.datos_snapshot) {
        const snap = typeof ultimaAuditoria.datos_snapshot === 'string' ? JSON.parse(ultimaAuditoria.datos_snapshot) : ultimaAuditoria.datos_snapshot;
        if (snap?.evidencia_respuesta_path) evidenciaAdmin = snap.evidencia_respuesta_path;
    }

    if (!tieneObservacion && !tieneFeedback && !evidenciaAdmin) return null;

    const Icono = esAlertaPago ? AlertTriangle : (esError ? AlertOctagon : CheckCircle2);
    const tono = esAlertaPago ? 'theme-text-aviso' : (esError ? 'theme-text-peligro' : 'theme-text-exito');
    const autor = ultimaAuditoria?.usuario?.name?.trim();
    const estadoAnteriorNombre = ultimaAuditoria?.estado_anterior?.nombre
        || ultimaAuditoria?.estadoAnterior?.nombre
        || '';
    const esRespuestaACorreccion = !esError && esAprobada && estadoAnteriorNombre === 'Incorrecta';

    let titulo;
        if (esAlertaPago) {
        titulo = 'Alerta de ajuste de lista';
    } else if (esVencimiento || esMotivoVencimientoPago(motivoUltimo)) {
        titulo = 'Pago vencido · Sistema';
    } else if (esSistema) {
        titulo = 'Respuesta del sistema';
    } else if (esError) {
        titulo = autor ? `Error reportado por ${autor}` : 'Corrección requerida';
    } else if (esRespuestaACorreccion) {
        titulo = autor ? `Respuesta a corrección · ${autor}` : 'Respuesta a corrección';
    } else {
        titulo = autor ? `Respuesta de ${autor}` : 'Respuesta';
    }

    const panel = esAlertaPago ? PANEL_AVISO : (esError ? PANEL_ERROR : PANEL_EXITO);

    return (
        <div className="mt-2 flex flex-col gap-1.5">
            {tieneObservacion && !esAlertaPago && (
                <div className={`${PANEL_NOTA} flex items-start gap-2`}>
                    <MessageSquare className="mt-0.5 h-4 w-4 shrink-0 theme-text-muted" aria-hidden="true" />
                    <div className="min-w-0">
                        <p className="mb-0.5 text-xs font-medium theme-text-muted">Nota</p>
                        <p className="text-xs leading-snug theme-text-main">{solicitud.observaciones_vendedor}</p>
                    </div>
                </div>
            )}

            {(esError || esAprobada || esAlertaPago) && (
                <div className={`${panel} flex flex-col gap-1.5`}>
                    <div className="flex items-start gap-2">
                        <Icono className={`mt-0.5 h-4 w-4 shrink-0 ${tono}`} aria-hidden="true" />
                        <div className="min-w-0 flex-1">
                            <p className={`mb-0.5 text-xs font-medium ${tono}`}>
                                {titulo}
                            </p>
                            {motivoUltimo && (
                                <p className="text-xs leading-snug theme-text-main">
                                    {motivoUltimo}
                                </p>
                            )}
                        </div>
                    </div>
                    {evidenciaAdmin && !esVencimiento && !esMotivoVencimientoPago(motivoUltimo) && (
                        <VisorImagenHover path={evidenciaAdmin} />
                    )}
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

const TiemposSolicitud = ({ solicitud }) => (
    <div className="mt-1 space-y-0.5">
        <div className="flex items-center gap-1 text-xs theme-text-muted" title={solicitud.created_at}>
            <Clock className="h-3 w-3" aria-hidden="true" /> Emitida: {formatearTiempoRelativo(solicitud.created_at)}
        </div>
        {solicitud.updated_at && solicitud.updated_at !== solicitud.created_at && (
            <div className="flex items-center gap-1 text-xs theme-text-info" title={solicitud.updated_at}>
                <History className="h-3 w-3" aria-hidden="true" /> Actualizada: {formatearTiempoRelativo(solicitud.updated_at)}
            </div>
        )}
    </div>
);

const ClienteSolicitud = ({ solicitud, esHeredado, copiadoId, onCopiar }) => (
    <div className="min-w-0">
        <div className="mb-1 flex items-center gap-2">
            <span className="rounded border theme-border theme-element px-1.5 py-0.5 text-xs font-medium theme-text-main">{solicitud.cliente?.numero_cliente || 'N/A'}</span>
            {solicitud.cliente?.numero_cliente && (
                <button type="button" onClick={(e) => onCopiar(e, solicitud.cliente.numero_cliente, solicitud.id)} className="rounded p-1 theme-text-muted transition-colors hover:text-[var(--color-primario)] focus-visible:outline focus-visible:outline-2" aria-label={`Copiar número de cliente ${solicitud.cliente.numero_cliente}`}>
                    {copiadoId === solicitud.id ? <Check className="h-3 w-3 theme-text-exito" aria-hidden="true" /> : <Copy className="h-3 w-3" aria-hidden="true" />}
                </button>
            )}
            {esHeredado && <span className={CHIP_INFO}><ShieldAlert className="h-3 w-3" aria-hidden="true" /> Heredado</span>}
        </div>
        <div className="truncate text-sm font-medium theme-text-main">{solicitud.cliente?.nombre || 'Nuevo prospecto'}</div>
    </div>
);

const CotizacionSolicitud = ({ solicitud }) => {
    const concluida = (solicitud.compra_en_tienda || solicitud.compra_en_tienda_solo_tag) && solicitud.pago_confirmado;
    const tono = solicitud.pago_confirmado ? 'theme-text-exito' : 'theme-text-aviso';
    const etiqueta = concluida ? 'Concluida' : (solicitud.pago_confirmado ? 'Pago confirmado' : 'Pago pendiente');
    const panel = solicitud.pago_confirmado ? PANEL_EXITO : PANEL_AVISO;
    return (
        <div className="inline-flex flex-col items-start gap-1.5">
            <div className="rounded-lg border theme-border theme-element px-2.5 py-1 text-sm font-semibold tabular-nums theme-text-main">{new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(solicitud.monto_cotizado)}</div>
            <div className={`${panel} flex items-center gap-1 text-xs ${tono}`}>
                {solicitud.pago_confirmado ? <CheckCircle2 className="h-3 w-3" aria-hidden="true" /> : <Clock className="h-3 w-3" aria-hidden="true" />}
                {etiqueta}
            </div>
        </div>
    );
};

const MotivoIncidencia = ({ motivo }) => (
    <span className={`mt-1 ${CHIP_ERROR}`}>
        <AlertOctagon className="h-3 w-3" aria-hidden="true" />
        {motivo === 'vencimiento_pago' ? 'Pago vencido' : motivo === 'error_reportado' ? 'Error reportado' : motivo === 'pago_insuficiente' ? 'Pago insuficiente' : motivo}
    </span>
);

const EstadoVacioLista = ({ hayFiltros, puedeCrear, onLimpiar, onCrear }) => (
    <div className="rounded-xl border theme-border theme-surface px-6 py-10 text-center">
        <p className="m-0 text-sm theme-text-main">
            {hayFiltros ? 'Ninguna solicitud coincide con estos filtros.' : 'Aún no hay solicitudes.'}
        </p>
        <div className="mt-4 flex justify-center">
            {hayFiltros ? (
                <button type="button" onClick={onLimpiar} className={BTN_SECUNDARIO}>Limpiar filtros</button>
            ) : puedeCrear ? (
                <button type="button" onClick={onCrear} className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact`}>
                    <Plus className="h-4 w-4" aria-hidden="true" /> Nueva solicitud
                </button>
            ) : null}
        </div>
    </div>
);

const ConsultasPendientes = ({ solicitud }) => {
    const pendientes = (solicitud.consultas || []).filter((c) => c.estado === 'pendiente');
    if (!pendientes.length) return null;

    return (
        <div className="mt-1 flex flex-wrap gap-1.5">
            {pendientes.map((c) => (
                <React.Fragment key={c.id}>
                    {c.consulta_tag && (
                        <span className={CHIP_AVISO}>
                            <MessageSquare className="h-3 w-3" aria-hidden="true" /> Consulta TAG
                        </span>
                    )}
                    {c.consulta_lista && (
                        <span className={CHIP_AVISO}>
                            <MessageSquare className="h-3 w-3" aria-hidden="true" /> Consulta de lista
                        </span>
                    )}
                </React.Fragment>
            ))}
        </div>
    );
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
        <div className={`${compacto ? 'mt-2' : 'mt-2'} flex flex-col gap-1`}>
            <div className="flex items-start gap-2">
                <Ban className={`mt-0.5 h-4 w-4 shrink-0 ${pendiente ? 'theme-text-peligro' : 'theme-text-muted'}`} aria-hidden="true" />
                <div className="min-w-0 flex-1">
                    <p className={`mb-0.5 text-xs font-medium ${pendiente ? 'theme-text-peligro' : 'theme-text-muted'}`}>
                        {titulo}
                    </p>
                    {fechaSolicitud && pendiente && (
                        <p className="mb-1 text-xs theme-text-muted">Solicitada {fechaSolicitud}</p>
                    )}
                    {listaRebajaNombre && (
                        <p className={`mb-1 text-xs ${pendiente ? 'theme-text-peligro' : 'theme-text-muted'}`}>
                            Lista de rebaja: {listaRebajaNombre}
                        </p>
                    )}
                    <p className="whitespace-pre-wrap break-words text-xs leading-snug theme-text-main">
                        {solicitud.motivo_cancelacion}
                    </p>
                </div>
            </div>
        </div>
    );
};

const ModalConfirmarCancelacion = ({ onClose, solicitud, onProcesando }) => {
    const { put, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();
        onProcesando?.(true);
        put(route('solicitudes.cancelar', solicitud.id), {
            onSuccess: () => onClose(),
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
    const esCompraTienda = !!solicitud?.compra_en_tienda || !!solicitud?.compra_en_tienda_solo_tag;
    const { data, setData, processing } = useForm({
        modo: 'pago',
        monto_final_pagado: solicitud?.monto_cotizado || '',
    });

    const requiereMonto = data.modo === 'pago';
    const submit = (e) => {
        e.preventDefault();
        const payload = requiereMonto
            ? { modo: data.modo, monto_final_pagado: data.monto_final_pagado }
            : { modo: data.modo };
        onConfirmar(solicitud.id, payload);
    };

    return createPortal(
        <div className="fixed inset-0 z-[1000] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md animate-fade-in" onClick={onClose}>
            <div className="w-full max-w-sm theme-surface border theme-border shadow-2xl rounded-3xl p-8 relative modal-pop" onClick={e => e.stopPropagation()}>
                <button onClick={onClose} className="absolute top-4 right-4 p-2 theme-text-muted hover:theme-text-main rounded-xl outline-none transition-transform hover:scale-110"><X className="w-5 h-5" /></button>
                <div className="flex items-center gap-3 mb-6">
                    <CreditCard className="w-6 h-6 text-blue-500" />
                    <h3 className="text-xl font-black italic theme-text-main uppercase m-0">
                        {esCompraTienda ? 'Validar Solicitud' : 'Confirmar Pago'}
                    </h3>
                </div>
                <form onSubmit={submit} className="space-y-6">
                    {esCompraTienda && (
                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">Tipo de validación_</label>
                            <select
                                value={data.modo}
                                onChange={e => setData('modo', e.target.value)}
                                className="w-full px-4 py-3 theme-surface border theme-border rounded-xl theme-text-main text-xs font-black outline-none focus:ring-2 shadow-sm transition-all"
                            >
                                <option value="pago">Confirmar pago (con monto)</option>
                                <option value="pago_sin_monto">Confirmar pago sin monto</option>
                                <option value="atencion_gelia">Confirmar atención Gelia</option>
                            </select>
                            {data.modo === 'pago_sin_monto' && (
                                <p className="text-[10px] theme-text-muted italic leading-snug">
                                    Valida la solicitud sin sumar monto (si la remisión ya se cargó, evita duplicar cantidades).
                                </p>
                            )}
                            {data.modo === 'atencion_gelia' && (
                                <p className="text-[10px] theme-text-muted italic leading-snug">
                                    Confirma que el cliente fue atendido en Gelia. No modifica montos ni lista.
                                </p>
                            )}
                        </div>
                    )}
                    {requiereMonto && (
                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">Monto Final Cobrado_</label>
                            <input type="number" step="0.01" required value={data.monto_final_pagado} onChange={e => setData('monto_final_pagado', e.target.value)} className="w-full px-4 py-3 theme-surface border theme-border rounded-xl theme-text-main text-sm font-black outline-none focus:ring-2 shadow-sm transition-all" />
                            <p className="text-[10px] theme-text-muted mt-2 italic">* Si el monto cobrado con descuento es inferior a la meta de la lista, el sistema lo alertará automáticamente a la encargada.</p>
                        </div>
                    )}
                    <button type="submit" disabled={processing} className="w-full py-4 text-white rounded-xl font-black uppercase text-[11px] tracking-widest bg-blue-600 hover:bg-blue-700 transition-all shadow-lg outline-none disabled:opacity-50">
                        Confirmar Operación
                    </button>
                </form>
            </div>
        </div>,
        document.body
    );
};

const ModalSolicitarCancelacion = ({ onClose, solicitud, listas = [] }) => {
    const esCambioLista = esProcesoCambioLista(solicitud);
    const listasInferiores = listasInferioresParaCancelacion(listas, solicitud);
    const { data, setData, post, processing } = useForm({
        motivo_cancelacion: '',
        catalogo_lista_rebaja_id: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('solicitudes.solicitar_cancelacion', solicitud.id), {
            onSuccess: () => onClose(),
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
const MenuAccionesPortal = ({ menuAbierto, menuSolicitud, menuPos, setMenuAbierto, setModalForm, setModalRespuesta, setModalBitacora, setModalConsulta, setModalRespuestaConsulta, abrirModalPago, confirmarCambioLista, confirmarRollback, eliminarSolicitud, abrirModalCancelacion, abrirModalConfirmarCancelacion, can, auth, idRespondida, idVerificada, idIncorrecta }) => {
    if (!menuAbierto || !menuSolicitud) return null;
    const solicitud = menuSolicitud;
    const esCancelada = solicitud.estado?.nombre === 'Cancelada';
    const estadosActivos = ['Pendiente', 'Respondida', 'Verificada'];

    const roles = auth?.user?.roles || [];
    const esGerente = roles.some(r => String(r).toLowerCase().includes('gerente'));
    const esDueña = Number(solicitud.vendedor_id) === Number(auth.user.id);
    const puedeReportarErrorStaff = (can('solicitudes.reportar') || can('solicitudes.verificar') || esGerente)
        && solicitud.estado?.nombre !== 'Incorrecta'
        && !esCancelada;
    const puedeReportarErrorVendedora = esDueña
        && solicitud.estado?.nombre === 'Respondida'
        && !esCancelada;
    const puedeReportarError = puedeReportarErrorStaff || puedeReportarErrorVendedora;

    const auditoriasOrdenadas = [...(solicitud.auditorias || [])].sort((a, b) => b.id - a.id);
    const ultimaAuditoria = auditoriasOrdenadas.find(a => {
        const m = a.motivo_reporte?.toUpperCase() || '';
        return !m.includes('AUTOMÁTICAMENTE') && !m.includes('SISTEMA AUTOMÁTICO');
    });
    const esAlertaPago = ultimaAuditoria?.motivo_reporte?.toUpperCase().includes('ALERTA DE PAGO');
    const esVencimiento = solicitud.motivo_incorrecta === 'vencimiento_pago';
    const puedeCorregirRespuesta = can('solicitudes.reportar')
        && solicitud.estado?.nombre === 'Incorrecta'
        && solicitud.motivo_incorrecta === 'error_reportado'
        && !esAlertaPago
        && !esVencimiento
        && idRespondida;
    const esAprobadaConPagoPendiente = ['Respondida', 'Verificada'].includes(solicitud.estado?.nombre);
    const consultaPendiente = (solicitud.consultas || []).find(c => c.estado === 'pendiente');
    const puedeConsultar = puedeEmitirConsultaSolicitud(auth)
        && Number(solicitud.vendedor_id) === Number(auth.user.id)
        && esAprobadaConPagoPendiente
        && !solicitud.pago_confirmado
        && !consultaPendiente
        && !esAlertaPago;

    const puedeSolicitarCancelacion = can('solicitudes.solicitar_cancelacion')
        && Number(solicitud.vendedor_id) === Number(auth.user.id)
        && estadosActivos.includes(solicitud.estado?.nombre)
        && !solicitud.cancelacion_solicitada_at
        && !esVencimiento
        && !esCancelada;

    const puedeConfirmarCancelacion = can('solicitudes.cancelar')
        && solicitud.cancelacion_solicitada_at
        && !esCancelada;

    const accion = 'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm theme-text-main transition-colors duration-200 hover:bg-black/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primario)] dark:hover:bg-white/5';
    const accionPeligro = 'flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm theme-text-peligro transition-colors duration-200 hover:bg-black/5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-primario)] dark:hover:bg-white/5';

    return createPortal(
        <>
            <div className="fixed inset-0 z-[999]" onClick={() => setMenuAbierto(null)}></div>
            <div className={`fixed z-[1000] flex flex-col gap-0.5 rounded-xl border theme-border theme-surface p-1.5 shadow-lg ${puedeConfirmarCancelacion && solicitud.motivo_cancelacion ? 'w-72' : 'w-56'}`} style={{ top: menuPos.top, left: menuPos.left }} role="menu">

                {/* Confirmar Cambio de Lista (Solo si hay alerta) */}
                {esAlertaPago && can('solicitudes.confirmar_cambio_lista') && (
                    <button type="button" onClick={() => { setMenuAbierto(null); confirmarCambioLista(solicitud.id); }} className={accion}>
                        <TrendingUp className="h-4 w-4" aria-hidden="true" /> Confirmar ajuste
                    </button>
                )}

                {solicitud.vendedor_id === auth.user.id && solicitud.estado?.nombre === 'Incorrecta' && !esAlertaPago && !esVencimiento && (
                    <button type="button" onClick={() => { setMenuAbierto(null); setModalForm({ abierto: true, modoEdicion: true, solicitud }); }} className={accion}>
                        <Edit2 className="h-4 w-4" aria-hidden="true" /> Reparar solicitud
                    </button>
                )}

                {esVencimiento && solicitud.vendedor_id === auth.user.id && (
                    <p className="m-0 border-b theme-border px-3 py-2 text-xs theme-text-muted">
                        Pago vencido: debe iniciar una nueva solicitud
                    </p>
                )}

                {can('solicitudes.reportar') && esVencimiento && !solicitud.rollback_confirmado_at && (
                    <button type="button" onClick={() => { setMenuAbierto(null); confirmarRollback(solicitud.id); }} className={accionPeligro}>
                        <ShieldAlert className="h-4 w-4" aria-hidden="true" /> Confirmar reversión
                    </button>
                )}

                {puedeConsultar && (
                    <button type="button" onClick={() => { setMenuAbierto(null); setModalConsulta({ abierto: true, solicitud }); }} className={accion}>
                        <MessageSquare className="h-4 w-4" aria-hidden="true" /> Consultar TAG o lista
                    </button>
                )}

                {puedeResponderConsultaSolicitud(auth) && consultaPendiente && (
                    <button type="button" onClick={() => { setMenuAbierto(null); setModalRespuestaConsulta({ abierto: true, solicitud, consulta: consultaPendiente }); }} className={accion}>
                        <MessageSquare className="h-4 w-4" aria-hidden="true" /> Responder consulta
                    </button>
                )}

                {/* Confirmar Pago / Validar tienda — no aplica a flujos tienda (se concluye al aprobar) */}
                {(can('solicitudes.confirmar_pago') || solicitud.vendedor_id === auth.user.id)
                    && !solicitud.pago_confirmado
                    && !solicitud.compra_en_tienda
                    && !solicitud.compra_en_tienda_solo_tag
                    && solicitud.estado?.nombre === 'Respondida'
                    && !esAlertaPago && (
                    <button type="button" onClick={() => { setMenuAbierto(null); abrirModalPago(solicitud); }} className={accion}>
                        <CreditCard className="h-4 w-4" aria-hidden="true" /> Confirmar pago
                    </button>
                )}

                {puedeSolicitarCancelacion && (
                    <button type="button" onClick={() => { setMenuAbierto(null); abrirModalCancelacion(solicitud); }} className={accionPeligro}>
                        <Ban className="h-4 w-4" aria-hidden="true" /> Solicitar cancelación
                    </button>
                )}

                {puedeConfirmarCancelacion && solicitud.motivo_cancelacion && (
                    <div className="mb-1 border-b theme-border px-3 py-2">
                        <p className="mb-1 text-xs font-medium theme-text-peligro">Motivo de la solicitud</p>
                        {obtenerListaRebajaNombre(solicitud) && (
                            <p className="mb-1 text-xs theme-text-peligro">
                                Lista de rebaja: {obtenerListaRebajaNombre(solicitud)}
                            </p>
                        )}
                        <p className="line-clamp-4 text-xs leading-snug theme-text-main">
                            {solicitud.motivo_cancelacion}
                        </p>
                    </div>
                )}

                {puedeConfirmarCancelacion && (
                    <button type="button" onClick={() => { setMenuAbierto(null); abrirModalConfirmarCancelacion(solicitud); }} className={accionPeligro}>
                        <XCircle className="h-4 w-4" aria-hidden="true" /> Confirmar cancelación
                    </button>
                )}

                {/* Verificado (Paso final) — flujos tienda también quedan pendientes de verificar */}
                {can('solicitudes.verificar')
                    && solicitud.estado?.nombre === 'Respondida'
                    && solicitud.pago_confirmado
                    && !esAlertaPago
                    && idVerificada && (
                    <button type="button" onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud, estadoId: idVerificada }); }} className={accion}>
                        <CheckSquare className="h-4 w-4" aria-hidden="true" /> Verificado
                    </button>
                )}

                {/* Aprobar (Encargada) — flujos tienda: marca concluida para vendedora, sigue pendiente de verificar */}
                {can('solicitudes.reportar') && !esAlertaPago && !esCancelada && solicitud.estado?.nombre === 'Pendiente' && idRespondida && (
                    <button type="button" onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud, estadoId: idRespondida }); }} className={accion}>
                        <CheckCircle2 className="h-4 w-4" aria-hidden="true" /> Aprobar proceso
                    </button>
                )}

                {puedeCorregirRespuesta && (
                    <button type="button" onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud, estadoId: idRespondida }); }} className={accion}>
                        <CheckCircle2 className="h-4 w-4" aria-hidden="true" /> Corregir respuesta
                    </button>
                )}

                {/* Reportar error — staff en etapas activas; vendedora dueña solo en Respondida */}
                {puedeReportarError && !esAlertaPago && idIncorrecta && (
                    <button type="button" onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud, estadoId: idIncorrecta }); }} className={accionPeligro}>
                        <AlertOctagon className="h-4 w-4" aria-hidden="true" /> Reportar error
                    </button>
                )}

                {/* Bitácora */}
                {can('configuracion.ver_auditoria') && (
                    <button type="button" onClick={() => { setMenuAbierto(null); setModalBitacora({ abierto: true, solicitud }); }} className={`${accion} mt-1 border-t theme-border pt-2`}>
                        <History className="h-4 w-4" aria-hidden="true" /> Ver bitácora
                    </button>
                )}

                {/* Eliminar */}
                {can('solicitudes.eliminar') && (
                    <button type="button" onClick={() => eliminarSolicitud(solicitud.id)} className={`${accionPeligro} mt-1 border-t theme-border pt-2`}>
                        <Trash2 className="h-4 w-4" aria-hidden="true" /> Eliminar registro
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
            paginas.push(1); if (paginaActual > 3) paginas.push('…');
            for (let i = Math.max(2, paginaActual - 1); i <= Math.min(totalPaginas - 1, paginaActual + 1); i++) paginas.push(i);
            if (paginaActual < totalPaginas - 2) paginas.push('…'); paginas.push(totalPaginas);
        }
        return paginas;
    };
    return (
        <div className="flex flex-col items-center justify-between gap-3 sm:flex-row">
            <span className="text-sm tabular-nums theme-text-muted">Viendo {desde} a {hasta} de {totalRegistros.toLocaleString('es-MX')}</span>
            <div className="flex items-center gap-2">
                <button type="button" onClick={() => onIrAPagina(paginaActual - 1)} disabled={paginaActual === 1} aria-label="Página anterior" className="paginacion-btn theme-surface theme-text-muted hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"><ChevronLeft className="h-4 w-4" aria-hidden="true" /></button>
                {generarPaginas().map((p, i) => p === '…' ? (<span key={`dots-${i}`} className="w-10 text-center text-sm theme-text-muted">…</span>) : (<button type="button" key={p} onClick={() => onIrAPagina(p)} aria-current={p === paginaActual ? 'page' : undefined} aria-label={`Página ${p}`} className={`paginacion-btn tabular-nums focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 ${p === paginaActual ? 'paginacion-btn--active' : 'theme-surface theme-text-main hover:border-[var(--color-primario)] hover:text-[var(--color-primario)]'}`}>{p}</button>))}
                <button type="button" onClick={() => onIrAPagina(paginaActual + 1)} disabled={paginaActual === totalPaginas} aria-label="Página siguiente" className="paginacion-btn theme-surface theme-text-muted hover:border-[var(--color-primario)] hover:text-[var(--color-primario)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"><ChevronRight className="h-4 w-4" aria-hidden="true" /></button>
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
    estados = [],
    filtros = {},
    auth
}) {
    const idRespondida = idEstadoPorNombre(estados, 'Respondida');
    const idVerificada = idEstadoPorNombre(estados, 'Verificada');
    const idIncorrecta = idEstadoPorNombre(estados, 'Incorrecta');

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
    const [consultando, setConsultando] = useState(false);
    const [menuExportar, setMenuExportar] = useState(false);
    const exportarRef = useRef(null);

    const modalsAbiertosRef = useRef(false);
    modalsAbiertosRef.current = modalForm.abierto
        || modalRespuesta.abierto
        || modalBitacora.abierto
        || modalConsulta.abierto
        || modalRespuestaConsulta.abierto
        || modalPago.abierto
        || modalCancelacion.abierto
        || modalConfirmarCancelacion.abierto
        || modalEscalonamiento
        || menuAbierto !== null;

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
        onInicioConsulta: () => setConsultando(true),
        onFinConsulta: () => setConsultando(false),
    });

    const can = (permiso) => auth?.user?.permissions?.includes(permiso) ?? false;
    const puedeExportar = can('solicitudes.exportar');
    const puedeVerEscalonamiento = can('ejercicio_escalonamiento.ver')
        || (auth?.user?.roles || []).includes('Super Admin');

    const eliminarSolicitud = (id) => {
        const motivo = window.prompt("ATENCIÓN: Se eliminará este registro y se creará un respaldo en la auditoría.\n\nIngresa el motivo de la eliminación (Mínimo 10 caracteres):");
        if (motivo === null) return;
        if (motivo.trim().length < 10) { alert("Operación cancelada: El motivo debe tener al menos 10 caracteres."); return; }
        setMenuAbierto(null); setProcesandoAccion(true);
        router.delete(route('solicitudes.destroy', id), {
            data: { motivo: motivo.trim() },
            preserveScroll: true,
            onFinish: () => setProcesandoAccion(false),
        });
    };

    const marcarConsultaLeida = (solicitudId, consultaId) => {
        setProcesandoAccion(true);
        router.put(route('solicitudes.consultas.leer', [solicitudId, consultaId]), {}, {
            preserveScroll: true,
            onFinish: () => setProcesandoAccion(false),
        });
    };

    useEffect(() => {
        const interval = setInterval(() => {
            if (/\/\d+\//.test(window.location.pathname)) return;
            if (modalsAbiertosRef.current) return;
            router.reload({ only: ['solicitudes'], preserveState: true, preserveScroll: true, showProgress: false });
        }, 15000);
        return () => clearInterval(interval);
    }, []);

    useEffect(() => {
        const handleScroll = () => setMenuAbierto(null);
        window.addEventListener('scroll', handleScroll, true);
        return () => window.removeEventListener('scroll', handleScroll, true);
    }, []);

    useEffect(() => {
        if (!menuExportar) return undefined;
        const cerrar = (event) => {
            if (exportarRef.current && !exportarRef.current.contains(event.target)) {
                setMenuExportar(false);
            }
        };
        const tecla = (event) => {
            if (event.key === 'Escape') setMenuExportar(false);
        };
        document.addEventListener('mousedown', cerrar);
        document.addEventListener('keydown', tecla);
        return () => {
            document.removeEventListener('mousedown', cerrar);
            document.removeEventListener('keydown', tecla);
        };
    }, [menuExportar]);

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
            onFinish: () => setProcesandoAccion(false),
        });
    };

    const confirmarCambioLista = (id) => {
        if (window.confirm('¿Confirmar el ajuste de lista para este cliente?')) {
            setProcesandoAccion(true);
            router.put(route('solicitudes.confirmar_lista', id), {}, {
                preserveScroll: true,
                onFinish: () => setProcesandoAccion(false),
            });
        }
    };

    const confirmarRollback = (id) => {
        if (window.confirm('¿Confirmar la reversión de cambios por vencimiento de pago? La vendedora deberá iniciar una nueva solicitud.')) {
            setProcesandoAccion(true);
            router.put(route('solicitudes.confirmar_rollback', id), {}, {
                preserveScroll: true,
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
    const hayFiltros = tabActiva !== 'TODAS' || Boolean(busqueda) || filtrosAdicionalesActivos > 0;
    const limpiarConsulta = () => aplicarFiltros({
        tab: 'TODAS',
        q: '',
        vendedor_id: '',
        motivo_incorrecta: '',
        tipo_fecha: 'TODAS',
        fecha_inicio: '',
        fecha_fin: '',
    });

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
        setConsultando(true);
        router.get(route('solicitudes.index'), construirParams({ page: pagina }), {
            preserveState: true,
            preserveScroll: false,
            showProgress: false,
            onFinish: () => setConsultando(false),
        });
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Panel de Solicitudes" />
            <GeliaLoader isVisible={procesandoAccion} message="Guardando…" />

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
                can={can}
                auth={auth}
                idRespondida={idRespondida}
                idVerificada={idVerificada}
                idIncorrecta={idIncorrecta}
            />
            {modalPago.abierto && <ModalConfirmarPago onClose={() => setModalPago({ abierto: false, solicitud: null })} solicitud={modalPago.solicitud} onConfirmar={confirmarPagoConMonto} />}
            {modalCancelacion.abierto && (
                <ModalSolicitarCancelacion
                    onClose={() => setModalCancelacion({ abierto: false, solicitud: null })}
                    solicitud={modalCancelacion.solicitud}
                    listas={listas}
                />
            )}
            {modalConfirmarCancelacion.abierto && (
                <ModalConfirmarCancelacion
                    onClose={() => setModalConfirmarCancelacion({ abierto: false, solicitud: null })}
                    solicitud={modalConfirmarCancelacion.solicitud}
                    onProcesando={setProcesandoAccion}
                />
            )}
            <GeliaPageShell className="space-y-4">
                <p className="sr-only" aria-live="polite">{copiadoId ? 'Número de cliente copiado' : ''}</p>
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div className="min-w-0">
                        <h1 className="m-0 text-2xl font-semibold theme-text-main text-balance">Solicitudes</h1>
                        <p className="m-0 mt-1 text-sm theme-text-muted">Cola de trámites comerciales</p>
                    </div>
                    <div className="flex w-full flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center md:w-auto">
                        {puedeExportar && (
                            <div className="relative" ref={exportarRef}>
                                <button
                                    type="button"
                                    className={BTN_SECUNDARIO}
                                    aria-expanded={menuExportar}
                                    aria-haspopup="menu"
                                    onClick={() => setMenuExportar((abierto) => !abierto)}
                                >
                                    <Download className="h-4 w-4 shrink-0" aria-hidden="true" />
                                    Exportar
                                    <ChevronDown className="h-4 w-4 shrink-0" aria-hidden="true" />
                                </button>
                                {menuExportar && (
                                    <div className="absolute right-0 z-20 mt-2 w-48 rounded-xl border theme-border theme-surface p-1 shadow-lg" role="menu">
                                        <a href={route('reportes.solicitudes.exportar', { ...exportParams, format: 'pdf' })} className={ITEM_EXPORTAR} role="menuitem">
                                            <FileText className="h-4 w-4" aria-hidden="true" /> PDF
                                        </a>
                                        <a href={route('reportes.solicitudes.exportar', { ...exportParams, format: 'xlsx' })} className={ITEM_EXPORTAR} role="menuitem">
                                            <FileSpreadsheet className="h-4 w-4" aria-hidden="true" /> Excel
                                        </a>
                                        <a href={route('reportes.solicitudes.exportar', { ...exportParams, format: 'csv' })} className={ITEM_EXPORTAR} role="menuitem">
                                            <Download className="h-4 w-4" aria-hidden="true" /> CSV
                                        </a>
                                        <Link href={route('reportes.solicitudes.index', exportParams)} className={ITEM_EXPORTAR} role="menuitem">
                                            Reportes
                                        </Link>
                                    </div>
                                )}
                            </div>
                        )}
                        {puedeVerEscalonamiento && (
                            <button type="button" onClick={() => setModalEscalonamiento(true)} className={BTN_SECUNDARIO}>
                                <Calculator className="h-4 w-4 shrink-0" aria-hidden="true" /> Escalonamiento
                            </button>
                        )}
                        {can('solicitudes.crear') && (
                            <button type="button" onClick={() => setModalForm({ abierto: true, modoEdicion: false, solicitud: null })} className={`${THEME_BTN_PRIMARY} theme-btn-primary--compact w-full sm:w-auto`}>
                                <Plus className="h-4 w-4" aria-hidden="true" /> Nueva solicitud
                            </button>
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
                />

                <div className="block space-y-3 lg:hidden">
                    {consultando ? (
                        <div className="space-y-3" aria-hidden="true">
                            {Array.from({ length: 4 }, (_, i) => (
                                <div key={i} className="h-28 animate-pulse rounded-xl border theme-border theme-element" />
                            ))}
                        </div>
                    ) : solicitudesFiltradas.length === 0 ? (
                        <EstadoVacioLista hayFiltros={hayFiltros} puedeCrear={can('solicitudes.crear')} onLimpiar={limpiarConsulta} onCrear={() => setModalForm({ abierto: true, modoEdicion: false, solicitud: null })} />
                    ) : (
                        solicitudesFiltradas.map((solicitud) => {
                            const estatus = obtenerEstiloEstado(solicitud.estado?.nombre); const StatusIcon = estatus.icon; const nombreProceso = solicitud.proceso?.nombre || ''; const esHeredado = solicitud.cliente?.es_heredado;
                            return (
                                <div key={solicitud.id} className="flex flex-col gap-3 rounded-xl border theme-border theme-surface p-4">
                                    <div className="flex items-start justify-between gap-3 border-b theme-border pb-3">
                                        <div className="min-w-0">
                                            <div className="text-sm font-semibold theme-text-main">FOL-{solicitud.id}</div>
                                            <div className="mt-0.5 flex items-center gap-1 text-xs theme-text-muted">
                                                <User className="h-3 w-3" aria-hidden="true" /> {solicitud.vendedor?.name}
                                            </div>
                                            <TiemposSolicitud solicitud={solicitud} />
                                        </div>
                                        <div className={`${estatus.clase} whitespace-nowrap`}>
                                            <StatusIcon className="h-3.5 w-3.5" aria-hidden="true" />
                                            <span>{estatus.label}</span>
                                        </div>
                                    </div>
                                    <ClienteSolicitud solicitud={solicitud} esHeredado={esHeredado} copiadoId={copiadoId} onCopiar={copiarAlPortapapeles} />
                                    <div className="flex flex-col gap-2">
                                        <span className="text-sm font-medium theme-text-main">{nombreProceso}</span>
                                        <EtiquetasOperacion solicitud={solicitud} listas={listas} />
                                        <ConsultasPendientes solicitud={solicitud} />
                                        {solicitud.motivo_incorrecta && <MotivoIncidencia motivo={solicitud.motivo_incorrecta} />}
                                    </div>
                                    <RespuestaConsultaEncargada solicitud={solicitud} auth={auth} onMarcarLeido={marcarConsultaLeida} procesando={procesandoAccion} />
                                    <FeedbackYComentarios solicitud={solicitud} />
                                    <MotivoCancelacionBloque solicitud={solicitud} />
                                    <div className="flex items-center justify-between border-t theme-border pt-2">
                                        <CotizacionSolicitud solicitud={solicitud} />
                                        <button type="button" onClick={(e) => abrirMenu(e, solicitud)} aria-label={`Acciones de FOL-${solicitud.id}`} className="rounded-lg border theme-border theme-element p-2 transition-colors duration-200 hover:border-[var(--color-primario)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"><MoreVertical className="h-5 w-5 theme-text-main" aria-hidden="true" /></button>
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>

                <div className="hidden overflow-hidden rounded-xl border theme-border theme-surface lg:block">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[1000px] border-collapse text-left">
                            <thead className="theme-surface">
                                <tr className="border-b theme-border">
                                    <th className="px-4 py-3 text-xs font-medium theme-text-muted">Folio</th>
                                    <th className="px-4 py-3 text-xs font-medium theme-text-muted">Cliente</th>
                                    <th className="px-4 py-3 text-xs font-medium theme-text-muted">Operación</th>
                                    <th className="px-4 py-3 text-xs font-medium theme-text-muted">Cotización</th>
                                    <th className="px-4 py-3 text-xs font-medium theme-text-muted">Estado</th>
                                    <th className="sticky-actions px-4 py-3 text-center text-xs font-medium theme-text-muted">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                {consultando ? (
                                    Array.from({ length: 6 }, (_, i) => (
                                        <tr key={`esqueleto-${i}`} className="border-b theme-border">
                                            <td colSpan={6} className="px-4 py-3"><div className="h-8 animate-pulse rounded-md theme-element" /></td>
                                        </tr>
                                    ))
                                ) : solicitudesFiltradas.length === 0 ? (
                                    <tr>
                                        <td colSpan={6} className="px-4 py-8">
                                            <EstadoVacioLista hayFiltros={hayFiltros} puedeCrear={can('solicitudes.crear')} onLimpiar={limpiarConsulta} onCrear={() => setModalForm({ abierto: true, modoEdicion: false, solicitud: null })} />
                                        </td>
                                    </tr>
                                ) : solicitudesFiltradas.map((solicitud) => {
                                    const estatus = obtenerEstiloEstado(solicitud.estado?.nombre); const StatusIcon = estatus.icon; const nombreProceso = solicitud.proceso?.nombre || ''; const esHeredado = solicitud.cliente?.es_heredado;
                                    return (
                                        <tr key={solicitud.id} className="border-b theme-border transition-colors duration-200 hover:bg-black/5 dark:hover:bg-white/5">
                                            <td className="px-4 py-3 align-top">
                                                <div className="text-sm font-semibold theme-text-main">FOL-{solicitud.id}</div>
                                                <div className="mt-1 text-xs theme-text-muted">
                                                    <User className="mr-1 inline h-3 w-3" aria-hidden="true" /> {solicitud.vendedor?.name}
                                                </div>
                                                <TiemposSolicitud solicitud={solicitud} />
                                            </td>
                                            <td className="max-w-[220px] px-4 py-3 align-top">
                                                <ClienteSolicitud solicitud={solicitud} esHeredado={esHeredado} copiadoId={copiadoId} onCopiar={copiarAlPortapapeles} />
                                            </td>
                                            <td className="px-4 py-3 align-top">
                                                <div className="mb-1 text-sm font-medium theme-text-main">{nombreProceso}</div>
                                                <EtiquetasOperacion solicitud={solicitud} listas={listas} />
                                                <ConsultasPendientes solicitud={solicitud} />
                                                {solicitud.motivo_incorrecta && <MotivoIncidencia motivo={solicitud.motivo_incorrecta} />}
                                                <RespuestaConsultaEncargada solicitud={solicitud} auth={auth} onMarcarLeido={marcarConsultaLeida} procesando={procesandoAccion} />
                                                <FeedbackYComentarios solicitud={solicitud} />
                                                <MotivoCancelacionBloque solicitud={solicitud} />
                                            </td>
                                            <td className="px-4 py-3 align-top">
                                                <CotizacionSolicitud solicitud={solicitud} />
                                            </td>
                                            <td className="px-4 py-3 align-top">
                                                <div className={`${estatus.clase} whitespace-nowrap`}><StatusIcon className="h-3.5 w-3.5" aria-hidden="true" /><span>{estatus.label}</span></div>
                                            </td>
                                            <td className="sticky-actions px-4 py-3 text-center align-top">
                                                <button type="button" onClick={(e) => abrirMenu(e, solicitud)} aria-label={`Acciones de FOL-${solicitud.id}`} className="rounded-lg border theme-border theme-element p-2 transition-colors duration-200 hover:border-[var(--color-primario)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"><MoreVertical className="h-5 w-5 theme-text-main" aria-hidden="true" /></button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>

                <Paginacion solicitudes={solicitudes} onIrAPagina={irAPagina} />
            </GeliaPageShell>

            {modalForm.abierto && <ModalFormSolicitud onClose={() => setModalForm({ ...modalForm, abierto: false })} procesos={procesos} listas={listas} tiposCliente={tipos_cliente} bancos={bancos} modoEdicion={modalForm.modoEdicion} solicitudAEditar={modalForm.solicitud} />}
            {modalRespuesta.abierto && (
                <ModalRespuestaSolicitud
                    onClose={() => setModalRespuesta({ ...modalRespuesta, abierto: false })}
                    solicitud={modalRespuesta.solicitud}
                    estadoId={modalRespuesta.estadoId}
                    esReporteError={Number(modalRespuesta.estadoId) === Number(idIncorrecta)}
                />
            )}
            {modalBitacora.abierto && <ModalBitacoraSolicitud onClose={() => setModalBitacora({ ...modalBitacora, abierto: false })} solicitud={modalBitacora.solicitud} listas={listas} tiposCliente={tipos_cliente} />}
            {modalConsulta.abierto && <ModalConsultaSolicitud onClose={() => setModalConsulta({ ...modalConsulta, abierto: false })} solicitud={modalConsulta.solicitud} />}
            {modalRespuestaConsulta.abierto && <ModalRespuestaConsulta onClose={() => setModalRespuestaConsulta({ ...modalRespuestaConsulta, abierto: false })} solicitud={modalRespuestaConsulta.solicitud} consulta={modalRespuestaConsulta.consulta} />}
            {modalEscalonamiento && (
                <ModalEjercicioEscalonamiento
                    onClose={() => setModalEscalonamiento(false)}
                    listas={listas}
                />
            )}
        </AppLayout>
    );
}