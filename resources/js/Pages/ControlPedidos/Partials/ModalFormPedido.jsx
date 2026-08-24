import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, usePage, router } from '@inertiajs/react';
import axios from 'axios';
import {
    X, Search, Save, Send, RotateCcw, ImagePlus, Trash2, AlertTriangle, PenLine, Link2, Cloud, HardDrive, Scale, FileText, ArrowRight, CheckCircle2,
} from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';
import { THEME_INPUT, THEME_SELECT, THEME_TEXTAREA } from '../../../utils/geliaTheme';
import InputMoneda from './InputMoneda';
import { codigoDireccionCliente, labelOpcionDireccion } from './codigoDireccionCliente';
import {
    calcularTotalCobrar,
    calcularResumenCoberturaPago,
    calcCostoSeguro,
    calcularPesoCobradoGuia,
    paqueteriaTieneCobertura,
    etiquetaCostoEnvio,
    calcularCostoTarifaLocal,
    esCotizacionLista,
    formatearMoneda,
    etiquetaAlmacen,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
    validarCamposEnvioPedido,
    etiquetaEstatusPedido,
    LABELS_ESTATUS_ENVIO,
    LABELS_MOTIVO_REPESAJE,
    LABELS_ESTATUS_POR_FASE,
    formatearFechaNegocio,
    LABEL_NOTA_COMPRA_PREGUNTA,
    LABEL_GUIA_EMPRESA,
    LABEL_GUIA_CLIENTE,
    etiquetaEnvio,
} from './pedidosBmaStyles';
import ModalVistaPreviaDocumento, { MiniaturaDocumento } from './ModalVistaPreviaDocumento';
import { archivosImagenDesdeClipboard } from './archivosDesdeClipboard';
import { elegirDireccionParaPedido, manualDireccionCompleta, faltantesManualDireccion } from './elegirDireccionParaPedido';
import ModalGenerarLinkDireccion from './ModalGenerarLinkDireccion';
import AvisoOperativoPedido from './AvisoOperativoPedido';
import TarjetaEnvioPedido from './TarjetaEnvioPedido';
import SeccionRevisionFisicaPedido from './SeccionRevisionFisicaPedido';
import CamposDireccionPedido, {
    CAMPOS_DIRECCION_VACIOS,
    camposDesdeDireccion,
    resumirCamposDireccion,
} from './CamposDireccionPedido';
import { resolverReexpedicionForm, separarCostoEnvioDeReexpedicion, costoEnvioParaPersistir, costoReexpedicionDeZona } from './resolverReexpedicionForm';
import ModalConfirmarAccion from './ModalConfirmarAccion';
import SeccionPagosExhibicion from './SeccionPagosExhibicion';
import { CAMPOS_ERROR_DATOS } from './ModalReportarErrorDatos';

const STORAGE_BORRADOR = 'control_pedidos.borrador_pedido_v2';
/** Autoguardado local: rápido, no satura red. */
const AUTOSAVE_LOCAL_MS = 800;
/** Autoguardado BD: solo tras pausa larga. */
const AUTOSAVE_BD_MS = 15000;

function serializarBorrador(data) {
    const {
        comprobantes, documentos_eliminar, enviar, pedido_id, _nombre_cliente, ...resto
    } = data;
    return resto;
}

function fingerprintBd(data) {
    return JSON.stringify(serializarBorrador(data));
}

function tieneContenidoParaBd(data) {
    return Boolean(
        data.cliente_id
        || String(data.folio_remision || '').trim()
        || data.origen_id
        || data.cliente_direccion_id
        || String(data.domicilio_entrega || '').trim()
        || (data.total_mercancia !== '' && data.total_mercancia != null && Number(data.total_mercancia) > 0)
        || data.catalogo_paqueteria_id
        || data.almacen_id
    );
}

/** Hay un borrador local con datos útiles (para preguntar al abrir Nuevo pedido). */
export function hayBorradorPedidoLocal() {
    const borrador = leerBorradorLocal();
    if (!borrador) return false;
    return Boolean(borrador.pedido_id) || tieneContenidoParaBd(borrador);
}

function leerBorradorLocal() {
    if (typeof window === 'undefined') return null;
    try {
        const raw = localStorage.getItem(STORAGE_BORRADOR);
        if (raw) return JSON.parse(raw);
        // migración suave desde v1
        const legacy = localStorage.getItem('control_pedidos.borrador_pedido_v1');
        return legacy ? JSON.parse(legacy) : null;
    } catch {
        return null;
    }
}

function guardarBorradorLocal(data) {
    if (typeof window === 'undefined') return;
    localStorage.setItem(STORAGE_BORRADOR, JSON.stringify({
        ...serializarBorrador(data),
        ...(data.pedido_id ? { pedido_id: data.pedido_id } : {}),
        ...(data._nombre_cliente ? { _nombre_cliente: data._nombre_cliente } : {}),
    }));
}

function limpiarBorradorLocal() {
    if (typeof window === 'undefined') return;
    localStorage.removeItem(STORAGE_BORRADOR);
    localStorage.removeItem('control_pedidos.borrador_pedido_v1');
}

const SECCION = `${THEME_LABEL} mb-3 block`;
const SECCION_WRAP = 'border-b theme-border pb-8 last:border-0';
const WRAP_FALTANTE = 'rounded-xl p-2 ring-2 ring-[var(--color-peligro)] bg-[color-mix(in_srgb,var(--color-peligro)_10%,transparent)]';
const COLOR_EXITO = { color: 'var(--color-exito)' };
const COLOR_INFO = { color: 'var(--color-info)' };

const mensajeAxios = (err, fallback) => {
    const payload = err?.response?.data;
    if (typeof payload?.message === 'string' && payload.message) return payload.message;
    const errs = payload?.errors;
    if (errs && typeof errs === 'object') {
        const msgs = Object.values(errs).flatMap((v) => (Array.isArray(v) ? v : [v])).filter(Boolean);
        if (msgs.length) return msgs.slice(0, 4).join(' · ');
    }
    return fallback;
};

const headersJsonCsrf = () => ({
    Accept: 'application/json',
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
    'X-Requested-With': 'XMLHttpRequest',
});

function formDefaults(pedido = null, tiposOperacion = []) {
    const tipoCodigo = pedido?.tipo_operacion_envio?.codigo
        || tiposOperacion.find((t) => String(t.id) === String(pedido?.tipo_operacion_envio_id))?.codigo
        || '';
    let modoResguardo = 'abierto';
    if (tipoCodigo === 'RESGUARDO_COMPLEMENTARIO') modoResguardo = 'complementario';
    else if (tipoCodigo === 'RESGUARDO_ABIERTO') modoResguardo = 'abierto';

    return {
        origen_id: pedido?.origen_id ?? pedido?.origen?.id ?? '',
        tipo_operacion_envio_id: pedido?.tipo_operacion_envio_id || '',
        modo_resguardo: modoResguardo,
        pedido_principal_id: pedido?.pedido_principal_id || '',
        cliente_id: pedido?.cliente_id || '',
        numero_cliente: pedido?.cliente?.numero_cliente || '',
        folio_remision: pedido?.folio_remision || '',
        fecha: pedido?.fecha?.slice?.(0, 10) || new Date().toISOString().slice(0, 10),
        catalogo_banco_id: pedido?.catalogo_banco_id || '',
        almacen_id: pedido?.almacen_id || '',
        catalogo_tipo_caja_id: pedido?.catalogo_tipo_caja_id || '',
        numero_cajas: pedido?.numero_cajas ?? '',
        peso_real_kg: pedido?.peso_real_kg ?? '',
        peso_cobrado_guia_kg: pedido?.peso_cobrado_guia_kg ?? '',
        catalogo_tipo_guia_id: pedido?.catalogo_tipo_guia_id || '',
        codigo_postal: pedido?.codigo_postal || '',
        domicilio_entrega: pedido?.domicilio_entrega || '',
        cliente_direccion_id: pedido?.cliente_direccion_id || '',
        direccion_manual_excepcion: false,
        motivo_direccion_manual: '',
        total_mercancia: pedido?.total_mercancia ?? '',
        catalogo_paqueteria_id: pedido?.catalogo_paqueteria_id || '',
        costo_envio: pedido?.costo_envio ?? '',
        cajas_costos: (pedido?.cajas || [])
            .filter((c) => c.estado_operativo !== 'retirada' && c.uuid_operativo)
            .map((c) => ({
                uuid_operativo: c.uuid_operativo,
                costo_envio: c.costo_envio ?? '',
                costo_seguro: c.costo_seguro ?? '',
                costo_adicional: c.costo_adicional ?? '',
                concepto_adicional: c.concepto_adicional ?? '',
            })),
        reabrir_pago_costos: false,
        motivo_reapertura_pago: '',
        aplica_saldo_favor: Number(pedido?.saldo_a_favor || 0) > 0,
        saldo_a_favor: pedido?.saldo_a_favor ?? '',
        saf_aplicaciones: (pedido?.saf_aplicaciones || [])
            .filter((a) => a.estado !== 'liberado')
            .map((a) => ({ saf_credito_id: a.saf_credito_id, monto: a.monto, folio: a.credito?.folio })),
        aplica_seguro: pedido?.aplica_seguro || false,
        cliente_proporciona_guia: pedido?.cliente_proporciona_guia || false,
        envio_por_cobrar: pedido?.envio_por_cobrar || false,
        costo_seguro: pedido?.costo_seguro ?? '',
        envia_a_otra_persona: pedido?.envia_a_otra_persona || false,
        envia_otra_persona: pedido?.envia_otra_persona || '',
        es_resguardo: pedido?.es_resguardo || false,
        anexar_remision: pedido?.anexar_remision || false,
        catalogo_zona_id: pedido?.catalogo_zona_id || '',
        comentarios_drive: pedido?.comentarios_drive || '',
        comprobantes: [],
        documentos_eliminar: [],
        enviar: false,
    };
}

