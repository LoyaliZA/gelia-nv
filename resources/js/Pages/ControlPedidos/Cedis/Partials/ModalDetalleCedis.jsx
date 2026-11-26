import { esRegistroMunicipal } from '../../Municipal/terminalMunicipal';
import { etiquetaMarcarEnviado } from './etiquetaEnvioCedis';
import IntegracionFisicaConjunto from '../../Municipal/IntegracionFisicaConjunto';
import { presentacionPedidoCedis } from '../../Partials/presentacionPedidoCedis';
import RutaPedidoTienda from '../../Partials/RutaPedidoTienda';
import SeccionCaratulaMunicipalCedis, { AccionImprimirCaratula } from './SeccionCaratulaMunicipalCedis';
import { esCapturaTienda } from '../../Partials/flujoCapturaPedido';
import ConsultaSolicitudTienda from '../../Partials/ConsultaSolicitudTienda';
import { esTransferenciaDocumental } from '../../Partials/SeguimientoTransferenciaTienda';
import { pedidoEsTrasladoInternoPdv } from '../../Partials/destinoSucursalPedido';
import { uuidSolicitud } from '../../../../utils/uuidSolicitud';
import {
    cambioATiendaNacional,
    integracionFisicaPendiente,
    piezasComplementoNacional,
    sucursalRecoleccionNacional,
    vinculoFisicoNacional,
} from '../../Nacional/superficieNacional';
import ListaProductosOk from '../../Partials/ListaProductosOk';
import usePedidoDialog from '../../Partials/usePedidoDialog';
import NavegacionPedido from '../../Partials/NavegacionPedido';
import React, { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { createPortal } from 'react-dom';
import { router, usePage } from '@inertiajs/react';
import {
    X, CheckCircle2, AlertTriangle, FileText, User, Truck, PackageCheck, Undo2, Image, Download,
} from 'lucide-react';
import {
    badgeEmpaqueSemantico,
    cedisConsultaEsRevisionMercancia,
    contextoRecoleccionTienda,
    terminologiaEmpaqueConsultaCedis,
    badgeRetrasoGuia,
    badgesRetrasoSla,
    badgeConComplementos,
    complementosDe,
    esPedidoEmpacadoCedis,
    formatearMoneda,
    etiquetaAlmacen,
    etiquetaSucursal,
    etiquetaCostoEnvio,
    formatearFechaHoraAuditoria,
    badgeEstadoFisico,
    esFasePreVenta,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
    tieneGuiaPdfDisponible,
    etiquetasInstanciaRevision,
    revisionSinExistenciaAbierta,
    LABELS_RESOLUCION_SIN_EXISTENCIA,
    etiquetaOrigenGuia,
    etiquetaEnvio,
    LABEL_NOTA_COMPRA_CAMPO,
    mostrarNotaCompraCedis,
    empacarRequiereModalBultos,
} from '../../Partials/pedidosBmaStyles';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import DireccionPedidoResumen from '../../Partials/DireccionPedidoResumen';
import { codigoDireccionCliente } from '../../Partials/codigoDireccionCliente';
import ModalVistaPreviaDocumento, { MiniaturaDocumento } from '../../Partials/ModalVistaPreviaDocumento';
import ModalConfirmarAccion from '../../Partials/ModalConfirmarAccion';
import ModalMarcarEmpacadoBultos from './ModalMarcarEmpacadoBultos';
import ModalAlertaPedido from '../../Partials/ModalAlertaPedido';
import SeccionGuiaRastreo from '../../Partials/SeccionGuiaRastreo';
import AvisoOperativoPedido from '../../Partials/AvisoOperativoPedido';
import ListaErroresPedido from '../../Partials/ListaErroresPedido';
import { THEME_INPUT, THEME_SELECT, THEME_TEXTAREA } from '../../../../utils/geliaTheme';
import useDispositivoCampo from '../../../Activos/Partials/useDispositivoCampo';

const SECCION = `${THEME_LABEL} mb-3 block`;
const SECCION_WRAP = 'border-b theme-border pb-6 last:border-0';

const Campo = ({ label, value }) => (
    <div>
        <p className="text-xs font-semibold theme-text-muted m-0">{label}</p>
        <p className="text-sm font-bold theme-text-main m-0 mt-0.5">{value ?? '—'}</p>
    </div>
);

function AccionEnvio({ icono: Icono, etiqueta, descripcion = etiqueta, onClick, descarga, deshabilitada }) {
    const clase = 'grid size-11 place-items-center rounded-lg border theme-border theme-text-main '
        + 'enabled:hover:border-[var(--color-primario)] focus-visible:outline-2 focus-visible:outline-offset-2 '
        + 'focus-visible:outline-[var(--color-primario)] disabled:opacity-40';
    const icono = <Icono size={16} aria-hidden="true" />;

    return (
        <span className="group relative">
            {descarga ? (
                <a
                    className={`${clase} hover:border-[var(--color-primario)]`}
                    href={descarga.url}
                    download={descarga.nombre_original || 'guia.pdf'}
                    aria-label={etiqueta}
                >
                    {icono}
                </a>
            ) : (
                <button type="button" className={clase} onClick={onClick} disabled={deshabilitada} aria-label={etiqueta}>
                    {icono}
                </button>
            )}
            <span
                role="tooltip"
                className="pointer-events-none absolute right-full top-1/2 z-10 mr-1 hidden -translate-y-1/2 whitespace-nowrap rounded-md border theme-border bg-[var(--theme-surface-solid)] px-2 py-1 text-xs theme-text-main group-hover:block group-focus-within:block"
            >
                {descripcion}
            </span>
        </span>
    );
}

const comprobantesDe = (pedido) => (pedido?.documentos || []).filter((d) => d.tipo === 'comprobante' || !d.tipo);
const remisionDe = (pedido) => (pedido?.documentos || []).find((d) => d.tipo === 'remision');
const pdfPedidoDe = (pedido) => (pedido?.documentos || []).find((d) => d.tipo === 'pdf_pedido')
    || (pedido?.documentos || []).find((d) => d.tipo === 'cotizacion');

export default function ModalDetalleCedis({
    abierto, onClose, pedido: pedidoInicial, onReportarErrorDatos, onMarcarApartado,
}) {
    const { auth } = usePage().props;
    const permisos = auth?.user?.permissions || [];
    const puedeReabrir = permisos.includes('control_pedidos.reabrir') || auth?.user?.roles?.includes('Super Admin');
    const puedeEnviar = permisos.includes('control_pedidos.cedis.enviar') || auth?.user?.roles?.includes('Super Admin');
    const [pedido, setPedido] = useState(pedidoInicial);
    const [procesando, setProcesando] = useState(false);
    const [docPreview, setDocPreview] = useState(null);
    const [confirmacion, setConfirmacion] = useState(null);
    const [modalEmpacarBultos, setModalEmpacarBultos] = useState(false);
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'success', titulo: '', mensaje: '' });
    const [reporteSinEx, setReporteSinEx] = useState({ descripcion: '', comentario: '' });
    const [seleccionEnvios, setSeleccionEnvios] = useState({});
    const [guiasEnvio, setGuiasEnvio] = useState({});
    const [revisionExistenciasId, setRevisionExistenciasId] = useState(null);
    const [respuestasComplemento, setRespuestasComplemento] = useState({});
    const [errorComplemento, setErrorComplemento] = useState('');
    const claveComplemento = useRef(uuidSolicitud());
    const { esCampo } = useDispositivoCampo();

    useEffect(() => {
        if (abierto && pedidoInicial) {
            setPedido(pedidoInicial);
            setProcesando(false);
            setConfirmacion(null);
            setModalEmpacarBultos(false);
            setDocPreview(null);
            const pendientes = [...(pedidoInicial.cajas || [])]
                .filter((c) => (c.estatus_recoleccion || 'pendiente') === 'pendiente');
            const sel = {};
            const guias = {};
            pendientes.forEach((c) => {
                sel[c.id] = pendientes.length === 1;
                guias[c.id] = c.numero_rastreo || '';
            });
            setSeleccionEnvios(sel);
            setGuiasEnvio(guias);
        }
    }, [abierto, pedidoInicial?.id]);

    useEffect(() => {
        if (!abierto || !pedidoInicial?.guias_pdf_por_caja) return;
        setPedido(pedidoInicial);
        setDocPreview((actual) => {
            if (!actual || actual.tipo !== 'guia') return actual;
            return (pedidoInicial.documentos || []).some((doc) => doc.id === actual.id) ? actual : null;
        });
    }, [abierto, pedidoInicial]);

    const dialog = usePedidoDialog({ abierto: abierto && Boolean(pedido), onClose, bloqueado: procesando });

    if (!abierto || !pedido) return null;

    const municipal = esRegistroMunicipal(pedido);
    const presentacion = presentacionPedidoCedis(pedido);
    const pdfPedido = pdfPedidoDe(pedido);
    const esTienda = cedisConsultaEsRevisionMercancia(pedido);
    const empaqueConsulta = esTienda ? terminologiaEmpaqueConsultaCedis(pedido) : null;
    const trasladoInterno = pedidoEsTrasladoInternoPdv(pedido);
    const fase = pedido.estatus?.fase_ciclo;
    const badgeEmpaque = badgeEmpaqueSemantico(fase, pedido.es_resguardo, Boolean(pedido.resguardo_apartado_at));
    const badgeRetraso = pedido.guia_retraso ? badgeRetrasoGuia() : null;
    const badgesSla = badgesRetrasoSla(pedido);
    const badgeComp = badgeConComplementos(pedido);
    const complementos = complementosDe(pedido);
    const comprobantes = comprobantesDe(pedido);
    const remision = remisionDe(pedido);
    const evidenciasApartado = (pedido?.documentos || []).filter((d) => d.tipo === 'evidencia_apartado');
    const evidenciasCondicion = (pedido?.documentos || []).filter((d) => d.tipo === 'evidencia_condicion');
    const revisionesTodas = [...(pedido.revisiones_producto || pedido.revisionesProducto || [])]
        .sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0));
    const revisiones = revisionesTodas.filter((r) => !r.retirada_at);
    const revisionesRetiradas = revisionesTodas.filter((r) => r.retirada_at);
    const instancias = etiquetasInstanciaRevision(revisiones);
    const docsDeProducto = (revId) => evidenciasCondicion.filter(
        (d) => d.relacion_tipo === 'revision_producto' && String(d.relacion_id) === String(revId),
    );
    const revisionConDetalle = (r) => (
        r.cantidad_solicitada != null
        || r.estado_fisico !== 'bueno'
        || Boolean(r.comentario)
        || Boolean(r.unica_pieza)
        || Boolean(r.mejor_ejemplar)
        || docsDeProducto(r.id).length > 0
    );
    const revisionesConDetalle = revisiones.filter(revisionConDetalle);
    const revisionesOk = revisiones.filter((r) => !revisionConDetalle(r));
    const indiceRevision = (r) => revisiones.findIndex((x) => x === r || (x.id && x.id === r.id));
    const evidenciasLote = evidenciasCondicion.filter(
        (d) => d.relacion_tipo === 'revision_general' || !d.relacion_tipo,
    );
    const evidenciasEnvio = evidenciasCondicion.filter((d) => d.relacion_tipo === 'envio_caja');
    const guiasPorCaja = Boolean(pedido.guias_pdf_por_caja);
    const faltanGuias = guiasPorCaja && (pedido.faltan_guias_por_caja > 0 || !pedido.numero_rastreo);
    const cajasOrdenadas = [...(pedido.cajas || [])]
        .filter((c) => !guiasPorCaja || !['retirada', 'cancelada'].includes(c.estado_operativo))
        .sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0));
    const etiquetaEnvioDoc = (doc) => {
        const idx = cajasOrdenadas.findIndex((c) => String(c.id) === String(doc.relacion_id));
        if (idx >= 0) return `Envío ${idx + 1}`;
        return doc.comentario || 'Envío';
    };
    const badgeFisico = pedido.estado_fisico_general ? badgeEstadoFisico(pedido.estado_fisico_general) : null;
    const tieneRevisionFisica = Boolean(pedido.estado_fisico_general)
        || revisiones.length > 0
        || evidenciasLote.length > 0
        || evidenciasEnvio.length > 0;
    const esErrorCedis = fase === 'INCIDENCIA_CEDIS';
    const esEmpacado = esPedidoEmpacadoCedis(fase);
    const haySinExAbierta = revisiones.some(revisionSinExistenciaAbierta);
    const actualizacionMercanciaPendiente = Boolean(pedido.consulta_actualizacion_pendiente);
    const recolectaSucursal = cambioATiendaNacional(pedido);
    const vinculoDato = vinculoFisicoNacional(pedido);
    const esperaIntegracion = integracionFisicaPendiente(pedido);
    const hijosComplemento = piezasComplementoNacional(pedido);
    const puedeEmpacar = !esTransferenciaDocumental(pedido) && (fase === 'EN_CEDIS' || fase === 'INCIDENCIA_CEDIS') && !pedido.es_resguardo && !haySinExAbierta && !actualizacionMercanciaPendiente && !recolectaSucursal && !esperaIntegracion;
    const puedeReportarSinEx = (fase === 'EN_CEDIS' || fase === 'INCIDENCIA_CEDIS') && !pedido.empacado_at;
    const puedeMarcarEnviado = !esTransferenciaDocumental(pedido) && fase === 'PENDIENTE_DE_ENVIO' && Boolean(puedeEnviar) && !actualizacionMercanciaPendiente && !recolectaSucursal && !esperaIntegracion;
    const puedeReabrirEnvio = fase === 'ENVIADO' && Boolean(puedeReabrir);
    const cajasPendientes = cajasOrdenadas.filter((c) => (c.estatus_recoleccion || 'pendiente') === 'pendiente');
    const cajasRecolectadasCount = pedido.cajas_recolectadas
        ?? cajasOrdenadas.filter((c) => c.estatus_recoleccion === 'recolectada').length;
    const cajasPendientesCount = pedido.cajas_pendientes ?? cajasPendientes.length;
    const idsSeleccionados = Object.entries(seleccionEnvios).filter(([, v]) => v).map(([id]) => Number(id));
    const seleccionCompletaPendientes = cajasPendientes.length > 0
        && idsSeleccionados.length === cajasPendientes.length
        && cajasPendientes.every((c) => idsSeleccionados.includes(Number(c.id)));
    const envioCompleto = guiasPorCaja || cajasOrdenadas.length === 0 || seleccionCompletaPendientes;
    const etiquetaBotonEnviar = envioCompleto
        ? etiquetaMarcarEnviado(pedido)
        : 'Marcar recolectadas';
    const puedeReportarError = ['EN_CEDIS', 'INCIDENCIA_CEDIS', 'PENDIENTE_DE_GUIA', 'PENDIENTE_DE_ENVIO'].includes(fase) && !pedido.es_resguardo;
    const puedeApartar = Boolean(pedido.es_resguardo) && fase === 'EN_CEDIS' && !pedido.resguardo_apartado_at;
    const mostrarGuia = tieneGuiaPdfDisponible(pedido) || Boolean(pedido.numero_rastreo)
        || fase === 'PENDIENTE_DE_ENVIO' || fase === 'ENVIADO';
    const mostrarNotaCompra = mostrarNotaCompraCedis(fase);

    const cajasSeleccionadasParaEnviar = () => {
        if (cajasOrdenadas.length === 0) return undefined;
        if (cajasPendientes.length === 1 && idsSeleccionados.length === 0) {
            const c = cajasPendientes[0];
            const guia = (guiasEnvio[c.id] || '').trim();
            return [{ id: c.id, ...(guia ? { numero_rastreo: guia } : {}) }];
        }
        return idsSeleccionados.map((id) => {
            const guia = (guiasEnvio[id] || '').trim();
            return { id, ...(guia ? { numero_rastreo: guia } : {}) };
        });
    };

    const ejecutarConfirmacion = () => {
        const accion = confirmacion;
        setConfirmacion(null);

        if (accion === 'empacar') {
            setProcesando(true);
            router.post(route('control_pedidos.cedis.marcar_empacado', pedido.id), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    setAlerta({ abierto: true, tipo: 'success', titulo: 'Empacado', mensaje: 'Pedido marcado como empacado.' });
                    onClose();
                },
                onError: () => setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: 'No se pudo marcar como empacado.' }),
                onFinish: () => setProcesando(false),
            });
            return;
        }

        if (accion === 'enviar') {
            const cajas = guiasPorCaja ? undefined : cajasSeleccionadasParaEnviar();
            if (!guiasPorCaja && cajasPendientes.length > 1 && (!cajas || cajas.length === 0)) {
                setAlerta({
                    abierto: true,
                    tipo: 'error',
                    titulo: 'Selección requerida',
                    mensaje: 'Selecciona qué envíos recolectó la paquetería.',
                });
                return;
            }
            setProcesando(true);
            const datos = guiasPorCaja ? { todas_las_cajas_pendientes: true } : (cajas ? { cajas } : {});
            router.post(route('control_pedidos.cedis.marcar_enviado', pedido.id), datos, {
                preserveScroll: true,
                onSuccess: (page) => {
                    if (guiasPorCaja && page?.props?.flash?.error) {
                        setAlerta({ abierto: true, tipo: 'error', titulo: 'Salida pendiente', mensaje: page.props.flash.error });
                        return;
                    }
                    const completo = envioCompleto || cajasPendientes.length <= 1;
                    setAlerta({
                        abierto: true,
                        tipo: 'success',
                        titulo: completo ? 'Enviado' : 'Recolección parcial',
                        mensaje: completo
                            ? 'Pedido marcado como enviado.'
                            : 'Se registraron los envíos recolectados; el pedido sigue pendiente mientras queden cajas.',
                    });
                    onClose();
                },
                onError: () => setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: 'No se pudo registrar la recolección.' }),
                onFinish: () => setProcesando(false),
            });
            return;
        }

        if (accion === 'reportar_sin_ex') {
            if (!reporteSinEx.descripcion.trim() || !reporteSinEx.comentario.trim()) {
                setAlerta({ abierto: true, tipo: 'error', titulo: 'Sin existencias', mensaje: 'Indique producto y comentario para Ventas.' });
                return;
            }
            setProcesando(true);
            router.post(route('control_pedidos.cedis.reportar_sin_existencia', pedido.id), {
                descripcion_producto: reporteSinEx.descripcion,
                comentario: reporteSinEx.comentario,
            }, {
                preserveScroll: true,
                onSuccess: () => {
                    setAlerta({ abierto: true, tipo: 'success', titulo: 'Reportado', mensaje: 'Sin existencias reportada. Ventas fue notificada.' });
                    setReporteSinEx({ descripcion: '', comentario: '' });
                },
                onError: () => setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: 'No se pudo reportar sin existencias.' }),
                onFinish: () => setProcesando(false),
            });
            return;
        }

        if (accion === 'reabrir') {
            setProcesando(true);
            router.post(route('control_pedidos.cedis.reabrir_envio', pedido.id), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    setAlerta({ abierto: true, tipo: 'success', titulo: 'Reabierto', mensaje: 'Pedido pendiente de recolección.' });
                    onClose();
                },
                onError: () => setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: 'No se pudo reabrir el envío.' }),
                onFinish: () => setProcesando(false),
            });
            return;
        }

        if (accion === 'existencias' && revisionExistenciasId) {
            setProcesando(true);
            router.post(route('control_pedidos.cedis.confirmar_stock_sin_existencia', pedido.id), {
                revision_id: revisionExistenciasId,
            }, {
                preserveScroll: true,
                onSuccess: () => setAlerta({ abierto: true, tipo: 'success', titulo: 'Existencias', mensaje: 'Se confirmó que ya hay existencias. El estado físico se conserva.' }),
                onError: () => setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: 'No se pudo confirmar existencias.' }),
                onFinish: () => {
                    setProcesando(false);
                    setRevisionExistenciasId(null);
                },
            });
        }
    };

    const cfgConfirm = confirmacion === 'empacar'
        ? {
            titulo: complementos.length ? 'Confirmar empaque del grupo' : 'Confirmar empaque',
            mensaje: complementos.length
                ? `Se empacará ${pedido.folio} y ${complementos.length} complemento(s).`
                : '¿Confirmar que el pedido fue empacado?',
            etiquetaConfirmar: complementos.length ? 'Empacar grupo' : 'Marcar empacado',
            variante: 'primary',
        }
        : confirmacion === 'enviar'
            ? {
                titulo: envioCompleto ? 'Confirmar envío' : 'Confirmar recolección parcial',
                mensaje: guiasPorCaja
                    ? (cajasPendientes.length === 1
                        ? 'Confirma que la paquetería recogió la caja pendiente.'
                        : `Confirma que la paquetería recogió las ${cajasPendientes.length} cajas pendientes.`)
                    : (envioCompleto
                        ? 'Al confirmar, el pedido sale a recolección y el estado se actualiza para auxiliar, CEDIS y logística.'
                        : `Se marcarán ${idsSeleccionados.length} de ${cajasPendientes.length} envíos pendientes. El pedido seguirá en pendiente de envío.`),
                etiquetaConfirmar: etiquetaBotonEnviar,
                variante: 'primary',
            }
            : confirmacion === 'reabrir'
                ? { titulo: 'Reabrir recolección', mensaje: 'El pedido volverá a pendiente de recolección. Solo si la paquetería no recogió.', etiquetaConfirmar: 'Reabrir', variante: 'danger' }
                : confirmacion === 'reportar_sin_ex'
                    ? { titulo: 'Reportar sin existencias', mensaje: 'El pedido quedará detenido y se avisará a Ventas.', etiquetaConfirmar: 'Reportar', variante: 'danger' }
                    : confirmacion === 'existencias'
                        ? { titulo: 'Confirmar existencias', mensaje: '¿Confirmar que ya hay existencias de esta pieza?', etiquetaConfirmar: 'Ya hay existencias', variante: 'primary' }
                    : null;

    return createPortal(
        <>
            <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={esCampo || procesando ? undefined : onClose}>
                <div
                    {...dialog}
                    aria-label="Detalle del pedido CEDIS"
                    className={`${THEME_MODAL_SHELL} gelia-pedidos-dialog-workspace max-w-4xl w-full flex flex-col`}
                    style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                    onClick={(e) => e.stopPropagation()}
                >
                    <div className="p-4 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                        <div className="min-w-0">
                            <p className="text-xs font-semibold theme-text-muted m-0 mb-1">Detalle CEDIS</p>
                            <EncabezadoFolioPedido pedido={pedido} size="lg" />
                            {pedido.vendedor?.name && (
                                <p className="text-xs font-bold theme-text-muted mt-2 m-0 flex items-center gap-1">
                                    <User className="w-3.5 h-3.5" /> Capturado por: {pedido.vendedor.name}
                                </p>
                            )}
                            <div className="flex flex-wrap gap-2 mt-2">
                                <span className={badgeEmpaque.className} style={badgeEmpaque.style}>
                                    {badgeEmpaque.label}
                                </span>
                                {badgeRetraso && (
                                    <span className={badgeRetraso.className} style={badgeRetraso.style}>
                                        {badgeRetraso.label}
                                    </span>
                                )}
                                {badgesSla.map((b) => (
                                    <span key={b.label} className={b.className} style={b.style}>{b.label}</span>
                                ))}
                                {badgeComp && (
                                    <span className={badgeComp.className} style={badgeComp.style}>
                                        {badgeComp.label}
                                    </span>
                                )}
                            </div>
                        </div>
                        <button type="button" onClick={onClose} disabled={procesando} className="p-2 min-h-[44px] min-w-[44px] rounded-full theme-text-muted hover:theme-text-main outline-none shrink-0 inline-flex items-center justify-center" aria-label="Cerrar">
                            <X className="w-5 h-5" />
                        </button>
                    </div>

                    <NavegacionPedido secciones={[
                        { id: 'cedis-estado', label: 'Estado' },
                        { id: 'cedis-destino', label: 'Destino' },
                        tieneRevisionFisica && { id: 'cedis-revision', label: 'Revisión', cantidad: revisiones.length },
                        { id: 'cedis-pedido', label: 'Datos operativos' },
                    ]} />
                    <div className="gelia-modal-body p-4 md:p-6 space-y-4">
                        {/* 1. Estatus / avisos / errores */}
                        <section id="cedis-estado" tabIndex={-1} className={`${SECCION_WRAP} gelia-pedidos-seccion`}>
                            <h3 className="gelia-pedidos-seccion-titulo mb-3">Estado de empaque</h3>
                            <div className="space-y-3">
                                <IntegracionFisicaConjunto pedido={pedido} mecanismos={['resguardo_envio']} onActualizado={() => router.reload({ preserveScroll: true })} />
                                {(fase === 'EN_CEDIS' || fase === 'INCIDENCIA_CEDIS') && pedido.es_resguardo && (
                                    <AvisoOperativoPedido
                                        label="Resguardo"
                                        tono="blue"
                                        icon={PackageCheck}
                                    >
                                        {pedido.resguardo_apartado_at
                                            ? 'Resguardo apartado — empaque bloqueado'
                                            : 'Empaque bloqueado — en resguardo'}
                                    </AvisoOperativoPedido>
                                )}
                                {esEmpacado && pedido.empacado_at && (
                                    <AvisoOperativoPedido
                                        label="Empaque"
                                        tono="success"
                                        icon={CheckCircle2}
                                    >
                                        Empacado por {(pedido.empacado_por?.name || pedido.empacadoPor?.name) || '—'}
                                        <span className="block text-sm font-bold mt-1 opacity-80 tabular-nums">
                                            {formatearFechaHoraAuditoria(pedido.empacado_at)}
                                        </span>
                                    </AvisoOperativoPedido>
                                )}
                                {esErrorCedis && (
                                    <AvisoOperativoPedido
                                        label="Error reportado"
                                        tono="danger"
                                        icon={AlertTriangle}
                                    >
                                        {pedido.detalle_incidencia_empaque || pedido.detalle_error_datos || 'Error CEDIS reportado'}
                                        {pedido.incidencia_empaque_at && (
                                            <span className="block text-sm font-bold mt-1 opacity-80 tabular-nums">
                                                {(pedido.incidencia_empaque_por?.name || pedido.incidenciaEmpaquePor?.name) || '—'}
                                                {' · '}
                                                {formatearFechaHoraAuditoria(pedido.incidencia_empaque_at)}
                                            </span>
                                        )}
                                    </AvisoOperativoPedido>
                                )}
                                <ListaErroresPedido errores={pedido.errores} />
                                {!pedido.es_resguardo && !esEmpacado && !esErrorCedis && fase === 'EN_CEDIS' && (
                                    <AvisoOperativoPedido label="Estatus" tono="warning">
                                        Pendiente de empaque en almacén
                                    </AvisoOperativoPedido>
                                )}
                                {fase === 'PENDIENTE_DE_GUIA' && (
                                    <AvisoOperativoPedido label="Estatus" tono="info">
                                        Esperando captura de guía por delegado
                                    </AvisoOperativoPedido>
                                )}
                                {fase === 'PENDIENTE_GUIA_CLIENTE' && (
                                    <AvisoOperativoPedido label="Estatus" tono="info">
                                        Esperando guía del cliente (Ventas). No liberar el paquete hasta que cargue la guía.
                                    </AvisoOperativoPedido>
                                )}
                                {fase === 'PENDIENTE_DE_ENVIO' && (
                                    <AvisoOperativoPedido label="Estatus" tono="info">
                                        Listo para verificación y envío en almacén
                                    </AvisoOperativoPedido>
                                )}
                            </div>
                        </section>

                        {/* 2. Nota de compra + guía (nota solo en empaque/envío) */}
                        {!municipal && (mostrarNotaCompra || mostrarGuia) && (
                        <section className={`${SECCION_WRAP} gelia-pedidos-seccion`}>
                            <p className={SECCION}>Empaque y guía</p>
                            <div className="space-y-3">
                                {mostrarNotaCompra && (
                                <AvisoOperativoPedido
                                    label={LABEL_NOTA_COMPRA_CAMPO}
                                    tono={pedido.anexar_remision ? 'success' : 'warning'}
                                    icon={FileText}
                                >
                                    {pedido.anexar_remision
                                        ? 'Incluir nota de compra en el paquete'
                                        : 'No incluir nota de compra (dropshipping)'}
                                </AvisoOperativoPedido>
                                )}
                                {mostrarGuia && !recolectaSucursal && !esperaIntegracion && (
                                    <SeccionGuiaRastreo pedido={guiasPorCaja
                                        ? { ...pedido, documentos: (pedido.documentos || []).filter((doc) => doc.tipo !== 'guia') }
                                        : pedido} onVerPdf={setDocPreview} />
                                )}
                            </div>
                        </section>
                        )}

                        {municipal && <section id="cedis-destino" tabIndex={-1} className={`${SECCION_WRAP} gelia-pedidos-seccion`}>
                            <h3 className="gelia-pedidos-seccion-titulo mb-3">Destino del pedido</h3>
                            <RutaPedidoTienda origen={presentacion.origen} destino={presentacion.destino} tipoDestino="terminal" />
                            <SeccionCaratulaMunicipalCedis pedido={pedido} onVer={setDocPreview} />
                        </section>}
                        {!municipal && (esTienda ? (
                        <section id="cedis-destino" tabIndex={-1} className={`${SECCION_WRAP} gelia-pedidos-seccion`}>
                            <h3 className="gelia-pedidos-seccion-titulo mb-3">Sucursal destino</h3>
                            <p className="text-sm font-bold theme-text-main m-0">
                                {etiquetaSucursal(pedido.sucursal_destino || pedido.sucursalDestino || {
                                    id: pedido.sucursal_destino_id,
                                    nombre: pedido.sucursal_destino_nombre,
                                    codigo: pedido.sucursal_destino_codigo,
                                })}
                            </p>
                        </section>
                        ) : (
                        <section id="cedis-destino" tabIndex={-1} className={`${SECCION_WRAP} gelia-pedidos-seccion`}>
                            <h3 className="gelia-pedidos-seccion-titulo mb-3">Dirección de entrega</h3>
                            <DireccionPedidoResumen
                                conCopia
                                direccion={pedido.direccion_vigente || pedido.direccionVigente}
                                domicilioLegacy={pedido.domicilio_entrega}
                                codigoPostal={pedido.codigo_postal}
                                codigoDireccion={codigoDireccionCliente(
                                    pedido.cliente?.numero_cliente,
                                    (pedido.direccion_vigente || pedido.direccionVigente)?.numero_direccion,
                                )}
                                etiquetaDestinatario="Nombre de quien recibe"
                            />
                        </section>
                        ))}

                        <ConsultaSolicitudTienda pedido={pedido} operarTransferencia onTransferenciaActualizada={avance => setPedido(actual => ({ ...actual, estatus: avance.estatus, estatus_sucursal: avance.estatus_sucursal, empacado_at: avance.empacado_at, solicitud_preparacion: avance.solicitud_preparacion || actual.solicitud_preparacion }))} />
                        {recolectaSucursal && (
                            <AvisoOperativoPedido tono="warning" icon={PackageCheck}>
                                No despachar por paquetería. Recolección en {sucursalRecoleccionNacional(pedido) || 'sucursal'}.
                            </AvisoOperativoPedido>
                        )}
                        {hijosComplemento.length > 0 && (
                            <section className={`${SECCION_WRAP} gelia-pedidos-seccion`} aria-label="Piezas del complemento nacional">
                                <h3 className="gelia-pedidos-seccion-titulo mb-3">Piezas del complemento</h3>
                                <div className="space-y-3">
                                    {hijosComplemento.map((hijo) => (
                                        <div key={hijo.id} className="space-y-2">
                                            <p className="text-xs font-semibold theme-text-muted m-0">{hijo.folio}{hijo.folio_remision ? ` · ${hijo.folio_remision}` : ''}</p>
                                            {(hijo.revisiones_producto || hijo.revisionesProducto || []).map((pieza) => {
                                                const docs = (hijo.documentos || []).filter((d) => d.relacion_tipo === 'revision_producto' && String(d.relacion_id) === String(pieza.id));
                                                const respondida = pieza.cantidad_encontrada != null;
                                                const valor = respuestasComplemento[pieza.id] || {
                                                    cantidad_encontrada: pieza.cantidad_encontrada ?? pieza.cantidad_solicitada ?? 0,
                                                    estado_fisico: pieza.estado_fisico || 'bueno',
                                                };
                                                return (
                                                    <div key={pieza.id} className="p-3 rounded-xl border theme-border space-y-2">
                                                        <p className="text-sm font-bold theme-text-main m-0">{pieza.sku ? `${pieza.sku} · ` : ''}{pieza.descripcion_producto} · {pieza.cantidad_solicitada}</p>
                                                        {docs.map((doc) => (
                                                            <button key={doc.id} type="button" className={`${BTN_SECONDARY} min-h-[44px]`} onClick={() => setDocPreview({ documentos: [doc], indice: 0 })}>
                                                                {doc.nombre_original || 'Evidencia'}
                                                            </button>
                                                        ))}
                                                        {respondida ? (
                                                            <p className="text-sm theme-text-main m-0">Encontradas: {pieza.cantidad_encontrada} · {pieza.estado_fisico}</p>
                                                        ) : (
                                                            <div className="grid gap-2 sm:grid-cols-2">
                                                                <label className={THEME_LABEL} htmlFor={`comp-cant-${pieza.id}`}>Cantidad encontrada
                                                                    <input id={`comp-cant-${pieza.id}`} className={`${THEME_INPUT} w-full min-h-[44px] mt-1`} inputMode="numeric" value={valor.cantidad_encontrada} onChange={(evento) => setRespuestasComplemento((actual) => ({ ...actual, [pieza.id]: { ...valor, cantidad_encontrada: evento.target.value } }))} />
                                                                </label>
                                                                <label className={THEME_LABEL} htmlFor={`comp-est-${pieza.id}`}>Estado
                                                                    <select id={`comp-est-${pieza.id}`} className={`${THEME_SELECT} w-full min-h-[44px] mt-1`} value={valor.estado_fisico} onChange={(evento) => setRespuestasComplemento((actual) => ({ ...actual, [pieza.id]: { ...valor, estado_fisico: evento.target.value } }))}>
                                                                        <option value="bueno">Bueno</option>
                                                                        <option value="regular">Regular</option>
                                                                        <option value="malo">Malo</option>
                                                                        <option value="danado">Dañado</option>
                                                                        <option value="sin_existencia">Sin existencias</option>
                                                                    </select>
                                                                </label>
                                                            </div>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    ))}
                                    <button
                                        type="button"
                                        className={`${BTN_PRIMARY} min-h-[44px]`}
                                        disabled={procesando}
                                        onClick={async () => {
                                            const pendientes = hijosComplemento.flatMap((hijo) => (hijo.revisiones_producto || hijo.revisionesProducto || [])
                                                .filter((pieza) => pieza.cantidad_encontrada == null)
                                                .map((pieza) => {
                                                    const valor = respuestasComplemento[pieza.id] || {
                                                        cantidad_encontrada: pieza.cantidad_solicitada ?? 0,
                                                        estado_fisico: pieza.estado_fisico || 'bueno',
                                                    };
                                                    return {
                                                        revision_id: pieza.id,
                                                        cantidad_encontrada: Number(valor.cantidad_encontrada),
                                                        estado_fisico: valor.estado_fisico,
                                                    };
                                                }));
                                            if (!pendientes.length) return;
                                            setProcesando(true);
                                            setErrorComplemento('');
                                            try {
                                                await axios.post(`/control-pedidos/cedis/${pedido.id}/responder-complemento-nacional`, {
                                                    solicitud_id: claveComplemento.current,
                                                    piezas: pendientes,
                                                    revision_esperada: pedido.revision_registro,
                                                    base_captura: pedido.base_captura,
                                                }, { headers: { Accept: 'application/json' } });
                                                claveComplemento.current = uuidSolicitud();
                                                setAlerta({ abierto: true, tipo: 'success', titulo: 'Complemento', mensaje: 'Se registró la respuesta de las piezas del complemento.' });
                                                router.reload({ preserveScroll: true });
                                            } catch (fallo) {
                                                setErrorComplemento(fallo?.response?.data?.message || 'No se registró la respuesta del complemento.');
                                            } finally {
                                                setProcesando(false);
                                            }
                                        }}
                                    >
                                        Registrar respuesta del complemento
                                    </button>
                                    {errorComplemento && <p className="text-sm theme-text-aviso m-0" role="alert">{errorComplemento}</p>}
                                </div>
                            </section>
                        )}
                        {!esCapturaTienda(pedido) && tieneRevisionFisica && (
                            <section id="cedis-revision" tabIndex={-1} className={`${SECCION_WRAP} gelia-pedidos-seccion`}>
                                <h3 className="gelia-pedidos-seccion-titulo mb-3">{esTienda ? 'Registro de productos' : 'Revisión física'}</h3>
                                <div className="space-y-3">
                                    {pedido.estado_fisico_general && badgeFisico && (
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className={badgeFisico.className} style={badgeFisico.style}>{badgeFisico.label}</span>
                                            {pedido.comentario_fisico_general && (
                                                <p className="text-sm font-bold theme-text-main m-0">{pedido.comentario_fisico_general}</p>
                                            )}
                                        </div>
                                    )}
                                    {revisionesConDetalle.length > 0 && (
                                        <div className="space-y-2">
                                            <p className="text-xs font-semibold theme-text-muted m-0">Productos con detalle</p>
                                            {revisionesConDetalle.map((r) => {
                                                const b = badgeEstadoFisico(r.estado_fisico);
                                                const docs = docsDeProducto(r.id);
                                                const instancia = instancias[indiceRevision(r)];
                                                return (
                                                    <div key={r.id} className="p-3 rounded-xl border theme-border space-y-2">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            {instancia && (
                                                                <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-semibold tabular-nums theme-element border theme-border theme-text-main">
                                                                    {instancia}
                                                                </span>
                                                            )}
                                                            <p className="text-xs font-semibold theme-text-main m-0">{r.descripcion_producto}</p>
                                                            <span className={b.className} style={b.style}>{b.label}</span>
                                                            {(r.unica_pieza || r.mejor_ejemplar) && (
                                                                <div className="gelia-pedidos-atributos" aria-label="Características de la pieza">
                                                                    {r.unica_pieza && <span className="gelia-pedidos-atributo">Única pieza</span>}
                                                                    {r.mejor_ejemplar && <span className="gelia-pedidos-atributo">Mejor ejemplar</span>}
                                                                </div>
                                                            )}
                                                        </div>
                                                        {r.cantidad_solicitada != null && <p className="text-xs theme-text-main m-0">Solicitadas: {r.cantidad_solicitada} · Encontradas: {r.cantidad_encontrada ?? '—'} · Faltantes: {r.cantidad_encontrada == null ? '—' : r.cantidad_solicitada - r.cantidad_encontrada}</p>}
                                                        {r.comentario && <p className="text-xs theme-text-muted font-bold m-0">{r.comentario}</p>}
                                                        {r.estado_fisico === 'sin_existencia' && esFasePreVenta(pedido?.estatus?.fase_ciclo) && (
                                                            <p className="text-xs font-semibold theme-text-info m-0">
                                                                Sin existencias en CEDIS — Ventas debe proceder.
                                                                {r.resolucion ? ` (${LABELS_RESOLUCION_SIN_EXISTENCIA[r.resolucion] || r.resolucion})` : ''}
                                                            </p>
                                                        )}
                                                        {revisionSinExistenciaAbierta(r) && puedeReportarSinEx && (
                                                            <button
                                                                type="button"
                                                                disabled={procesando}
                                                                onClick={() => {
                                                                    setRevisionExistenciasId(r.id);
                                                                    setConfirmacion('existencias');
                                                                }}
                                                                className={`${BTN_SECONDARY} text-xs min-h-[44px]`}
                                                            >
                                                                Ya hay existencias
                                                            </button>
                                                        )}
                                                        {docs.length > 0 && (
                                                            <div className="flex flex-wrap gap-2">
                                                                {docs.map((doc) => (
                                                                    <MiniaturaDocumento key={doc.id} documento={doc} onVer={setDocPreview} />
                                                                ))}
                                                            </div>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                    {revisionesRetiradas.length > 0 && (
                                        <div className="space-y-2">
                                            <p className="text-xs font-semibold theme-text-muted m-0">Piezas retiradas</p>
                                            {revisionesRetiradas.map((r) => {
                                                const docs = docsDeProducto(r.id);
                                                return (
                                                    <div key={r.id} className="p-3 rounded-xl border theme-border space-y-2">
                                                        <p className="text-xs font-semibold theme-text-main m-0">{r.descripcion_producto} · Retirada</p>
                                                        {docs.length > 0 && (
                                                            <div className="flex flex-wrap gap-2">
                                                                {docs.map((doc) => (
                                                                    <MiniaturaDocumento key={doc.id} documento={doc} onVer={setDocPreview} />
                                                                ))}
                                                            </div>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                    {revisionesOk.length > 0 && (
                                        <div className="space-y-1">
                                            <p className="text-xs font-semibold theme-text-muted m-0">Productos OK</p>
                                            <ListaProductosOk productos={revisionesOk}
                                                etiquetaInstancia={(r) => instancias[indiceRevision(r)]} />
                                        </div>
                                    )}
                                    {evidenciasLote.length > 0 && (
                                        <div className="space-y-2">
                                            <p className="text-xs font-semibold theme-text-muted m-0">Evidencias del lote</p>
                                            <div className="flex flex-wrap gap-2">
                                                {evidenciasLote.map((doc) => (
                                                    <MiniaturaDocumento key={doc.id} documento={doc} onVer={setDocPreview} />
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                    {evidenciasEnvio.length > 0 && (
                                        <div className="space-y-2">
                                            <p className="text-xs font-semibold theme-text-muted m-0">Foto por envío</p>
                                            {evidenciasEnvio.map((doc) => (
                                                <div key={doc.id} className="space-y-1">
                                                    <p className="text-xs font-semibold theme-text-muted m-0">{etiquetaEnvioDoc(doc)}</p>
                                                    <MiniaturaDocumento documento={doc} onVer={setDocPreview} />
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </section>
                        )}

                        {puedeReportarSinEx && (
                            <section className={SECCION_WRAP}>
                                <p className={SECCION}>Reportar sin existencias</p>
                                <p className="text-xs font-bold theme-text-muted m-0 mb-3">
                                    Si detecta una pieza faltante en empaque, márquela aquí. El pedido se detiene hasta que Ventas decida.
                                </p>
                                <div className="space-y-3">
                                    <div>
                                        <label htmlFor="cedis-faltante-producto" className="text-xs font-semibold theme-text-muted mb-1 block">Producto</label>
                                        <input
                                            id="cedis-faltante-producto" name="producto_faltante" autoComplete="off"
                                            value={reporteSinEx.descripcion}
                                            onChange={(e) => setReporteSinEx((s) => ({ ...s, descripcion: e.target.value }))}
                                            className={`${THEME_INPUT} w-full py-2`}
                                            placeholder="SKU o descripción"
                                        />
                                    </div>
                                    <div>
                                        <label htmlFor="cedis-faltante-nota" className="text-xs font-semibold theme-text-muted mb-1 block">Comentario para Ventas *</label>
                                        <textarea
                                            id="cedis-faltante-nota" name="nota_faltante" autoComplete="off"
                                            value={reporteSinEx.comentario}
                                            onChange={(e) => setReporteSinEx((s) => ({ ...s, comentario: e.target.value }))}
                                            className={`${THEME_TEXTAREA} w-full min-h-[60px]`}
                                            placeholder="Qué falta y cómo debe proceder Ventas…"
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        disabled={procesando}
                                        onClick={() => setConfirmacion('reportar_sin_ex')}
                                        className={`${BTN_SECONDARY} text-xs min-h-[44px] border-sky-500/40 theme-text-info`}
                                    >
                                        Reportar sin existencias
                                    </button>
                                </div>
                            </section>
                        )}

                        {/* 3. Documentos */}
                        {(comprobantes.length > 0 || remision || evidenciasApartado.length > 0 || complementos.length > 0 || (municipal && pdfPedido)) && (
                            <section className={SECCION_WRAP}>
                                <p className={SECCION}>Documentos</p>
                                {municipal && pdfPedido && (
                                    <div className="flex items-center gap-3 p-4 rounded-xl border theme-border theme-element mb-3">
                                        <FileText className="w-8 h-8 shrink-0" style={{ color: 'var(--color-primario)' }} />
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-bold theme-text-main m-0 truncate">{pdfPedido.nombre_original || 'Pedido Wizerp'}</p>
                                            <button
                                                type="button"
                                                onClick={() => setDocPreview(pdfPedido)}
                                                className="text-xs font-bold inline-flex items-center gap-1 mt-1 outline-none min-h-[44px]"
                                                style={{ color: 'var(--color-primario)' }}
                                            >
                                                Ver PDF pedido
                                            </button>
                                        </div>
                                    </div>
                                )}
                                {remision && (
                                    <div className="flex items-center gap-3 p-4 rounded-xl border theme-border theme-element mb-3">
                                        <FileText className="w-8 h-8 shrink-0" style={{ color: 'var(--color-primario)' }} />
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-bold theme-text-main m-0 truncate">{remision.nombre_original}</p>
                                            <button
                                                type="button"
                                                onClick={() => setDocPreview(remision)}
                                                className="text-xs font-bold inline-flex items-center gap-1 mt-1 outline-none min-h-[44px]"
                                                style={{ color: 'var(--color-primario)' }}
                                            >
                                                Ver remisión PDF
                                            </button>
                                        </div>
                                    </div>
                                )}
                                {comprobantes.length > 0 && (
                                    <div className="mb-3">
                                        <p className="text-xs font-semibold theme-text-muted mb-2">Comprobantes</p>
                                        <div className="flex flex-wrap gap-2">
                                            {comprobantes.map((doc) => (
                                                <MiniaturaDocumento key={doc.id} documento={doc} onVer={setDocPreview} />
                                            ))}
                                        </div>
                                    </div>
                                )}
                                {evidenciasApartado.length > 0 && (
                                    <div className="mb-3">
                                        <p className="text-xs font-semibold theme-text-muted mb-2">Evidencia de apartado</p>
                                        <div className="flex flex-wrap gap-2">
                                            {evidenciasApartado.map((doc) => (
                                                <MiniaturaDocumento key={doc.id} documento={doc} onVer={setDocPreview} />
                                            ))}
                                        </div>
                                    </div>
                                )}
                                {complementos.length > 0 && (
                                    <div className="space-y-3">
                                        <p className="text-xs font-semibold theme-text-muted m-0">Remisiones del grupo</p>
                                        {[pedido, ...complementos].map((p) => {
                                            const rem = remisionDe(p);
                                            return (
                                                <div key={p.id} className="p-3 rounded-xl border theme-border theme-element space-y-2">
                                                    <p className="text-sm font-semibold theme-text-main m-0">
                                                        {p.folio}
                                                        {p.folio_remision ? ` · ${p.folio_remision}` : ''}
                                                        {p.id === pedido.id ? ' · principal' : ' · complemento'}
                                                    </p>
                                                    <p className="text-xs theme-text-muted font-bold m-0">
                                                        {formatearMoneda(p.total_mercancia)}
                                                    </p>
                                                    {rem && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setDocPreview(rem)}
                                                            className="text-xs font-bold inline-flex items-center gap-1 outline-none min-h-[44px]"
                                                            style={{ color: 'var(--color-primario)' }}
                                                        >
                                                            <FileText className="w-3.5 h-3.5" /> Ver remisión
                                                        </button>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </section>
                        )}

                        {/* 4. Datos operativos */}
                        <section id="cedis-pedido" tabIndex={-1} className={`${SECCION_WRAP} gelia-pedidos-seccion`}>
                            <h3 className="gelia-pedidos-seccion-titulo mb-3">Datos operativos</h3>
                            <div className="grid grid-cols-2 gap-4">
                                {contextoRecoleccionTienda(pedido) && (
                                    <p className="text-sm theme-text-main col-span-2 m-0 break-words">{contextoRecoleccionTienda(pedido)}</p>
                                )}
                                <Campo label="Cliente" value={pedido.cliente?.nombre} />
                                <Campo label="N° Cliente" value={pedido.cliente?.numero_cliente} />
                                <Campo label={presentacion.etiqueta} value={presentacion.nombre} />
                                {!municipal && <>
                                <Campo label="Almacén" value={etiquetaAlmacen(pedido.almacen)} />
                                {!esCapturaTienda(pedido) && (trasladoInterno
                                    ? <Campo label="Recorrido" value="Traslado interno" />
                                    : <Campo label="Paquetería" value={pedido.paqueteria?.nombre} />)}
                                {(!esCapturaTienda(pedido) || pedido.numero_cajas > 0) && <Campo label={esTienda ? empaqueConsulta.campo : 'N° de envíos'} value={pedido.numero_cajas} />}
                                {!esCapturaTienda(pedido) && !trasladoInterno && <>
                                <Campo label="Origen de la guía" value={etiquetaOrigenGuia(pedido)} />
                                <Campo label="Tipo de guía" value={pedido.tipo_guia?.nombre} />
                                <Campo label="Peso real" value={pedido.peso_real_kg != null ? `${pedido.peso_real_kg} kg` : null} />
                                </>}
                                {!esCapturaTienda(pedido) && <Campo label="Seguro" value={pedido.aplica_seguro ? formatearMoneda(pedido.costo_seguro) : 'No aplica'} />}
                                </>}
                                <Campo label="Registrado" value={formatearFechaHoraAuditoria(pedido.created_at)} />
                            </div>
                            {!municipal && (pedido.cajas || []).length > 0 && (
                                <div className="mt-3 space-y-2 rounded-2xl border theme-border theme-surface p-3 sm:p-4">
                                    <div className="flex flex-wrap items-baseline justify-between gap-x-2 gap-y-1">
                                        <p className="text-sm font-semibold theme-text-main m-0">Detalle de envíos (pesaje)</p>
                                        {cajasOrdenadas.length > 0 && (cajasRecolectadasCount > 0 || fase === 'PENDIENTE_DE_ENVIO') && (
                                            <span className="text-xs font-semibold theme-text-muted">
                                                {cajasRecolectadasCount}/{cajasOrdenadas.length} recolectadas
                                            </span>
                                        )}
                                    </div>
                                    <div
                                        role="region"
                                        aria-label="Envíos del pesaje"
                                        tabIndex={0}
                                        className="max-h-44 space-y-2 overflow-y-auto rounded-xl border p-2 [scrollbar-width:thin] focus-visible:outline-2 focus-visible:outline-[var(--color-primario)]"
                                        style={{
                                            borderColor: 'color-mix(in srgb, var(--color-exito) 40%, var(--theme-border))',
                                            background: 'color-mix(in srgb, var(--color-exito) 7%, var(--theme-element-bg))',
                                        }}
                                    >
                                        {cajasOrdenadas.map((c, idx) => {
                                            const pendiente = (c.estatus_recoleccion || 'pendiente') === 'pendiente';
                                            const pdfCaja = guiasPorCaja && (pedido.documentos || []).find(
                                                (doc) => doc.relacion_tipo === 'guia_caja_confirmada' && doc.activo
                                                    && Number(doc.pedido_bma_caja_id) === Number(c.id),
                                            );
                                            const evidenciasCaja = evidenciasEnvio.filter(
                                                (d) => String(d.relacion_id) === String(c.id) && d.url,
                                            );
                                            return (
                                                <div
                                                    key={c.id || idx}
                                                    className="rounded-lg border theme-border theme-surface p-2"
                                                >
                                                    <div className="flex items-center gap-2">
                                                        {!guiasPorCaja && puedeMarcarEnviado && pendiente && (
                                                            <input
                                                                type="checkbox"
                                                                className="shrink-0"
                                                                checked={Boolean(seleccionEnvios[c.id])}
                                                                onChange={(e) => setSeleccionEnvios((prev) => ({
                                                                    ...prev,
                                                                    [c.id]: e.target.checked,
                                                                }))}
                                                                aria-label={`Recolectar envío ${idx + 1}`}
                                                            />
                                                        )}
                                                        <div className="min-w-0 flex-1 [overflow-wrap:anywhere]">
                                                            <p className="flex flex-wrap items-baseline gap-x-2 text-xs font-semibold theme-text-main m-0">
                                                                {etiquetaEnvio(idx, c)}
                                                                <span className={pendiente ? 'font-medium theme-text-muted' : 'font-medium text-[var(--color-exito)]'}>
                                                                    {pendiente ? 'Pendiente' : 'Recolectada'}
                                                                </span>
                                                            </p>
                                                            <p className="text-xs theme-text-main m-0 mt-1">
                                                                {c.largo != null ? `${c.largo}` : '—'}×{c.ancho != null ? `${c.ancho}` : '—'}×{c.alto != null ? `${c.alto}` : '—'} cm
                                                                {c.peso_volumetrico_kg != null ? ` · Vol. ${c.peso_volumetrico_kg} kg` : ''}
                                                                {c.peso_real_kg != null ? ` · Real ${c.peso_real_kg} kg` : ''}
                                                                {c.peso_cobrado_kg != null ? ` · Cobrado ${c.peso_cobrado_kg} kg` : ''}
                                                                {!guiasPorCaja && (c.numero_rastreo || pedido.numero_rastreo)
                                                                    ? ` · Guía: ${c.numero_rastreo || pedido.numero_rastreo}`
                                                                    : ''}
                                                            </p>
                                                        </div>
                                                        <div className="flex shrink-0 gap-1">
                                                            <AccionEnvio
                                                                icono={Image}
                                                                etiqueta={evidenciasCaja.length ? `Ver evidencia del envío ${idx + 1}` : `Envío ${idx + 1} sin evidencia`}
                                                                descripcion={evidenciasCaja.length ? 'Ver evidencia' : 'Sin evidencia'}
                                                                onClick={() => setDocPreview(evidenciasCaja)}
                                                                deshabilitada={!evidenciasCaja.length}
                                                            />
                                                            <AccionEnvio
                                                                icono={Download}
                                                                etiqueta={pdfCaja?.url ? `Descargar guía del envío ${idx + 1}` : `Envío ${idx + 1} sin guía PDF`}
                                                                descripcion={pdfCaja?.url ? 'Descargar guía' : (guiasPorCaja ? 'Guía pendiente de asignación' : 'Sin guía PDF individual')}
                                                                descarga={pdfCaja?.url ? pdfCaja : null}
                                                                deshabilitada={!pdfCaja?.url}
                                                            />
                                                        </div>
                                                    </div>
                                                    {!guiasPorCaja && puedeMarcarEnviado && pendiente && (
                                                        <input
                                                            type="text"
                                                            className="mt-2 w-full text-xs font-bold rounded-lg border theme-border theme-element px-2 py-1.5 outline-none"
                                                            placeholder="Guía de este envío (opcional si ya hay guía del pedido)"
                                                            value={guiasEnvio[c.id] || ''}
                                                            onChange={(e) => setGuiasEnvio((prev) => ({
                                                                ...prev,
                                                                [c.id]: e.target.value,
                                                            }))}
                                                        />
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                            {pedido.comentarios_drive && (
                                <div className="mt-4">
                                    <p className="text-xs font-semibold theme-text-muted m-0 mb-1">Comentarios para Drive</p>
                                    <p className="text-sm font-bold theme-text-main m-0">{pedido.comentarios_drive}</p>
                                </div>
                            )}
                        </section>

                        {/* 5. Costos */}
                        {!esCapturaTienda(pedido) && <section className={`${SECCION_WRAP} gelia-pedidos-seccion`}>
                            <p className={SECCION}>Costos</p>
                            <div className="space-y-3">
                                <Campo label="Código postal" value={pedido.codigo_postal} />
                                <Campo label="Reexpedición / Zona" value={pedido.zona?.nombre} />
                                {pedido.es_resguardo && (
                                    <Campo
                                        label="Resguardo"
                                        value={pedido.resguardo_apartado_at
                                            ? `Apartado · ${formatearFechaHoraAuditoria(pedido.resguardo_apartado_at)}`
                                            : 'Sí — pendiente de apartar'}
                                    />
                                )}
                                {pedido.detalle_resguardo_apartado && (
                                    <Campo label="Nota apartado" value={pedido.detalle_resguardo_apartado} />
                                )}
                                {pedido.envia_a_otra_persona && <Campo label="Destinatario alterno" value={pedido.envia_otra_persona} />}
                                <div className="mt-2 p-4 rounded-xl border theme-border theme-element space-y-2 text-sm">
                                    <div className="flex justify-between theme-text-muted font-bold"><span>Total de mercancía</span><span>{formatearMoneda(pedido.total_mercancia)}</span></div>
                                    <div className="flex justify-between theme-text-muted font-bold">
                                        <span>{etiquetaCostoEnvio(pedido.paqueteria)}</span>
                                        <span>{formatearMoneda(pedido.costo_envio)}</span>
                                    </div>
                                    <div className="flex justify-between theme-text-muted font-bold">
                                        <span>Costo del seguro</span>
                                        <span>{pedido.aplica_seguro ? formatearMoneda(pedido.costo_seguro) : formatearMoneda(0)}</span>
                                    </div>
                                    <div className="flex justify-between theme-text-muted font-bold">
                                        <span>Total a cubrir</span>
                                        <span>{formatearMoneda(
                                            Number(pedido.total_mercancia || 0)
                                            + Number(pedido.costo_envio || 0)
                                            + (pedido.aplica_seguro ? Number(pedido.costo_seguro || 0) : 0)
                                        )}</span>
                                    </div>
                                    <div className="flex justify-between font-bold" style={{ color: 'var(--color-exito)' }}>
                                        <span>Saldo a favor aplicado</span>
                                        <span>- {formatearMoneda(pedido.saldo_a_favor)}</span>
                                    </div>
                                    <div className="flex justify-between font-semibold pt-2 border-t theme-border" style={{ color: 'var(--color-primario)' }}>
                                        <span>Total a cobrar ahora</span><span>{formatearMoneda(pedido.total_a_cobrar)}</span>
                                    </div>
                                </div>
                            </div>
                        </section>}
                    </div>

                    <div className="gelia-modal-footer flex flex-col-reverse sm:flex-row sm:flex-wrap gap-3 p-4 md:p-6 pb-[max(1rem,env(safe-area-inset-bottom))] border-t theme-border shrink-0">
                        <button type="button" onClick={onClose} className={`${BTN_SECONDARY} theme-element border theme-border outline-none min-h-[44px] w-full sm:w-auto`}>
                            Cerrar
                        </button>
                        {puedeReportarError && (
                            <button
                                type="button"
                                onClick={() => onReportarErrorDatos?.(pedido)}
                                disabled={procesando}
                                className={`${BTN_SECONDARY} theme-element border border-[color:color-mix(in_srgb,var(--color-aviso)_40%,var(--theme-border))] theme-text-aviso outline-none min-h-[44px] w-full sm:w-auto`}
                            >
                                <AlertTriangle className="w-4 h-4 inline mr-1" /> Reportar error
                            </button>
                        )}
                        {puedeApartar && (
                            <button
                                type="button"
                                onClick={() => { onClose(); onMarcarApartado?.(pedido); }}
                                disabled={procesando}
                                className={`${BTN_PRIMARY} flex items-center justify-center gap-2 outline-none disabled:opacity-50 min-h-[44px] w-full sm:w-auto`}
                            >
                                <PackageCheck className="w-4 h-4" /> Marcar apartado
                            </button>
                        )}
                        {vinculoDato?.vinculo?.estado === 'en_traslado' && (
                            <button
                                type="button"
                                disabled={procesando}
                                onClick={async () => {
                                    setProcesando(true);
                                    try {
                                        await axios.post(`/control-pedidos/cedis/${vinculoDato.pedidoId}/custodia-nacional`, { accion: 'recepcion' }, { headers: { Accept: 'application/json' } });
                                        router.reload({ preserveScroll: true });
                                    } finally {
                                        setProcesando(false);
                                    }
                                }}
                                className={`${BTN_PRIMARY} flex items-center justify-center gap-2 outline-none disabled:opacity-50 min-h-[44px] w-full sm:w-auto`}
                            >
                                Confirmar recepción
                            </button>
                        )}
                        {vinculoDato?.vinculo?.estado === 'recibido_cedis' && vinculoDato?.vinculo?.integrado !== true && (
                            <button
                                type="button"
                                disabled={procesando}
                                onClick={async () => {
                                    setProcesando(true);
                                    try {
                                        await axios.post(`/control-pedidos/cedis/${vinculoDato.pedidoId}/custodia-nacional`, { accion: 'liberar' }, { headers: { Accept: 'application/json' } });
                                        router.reload({ preserveScroll: true });
                                    } finally {
                                        setProcesando(false);
                                    }
                                }}
                                className={`${BTN_PRIMARY} flex items-center justify-center gap-2 outline-none disabled:opacity-50 min-h-[44px] w-full sm:w-auto`}
                            >
                                Liberar integración
                            </button>
                        )}
                        {puedeMarcarEnviado && (
                            <button
                                type="button"
                                onClick={() => setConfirmacion('enviar')}
                                disabled={procesando || faltanGuias || (!guiasPorCaja && cajasPendientes.length > 1 && idsSeleccionados.length === 0)}
                                className={`${BTN_PRIMARY} flex items-center justify-center gap-2 outline-none disabled:opacity-50 min-h-[44px] w-full sm:w-auto sm:ml-auto`}
                            >
                                <Truck className="w-4 h-4" /> {etiquetaBotonEnviar}
                            </button>
                        )}
                        {puedeReabrirEnvio && (
                            <button
                                type="button"
                                onClick={() => setConfirmacion('reabrir')}
                                disabled={procesando}
                                className={`${BTN_PRIMARY} flex items-center justify-center gap-2 outline-none disabled:opacity-50 min-h-[44px] w-full sm:w-auto sm:ml-auto`}
                            >
                                <Undo2 className="w-4 h-4" /> Reabrir recolección
                            </button>
                        )}
                        {puedeEmpacar && (
                            <button
                                type="button"
                                onClick={() => {
                                    if (empacarRequiereModalBultos(pedido)) {
                                        setModalEmpacarBultos(true);
                                    } else {
                                        setConfirmacion('empacar');
                                    }
                                }}
                                disabled={procesando}
                                className={`${BTN_PRIMARY} flex items-center justify-center gap-2 outline-none disabled:opacity-50 min-h-[44px] w-full sm:w-auto sm:ml-auto`}
                            >
                                <CheckCircle2 className="w-4 h-4" /> {complementos.length ? 'Empacar grupo' : 'Marcar empacado'}
                            </button>
                        )}
                    </div>
                </div>
            </div>

            <ModalVistaPreviaDocumento
                abierto={Boolean(docPreview)}
                documento={Array.isArray(docPreview) ? null : docPreview}
                documentos={Array.isArray(docPreview) ? docPreview : null}
                onClose={() => setDocPreview(null)}
                accionesDocumento={docPreview?.tipo === 'caratula_municipal'
                    ? (documento) => <AccionImprimirCaratula documento={documento} />
                    : null}
            />
            <ModalMarcarEmpacadoBultos
                abierto={modalEmpacarBultos}
                pedido={pedido}
                onClose={() => setModalEmpacarBultos(false)}
                onExito={() => {
                    setAlerta({ abierto: true, tipo: 'success', titulo: 'Empacado', mensaje: 'Pedido marcado como empacado.' });
                    onClose();
                }}
            />
            <ModalConfirmarAccion
                abierto={Boolean(cfgConfirm)}
                titulo={cfgConfirm?.titulo}
                mensaje={cfgConfirm?.mensaje}
                etiquetaConfirmar={cfgConfirm?.etiquetaConfirmar}
                variante={cfgConfirm?.variante}
                onClose={() => setConfirmacion(null)}
                onConfirm={ejecutarConfirmacion}
            />
            <ModalAlertaPedido
                abierto={alerta.abierto}
                tipo={alerta.tipo}
                titulo={alerta.titulo}
                mensaje={alerta.mensaje}
                onClose={() => setAlerta({ ...alerta, abierto: false })}
            />
        </>,
        document.body
    );
}
