import ResumenSolicitudes from './Partials/ResumenSolicitudes';
import IndicadoresSolicitudes from '@/Components/Solicitudes/IndicadoresSolicitudes';
import ModalAccionSolicitud from './Partials/ModalAccionSolicitud';
import SolicitudDialog from '@/Components/Solicitudes/SolicitudDialog';
import EvidenciaSolicitud from '@/Components/Solicitudes/EvidenciaSolicitud';
import React, { useEffect, useLayoutEffect, useState, useRef } from 'react';
import { createPortal } from 'react-dom';
import { Head, Link, router } from '@inertiajs/react';
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
        const snap = typeof ultimaAuditoria.datos_snapshot === 'string' ? leerSnapshot(ultimaAuditoria.datos_snapshot) : ultimaAuditoria.datos_snapshot;
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
                        <p className="text-sm leading-relaxed whitespace-pre-wrap break-words theme-text-main">{solicitud.observaciones_vendedor}</p>
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
                                <p className="text-sm leading-relaxed whitespace-pre-wrap break-words theme-text-main">
                                    {motivoUltimo}
                                </p>
                            )}
                        </div>
                    </div>
                    {evidenciaAdmin && !esVencimiento && !esMotivoVencimientoPago(motivoUltimo) && (
                        <EvidenciaSolicitud path={evidenciaAdmin} dialogClassName="gelia-tag-lista-overlay" />
                    )}
                </div>
            )}
        </div>
    );
};

const obtenerListaRebajaNombre = solicitud => solicitud?.lista_rebaja?.nombre || solicitud?.listaRebaja?.nombre || null;

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
        <div className="text-sm font-medium break-words theme-text-main">{solicitud.cliente?.nombre || 'Nuevo prospecto'}</div>
    </div>
);

const CotizacionSolicitud = ({ solicitud }) => {
    const aprobada = ['Respondida', 'Verificada'].includes(solicitud.estado?.nombre);
    const concluida = (solicitud.compra_en_tienda || solicitud.compra_en_tienda_solo_tag) && aprobada;
    const vencido = solicitud.motivo_incorrecta === 'vencimiento_pago';
    const exito = solicitud.pago_confirmado || concluida;
    const etiqueta = concluida ? 'Atención concluida' : solicitud.pago_confirmado ? 'Pago confirmado' : vencido ? 'Pago vencido' : 'Pago pendiente';
    const chip = vencido ? CHIP_ERROR : exito ? `gelia-estado-vivo gelia-estado-vivo--compacto ${GELIA_ESTADO_VIVO_TONO.exito}` : CHIP_AVISO;
    const mostrarPago = solicitud.estado?.nombre !== 'Cancelada' && (aprobada || solicitud.pago_confirmado || vencido);
    return (
        <div className="inline-flex flex-col items-start gap-1.5">
            <div className="text-sm font-semibold tabular-nums theme-text-main">{new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(solicitud.monto_cotizado || 0)}</div>
            {mostrarPago && <div className={`${chip} flex items-center gap-1 text-xs`}>
                {exito ? <CheckCircle2 className="h-3 w-3" aria-hidden="true" /> : <Clock className="h-3 w-3" aria-hidden="true" />}
                {etiqueta}
            </div>}
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
                    <p className="whitespace-pre-wrap break-words text-sm leading-relaxed theme-text-main">
                        {solicitud.motivo_cancelacion}
                    </p>
                </div>
            </div>
        </div>
    );
};

const leerSnapshot = (snapshot) => {
    if (typeof snapshot !== 'string') return snapshot;
    try { return JSON.parse(snapshot); } catch { return null; }
};