export default function ModalFormPedido({
    abierto,
    onClose,
    pedido = null,
    catalogos = {},
    direccionesNormalizadas = false,
    recuperarBorrador = false,
    onPedidoCreado = null,
}) {
    const { auth } = usePage().props;
    const permisos = auth?.user?.permissions || [];
    const can = (p) => permisos.includes(p) || auth?.user?.roles?.includes('Super Admin');
    const puedeSeleccionar = can('control_pedidos.direccion.seleccionar') || can('control_pedidos.crear');
    const puedeManual = can('control_pedidos.direccion.usar_manual');

    const modoEdicion = Boolean(pedido?.id);
    const [listaClientes, setListaClientes] = useState([]);
    const [mostrarDropdown, setMostrarDropdown] = useState(false);
    const [buscandoCliente, setBuscandoCliente] = useState(false);
    const [infoCliente, setInfoCliente] = useState(pedido?.cliente || null);
    const [alertaDireccion, setAlertaDireccion] = useState(false);
    const [msgDireccion, setMsgDireccion] = useState('');
    const [cargandoDireccion, setCargandoDireccion] = useState(false);
    const [direccionesCliente, setDireccionesCliente] = useState([]);
    const [mostrarExcepcion, setMostrarExcepcion] = useState(false);
    const [previews, setPreviews] = useState([]);
    const [docsEliminar, setDocsEliminar] = useState([]);
    const [pesoVolumetrico, setPesoVolumetrico] = useState(pedido?.peso_volumetrico_kg ?? '');
    const [intentoEnviar, setIntentoEnviar] = useState(false);
    const [avisoForm, setAvisoForm] = useState(null);
    const [avisoPdf, setAvisoPdf] = useState(null);
    const [avisoPesaje, setAvisoPesaje] = useState(null);
    const [pdfDocLocal, setPdfDocLocal] = useState(null);
    const [anexoLocalOk, setAnexoLocalOk] = useState(false);
    const [anexoDocsLocal, setAnexoDocsLocal] = useState([]);
    const [modalLinkDireccion, setModalLinkDireccion] = useState(false);
    const [candidatosPrincipal, setCandidatosPrincipal] = useState([]);
    const [buscandoPrincipal, setBuscandoPrincipal] = useState(false);
    const [principalSeleccionado, setPrincipalSeleccionado] = useState(pedido?.principal || null);
    const [qPrincipal, setQPrincipal] = useState('');
    const [filtroFasePrincipal, setFiltroFasePrincipal] = useState('');
    const [filtroFechaDesdePrincipal, setFiltroFechaDesdePrincipal] = useState('');
    const [filtroFechaHastaPrincipal, setFiltroFechaHastaPrincipal] = useState('');
    const temporizadorBusqueda = useRef(null);
    const temporizadorPrincipal = useRef(null);
    const abortBusqueda = useRef(null);
    const costoReexpedicionAplicado = useRef(0);
    const matchReexpedicionKey = useRef(null);
    const rexStripHechoRef = useRef(false);
    const [costoReexpedicion, setCostoReexpedicion] = useState(0);
    const pedidoBdIdRef = useRef(pedido?.id || null);
    const ultimoFingerprintBd = useRef('');
    const ultimoSyncCedis = useRef('');
    const direccionSuciaRef = useRef(false);
    const capturaManualRef = useRef(false);
    const autoguardandoBd = useRef(false);
    const ignoreOverlayCloseUntil = useRef(0);
    const cuerpoFormRef = useRef(null);
    const [pedidoBdId, setPedidoBdId] = useState(pedido?.id || null);
    const [estadoAuto, setEstadoAuto] = useState({ local: null, bd: null });
    const [motivoRepesaje, setMotivoRepesaje] = useState('');
    const [procesandoPesaje, setProcesandoPesaje] = useState(false);
    /** Optimistic: tras solicitar, hide botón aunque modalForm.pedido aún no refresque. */
    const [consultaPendienteLocal, setConsultaPendienteLocal] = useState(
        () => pedido?.estatus_envio === 'pendiente_pesaje'
    );
    const [pdfLocalOk, setPdfLocalOk] = useState(false);
    const [vistaPrevia, setVistaPrevia] = useState(null);
    const [camposDireccion, setCamposDireccion] = useState({ ...CAMPOS_DIRECCION_VACIOS });
    const [direccionSucia, setDireccionSucia] = useState(false);
    const [guardandoDireccion, setGuardandoDireccion] = useState(false);
    const [confirmarActualizarDir, setConfirmarActualizarDir] = useState(false);
    const [sinDireccionPrincipal, setSinDireccionPrincipal] = useState(false);
    const [safCuenta, setSafCuenta] = useState(null);
    const [cargandoSaf, setCargandoSaf] = useState(false);
    const [safFifoItems, setSafFifoItems] = useState([]);
    const [pagoResumen, setPagoResumen] = useState(null);

    const { data, setData, post, processing, reset, errors, transform } = useForm(formDefaults(pedido, catalogos.tipos_operacion_envio || []));

    const saldoFavorCalculado = data.aplica_saldo_favor ? Number(data.saldo_a_favor || 0) : 0;

    const aplicarFifoSaf = (montoDeseado) => {
        const monto = Math.max(0, Number(montoDeseado) || 0);
        if (monto <= 0) {
            setSafFifoItems([]);
            setData('saf_aplicaciones', []);
            return;
        }
        const creditos = [...(safCuenta?.creditos_usables || [])].sort((a, b) => {
            const fa = String(a.fecha_vencimiento || '');
            const fb = String(b.fecha_vencimiento || '');
            if (fa !== fb) return fa < fb ? -1 : 1;
            return Number(a.id) - Number(b.id);
        });
        let restante = monto;
        const items = [];
        for (const c of creditos) {
            if (restante <= 0) break;
            const disp = Number(c.monto_disponible) || 0;
            const tomar = Math.min(disp, restante);
            if (tomar <= 0) continue;
            items.push({
                saf_credito_id: c.id,
                folio: c.folio,
                canal_origen: c.canal_origen,
                disponible: disp,
                fecha_vencimiento: c.fecha_vencimiento,
                monto: Math.round(tomar * 100) / 100,
            });
            restante = Math.round((restante - tomar) * 100) / 100;
        }
        setSafFifoItems(items);
        setData('saf_aplicaciones', items.map((i) => ({
            saf_credito_id: i.saf_credito_id,
            monto: i.monto,
            folio: i.folio,
        })));
        const cubierto = items.reduce((a, i) => a + Number(i.monto || 0), 0);
        if (cubierto > 0 && Math.abs(cubierto - monto) > 0.01) {
            setData('saldo_a_favor', cubierto);
        }
    };

    useEffect(() => {
        if (!data.cliente_id) {
            setSafCuenta(null);
            return undefined;
        }
        let cancelado = false;
        setCargandoSaf(true);
        axios.get(route('control_pedidos.cliente.saldo_favor', data.cliente_id), {
            headers: { Accept: 'application/json' },
            params: {
                pedido_id: pedido?.id || undefined,
                almacen_id: data.almacen_id || undefined,
            },
        }).then((res) => {
            if (!cancelado) setSafCuenta(res.data);
        }).catch(() => {
            if (!cancelado) setSafCuenta(null);
        }).finally(() => {
            if (!cancelado) setCargandoSaf(false);
        });
        return () => { cancelado = true; };
    }, [data.cliente_id, pedido?.id, data.almacen_id]);

    const puedeAutoguardarBd = !pedido || ['BORRADOR', 'PESAJE_PENDIENTE', 'PESAJE_RESPONDIDO', 'RECHAZADO_VENDEDORA'].includes(pedido?.estatus?.fase_ciclo);
    const fasePedido = pedido?.estatus?.fase_ciclo;
    const puedeVolverBorrador = ['PESAJE_PENDIENTE', 'PESAJE_RESPONDIDO'].includes(fasePedido);
    const puedeContinuarPedido = fasePedido === 'PESAJE_PENDIENTE' && Boolean(pedido?.pesaje_respondido_at);

    const paqueteriaSeleccionada = (catalogos.paqueterias || []).find(
        (p) => String(p.id) === String(data.catalogo_paqueteria_id)
    );
    const origenSeleccionado = (catalogos.origenes || []).find(
        (o) => String(o.id) === String(data.origen_id || pedido?.origen_id || pedido?.origen?.id || '')
    ) || (pedido?.origen && String(pedido.origen.id) === String(data.origen_id || pedido?.origen_id || pedido?.origen?.id || '')
        ? pedido.origen
        : null);
    const requiereLogistica = origenSeleccionado?.requiere_logistica ?? false;
    const esResguardoAbierto = Boolean(data.es_resguardo) && (data.modo_resguardo || 'abierto') === 'abierto';
    const esResguardoComplementario = Boolean(data.es_resguardo) && data.modo_resguardo === 'complementario';
    const esMunicipioDiferido = !data.es_resguardo && Boolean(paqueteriaSeleccionada?.permite_costo_diferido);
    const logisticaBloqueada = esResguardoComplementario && Boolean(data.pedido_principal_id);
    const camposEnvioBloqueados = esResguardoAbierto || esResguardoComplementario;
    useEffect(() => {
        if (pedido?.estatus_envio === 'pendiente_pesaje') {
            setConsultaPendienteLocal(true);
        } else if (pedido?.estatus_envio && pedido.estatus_envio !== 'pendiente_pesaje') {
            setConsultaPendienteLocal(false);
        }
    }, [pedido?.id, pedido?.estatus_envio]);

    const tienePesajeRespondido = Boolean(pedido?.pesaje_respondido_at)
        || pedido?.estatus_envio === 'pesaje_listo'
        || (pedido?.cajas || []).length > 0
        || pedido?.estatus?.fase_ciclo === 'PESAJE_RESPONDIDO';
    const pendientePesaje = pedido?.estatus_envio === 'pendiente_pesaje' || consultaPendienteLocal;
    const consultaCerrada = Boolean(pedido?.consulta_cerrada || pedido?.consulta_cerrada_at);
    const puedeCerrarConsulta = Boolean(pedido?.puede_cerrar_consulta);
    const esConsultaMercancia = Boolean(pedido?.es_consulta_mercancia) || !requiereLogistica;
    const labelConsulta = esConsultaMercancia ? 'Consulta de mercancía' : 'Consulta de pesaje';
    const guiaCliente = Boolean(data.cliente_proporciona_guia);
    const envioPorCobrar = Boolean(data.envio_por_cobrar);
    const pesoCajasSoloLectura = tienePesajeRespondido || pendientePesaje || camposEnvioBloqueados;
    const cotizacionHabilitada = !requiereLogistica || esResguardoComplementario || tienePesajeRespondido;
    const omiteCostoPorTarifaPeso = !tienePesajeRespondido
        && paqueteriaSeleccionada?.categoria !== 'comercial'
        && paqueteriaSeleccionada?.modalidad_tarifa === 'por_peso';
    const omiteCosto = esMunicipioDiferido || esResguardoAbierto || esResguardoComplementario
        || guiaCliente || envioPorCobrar || omiteCostoPorTarifaPeso;
    const cotizacionLista = esCotizacionLista({
        requiereLogistica,
        cotizacionHabilitada,
        guiaCliente,
        envioPorCobrar,
        esResguardoAbierto,
        esResguardoComplementario,
        omiteCosto,
        catalogo_paqueteria_id: data.catalogo_paqueteria_id,
        catalogo_tipo_guia_id: data.catalogo_tipo_guia_id,
        catalogo_zona_id: data.catalogo_zona_id,
        costo_envio: data.costo_envio,
        total_mercancia: data.total_mercancia,
    });
    const labelCostoEnvio = etiquetaCostoEnvio(paqueteriaSeleccionada);
    const idPedidoAcciones = modoEdicion ? pedido?.id : pedidoBdId;
    direccionSuciaRef.current = direccionSucia;
    capturaManualRef.current = Boolean(mostrarExcepcion || data.direccion_manual_excepcion);

    // Sin tipo: cliente + tipo. Con Envío/Tienda: consulta CEDIS primero; monto/pago tras cierre.
    const tieneTipo = Boolean(data.origen_id || pedido?.origen_id || pedido?.origen?.id);
    const enfocadoEnPesaje = fasePedido === 'PESAJE_PENDIENTE' && !tienePesajeRespondido;
    const mostrarPesaje = tieneTipo || tienePesajeRespondido || pendientePesaje;
    const mostrarPdfPedido = mostrarPesaje || (tieneTipo && !requiereLogistica);
    const mostrarRestoPedido = Boolean(data.origen_id) && (!requiereLogistica || (cotizacionHabilitada && !enfocadoEnPesaje));

    const mostrarLogisticaPostPesaje = requiereLogistica && mostrarPesaje && mostrarRestoPedido;
    const mostrarEnviarPedido = mostrarRestoPedido;
    // Envío: sección pago visible tras respuesta CEDIS; registro solo con consulta cerrada + cotización.
    // Tienda: sección pago tras cerrar consulta.
    const mostrarSeccionPago = mostrarRestoPedido && (
        requiereLogistica
            ? (tienePesajeRespondido && !pendientePesaje)
            : consultaCerrada
    );
    const puedeRegistrarPago = Boolean(idPedidoAcciones)
        && consultaCerrada
        && (requiereLogistica ? cotizacionLista : Number(data.total_mercancia || 0) > 0);
    const requiereConsultaCerradaUi = !esResguardoComplementario;
    const nSec = requiereLogistica
        ? { cliente: 1, tipo: 2, pdf: 3, solPesaje: 4, resp: 5, monto: 6, dir: 7, paq: 8, saf: 9, cot: 10, pago: 11, rem: 12 }
        : { cliente: 1, tipo: 2, pdf: 3, solPesaje: 4, resp: 5, monto: 6, pago: 7, rem: 8 };
    const mostrarMontoMercancia = consultaCerrada && !pendientePesaje && !esResguardoComplementario;
    const totalCobrar = calcularTotalCobrar(
        data.total_mercancia,
        (guiaCliente ? 0 : Number(data.costo_envio || 0)) + (guiaCliente ? 0 : Number(costoReexpedicion || 0)),
        data.aplica_seguro,
        data.costo_seguro,
        saldoFavorCalculado
    );
    const resumenCoberturaVivo = calcularResumenCoberturaPago({
        totalMercancia: data.total_mercancia,
        costoEnvio: guiaCliente
            ? 0
            : Number(data.costo_envio || 0) + Number(costoReexpedicion || 0),
        aplicaSeguro: Boolean(data.aplica_seguro),
        costoSeguro: data.costo_seguro,
        saldoAFavorAplicado: data.aplica_saldo_favor ? saldoFavorCalculado : 0,
        totalPagado: pagoResumen?.total_pagado ?? pagoResumen?.total_recibido ?? 0,
    });
    const pagoPendienteVivo = !idPedidoAcciones
        ? null
        : (pagoResumen == null ? null : resumenCoberturaVivo.pendiente);
    // Tras reemplazar PDF, pdfDocLocal gana: pedido del modal suele quedar stale (mismo bug que anexos).
    const pdfPedidoDoc = pdfDocLocal
        || (pedido?.documentos || []).find((d) => d.tipo === 'pdf_pedido' && !docsEliminar.includes(d.id));
    const anexosPiezasDocs = (() => {
        const fromPedido = (pedido?.documentos || []).filter((d) => d.tipo === 'anexo_piezas' && !docsEliminar.includes(d.id));
        const ids = new Set(fromPedido.map((d) => d.id));
        return [...fromPedido, ...anexoDocsLocal.filter((d) => d?.id && !ids.has(d.id))];
    })();
    const tienePdfPedido = Boolean(pdfPedidoDoc) || pdfLocalOk;
    const tieneAnexoPiezas = anexosPiezasDocs.length > 0 || anexoLocalOk;
    const validacionEnvio = validarCamposEnvioPedido(data, {
        requiereLogistica,
        direccionesNormalizadas,
        esMunicipioDiferido,
        esResguardoAbierto,
        esResguardoComplementario,
        tienePesajeRespondido,
        tienePdfPedido,
        pagoPendiente: pagoPendienteVivo,
        paqueteria: paqueteriaSeleccionada,
        consultaCerrada,
        requiereConsultaCerrada: requiereConsultaCerradaUi,
        manualDireccionCompleta: Boolean(data.direccion_manual_excepcion)
            && manualDireccionCompleta(camposDireccion),
    });
    const enviarPedidoListo = validacionEnvio.valido
        && !(esResguardoComplementario && !data.pedido_principal_id)
        && !pendientePesaje;

    const cambiarTipoPedido = (nuevoId) => {
        if (String(nuevoId) === String(data.origen_id)) return;
        const hayDatos = Boolean(data.cliente_id)
            || Number(data.total_mercancia || 0) > 0
            || Boolean(data.folio_remision)
            || Boolean(idPedidoAcciones);
        if (hayDatos && data.origen_id) {
            const ok = window.confirm(
                'Cambiar el tipo de pedido puede ocultar secciones del flujo (p. ej. pesaje o envío). Los datos ya capturados se conservan. ¿Continuar?'
            );
            if (!ok) return;
        }
        setData('origen_id', nuevoId);
    };
    const abrirVistaPrevia = (docOrDocs, indice = 0) => {
        if (Array.isArray(docOrDocs)) {
            setVistaPrevia({ documentos: docOrDocs.filter((d) => d?.url), indice });
            return;
        }
        if (docOrDocs?.url) {
            setVistaPrevia({ documentos: [docOrDocs], indice: 0 });
        }
    };
    const cajasPesaje = (pedido?.cajas || []).filter((c) => c.estado_operativo !== 'retirada');
    const detalleCajasUi = Boolean(catalogos?.envios_config?.detalle_cajas);
    const pagoValidado = Boolean(pedido?.pago_validado_at);
    const puedeEditarCostosCaja = detalleCajasUi && !pagoValidado && Boolean(pedido?.puede_mutar);
    const tieneCoberturaSeguro = paqueteriaTieneCobertura(paqueteriaSeleccionada?.nombre);
    const paqueteriasComerciales = (catalogos.paqueterias || []).filter((p) => p.categoria === 'comercial');
    const paqueteriasLocales = (catalogos.paqueterias || []).filter((p) => p.categoria !== 'comercial');

    const modalAnidadoAbierto = confirmarActualizarDir || Boolean(vistaPrevia?.documentos?.length) || modalLinkDireccion;

    useEffect(() => {
        // Tras cerrar un modal hijo, ignorar clics al overlay del borrador un instante.
        if (!modalAnidadoAbierto) {
            ignoreOverlayCloseUntil.current = Date.now() + 400;
        }
    }, [modalAnidadoAbierto]);

    useEffect(() => {
        if (!abierto) return;
        setVistaPrevia(null);
        costoReexpedicionAplicado.current = 0;
        matchReexpedicionKey.current = null;
        rexStripHechoRef.current = false;
        setCostoReexpedicion(0);
        ultimoFingerprintBd.current = '';
        ultimoSyncCedis.current = '';
        if (pedido) {
            pedidoBdIdRef.current = pedido.id;
            setPedidoBdId(pedido.id);
            setData(formDefaults(pedido, catalogos.tipos_operacion_envio || []));
            ultimoSyncCedis.current = [
                pedido.updated_at,
                pedido.pesaje_respondido_at,
                pedido.estatus_envio,
                pedido.peso_real_kg,
                pedido.peso_volumetrico_kg,
                pedido.peso_cobrado_guia_kg,
                pedido.numero_cajas,
                pedido.catalogo_estatus_pedido_id,
                (pedido.cajas || []).map((c) => `${c.id}:${c.peso_kg ?? ''}`).join(','),
            ].join('|');
            setInfoCliente(pedido.cliente || null);
            setPesoVolumetrico(pedido.peso_volumetrico_kg ?? '');
            setAlertaDireccion(false);
            setMsgDireccion('');
            setDocsEliminar([]);
            setPreviews([]);
            setDireccionesCliente([]);
            const snap = pedido.direccion_vigente || pedido.direccionVigente;
            const esManual = snap?.origen === 'manual'
                || (!pedido.cliente_direccion_id && Boolean(snap?.calle || snap?.referencias));
            if (esManual && snap) {
                setMostrarExcepcion(true);
                setCamposDireccion(camposDesdeDireccion(snap));
                setData('direccion_manual_excepcion', true);
            } else {
                setMostrarExcepcion(false);
                setCamposDireccion({ ...CAMPOS_DIRECCION_VACIOS });
            }
            setEstadoAuto({ local: null, bd: null });
            setMotivoRepesaje('');
            setPdfLocalOk(false);
            setPdfDocLocal(null);
            setAnexoLocalOk(false);
            setAnexoDocsLocal([]);
            setIntentoEnviar(false);
            setAvisoForm(null);
            setAvisoPdf(null);
            setAvisoPesaje(null);
            if (pedido.cliente_id) {
                cargarDireccionCliente(pedido.cliente_id, {
                    silencioso: true,
                    conservarSeleccion: true,
                    direccionId: pedido.cliente_direccion_id,
                });
            }
        } else if (recuperarBorrador) {
            const borrador = leerBorradorLocal();
            const idBd = borrador?.pedido_id || null;
            pedidoBdIdRef.current = idBd;
            setPedidoBdId(idBd);
            if (borrador) {
                const { pedido_id: _pid, _nombre_cliente: nombreCli, ...restoBorrador } = borrador;
                setData({ ...formDefaults(null, catalogos.tipos_operacion_envio || []), ...restoBorrador, comprobantes: [], documentos_eliminar: [], enviar: false });
                if (borrador.cliente_id) {
                    setInfoCliente({
                        id: borrador.cliente_id,
                        numero_cliente: borrador.numero_cliente,
                        nombre: nombreCli || '',
                    });
                    cargarDireccionCliente(borrador.cliente_id, {
                        silencioso: true,
                        conservarSeleccion: true,
                        direccionId: borrador.cliente_direccion_id,
                    });
                } else {
                    setInfoCliente(null);
                }
            } else {
                setData(formDefaults(null, catalogos.tipos_operacion_envio || []));
                setInfoCliente(null);
            }
            setPesoVolumetrico('');
            setAlertaDireccion(false);
            setMsgDireccion('');
            setPreviews([]);
            setDocsEliminar([]);
            if (!borrador?.cliente_id) {
                setDireccionesCliente([]);
            }
            setMostrarExcepcion(Boolean(borrador?.direccion_manual_excepcion));
            if (borrador?.direccion) {
                setCamposDireccion({ ...CAMPOS_DIRECCION_VACIOS, ...borrador.direccion });
            }
            setMotivoRepesaje('');
            setPdfLocalOk(false);
            setPdfDocLocal(null);
            setAnexoLocalOk(false);
            setAnexoDocsLocal([]);
            setIntentoEnviar(false);
            setAvisoForm(null);
            setAvisoPdf(null);
            setAvisoPesaje(null);
            setEstadoAuto({ local: borrador ? 'Borrador local recuperado' : null, bd: idBd ? `Borrador #${idBd}` : null });
        } else {
            // Nuevo pedido en limpio: no reutilizar borrador local ni el id en BD.
            limpiarBorradorLocal();
            pedidoBdIdRef.current = null;
            setPedidoBdId(null);
            setData(formDefaults(null, catalogos.tipos_operacion_envio || []));
            setInfoCliente(null);
            setPesoVolumetrico('');
            setAlertaDireccion(false);
            setMsgDireccion('');
            setPreviews([]);
            setDocsEliminar([]);
            setDireccionesCliente([]);
            setMostrarExcepcion(false);
            setMotivoRepesaje('');
            setPdfLocalOk(false);
            setPdfDocLocal(null);
            setAnexoLocalOk(false);
            setAnexoDocsLocal([]);
            setIntentoEnviar(false);
            setAvisoForm(null);
            setAvisoPdf(null);
            setAvisoPesaje(null);
            setEstadoAuto({ local: null, bd: null });
        }
    }, [abierto, recuperarBorrador]);

    // Autocarga cuando CEDIS (u otro proceso) actualiza el mismo pedido con el modal abierto.
    useEffect(() => {
        if (!abierto || !pedido?.id) return;
        const fp = [
            pedido.updated_at,
            pedido.pesaje_respondido_at,
            pedido.estatus_envio,
            pedido.peso_real_kg,
            pedido.peso_volumetrico_kg,
            pedido.peso_cobrado_guia_kg,
            pedido.numero_cajas,
            pedido.catalogo_estatus_pedido_id,
            (pedido.cajas || []).map((c) => `${c.id}:${c.peso_kg ?? ''}`).join(','),
        ].join('|');
        if (!ultimoSyncCedis.current) {
            ultimoSyncCedis.current = fp;
            return;
        }
        if (fp === ultimoSyncCedis.current) return;
        ultimoSyncCedis.current = fp;

        // Inertia setData(object) REEMPLAZA todo el form; hay que mergear con updater.
        const origenId = pedido.origen_id ?? pedido.origen?.id ?? null;
        setData((prev) => {
            const paqId = pedido.catalogo_paqueteria_id || prev.catalogo_paqueteria_id || '';
            const zonaId = pedido.catalogo_zona_id || prev.catalogo_zona_id || '';
            const cp = prev.codigo_postal || pedido.codigo_postal || '';
            const rex = resolverReexpedicionForm({
                codigoPostal: cp,
                paqueteriaId: paqId,
                reexpediciones: catalogos.reexpediciones || [],
                zonas: catalogos.zonas || [],
                zonaIdSeleccionada: zonaId,
            });
            const rawEnvio = pedido.costo_envio ?? prev.costo_envio ?? '';
            const { base } = separarCostoEnvioDeReexpedicion(rawEnvio, rex.costoAplicado);
            if (rex.costoAplicado !== costoReexpedicionAplicado.current) {
                costoReexpedicionAplicado.current = rex.costoAplicado;
                matchReexpedicionKey.current = rex.matchKey;
                setCostoReexpedicion(rex.costoAplicado);
            }
            return {
                ...prev,
                ...(origenId ? { origen_id: origenId } : {}),
                peso_real_kg: pedido.peso_real_kg ?? '',
                numero_cajas: pedido.numero_cajas ?? '',
                peso_cobrado_guia_kg: pedido.peso_cobrado_guia_kg ?? '',
                catalogo_tipo_caja_id: pedido.catalogo_tipo_caja_id || '',
                costo_envio: base,
                catalogo_paqueteria_id: paqId || prev.catalogo_paqueteria_id || '',
                catalogo_tipo_guia_id: pedido.catalogo_tipo_guia_id || prev.catalogo_tipo_guia_id || '',
                ...(zonaId ? { catalogo_zona_id: zonaId } : {}),
            };
        });
        if (pedido.pesaje_respondido_at || pedido.estatus_envio === 'pesaje_listo') {
            setPesoVolumetrico(pedido.peso_volumetrico_kg ?? '');
        }
        if (pedido.cliente_id) {
            cargarDireccionCliente(pedido.cliente_id, {
                silencioso: true,
                conservarSeleccion: true,
                direccionId: pedido.cliente_direccion_id,
            });
        }
        setEstadoAuto((s) => ({ ...s, bd: 'Actualizado desde servidor' }));
    }, [
        abierto,
        pedido?.id,
        pedido?.updated_at,
        pedido?.pesaje_respondido_at,
        pedido?.estatus_envio,
        pedido?.peso_real_kg,
        pedido?.peso_volumetrico_kg,
        pedido?.peso_cobrado_guia_kg,
        pedido?.numero_cajas,
        pedido?.catalogo_estatus_pedido_id,
        pedido?.cajas,
        pedido?.catalogo_tipo_caja_id,
        pedido?.costo_envio,
        pedido?.catalogo_paqueteria_id,
        pedido?.catalogo_tipo_guia_id,
        setData,
    ]);

    // Si el form abrió sin tipo pero el pedido sí lo trae, hidratar (evita ocultar pesaje/continuar).
    useEffect(() => {
        if (!abierto || !pedido?.id || data.origen_id) return;
        const origenId = pedido.origen_id ?? pedido.origen?.id;
        if (!origenId) return;
        setData('origen_id', origenId);
    }, [abierto, pedido?.id, pedido?.origen_id, pedido?.origen?.id, data.origen_id, setData]);

    const conDireccionManual = (d) => {
        const capturaActiva = Boolean(d.direccion_manual_excepcion)
            || mostrarExcepcion
            || (!d.cliente_direccion_id && direccionesCliente.length === 0);
        if (!capturaActiva) return d;
        return {
            ...d,
            direccion_manual_excepcion: true,
            direccion: camposDireccion,
            domicilio_entrega: resumirCamposDireccion(camposDireccion) || d.domicilio_entrega || '',
            codigo_postal: camposDireccion.codigo_postal || d.codigo_postal || '',
            cliente_direccion_id: '',
        };
    };

    /** Guarda el borrador en BD (sin archivos) y devuelve su id. */
    const persistirBorradorBd = async () => {
        autoguardandoBd.current = true;
        setEstadoAuto((s) => ({ ...s, bd: 'Servidor · guardando…' }));
        try {
            const base = serializarBorrador(conDireccionManual(data));
            const payload = {};
            Object.entries(base).forEach(([k, v]) => {
                if (typeof v === 'boolean') {
                    payload[k] = v;
                } else {
                    payload[k] = v === '' ? null : v;
                }
            });
            if (!data.cliente_proporciona_guia && !data.envio_por_cobrar) {
                payload.costo_envio = costoEnvioParaPersistir(data.costo_envio, costoReexpedicion);
            }
            payload.pedido_id = pedidoBdIdRef.current || undefined;
            payload.saldo_a_favor = data.aplica_saldo_favor
                ? (Number(data.saldo_a_favor) || (data.saf_aplicaciones || []).reduce((a, i) => a + (Number(i.monto) || 0), 0) || 0)
                : 0;
            payload.saf_aplicaciones = data.aplica_saldo_favor && Number(data.saldo_a_favor) > 0
                ? (data.saf_aplicaciones?.length
                    ? data.saf_aplicaciones.filter((i) => Number(i.monto) > 0)
                    : [{ monto: Number(data.saldo_a_favor) }])
                : [];
            payload.comentarios_drive = data.direccion_manual_excepcion && data.motivo_direccion_manual
                ? `${data.comentarios_drive || ''}\n[Excepción dirección] ${data.motivo_direccion_manual}`.trim()
                : data.comentarios_drive;
            payload.enviar = false;

            let url = '/control-pedidos/autoguardar';
            try {
                url = route('control_pedidos.autoguardar');
            } catch {
                /* ziggy stale */
            }
            const { data: res } = await axios.post(url, payload, {
                headers: headersJsonCsrf(),
            });
            const eraNuevo = !pedidoBdIdRef.current;
            pedidoBdIdRef.current = res.id;
            setPedidoBdId(res.id);
            ultimoFingerprintBd.current = fingerprintBd(data);
            if (!modoEdicion) {
                guardarBorradorLocal({
                    ...data,
                    pedido_id: res.id,
                    _nombre_cliente: infoCliente?.nombre || '',
                });
            }
            setEstadoAuto((s) => ({ ...s, bd: `Servidor · ${res.folio || `#${res.id}`}` }));
            if (eraNuevo) {
                onPedidoCreado?.({ id: res.id, folio: res.folio });
            }
            return res.id;
        } finally {
            autoguardandoBd.current = false;
        }
    };

    /** Devuelve el id del pedido en BD, creando el borrador si aún no existe. */
    const asegurarPedidoEnBd = async ({ zona = 'form' } = {}) => {
        if (idPedidoAcciones) return idPedidoAcciones;
        if (!tieneContenidoParaBd(data)) {
            const msg = 'Capture al menos el origen y el cliente antes de continuar.';
            if (zona === 'pdf') setAvisoPdf({ tipo: 'error', mensaje: msg });
            else if (zona === 'pesaje') setAvisoPesaje({ tipo: 'error', mensaje: msg });
            else setAvisoForm({ tipo: 'error', mensaje: msg });
            return null;
        }
        try {
            return await persistirBorradorBd();
        } catch (err) {
            const msg = mensajeAxios(err, 'No se pudo guardar el borrador en el servidor.');
            if (zona === 'pdf') setAvisoPdf({ tipo: 'error', mensaje: msg });
            else if (zona === 'pesaje') setAvisoPesaje({ tipo: 'error', mensaje: msg });
            else setAvisoForm({ tipo: 'error', mensaje: msg });
            return null;
        }
    };

    // Autoguardado localStorage (rápido)
    useEffect(() => {
        if (!abierto || modoEdicion) return;
        const timer = setTimeout(() => {
            guardarBorradorLocal({
                ...data,
                pedido_id: pedidoBdIdRef.current || undefined,
                _nombre_cliente: infoCliente?.nombre || '',
            });
            setEstadoAuto((s) => ({ ...s, local: 'Local · guardado' }));
        }, AUTOSAVE_LOCAL_MS);
        return () => clearTimeout(timer);
    }, [data, abierto, modoEdicion, infoCliente?.nombre]);

    // Autoguardado BD (lento)
    useEffect(() => {
        if (!abierto || !puedeAutoguardarBd || processing) return;
        if (!tieneContenidoParaBd(data)) return;

        const fp = fingerprintBd(data);
        if (fp === ultimoFingerprintBd.current) return;

        const timer = setTimeout(async () => {
            if (autoguardandoBd.current || processing) return;
            const fpNow = fingerprintBd(data);
            if (fpNow === ultimoFingerprintBd.current) return;
            if (!tieneContenidoParaBd(data)) return;

            try {
                await persistirBorradorBd();
            } catch (err) {
                const msg = err?.response?.data?.message || 'No se pudo autoguardar en servidor';
                setEstadoAuto((s) => ({ ...s, bd: 'Servidor · error' }));
                console.warn('[autoguardar pedido]', msg);
            }
        }, AUTOSAVE_BD_MS);

        return () => clearTimeout(timer);
    }, [data, abierto, puedeAutoguardarBd, processing, modoEdicion, infoCliente?.nombre]);

    useEffect(() => {
        if (pedido?.pesaje_respondido_at) {
            setPesoVolumetrico(pedido.peso_volumetrico_kg ?? '');
            return;
        }
        if (!data.catalogo_tipo_caja_id) {
            setPesoVolumetrico('');
            return;
        }
        const caja = (catalogos.tipos_caja || []).find((c) => String(c.id) === String(data.catalogo_tipo_caja_id));
        setPesoVolumetrico(caja?.peso_volumetrico ?? '');
    }, [data.catalogo_tipo_caja_id, catalogos.tipos_caja, pedido?.pesaje_respondido_at, pedido?.peso_volumetrico_kg]);

    useEffect(() => {
        if (camposEnvioBloqueados) {
            if (data.peso_cobrado_guia_kg !== '') setData('peso_cobrado_guia_kg', '');
            return;
        }
        // Tras pesaje CEDIS el cobrado es suma de max por envío; no recalcular con agregados.
        if (pedido?.pesaje_respondido_at) {
            return;
        }
        const cobrado = calcularPesoCobradoGuia(data.peso_real_kg, pesoVolumetrico);
        if (String(data.peso_cobrado_guia_kg ?? '') !== String(cobrado)) {
            setData('peso_cobrado_guia_kg', cobrado);
        }
    }, [data.peso_real_kg, pesoVolumetrico, camposEnvioBloqueados, pedido?.pesaje_respondido_at]);

    useEffect(() => {
        if (!abierto || !requiereLogistica) return;
        const resolved = resolverReexpedicionForm({
            codigoPostal: data.codigo_postal,
            paqueteriaId: data.catalogo_paqueteria_id,
            reexpediciones: catalogos.reexpediciones || [],
            zonas: catalogos.zonas || [],
            zonaIdSeleccionada: data.catalogo_zona_id,
        });
        const mismoMatch = resolved.matchKey === matchReexpedicionKey.current;

        if (!mismoMatch) {
            matchReexpedicionKey.current = resolved.matchKey;
            // CP+paquetería solo sugiere zona; el monto lo define costo_adicional de la zona.
            if (resolved.zonaIdSugerida !== '' && String(resolved.zonaIdSugerida) !== String(data.catalogo_zona_id)) {
                setData('catalogo_zona_id', resolved.zonaIdSugerida);
            }
        }

        // Pedidos viejos: costo_envio traía flete+reexpedición mezclados; separar una vez al abrir.
        if (!rexStripHechoRef.current) {
            rexStripHechoRef.current = true;
            const costoZona = costoReexpedicionDeZona(catalogos.zonas, data.catalogo_zona_id || resolved.zonaIdSugerida);
            if (costoZona > 0 && data.costo_envio !== '' && data.costo_envio != null) {
                const { base } = separarCostoEnvioDeReexpedicion(data.costo_envio, costoZona);
                if (Number(base) !== Number(data.costo_envio)) {
                    setData('costo_envio', base);
                }
            }
        }
    }, [abierto, requiereLogistica, data.codigo_postal, data.catalogo_paqueteria_id, catalogos.reexpediciones, catalogos.zonas]);

    // Cargo de reexpedición = costo_adicional de la zona elegida (Admin → Zonas Pedido).
    useEffect(() => {
        if (!abierto || !requiereLogistica || guiaCliente) {
            if (costoReexpedicion !== 0) {
                setCostoReexpedicion(0);
                costoReexpedicionAplicado.current = 0;
            }
            return;
        }
        const costo = costoReexpedicionDeZona(catalogos.zonas, data.catalogo_zona_id);
        if (Number(costo) !== Number(costoReexpedicionAplicado.current)) {
            costoReexpedicionAplicado.current = costo;
            setCostoReexpedicion(costo);
        }
    }, [abierto, requiereLogistica, guiaCliente, data.catalogo_zona_id, catalogos.zonas]);

    useEffect(() => {
        if (!data.catalogo_paqueteria_id) {
            setData('costo_seguro', 0);
            setData('aplica_seguro', false);
            return;
        }

        if (data.cliente_proporciona_guia) {
            setData('costo_seguro', 0);
            setData('aplica_seguro', false);
            return;
        }

        const paq = (catalogos.paqueterias || []).find((p) => String(p.id) === String(data.catalogo_paqueteria_id));
        // Seguro sobre flete base + mercancía (sin reexpedición).
        const costo = calcCostoSeguro(paq?.nombre, data.costo_envio, data.total_mercancia);
        setData('costo_seguro', costo);

        if (!paqueteriaTieneCobertura(paq?.nombre)) {
            setData('aplica_seguro', false);
        }
    }, [data.catalogo_paqueteria_id, data.costo_envio, data.total_mercancia, data.cliente_proporciona_guia, catalogos.paqueterias]);

    const marcarGuiaCliente = (checked) => {
        setData('cliente_proporciona_guia', checked);
        if (checked) {
            setData('envio_por_cobrar', false);
            setData('costo_envio', '');
            setData('aplica_seguro', false);
            setData('costo_seguro', 0);
            setCostoReexpedicion(0);
            costoReexpedicionAplicado.current = 0;
            matchReexpedicionKey.current = null;
            setData('catalogo_tipo_guia_id', '');
            setData('catalogo_zona_id', '');
        }
    };

    const marcarEnvioPorCobrar = (checked) => {
        setData('envio_por_cobrar', checked);
        if (checked) {
            setData('cliente_proporciona_guia', false);
            setData('costo_envio', '');
        }
    };

    useEffect(() => {
        if (!abierto || guiaCliente || envioPorCobrar || camposEnvioBloqueados) return;
        const paq = paqueteriaSeleccionada;
        if (!paq || paq.categoria === 'comercial' || !paq.modalidad_tarifa) return;
        if (data.costo_envio !== '' && data.costo_envio != null) return;
        const peso = data.peso_cobrado_guia_kg !== '' && data.peso_cobrado_guia_kg != null
            ? Number(data.peso_cobrado_guia_kg)
            : null;
        const calc = calcularCostoTarifaLocal(paq, Number.isFinite(peso) ? peso : null);
        if (calc != null) {
            setData('costo_envio', calc);
        }
    // Solo auto-sugiere cuando el costo está vacío (override manual se respeta).
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [abierto, data.peso_cobrado_guia_kg, data.catalogo_paqueteria_id, guiaCliente, envioPorCobrar]);

    const fetchClientes = async (term) => {
        const limpio = term.trim();
        if (limpio.length < 2) {
            setListaClientes([]);
            setMostrarDropdown(false);
            return;
        }
        abortBusqueda.current?.abort();
        const controller = new AbortController();
        abortBusqueda.current = controller;
        setBuscandoCliente(true);
        setMostrarDropdown(true);
        try {
            const response = await axios.get('/api/clientes', { params: { q: limpio }, signal: controller.signal });
            setListaClientes(response.data);
        } catch {
            setListaClientes([]);
        } finally {
            if (!controller.signal.aborted) setBuscandoCliente(false);
        }
    };

    const aplicarDireccionSeleccionada = (dir, { marcarAlerta = false } = {}) => {
        if (!dir) {
            setCamposDireccion({ ...CAMPOS_DIRECCION_VACIOS });
            setDireccionSucia(false);
            setData('cliente_direccion_id', '');
            setData('domicilio_entrega', '');
            setData('codigo_postal', '');
            return;
        }
        const campos = camposDesdeDireccion(dir);
        setCamposDireccion(campos);
        setDireccionSucia(false);
        setData('cliente_direccion_id', dir.id);
        setData('domicilio_entrega', dir.direccion_resumida || resumirCamposDireccion(campos));
        setData('codigo_postal', dir.codigo_postal || '');
        setData('direccion_manual_excepcion', false);
        if (dir.anexa_remision) {
            setData('anexar_remision', true);
        }
        if (marcarAlerta) setAlertaDireccion(true);
    };

    const cargarDireccionCliente = async (clienteId, { silencioso = false, conservarSeleccion = false, direccionId = null } = {}) => {
        if (!clienteId) {
            if (!silencioso) {
                setMsgDireccion('Seleccione un cliente primero para cargar la dirección.');
                setAlertaDireccion(false);
            }
            return;
        }

        if (!silencioso) {
            setCargandoDireccion(true);
            setMsgDireccion('');
            setSinDireccionPrincipal(false);
        }
        try {
            const response = await axios.get(`/api/clientes/id/${clienteId}/direccion-envio`);
            const dirs = response.data?.direcciones || [];
            setDireccionesCliente(dirs);
            // No pisar captura en curso (edición de catálogo o excepción manual).
            if (silencioso && (direccionSuciaRef.current || capturaManualRef.current)) return;

            const idSeleccion = conservarSeleccion
                ? (direccionId || data.cliente_direccion_id)
                : null;
            const elegida = elegirDireccionParaPedido(dirs, { direccionId: idSeleccion });
            const tienePrincipal = Boolean(dirs.find((d) => d.es_principal))
                || Boolean(response.data?.tiene_direccion_principal);

            setSinDireccionPrincipal(!tienePrincipal);

            if (elegida) {
                aplicarDireccionSeleccionada(elegida, {
                    marcarAlerta: !conservarSeleccion && !tienePrincipal,
                });
                setMsgDireccion(tienePrincipal
                    ? ''
                    : 'Este cliente no tiene dirección principal verificada. Se preseleccionó una del catálogo; puede cambiarla o marcar una como principal.');
                return;
            }

            aplicarDireccionSeleccionada(null);
            setMsgDireccion(
                'Este cliente no tiene direcciones verificadas en el catálogo. Capture los datos y pulse «Guardar como dirección principal», o genere el link de registro.'
            );
        } catch {
            if (silencioso) return;
            setAlertaDireccion(false);
            setDireccionesCliente([]);
            setSinDireccionPrincipal(true);
            aplicarDireccionSeleccionada(null);
            setMsgDireccion('No se pudo obtener la dirección del cliente. Capture y guarde en el catálogo, o genere el link de registro.');
        } finally {
            setCargandoDireccion(false);
        }
    };

    useEffect(() => {
        if (!abierto || !data.cliente_id) return undefined;
        const clienteId = data.cliente_id;
        const direccionId = data.cliente_direccion_id;
        const recargar = () => cargarDireccionCliente(clienteId, {
            silencioso: true,
            conservarSeleccion: true,
            direccionId,
        });
        const onFocus = () => recargar();
        window.addEventListener('focus', onFocus);
        const t = setInterval(recargar, 15000);
        return () => {
            window.removeEventListener('focus', onFocus);
            clearInterval(t);
        };
    }, [abierto, data.cliente_id, data.cliente_direccion_id]);

    const onCambiarCamposDireccion = (nuevos) => {
        setCamposDireccion(nuevos);
        setDireccionSucia(true);
        setData('codigo_postal', nuevos.codigo_postal || '');
        setData('domicilio_entrega', resumirCamposDireccion(nuevos));
    };

    const descartarCambiosDireccion = () => {
        const actual = direccionesCliente.find((d) => String(d.id) === String(data.cliente_direccion_id));
        aplicarDireccionSeleccionada(actual || null);
    };

    const confirmarGuardarDireccion = async () => {
        setConfirmarActualizarDir(false);
        if (!data.cliente_id || !data.cliente_direccion_id) {
            setAvisoForm({ tipo: 'error', mensaje: 'Seleccione un cliente y una dirección del catálogo.' });
            return;
        }
        setGuardandoDireccion(true);
        try {
            const res = await axios.post(route('control_pedidos.actualizar_campos_direccion'), {
                cliente_id: data.cliente_id,
                cliente_direccion_id: data.cliente_direccion_id,
                pedido_id: idPedidoAcciones || null,
                motivo: 'Actualización de campos de dirección desde el formulario de pedido.',
                ...camposDireccion,
            });
            const nueva = res.data?.direccion;
            if (nueva) {
                setDireccionesCliente((prev) => {
                    const sinVieja = prev.filter((d) => String(d.id) !== String(data.cliente_direccion_id));
                    return [nueva, ...sinVieja];
                });
                aplicarDireccionSeleccionada(nueva);
            }
            setAvisoForm({ tipo: 'success', mensaje: res.data?.message || 'Dirección actualizada.' });
        } catch (err) {
            const msg = mensajeAxios(err, 'No se pudo actualizar la dirección.');
            setAvisoForm({ tipo: 'error', mensaje: msg });
        } finally {
            setGuardandoDireccion(false);
        }
    };

    const registrarDireccionEnCatalogo = async (esPrincipal) => {
        if (!data.cliente_id) {
            setAvisoForm({ tipo: 'error', mensaje: 'Seleccione un cliente antes de guardar la dirección.' });
            return;
        }
        const faltan = faltantesManualDireccion(camposDireccion);
        if (faltan.length) {
            setAvisoForm({
                tipo: 'error',
                mensaje: `Complete antes de guardar: ${faltan.join(', ')}.`,
            });
            return;
        }
        setGuardandoDireccion(true);
        try {
            const res = await axios.post(route('control_pedidos.registrar_direccion_catalogo'), {
                cliente_id: data.cliente_id,
                pedido_id: idPedidoAcciones || null,
                es_principal: Boolean(esPrincipal) || direccionesCliente.length === 0,
                ...camposDireccion,
            }, { headers: headersJsonCsrf() });
            const nueva = res.data?.direccion;
            if (nueva) {
                setDireccionesCliente((prev) => {
                    const resto = prev.filter((d) => String(d.id) !== String(nueva.id));
                    return [nueva, ...resto];
                });
                setMostrarExcepcion(false);
                setDireccionSucia(false);
                aplicarDireccionSeleccionada(nueva);
                if (nueva.es_principal) setSinDireccionPrincipal(false);
                setMsgDireccion('');
            }
            setAvisoForm({ tipo: 'success', mensaje: res.data?.message || 'Dirección guardada en el catálogo.' });
        } catch (err) {
            setAvisoForm({ tipo: 'error', mensaje: mensajeAxios(err, 'No se pudo guardar la dirección en el catálogo.') });
        } finally {
            setGuardandoDireccion(false);
        }
    };

    const manejarBusquedaCliente = (valor) => {
        setData('numero_cliente', valor);
        setInfoCliente(null);
        setData('cliente_id', '');
        setAlertaDireccion(false);
        setMsgDireccion('');
        if (temporizadorBusqueda.current) clearTimeout(temporizadorBusqueda.current);
        temporizadorBusqueda.current = setTimeout(() => fetchClientes(valor), 400);
    };

    const seleccionarCliente = (cliente) => {
        setData({
            ...data,
            numero_cliente: cliente.numero_cliente,
            cliente_id: cliente.id,
            pedido_principal_id: '',
        });
        setInfoCliente(cliente);
        setMostrarDropdown(false);
        setMsgDireccion('');
        setPrincipalSeleccionado(null);
        setCandidatosPrincipal([]);
        setQPrincipal('');
        setFiltroFasePrincipal('');
        setFiltroFechaDesdePrincipal('');
        setFiltroFechaHastaPrincipal('');
        cargarDireccionCliente(cliente.id, { silencioso: true });
    };

    const etiquetaCandidatoPrincipal = (p) => {
        const fase = p.estatus?.fase_ciclo;
        const estatus = LABELS_ESTATUS_POR_FASE[fase] || p.estatus?.nombre_visual || fase || '—';
        const fecha = p.fecha ? formatearFechaNegocio(p.fecha) : '—';
        return `Folio interno: ${p.folio || '—'} · Folio de pedido: ${p.folio_remision || '—'} · ${estatus} · ${fecha}`;
    };

    const buscarPrincipales = async (termino = qPrincipal, extras = {}) => {
        if (!data.cliente_id) {
            setCandidatosPrincipal([]);
            return;
        }
        const fase = extras.fase_ciclo !== undefined ? extras.fase_ciclo : filtroFasePrincipal;
        const desde = extras.fecha_desde !== undefined ? extras.fecha_desde : filtroFechaDesdePrincipal;
        const hasta = extras.fecha_hasta !== undefined ? extras.fecha_hasta : filtroFechaHastaPrincipal;
        setBuscandoPrincipal(true);
        try {
            const { data: json } = await axios.get(route('control_pedidos.candidatos_principal'), {
                params: {
                    cliente_id: data.cliente_id,
                    q: termino || '',
                    ...(fase ? { fase_ciclo: fase } : {}),
                    ...(desde ? { fecha_desde: desde } : {}),
                    ...(hasta ? { fecha_hasta: hasta } : {}),
                },
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            setCandidatosPrincipal(json.data || []);
        } catch {
            setCandidatosPrincipal([]);
        } finally {
            setBuscandoPrincipal(false);
        }
    };

    const aplicarLogisticaDesdePrincipal = (p) => ({
        pedido_principal_id: p.id,
        origen_id: p.origen_id || '',
        almacen_id: p.almacen_id || '',
        cliente_direccion_id: p.cliente_direccion_id || '',
        domicilio_entrega: p.domicilio_entrega || '',
        codigo_postal: p.codigo_postal || '',
        catalogo_paqueteria_id: p.catalogo_paqueteria_id || '',
        catalogo_tipo_guia_id: p.catalogo_tipo_guia_id || '',
        catalogo_zona_id: p.catalogo_zona_id || '',
        catalogo_tipo_caja_id: p.catalogo_tipo_caja_id || '',
        envia_a_otra_persona: Boolean(p.envia_a_otra_persona),
        envia_otra_persona: p.envia_otra_persona || '',
        anexar_remision: Boolean(p.anexar_remision),
        costo_envio: '',
        numero_cajas: '',
        peso_real_kg: '',
        peso_cobrado_guia_kg: '',
    });

    const manejarPaqueteria = (id) => {
        setData('catalogo_paqueteria_id', id);
        const paq = (catalogos.paqueterias || []).find((p) => String(p.id) === String(id));
        if (!paqueteriaTieneCobertura(paq?.nombre)) {
            setData('aplica_seguro', false);
        }
        if (!guiaCliente && !data.envio_por_cobrar && paq?.categoria !== 'comercial' && paq?.modalidad_tarifa) {
            const peso = data.peso_cobrado_guia_kg !== '' && data.peso_cobrado_guia_kg != null
                ? Number(data.peso_cobrado_guia_kg)
                : (pedido?.peso_cobrado_guia_kg != null ? Number(pedido.peso_cobrado_guia_kg) : null);
            const calc = calcularCostoTarifaLocal(paq, Number.isFinite(peso) ? peso : null);
            setData('costo_envio', calc != null ? calc : '');
        }
    };

    const agregarArchivos = (files) => {
        const lista = Array.from(files || []).filter((f) => f?.type?.startsWith('image/'));
        if (!lista.length) return;
        setData('comprobantes', [...(data.comprobantes || []), ...lista]);
        setPreviews((prev) => [...prev, ...lista.map((f) => ({ name: f.name, url: URL.createObjectURL(f) }))]);
    };

    const manejarArchivos = (e) => agregarArchivos(e.target.files);

    const handlePaste = (e) => {
        const pasted = archivosImagenDesdeClipboard(e.clipboardData);
        if (!pasted.length) return;

        // Actualizar consulta: pegar anexos de piezas (uno o varios).
        if (tienePesajeRespondido && !pendientePesaje && idPedidoAcciones && !pedido?.empacado_at) {
            e.preventDefault();
            (async () => {
                for (let i = 0; i < pasted.length; i += 1) {
                    const img = pasted[i];
                    const file = new File([img], `anexo-paste-${Date.now()}-${i}.png`, { type: img.type || 'image/png' });
                    await subirAnexoPiezasArchivo(file);
                }
            })();
            return;
        }

        // Comprobante: SeccionPagosExhibicion detiene el bubbling en su form.
        if (mostrarSeccionPago && puedeRegistrarPago) return;

        // PDF/foto del pedido (flujo temprano).
        if (mostrarPdfPedido && !procesandoPesaje) {
            e.preventDefault();
            const img = pasted[0];
            const file = new File([img], `pedido-paste-${Date.now()}.png`, { type: img.type || 'image/png' });
            subirPdfPedidoArchivo(file);
        }
    };

    const quitarPreviewNuevo = (idx) => {
        const archivos = [...(data.comprobantes || [])];
        archivos.splice(idx, 1);
        setData('comprobantes', archivos);
        setPreviews((prev) => {
            const copia = [...prev];
            if (copia[idx]?.url) URL.revokeObjectURL(copia[idx].url);
            copia.splice(idx, 1);
            return copia;
        });
    };

    const toggleEliminarDoc = (docId) => {
        setDocsEliminar((prev) => {
            const next = prev.includes(docId) ? prev.filter((id) => id !== docId) : [...prev, docId];
            setData('documentos_eliminar', next);
            return next;
        });
    };

    const guardar = (enviarPedido = false, { cerrar = true, alTerminar = null } = {}) => {
        setAvisoForm(null);

        if (enviarPedido) {
            setIntentoEnviar(true);
            if (esResguardoComplementario && !data.pedido_principal_id) {
                setAvisoForm({ tipo: 'error', mensaje: 'Seleccione el pedido principal a complementar.' });
                return;
            }
            if (requiereLogistica && pendientePesaje) {
                setAvisoForm({ tipo: 'error', mensaje: 'Espere la respuesta de pesaje de CEDIS antes de enviar.' });
                return;
            }
            // Misma validación que enviarPedidoListo (tienePdfPedido, consultaCerrada, etc.).
            if (!validacionEnvio.valido) {
                const lista = (validacionEnvio.faltantes || []).join(', ');
                setAvisoForm({
                    tipo: 'error',
                    mensaje: lista
                        ? `Complete: ${lista}.`
                        : (validacionEnvio.mensaje || 'Hay campos faltantes.'),
                });
                requestAnimationFrame(() => {
                    const clave = validacionEnvio.claves?.[0];
                    const nodo = clave
                        ? cuerpoFormRef.current?.querySelector(`[data-campo="${clave}"]`)
                        : null;
                    nodo?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
                return;
            }
        }

        const idDestino = modoEdicion ? pedido.id : pedidoBdIdRef.current;
        const payloadEnvio = (d) => {
            const omitEnvio = Boolean(d.cliente_proporciona_guia) || Boolean(d.envio_por_cobrar);
            const cajasCostos = detalleCajasUi
                ? (d.cajas_costos || []).filter((row) => row?.uuid_operativo)
                : undefined;
            return conDireccionManual({
                ...d,
                costo_envio: omitEnvio
                    ? (d.costo_envio === '' || d.costo_envio == null ? '' : 0)
                    : costoEnvioParaPersistir(d.costo_envio, costoReexpedicion),
                cajas_costos: cajasCostos,
                enviar: undefined,
                saldo_a_favor: d.aplica_saldo_favor
                    ? (Number(d.saldo_a_favor) || (d.saf_aplicaciones || []).reduce((a, i) => a + (Number(i.monto) || 0), 0) || 0)
                    : 0,
                saf_aplicaciones: d.aplica_saldo_favor && Number(d.saldo_a_favor) > 0
                    ? (d.saf_aplicaciones?.length
                        ? d.saf_aplicaciones.filter((i) => Number(i.monto) > 0)
                        : [{ monto: Number(d.saldo_a_favor) }])
                    : [],
                comentarios_drive: d.direccion_manual_excepcion && d.motivo_direccion_manual
                    ? `${d.comentarios_drive || ''}\n[Excepción dirección] ${d.motivo_direccion_manual}`.trim()
                    : d.comentarios_drive,
            });
        };
        const config = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: (page) => {
                if (page?.props?.flash?.error) {
                    setAvisoForm({ tipo: 'error', mensaje: page.props.flash.error });
                    return;
                }
                if (!cerrar) {
                    setData('comprobantes', []);
                    setPreviews([]);
                    alTerminar?.(idDestino);
                    return;
                }
                limpiarBorradorLocal();
                pedidoBdIdRef.current = null;
                setPedidoBdId(null);
                onClose();
                reset();
            },
        };
        if (idDestino) {
            transform((d) => ({ ...payloadEnvio(d), _method: 'put', enviar: enviarPedido }));
            post(route('control_pedidos.update', idDestino), config);
        } else {
            transform((d) => ({ ...payloadEnvio(d), enviar: enviarPedido }));
            post(route('control_pedidos.store'), config);
        }
    };

    const optsPesaje = {
        preserveState: true,
        preserveScroll: true,
        onStart: () => setProcesandoPesaje(true),
        onFinish: () => setProcesandoPesaje(false),
        onError: (errs) => {
            const msg = Object.values(errs || {})[0];
            setAvisoPesaje({
                tipo: 'error',
                mensaje: typeof msg === 'string' ? msg : 'No se pudo completar la acción de pesaje.',
            });
        },
    };

    const subirPdfPedidoArchivo = async (file) => {
        if (!file || procesandoPesaje) return;
        setAvisoPdf(null);
        const id = await asegurarPedidoEnBd({ zona: 'pdf' });
        if (!id) return;
        const fd = new FormData();
        fd.append('pdf_pedido', file);
        setProcesandoPesaje(true);
        try {
            const { data: res } = await axios.post(route('control_pedidos.pdf_pedido.store', id), fd, {
                headers: headersJsonCsrf(),
            });
            setPdfLocalOk(true);
            if (res?.documento) setPdfDocLocal(res.documento);
            setAvisoPdf({ tipo: 'success', mensaje: 'Cargado y listo.' });
        } catch (err) {
            setAvisoPdf({ tipo: 'error', mensaje: mensajeAxios(err, 'No se pudo adjuntar el PDF o foto.') });
        } finally {
            setProcesandoPesaje(false);
        }
    };

    const subirPdfPedido = async (e) => {
        const file = e.target.files?.[0];
        e.target.value = '';
        await subirPdfPedidoArchivo(file);
    };

    const pegarPdfPedido = (e) => {
        const pasted = archivosImagenDesdeClipboard(e.clipboardData);
        if (!pasted.length) return;
        e.preventDefault();
        e.stopPropagation();
        const img = pasted[0];
        const file = new File([img], `pedido-paste-${Date.now()}.png`, { type: img.type || 'image/png' });
        subirPdfPedidoArchivo(file);
    };

    const subirAnexoPiezasArchivo = async (file) => {
        if (!file || !idPedidoAcciones) return;
        setAvisoPesaje(null);
        const fd = new FormData();
        fd.append('anexo_piezas', file);
        setProcesandoPesaje(true);
        try {
            const { data: res } = await axios.post(route('control_pedidos.anexo_piezas.store', idPedidoAcciones), fd, {
                headers: headersJsonCsrf(),
            });
            setAnexoLocalOk(true);
            if (res?.documento) {
                setAnexoDocsLocal((prev) => {
                    if (prev.some((d) => d.id === res.documento.id)) return prev;
                    return [...prev, res.documento];
                });
            }
            setAvisoPesaje({ tipo: 'success', mensaje: 'Anexo de piezas adjuntado.' });
        } catch (err) {
            setAvisoPesaje({ tipo: 'error', mensaje: mensajeAxios(err, 'No se pudo adjuntar el anexo.') });
        } finally {
            setProcesandoPesaje(false);
        }
    };

    const subirAnexoPiezas = async (e) => {
        const files = Array.from(e.target.files || []);
        e.target.value = '';
        for (const file of files) {
            await subirAnexoPiezasArchivo(file);
        }
    };

    const postSolicitudPesaje = (id) => {
        router.post(route('control_pedidos.solicitar_pesaje', id), {}, {
            ...optsPesaje,
            onSuccess: (page) => {
                const err = page?.props?.flash?.error;
                if (err) {
                    setAvisoPesaje({ tipo: 'error', mensaje: err });
                    return;
                }
                setConsultaPendienteLocal(true);
                setAvisoPesaje({
                    tipo: 'success',
                    mensaje: page?.props?.flash?.success || `${labelConsulta} enviada a CEDIS.`,
                });
            },
        });
    };

    const solicitarPesaje = async () => {
        setAvisoPesaje(null);
        if (!data.cliente_id) {
            setAvisoPesaje({ tipo: 'error', mensaje: `Seleccione el cliente antes de solicitar la ${labelConsulta.toLowerCase()}.` });
            return;
        }
        if (!tienePdfPedido) {
            setAvisoPesaje({ tipo: 'error', mensaje: `Adjunte el PDF o una foto del pedido antes de solicitar la ${labelConsulta.toLowerCase()}.` });
            return;
        }
        if (procesandoPesaje || pendientePesaje) return;
        setProcesandoPesaje(true);
        const id = await asegurarPedidoEnBd({ zona: 'pesaje' });
        if (!id) {
            setProcesandoPesaje(false);
            return;
        }
        postSolicitudPesaje(id);
    };

    const cerrarConsulta = () => {
        if (!idPedidoAcciones) return;
        setProcesandoPesaje(true);
        router.post(route('control_pedidos.cerrar_consulta', idPedidoAcciones), {}, {
            preserveScroll: true,
            onFinish: () => setProcesandoPesaje(false),
            onSuccess: (page) => {
                const err = page?.props?.flash?.error;
                if (err) {
                    setAvisoPesaje({ tipo: 'error', mensaje: err });
                    return;
                }
                setAvisoPesaje({ tipo: 'success', mensaje: page?.props?.flash?.success || 'Consulta cerrada.' });
            },
            onError: () => setAvisoPesaje({ tipo: 'error', mensaje: 'No se pudo cerrar la consulta.' }),
        });
    };

    const reabrirConsulta = () => {
        if (!idPedidoAcciones) return;
        if (!window.confirm('¿Reabrir la consulta? El monto y el pago quedarán bloqueados hasta cerrarla de nuevo.')) return;
        setProcesandoPesaje(true);
        router.post(route('control_pedidos.reabrir_consulta', idPedidoAcciones), {}, {
            preserveScroll: true,
            onFinish: () => setProcesandoPesaje(false),
            onSuccess: (page) => {
                const err = page?.props?.flash?.error;
                if (err) {
                    setAvisoPesaje({ tipo: 'error', mensaje: err });
                    return;
                }
                setAvisoPesaje({ tipo: 'success', mensaje: page?.props?.flash?.success || 'Consulta reabierta.' });
            },
            onError: () => setAvisoPesaje({ tipo: 'error', mensaje: 'No se pudo reabrir la consulta.' }),
        });
    };

    const solicitarRepesaje = () => {
        if (!idPedidoAcciones || !motivoRepesaje) {
            setAvisoPesaje({ tipo: 'error', mensaje: 'Seleccione el motivo de la actualización (anexo, retiro o surtido).' });
            return;
        }
        if (motivoRepesaje === 'anexo_piezas' && !tieneAnexoPiezas) {
            setAvisoPesaje({ tipo: 'error', mensaje: 'Adjunte el PDF o foto de las piezas adicionales antes de actualizar la consulta.' });
            return;
        }
        setAvisoPesaje(null);
        router.post(route('control_pedidos.solicitar_repesaje', idPedidoAcciones), { motivo: motivoRepesaje }, {
            ...optsPesaje,
            onSuccess: (page) => {
                const err = page?.props?.flash?.error;
                if (err) {
                    setAvisoPesaje({ tipo: 'error', mensaje: err });
                    return;
                }
                setAvisoPesaje({ tipo: 'success', mensaje: page?.props?.flash?.success || 'Re-pesaje solicitado a CEDIS.' });
            },
        });
    };

    const continuarPedido = () => {
        if (!idPedidoAcciones) return;
        setAvisoPesaje(null);
        router.post(route('control_pedidos.volver_borrador', idPedidoAcciones), {}, {
            ...optsPesaje,
            onSuccess: (page) => {
                const err = page?.props?.flash?.error;
                if (err) {
                    setAvisoPesaje({ tipo: 'error', mensaje: err });
                    return;
                }
                setAvisoPesaje({ tipo: 'success', mensaje: page?.props?.flash?.success || 'Pedido listo para continuar.' });
            },
        });
    };

    const docsExistentes = (pedido?.documentos || []).filter((d) => d.tipo === 'comprobante' && !docsEliminar.includes(d.id));
    const camposIncorrectos = Array.isArray(pedido?.campos_incorrectos) ? pedido.campos_incorrectos : [];
    const esCampoIncorrecto = (key) => camposIncorrectos.includes(key);
    const wrapIncorrecto = (key) => (esCampoIncorrecto(key)
        ? 'rounded-xl ring-2 ring-orange-500/70 bg-orange-500/10 p-2'
        : '');
    const esCampoFaltante = (clave) => intentoEnviar && (validacionEnvio.claves || []).includes(clave);
    const wrapFaltante = (clave) => (esCampoFaltante(clave) ? WRAP_FALTANTE : '');
    const wrapCampo = (clave, claveIncorrecto = clave) => wrapFaltante(clave) || wrapIncorrecto(claveIncorrecto);
    const etiquetasIncorrectas = Object.fromEntries(
        CAMPOS_ERROR_DATOS.map((c) => [c.id, c.label])
    );

    const bloqueSafControles = (
        <>
            <div className="flex flex-wrap items-center gap-x-6 gap-y-3 p-4 rounded-xl border theme-border theme-element">
                <label className="flex items-center gap-2 theme-text-main cursor-pointer">
                    <input
                        type="checkbox"
                        checked={data.aplica_saldo_favor}
                        onChange={(e) => {
                            const on = e.target.checked;
                            setData('aplica_saldo_favor', on);
                            if (!on) {
                                setData('saf_aplicaciones', []);
                                setData('saldo_a_favor', '');
                                setSafFifoItems([]);
                            } else {
                                const disponible = Number(safCuenta?.disponible || 0);
                                const sugerido = disponible > 0 ? disponible : '';
                                setData('saldo_a_favor', sugerido);
                                if (sugerido) aplicarFifoSaf(sugerido);
                            }
                        }}
                    />
                    <span className="text-sm font-bold">Saldo a favor</span>
                    {safCuenta && (
                        <span className="text-xs font-semibold" style={COLOR_EXITO}>
                            Disp. {formatearMoneda(safCuenta.disponible)}
                        </span>
                    )}
                    {cargandoSaf && <span className="text-xs theme-text-muted">Consultando…</span>}
                    <span className="text-[10px] theme-text-muted font-bold">
                        Solo saldos del mismo almacén/área. Un saldo generado aquí aplica a partir del siguiente pedido.
                    </span>
                </label>
            </div>
            {data.aplica_saldo_favor && (
                <div className="space-y-2">
                    <label className={SECCION}>Monto a aplicar (FIFO: vence primero)</label>
                    <InputMoneda
                        value={data.saldo_a_favor}
                        onChange={(v) => {
                            setData('saldo_a_favor', v);
                            aplicarFifoSaf(v);
                        }}
                        className="w-full max-w-xs py-3"
                    />
                    <p className="text-xs theme-text-muted m-0">
                        El sistema reparte automáticamente el saldo más antiguo primero. No se elige crédito a crédito.
                    </p>
                    {safFifoItems.length > 0 && (
                        <div className="space-y-1 border theme-border rounded-lg p-2">
                            {safFifoItems.map((i) => {
                                const parcial = Number(i.monto) + 0.001 < Number(i.disponible);
                                return (
                                    <div key={i.saf_credito_id} className="flex justify-between gap-2 text-xs">
                                        <span className="font-bold">{i.folio} · vence {i.fecha_vencimiento}</span>
                                        <span>
                                            {formatearMoneda(i.monto)}
                                            {parcial && (
                                                <span className="ml-2 text-amber-600 font-semibold">
                                                    (se sugiere usar completo: {formatearMoneda(i.disponible)})
                                                </span>
                                            )}
                                        </span>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                    {(safCuenta?.creditos_usables || []).length === 0 && (
                        <div className="text-xs theme-text-muted">
                            {data.cliente_id
                                ? 'El cliente no tiene saldo disponible en el libro.'
                                : 'Seleccione un cliente para consultar su saldo.'}
                        </div>
                    )}
                    <div className="text-sm font-bold" style={COLOR_EXITO}>Total saldo: {formatearMoneda(saldoFavorCalculado)}</div>
                </div>
            )}
        </>
    );

    const cerrarOverlayBorrador = (e) => {
        if (e.target !== e.currentTarget) return;
        if (modalAnidadoAbierto) return;
        if (Date.now() < ignoreOverlayCloseUntil.current) return;
        onClose();
    };

    const modal = abierto ? createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} data-gelia-modal="1" onClick={cerrarOverlayBorrador}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-4xl w-full flex flex-col text-left ${data.es_resguardo ? 'ring-2 ring-blue-500/50' : ''}`}
                style={{ maxHeight: 'calc(100dvh - 2rem)', ...(data.es_resguardo ? { backgroundColor: 'color-mix(in srgb, #3B82F6 6%, var(--color-surface))' } : {}) }}
                onClick={(e) => e.stopPropagation()}
                onPaste={handlePaste}
            >
                <GeliaLoader isVisible={processing} message="Guardando pedido_" />
                <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <div className="min-w-0">
                        <h2 className="text-xl md:text-2xl font-black italic theme-text-main uppercase tracking-tighter m-0">
                            {modoEdicion ? 'Editar pedido' : 'Nuevo pedido'}
                            {data.es_resguardo ? ' · Resguardo' : ''}
                        </h2>
                        {(estadoAuto.local || estadoAuto.bd || pedidoBdId) && (
                            <div className="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-[10px] font-bold uppercase tracking-widest theme-text-muted">
                                {estadoAuto.local && (
                                    <span className="inline-flex items-center gap-1">
                                        <HardDrive className="w-3 h-3" /> {estadoAuto.local}
                                    </span>
                                )}
                                {(estadoAuto.bd || pedidoBdId) && (
                                    <span className="inline-flex items-center gap-1">
                                        <Cloud className="w-3 h-3" /> {estadoAuto.bd || `Servidor · #${pedidoBdId}`}
                                    </span>
                                )}
                            </div>
                        )}
                    </div>
                    <button type="button" onClick={onClose} className="p-2 rounded-full theme-text-muted hover:theme-text-main outline-none shrink-0" aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="gelia-modal-body p-5 md:p-8 space-y-8" ref={cuerpoFormRef}>
                    {avisoForm && (
                        <div
                            className="p-4 rounded-xl border flex items-start gap-3"
                            style={{
                                borderColor: avisoForm.tipo === 'success' ? 'color-mix(in srgb, var(--color-exito) 45%, transparent)' : 'color-mix(in srgb, var(--color-peligro) 50%, transparent)',
                                backgroundColor: avisoForm.tipo === 'success' ? 'color-mix(in srgb, var(--color-exito) 10%, transparent)' : 'color-mix(in srgb, var(--color-peligro) 10%, transparent)',
                            }}
                        >
                            <AlertTriangle className="w-5 h-5 shrink-0 mt-0.5" style={{ color: avisoForm.tipo === 'success' ? 'var(--color-exito)' : 'var(--color-peligro)' }} />
                            <div className="min-w-0">
                                <p className="text-sm font-black m-0" style={{ color: avisoForm.tipo === 'success' ? 'var(--color-exito)' : 'var(--color-peligro)' }}>
                                    {avisoForm.tipo === 'success' ? 'Listo' : 'Atención'}
                                </p>
                                <p className="text-xs font-bold theme-text-main mt-1 m-0">{avisoForm.mensaje}</p>
                            </div>
                        </div>
                    )}
                    {intentoEnviar && !enviarPedidoListo && (
                        <div
                            className="p-4 rounded-xl border flex items-start gap-3"
                            style={{
                                borderColor: 'color-mix(in srgb, var(--color-peligro) 50%, transparent)',
                                backgroundColor: 'color-mix(in srgb, var(--color-peligro) 10%, transparent)',
                            }}
                        >
                            <AlertTriangle className="w-5 h-5 shrink-0 mt-0.5" style={{ color: 'var(--color-peligro)' }} />
                            <div className="min-w-0">
                                <p className="text-sm font-black m-0" style={{ color: 'var(--color-peligro)' }}>Hay campos faltantes</p>
                                {(validacionEnvio.faltantes || []).length > 0 && (
                                    <p className="text-xs font-bold theme-text-main mt-1 m-0">
                                        {validacionEnvio.faltantes.join(', ')}.
                                    </p>
                                )}
                            </div>
                        </div>
                    )}
                    {camposIncorrectos.length > 0 && (
                        <div className="p-4 rounded-xl border border-orange-500/50 bg-orange-500/10 flex items-start gap-3">
                            <AlertTriangle className="w-5 h-5 text-orange-600 shrink-0 mt-0.5" />
                            <div className="min-w-0">
                                <p className="text-sm font-black text-orange-700 m-0">Datos a corregir</p>
                                <p className="text-xs font-bold theme-text-main mt-1 m-0">
                                    {camposIncorrectos.map((k) => etiquetasIncorrectas[k] || k).join(', ')}
                                </p>
                                {pedido?.detalle_error_datos && (
                                    <p className="text-xs font-bold theme-text-muted mt-2 m-0">{pedido.detalle_error_datos}</p>
                                )}
                                {(esCampoIncorrecto('numero_rastreo') || esCampoIncorrecto('guia_pdf')) && (
                                    <p className="text-[10px] font-bold text-orange-600 mt-2 m-0">
                                        La guía fue invalidada; al reenviar el pedido se solicitará una nueva guía.
                                    </p>
                                )}
                            </div>
                        </div>
                    )}

                    {/* 1. Cliente */}
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>{nSec.cliente}. Cliente</p>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="relative" data-campo="cliente">
                                <div className={wrapCampo('cliente')}>
                                <label className={SECCION}>Número de cliente *</label>
                                <div className="theme-field-with-icon">
                                    <Search className="theme-field-icon w-4 h-4" />
                                    <input type="text" value={data.numero_cliente} disabled={logisticaBloqueada} onChange={(e) => manejarBusquedaCliente(e.target.value)} placeholder="Buscar cliente..." className={`${THEME_INPUT} w-full py-3 ${logisticaBloqueada ? 'opacity-50' : ''}`} />
                                </div>
                                {infoCliente && <p className="text-xs font-bold mt-2 theme-text-main">{infoCliente.nombre}</p>}
                                {mostrarDropdown && (
                                    <div className="absolute z-50 mt-1 w-full theme-surface border theme-border rounded-xl shadow-xl max-h-48 overflow-y-auto p-2">
                                        {buscandoCliente ? <p className="p-3 text-xs theme-text-muted font-bold">Buscando...</p> : listaClientes.map((c) => (
                                            <button key={c.id} type="button" onClick={() => seleccionarCliente(c)} className="w-full text-left p-3 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 text-xs font-bold uppercase theme-text-main">
                                                {c.numero_cliente} — {c.nombre}
                                            </button>
                                        ))}
                                    </div>
                                )}
                                </div>
                            </div>
                            <div>
                                <label className={SECCION}>Fecha</label>
                                <input type="date" value={data.fecha} onChange={(e) => setData('fecha', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                            </div>
                        </div>
                    </section>

                    {/* 2. Tipo de entrega */}
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>{nSec.tipo}. Tipo de entrega</p>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className={wrapCampo('origen')} data-campo="origen">
                                <label className={SECCION}>Tipo de pedido *</label>
                                <select
                                    value={data.origen_id}
                                    disabled={logisticaBloqueada}
                                    onChange={(e) => cambiarTipoPedido(e.target.value)}
                                    className={`${THEME_SELECT} w-full py-3 ${logisticaBloqueada ? 'opacity-50' : ''}`}
                                >
                                    <option value="">Seleccionar...</option>
                                    {(catalogos.origenes || []).map((o) => (
                                        <option key={o.id} value={o.id}>{o.nombre}</option>
                                    ))}
                                </select>
                                {origenSeleccionado ? (
                                    <p className="text-[10px] font-bold theme-text-muted mt-1.5 m-0">
                                        {requiereLogistica
                                            ? 'Envío: solicite el pesaje a CEDIS; al responder y cerrar la consulta podrá capturar el monto, cotizar y pagar.'
                                            : 'Tienda/mostrador: solicite consulta de mercancía a CEDIS; al cerrarla capture el monto y el pago (sin cajas ni guía).'}
                                    </p>
                                ) : (
                                    <p className="text-[10px] font-bold text-amber-600 mt-1.5 m-0">
                                        Seleccione Tipo de pedido (Tienda o Envío) para mostrar el resto del formulario.
                                    </p>
                                )}
                            </div>
                            {tieneTipo && (
                            <>
                            <div className={wrapCampo('almacen')} data-campo="almacen">
                                <label className={SECCION}>Almacén de salida</label>
                                <select value={data.almacen_id} disabled={logisticaBloqueada} onChange={(e) => setData('almacen_id', e.target.value)} className={`${THEME_SELECT} w-full py-3 ${logisticaBloqueada ? 'opacity-50' : ''}`}>
                                    <option value="">Seleccionar...</option>
                                    {(catalogos.almacenes || []).map((a) => (
                                        <option key={a.id} value={a.id}>{etiquetaAlmacen(a)}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="md:col-span-2">
                                <label className="flex items-center gap-3 theme-text-main p-3 rounded-xl border theme-border w-full cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={Boolean(data.es_resguardo)}
                                        onChange={(e) => {
                                            const on = e.target.checked;
                                            if (on) {
                                                setData({
                                                    ...data,
                                                    es_resguardo: true,
                                                    modo_resguardo: data.modo_resguardo || 'abierto',
                                                    ...(data.modo_resguardo !== 'complementario' ? {
                                                        costo_envio: '',
                                                        numero_cajas: '',
                                                        peso_real_kg: '',
                                                        peso_cobrado_guia_kg: '',
                                                    } : {}),
                                                });
                                            } else {
                                                setData({
                                                    ...data,
                                                    es_resguardo: false,
                                                    modo_resguardo: 'abierto',
                                                    pedido_principal_id: '',
                                                });
                                                setPrincipalSeleccionado(null);
                                            }
                                        }}
                                        className="w-4 h-4"
                                    />
                                    <span className="text-sm font-bold">¿Dejar en resguardo?</span>
                                </label>
                            </div>
                            {data.es_resguardo && (
                                <div className="md:col-span-2 space-y-3">
                                    <label className={SECCION}>Tipo de resguardo</label>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <button
                                            type="button"
                                            onClick={() => setData({
                                                ...data,
                                                modo_resguardo: 'abierto',
                                                es_resguardo: true,
                                                costo_envio: '',
                                                numero_cajas: '',
                                                peso_real_kg: '',
                                                peso_cobrado_guia_kg: '',
                                            })}
                                            className={`text-left p-4 rounded-xl border outline-none transition-colors ${data.modo_resguardo === 'abierto' ? 'border-[var(--color-primario)] bg-[var(--color-primario)]/10' : 'theme-border theme-element'}`}
                                        >
                                            <p className="text-sm font-black uppercase theme-text-main m-0">Resguardo abierto</p>
                                            <p className="text-[11px] theme-text-muted font-bold mt-1 m-0">
                                                Cliente indeciso: peso, cajas y costo bloqueados hasta liberar.
                                            </p>
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setData({
                                                ...data,
                                                modo_resguardo: 'complementario',
                                                es_resguardo: true,
                                                costo_envio: '',
                                                numero_cajas: '',
                                                peso_real_kg: '',
                                                peso_cobrado_guia_kg: '',
                                            })}
                                            className={`text-left p-4 rounded-xl border outline-none transition-colors ${data.modo_resguardo === 'complementario' ? 'border-[var(--color-primario)] bg-[var(--color-primario)]/10' : 'theme-border theme-element'}`}
                                        >
                                            <p className="text-sm font-black uppercase theme-text-main m-0">Resguardo complementario</p>
                                            <p className="text-[11px] theme-text-muted font-bold mt-1 m-0">
                                                Hereda logística del principal; solo remisión y pago de esta pieza.
                                            </p>
                                        </button>
                                    </div>
                                </div>
                            )}
                            {data.es_resguardo && (
                                <div className="md:col-span-2 space-y-3">
                                    {esResguardoAbierto && (
                                        <div className="flex items-start gap-2 p-3 rounded-xl border border-blue-500/40 bg-blue-500/10">
                                            <AlertTriangle className="w-4 h-4 text-blue-600 shrink-0 mt-0.5" />
                                            <p className="text-xs font-bold text-blue-700 dark:text-blue-400 m-0">
                                                Envío diferido: dirección y costo se capturan al Completar envío. Capture ya el folio Wizerp y la paquetería junto al archivo del pedido.
                                            </p>
                                        </div>
                                    )}
                                    {esResguardoComplementario && (
                                        <div className="space-y-3">
                                            <div className="flex items-start gap-2 p-3 rounded-xl border border-teal-500/40 bg-teal-500/10">
                                                <AlertTriangle className="w-4 h-4 text-teal-600 shrink-0 mt-0.5" />
                                                <p className="text-xs font-bold text-teal-700 dark:text-teal-400 m-0">
                                                    {principalSeleccionado
                                                        ? `Se agregará al folio ${principalSeleccionado.folio} como complemento. Logística heredada del padre; el peso del paquete se captura al completar el envío del principal.`
                                                        : 'Seleccione el pedido principal (mismo cliente). Se listan todos los pedidos del cliente, no solo resguardos. Se reutilizará su logística; CEDIS verá una sola card.'}
                                                </p>
                                            </div>
                                            {!data.cliente_id && (
                                                <p className="text-xs font-bold text-amber-600 m-0">Seleccione primero el cliente para buscar el pedido principal.</p>
                                            )}
                                            {data.cliente_id && (
                                                <div className="relative space-y-2">
                                                    <label className={SECCION}>Pedido principal *</label>
                                                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                                                        <select
                                                            value={filtroFasePrincipal}
                                                            onChange={(e) => {
                                                                const v = e.target.value;
                                                                setFiltroFasePrincipal(v);
                                                                buscarPrincipales(qPrincipal, { fase_ciclo: v });
                                                            }}
                                                            className={`${THEME_SELECT} w-full py-2 text-xs`}
                                                        >
                                                            <option value="">Todos los estatus</option>
                                                            {(catalogos.estatus || [])
                                                                .filter((e, idx, arr) => arr.findIndex((x) => x.fase_ciclo === e.fase_ciclo) === idx)
                                                                .map((e) => (
                                                                    <option key={e.fase_ciclo || e.id} value={e.fase_ciclo}>
                                                                        {LABELS_ESTATUS_POR_FASE[e.fase_ciclo] || e.nombre_visual || e.fase_ciclo}
                                                                    </option>
                                                                ))}
                                                        </select>
                                                        <input
                                                            type="date"
                                                            value={filtroFechaDesdePrincipal}
                                                            onChange={(e) => {
                                                                const v = e.target.value;
                                                                setFiltroFechaDesdePrincipal(v);
                                                                buscarPrincipales(qPrincipal, { fecha_desde: v });
                                                            }}
                                                            className={`${THEME_INPUT} w-full py-2 text-xs`}
                                                            title="Fecha desde"
                                                        />
                                                        <input
                                                            type="date"
                                                            value={filtroFechaHastaPrincipal}
                                                            onChange={(e) => {
                                                                const v = e.target.value;
                                                                setFiltroFechaHastaPrincipal(v);
                                                                buscarPrincipales(qPrincipal, { fecha_hasta: v });
                                                            }}
                                                            className={`${THEME_INPUT} w-full py-2 text-xs`}
                                                            title="Fecha hasta"
                                                        />
                                                    </div>
                                                    <div className="theme-field-with-icon">
                                                        <Search className="theme-field-icon w-4 h-4" />
                                                        <input
                                                            type="text"
                                                            value={qPrincipal}
                                                            onChange={(e) => {
                                                                const v = e.target.value;
                                                                setQPrincipal(v);
                                                                if (temporizadorPrincipal.current) clearTimeout(temporizadorPrincipal.current);
                                                                temporizadorPrincipal.current = setTimeout(() => buscarPrincipales(v), 350);
                                                            }}
                                                            onFocus={() => buscarPrincipales(qPrincipal)}
                                                            placeholder="Buscar folio interno o folio de pedido..."
                                                            className={`${THEME_INPUT} w-full py-3`}
                                                        />
                                                    </div>
                                                    {principalSeleccionado && (
                                                        <p className="text-xs font-bold theme-text-main mt-1 m-0">
                                                            Principal: {etiquetaCandidatoPrincipal(principalSeleccionado)}
                                                        </p>
                                                    )}
                                                    {(buscandoPrincipal || candidatosPrincipal.length > 0) && (
                                                        <div className="absolute z-50 mt-1 w-full theme-surface border theme-border rounded-xl shadow-xl max-h-56 overflow-y-auto p-2 top-full">
                                                            {buscandoPrincipal ? (
                                                                <p className="p-3 text-xs theme-text-muted font-bold">Buscando...</p>
                                                            ) : candidatosPrincipal.map((p) => (
                                                                <button
                                                                    key={p.id}
                                                                    type="button"
                                                                    onClick={() => {
                                                                        setPrincipalSeleccionado(p);
                                                                        setData({
                                                                            ...data,
                                                                            ...aplicarLogisticaDesdePrincipal(p),
                                                                        });
                                                                        setCandidatosPrincipal([]);
                                                                        setQPrincipal(p.folio || '');
                                                                    }}
                                                                    className="w-full text-left p-3 rounded-lg hover:bg-black/5 dark:hover:bg-white/5 text-xs font-bold theme-text-main normal-case"
                                                                >
                                                                    {etiquetaCandidatoPrincipal(p)}
                                                                    {p.es_resguardo ? (
                                                                        <span className="block text-[10px] theme-text-muted font-black uppercase mt-1">Resguardo</span>
                                                                    ) : null}
                                                                </button>
                                                            ))}
                                                        </div>
                                                    )}
                                                    {errors.pedido_principal_id && (
                                                        <p className="text-[10px] text-red-500 font-bold mt-1 m-0">{errors.pedido_principal_id}</p>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            )}
                            </>
                            )}
                        </div>
                    </section>

                    {mostrarPdfPedido && (
                    <section className={`${SECCION_WRAP} ${wrapCampo('pdf_pedido')}`} data-campo="pdf_pedido" onPaste={pegarPdfPedido}>
                        <p className={SECCION}>
                            {nSec.pdf}. {requiereLogistica ? 'Folio, paquetería y archivo del pedido' : 'Folio y archivo del pedido'}
                        </p>
                        <div className="space-y-4">
                        {!requiereLogistica && (
                            <p className="text-[10px] font-bold theme-text-muted m-0 -mt-1">
                                Capture el folio Wizerp y adjunte el PDF o foto. El comprobante de pago se carga en la sección de pago.
                            </p>
                        )}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className={wrapCampo('folio_remision')} data-campo="folio_remision">
                                <label className={SECCION}>Folio de pedido *</label>
                                <input
                                    type="text"
                                    value={data.folio_remision}
                                    onChange={(e) => setData('folio_remision', e.target.value)}
                                    placeholder="Folio generado por Wizerp..."
                                    className={`${THEME_INPUT} w-full py-3`}
                                />
                            </div>
                            {requiereLogistica && (
                                <div className={wrapCampo('paqueteria')} data-campo="paqueteria">
                                    <label className={SECCION}>Paquetería{guiaCliente ? ' (opcional)' : ' *'}</label>
                                    <select
                                        value={data.catalogo_paqueteria_id}
                                        disabled={logisticaBloqueada}
                                        onChange={(e) => manejarPaqueteria(e.target.value)}
                                        className={`${THEME_SELECT} w-full py-3 ${logisticaBloqueada ? 'opacity-50' : ''}`}
                                    >
                                        <option value="">Seleccionar...</option>
                                        {paqueteriasComerciales.length > 0 && (
                                            <optgroup label="Comercial (FedEx, DHL…)">
                                                {paqueteriasComerciales.map((p) => (
                                                    <option key={p.id} value={p.id}>{p.nombre}</option>
                                                ))}
                                            </optgroup>
                                        )}
                                        {paqueteriasLocales.length > 0 && (
                                            <optgroup label="Local / Regional (municipio)">
                                                {paqueteriasLocales.map((p) => (
                                                    <option key={p.id} value={p.id}>
                                                        {p.nombre}{p.permite_costo_diferido ? ' · costo diferido' : ''}
                                                    </option>
                                                ))}
                                            </optgroup>
                                        )}
                                    </select>
                                </div>
                            )}
                        </div>
                        <div className="flex flex-wrap items-center gap-3">
                            <label className="flex items-center gap-2 px-4 py-3 border theme-border border-dashed rounded-xl cursor-pointer w-fit theme-element theme-text-main">
                                <FileText className="w-4 h-4 theme-text-muted" />
                                <span className="text-xs font-black uppercase">
                                    {tienePdfPedido ? 'Reemplazar archivo' : 'Adjuntar PDF o foto'}
                                </span>
                                <input type="file" accept="application/pdf,.pdf,image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" className="hidden" onChange={subirPdfPedido} disabled={procesandoPesaje} />
                            </label>
                            {pdfPedidoDoc?.url && (
                                <div className="flex items-center gap-2 min-w-0">
                                    <MiniaturaDocumento
                                        key={pdfPedidoDoc.id || pdfPedidoDoc.url}
                                        documento={pdfPedidoDoc}
                                        onVer={() => abrirVistaPrevia(pdfPedidoDoc)}
                                    />
                                    {pdfPedidoDoc.nombre_original && (
                                        <span className="text-[10px] font-bold theme-text-muted truncate max-w-[10rem]" title={pdfPedidoDoc.nombre_original}>
                                            {pdfPedidoDoc.nombre_original}
                                        </span>
                                    )}
                                </div>
                            )}
                            {tienePdfPedido && !pdfPedidoDoc?.url && (
                                <span className="text-xs font-bold" style={COLOR_EXITO}>Cargado y listo</span>
                            )}
                            {procesandoPesaje && !avisoPdf && (
                                <span className="text-xs font-bold theme-text-muted">Subiendo…</span>
                            )}
                        </div>
                        <p className="text-[10px] theme-text-muted font-bold m-0 mt-2">
                            Puede pegar una captura (Ctrl+V). Clic en la miniatura abre el visor.
                        </p>
                        {avisoPdf && (
                            <p
                                className="text-xs font-bold m-0"
                                style={{ color: avisoPdf.tipo === 'success' ? 'var(--color-exito)' : 'var(--color-peligro)' }}
                            >
                                {avisoPdf.mensaje}
                            </p>
                        )}
                        </div>
                    </section>
                    )}

                    {mostrarPesaje && (
                    <>
                    <section className={SECCION_WRAP} data-campo="pesaje">
                        <p className={SECCION}>{nSec.solPesaje}. {labelConsulta}</p>
                        {avisoPesaje && (
                            <p
                                className="text-xs font-bold m-0 mb-3"
                                style={{ color: avisoPesaje.tipo === 'success' ? 'var(--color-exito)' : 'var(--color-peligro)' }}
                            >
                                {avisoPesaje.mensaje}
                            </p>
                        )}
                        {pedido?.estatus_envio && LABELS_ESTATUS_ENVIO[pedido.estatus_envio]
                            && !(pedido.estatus_envio === 'pendiente_pesaje' && fasePedido === 'PESAJE_PENDIENTE')
                            && !(pedido.estatus_envio === 'pesaje_listo' && fasePedido === 'PESAJE_RESPONDIDO') && (
                            <p className="text-xs font-bold theme-text-muted mb-3 m-0">
                                Estado envío: {LABELS_ESTATUS_ENVIO[pedido.estatus_envio]}
                            </p>
                        )}
                        {pendientePesaje && (
                            <AvisoOperativoPedido label="Esperando CEDIS" tono="warning" icon={Scale} className="mb-4">
                                {labelConsulta} enviada. Cuando CEDIS responda verá aquí el detalle
                                {esConsultaMercancia ? ' de piezas y estado físico.' : ' de peso, medidas y estado físico.'}
                            </AvisoOperativoPedido>
                        )}
                        {tienePesajeRespondido && !pendientePesaje && puedeContinuarPedido && (
                            <AvisoOperativoPedido label="Respuesta lista" tono="success" icon={Scale} className="mb-4">
                                CEDIS ya respondió. Pulse «Continuar pedido» para desbloquear el resto del formulario.
                            </AvisoOperativoPedido>
                        )}
                        {tienePesajeRespondido && !pendientePesaje && !puedeContinuarPedido && !consultaCerrada && (
                            <AvisoOperativoPedido label="Confirme con el cliente" tono="info" icon={CheckCircle2} className="mb-4">
                                Revise las piezas con el cliente y cierre la consulta para capturar el monto y el pago.
                                Si hay cambios, use «Actualizar consulta» (anexo/retiro).
                            </AvisoOperativoPedido>
                        )}
                        {consultaCerrada && (
                            <AvisoOperativoPedido label="Consulta cerrada" tono="success" icon={CheckCircle2} className="mb-4">
                                Ya puede capturar el total de mercancía y el pago.
                                {requiereLogistica ? ' Complete también la cotización de envío.' : ''}
                            </AvisoOperativoPedido>
                        )}
                        {!tienePesajeRespondido && !pendientePesaje && (
                            <AvisoOperativoPedido label="Paso requerido" tono="info" icon={Scale} className="mb-4">
                                Adjunta el PDF o foto del pedido y solicite la {labelConsulta.toLowerCase()}. El monto se captura después de cerrarla.
                            </AvisoOperativoPedido>
                        )}
                        <div className="space-y-4">
                            {!tienePesajeRespondido && !pendientePesaje && (
                                <button
                                    type="button"
                                    onClick={solicitarPesaje}
                                    disabled={procesandoPesaje || processing || !data.cliente_id || !tienePdfPedido}
                                    className={`${BTN_PRIMARY} flex items-center gap-2 outline-none`}
                                >
                                    <Scale className="w-4 h-4" /> Solicitar {labelConsulta.toLowerCase()} a CEDIS
                                </button>
                            )}
                            {!data.cliente_id && !tienePesajeRespondido && !pendientePesaje && (
                                <p className="text-[10px] font-bold text-amber-600 m-0">Seleccione el cliente para poder solicitar la consulta.</p>
                            )}
                            {data.cliente_id && !tienePdfPedido && !tienePesajeRespondido && !pendientePesaje && (
                                <p className="text-[10px] font-bold text-amber-600 m-0">Adjunte el PDF o foto del pedido para solicitar la consulta.</p>
                            )}
                            {puedeCerrarConsulta && (
                                <button
                                    type="button"
                                    onClick={cerrarConsulta}
                                    disabled={procesandoPesaje}
                                    className={`${BTN_PRIMARY} flex items-center gap-2 outline-none`}
                                >
                                    <CheckCircle2 className="w-4 h-4" /> Cerrar consulta / Confirmar mercancía con cliente
                                </button>
                            )}
                            {consultaCerrada && !pendientePesaje && !pedido?.empacado_at && (
                                <button
                                    type="button"
                                    onClick={reabrirConsulta}
                                    disabled={procesandoPesaje}
                                    className={`${BTN_SECONDARY} flex items-center gap-2 outline-none`}
                                >
                                    Reabrir consulta
                                </button>
                            )}
                            {tienePesajeRespondido && !pendientePesaje && !pedido?.empacado_at && !puedeContinuarPedido && (
                                <div className="space-y-3 p-3 rounded-xl border theme-border">
                                    <div>
                                        <label className={SECCION}>Piezas adicionales (PDF o fotos)</label>
                                        <div className="flex flex-wrap items-center gap-3">
                                            <label className="flex items-center gap-2 px-4 py-3 border theme-border border-dashed rounded-xl cursor-pointer w-fit theme-element theme-text-main">
                                                <ImagePlus className="w-4 h-4 theme-text-muted" />
                                                <span className="text-xs font-black uppercase">
                                                    {tieneAnexoPiezas ? 'Agregar más' : 'Adjuntar anexos'}
                                                </span>
                                                <input type="file" multiple accept="application/pdf,.pdf,image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" className="hidden" onChange={subirAnexoPiezas} disabled={procesandoPesaje} />
                                            </label>
                                        </div>
                                        {anexosPiezasDocs.length > 0 && (
                                            <div className="flex flex-wrap gap-2 mt-3">
                                                {anexosPiezasDocs.map((doc, idx) => (
                                                    <MiniaturaDocumento
                                                        key={doc.id || doc.url || idx}
                                                        documento={doc}
                                                        onVer={() => abrirVistaPrevia(anexosPiezasDocs, idx)}
                                                    />
                                                ))}
                                            </div>
                                        )}
                                        <p className="text-[10px] theme-text-muted font-bold m-0 mt-2">
                                            Puede pegar una o varias capturas (Ctrl+V). Clic en miniatura abre el visor; en fotos también se agrandan al pasar el cursor.
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-end gap-3">
                                        <div className="min-w-[200px] flex-1">
                                            <label className={SECCION}>Actualizar consulta</label>
                                            <select value={motivoRepesaje} onChange={(e) => setMotivoRepesaje(e.target.value)} className={`${THEME_SELECT} w-full py-3`}>
                                                <option value="">Motivo…</option>
                                                {Object.entries(LABELS_MOTIVO_REPESAJE).map(([k, label]) => (
                                                    <option key={k} value={k}>{label}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <button type="button" onClick={solicitarRepesaje} disabled={procesandoPesaje || !motivoRepesaje} className={`${BTN_SECONDARY} flex items-center gap-2 outline-none`}>
                                            <Scale className="w-4 h-4" /> Actualizar consulta
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </section>

                    {tienePesajeRespondido && (
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>{nSec.resp}. {esConsultaMercancia ? 'Respuesta de mercancía' : 'Respuesta de cajas y pesos'}</p>
                        {(pedido?.pesaje_respondido_por?.name || pedido?.pesajeRespondidoPor?.name) && (
                            <p className="text-xs font-bold theme-text-main m-0 mb-3">
                                Respondió: {pedido.pesaje_respondido_por?.name || pedido.pesajeRespondidoPor?.name}
                                {pedido.pesaje_respondido_at
                                    ? ` · ${formatearFechaNegocio(pedido.pesaje_respondido_at)}`
                                    : ''}
                            </p>
                        )}
                                <SeccionRevisionFisicaPedido
                                    pedido={pedido}
                                    onVerDoc={abrirVistaPrevia}
                                    titulo="Revisión física CEDIS"
                                    puedeAtender={Boolean(pedido?.puede_mutar)}
                                    puedeCancelar={Boolean(pedido?.puede_cancelar)}
                                />
                                {!esConsultaMercancia && (
                                <>
                                {cajasPesaje.length > 0 ? (
                                    <div className="space-y-3" data-campo="tipo_caja">
                                        <p className="text-[9px] font-black uppercase theme-text-muted m-0">Envíos (pesaje)</p>
                                        {detalleCajasUi ? (
                                            [...cajasPesaje].sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0)).map((c, idx) => {
                                                const costos = (data.cajas_costos || []).find((x) => x.uuid_operativo === c.uuid_operativo) || {
                                                    uuid_operativo: c.uuid_operativo,
                                                    costo_envio: c.costo_envio ?? '',
                                                    costo_seguro: c.costo_seguro ?? '',
                                                    costo_adicional: c.costo_adicional ?? '',
                                                    concepto_adicional: c.concepto_adicional ?? '',
                                                };
                                                const incompleto = costos.costo_envio === '' || costos.costo_envio == null;
                                                return (
                                                    <TarjetaEnvioPedido
                                                        key={c.uuid_operativo || c.id || idx}
                                                        caja={c}
                                                        indice={idx}
                                                        abiertoInicial={cajasPesaje.length === 1}
                                                        modo={puedeEditarCostosCaja ? 'costos' : 'lectura'}
                                                        costos={costos}
                                                        incompleto={incompleto}
                                                        bloqueado={!puedeEditarCostosCaja}
                                                        onCostosChange={(next) => {
                                                            const prev = data.cajas_costos || [];
                                                            const sin = prev.filter((x) => x.uuid_operativo !== next.uuid_operativo);
                                                            const actualizados = [...sin, next];
                                                            const completo = actualizados.length > 0
                                                                && cajasPesaje.every((caja) => {
                                                                    const row = actualizados.find((x) => x.uuid_operativo === caja.uuid_operativo);
                                                                    return row && row.costo_envio !== '' && row.costo_envio != null;
                                                                });
                                                            const suma = actualizados.reduce((acc, row) => (
                                                                acc + (Number(row.costo_envio) || 0) + (Number(row.costo_adicional) || 0)
                                                            ), 0);
                                                            const sumaSeg = actualizados.reduce((acc, row) => acc + (Number(row.costo_seguro) || 0), 0);
                                                            setData({
                                                                ...data,
                                                                cajas_costos: actualizados,
                                                                ...(completo ? {
                                                                    costo_envio: String(Math.round(suma * 100) / 100),
                                                                    costo_seguro: String(Math.round(sumaSeg * 100) / 100),
                                                                    aplica_seguro: sumaSeg > 0 || data.aplica_seguro,
                                                                } : {}),
                                                            });
                                                        }}
                                                        documentos={(pedido?.documentos || []).filter((d) => (
                                                            d.pedido_bma_caja_id === c.id
                                                            || (d.relacion_tipo === 'envio_caja' && Number(d.relacion_id) === Number(c.id))
                                                        ))}
                                                        onVerDoc={abrirVistaPrevia}
                                                    />
                                                );
                                            })
                                        ) : (
                                            [...cajasPesaje].sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0)).map((c, idx) => (
                                                <div key={c.id || idx} className="p-4 rounded-xl border theme-border theme-element space-y-2">
                                                    <p className="text-sm font-black theme-text-main m-0">{etiquetaEnvio(idx, c)}</p>
                                                    <div className="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
                                                        <p className="m-0 theme-text-muted font-bold">Tipo: <span className="theme-text-main">{c.tipo_caja?.nombre || '—'}</span></p>
                                                        <p className="m-0 theme-text-muted font-bold">Largo: <span className="theme-text-main">{c.largo != null ? `${c.largo} cm` : '—'}</span></p>
                                                        <p className="m-0 theme-text-muted font-bold">Ancho: <span className="theme-text-main">{c.ancho != null ? `${c.ancho} cm` : '—'}</span></p>
                                                        <p className="m-0 theme-text-muted font-bold">Alto: <span className="theme-text-main">{c.alto != null ? `${c.alto} cm` : '—'}</span></p>
                                                        <p className="m-0 theme-text-muted font-bold">Real: <span className="theme-text-main">{c.peso_real_kg != null ? `${c.peso_real_kg} kg` : '—'}</span></p>
                                                        <p className="m-0 theme-text-muted font-bold">Vol.: <span className="theme-text-main">{c.peso_volumetrico_kg != null ? `${c.peso_volumetrico_kg} kg` : '—'}</span></p>
                                                        <p className="m-0 theme-text-muted font-bold">Cobrado: <span className="theme-text-main">{c.peso_cobrado_kg != null ? `${c.peso_cobrado_kg} kg` : '—'}</span></p>
                                                    </div>
                                                </div>
                                            ))
                                        )}
                                    </div>
                                ) : null}
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className={wrapCampo('numero_envios')} data-campo="numero_envios">
                                        <label className={SECCION}>Núm. envíos</label>
                                        <input type="text" readOnly value={data.numero_cajas ?? cajasPesaje.length ?? '—'} className={`${THEME_INPUT} w-full py-3 opacity-60`} />
                                    </div>
                                    <div className={wrapCampo('peso_real')} data-campo="peso_real">
                                        <label className={SECCION}>Peso real total (kg)</label>
                                        <input type="text" readOnly value={data.peso_real_kg !== '' && data.peso_real_kg != null ? data.peso_real_kg : '—'} className={`${THEME_INPUT} w-full py-3 opacity-60`} />
                                    </div>
                                    <div>
                                        <label className={SECCION}>Peso volumétrico total (kg)</label>
                                        <input type="text" readOnly value={pesoVolumetrico !== '' && pesoVolumetrico != null ? pesoVolumetrico : '—'} className={`${THEME_INPUT} w-full py-3 opacity-60`} />
                                    </div>
                                    <div>
                                        <label className={SECCION}>Peso cobrado guía total (kg)</label>
                                        <input
                                            type="text"
                                            readOnly
                                            value={data.peso_cobrado_guia_kg !== '' && data.peso_cobrado_guia_kg != null ? data.peso_cobrado_guia_kg : '—'}
                                            className={`${THEME_INPUT} w-full py-3 opacity-60`}
                                            title="Suma del mayor entre peso real y volumétrico de cada envío"
                                        />
                                    </div>
                                </div>
                                </>
                                )}
                                {puedeContinuarPedido && (
                                    <div className="mt-2 p-4 rounded-xl border-2 border-orange-500/50 bg-orange-500/10 space-y-4">
                                        <p className="text-[10px] font-black uppercase tracking-widest text-orange-600 dark:text-orange-400 m-0">
                                            Siguiente paso
                                        </p>
                                        <p className="text-sm font-bold theme-text-main m-0 leading-snug pb-1">
                                            Continúe el pedido para capturar dirección, cotización y pago.
                                        </p>
                                        <button
                                            type="button"
                                            onClick={continuarPedido}
                                            disabled={procesandoPesaje || processing}
                                            className={`${BTN_PRIMARY} w-full sm:w-auto flex items-center justify-center gap-2 outline-none min-h-[48px] px-6 mt-1 text-sm font-black uppercase tracking-widest ring-2 ring-orange-400/60`}
                                            style={{ backgroundColor: '#EA580C' }}
                                        >
                                            <ArrowRight className="w-5 h-5" /> Continuar pedido
                                        </button>
                                    </div>
                                )}
                    </section>
                    )}
                    </>
                    )}

                    {mostrarMontoMercancia && (
                    <section className={SECCION_WRAP} data-campo="total_mercancia">
                        <p className={SECCION}>{nSec.monto}. Total de mercancía</p>
                        <AvisoOperativoPedido label="Pedido final" tono="info" icon={CheckCircle2} className="mb-4">
                            Capture el monto solo cuando la mercancía ya está confirmada con el cliente (consulta CEDIS cerrada).
                        </AvisoOperativoPedido>
                        <div className={wrapCampo('total_mercancia')}>
                            <label className={SECCION}>Total mercancía *</label>
                            <InputMoneda value={data.total_mercancia} onChange={(v) => setData('total_mercancia', v)} className="w-full py-3" />
                        </div>
                    </section>
                    )}

                    {mostrarLogisticaPostPesaje && esResguardoAbierto && (
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>{nSec.dir}. Envío (diferido)</p>
                        <AvisoOperativoPedido label="Resguardo abierto" tono="info" icon={AlertTriangle} className="mb-0">
                            Dirección y costo de envío se capturan después, con «Completar envío». Folio y paquetería ya se capturan con el archivo del pedido.
                        </AvisoOperativoPedido>
                    </section>
                    )}

                    {mostrarLogisticaPostPesaje && !esResguardoAbierto && (
                    <>
                    {/* Dirección de envío */}
                    <section className={SECCION_WRAP}>
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
                            <p className={`${THEME_LABEL} m-0`}>{nSec.dir}. Dirección de envío{guiaCliente ? ' (opcional)' : ''}</p>
                            {can('clientes.direcciones.generar_enlace') && infoCliente?.id && (
                                <button
                                    type="button"
                                    className={`${BTN_SECONDARY} inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-widest py-2 px-3`}
                                    onClick={() => setModalLinkDireccion(true)}
                                >
                                    <Link2 className="w-3.5 h-3.5" />
                                    Link de dirección
                                </button>
                            )}
                        </div>
                        {(msgDireccion || (sinDireccionPrincipal && !data.cliente_direccion_id)) && (
                            <div className="mb-4 p-4 rounded-xl border border-amber-500/40 bg-amber-500/10 flex items-start gap-3">
                                <AlertTriangle className="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
                                <div className="min-w-0 space-y-2">
                                    <p className="text-xs font-bold theme-text-main m-0">
                                        {msgDireccion
                                            || (direccionesCliente.length === 0
                                                ? 'Este cliente no tiene direcciones verificadas. Capture los datos abajo y pulse «Guardar como dirección principal».'
                                                : 'Este cliente no tiene dirección principal. Elija una del listado o guarde una nueva como principal.')}
                                    </p>
                                    {can('clientes.direcciones.generar_enlace') && infoCliente?.id && (
                                        <button
                                            type="button"
                                            className={`${BTN_SECONDARY} inline-flex items-center gap-2 text-xs`}
                                            onClick={() => setModalLinkDireccion(true)}
                                        >
                                            <Link2 className="w-3.5 h-3.5" />
                                            Generar link para registrar dirección
                                        </button>
                                    )}
                                </div>
                            </div>
                        )}
                        <div
                            className={`space-y-4 ${!cotizacionHabilitada ? 'opacity-60 pointer-events-none' : ''} ${
                                wrapFaltante('domicilio')
                                || wrapFaltante('codigo_postal')
                                || ((esCampoIncorrecto('domicilio') || esCampoIncorrecto('ciudad_estado') || esCampoIncorrecto('referencia') || esCampoIncorrecto('destinatario') || esCampoIncorrecto('telefono'))
                                    ? 'rounded-xl ring-2 ring-orange-500/40 bg-orange-500/5 p-3'
                                    : '')
                            }`}
                            data-campo="domicilio"
                        >
                            {puedeSeleccionar && !mostrarExcepcion && (
                                <div className="space-y-3">
                                    <label className={`${SECCION} m-0`}>Seleccionar dirección del catálogo</label>
                                    <select
                                        value={data.cliente_direccion_id}
                                        disabled={logisticaBloqueada || cargandoDireccion}
                                        onChange={(e) => {
                                            const id = e.target.value;
                                            const sel = direccionesCliente.find((d) => String(d.id) === String(id));
                                            aplicarDireccionSeleccionada(sel || null);
                                        }}
                                        className={`${THEME_SELECT} w-full py-3 ${logisticaBloqueada ? 'opacity-50' : ''}`}
                                    >
                                        <option value="">{cargandoDireccion ? 'Cargando…' : 'Seleccionar dirección de envío…'}</option>
                                        {direccionesCliente.map((d) => (
                                            <option key={d.id} value={d.id}>
                                                {labelOpcionDireccion(data.numero_cliente || infoCliente?.numero_cliente, d)}
                                                {d.es_principal ? ' · Principal' : ''}
                                            </option>
                                        ))}
                                    </select>
                                    {data.cliente_direccion_id && (
                                        <div className="space-y-2">
                                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                                                {codigoDireccionCliente(
                                                    data.numero_cliente || infoCliente?.numero_cliente,
                                                    direccionesCliente.find((d) => String(d.id) === String(data.cliente_direccion_id))?.numero_direccion,
                                                )}
                                                {direccionSucia ? ' · cambios sin guardar' : ''}
                                            </p>
                                            <CamposDireccionPedido
                                                valores={camposDireccion}
                                                onChange={onCambiarCamposDireccion}
                                                disabled={logisticaBloqueada}
                                                sucio={direccionSucia}
                                                guardando={guardandoDireccion}
                                                puedeEditar={can('clientes.direcciones.editar')}
                                                onGuardar={() => setConfirmarActualizarDir(true)}
                                                onDescartar={descartarCambiosDireccion}
                                            />
                                        </div>
                                    )}
                                </div>
                            )}

                            {(direccionesCliente.length === 0 || mostrarExcepcion) && (
                                <div className="rounded-xl border border-amber-500/30 bg-amber-500/5 p-4 space-y-3">
                                    <p className="text-xs font-bold theme-text-main m-0">
                                        {mostrarExcepcion
                                            ? 'Captura de excepción manual autorizada.'
                                            : 'Sin direcciones verificadas en el catálogo. Registre una dirección o use excepción autorizada.'}
                                    </p>
                                    <div className="flex flex-wrap gap-2">
                                        {can('clientes.direcciones.generar_enlace') && infoCliente?.id && (
                                            <button
                                                type="button"
                                                className={`${BTN_SECONDARY} inline-flex items-center gap-2 text-xs`}
                                                onClick={() => setModalLinkDireccion(true)}
                                            >
                                                <Link2 className="w-3.5 h-3.5" />
                                                Generar link de dirección
                                            </button>
                                        )}
                                        {puedeManual && !mostrarExcepcion && (
                                            <button
                                                type="button"
                                                className={`${BTN_SECONDARY} inline-flex items-center gap-2 text-xs`}
                                                onClick={() => {
                                                    setMostrarExcepcion(true);
                                                    setData('direccion_manual_excepcion', true);
                                                    setData('cliente_direccion_id', '');
                                                    setCamposDireccion({ ...CAMPOS_DIRECCION_VACIOS });
                                                    setDireccionSucia(true);
                                                }}
                                            >
                                                <PenLine className="w-3.5 h-3.5" />
                                                Usar dirección manual
                                            </button>
                                        )}
                                    </div>
                                    {(mostrarExcepcion || (puedeManual && direccionesCliente.length === 0)) && (
                                        <div className="space-y-3">
                                            <CamposDireccionPedido
                                                valores={camposDireccion}
                                                onChange={(nuevos) => {
                                                    setCamposDireccion(nuevos);
                                                    setDireccionSucia(true);
                                                    setData('direccion_manual_excepcion', true);
                                                    setData('cliente_direccion_id', '');
                                                    setData('domicilio_entrega', resumirCamposDireccion(nuevos));
                                                    setData('codigo_postal', nuevos.codigo_postal || '');
                                                }}
                                                disabled={logisticaBloqueada}
                                                sucio={direccionSucia}
                                                puedeEditar={!logisticaBloqueada}
                                            />
                                            {can('clientes.direcciones.crear') && (
                                                <div className="flex flex-wrap gap-2">
                                                    <button
                                                        type="button"
                                                        disabled={guardandoDireccion || logisticaBloqueada}
                                                        className={`${BTN_PRIMARY} text-xs outline-none disabled:opacity-50`}
                                                        onClick={() => registrarDireccionEnCatalogo(true)}
                                                    >
                                                        {guardandoDireccion ? 'Guardando…' : 'Guardar como dirección principal'}
                                                    </button>
                                                    {direccionesCliente.length > 0 && (
                                                        <button
                                                            type="button"
                                                            disabled={guardandoDireccion || logisticaBloqueada}
                                                            className={`${BTN_SECONDARY} text-xs outline-none disabled:opacity-50`}
                                                            onClick={() => registrarDireccionEnCatalogo(false)}
                                                        >
                                                            Guardar como adicional
                                                        </button>
                                                    )}
                                                </div>
                                            )}
                                            <p className="text-[10px] theme-text-muted font-bold m-0">
                                                {direccionesCliente.length === 0
                                                    ? 'La primera dirección del cliente debe guardarse como principal para poder enviar el pedido y registrar el pago.'
                                                    : 'Guardar en catálogo vincula la dirección al pedido. «Adicional» no reemplaza la principal.'}
                                            </p>
                                            {Boolean(data.direccion_manual_excepcion) && !manualDireccionCompleta(camposDireccion) && (
                                                <p className="text-[10px] font-bold m-0" style={{ color: 'var(--color-peligro)' }}>
                                                    Falta: {faltantesManualDireccion(camposDireccion).join(', ') || 'datos de dirección'}.
                                                </p>
                                            )}
                                            <input
                                                type="text"
                                                placeholder="Motivo de la excepción (solo si no guarda en catálogo)"
                                                value={data.motivo_direccion_manual}
                                                onChange={(e) => setData('motivo_direccion_manual', e.target.value)}
                                                className={`${THEME_INPUT} w-full py-3`}
                                            />
                                            {direccionesCliente.length > 0 && (
                                                <button
                                                    type="button"
                                                    className="text-xs underline theme-text-muted"
                                                    onClick={() => {
                                                        setMostrarExcepcion(false);
                                                        setData('direccion_manual_excepcion', false);
                                                        setDireccionSucia(false);
                                                        const elegida = elegirDireccionParaPedido(direccionesCliente, {
                                                            direccionId: data.cliente_direccion_id,
                                                        });
                                                        aplicarDireccionSeleccionada(elegida);
                                                    }}
                                                >
                                                    Volver al selector
                                                </button>
                                            )}
                                        </div>
                                    )}
                                </div>
                            )}
                            {direccionesCliente.length > 0 && !mostrarExcepcion && puedeManual && (
                                <button
                                    type="button"
                                    className="text-xs underline theme-text-muted"
                                    onClick={() => {
                                        setMostrarExcepcion(true);
                                        setData('direccion_manual_excepcion', true);
                                        setData('cliente_direccion_id', '');
                                        setCamposDireccion({ ...CAMPOS_DIRECCION_VACIOS });
                                        setDireccionSucia(true);
                                    }}
                                >
                                    Usar excepción manual en su lugar
                                </button>
                            )}
                        </div>
                    </section>

                    {/* 7. Paquetería y seguro */}
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>{nSec.paq}. Guía y seguro</p>
                        <div className="space-y-4">
                            <div className={`space-y-2 p-4 rounded-xl border theme-border theme-element ${logisticaBloqueada ? 'opacity-50' : ''}`}>
                                <label className={`flex items-center gap-2 theme-text-main ${logisticaBloqueada ? '' : 'cursor-pointer'}`}>
                                    <input
                                        type="radio"
                                        name="origen_guia"
                                        checked={!guiaCliente}
                                        disabled={logisticaBloqueada}
                                        onChange={() => marcarGuiaCliente(false)}
                                    />
                                    <span className="text-sm font-bold">{LABEL_GUIA_EMPRESA}</span>
                                </label>
                                <label className={`flex items-center gap-2 theme-text-main ${logisticaBloqueada ? '' : 'cursor-pointer'}`}>
                                    <input
                                        type="radio"
                                        name="origen_guia"
                                        checked={guiaCliente}
                                        disabled={logisticaBloqueada}
                                        onChange={() => marcarGuiaCliente(true)}
                                    />
                                    <span className="text-sm font-bold">{LABEL_GUIA_CLIENTE}</span>
                                </label>
                            </div>
                            {guiaCliente && (
                                <div className="flex items-start gap-2 p-3 rounded-xl border border-sky-500/40 bg-sky-500/10">
                                    <p className="text-xs font-bold text-sky-700 dark:text-sky-400 m-0">
                                        No se cobra envío ni seguro de la empresa. Tras el empaque, usted cargará la guía del cliente (no se notifica a quien genera guías).
                                    </p>
                                </div>
                            )}
                            {!guiaCliente && (
                                <label className={`flex items-center gap-2 theme-text-main ${logisticaBloqueada ? 'opacity-50' : 'cursor-pointer'} p-4 rounded-xl border theme-border theme-element`}>
                                    <input
                                        type="checkbox"
                                        checked={envioPorCobrar}
                                        disabled={logisticaBloqueada}
                                        onChange={(e) => marcarEnvioPorCobrar(e.target.checked)}
                                    />
                                    <span className="text-sm font-bold">Envío por cobrar (no se cobra envío de la empresa).</span>
                                </label>
                            )}
                            {envioPorCobrar && !guiaCliente && (
                                <div className="flex items-start gap-2 p-3 rounded-xl border border-amber-500/40 bg-amber-500/10">
                                    <p className="text-xs font-bold text-amber-700 dark:text-amber-400 m-0">
                                        El costo de envío empresarial queda en $0. La paquetería cobra al destinatario.
                                    </p>
                                </div>
                            )}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {!guiaCliente && !tieneCoberturaSeguro && data.catalogo_paqueteria_id && (
                                    <div id="seg-warn" className="md:col-span-2 flex items-start gap-2 p-3 rounded-xl border border-amber-500/40 bg-amber-500/10">
                                        <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                                        <p className="text-xs font-bold text-amber-700 dark:text-amber-400 m-0">
                                            Este transporte no cuenta con cobertura de seguro.
                                        </p>
                                    </div>
                                )}
                                {!guiaCliente && (
                                <div className={wrapCampo('tipo_guia')} data-campo="tipo_guia">
                                    <label className={SECCION}>Tipo de guía</label>
                                    <select value={data.catalogo_tipo_guia_id} disabled={logisticaBloqueada || !cotizacionHabilitada} onChange={(e) => setData('catalogo_tipo_guia_id', e.target.value)} className={`${THEME_SELECT} w-full py-3 ${logisticaBloqueada || !cotizacionHabilitada ? 'opacity-50' : ''}`}>
                                        <option value="">{cotizacionHabilitada ? 'Seleccionar...' : 'Tras pesaje CEDIS...'}</option>
                                        {(catalogos.tipos_guia || []).map((g) => <option key={g.id} value={g.id}>{g.nombre}</option>)}
                                    </select>
                                </div>
                                )}
                                {!guiaCliente && (
                                <div className={wrapCampo('reexpedicion')} data-campo="reexpedicion">
                                    <label className={SECCION}>Reexpedición</label>
                                    <select value={data.catalogo_zona_id} disabled={logisticaBloqueada} onChange={(e) => setData('catalogo_zona_id', e.target.value)} className={`${THEME_SELECT} w-full py-3 ${logisticaBloqueada ? 'opacity-50' : ''}`}>
                                        <option value="">Seleccionar...</option>
                                        {(catalogos.zonas || []).map((z) => <option key={z.id} value={z.id}>{z.nombre}</option>)}
                                    </select>
                                    {Number(costoReexpedicion) > 0 ? (
                                        <p className="text-[10px] font-bold mt-1 m-0" style={{ color: 'var(--color-exito)' }}>
                                            Cargo de zona: {formatearMoneda(costoReexpedicion)}
                                        </p>
                                    ) : (
                                        <p className="text-[10px] theme-text-muted font-bold mt-1 m-0">
                                            Al elegir «Con reexpedición» se aplica el monto configurado en Admin → Zonas Pedido (aunque el CP no esté en el catálogo).
                                        </p>
                                    )}
                                </div>
                                )}
                            </div>
                            {!data.es_resguardo && esMunicipioDiferido && (
                                <div className="flex items-start gap-2 p-3 rounded-xl border border-amber-500/40 bg-amber-500/10">
                                    <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                                    <p className="text-xs font-bold text-amber-700 dark:text-amber-400 m-0">
                                        Paquetería local/regional: el costo de envío puede anexarse después. Peso, cajas y costo son opcionales al registrar.
                                    </p>
                                </div>
                            )}
                            {!data.es_resguardo && data.catalogo_paqueteria_id && !esMunicipioDiferido && (
                                <div className="flex items-start gap-2 p-3 rounded-xl border theme-border theme-element">
                                    <p className="text-xs font-bold theme-text-muted m-0">
                                        Envío comercial: capture peso, cajas y costo al registrar el pedido.
                                    </p>
                                </div>
                            )}

                            <div className="flex flex-wrap items-center gap-x-6 gap-y-3 p-4 rounded-xl border theme-border theme-element">
                                {!guiaCliente && tieneCoberturaSeguro && (
                                    <label className="flex items-center gap-2 theme-text-main cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={data.aplica_seguro}
                                            onChange={(e) => setData('aplica_seguro', e.target.checked)}
                                        />
                                        <span className="text-sm font-bold">
                                            {data.aplica_seguro ? 'Con seguro' : 'Sin seguro'}
                                        </span>
                                    </label>
                                )}
                                <label className={`flex items-center gap-2 theme-text-main ${logisticaBloqueada ? 'opacity-50' : 'cursor-pointer'}`}>
                                    <input type="checkbox" checked={data.envia_a_otra_persona} disabled={logisticaBloqueada} onChange={(e) => setData('envia_a_otra_persona', e.target.checked)} />
                                    <span className="text-sm font-bold">Enviar a otra persona</span>
                                </label>
                            </div>

                            {((!guiaCliente && data.aplica_seguro) || data.envia_a_otra_persona || (!guiaCliente && tieneCoberturaSeguro)) && (
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    {!guiaCliente && tieneCoberturaSeguro && (
                                        <div>
                                            <label className={SECCION}>Costo de seguro (calculado)</label>
                                            <InputMoneda value={data.costo_seguro} onChange={() => {}} readOnly className="w-full py-3 opacity-80" />
                                        </div>
                                    )}
                                    {data.envia_a_otra_persona && (
                                        <div className={`md:col-span-2 ${wrapCampo('destinatario')}`}>
                                            <label className={SECCION}>Nombre del destinatario</label>
                                            <input type="text" placeholder="Nombre completo" value={data.envia_otra_persona} onChange={(e) => setData('envia_otra_persona', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    </section>
                    </>
                    )}

                    {mostrarLogisticaPostPesaje && (
                    <>
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>{nSec.saf}. Saldo a favor</p>
                        <div className="space-y-4">
                            {bloqueSafControles}
                        </div>
                    </section>

                    {/* 9. Cotización */}
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>{nSec.cot}. Cotización</p>
                        <div className="space-y-4">
                            {!guiaCliente && !envioPorCobrar && (
                                <div className={`max-w-md ${wrapCampo('costo_envio')}`} data-campo="costo_envio">
                                    <label className={SECCION}>{labelCostoEnvio}{esMunicipioDiferido || omiteCostoPorTarifaPeso ? ' (opcional)' : ''}{camposEnvioBloqueados ? ' (bloqueado)' : ''}</label>
                                    <InputMoneda value={camposEnvioBloqueados ? '' : data.costo_envio} onChange={(v) => setData('costo_envio', v)} className={`w-full py-3 ${camposEnvioBloqueados ? 'opacity-50 pointer-events-none' : ''}`} placeholder="" />
                                    {omiteCostoPorTarifaPeso && (
                                        <p className="text-[10px] theme-text-muted font-bold mt-1 m-0">
                                            Se calculará con el pesaje CEDIS según la tarifa por peso de la paquetería.
                                        </p>
                                    )}
                                    {Number(costoReexpedicion) > 0 && (
                                        <p className="text-[10px] theme-text-muted font-bold mt-1 m-0">
                                            Reexpedición (zona): {formatearMoneda(costoReexpedicion)} (se suma aparte al cobro).
                                        </p>
                                    )}
                                </div>
                            )}
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between theme-text-muted font-bold"><span>Total de mercancía</span><span>{formatearMoneda(data.total_mercancia)}</span></div>
                                <div className="flex justify-between theme-text-muted font-bold"><span>{labelCostoEnvio}</span><span>{formatearMoneda(guiaCliente || envioPorCobrar ? 0 : data.costo_envio)}</span></div>
                                <div className="flex justify-between theme-text-muted font-bold">
                                    <span>Reexpedición</span>
                                    <span>{formatearMoneda(guiaCliente || envioPorCobrar ? 0 : costoReexpedicion)}</span>
                                </div>
                                <div className="flex justify-between theme-text-muted font-bold">
                                    <span>Costo del seguro</span>
                                    <span>{data.aplica_seguro ? formatearMoneda(data.costo_seguro) : formatearMoneda(0)}</span>
                                </div>
                                <div className="flex justify-between theme-text-muted font-bold">
                                    <span>Total a cubrir</span>
                                    <span>{formatearMoneda(resumenCoberturaVivo.total_a_cubrir)}</span>
                                </div>
                                <div className="flex justify-between font-bold" style={COLOR_EXITO}>
                                    <span>Saldo a favor aplicado</span>
                                    <span>- {formatearMoneda(data.aplica_saldo_favor ? saldoFavorCalculado : 0)}</span>
                                </div>
                                {resumenCoberturaVivo.excedente_generado > 0.01 && (
                                    <div className="flex justify-between font-bold" style={COLOR_INFO}>
                                        <span>Excedente generado (este pedido)</span>
                                        <span>{formatearMoneda(resumenCoberturaVivo.excedente_generado)}</span>
                                    </div>
                                )}
                            </div>
                            <div className="mt-4 p-4 rounded-2xl border-2" style={{ borderColor: 'var(--color-primario)' }}>
                                <p className="text-[10px] font-black uppercase theme-text-muted m-0">Total a cobrar ahora</p>
                                <p className="text-[10px] theme-text-muted font-bold m-0">Después del saldo a favor aplicado</p>
                                <p className="text-2xl font-black m-0" style={{ color: 'var(--color-primario)' }}>{formatearMoneda(totalCobrar)}</p>
                            </div>
                        </div>
                    </section>
                    </>
                    )}

                    {mostrarSeccionPago && (
                    <section className={`${SECCION_WRAP} ${wrapCampo('pago')}`} data-campo="pago">
                        <p className={SECCION}>{nSec.pago}. Pago</p>
                        <div className="space-y-4">
                            <p className="text-[10px] font-bold theme-text-muted m-0 -mt-1">
                                Registre el pago cuando el cliente transfiera: elija banco receptor y adjunte el comprobante. La referencia va en el comprobante (no es necesario capturarla).
                            </p>
                            <SeccionPagosExhibicion
                                pedidoId={idPedidoAcciones}
                                bancos={catalogos.bancos || []}
                                formasPago={catalogos.formas_pago || []}
                                puedeRegistrar={puedeRegistrarPago}
                                puedeGenerarSaldo={false}
                                totalMercancia={data.total_mercancia}
                                costoEnvio={guiaCliente
                                    ? 0
                                    : Number(data.costo_envio || 0) + Number(costoReexpedicion || 0)}
                                aplicaSeguro={Boolean(data.aplica_seguro)}
                                costoSeguro={data.costo_seguro}
                                saldoAFavorAplicado={data.aplica_saldo_favor ? saldoFavorCalculado : 0}
                                onResumenChange={(r) => setPagoResumen(r)}
                                mensajeBloqueo={!consultaCerrada
                                    ? 'Cierre la consulta CEDIS antes de registrar el pago.'
                                    : (requiereLogistica && !cotizacionLista
                                        ? 'Complete la cotización de envío antes de registrar el pago.'
                                        : null)}
                            />
                            {docsExistentes.length > 0 && (
                                <div>
                                    <label className={SECCION}>Comprobantes anteriores (solo consulta)</label>
                                    <p className="text-[10px] theme-text-muted font-bold mb-3 -mt-1">
                                        Los nuevos comprobantes se adjuntan en cada exhibición de pago.
                                    </p>
                                    <div className="flex flex-wrap gap-3 mt-3">
                                        {docsExistentes.map((doc) => (
                                            <MiniaturaDocumento
                                                key={doc.id}
                                                documento={doc}
                                                onVer={() => abrirVistaPrevia(doc)}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                            {!requiereLogistica && bloqueSafControles}
                            {!requiereLogistica && (
                                <div className="space-y-2 text-sm">
                                    <div className="flex justify-between theme-text-muted font-bold"><span>Total de mercancía</span><span>{formatearMoneda(data.total_mercancia)}</span></div>
                                    <div className="flex justify-between font-bold" style={COLOR_EXITO}>
                                        <span>Saldo a favor aplicado</span>
                                        <span>- {formatearMoneda(data.aplica_saldo_favor ? saldoFavorCalculado : 0)}</span>
                                    </div>
                                    <div className="mt-4 p-4 rounded-2xl border-2" style={{ borderColor: 'var(--color-primario)' }}>
                                        <p className="text-[10px] font-black uppercase theme-text-muted m-0">Total a cobrar ahora</p>
                                        <p className="text-2xl font-black m-0" style={{ color: 'var(--color-primario)' }}>{formatearMoneda(totalCobrar)}</p>
                                    </div>
                                </div>
                            )}
                        </div>
                    </section>
                    )}

                    {mostrarRestoPedido && !mostrarSeccionPago && (
                    <section className={SECCION_WRAP} data-campo="pago">
                        <p className={SECCION}>{nSec.pago}. Pago</p>
                        <AvisoOperativoPedido label="Más adelante" tono="info" icon={Scale} className="mb-0">
                            {requiereLogistica
                                ? (tienePesajeRespondido && !consultaCerrada
                                    ? 'Cierre la consulta CEDIS y complete la cotización. Luego registre banco y comprobante.'
                                    : 'Complete el pesaje CEDIS, cierre la consulta y la cotización. El pago se captura después.')
                                : 'Solicite la consulta de mercancía, ciérrela con el cliente y capture el monto. Luego registre el pago.'}
                        </AvisoOperativoPedido>
                    </section>
                    )}

                    {mostrarRestoPedido && (
                    <>
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>{nSec.rem}. Remisión</p>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className={SECCION}>Folio de pedido</label>
                                <input
                                    type="text"
                                    readOnly
                                    value={data.folio_remision || '—'}
                                    className={`${THEME_INPUT} w-full py-3 opacity-60`}
                                />
                            </div>
                            <div>
                                <label className={SECCION}>Folio interno</label>
                                <input type="text" readOnly value={pedido?.folio || 'Se asignará al guardar'} className={`${THEME_INPUT} w-full py-3 text-[11px] opacity-50`} />
                            </div>
                            <div>
                                <label className={SECCION}>Status</label>
                                <input
                                    type="text"
                                    readOnly
                                    value={etiquetaEstatusPedido(pedido?.estatus, { esResguardo: pedido?.es_resguardo }) || 'Borrador'}
                                    className={`${THEME_INPUT} w-full py-3 opacity-60`}
                                />
                            </div>
                            <div>
                                <label className={SECCION}>{LABEL_NOTA_COMPRA_PREGUNTA}</label>
                                <select
                                    value={data.anexar_remision ? '1' : '0'}
                                    disabled={logisticaBloqueada}
                                    onChange={(e) => setData('anexar_remision', e.target.value === '1')}
                                    className={`${THEME_SELECT} w-full py-3 ${logisticaBloqueada ? 'opacity-50' : ''}`}
                                >
                                    <option value="0">NO</option>
                                    <option value="1">SÍ</option>
                                </select>
                            </div>
                            <div className="md:col-span-2">
                                <label className={SECCION}>Comentarios para Drive / Almacén</label>
                                <textarea placeholder="Notas adicionales..." value={data.comentarios_drive} onChange={(e) => setData('comentarios_drive', e.target.value)} className={`${THEME_TEXTAREA} w-full py-3 min-h-[80px]`} />
                            </div>
                        </div>
                    </section>
                    </>
                    )}

                </div>
                    <section className="gelia-modal-footer flex flex-col gap-3 p-5 md:p-6 shrink-0">
                        {mostrarEnviarPedido && intentoEnviar && !enviarPedidoListo ? (
                            <p className="text-xs font-bold m-0 leading-snug" style={{ color: 'var(--color-peligro)' }}>
                                {(validacionEnvio.faltantes || []).length
                                    ? `Falta: ${validacionEnvio.faltantes.join(', ')}.`
                                    : 'Hay campos faltantes.'}
                            </p>
                        ) : null}
                        {Object.keys(errors).length > 0 && (
                            <p className="text-xs text-red-500 font-bold m-0">Revise los campos del formulario.</p>
                        )}
                        <div className="flex flex-wrap gap-3">
                        {mostrarEnviarPedido && (
                            <button
                                type="button"
                                onClick={() => guardar(true)}
                                disabled={processing}
                                title={!enviarPedidoListo ? (validacionEnvio.mensaje || 'Complete los datos necesarios para enviar') : undefined}
                                className={`${BTN_PRIMARY} flex items-center gap-2 outline-none`}
                            >
                                <Send className="w-4 h-4" /> Enviar pedido
                            </button>
                        )}
                        <button type="button" onClick={() => guardar(false)} disabled={processing} className={`${BTN_SECONDARY} theme-element border theme-border flex items-center gap-2 outline-none`}>
                            <Save className="w-4 h-4" /> Guardar borrador
                        </button>
                        <button type="button" onClick={() => {
                            setData(formDefaults(pedido, catalogos.tipos_operacion_envio || []));
                            setPreviews([]);
                            setInfoCliente(pedido?.cliente || null);
                            setAlertaDireccion(false);
                            setMsgDireccion('');
                            setDocsEliminar([]);
                            setIntentoEnviar(false);
                            setAvisoForm(null);
                            setAvisoPdf(null);
                            setAvisoPesaje(null);
                            setPdfLocalOk(false);
                            setPdfDocLocal(null);
                            setAnexoLocalOk(false);
                            setAnexoDocsLocal([]);
                            if (!modoEdicion) {
                                limpiarBorradorLocal();
                                // Conserva pedidoBdId para no crear borradores huérfanos; el próximo autoguardado limpia campos en BD.
                                setEstadoAuto({ local: null, bd: pedidoBdIdRef.current ? `Servidor · #${pedidoBdIdRef.current}` : null });
                            }
                        }} className={`${BTN_SECONDARY} theme-element border theme-border flex items-center gap-2 outline-none`}>
                            <RotateCcw className="w-4 h-4" /> Limpiar
                        </button>
                        </div>
                    </section>
            </div>
        </div>,
        document.body
    ) : null;

    return (
        <>
            {modal}
            <ModalGenerarLinkDireccion
                abierto={Boolean(abierto) && modalLinkDireccion}
                onClose={() => {
                    ignoreOverlayCloseUntil.current = Date.now() + 400;
                    setModalLinkDireccion(false);
                }}
                clientePreseleccionado={infoCliente?.id ? infoCliente : null}
                onEnlaceGenerado={() => {
                    if (infoCliente?.id) {
                        cargarDireccionCliente(infoCliente.id, {
                            silencioso: true,
                            conservarSeleccion: true,
                            direccionId: data.cliente_direccion_id,
                        });
                    }
                }}
            />
            <ModalVistaPreviaDocumento
                abierto={Boolean(abierto) && Boolean(vistaPrevia?.documentos?.length)}
                documentos={vistaPrevia?.documentos || []}
                indice={vistaPrevia?.indice || 0}
                onClose={() => {
                    ignoreOverlayCloseUntil.current = Date.now() + 400;
                    setVistaPrevia(null);
                }}
            />
            <ModalConfirmarAccion
                abierto={Boolean(abierto) && confirmarActualizarDir}
                titulo="Actualizar dirección"
                mensaje={(() => {
                    const sel = direccionesCliente.find((d) => String(d.id) === String(data.cliente_direccion_id));
                    if (sel?.es_principal) {
                        return 'Está actualizando la dirección PRINCIPAL del catálogo del cliente (nueva versión + auditoría). Si el pedido ya existe, también se guardará un snapshot. Si solo necesita una excepción para este pedido, use «dirección manual» en su lugar. ¿Confirmar?';
                    }
                    return 'Se creará una nueva versión de la dirección en el catálogo del cliente, con auditoría. Si el pedido ya existe, también se guardará un snapshot. ¿Confirmar?';
                })()}
                etiquetaConfirmar="Confirmar actualización"
                variante="primary"
                onClose={() => {
                    ignoreOverlayCloseUntil.current = Date.now() + 400;
                    setConfirmarActualizarDir(false);
                }}
                onConfirm={confirmarGuardarDireccion}
            />
        </>
    );
}
