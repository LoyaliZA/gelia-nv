import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, usePage, router } from '@inertiajs/react';
import axios from 'axios';
import {
    X, Search, Save, Send, RotateCcw, ImagePlus, Trash2, AlertTriangle, PenLine, Link2, Cloud, HardDrive, Scale, FileText, ArrowRight,
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
} from './pedidosBmaStyles';
import ModalAlertaPedido from './ModalAlertaPedido';
import ModalVistaPreviaDocumento from './ModalVistaPreviaDocumento';
import ModalGenerarLinkDireccion from './ModalGenerarLinkDireccion';
import AvisoOperativoPedido from './AvisoOperativoPedido';
import SeccionRevisionFisicaPedido from './SeccionRevisionFisicaPedido';
import CamposDireccionPedido, {
    CAMPOS_DIRECCION_VACIOS,
    camposDesdeDireccion,
    resumirCamposDireccion,
} from './CamposDireccionPedido';
import { resolverReexpedicionForm } from './resolverReexpedicionForm';
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
    const [alertaEnvio, setAlertaEnvio] = useState({ abierto: false, mensaje: '', tipo: 'error' });
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
    const pedidoBdIdRef = useRef(pedido?.id || null);
    const ultimoFingerprintBd = useRef('');
    const ultimoSyncCedis = useRef('');
    const autoguardandoBd = useRef(false);
    const ignoreOverlayCloseUntil = useRef(0);
    const [pedidoBdId, setPedidoBdId] = useState(pedido?.id || null);
    const [estadoAuto, setEstadoAuto] = useState({ local: null, bd: null });
    const [motivoRepesaje, setMotivoRepesaje] = useState('');
    const [procesandoPesaje, setProcesandoPesaje] = useState(false);
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
    const tienePesajeRespondido = Boolean(pedido?.pesaje_respondido_at);
    const pendientePesaje = pedido?.estatus_envio === 'pendiente_pesaje';
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

    // Sin tipo elegido: solo Tipo + Cliente. Con Envío: pesaje primero; resto al responder (o Continuar legacy).
    const tieneTipo = Boolean(data.origen_id || pedido?.origen_id || pedido?.origen?.id);
    const enfocadoEnPesaje = fasePedido === 'PESAJE_PENDIENTE';
    // Pesaje listo/pendiente debe verse aunque falte origen en el form (p. ej. fila sin origen_id).
    const mostrarPesaje = (tieneTipo && requiereLogistica) || tienePesajeRespondido || pendientePesaje;
    const mostrarRestoPedido = Boolean(data.origen_id) && (!requiereLogistica || (cotizacionHabilitada && !enfocadoEnPesaje));

    const mostrarLogisticaPostPesaje = mostrarPesaje && mostrarRestoPedido;
    const mostrarPagosYCierre = mostrarRestoPedido;
    // «Enviar» tras pesaje respondido (auto-borrador) o Continuar legacy / flujo sin pesaje.
    const mostrarEnviarPedido = mostrarRestoPedido;
    const totalCobrar = calcularTotalCobrar(
        data.total_mercancia, data.costo_envio, data.aplica_seguro, data.costo_seguro,
        saldoFavorCalculado
    );
    const resumenCoberturaVivo = calcularResumenCoberturaPago({
        totalMercancia: data.total_mercancia,
        costoEnvio: guiaCliente ? 0 : data.costo_envio,
        aplicaSeguro: Boolean(data.aplica_seguro),
        costoSeguro: data.costo_seguro,
        saldoAFavorAplicado: data.aplica_saldo_favor ? saldoFavorCalculado : 0,
        totalPagado: pagoResumen?.total_pagado ?? pagoResumen?.total_recibido ?? 0,
    });
    const pagoPendienteVivo = !idPedidoAcciones
        ? null
        : (pagoResumen == null ? null : resumenCoberturaVivo.pendiente);
    const validacionEnvio = validarCamposEnvioPedido(data, {
        requiereLogistica,
        direccionesNormalizadas,
        esMunicipioDiferido,
        esResguardoAbierto,
        esResguardoComplementario,
        tienePesajeRespondido,
        pagoPendiente: pagoPendienteVivo,
        paqueteria: paqueteriaSeleccionada,
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
    const pdfPedidoDoc = (pedido?.documentos || []).find((d) => d.tipo === 'pdf_pedido' && !docsEliminar.includes(d.id));
    const anexoPiezasDoc = (pedido?.documentos || []).find((d) => d.tipo === 'anexo_piezas' && !docsEliminar.includes(d.id));
    const tienePdfPedido = Boolean(pdfPedidoDoc) || pdfLocalOk;
    const tieneAnexoPiezas = Boolean(anexoPiezasDoc);
    const labelSoportePedido = (doc) => {
        const mime = String(doc?.mime_type || '');
        const nombre = String(doc?.nombre_original || '').toLowerCase();
        if (mime.startsWith('image/') || /\.(jpe?g|png|webp)$/.test(nombre)) return 'Ver foto';
        return 'Ver PDF';
    };
    const cajasPesaje = pedido?.cajas || [];
    const tieneCoberturaSeguro = paqueteriaTieneCobertura(paqueteriaSeleccionada?.nombre);
    const paqueteriasComerciales = (catalogos.paqueterias || []).filter((p) => p.categoria === 'comercial');
    const paqueteriasLocales = (catalogos.paqueterias || []).filter((p) => p.categoria !== 'comercial');

    const modalAnidadoAbierto = confirmarActualizarDir || alertaEnvio.abierto || Boolean(vistaPrevia) || modalLinkDireccion;

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
            setAlertaEnvio({ abierto: false, mensaje: '' });
            setDireccionesCliente([]);
            setMostrarExcepcion(false);
            setEstadoAuto({ local: null, bd: null });
            setMotivoRepesaje('');
            setPdfLocalOk(false);
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
            setAlertaEnvio({ abierto: false, mensaje: '' });
            if (!borrador?.cliente_id) {
                setDireccionesCliente([]);
            }
            setMostrarExcepcion(Boolean(borrador?.direccion_manual_excepcion));
            setMotivoRepesaje('');
            setPdfLocalOk(false);
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
            setAlertaEnvio({ abierto: false, mensaje: '' });
            setDireccionesCliente([]);
            setMostrarExcepcion(false);
            setMotivoRepesaje('');
            setPdfLocalOk(false);
            setEstadoAuto({ local: null, bd: null });
        }
    }, [abierto, pedido?.id, recuperarBorrador]);

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
        setData((prev) => ({
            ...prev,
            ...(origenId ? { origen_id: origenId } : {}),
            peso_real_kg: pedido.peso_real_kg ?? '',
            numero_cajas: pedido.numero_cajas ?? '',
            peso_cobrado_guia_kg: pedido.peso_cobrado_guia_kg ?? '',
            catalogo_tipo_caja_id: pedido.catalogo_tipo_caja_id || '',
            costo_envio: pedido.costo_envio ?? prev.costo_envio ?? '',
            catalogo_paqueteria_id: pedido.catalogo_paqueteria_id || prev.catalogo_paqueteria_id || '',
            catalogo_tipo_guia_id: pedido.catalogo_tipo_guia_id || prev.catalogo_tipo_guia_id || '',
        }));
        if (pedido.pesaje_respondido_at) {
            setPesoVolumetrico(pedido.peso_volumetrico_kg ?? '');
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

    /** Guarda el borrador en BD (sin archivos) y devuelve su id. */
    const persistirBorradorBd = async () => {
        autoguardandoBd.current = true;
        setEstadoAuto((s) => ({ ...s, bd: 'Servidor · guardando…' }));
        try {
            const base = serializarBorrador(data);
            const payload = {};
            Object.entries(base).forEach(([k, v]) => {
                if (typeof v === 'boolean') {
                    payload[k] = v;
                } else {
                    payload[k] = v === '' ? null : v;
                }
            });
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

            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            let url = '/control-pedidos/autoguardar';
            try {
                url = route('control_pedidos.autoguardar');
            } catch {
                /* ziggy stale */
            }
            const { data: res } = await axios.post(url, payload, {
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
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
                router.reload({ only: ['pedidos', 'metricas'], preserveState: true, preserveScroll: true });
            }
            return res.id;
        } finally {
            autoguardandoBd.current = false;
        }
    };

    /** Devuelve el id del pedido en BD, creando el borrador si aún no existe. */
    const asegurarPedidoEnBd = async () => {
        if (idPedidoAcciones) return idPedidoAcciones;
        if (!tieneContenidoParaBd(data)) {
            setAlertaEnvio({ abierto: true, mensaje: 'Capture al menos el origen y el cliente antes de continuar.' });
            return null;
        }
        try {
            return await persistirBorradorBd();
        } catch (err) {
            const msg = err?.response?.data?.message || 'No se pudo guardar el borrador en el servidor.';
            setAlertaEnvio({ abierto: true, mensaje: msg });
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
            costoEnvioActual: data.costo_envio,
            costoAplicadoPrevio: costoReexpedicionAplicado.current,
        });
        if (resolved.matchKey === matchReexpedicionKey.current
            && Number(resolved.costoAplicado) === Number(costoReexpedicionAplicado.current)) {
            return;
        }
        // En edición, la primera sync asume que costo_envio ya incluye el adicional.
        if (modoEdicion && matchReexpedicionKey.current === null && resolved.matchKey) {
            matchReexpedicionKey.current = resolved.matchKey;
            costoReexpedicionAplicado.current = resolved.costoAplicado;
            if (resolved.zonaId !== '' && String(resolved.zonaId) !== String(data.catalogo_zona_id)) {
                setData('catalogo_zona_id', resolved.zonaId);
            }
            return;
        }
        matchReexpedicionKey.current = resolved.matchKey;
        costoReexpedicionAplicado.current = resolved.costoAplicado;
        if (resolved.zonaId !== '' && String(resolved.zonaId) !== String(data.catalogo_zona_id)) {
            setData('catalogo_zona_id', resolved.zonaId);
        }
        if (Number(resolved.costoEnvio) !== Number(data.costo_envio || 0)) {
            setData('costo_envio', resolved.costoEnvio);
        }
    }, [abierto, requiereLogistica, modoEdicion, data.codigo_postal, data.catalogo_paqueteria_id, catalogos.reexpediciones, catalogos.zonas]);

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

        setCargandoDireccion(true);
        setMsgDireccion('');
        setSinDireccionPrincipal(false);
        try {
            const response = await axios.get(`/api/clientes/id/${clienteId}/direccion-envio`);
            const dirs = response.data?.direcciones || [];
            setDireccionesCliente(dirs);

            const idSeleccion = conservarSeleccion
                ? (direccionId || data.cliente_direccion_id)
                : null;
            const seleccionada = idSeleccion
                ? dirs.find((d) => String(d.id) === String(idSeleccion))
                : null;
            const principal = dirs.find((d) => d.es_principal) || null;
            const tienePrincipal = Boolean(principal) || Boolean(response.data?.tiene_direccion_principal);

            if (!tienePrincipal) {
                setSinDireccionPrincipal(true);
                setAlertaDireccion(false);
                if (!seleccionada) {
                    aplicarDireccionSeleccionada(null);
                } else {
                    aplicarDireccionSeleccionada(seleccionada);
                }
                if (!silencioso) {
                    setMsgDireccion('Este cliente no tiene una dirección principal registrada. Debe registrar los datos de dirección antes de continuar.');
                }
                return;
            }

            setSinDireccionPrincipal(false);
            const elegida = seleccionada || principal;
            if (elegida) {
                aplicarDireccionSeleccionada(elegida, { marcarAlerta: !conservarSeleccion });
                setMsgDireccion('');
            } else {
                aplicarDireccionSeleccionada(null);
                if (!silencioso) {
                    setMsgDireccion('Este cliente no tiene direcciones verificadas. Solicite el registro de dirección.');
                }
            }
        } catch {
            setAlertaDireccion(false);
            setDireccionesCliente([]);
            setSinDireccionPrincipal(true);
            aplicarDireccionSeleccionada(null);
            if (!silencioso) {
                setMsgDireccion('No se pudo obtener la dirección del cliente. Registre una dirección en el catálogo.');
            }
        } finally {
            setCargandoDireccion(false);
        }
    };

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
            setAlertaEnvio({ abierto: true, mensaje: 'Seleccione un cliente y una dirección del catálogo.' });
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
            setAlertaEnvio({ abierto: true, mensaje: res.data?.message || 'Dirección actualizada.', tipo: 'success' });
        } catch (err) {
            const msg = err?.response?.data?.message || 'No se pudo actualizar la dirección.';
            setAlertaEnvio({ abierto: true, mensaje: msg, tipo: 'error' });
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
        if (!cotizacionLista) return;
        const items = e.clipboardData?.items;
        if (!items) return;
        const pasted = [];
        for (const item of items) {
            if (item.type.indexOf('image') !== -1) {
                const file = item.getAsFile();
                if (file) pasted.push(file);
            }
        }
        if (pasted.length) {
            e.preventDefault();
            agregarArchivos(pasted);
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
        setAlertaEnvio({ abierto: false, mensaje: '' });

            if (enviarPedido) {
            if (esResguardoComplementario && !data.pedido_principal_id) {
                setAlertaEnvio({ abierto: true, mensaje: 'Seleccione el pedido principal a complementar.' });
                return;
            }
            if (requiereLogistica && pendientePesaje) {
                setAlertaEnvio({ abierto: true, mensaje: 'Espere la respuesta de pesaje de CEDIS antes de enviar.' });
                return;
            }
            const { valido, mensaje } = validarCamposEnvioPedido(data, {
                requiereLogistica,
                direccionesNormalizadas,
                esMunicipioDiferido,
                esResguardoAbierto,
                esResguardoComplementario,
                tienePesajeRespondido,
                pagoPendiente: pagoPendienteVivo,
                paqueteria: paqueteriaSeleccionada,
            });
            if (!valido) {
                setAlertaEnvio({ abierto: true, mensaje });
                return;
            }
        }

        const idDestino = modoEdicion ? pedido.id : pedidoBdIdRef.current;
        const config = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: (page) => {
                if (page?.props?.flash?.error) {
                    setAlertaEnvio({ abierto: true, mensaje: page.props.flash.error });
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
            transform((d) => ({
                ...d,
                _method: 'put',
                enviar: enviarPedido,
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
            }));
            post(route('control_pedidos.update', idDestino), config);
        } else {
            transform((d) => ({
                ...d,
                enviar: enviarPedido,
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
            }));
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
            setAlertaEnvio({ abierto: true, mensaje: typeof msg === 'string' ? msg : 'No se pudo completar la acción de pesaje.', tipo: 'error' });
        },
    };

    const subirPdfPedido = async (e) => {
        const file = e.target.files?.[0];
        e.target.value = '';
        if (!file) return;
        const id = await asegurarPedidoEnBd();
        if (!id) return;
        const fd = new FormData();
        fd.append('pdf_pedido', file);
        router.post(route('control_pedidos.pdf_pedido.store', id), fd, {
            ...optsPesaje,
            forceFormData: true,
            onSuccess: (page) => {
                if (page?.props?.flash?.error) {
                    setAlertaEnvio({ abierto: true, mensaje: page.props.flash.error, tipo: 'error' });
                    return;
                }
                setPdfLocalOk(true);
                setAlertaEnvio({
                    abierto: true,
                    mensaje: page?.props?.flash?.success || 'PDF o foto del pedido adjuntado.',
                    tipo: 'success',
                });
            },
        });
    };

    const subirAnexoPiezas = async (e) => {
        const file = e.target.files?.[0];
        e.target.value = '';
        if (!file || !idPedidoAcciones) return;
        const fd = new FormData();
        fd.append('anexo_piezas', file);
        router.post(route('control_pedidos.anexo_piezas.store', idPedidoAcciones), fd, {
            ...optsPesaje,
            forceFormData: true,
            onSuccess: (page) => {
                if (page?.props?.flash?.error) {
                    setAlertaEnvio({ abierto: true, mensaje: page.props.flash.error, tipo: 'error' });
                    return;
                }
                setAlertaEnvio({
                    abierto: true,
                    mensaje: page?.props?.flash?.success || 'Anexo de piezas adicionales adjuntado.',
                    tipo: 'success',
                });
            },
        });
    };

    const postSolicitudPesaje = (id) => {
        router.post(route('control_pedidos.solicitar_pesaje', id), {}, {
            ...optsPesaje,
            onSuccess: (page) => {
                const err = page?.props?.flash?.error;
                if (err) {
                    setAlertaEnvio({ abierto: true, mensaje: err, tipo: 'error' });
                    return;
                }
                setAlertaEnvio({
                    abierto: true,
                    mensaje: page?.props?.flash?.success || 'Consulta de pesaje enviada a CEDIS.',
                    tipo: 'success',
                });
            },
        });
    };

    const solicitarPesaje = async () => {
        setAlertaEnvio({ abierto: false, mensaje: '' });
        if (!data.cliente_id) {
            setAlertaEnvio({ abierto: true, mensaje: 'Seleccione el cliente antes de solicitar el pesaje.' });
            return;
        }
        if (Number(data.total_mercancia || 0) <= 0) {
            setAlertaEnvio({ abierto: true, mensaje: 'Indique el total de mercancía (productos) antes de solicitar el pesaje.' });
            return;
        }
        if (!tienePdfPedido) {
            setAlertaEnvio({ abierto: true, mensaje: 'Adjunte el PDF o una foto del pedido antes de solicitar el pesaje.' });
            return;
        }
        const id = await asegurarPedidoEnBd();
        if (!id) return;
        postSolicitudPesaje(id);
    };

    const solicitarRepesaje = () => {
        if (!idPedidoAcciones || !motivoRepesaje) {
            setAlertaEnvio({ abierto: true, mensaje: 'Seleccione el motivo del re-pesaje (cambio de pedido).' });
            return;
        }
        if (motivoRepesaje === 'anexo_piezas' && !tieneAnexoPiezas) {
            setAlertaEnvio({ abierto: true, mensaje: 'Adjunte el PDF o foto de las piezas adicionales antes de solicitar el re-pesaje.' });
            return;
        }
        router.post(route('control_pedidos.solicitar_repesaje', idPedidoAcciones), { motivo: motivoRepesaje }, {
            ...optsPesaje,
            onSuccess: (page) => {
                const err = page?.props?.flash?.error;
                if (err) {
                    setAlertaEnvio({ abierto: true, mensaje: err, tipo: 'error' });
                    return;
                }
                setAlertaEnvio({
                    abierto: true,
                    mensaje: page?.props?.flash?.success || 'Re-pesaje solicitado a CEDIS.',
                    tipo: 'success',
                });
            },
        });
    };

    const continuarPedido = () => {
        if (!idPedidoAcciones) return;
        router.post(route('control_pedidos.volver_borrador', idPedidoAcciones), {}, {
            ...optsPesaje,
            onSuccess: (page) => {
                const err = page?.props?.flash?.error;
                if (err) {
                    setAlertaEnvio({ abierto: true, mensaje: err, tipo: 'error' });
                    return;
                }
                setAlertaEnvio({
                    abierto: true,
                    mensaje: page?.props?.flash?.success || 'Pedido listo para continuar.',
                    tipo: 'success',
                });
            },
        });
    };

    const docsExistentes = (pedido?.documentos || []).filter((d) => d.tipo === 'comprobante' && !docsEliminar.includes(d.id));
    const camposIncorrectos = Array.isArray(pedido?.campos_incorrectos) ? pedido.campos_incorrectos : [];
    const esCampoIncorrecto = (key) => camposIncorrectos.includes(key);
    const wrapIncorrecto = (key) => (esCampoIncorrecto(key)
        ? 'rounded-xl ring-2 ring-orange-500/70 bg-orange-500/10 p-2'
        : '');
    const etiquetasIncorrectas = Object.fromEntries(
        CAMPOS_ERROR_DATOS.map((c) => [c.id, c.label])
    );

    const cerrarOverlayBorrador = (e) => {
        if (e.target !== e.currentTarget) return;
        if (modalAnidadoAbierto) return;
        if (Date.now() < ignoreOverlayCloseUntil.current) return;
        onClose();
    };

    const modal = abierto ? createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={cerrarOverlayBorrador}>
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

                <div className="gelia-modal-body p-5 md:p-8 space-y-8">
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

                    {/* 1. Tipo + cliente; el resto depende del flujo (pesaje o datos del pedido) */}
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>1. Tipo de pedido y cliente</p>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
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
                                            ? 'Envío: solicite el pesaje a CEDIS; al responder podrá cotizar y completar el pedido.'
                                            : 'Tienda/mostrador: sin pesaje ni datos de guía.'}
                                    </p>
                                ) : (
                                    <p className="text-[10px] font-bold text-amber-600 mt-1.5 m-0">
                                        Seleccione Tipo de pedido (Tienda o Envío) para mostrar el resto del formulario.
                                    </p>
                                )}
                            </div>
                            <div className="relative">
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
                            {tieneTipo && !requiereLogistica && (
                                <div>
                                    <label className={SECCION}>Total mercancía *</label>
                                    <InputMoneda value={data.total_mercancia} onChange={(v) => setData('total_mercancia', v)} className="w-full py-3" />
                                </div>
                            )}
                        </div>
                    </section>

                    {mostrarPesaje && (
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>2. Pesaje CEDIS</p>
                        {pedido?.estatus_envio && LABELS_ESTATUS_ENVIO[pedido.estatus_envio] && (
                            <p className="text-xs font-bold theme-text-muted mb-3 m-0">
                                Estado envío: {LABELS_ESTATUS_ENVIO[pedido.estatus_envio]}
                            </p>
                        )}
                        {pendientePesaje && (
                            <AvisoOperativoPedido label="Esperando CEDIS" tono="warning" icon={Scale} className="mb-4">
                                Consulta de pesaje enviada. Cuando CEDIS responda verá aquí el peso, medidas y detalle físico.
                            </AvisoOperativoPedido>
                        )}
                        {tienePesajeRespondido && !pendientePesaje && puedeContinuarPedido && (
                            <AvisoOperativoPedido label="Pesaje listo" tono="success" icon={Scale} className="mb-4">
                                CEDIS ya respondió. Pulse «Continuar pedido» para desbloquear el resto del formulario (pedidos que aún figuran en pesaje pendiente).
                            </AvisoOperativoPedido>
                        )}
                        {tienePesajeRespondido && !pendientePesaje && !puedeContinuarPedido && (
                            <AvisoOperativoPedido label="Pesaje listo" tono="success" icon={Scale} className="mb-4">
                                CEDIS registró el peso y las cajas. Ya puede cotizar (paquetería y costos) y enviar; el comprobante de pago se solicita después. Si agrega piezas, suba un segundo PDF/foto y solicite re-pesaje.
                            </AvisoOperativoPedido>
                        )}
                        {!tienePesajeRespondido && !pendientePesaje && (
                            <AvisoOperativoPedido label="Paso requerido" tono="info" icon={Scale} className="mb-4">
                                Indique el total, adjunte el PDF o foto del pedido y solicite el pesaje. No se requiere comprobante de pago en este paso.
                            </AvisoOperativoPedido>
                        )}
                        <div className="space-y-4">
                            {!tienePesajeRespondido && !pendientePesaje && (
                                <div className="max-w-md">
                                    <label className={SECCION}>Total mercancía *</label>
                                    <InputMoneda value={data.total_mercancia} onChange={(v) => setData('total_mercancia', v)} className="w-full py-3" />
                                </div>
                            )}
                            {(tienePesajeRespondido || pendientePesaje) && Number(data.total_mercancia || 0) > 0 && (
                                <p className="text-xs font-bold theme-text-muted m-0">
                                    Total mercancía: {formatearMoneda(data.total_mercancia)}
                                </p>
                            )}
                            <div>
                                <label className={SECCION}>PDF o foto del pedido</label>
                                <div className="flex flex-wrap items-center gap-3">
                                    <label className="flex items-center gap-2 px-4 py-3 border theme-border border-dashed rounded-xl cursor-pointer w-fit theme-element theme-text-main">
                                        <FileText className="w-4 h-4 theme-text-muted" />
                                        <span className="text-xs font-black uppercase">
                                            {tienePdfPedido ? 'Reemplazar archivo' : 'Adjuntar PDF o foto'}
                                        </span>
                                        <input type="file" accept="application/pdf,.pdf,image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" className="hidden" onChange={subirPdfPedido} disabled={procesandoPesaje} />
                                    </label>
                                    {tienePdfPedido && (
                                        pdfPedidoDoc?.url
                                            ? (
                                                <button
                                                    type="button"
                                                    onClick={() => setVistaPrevia(pdfPedidoDoc)}
                                                    className="text-xs font-bold underline outline-none"
                                                    style={{ color: 'var(--color-primario)' }}
                                                >
                                                    {labelSoportePedido(pdfPedidoDoc)}
                                                </button>
                                            )
                                            : <span className="text-xs font-bold text-emerald-600">Archivo adjuntado</span>
                                    )}
                                </div>
                            </div>
                            {!tienePesajeRespondido && !pendientePesaje && (
                                <button
                                    type="button"
                                    onClick={solicitarPesaje}
                                    disabled={procesandoPesaje || processing || !data.cliente_id || Number(data.total_mercancia || 0) <= 0}
                                    className={`${BTN_PRIMARY} flex items-center gap-2 outline-none`}
                                >
                                    <Scale className="w-4 h-4" /> Solicitar pesaje a CEDIS
                                </button>
                            )}
                            {!data.cliente_id && !tienePesajeRespondido && !pendientePesaje && (
                                <p className="text-[10px] font-bold text-amber-600 m-0">Seleccione el cliente para poder solicitar el pesaje.</p>
                            )}
                            {data.cliente_id && Number(data.total_mercancia || 0) <= 0 && !tienePesajeRespondido && !pendientePesaje && (
                                <p className="text-[10px] font-bold text-amber-600 m-0">Indique el total de mercancía para poder solicitar el pesaje.</p>
                            )}
                            {tienePesajeRespondido && !pendientePesaje && !pedido?.empacado_at && !puedeContinuarPedido && (
                                <div className="space-y-3 p-3 rounded-xl border theme-border">
                                    <div>
                                        <label className={SECCION}>Piezas adicionales (PDF o foto)</label>
                                        <div className="flex flex-wrap items-center gap-3">
                                            <label className="flex items-center gap-2 px-4 py-3 border theme-border border-dashed rounded-xl cursor-pointer w-fit theme-element theme-text-main">
                                                <ImagePlus className="w-4 h-4 theme-text-muted" />
                                                <span className="text-xs font-black uppercase">
                                                    {tieneAnexoPiezas ? 'Reemplazar anexo' : 'Adjuntar anexo'}
                                                </span>
                                                <input type="file" accept="application/pdf,.pdf,image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" className="hidden" onChange={subirAnexoPiezas} disabled={procesandoPesaje} />
                                            </label>
                                            {anexoPiezasDoc?.url && (
                                                <button
                                                    type="button"
                                                    onClick={() => setVistaPrevia(anexoPiezasDoc)}
                                                    className="text-xs font-bold underline outline-none"
                                                    style={{ color: 'var(--color-primario)' }}
                                                >
                                                    {labelSoportePedido(anexoPiezasDoc)}
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                    <div className="flex flex-wrap items-end gap-3">
                                        <div className="min-w-[200px] flex-1">
                                            <label className={SECCION}>Re-pesaje (cambio de pedido)</label>
                                            <select value={motivoRepesaje} onChange={(e) => setMotivoRepesaje(e.target.value)} className={`${THEME_SELECT} w-full py-3`}>
                                                <option value="">Motivo…</option>
                                                {Object.entries(LABELS_MOTIVO_REPESAJE).map(([k, label]) => (
                                                    <option key={k} value={k}>{label}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <button type="button" onClick={solicitarRepesaje} disabled={procesandoPesaje || !motivoRepesaje} className={`${BTN_SECONDARY} flex items-center gap-2 outline-none`}>
                                            <Scale className="w-4 h-4" /> Solicitar re-pesaje
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>

                        {tienePesajeRespondido && (
                            <div className="mt-6 pt-6 border-t theme-border space-y-4">
                                <p className={SECCION}>Respuesta de pesaje y detalles</p>
                                <SeccionRevisionFisicaPedido
                                    pedido={pedido}
                                    onVerDoc={setVistaPrevia}
                                    titulo="Revisión física CEDIS"
                                    puedeAtender={Boolean(pedido?.puede_mutar)}
                                    puedeCancelar={Boolean(pedido?.puede_cancelar)}
                                />
                                {cajasPesaje.length > 0 ? (
                                    <div className="space-y-3">
                                        <p className="text-[9px] font-black uppercase theme-text-muted m-0">Envíos (pesaje)</p>
                                        {[...cajasPesaje].sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0)).map((c, idx) => (
                                            <div key={c.id || idx} className="p-4 rounded-xl border theme-border theme-element space-y-2">
                                                <p className="text-sm font-black theme-text-main m-0">Envío {idx + 1}</p>
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
                                        ))}
                                    </div>
                                ) : null}
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className={SECCION}>Núm. envíos</label>
                                        <input type="text" readOnly value={data.numero_cajas ?? cajasPesaje.length ?? '—'} className={`${THEME_INPUT} w-full py-3 opacity-60`} />
                                    </div>
                                    <div>
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
                                {puedeContinuarPedido && (
                                    <div className="mt-2 p-4 rounded-xl border-2 border-orange-500/50 bg-orange-500/10 space-y-4">
                                        <p className="text-[10px] font-black uppercase tracking-widest text-orange-600 dark:text-orange-400 m-0">
                                            Siguiente paso
                                        </p>
                                        <p className="text-sm font-bold theme-text-main m-0 leading-snug pb-1">
                                            Continúe el pedido para capturar datos generales, dirección, costos y pagos.
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
                            </div>
                        )}
                    </section>
                    )}

                    {mostrarRestoPedido && (
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>{requiereLogistica ? '3. Datos generales' : '2. Datos generales'}</p>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className={SECCION}>Folio de Pedido *</label>
                                <input
                                    type="text"
                                    value={data.folio_remision}
                                    onChange={(e) => setData('folio_remision', e.target.value)}
                                    placeholder="Número de folio del pedido..."
                                    className={`${THEME_INPUT} w-full py-3`}
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
                                <label className={SECCION}>Fecha</label>
                                <input type="date" value={data.fecha} onChange={(e) => setData('fecha', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                            </div>
                            {requiereLogistica && (
                                <div>
                                    <label className={SECCION}>Total mercancía *</label>
                                    <InputMoneda value={data.total_mercancia} onChange={(v) => setData('total_mercancia', v)} className="w-full py-3" />
                                </div>
                            )}
                            <div>
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
                                                Envío bloqueado hasta completar el resguardo. Peso, cajas y costo se capturan al Completar envío del folio principal.
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
                            {!data.es_resguardo && requiereLogistica && esMunicipioDiferido && (
                                <div className="md:col-span-2 flex items-start gap-2 p-3 rounded-xl border border-amber-500/40 bg-amber-500/10">
                                    <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                                    <p className="text-xs font-bold text-amber-700 dark:text-amber-400 m-0">
                                        Paquetería local/regional: el costo de envío puede anexarse después. Peso, cajas y costo son opcionales al registrar.
                                    </p>
                                </div>
                            )}
                            {!data.es_resguardo && requiereLogistica && data.catalogo_paqueteria_id && !esMunicipioDiferido && (
                                <div className="md:col-span-2 flex items-start gap-2 p-3 rounded-xl border theme-border theme-element">
                                    <p className="text-xs font-bold theme-text-muted m-0">
                                        Envío comercial: capture peso, cajas y costo al registrar el pedido.
                                    </p>
                                </div>
                            )}
                        </div>
                    </section>

                    )}


                    {mostrarLogisticaPostPesaje && (
                    <>
                    {/* Dirección de envío */}
                    <section className={SECCION_WRAP}>
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
                            <p className={`${THEME_LABEL} m-0`}>4. Dirección de envío{guiaCliente ? ' (opcional)' : ''}</p>
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
                        {(msgDireccion || sinDireccionPrincipal) && (
                            <div className="mb-4 p-4 rounded-xl border border-amber-500/40 bg-amber-500/10 flex items-start gap-3">
                                <AlertTriangle className="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
                                <div className="min-w-0 space-y-2">
                                    <p className="text-xs font-bold theme-text-main m-0">
                                        {msgDireccion || 'Este cliente no tiene una dirección principal registrada. Debe registrar los datos de dirección.'}
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
                        <div className={`space-y-4 ${!cotizacionHabilitada ? 'opacity-60 pointer-events-none' : ''} ${(esCampoIncorrecto('domicilio') || esCampoIncorrecto('ciudad_estado') || esCampoIncorrecto('referencia') || esCampoIncorrecto('destinatario') || esCampoIncorrecto('telefono')) ? 'rounded-xl ring-2 ring-orange-500/40 bg-orange-500/5 p-3' : ''}`}>
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
                                                }}
                                            >
                                                <PenLine className="w-3.5 h-3.5" />
                                                Usar dirección manual
                                            </button>
                                        )}
                                    </div>
                                    {(mostrarExcepcion || (puedeManual && direccionesCliente.length === 0)) && (
                                        <div className="space-y-2">
                                            <textarea
                                                placeholder="Domicilio completo (excepción manual)…"
                                                value={data.domicilio_entrega}
                                                onChange={(e) => {
                                                    setData('domicilio_entrega', e.target.value);
                                                    setData('direccion_manual_excepcion', true);
                                                    setData('cliente_direccion_id', '');
                                                }}
                                                className={`${THEME_TEXTAREA} w-full py-3 min-h-[80px]`}
                                            />
                                            <input
                                                type="text"
                                                placeholder="C.P."
                                                value={data.codigo_postal}
                                                onChange={(e) => setData('codigo_postal', e.target.value)}
                                                className={`${THEME_INPUT} w-full py-3`}
                                            />
                                            <input
                                                type="text"
                                                placeholder="Motivo de la excepción (requerido al enviar)"
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
                                    }}
                                >
                                    Usar excepción manual en su lugar
                                </button>
                            )}
                        </div>
                    </section>

                    {/* 5. Envío y costos (logística) */}
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>5. Envío y costos</p>
                        <div className="space-y-4">
                            <label className={`flex items-center gap-2 theme-text-main ${logisticaBloqueada ? 'opacity-50' : 'cursor-pointer'} p-4 rounded-xl border theme-border theme-element`}>
                                <input
                                    type="checkbox"
                                    checked={guiaCliente}
                                    disabled={logisticaBloqueada}
                                    onChange={(e) => marcarGuiaCliente(e.target.checked)}
                                />
                                <span className="text-sm font-bold">El cliente utilizará su propia guía.</span>
                            </label>
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
                                <div>
                                    <label className={SECCION}>Paquetería{guiaCliente ? ' (opcional)' : ''}</label>
                                    <div className={wrapIncorrecto('paqueteria')}>
                                    <select value={data.catalogo_paqueteria_id} disabled={logisticaBloqueada} onChange={(e) => manejarPaqueteria(e.target.value)} className={`${THEME_SELECT} w-full py-3 ${logisticaBloqueada ? 'opacity-50' : ''}`}>
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
                                </div>
                                {!guiaCliente && !tieneCoberturaSeguro && data.catalogo_paqueteria_id && (
                                    <div id="seg-warn" className="md:col-span-2 flex items-start gap-2 p-3 rounded-xl border border-amber-500/40 bg-amber-500/10">
                                        <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                                        <p className="text-xs font-bold text-amber-700 dark:text-amber-400 m-0">
                                            Este transporte no cuenta con cobertura de seguro.
                                        </p>
                                    </div>
                                )}
                                {!guiaCliente && !envioPorCobrar && (
                                <div>
                                    <label className={SECCION}>{labelCostoEnvio}{esMunicipioDiferido || omiteCostoPorTarifaPeso ? ' (opcional)' : ''}{camposEnvioBloqueados ? ' (bloqueado)' : ''}</label>
                                    <InputMoneda value={camposEnvioBloqueados ? '' : data.costo_envio} onChange={(v) => setData('costo_envio', v)} className={`w-full py-3 ${camposEnvioBloqueados ? 'opacity-50 pointer-events-none' : ''}`} placeholder="" />
                                    {omiteCostoPorTarifaPeso && (
                                        <p className="text-[10px] theme-text-muted font-bold mt-1 m-0">
                                            Se calculará con el pesaje CEDIS según la tarifa por peso de la paquetería.
                                        </p>
                                    )}
                                </div>
                                )}
                                {!guiaCliente && (
                                <div className={wrapIncorrecto('tipo_guia')}>
                                    <label className={SECCION}>Tipo de guía</label>
                                    <select value={data.catalogo_tipo_guia_id} disabled={logisticaBloqueada || !cotizacionHabilitada} onChange={(e) => setData('catalogo_tipo_guia_id', e.target.value)} className={`${THEME_SELECT} w-full py-3 ${logisticaBloqueada || !cotizacionHabilitada ? 'opacity-50' : ''}`}>
                                        <option value="">{cotizacionHabilitada ? 'Seleccionar...' : 'Tras pesaje CEDIS...'}</option>
                                        {(catalogos.tipos_guia || []).map((g) => <option key={g.id} value={g.id}>{g.nombre}</option>)}
                                    </select>
                                </div>
                                )}
                                {!guiaCliente && (
                                <div>
                                    <label className={SECCION}>Reexpedición</label>
                                    <select value={data.catalogo_zona_id} disabled={logisticaBloqueada} onChange={(e) => setData('catalogo_zona_id', e.target.value)} className={`${THEME_SELECT} w-full py-3 ${logisticaBloqueada ? 'opacity-50' : ''}`}>
                                        <option value="">Seleccionar...</option>
                                        {(catalogos.zonas || []).map((z) => <option key={z.id} value={z.id}>{z.nombre}</option>)}
                                    </select>
                                </div>
                                )}
                            </div>

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
                                        <span className="text-xs text-emerald-600 font-semibold">
                                            Disp. {formatearMoneda(safCuenta.disponible)}
                                        </span>
                                    )}
                                    {cargandoSaf && <span className="text-xs theme-text-muted">Consultando…</span>}
                                    <span className="text-[10px] theme-text-muted font-bold">
                                        Solo saldos del mismo almacén/área. Un saldo generado aquí aplica a partir del siguiente pedido.
                                    </span>
                                </label>
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

                            {(data.aplica_saldo_favor || (!guiaCliente && data.aplica_seguro) || data.envia_a_otra_persona || (!guiaCliente && tieneCoberturaSeguro)) && (
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    {data.aplica_saldo_favor && (
                                        <div className="md:col-span-2 space-y-2">
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
                                            <div className="text-sm font-bold text-emerald-700">Total saldo: {formatearMoneda(saldoFavorCalculado)}</div>
                                        </div>
                                    )}
                                    {!guiaCliente && tieneCoberturaSeguro && (
                                        <div>
                                            <label className={SECCION}>Costo de seguro (calculado)</label>
                                            <InputMoneda value={data.costo_seguro} onChange={() => {}} readOnly className="w-full py-3 opacity-80" />
                                        </div>
                                    )}
                                    {data.envia_a_otra_persona && (
                                        <div className={`md:col-span-2 ${wrapIncorrecto('destinatario')}`}>
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

                    {mostrarPagosYCierre && (
                    <>
                    {/* Pagos y comentarios */}
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>{requiereLogistica ? '6. Pagos y comentarios' : '3. Pagos y comentarios'}</p>
                        <div className="space-y-4">
                            <SeccionPagosExhibicion
                                pedidoId={idPedidoAcciones}
                                bancos={catalogos.bancos || []}
                                formasPago={catalogos.formas_pago || []}
                                puedeRegistrar={Boolean(idPedidoAcciones) && cotizacionLista}
                                puedeGenerarSaldo={false}
                                totalMercancia={data.total_mercancia}
                                costoEnvio={guiaCliente ? 0 : data.costo_envio}
                                aplicaSeguro={Boolean(data.aplica_seguro)}
                                costoSeguro={data.costo_seguro}
                                saldoAFavorAplicado={data.aplica_saldo_favor ? saldoFavorCalculado : 0}
                                onResumenChange={(r) => setPagoResumen(r)}
                                mensajeBloqueo={!cotizacionLista
                                    ? (requiereLogistica && !tienePesajeRespondido && !esResguardoComplementario
                                        ? 'Complete el pesaje CEDIS y la cotización antes de registrar pagos.'
                                        : 'Complete la cotización (paquetería y costos) antes de registrar pagos.')
                                    : null}
                            />
                            {docsExistentes.length > 0 && (
                                <div>
                                    <label className={SECCION}>Comprobantes anteriores (solo consulta)</label>
                                    <p className="text-[10px] theme-text-muted font-bold mb-3 -mt-1">
                                        Los nuevos comprobantes se adjuntan en cada exhibición de pago.
                                    </p>
                                    <div className="flex flex-wrap gap-3 mt-3">
                                        {docsExistentes.map((doc) => (
                                            <div key={doc.id} className="relative w-20 h-20 rounded-xl overflow-hidden border theme-border">
                                                <img src={doc.url} alt={doc.nombre_original} className="w-full h-full object-cover" />
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                            <div>
                                <label className={SECCION}>Comentarios para Drive / Almacén</label>
                                <textarea placeholder="Notas adicionales..." value={data.comentarios_drive} onChange={(e) => setData('comentarios_drive', e.target.value)} className={`${THEME_TEXTAREA} w-full py-3 min-h-[80px]`} />
                            </div>
                            <div>
                                <label className={SECCION}>Nota de compra en el envío</label>
                                <select
                                    value={data.anexar_remision ? '1' : '0'}
                                    disabled={logisticaBloqueada}
                                    onChange={(e) => setData('anexar_remision', e.target.value === '1')}
                                    className={`${THEME_SELECT} w-full py-3 max-w-xs ${logisticaBloqueada ? 'opacity-50' : ''}`}
                                >
                                    <option value="0">NO</option>
                                    <option value="1">SÍ</option>
                                </select>
                            </div>
                        </div>
                    </section>

                    {/* Desglose de montos */}
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>{requiereLogistica ? '7. Desglose de montos' : '4. Desglose de montos'}</p>
                        <div className="space-y-2 text-sm">
                            <div className="flex justify-between theme-text-muted font-bold"><span>Total de mercancía</span><span>{formatearMoneda(data.total_mercancia)}</span></div>
                            <div className="flex justify-between theme-text-muted font-bold"><span>{labelCostoEnvio}</span><span>{formatearMoneda(guiaCliente || envioPorCobrar ? 0 : data.costo_envio)}</span></div>
                            <div className="flex justify-between theme-text-muted font-bold">
                                <span>Costo del seguro</span>
                                <span>{data.aplica_seguro ? formatearMoneda(data.costo_seguro) : formatearMoneda(0)}</span>
                            </div>
                            <div className="flex justify-between theme-text-muted font-bold">
                                <span>Total a cubrir</span>
                                <span>{formatearMoneda(resumenCoberturaVivo.total_a_cubrir)}</span>
                            </div>
                            <div className="flex justify-between text-emerald-600 font-bold">
                                <span>Saldo a favor aplicado</span>
                                <span>- {formatearMoneda(data.aplica_saldo_favor ? saldoFavorCalculado : 0)}</span>
                            </div>
                            {resumenCoberturaVivo.excedente_generado > 0.01 && (
                                <div className="flex justify-between font-bold" style={{ color: '#3B82F6' }}>
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
                    </section>
                    </>
                    )}

                </div>
                    <section className="gelia-modal-footer flex flex-col gap-3 p-5 md:p-6 shrink-0">
                        {mostrarEnviarPedido && !enviarPedidoListo && validacionEnvio.mensaje ? (
                            <p className="text-xs font-bold text-amber-700 dark:text-amber-400 m-0 leading-snug">
                                {validacionEnvio.mensaje}
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
                            setAlertaEnvio({ abierto: false, mensaje: '' });
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
            <ModalAlertaPedido
                abierto={Boolean(abierto) && alertaEnvio.abierto}
                tipo={alertaEnvio.tipo || 'error'}
                titulo={alertaEnvio.tipo === 'success' ? 'Listo' : 'Campos incompletos'}
                mensaje={alertaEnvio.mensaje}
                onClose={() => {
                    ignoreOverlayCloseUntil.current = Date.now() + 400;
                    setAlertaEnvio({ abierto: false, mensaje: '', tipo: 'error' });
                }}
            />
            <ModalVistaPreviaDocumento
                abierto={Boolean(abierto) && Boolean(vistaPrevia)}
                documento={vistaPrevia}
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