const ResumenDetalle = ({ solicitud, auth, onAbrir }) => {
    const sinLeer = (solicitud.consultas || []).filter(c => c.estado === 'respondida' && !c.leido_vendedor_at && Number(solicitud.vendedor_id) === Number(auth?.user?.id)).length;
    const cancelacion = tieneMotivoCancelacionVisible(solicitud);
    const ultima = [...(solicitud.auditorias || [])].sort((a, b) => b.id - a.id)[0];
    const resumen = cancelacion ? solicitud.motivo_cancelacion : ultima?.motivo_reporte || solicitud.observaciones_vendedor;
    return <div className="gelia-tag-resumen">
        {sinLeer > 0 && <span className="gelia-tag-unread"><MessageSquare className="w-3.5 h-3.5" aria-hidden="true" />{sinLeer} {sinLeer === 1 ? 'respuesta sin leer' : 'respuestas sin leer'}</span>}
        {resumen && <p className="line-clamp-2 text-sm theme-text-muted m-0">{resumen}</p>}
        <button type="button" onClick={onAbrir} className="gelia-tag-detail-link"><Eye className="w-4 h-4" aria-hidden="true" />{cancelacion ? 'Ver cancelación y detalle' : sinLeer ? 'Leer respuesta' : 'Ver detalle y respuestas'}</button>
    </div>;
};

const ModalDetalleSolicitud = ({ solicitud, auth, onClose, onMarcarLeido, procesando, onBitacora }) => (
    <SolicitudDialog onClose={onClose} title={`Detalle de FOL-${solicitud.id}`} className="gelia-tag-lista-overlay">
        <div className="gelia-modal-shell gelia-tag-detalle w-full max-w-3xl">
            <header className="gelia-workflow-header"><div><h2 className="m-0 theme-text-main">Detalle de la solicitud</h2><p className="m-0 mt-1 text-sm theme-text-muted">FOL-{solicitud.id} · {solicitud.proceso?.nombre}</p></div><button type="button" data-dialog-close className="gelia-workflow-close" aria-label="Cerrar detalle"><X className="w-5 h-5" aria-hidden="true" /></button></header>
            <div className="gelia-modal-body p-5 sm:p-6 space-y-6">
                <dl className="gelia-respuesta-contexto"><div><dt>Cliente</dt><dd>{solicitud.cliente?.nombre || 'Nuevo prospecto'} · {solicitud.cliente?.numero_cliente || 'Sin número'}</dd></div><div><dt>Responsable</dt><dd>{solicitud.vendedor?.name || 'Sin asignar'}</dd></div><div><dt>Estado</dt><dd>{solicitud.estado?.nombre || 'Pendiente'}</dd></div><div><dt>Cotización</dt><dd>{new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(solicitud.monto_cotizado || 0)}</dd></div></dl>
                <EtiquetasOperacion solicitud={solicitud} />
                <section className="gelia-tag-detail-section"><h3>Notas y resolución</h3><FeedbackYComentarios solicitud={solicitud} />{!solicitud.observaciones_vendedor && !solicitud.auditorias?.length && <p className="text-sm theme-text-muted">La solicitud todavía no tiene una respuesta.</p>}{solicitud.evidencia_path && <EvidenciaSolicitud path={solicitud.evidencia_path} title="Evidencia de la solicitud" dialogClassName="gelia-tag-lista-overlay" />}</section>
                {tieneMotivoCancelacionVisible(solicitud) && <section className="gelia-tag-message" data-tone="peligro"><MotivoCancelacionBloque solicitud={solicitud} /></section>}
                {!!solicitud.consultas?.length && <section className="gelia-tag-detail-section"><h3>Consultas y respuestas de supervisión</h3><div className="space-y-4">{[...solicitud.consultas].sort((a, b) => new Date(b.created_at) - new Date(a.created_at)).map(c => <div key={c.id} className="gelia-tag-message"><div className="flex flex-wrap justify-between gap-2"><h4 className="m-0 text-sm font-medium theme-text-main">{[c.consulta_tag && 'TAG', c.consulta_lista && 'Lista'].filter(Boolean).join(' y ')}</h4><span className="text-xs theme-text-muted">{c.estado === 'pendiente' ? 'Pendiente de respuesta' : c.respuesta_positiva ? 'Confirmada' : 'Rechazada'}</span></div>{c.comentario_vendedor && <p>{c.comentario_vendedor}</p>}{c.estado === 'respondida' && <><p className="text-xs theme-text-muted">Respuesta de {c.encargada?.name || 'Supervisión'}</p><p>{c.comentario_encargada || 'Sin comentario adicional.'}</p>{c.evidencia_respuesta_path && <EvidenciaSolicitud path={c.evidencia_respuesta_path} dialogClassName="gelia-tag-lista-overlay" />}{Number(solicitud.vendedor_id) === Number(auth?.user?.id) && !c.leido_vendedor_at && <button type="button" disabled={procesando} className="theme-btn-secondary mt-3" onClick={() => onMarcarLeido(solicitud.id, c.id)}><Check className="w-4 h-4" aria-hidden="true" />Marcar como leída</button>}</>}</div>)}</div></section>}
            </div>
            <footer className="gelia-modal-footer gelia-workflow-actions">{onBitacora && <button type="button" className="theme-btn-secondary" onClick={onBitacora}><History className="w-4 h-4" aria-hidden="true" />Ver bitácora</button>}<button type="button" data-dialog-close className="theme-btn-primary">Cerrar detalle</button></footer>
        </div>
    </SolicitudDialog>
);

const MenuAccionesPortal = ({ menuAbierto, menuSolicitud, menuPos, setMenuAbierto, setModalForm, setModalRespuesta, setModalBitacora, setModalConsulta, setModalRespuestaConsulta, abrirModalPago, confirmarCambioLista, confirmarRollback, eliminarSolicitud, abrirModalCancelacion, abrirModalConfirmarCancelacion, can, auth, idRespondida, idVerificada, idIncorrecta }) => {
    const menuRef = useRef(null);
    const [posicion, setPosicion] = useState(menuPos);
    useLayoutEffect(() => {
        if (!menuAbierto || !menuRef.current) return;
        const rect = menuRef.current.getBoundingClientRect();
        const abajo = menuPos.anchorBottom + 8;
        const top = abajo + rect.height <= window.innerHeight - 8 ? abajo : Math.max(8, menuPos.anchorTop - rect.height - 8);
        setPosicion({ top: Math.min(top, window.innerHeight - rect.height - 8), left: Math.max(8, Math.min(menuPos.left, window.innerWidth - rect.width - 8)) });
        menuRef.current.querySelector('button')?.focus({ preventScroll: true });
    }, [menuAbierto, menuPos]);
    useEffect(() => {
        if (!menuAbierto) return undefined;
        const tecla = event => {
            const items = [...(menuRef.current?.querySelectorAll('button') || [])];
            const indice = items.indexOf(document.activeElement);
            if (event.key === 'Escape') { event.preventDefault(); setMenuAbierto(null); menuPos.trigger?.focus(); }
            if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
                event.preventDefault();
                const siguiente = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : (indice + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
                items[siguiente]?.focus();
            }
            if (event.key === 'Tab') setMenuAbierto(null);
        };
        document.addEventListener('keydown', tecla);
        return () => document.removeEventListener('keydown', tecla);
    }, [menuAbierto, menuPos, setMenuAbierto]);
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
            <div ref={menuRef} className={`gelia-tag-menu fixed z-[1000] flex flex-col gap-0.5 rounded-xl border theme-border theme-surface p-1.5 shadow-lg w-56`} style={{ top: posicion.top, left: posicion.left }} role="menu" aria-label={`Acciones de FOL-${solicitud.id}`}>

                {/* Confirmar Cambio de Lista (Solo si hay alerta) */}
                {esAlertaPago && can('solicitudes.confirmar_cambio_lista') && (
                    <button type="button" role="menuitem" onClick={() => { setMenuAbierto(null); confirmarCambioLista(solicitud.id); }} className={accion}>
                        <TrendingUp className="h-4 w-4" aria-hidden="true" /> Confirmar ajuste
                    </button>
                )}

                {solicitud.vendedor_id === auth.user.id && solicitud.estado?.nombre === 'Incorrecta' && !esAlertaPago && !esVencimiento && (
                    <button type="button" role="menuitem" onClick={() => { setMenuAbierto(null); setModalForm({ abierto: true, modoEdicion: true, solicitud }); }} className={accion}>
                        <Edit2 className="h-4 w-4" aria-hidden="true" /> Reparar solicitud
                    </button>
                )}

                {esVencimiento && solicitud.vendedor_id === auth.user.id && (
                    <p className="m-0 border-b theme-border px-3 py-2 text-xs theme-text-muted">
                        Pago vencido: debe iniciar una nueva solicitud
                    </p>
                )}

                {can('solicitudes.reportar') && esVencimiento && !solicitud.rollback_confirmado_at && (
                    <button type="button" role="menuitem" onClick={() => { setMenuAbierto(null); confirmarRollback(solicitud.id); }} className={accionPeligro}>
                        <ShieldAlert className="h-4 w-4" aria-hidden="true" /> Confirmar reversión
                    </button>
                )}

                {puedeConsultar && (
                    <button type="button" role="menuitem" onClick={() => { setMenuAbierto(null); setModalConsulta({ abierto: true, solicitud }); }} className={accion}>
                        <MessageSquare className="h-4 w-4" aria-hidden="true" /> Consultar TAG o lista
                    </button>
                )}

                {puedeResponderConsultaSolicitud(auth) && consultaPendiente && (
                    <button type="button" role="menuitem" onClick={() => { setMenuAbierto(null); setModalRespuestaConsulta({ abierto: true, solicitud, consulta: consultaPendiente }); }} className={accion}>
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
                    <button type="button" role="menuitem" onClick={() => { setMenuAbierto(null); abrirModalPago(solicitud); }} className={accion}>
                        <CreditCard className="h-4 w-4" aria-hidden="true" /> Confirmar pago
                    </button>
                )}

                {puedeSolicitarCancelacion && (
                    <button type="button" role="menuitem" onClick={() => { setMenuAbierto(null); abrirModalCancelacion(solicitud); }} className={accionPeligro}>
                        <Ban className="h-4 w-4" aria-hidden="true" /> Solicitar cancelación
                    </button>
                )}

                {puedeConfirmarCancelacion && (
                    <button type="button" role="menuitem" onClick={() => { setMenuAbierto(null); abrirModalConfirmarCancelacion(solicitud); }} className={accionPeligro}>
                        <XCircle className="h-4 w-4" aria-hidden="true" /> Confirmar cancelación
                    </button>
                )}

                {/* Verificado (Paso final) — flujos tienda también quedan pendientes de verificar */}
                {can('solicitudes.verificar')
                    && solicitud.estado?.nombre === 'Respondida'
                    && solicitud.pago_confirmado
                    && !esAlertaPago
                    && idVerificada && (
                    <button type="button" role="menuitem" onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud, estadoId: idVerificada }); }} className={accion}>
                        <CheckSquare className="h-4 w-4" aria-hidden="true" /> Verificado
                    </button>
                )}

                {/* Aprobar (Encargada) — flujos tienda: marca concluida para vendedora, sigue pendiente de verificar */}
                {can('solicitudes.reportar') && !esAlertaPago && !esCancelada && solicitud.estado?.nombre === 'Pendiente' && idRespondida && (
                    <button type="button" role="menuitem" onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud, estadoId: idRespondida }); }} className={accion}>
                        <CheckCircle2 className="h-4 w-4" aria-hidden="true" /> Aprobar proceso
                    </button>
                )}

                {puedeCorregirRespuesta && (
                    <button type="button" role="menuitem" onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud, estadoId: idRespondida }); }} className={accion}>
                        <CheckCircle2 className="h-4 w-4" aria-hidden="true" /> Corregir respuesta
                    </button>
                )}

                {/* Reportar error — staff en etapas activas; vendedora dueña solo en Respondida */}
                {puedeReportarError && !esAlertaPago && idIncorrecta && (
                    <button type="button" role="menuitem" onClick={() => { setMenuAbierto(null); setModalRespuesta({ abierto: true, solicitud, estadoId: idIncorrecta }); }} className={accionPeligro}>
                        <AlertOctagon className="h-4 w-4" aria-hidden="true" /> Reportar error
                    </button>
                )}

                {/* Bitácora */}
                {can('configuracion.ver_auditoria') && (
                    <button type="button" role="menuitem" onClick={() => { setMenuAbierto(null); setModalBitacora({ abierto: true, solicitud }); }} className={`${accion} mt-1 border-t theme-border pt-2`}>
                        <History className="h-4 w-4" aria-hidden="true" /> Ver bitácora
                    </button>
                )}

                {/* Eliminar */}
                {can('solicitudes.eliminar') && (
                    <button type="button" role="menuitem" onClick={() => eliminarSolicitud(solicitud.id)} className={`${accionPeligro} mt-1 border-t theme-border pt-2`}>
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
    metricas = null,
    resumen = null,
    listas_filtro = listas,
    tipos_cliente_filtro = tipos_cliente,
    auth
}) {
    const idRespondida = idEstadoPorNombre(estados, 'Respondida');
    const idVerificada = idEstadoPorNombre(estados, 'Verificada');
    const idIncorrecta = idEstadoPorNombre(estados, 'Incorrecta');

    const [detalleId, setDetalleId] = useState(null);
    const [modalAccion, setModalAccion] = useState(null);
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
        || detalleId !== null
        || modalAccion !== null
        || menuAbierto !== null;

    const {
        tabActiva,
        busqueda,
        tipoFecha,
        fechaInicio,
        fechaFin,
        filtroVendedor,
        filtroMotivo, filtroLista, filtroTipoCliente, filtroTag,
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

    const accionPrincipal = (solicitud) => {
        const ultima = [...(solicitud.auditorias || [])].sort((a, b) => b.id - a.id).find(a => !/AUTOMÁTICAMENTE|SISTEMA AUTOMÁTICO/i.test(a.motivo_reporte || ''));
        if (/ALERTA DE PAGO/i.test(ultima?.motivo_reporte || '')) return null;
        const consulta = solicitud.consultas?.find(c => c.estado === 'pendiente');
        let label, ejecutar;
        if (consulta && puedeResponderConsultaSolicitud(auth)) {
            label = 'Responder consulta'; ejecutar = () => setModalRespuestaConsulta({ abierto: true, solicitud, consulta });
        } else if (solicitud.estado?.nombre === 'Pendiente' && can('solicitudes.reportar') && idRespondida) {
            label = 'Aprobar'; ejecutar = () => setModalRespuesta({ abierto: true, solicitud, estadoId: idRespondida });
        } else if (solicitud.estado?.nombre === 'Incorrecta' && Number(solicitud.vendedor_id) === Number(auth?.user?.id) && solicitud.motivo_incorrecta !== 'vencimiento_pago') {
            label = 'Corregir'; ejecutar = () => setModalForm({ abierto: true, modoEdicion: true, solicitud });
        } else if (solicitud.estado?.nombre === 'Respondida' && solicitud.pago_confirmado && can('solicitudes.verificar') && idVerificada) {
            label = 'Verificar'; ejecutar = () => setModalRespuesta({ abierto: true, solicitud, estadoId: idVerificada });
        }
        return label ? <button type="button" className="gelia-tag-quick-action" onClick={ejecutar}>{label}</button> : null;
    };

    const eliminarSolicitud = (id) => { setMenuAbierto(null); setModalAccion({ accion: 'eliminar', solicitud: menuSolicitud }); };

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
            router.reload({ only: ['solicitudes', 'metricas', 'resumen'], preserveState: true, preserveScroll: true, showProgress: false });
        }, 15000);
        return () => clearInterval(interval);
    }, []);

    useEffect(() => {
        const handleScroll = (event) => {
            if (event.target instanceof Element && event.target.closest('.gelia-tag-menu')) return;
            setMenuAbierto(null);
        };
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

    const confirmarCambioLista = () => setModalAccion({ accion: 'lista', solicitud: menuSolicitud });
    const confirmarRollback = () => setModalAccion({ accion: 'rollback', solicitud: menuSolicitud });

    const abrirMenu = (e, solicitud) => {
        const btn = e.currentTarget; const rect = btn.getBoundingClientRect(); const menuWidth = 224; const menuHeight = 220;
        const spaceBelow = window.innerHeight - rect.bottom; const openUpward = spaceBelow < menuHeight + 16;
        let top = openUpward ? rect.top - menuHeight - 8 : rect.bottom + 8; let left = rect.right - menuWidth; if (left < 8) left = 8;
        setMenuPos({ top, left, anchorTop: rect.top, anchorBottom: rect.bottom, trigger: btn }); setMenuSolicitud(solicitud); setMenuAbierto(menuAbierto === solicitud.id ? null : solicitud.id);
    };

    const solicitudesFiltradas = solicitudes.data || [];
    const hayFiltros = tabActiva !== 'TODAS' || Boolean(busqueda) || filtrosAdicionalesActivos > 0;
    const limpiarConsulta = () => aplicarFiltros({
        lista_id: '', tipo_cliente_id: '', tag: '',
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
            {modalPago.abierto && <ModalAccionSolicitud accion="pago" onClose={() => setModalPago({ abierto: false, solicitud: null })} solicitud={modalPago.solicitud} />}
            {modalCancelacion.abierto && (
                <ModalAccionSolicitud accion="solicitar"
                    onClose={() => setModalCancelacion({ abierto: false, solicitud: null })}
                    solicitud={modalCancelacion.solicitud}
                    listas={listas}
                />
            )}
            {modalConfirmarCancelacion.abierto && (
                <ModalAccionSolicitud accion="cancelar"
                    onClose={() => setModalConfirmarCancelacion({ abierto: false, solicitud: null })}
                    solicitud={modalConfirmarCancelacion.solicitud}
                    onProcesando={setProcesandoAccion}
                />
            )}
            {modalAccion && <ModalAccionSolicitud {...modalAccion} onClose={() => setModalAccion(null)} />}
            {detalleId !== null && solicitudesFiltradas.find(s => s.id === detalleId) && <ModalDetalleSolicitud solicitud={solicitudesFiltradas.find(s => s.id === detalleId)} auth={auth} onClose={() => setDetalleId(null)} onMarcarLeido={marcarConsultaLeida} procesando={procesandoAccion} onBitacora={can('configuracion.ver_auditoria') ? () => { setModalBitacora({ abierto: true, solicitud: solicitudesFiltradas.find(s => s.id === detalleId) }); setDetalleId(null); } : null} />}
            <GeliaPageShell className="gelia-solicitudes-workspace gelia-tag-lista space-y-5">
                <p className="sr-only" aria-live="polite">{copiadoId ? 'Número de cliente copiado' : ''}</p>
                <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div className="min-w-0">
                        <h1 className="m-0 text-2xl font-semibold theme-text-main text-balance">Solicitudes</h1>
                        <p className="m-0 mt-1 text-sm theme-text-muted">Gestiona solicitudes de TAG y cambios de lista</p>
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

                {resumen ? (
                    <ResumenSolicitudes
                        resumen={resumen}
                        filtroTipoCliente={filtroTipoCliente}
                        onFiltrarTipo={(tipoClienteId) => aplicarFiltros({ tipo_cliente_id: tipoClienteId })}
                        onLimpiarTipo={() => aplicarFiltros({ tipo_cliente_id: '' })}
                    />
                ) : metricas && <IndicadoresSolicitudes metricas={metricas} onSeleccionar={tab => aplicarFiltros({ tab })} />}

                <FiltrosSolicitudes
                    tabActiva={tabActiva}
                    busqueda={busqueda}
                    tipoFecha={tipoFecha}
                    fechaInicio={fechaInicio}
                    fechaFin={fechaFin}
                    filtroVendedor={filtroVendedor}
                    filtroMotivo={filtroMotivo}
                    filtroLista={filtroLista} filtroTipoCliente={filtroTipoCliente} filtroTag={filtroTag}
                    listas={listas_filtro} tiposCliente={tipos_cliente_filtro}
                    vendedores={vendedores}
                    variante="tag-lista"
                    mostrarEliminadas={can('solicitudes.eliminadas')}
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
                                <article key={solicitud.id} className="gelia-tag-card flex flex-col gap-3 rounded-xl border theme-border theme-surface p-4">
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
                                    <ResumenDetalle solicitud={solicitud} auth={auth} onAbrir={() => setDetalleId(solicitud.id)} />
                                    <div className="flex items-center justify-between border-t theme-border pt-2">
                                        <CotizacionSolicitud solicitud={solicitud} />
                                        <div className="flex flex-wrap items-center justify-end gap-2">{accionPrincipal(solicitud)}<button type="button" onClick={(e) => abrirMenu(e, solicitud)} aria-haspopup="menu" aria-expanded={menuAbierto === solicitud.id} aria-label={`Acciones de FOL-${solicitud.id}`} className="rounded-lg border theme-border theme-element p-2 transition-colors duration-200 hover:border-[var(--color-primario)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"><MoreVertical className="h-5 w-5 theme-text-main" aria-hidden="true" /></button></div>
                                    </div>
                                </article>
                            );
                        })
                    )}
                </div>

                <div className="hidden overflow-hidden rounded-xl border theme-border theme-surface lg:block">
                    <div className="overflow-x-auto">
                        <table className="gelia-tag-table w-full min-w-[1000px] border-collapse text-left">
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
                                                <ResumenDetalle solicitud={solicitud} auth={auth} onAbrir={() => setDetalleId(solicitud.id)} />
                                            </td>
                                            <td className="px-4 py-3 align-top">
                                                <CotizacionSolicitud solicitud={solicitud} />
                                            </td>
                                            <td className="px-4 py-3 align-top">
                                                <div className={`${estatus.clase} whitespace-nowrap`}><StatusIcon className="h-3.5 w-3.5" aria-hidden="true" /><span>{estatus.label}</span></div>
                                            </td>
                                            <td className="sticky-actions px-4 py-3 text-center align-top">
                                                <div className="flex flex-wrap items-center justify-end gap-2">{accionPrincipal(solicitud)}<button type="button" onClick={(e) => abrirMenu(e, solicitud)} aria-haspopup="menu" aria-expanded={menuAbierto === solicitud.id} aria-label={`Acciones de FOL-${solicitud.id}`} className="rounded-lg border theme-border theme-element p-2 transition-colors duration-200 hover:border-[var(--color-primario)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"><MoreVertical className="h-5 w-5 theme-text-main" aria-hidden="true" /></button></div>
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
                    esVerificacion={modalRespuesta.estadoId === idVerificada}
                    esReporteError={Number(modalRespuesta.estadoId) === Number(idIncorrecta)}
                />
            )}
            {modalBitacora.abierto && <ModalBitacoraSolicitud onClose={() => setModalBitacora({ ...modalBitacora, abierto: false })} solicitud={modalBitacora.solicitud} listas={listas_filtro} tiposCliente={tipos_cliente_filtro} procesos={procesos} />}
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
