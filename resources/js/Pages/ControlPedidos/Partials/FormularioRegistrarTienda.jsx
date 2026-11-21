import FormularioRegistrarMunicipal from '../Municipal/FormularioRegistrarMunicipal';
import FormularioRegistrarNacional from '../Nacional/FormularioRegistrarNacional';
import SeguimientoSolicitudMunicipal from '../Municipal/SeguimientoSolicitudMunicipal';
import React, { useEffect, useId, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { ExpandIcon } from '@animateicons/react/lucide/expand-icon';
import { FileUpIcon } from '@animateicons/react/lucide/file-up-icon';
import { MapPinIcon } from '@animateicons/react/lucide/map-pin-icon';
import { Trash2Icon } from '@animateicons/react/lucide/trash-2-icon';
import {
    ArrowRight,
    Building2,
    Check,
    ChevronDown,
    ClipboardList,
    FileText,
    Hash,
    Package,
    Search,
    Store,
    Truck,
    UserRound,
    X,
} from 'lucide-react';
import CampoBusqueda from '../../../Components/CampoBusqueda';
import usePedidoDialog from './usePedidoDialog';
import {
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_INPUT,
    THEME_LABEL,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_SELECT,
    GELIA_BTN_OUTLINE,
    GELIA_ICON_BOX,
    GELIA_MODAL_TITLE,
} from '../../../utils/geliaTheme';
import ModalVistaPreviaDocumento from './ModalVistaPreviaDocumento';
import VisorPdfPaginas from './VisorPdfPaginas';
import { BotonBorrador } from './FormularioProgresivo/EncabezadoPedido';
import { archivosImagenDesdeClipboard } from './archivosDesdeClipboard';
import { claseEtiquetaLista } from '../../Solicitudes/Partials/solicitudesStyles';
import { GELIA_ESTADO_VIVO_TONO } from '../../../utils/geliaTheme';
import ContinuidadTienda from './ContinuidadTienda';
import ConfirmacionRevisionTienda from './ConfirmacionRevisionTienda';
import SeguimientoTransferenciaTienda from './SeguimientoTransferenciaTienda';
import { badgesListaPedido } from './pedidosListadoUi';
import useActualizacionesPedidos from './useActualizacionesPedidos';
import SeguimientoSolicitudTienda, { respuestaDeTiendaEmitida } from './SeguimientoSolicitudTienda';
import { fusionarLecturaMunicipal } from '../Municipal/vigenciaConsultaMunicipal';
import { lecturaConsultaVigente } from './vigenciaConsultaPedido';
import PanelConversionModalidad from './PanelConversionModalidad';
import AvisoOperativoPedido from './AvisoOperativoPedido';
import {
    DESTINOS_P0,
    armarFormularioConversion,
    clienteHttpConversion,
    flagsConversionDe,
    fusionarPedidoConversion,
    mensajeErrorConversion,
} from './conversionModalidad';

const MENSAJE_ARCHIVO = 'Adjunta el PDF o la imagen del pedido.';
const MENSAJE_AREA = 'Elige el destino de la revisión.';
const MENSAJE_ALMACEN = 'Elige la sucursal o el almacén de tienda.';
const MENSAJE_SIN_ALMACEN = 'No hay un almacén de tienda para recibir el apartado.';
const MENSAJE_MODALIDAD = 'Este pedido ya tiene una instrucción, un pago o una remisión. La modalidad se conserva.';
const ETIQUETA_AREA = {
    preparacion_tienda: 'Revisar y apartar en tienda',
    control_pedidos: 'Consultar disponibilidad en CEDIS',
};

const DESTINOS_TIENDA = [
    {
        id: 'revisar_apartar',
        icono: Store,
        area: 'preparacion_tienda',
        titulo: 'Revisar y apartar en tienda',
        detalle: 'La sucursal revisa el archivo y aparta lo aceptado. No confirma pago ni entrega.',
    },
    {
        id: 'consultar_cedis',
        icono: Search,
        area: 'control_pedidos',
        titulo: 'Consultar disponibilidad en CEDIS',
        detalle: 'Control Pedidos responde la disponibilidad. Enviar la consulta no registra un traslado.',
    },
];

function casoDe(solicitud, conservar = false) {
    const id = solicitud?.caso_tienda || '';
    if (id === 'mismo_dia_pdv') return id;
    if (conservar && id) return id;
    if (id === 'revisar_apartar' || id === 'apartado_pdv') return 'revisar_apartar';
    if (id === 'consultar_cedis' || id === 'consulta_cedis' || id === 'traspaso_cedis') return 'consultar_cedis';
    const area = areaDe(solicitud);
    if (area === 'preparacion_tienda') return 'revisar_apartar';
    if (area === 'control_pedidos') return 'consultar_cedis';
    return '';
}

const CODIGO_ENVIO = {
    municipal: 'ENVIO_MUNICIPIO',
    nacional: 'ENVIO_BODEGA_NORMAL',
};

const OPCIONES_MODALIDAD = [
    ['tienda', 'Tienda'],
    ['municipal', 'Envío municipal'],
    ['nacional', 'Envío nacional'],
];

function cabeceras() {
    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
}

function mensajeDe(error, reserva) {
    if (error?.response?.status === 419) return 'La sesión cambió o venció. Recargue GELIA antes de volver a guardar; no se confirmó la operación.';
    const datos = error?.response?.data;
    const lista = datos?.errors ? Object.values(datos.errors).flat() : [];
    return lista[0] || datos?.message || reserva;
}

function soporteDe(pedido) {
    return (
        (pedido?.documentos || []).find(
            (documento) =>
                ['pdf_pedido', 'cotizacion'].includes(documento?.tipo) && documento?.url && documento?.activo !== false,
        ) || null
    );
}

function modalidadDe(solicitud) {
    if (['tienda', 'municipal', 'nacional'].includes(solicitud?.modalidad_entrega)) {
        return solicitud.modalidad_entrega;
    }
    const codigo = solicitud?.codigo_modalidad;
    if (['RECOGE_TIENDA', 'RECOGE_TIENDA_TRANSFERENCIA', 'RECOGE_TIENDA_CREDITO'].includes(codigo)) return 'tienda';
    if (codigo === 'ENVIO_MUNICIPIO') return 'municipal';
    if (String(codigo || '').startsWith('ENVIO_BODEGA')) return 'nacional';
    return '';
}

function capturaAvanzada(pedido) {
    if (!pedido) return false;
    if (pedido.pago_validado_at || pedido.numero_remision || pedido.tarea_preparacion) return true;
    if ((pedido.documentos || []).some((documento) => documento?.tipo === 'remision' && documento?.activo !== false))
        return true;
    return (pedido.pagos_exhibicion || []).some((pago) => pago?.activo_para_cobertura);
}

function esImagen(documento) {
    const mime = String(documento?.mime_type || '');
    const nombre = String(documento?.nombre_original || '');
    return mime.startsWith('image/') || /\.(png|jpe?g|webp)$/i.test(nombre);
}

function areaDe(solicitud) {
    if (solicitud?.caso_tienda === 'mismo_dia_pdv') return '';
    return ['preparacion_tienda', 'control_pedidos'].includes(solicitud?.area_revision) ? solicitud.area_revision : '';
}

function enviadaDe(pedido, solicitud) {
    const area = areaDe(solicitud);
    const tarea = Boolean(pedido?.tarea_preparacion?.id);
    if (area === 'preparacion_tienda' && tarea) return true;
    return area === 'control_pedidos' && pedido?.estatus_envio === 'pendiente_pesaje' && !tarea;
}

function receptorDe(area, almacenId, almacenes) {
    if (area === 'control_pedidos') return { definido: true, motivo: '' };
    if (area !== 'preparacion_tienda') return { definido: false, motivo: MENSAJE_AREA };
    if (almacenes.length === 0) return { definido: false, motivo: MENSAJE_SIN_ALMACEN };
    if (almacenes.length === 1) {
        return { definido: true, motivo: '', id: almacenes[0].id, nombre: almacenes[0].nombre };
    }
    const elegido = almacenes.find((almacen) => String(almacen.id) === String(almacenId));
    if (!elegido) return { definido: false, motivo: MENSAJE_ALMACEN };
    return { definido: true, motivo: '', id: elegido.id, nombre: elegido.nombre };
}

function esperaDe(area, nombre) {
    if (area === 'preparacion_tienda') {
        const lugar = nombre ? ` (${nombre})` : '';
        return `Enviado a Preparación Tienda${lugar}. Se espera la revisión del archivo. Aún no hay apartado ni disponibilidad confirmada.`;
    }
    if (area === 'control_pedidos') {
        return 'Enviado a Control Pedidos. Se espera la consulta de disponibilidad. Aún no hay disponibilidad confirmada, traslado, pago ni entrega.';
    }
    return '';
}

function canonMonto(valor) {
    if (valor === '' || valor === null || valor === undefined) return null;
    const numero = Number(valor);
    if (Number.isNaN(numero)) return null;
    return Math.round(numero * 100) / 100;
}

function mismoMonto(a, b) {
    return canonMonto(a) === canonMonto(b);
}

export function importesListosCaptura({ cobertura, totalMercancia, saldoCapturado, confirmados, conflicto, estadoGuardado }) {
    const persistidos = mismoMonto(totalMercancia, confirmados?.total_mercancia)
        && mismoMonto(saldoCapturado, confirmados?.saldo_a_favor)
        && canonMonto(totalMercancia) !== null
        && !conflicto
        && !['guardando', 'pendiente', 'error', 'conflicto'].includes(estadoGuardado);
    if (cobertura !== 'credito') return persistidos;
    const monto = canonMonto(totalMercancia);
    return monto !== null && monto > 0 && !conflicto && estadoGuardado !== 'conflicto';
}

function aplicacionesDe(pedido) {
    return (pedido?.saf_aplicaciones || [])
        .filter((fila) => fila && fila.estado !== 'liberado' && fila.saf_credito_id)
        .map((fila) => ({ saf_credito_id: fila.saf_credito_id, monto: Number(fila.monto) }));
}

function saldoDeDatos(datos) {
    if (!datos?.aplicaSaf) return 0;
    const filas = datos.safAplicaciones || [];
    if (filas.length) return filas.reduce((suma, fila) => suma + Number(fila.monto || 0), 0);
    return canonMonto(datos.saldoFavor) ?? 0;
}

function VistaPreviaConversion({ activo, pedidoId, instanciaId, captura, onFlags, onError }) {
    const huella = JSON.stringify(captura || {});
    useEffect(() => {
        if (!activo || !pedidoId || !captura?.catalogo_paqueteria_id || !captura?.consignatario_id) return undefined;
        const timer = window.setTimeout(async () => {
            try {
                const { data } = await clienteHttpConversion().post(`/control-pedidos/${pedidoId}/conversion-modalidad/vista-previa`, {
                    destino: 'municipal',
                    instancia_id: instanciaId,
                    ...captura,
                }, { headers: { Accept: 'application/json' } });
                onFlags?.(flagsConversionDe(data));
            } catch (error) {
                if (error?.response?.status !== 404) {
                    onError?.(mensajeErrorConversion(error, 'No se pudo calcular el cargo de transporte.'));
                }
            }
        }, 400);
        return () => window.clearTimeout(timer);
    }, [activo, pedidoId, instanciaId, huella]);
    return null;
}

function motivoSolicitud({ modalidad, tieneCliente, tieneDepartamento, tieneFolio, tieneArchivo, receptor, enviada }) {
    if (modalidad !== 'tienda' || enviada) return '';
    if (!tieneCliente) return 'Indica el número de cliente.';
    if (!tieneDepartamento) return 'Indica el departamento.';
    if (!tieneFolio) return 'Indica el folio del pedido.';
    if (!tieneArchivo) return MENSAJE_ARCHIVO;
    if (!receptor.definido) return receptor.motivo || MENSAJE_AREA;
    return '';
}

export default function FormularioRegistrarTienda({
    abierto,
    onClose,
    pedido: pedidoInicial = null,
    catalogos = {},
    registrarTienda = {},
    onPedidoCreado = null,
    onActualizar = null,
    intencion = 'alta',
    reservaConversion = null,
}) {
    const conversion = intencion === 'conversion';
    const conversionId = useId();
    const { auth } = usePage().props;
    const [pedido, setPedido] = useState(pedidoInicial);
    const pedidoVivoRef = useRef(pedido); pedidoVivoRef.current = pedido;
    useEffect(() => {
        setPedido(actual => {
            if (actual?.id === pedidoInicial?.id) {
                if (actual?.version_consulta != null && pedidoInicial?.version_consulta != null && !lecturaConsultaVigente(actual, pedidoInicial)) return actual;
                const anterior = actual?.solicitud_preparacion || {};
                const entrante = pedidoInicial?.solicitud_preparacion || {};
                const conteo = s => (s.anexo_sin_remision?.length || 0) + (s.retiro_piezas?.length || 0) + Object.keys(s.acciones_disponibilidad || {}).length;
                if (conteo(anterior) > conteo(entrante)) return actual;
                if (actual?.updated_at && pedidoInicial?.updated_at && new Date(actual.updated_at) > new Date(pedidoInicial.updated_at)) return actual;
            }
            return pedidoInicial;
        });
    }, [pedidoInicial]);
    const solicitud = pedido?.solicitud_preparacion || {};
    const departamentosUsuario = [
        ...new Set([auth?.user?.departamento_id, ...(auth?.user?.departamento_ids || [])].filter(Boolean).map(String)),
    ];
    const superAdmin = auth?.user?.roles?.includes('Super Admin');
    const departamentos = (catalogos.departamentos || []).filter(
        (departamento) => superAdmin || departamentosUsuario.includes(String(departamento.id)),
    );
    const principalId = String(auth?.user?.departamento_id || '');
    const guardadoId = String(pedido?.departamento_operacion_id || '');
    const facultadDepartamento = superAdmin || departamentosUsuario.length > 1;
    const necesidadDepartamento = !principalId || (guardadoId !== '' && guardadoId !== principalId);
    const puedeCorregirDepartamento = facultadDepartamento && necesidadDepartamento;
    const departamentoInicial =
        guardadoId || principalId || (departamentosUsuario.length === 1 ? departamentosUsuario[0] : '');
    const soporteInicial = soporteDe(pedido);
    const almacenes = registrarTienda.almacenes_tienda || [];
    const enviadaInicial = enviadaDe(pedido, solicitud);
    const casoInicial = casoDe(solicitud, enviadaInicial || capturaAvanzada(pedido));
    const casoMetaInicial = DESTINOS_TIENDA.find((caso) => caso.id === casoInicial);
    const areaInicial =
        casoInicial === 'mismo_dia_pdv' ? '' : areaDe(solicitud) || casoMetaInicial?.area || '';
    const almacenInicial =
        areaInicial === 'preparacion_tienda' && !solicitud.almacen_revision_id && almacenes.length === 1
            ? String(almacenes[0].id)
            : solicitud.almacen_revision_id
              ? String(solicitud.almacen_revision_id)
              : '';
    const [clienteId, setClienteId] = useState(pedido?.cliente_id || '');
    const [numeroCliente, setNumeroCliente] = useState(pedido?.cliente?.numero_cliente || pedido?.numero_cliente || '');
    const [nombreCliente, setNombreCliente] = useState(pedido?.cliente?.nombre || '');
    const [listaCliente, setListaCliente] = useState(
        pedido?.cliente?.lista_descuento?.nombre || pedido?.cliente?.lista_actual || '',
    );
    const [coincidencias, setCoincidencias] = useState([]);
    const [departamentoId, setDepartamentoId] = useState(departamentoInicial);
    const [folio, setFolio] = useState(pedido?.folio_remision || '');
    const [modalidad, setModalidad] = useState(modalidadDe(solicitud));
    const [area, setArea] = useState(areaInicial);
    const [caso, setCaso] = useState(casoInicial);
    const [almacenId, setAlmacenId] = useState(almacenInicial);
    const [sucursalMd, setSucursalMd] = useState(String(pedido?.sucursal_destino_id || ''));
    const [enviada, setEnviada] = useState(enviadaInicial);
    const [espera, setEspera] = useState(
        enviadaInicial
            ? esperaDe(
                  areaInicial,
                  almacenes.find((almacen) => String(almacen.id) === almacenInicial)?.nombre ||
                      pedido?.tarea_preparacion?.almacen?.nombre ||
                      '',
              )
            : '',
    );
    const [transito, setTransito] = useState('');
    const [documento, setDocumento] = useState(soporteInicial);
    const [estadoArchivo, setEstadoArchivo] = useState(soporteInicial ? 'listo' : 'vacio');
    const [errorArchivo, setErrorArchivo] = useState('');
    const [ampliado, setAmpliado] = useState(false);
    const [pedidoId, setPedidoId] = useState(pedido?.id || null);
    const [procesosConfirmacionListos, setProcesosConfirmacionListos] = useState(false);
    const [consultaCerrada, setConsultaCerrada] = useState(Boolean(pedido?.consulta_cerrada || pedido?.consulta_cerrada_at));
    const [totalMercancia, setTotalMercancia] = useState(
        pedido?.total_mercancia === null || pedido?.total_mercancia === undefined ? '' : pedido.total_mercancia,
    );
    const [aplicaSaf, setAplicaSaf] = useState(Number(pedido?.saldo_a_favor || 0) > 0);
    const [saldoFavor, setSaldoFavor] = useState(Number(pedido?.saldo_a_favor || 0) > 0 ? pedido.saldo_a_favor : '');
    const [safAplicaciones, setSafAplicaciones] = useState(() => aplicacionesDe(pedido));
    const [safTocado, setSafTocado] = useState(false);
    const [lecturaOperativaLista, setLecturaOperativaLista] = useState(!pedidoInicial?.id);
    const [revisionLecturaOperativa, setRevisionLecturaOperativa] = useState(0);
    const [solicitudViva, setSolicitudViva] = useState(solicitud);
    const solicitudVivaRef = useRef(solicitud); solicitudVivaRef.current = solicitudViva;
    const [confirmados, setConfirmados] = useState({
        total_mercancia: pedido?.total_mercancia ?? null,
        saldo_a_favor: pedido?.saldo_a_favor ?? 0,
        costo_envio: pedido?.costo_envio ?? null,
        aplica_seguro: Boolean(pedido?.aplica_seguro),
        costo_seguro: pedido?.costo_seguro ?? null,
        revision_registro: pedido?.revision_registro ?? 0,
    });
    const [base, setBase] = useState({
        revision_esperada: pedido?.revision_registro ?? 0,
        base_captura: pedido?.base_captura || null,
    });
    const pedidoIdRef = useRef(pedido?.id || null);
    const baseRef = useRef(base);
    const datosRef = useRef({});
    const deseadaRef = useRef(modalidadDe(solicitud));
    const colaRef = useRef(Promise.resolve());
    const suspenderRef = useRef(false);
    const cicloRef = useRef(0);
    const huellaRef = useRef('');
    const [aviso, setAviso] = useState('');
    const [conflicto, setConflicto] = useState('');
    const [errorCampo, setErrorCampo] = useState('');
    const [guardando, setGuardando] = useState(false);
    const [operandoMunicipal, setOperandoMunicipal] = useState(false);
    const [enviando, setEnviando] = useState(false);
    const [destinoAccion, setDestinoAccion] = useState(null);
    const [avisoConfirmacion, setAvisoConfirmacion] = useState('');
    const [borradorConfirmado, setBorradorConfirmado] = useState(false);
    const [estadoGuardado, setEstadoGuardado] = useState(null);
    const estadoGuardadoRef = useRef(null);
    estadoGuardadoRef.current = estadoGuardado;
    const clave = useRef(globalThis.crypto?.randomUUID?.() || `tienda-${Date.now()}`);
    const archivoRef = useRef(null);
    const archivoPendienteRef = useRef(null);
    const documentoRef = useRef(documento);
    documentoRef.current = documento;
    const [archivoPendiente, setArchivoPendiente] = useState(false);
    const [motivoConversion, setMotivoConversion] = useState('');
    const [errorCapturaConversion, setErrorCapturaConversion] = useState(false);
    const [archivosConversion, setArchivosConversion] = useState([]);
    const [previewsConversion, setPreviewsConversion] = useState([]);
    const [vistaConversion, setVistaConversion] = useState(null);
    const [confirmarRetiroSoporteNacional, setConfirmarRetiroSoporteNacional] = useState(false);
    const [nacionalSucia, setNacionalSucia] = useState(false);
    const [confirmarSalidaNacional, setConfirmarSalidaNacional] = useState(false);
    const [operandoNacional, setOperandoNacional] = useState(false);
    const [confirmarSalidaConversion, setConfirmarSalidaConversion] = useState(false);
    const [resultadoConversion, setResultadoConversion] = useState(null);
    const [ocupadoConversion, setOcupadoConversion] = useState(false);
    const capturaMunicipalRef = useRef({ captura: null, motivoEnvio: '', sucia: false });
    const avanzado = capturaAvanzada(pedido);
    const flagsConversion = reservaConversion?.flags;
    const destinosConversion = flagsConversion?.destinos?.length ? flagsConversion.destinos : DESTINOS_P0;
    const receptor = receptorDe(area, almacenId, almacenes);
    const motivo = useMemo(
        () =>
            motivoSolicitud({
                modalidad,
                tieneCliente: Boolean(clienteId),
                tieneDepartamento: Boolean(departamentoId),
                tieneFolio: Boolean(folio.trim()),
                tieneArchivo: Boolean(documento?.id && documento?.url),
                receptor,
                enviada,
            }) || (caso === 'mismo_dia_pdv' && !sucursalMd ? 'Elige la sucursal de destino para Mismo día.' : ''),
        [modalidad, clienteId, departamentoId, folio, documento, receptor, enviada, caso, sucursalMd],
    );

    const departamentoNombre =
        (catalogos.departamentos || []).find((departamento) => String(departamento.id) === String(departamentoId))
            ?.nombre || '';

    const buscarCliente = async (valor) => {
        setNumeroCliente(valor);
        setClienteId('');
        setNombreCliente('');
        if (valor.trim().length < 2) {
            setCoincidencias([]);
            return;
        }
        try {
            const respuesta = await axios.get('/api/clientes', { params: { q: valor.trim() } });
            setCoincidencias(Array.isArray(respuesta.data) ? respuesta.data : []);
        } catch {
            setCoincidencias([]);
        }
    };

    const elegirCliente = (cliente) => {
        setClienteId(cliente.id);
        setNumeroCliente(String(cliente.numero_cliente || ''));
        setNombreCliente(cliente.nombre || '');
        setListaCliente(cliente.lista_actual || cliente.lista_descuento?.nombre || '');
        setCoincidencias([]);
    };

    const confirmadosRef = useRef(confirmados);
    confirmadosRef.current = confirmados;
    datosRef.current = {
        clienteId, numeroCliente, departamentoId, folio, modalidad, area, caso, almacenId, sucursalMd,
        totalMercancia, aplicaSaf, saldoFavor, safAplicaciones, safTocado,
    };

    const encolar = (trabajo) => {
        const siguiente = colaRef.current.then(trabajo, trabajo);
        colaRef.current = siguiente.then(
            () => undefined,
            () => undefined,
        );
        return siguiente;
    };

    const huellaActual = () => JSON.stringify(datosRef.current);

    const payload = (solicitudPreparacion = null) => {
        const datos = datosRef.current;
        const solicitudCaptura =
            solicitudPreparacion ||
            (datos.modalidad
                ? {
                      modalidad_entrega: datos.modalidad,
                      ...(datos.modalidad === 'tienda' && datos.caso ? { caso_tienda: datos.caso } : {}),
                      ...(datos.modalidad === 'tienda' && datos.area ? { area_revision: datos.area } : {}),
                      ...(datos.modalidad === 'nacional' ? { captura_nacional: 'compartida', codigo_modalidad: CODIGO_ENVIO.nacional } : {}),
                      ...(datos.modalidad === 'tienda' && !enviada ? { atencion_tienda: datos.caso === 'mismo_dia_pdv' ? 'prevista' : null } : {}),
                      ...(datos.modalidad === 'tienda' && !enviada && datos.caso === 'mismo_dia_pdv' ? { codigo_modalidad: 'RECOGE_TIENDA_TRANSFERENCIA' } : {}),
                      ...(datos.modalidad === 'tienda'
                          ? {
                                almacen_revision_id:
                                    datos.area === 'preparacion_tienda' && datos.almacenId
                                        ? Number(datos.almacenId)
                                        : null,
                            }
                          : {}),
                  }
                : {});
        const cuerpo = {
            pedido_id: pedidoIdRef.current || undefined,
            cliente_id: datos.clienteId || null,
            numero_cliente: datos.numeroCliente || null,
            departamento_operacion_id: datos.departamentoId || null,
            folio_remision: datos.folio || null,
            solicitud_preparacion: solicitudCaptura,
            captura_clave: pedidoIdRef.current ? undefined : clave.current,
            ...baseRef.current,
        };
        const mercancia = datos.totalMercancia;
        if (!enviada && datos.modalidad === 'tienda') cuerpo.sucursal_destino_id = datos.sucursalMd ? Number(datos.sucursalMd) : null;
        if (mercancia !== '' && mercancia !== null && mercancia !== undefined) {
            cuerpo.total_mercancia = mercancia;
        }
        if (datos.safTocado) {
            const filas = (datos.safAplicaciones || [])
                .filter((fila) => fila.saf_credito_id && Number(fila.monto) > 0)
                .map((fila) => ({ saf_credito_id: fila.saf_credito_id, monto: Number(fila.monto) }));
            cuerpo.aplica_saldo_favor = filas.length > 0;
            cuerpo.saldo_a_favor = filas.reduce((suma, fila) => suma + Number(fila.monto), 0);
            cuerpo.saf_aplicaciones = filas;
        }
        return cuerpo;
    };

    const aplicarBase = (lectura) => {
        if (lectura.revision_registro == null || !lectura.base_captura) return;
        const actual = baseRef.current;
        if (lectura.revision_registro === actual.revision_esperada && lectura.base_captura === actual.base_captura) return;
        const siguiente = { revision_esperada: lectura.revision_registro, base_captura: lectura.base_captura };
        baseRef.current = siguiente;
        setBase(siguiente);
    };

    const aplicarRespuesta = (respuesta) => {
        if (!respuesta) return;
        if (respuesta.id) {
            pedidoIdRef.current = respuesta.id;
            setPedidoId(respuesta.id);
        }
        aplicarBase(respuesta);
        if (respuesta.total_mercancia !== undefined || respuesta.saldo_a_favor !== undefined) {
            setConfirmados((actual) => ({
                ...actual,
                ...(respuesta.total_mercancia !== undefined ? { total_mercancia: respuesta.total_mercancia } : {}),
                ...(respuesta.saldo_a_favor !== undefined ? { saldo_a_favor: respuesta.saldo_a_favor } : {}),
                ...(respuesta.costo_envio !== undefined ? { costo_envio: respuesta.costo_envio } : {}),
                ...(respuesta.aplica_seguro !== undefined ? { aplica_seguro: respuesta.aplica_seguro } : {}),
                ...(respuesta.costo_seguro !== undefined ? { costo_seguro: respuesta.costo_seguro } : {}),
                ...(respuesta.revision_registro != null ? { revision_registro: respuesta.revision_registro } : {}),
            }));
        }
        setPedido(actual => ({ ...actual, ...respuesta }));
        if (respuesta.solicitud_preparacion) setSolicitudViva(respuesta.solicitud_preparacion);
        if (respuesta.soporte_pedido?.url) setDocumento(respuesta.soporte_pedido);
        if (respuesta.id) onPedidoCreado?.({ id: respuesta.id, folio: respuesta.folio });
        setConflicto('');
    };

    const ejecutarGuardar = async (cuerpo = null, silencioso = false) => {
        if (!silencioso) {
            setGuardando(true);
            setAviso('');
        }
        setErrorCampo('');
        setEstadoGuardado('guardando');
        const ciclo = cicloRef.current;
        const huellaEnviada = huellaActual();
        try {
            const respuesta = await axios.post('/control-pedidos/autoguardar', cuerpo || payload(), {
                headers: cabeceras(),
            });
            if (ciclo !== cicloRef.current) return null;
            aplicarRespuesta(respuesta.data);
            huellaRef.current = huellaEnviada;
            setEstadoGuardado(huellaActual() === huellaEnviada ? 'guardado' : 'pendiente');
            if (!silencioso) setBorradorConfirmado(true);
            return respuesta.data;
        } catch (error) {
            if (ciclo !== cicloRef.current) return null;
            const estado = error.response?.status;
            const datos = error.response?.data;
            if (estado === 409) {
                setConflicto(datos?.message || 'Otra persona guardó este pedido.');
                setEstadoGuardado('conflicto');
            } else {
                setErrorCampo(mensajeDe(error, 'No se pudo guardar el borrador.'));
                setEstadoGuardado('error');
            }
            return null;
        } finally {
            if (!silencioso) setGuardando(false);
        }
    };

    const guardar = (cuerpo = null, silencioso = false) => encolar(() => ejecutarGuardar(
        typeof cuerpo === 'function' ? cuerpo() : cuerpo, silencioso,
    ));
    const guardarNacional = (extra) => guardar(() => {
        const { piezas_nacionales: piezas = [], guardar_mercancia_nacional: guardarMercancia, ...campos } = extra;
        const datos = { ...payload(), ...campos };
        if (!guardarMercancia) return datos;

        const cuerpo = new FormData();
        const adjuntar = (llave, valor) => {
            if (valor === undefined) return;
            if (valor === null) { cuerpo.append(llave, ''); return; }
            if (valor instanceof File) cuerpo.append(llave, valor);
            else if (typeof valor === 'object') Object.entries(valor).forEach(([clave, dato]) => adjuntar(`${llave}[${clave}]`, dato));
            else cuerpo.append(llave, typeof valor === 'boolean' ? Number(valor) : valor);
        };
        Object.entries(datos).forEach(([llave, valor]) => adjuntar(llave, valor));
        cuerpo.append('guardar_mercancia_nacional', '1');
        piezas.forEach(({ id, sku, descripcion, cantidad, evidencia }, i) => {
            adjuntar(`piezas_nacionales[${i}]`, { id, sku, descripcion, cantidad, evidencia });
        });
        return cuerpo;
    });

    const ejecutarSubirArchivo = async (file) => {
        suspenderRef.current = true;
        setEstadoArchivo('cargando');
        setErrorArchivo('');
        setEstadoGuardado('guardando');
        try {
            let id = pedidoIdRef.current;
            if (!id || huellaActual() !== huellaRef.current) {
                const guardado = await ejecutarGuardar(null, true);
                id = guardado?.id || null;
            }
            if (!id) {
                archivoPendienteRef.current = null;
                setArchivoPendiente(false);
                setEstadoArchivo(documentoRef.current?.url ? 'listo' : 'error');
                setEstadoGuardado('error');
                if (!documentoRef.current?.url) setErrorArchivo('No se pudo preparar el borrador para adjuntar el archivo.');
                return null;
            }
            const cuerpo = new FormData();
            cuerpo.append('pdf_pedido', file);
            cuerpo.append('tipo_soporte', 'pdf_pedido');
            const { data } = await axios.post(route('control_pedidos.pdf_pedido.store', id), cuerpo, {
                headers: cabeceras(),
            });
            if (!data?.documento?.url) {
                archivoPendienteRef.current = null;
                setArchivoPendiente(false);
                setEstadoArchivo(documentoRef.current?.url ? 'listo' : 'error');
                setEstadoGuardado('error');
                setErrorArchivo('No se pudo adjuntar el PDF o la imagen.');
                return null;
            }
            aplicarRespuesta(data);
            huellaRef.current = huellaActual();
            setDocumento(data.documento);
            documentoRef.current = data.documento;
            archivoPendienteRef.current = null;
            setArchivoPendiente(false);
            setEstadoArchivo('listo');
            setErrorArchivo('');
            setEstadoGuardado('guardado');
            return data.documento;
        } catch (error) {
            archivoPendienteRef.current = null;
            setArchivoPendiente(false);
            setEstadoArchivo(documentoRef.current?.url ? 'listo' : 'error');
            setEstadoGuardado('error');
            setErrorArchivo(mensajeDe(error, 'No se pudo adjuntar el PDF o la imagen.'));
            return null;
        } finally {
            suspenderRef.current = false;
        }
    };

    const subirArchivo = (file) => {
        if (!file || enviando) return;
        setConfirmarRetiroSoporteNacional(false);
        archivoPendienteRef.current = file;
        setArchivoPendiente(true);
        return encolar(() => ejecutarSubirArchivo(file));
    };

    const elegirArchivo = (evento) => {
        const file = evento.target.files?.[0];
        evento.target.value = '';
        subirArchivo(file);
    };

    const pegarArchivo = (evento) => {
        if (modalidad === 'municipal' && solicitud.consulta_municipal?.enviada_at) return;
        if (modalidad === 'nacional' && (pedido?.pesaje_solicitado_at || evento.defaultPrevented)) return;
        const pegado = archivosImagenDesdeClipboard(evento.clipboardData);
        if (!pegado.length) return;
        evento.preventDefault();
        const imagen = pegado[0];
        subirArchivo(imagen);
    };

    const confirmarCapturaComun = () => encolar(async () => {
        const respuesta = await ejecutarGuardar(null, true);
        if (respuesta?.id) return respuesta;
        return pedidoIdRef.current ? { id: pedidoIdRef.current } : null;
    });

    const asegurarDocumentoMunicipal = () => encolar(async () => {
        if (documentoRef.current?.id && documentoRef.current?.url && !archivoPendienteRef.current) {
            return documentoRef.current;
        }
        const file = archivoPendienteRef.current;
        if (!file) return documentoRef.current;
        return ejecutarSubirArchivo(file);
    });

    const retirarArchivo = async () => {
        setConfirmarRetiroSoporteNacional(false);
        archivoPendienteRef.current = null;
        setArchivoPendiente(false);
        if (!documento?.id || !pedidoIdRef.current) {
            setDocumento(null);
            setEstadoArchivo('vacio');
            setErrorArchivo('');
            return;
        }
        setEstadoArchivo('cargando');
        setErrorArchivo('');
        try {
            const { data } = await axios.put(
                route('control_pedidos.update', pedidoIdRef.current),
                {
                    documentos_eliminar: [documento.id],
                    revision_esperada: baseRef.current.revision_esperada,
                    base_captura: baseRef.current.base_captura,
                },
                { headers: cabeceras() },
            );
            aplicarRespuesta(data);
            setDocumento(null);
            setEstadoArchivo('vacio');
        } catch (error) {
            setEstadoArchivo('listo');
            setErrorArchivo(mensajeDe(error, 'No se pudo retirar el archivo.'));
        }
    };

    const solicitar = () =>
        encolar(async () => {
            if (motivo || enviada || estadoArchivo === 'cargando') return;
            suspenderRef.current = true;
            cicloRef.current += 1;
            setEnviando(true);
            setAviso('');
            setErrorCampo('');
            try {
                const guardado = await ejecutarGuardar(null, true);
                if (!guardado?.id) return;
                const { data } = await axios.post(
                    `/control-pedidos/${guardado.id}/solicitud-tienda`,
                    {
                        area_revision: datosRef.current.area,
                        almacen_id:
                            datosRef.current.area === 'preparacion_tienda' ? Number(datosRef.current.almacenId) : null,
                        revision_esperada: baseRef.current.revision_esperada,
                        base_captura: baseRef.current.base_captura,
                    },
                    { headers: cabeceras() },
                );
                aplicarRespuesta(data);
                huellaRef.current = huellaActual();
                setEnviada(true);
                setEspera(data.espera || esperaDe(data.area_revision, data.almacen_revision));
                setEstadoGuardado('guardado');
                setAviso(data.espera || '');
            } catch (error) {
                setEstadoGuardado(error.response?.status === 409 ? 'conflicto' : 'error');
                setErrorCampo(mensajeDe(error, 'No se pudo enviar la solicitud.'));
            } finally {
                suspenderRef.current = false;
                setEnviando(false);
            }
        });

    const cambiarModalidad = (valor) => {
        if (!valor) return;
        if (conversion) {
            const destino = destinosConversion.find((item) => item.codigo === valor);
            if (!destino?.habilitado) return;
            deseadaRef.current = valor;
            setTransito('');
            setModalidad(valor);
            if (valor !== 'tienda') setArea('');
            return;
        }
        if (avanzado) {
            setAviso(MENSAJE_MODALIDAD);
            return;
        }
        if (modalidad === 'nacional' && pedido?.pesaje_solicitado_at && valor !== 'nacional') {
            setAviso('La consulta a CEDIS ya se envió. La modalidad se conserva.');
            return;
        }
        deseadaRef.current = valor;
        setTransito('');
        setModalidad(valor);
        if (valor !== 'tienda') setArea('');
    };

    useEffect(() => {
        setConsultaCerrada(Boolean(pedido?.consulta_cerrada || pedido?.consulta_cerrada_at));
    }, [pedido?.id, pedido?.consulta_cerrada, pedido?.consulta_cerrada_at]);

    useEffect(() => {
        if (conversion) return undefined;
        if (!abierto || (modalidad === 'municipal' && solicitud.consulta_municipal?.enviada_at)) return undefined;
        const actual = huellaActual();
        if (!huellaRef.current) {
            huellaRef.current = actual;
            return undefined;
        }
        if (actual === huellaRef.current) return undefined;
        setEstadoGuardado((estado) => (estado === 'guardando' ? estado : 'pendiente'));
        const timer = window.setTimeout(() => {
            if (suspenderRef.current || huellaActual() === huellaRef.current) return;
            const datos = datosRef.current;
            const hayContenido = Boolean(
                pedidoIdRef.current ||
                datos.clienteId ||
                String(datos.folio || '').trim(),
            );
            if (!hayContenido) return;
            if (huellaActual() !== huellaRef.current) setBorradorConfirmado(false);
            encolar(async () => {
                if (suspenderRef.current || huellaActual() === huellaRef.current) return;
                await ejecutarGuardar(null, true);
            });
        }, 700);
        return () => window.clearTimeout(timer);
    }, [abierto, clienteId, numeroCliente, departamentoId, folio, modalidad, area, caso, almacenId, sucursalMd, totalMercancia, aplicaSaf, saldoFavor, safAplicaciones, safTocado]);

    const suciaConversion = conversion && !resultadoConversion && Boolean(
        motivoConversion || archivosConversion.length || errorCapturaConversion
        || capturaMunicipalRef.current.sucia || (modalidad && modalidad !== modalidadDe(solicitud)),
    );
    const intentarCerrar = () => {
        if (ocupadoConversion || vistaConversion) return;
        if (suciaConversion && !confirmarSalidaConversion) {
            setConfirmarSalidaConversion(true);
            return;
        }
        onClose?.();
    };
    const cerrarNacional = () => {
        if (vistaConversion || operandoNacional) return;
        if (nacionalSucia) setConfirmarSalidaNacional(true);
        else onClose?.();
    };
    const dialog = usePedidoDialog({
        abierto,
        onClose: conversion ? intentarCerrar : modalidad === 'nacional' ? cerrarNacional : onClose,
        bloqueado: guardando || enviando || operandoMunicipal || operandoNacional || estadoArchivo === 'cargando' || ocupadoConversion || Boolean(vistaConversion),
        cerrarConEscape: !vistaConversion && !ocupadoConversion,
    });

    const refrescarRef = useRef(null);
    const consultaMunicipalActiva = modalidad === 'municipal'
        && Boolean(solicitudViva?.consulta_municipal?.enviada_at);
    const adoptarConsultaMunicipal = (lectura) => {
        const actual = pedidoVivoRef.current;
        const siguiente = fusionarLecturaMunicipal(actual, lectura);
        if (siguiente === actual) return;
        pedidoVivoRef.current = siguiente;
        setPedido(siguiente);
        if (siguiente.solicitud_preparacion) setSolicitudViva(siguiente.solicitud_preparacion);
        setConsultaCerrada(Boolean(siguiente.consulta_cerrada || siguiente.consulta_cerrada_at));
    };
    useActualizacionesPedidos({
        activo: abierto && Boolean(pedidoId),
        pedidoId,
        intervaloRespaldo: 15000,
        respaldoAunqueSuscrito: true,
        pausado: consultaMunicipalActiva
            ? (guardando || enviando || operandoMunicipal || estadoGuardado === 'guardando')
            : (guardando || enviando || operandoNacional || nacionalSucia || ['pendiente', 'guardando', 'error', 'conflicto'].includes(estadoGuardado)),
        onActualizar: () => refrescarRef.current?.(),
        onPendiente: () => setAviso('El pedido cambió. Se conserva su captura mientras termina de guardar.'),
    });
    useEffect(() => {
        if (!abierto || !pedidoId || resultadoConversion) return;
        let vigente = true;
        const baseAlLeer = baseRef.current;
        setLecturaOperativaLista(false);
        const lectura = deseadaRef.current === 'nacional' ? 'consulta-nacional' : 'disponibilidad-tienda';
        axios.get(`/control-pedidos/${pedidoId}/${lectura}`, { headers: cabeceras() }).then(({ data }) => {
            if (vigente && data?.id === pedidoId) {
                setLecturaOperativaLista(true);
                if (baseRef.current !== baseAlLeer) return;
                if (Number(data.revision_registro) === Number(baseAlLeer.revision_esperada)) aplicarBase(data);
                if (modalidad === 'municipal') adoptarConsultaMunicipal(data);
                else if (lectura === 'consulta-nacional') {
                    aplicarBase(data);
                    setPedido((actual) => ({ ...actual, ...data }));
                    if (data.solicitud_preparacion) setSolicitudViva(data.solicitud_preparacion);
                } else {
                    setPedido(data);
                    if (data.solicitud_preparacion) setSolicitudViva(data.solicitud_preparacion);
                    setConsultaCerrada(Boolean(data.consulta_cerrada || data.consulta_cerrada_at));
                }
            }
        }).catch(() => { if (vigente) setAviso('No se pudo leer el estado guardado. Actualice antes de solicitar otro ajuste.'); });
        return () => { vigente = false; };
    }, [abierto, pedidoId, revisionLecturaOperativa]);
    if (!abierto) return null;

    const nombreAlmacenVisible = almacenes.find((almacen) => String(almacen.id) === String(almacenId))?.nombre || '';
    const importesListos = importesListosCaptura({
        cobertura: solicitudViva?.cobertura_tienda?.tipo,
        totalMercancia,
        saldoCapturado: saldoDeDatos(datosRef.current),
        confirmados,
        conflicto,
        estadoGuardado,
    });
    const asegurarGuardado = () => encolar(async () => {
        if (suspenderRef.current || estadoGuardadoRef.current === 'conflicto') return false;
        if (huellaActual() !== huellaRef.current || estadoGuardadoRef.current === 'error') {
            const respuesta = await ejecutarGuardar(null, true);
            if (!respuesta) return false;
        }
        return huellaActual() === huellaRef.current;
    });
    const bancosContexto = (catalogos.bancos || []).filter((banco) => (
        banco.compartido !== false
        || (banco.departamento_ids || []).some((id) => String(id) === String(departamentoId))
    ));
    const adoptarLectura = (lectura, operacionLocal = false) => {
        if (!lectura) return;
        if (lectura.version_consulta != null && !lecturaConsultaVigente(pedidoVivoRef.current, lectura)) return;
        if (lectura.revision_registro != null && Number(lectura.revision_registro) < Number(baseRef.current.revision_esperada)) return;
        if (operacionLocal && lectura.revision_registro != null && Number(lectura.revision_registro) !== Number(baseRef.current.revision_esperada)) {
            setConflicto('El pedido cambió en otra sesión. Conserve esta captura y revise los datos guardados.');
            setEstadoGuardado('conflicto');
            return;
        }
        setPedido((actual) => ({ ...actual, ...lectura }));
        if (lectura.consulta_cerrada !== undefined) setConsultaCerrada(Boolean(lectura.consulta_cerrada));
        if (lectura.solicitud_preparacion) setSolicitudViva(lectura.solicitud_preparacion);
        const limpio = mismoMonto(datosRef.current.totalMercancia, confirmadosRef.current.total_mercancia)
            && mismoMonto(saldoDeDatos(datosRef.current), confirmadosRef.current.saldo_a_favor);
        if (!limpio) {
            if (operacionLocal) {
                aplicarBase(lectura);
            } else if (lectura.revision_registro != null && Number(lectura.revision_registro) !== Number(confirmadosRef.current.revision_registro)) {
                setConflicto('Los importes guardados cambiaron. Conserve esta captura y actualice los datos antes de registrar el pago.');
            }
            return;
        }
        setConfirmados((actual) => ({
            ...actual,
            ...(lectura.total_mercancia !== undefined ? { total_mercancia: lectura.total_mercancia } : {}),
            ...(lectura.saldo_a_favor !== undefined ? { saldo_a_favor: lectura.saldo_a_favor } : {}),
            ...(lectura.costo_envio !== undefined ? { costo_envio: lectura.costo_envio } : {}),
            ...(lectura.aplica_seguro !== undefined ? { aplica_seguro: lectura.aplica_seguro } : {}),
            ...(lectura.costo_seguro !== undefined ? { costo_seguro: lectura.costo_seguro } : {}),
            ...(lectura.revision_registro != null ? { revision_registro: lectura.revision_registro } : {}),
        }));
        aplicarBase(lectura);
    };
    refrescarRef.current = async () => {
        const baseAlLeer = baseRef.current;
        try {
            if (modalidad === 'nacional') {
                const { data } = await axios.get(`/control-pedidos/${pedidoId}/consulta-nacional`, { headers: cabeceras() });
                if (baseRef.current === baseAlLeer) adoptarLectura(data);
                return data;
            }
            if (consultaMunicipalActiva) {
                const { data } = await axios.get(`/control-pedidos/${pedidoId}/disponibilidad-tienda`, { headers: cabeceras() });
                if (baseRef.current !== baseAlLeer) return;
                adoptarConsultaMunicipal(data);
                return data;
            }
            const { data } = await axios.get(route('control_pedidos.pagos.resumen', pedidoId), { headers: { Accept: 'application/json' } });
            if (baseRef.current === baseAlLeer) adoptarLectura(data);
        } catch {
            setAviso('No se pudo actualizar el pedido. Se conserva su captura y se volverá a intentar.');
            return false;
        }
    };
    const actualizarSolicitud = (valor, versionConsulta = null) => {
        const siguiente = typeof valor === 'function' ? valor(solicitudVivaRef.current) : valor;
        if (!siguiente || typeof siguiente !== 'object') return;
        solicitudVivaRef.current = siguiente;
        setSolicitudViva(siguiente);
        // La versión de la respuesta deja por debajo cualquier lectura iniciada antes de este anexo.
        setPedido((actual) => ({
            ...actual,
            solicitud_preparacion: siguiente,
            ...(versionConsulta != null && Number(versionConsulta) > Number(actual?.version_consulta ?? 0) ? { version_consulta: Number(versionConsulta) } : {}),
        }));
        const pendientes = (siguiente?.retiro_piezas || []).some((fila) => (fila.estado || 'pendiente') === 'pendiente')
            || (siguiente?.anexo_sin_remision || []).some((fila) => !fila.respondida_at);
        if (pendientes) setConsultaCerrada(false);
        refrescarRef.current?.();
    };
    const revisionSolicitada = enviada || avanzado || respuestaDeTiendaEmitida(pedido)
        || Boolean(pedido?.estatus?.fase_ciclo && !['BORRADOR', 'RECHAZADO_VENDEDORA'].includes(pedido.estatus.fase_ciclo));
    const enRevisionAuxiliar = pedido?.estatus?.fase_ciclo === 'PENDIENTE_AUXILIAR';
    const campo = `${THEME_INPUT} w-full min-h-[44px] text-base`;
    const opcionesDepartamento = [...departamentos];
    if (
        departamentoId &&
        !opcionesDepartamento.some((departamento) => String(departamento.id) === String(departamentoId))
    ) {
        const actual = (catalogos.departamentos || []).find(
            (departamento) => String(departamento.id) === String(departamentoId),
        );
        if (actual) opcionesDepartamento.unshift(actual);
    }

    const confirmarConversion = async () => {
        if (!conversion || ocupadoConversion || resultadoConversion) return;
        if (modalidad !== 'municipal') {
            reservaConversion?.setError?.('Seleccione un destino habilitado.');
            return;
        }
        const captura = capturaMunicipalRef.current.captura || {};
        if (capturaMunicipalRef.current.motivoEnvio) {
            reservaConversion?.setError?.(capturaMunicipalRef.current.motivoEnvio);
            return;
        }
        if (!captura.catalogo_paqueteria_id || !captura.consignatario_id) {
            reservaConversion?.setError?.('Complete la terminal y el consignatario.');
            return;
        }
        if (!motivoConversion.trim() && !errorCapturaConversion) {
            reservaConversion?.setError?.('Registre el motivo o notifique un error de captura.');
            return;
        }
        if (!errorCapturaConversion && !archivosConversion.length) {
            reservaConversion?.setError?.('Adjunte la petición del cliente o notifique un error de captura.');
            return;
        }
        setOcupadoConversion(true);
        reservaConversion?.setError?.('');
        const form = armarFormularioConversion({
            instanciaId: reservaConversion?.instanciaId?.(),
            motivo: motivoConversion,
            errorCaptura: errorCapturaConversion,
            revisionEsperada: pedido?.revision_registro,
            conversionVersion: flagsConversion?.version,
            claveIdempotencia: reservaConversion?.claveIdempotencia?.(),
            captura,
            evidencias: archivosConversion,
        });
        try {
            const { data } = await clienteHttpConversion().post(`/control-pedidos/${pedidoId}/conversion-modalidad`, form, { headers: { Accept: 'application/json' } });
            reservaConversion?.marcarConfirmada?.();
            const fusionado = fusionarPedidoConversion(pedido, data);
            setResultadoConversion(data);
            setPedido(fusionado);
            if (fusionado.solicitud_preparacion) setSolicitudViva(fusionado.solicitud_preparacion);
            onPedidoCreado?.(fusionado);
            onActualizar?.();
        } catch (error) {
            reservaConversion?.setError?.(mensajeErrorConversion(error, 'No se pudo confirmar el cambio de modalidad.'));
        } finally {
            setOcupadoConversion(false);
        }
    };

    const renderFormulario = (municipal = null, nacional = null) => {
        if (conversion && municipal) {
            capturaMunicipalRef.current = {
                captura: municipal.captura,
                motivoEnvio: municipal.motivoEnvio,
                sucia: municipal.sucia,
            };
        }
        const pedidoSeguimiento = resultadoConversion ? fusionarPedidoConversion(pedido, resultadoConversion) : null;
        return createPortal(
        <div className={THEME_MODAL_OVERLAY} data-gelia-modal="1">
            <div
                {...dialog}
                aria-labelledby="registrar-tienda-titulo"
                className={`${THEME_MODAL_SHELL} gelia-pedidos-dialog gelia-pedidos-formulario gelia-captura-inicial registrar-tienda-modal w-full ${nacional ? 'registrar-nacional-modal' : ''}`}
            >
                <header className="registrar-tienda-cabecera">
                    <span className={`${GELIA_ICON_BOX} registrar-tienda-emblema`} aria-hidden="true">
                        <ClipboardList size={24} />
                    </span>
                    <div className="min-w-0">
                        <p className="registrar-tienda-antetitulo">
                            Gestión de pedidos{pedido?.folio ? ` · ${pedido.folio}` : ''}
                        </p>
                        <h2 id="registrar-tienda-titulo" className={GELIA_MODAL_TITLE}>
                            {conversion ? 'Cambiar modalidad' : pedidoInicial?.id ? 'Editar pedido' : 'Registrar pedido'}
                        </h2>
                        <p className="registrar-tienda-descripcion">
                            {conversion
                                ? 'El pedido conserva sus datos. Complete solo lo que el envío municipal necesita.'
                                : modalidad === 'municipal' ? 'Datos del pedido y envío municipal a terminal.'
                                : modalidad === 'nacional' ? 'Datos del pedido y consulta de peso en CEDIS.'
                                : 'Captura el pedido y elige el destino de la revisión.'}
                        </p>
                        {pedido?.estatus && <div className="flex flex-wrap gap-1.5 mt-2" aria-label="Estado del pedido" aria-live="polite">
                            {badgesListaPedido(pedido).slice(0, 2).map((badge) => <span key={badge.key} className={badge.className} style={badge.style}>{badge.label}</span>)}
                        </div>}
                        {(enRevisionAuxiliar || avisoConfirmacion) && <p className="text-sm theme-text-main mt-2 mb-0" role="status">{enRevisionAuxiliar ? 'Pedido enviado a revisión del auxiliar.' : avisoConfirmacion}</p>}
                        {pedido?.motivo_rechazo && <p className="text-sm theme-text-main mt-2 mb-0" role="status">{pedido.motivo_rechazo}</p>}
                    </div>
                    <button
                        type="button"
                        className={`${GELIA_BTN_OUTLINE} registrar-tienda-cerrar`}
                        onClick={conversion ? intentarCerrar : nacional ? cerrarNacional : onClose}
                        disabled={guardando || enviando || operandoMunicipal || operandoNacional || ocupadoConversion}
                        aria-label={conversion ? 'Cerrar cambio de modalidad' : 'Cerrar registro'}
                    >
                        <X size={18} aria-hidden="true" />
                    </button>
                </header>
                <div
                    className="gelia-modal-body registrar-tienda-cuerpo"
                    style={{ scrollPaddingBottom: '4.5rem' }}
                    onPaste={pegarArchivo}
                >
                    {conflicto && (
                        <div className="mb-4 p-3 border theme-border rounded-xl" role="alert">
                            <h3 className="text-base font-bold m-0">Otra persona guardó este pedido</h3>
                            <p className="text-sm m-0 mt-1">{conflicto}</p>
                            <div className="flex flex-col sm:flex-row gap-2 mt-3">
                                <button
                                    type="button"
                                    className={`${THEME_BTN_SECONDARY} min-h-[44px]`}
                                    onClick={() => setConflicto('')}
                                >
                                    Conservar esta captura
                                </button>
                                <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px]`} onClick={onClose}>
                                    Volver a abrir el pedido guardado
                                </button>
                            </div>
                        </div>
                    )}
                    {errorCampo && (
                        <p className="mb-4 text-sm theme-text-main m-0 break-words" role="alert">
                            {errorCampo}
                        </p>
                    )}
                    {avanzado && !conversion && (
                        <p className="mb-4 text-sm theme-text-main m-0" role="status">
                            {MENSAJE_MODALIDAD}
                        </p>
                    )}
                    {conversion && reservaConversion?.error && (
                        <p role="alert" className="mb-4 theme-text-peligro text-sm m-0">{reservaConversion.error}</p>
                    )}
                    {conversion && reservaConversion?.aviso && (
                        <p role="status" className="mb-4 text-sm theme-text-aviso m-0">{reservaConversion.aviso}</p>
                    )}
                    {conversion && !reservaConversion?.reservaOk && !reservaConversion?.error && !resultadoConversion && (
                        <p role="status" className="mb-4 text-sm theme-text-muted m-0">Reservando el cambio…</p>
                    )}
                    {conversion && flagsConversion?.accion_ui === 'continuar' && (
                        <AvisoOperativoPedido tono="info" className="mb-4">Continúa la solicitud de cambio pendiente de conexión. No se crea otra petición.</AvisoOperativoPedido>
                    )}
                    {nacional && nacionalSucia && confirmarSalidaNacional && (
                        <div className="registrar-tienda-panel space-y-2" role="alert">
                            <p className="m-0 text-sm">Hay cambios de mercancía o paquetería pendientes de guardar.</p>
                            <div className="flex flex-wrap gap-2">
                                <button type="button" className={GELIA_BTN_OUTLINE} onClick={() => setConfirmarSalidaNacional(false)}>Seguir editando</button>
                                <button type="button" className={GELIA_BTN_OUTLINE} onClick={onClose}>Descartar cambios</button>
                            </div>
                        </div>
                    )}
                    {confirmarSalidaConversion && (
                        <div className="mb-4 grid gap-4" role="status">
                            <h3 className="text-lg font-bold theme-text-main m-0">¿Descartar lo capturado?</h3>
                            <p className="text-sm theme-text-main m-0">Los datos que aún no se confirman se perderán. El pedido no se modifica.</p>
                            <div className="flex flex-wrap gap-2 justify-end">
                                <button type="button" className={THEME_BTN_SECONDARY} onClick={() => setConfirmarSalidaConversion(false)}>Seguir editando</button>
                                <button type="button" className={THEME_BTN_PRIMARY} onClick={onClose}>Descartar y cerrar</button>
                            </div>
                        </div>
                    )}
                    {resultadoConversion && (
                        <AvisoOperativoPedido tono="success" icon={Check} label="Cambio confirmado" className="mb-4">
                            El pedido {resultadoConversion.folio || pedido.folio} quedó en envío municipal. No se creó un folio nuevo.
                        </AvisoOperativoPedido>
                    )}
                    <section className="registrar-tienda-panel" aria-labelledby="registrar-tienda-datos">
                        <div className="registrar-tienda-seccion-titulo">
                            <UserRound size={18} aria-hidden="true" />
                            <h3 id="registrar-tienda-datos">Datos del pedido</h3>
                        </div>
                        <div className="registrar-tienda-datos">
                            <div className="min-w-0">
                                <label className={THEME_LABEL} htmlFor="cliente-tienda">
                                    Número de cliente
                                </label>
                                <CampoBusqueda
                                    id="cliente-tienda"
                                    disabled={conversion || municipal?.bloqueado || municipal?.guardando}
                                    className={campo}
                                    value={numeroCliente}
                                    onChange={(evento) => buscarCliente(evento.target.value)}
                                    autoComplete="off"
                                    placeholder="Número de cliente…"
                                />
                                {coincidencias.length > 0 && (
                                    <ul className="m-0 mt-1 p-0 list-none border theme-border rounded-xl overflow-hidden">
                                        {coincidencias.slice(0, 6).map((cliente) => (
                                            <li key={cliente.id}>
                                                <button
                                                    type="button"
                                                    className={`${THEME_BTN_SECONDARY} w-full min-h-[44px] rounded-none text-left break-words`}
                                                    onClick={() => elegirCliente(cliente)}
                                                >
                                                    {cliente.numero_cliente} · {cliente.nombre}
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                            <div className="min-w-0">
                                <label className={THEME_LABEL} htmlFor="depto-tienda">
                                    Departamento
                                </label>
                                {puedeCorregirDepartamento ? (
                                    <select
                                        id="depto-tienda"
                                        disabled={conversion || municipal?.bloqueado || municipal?.guardando}
                                        className={`${THEME_SELECT} w-full min-h-[44px] text-base`}
                                        value={String(departamentoId)}
                                        onChange={(evento) => setDepartamentoId(evento.target.value)}
                                    >
                                        <option value="">Elegir</option>
                                        {opcionesDepartamento.map((departamento) => (
                                            <option key={departamento.id} value={String(departamento.id)}>
                                                {departamento.nombre}
                                            </option>
                                        ))}
                                    </select>
                                ) : (
                                    <p id="depto-tienda" className="registrar-tienda-departamento">
                                        <Building2 size={17} aria-hidden="true" />
                                        {departamentoNombre || 'Sin departamento'}
                                    </p>
                                )}
                            </div>
                            <div className="min-w-0">
                                <label className={THEME_LABEL} htmlFor="folio-tienda">
                                    Folio del pedido
                                </label>
                                <div className="theme-field-with-icon">
                                    <Hash className="theme-field-icon" aria-hidden="true" />
                                    <input
                                        id="folio-tienda"
                                        disabled={conversion || municipal?.bloqueado || municipal?.guardando}
                                        className={campo}
                                        value={folio}
                                        onChange={(evento) => setFolio(evento.target.value)}
                                        autoComplete="off"
                                        placeholder="Folio de Wizerp"
                                    />
                                </div>
                            </div>
                            {nacional && (
                                <div className="min-w-0">
                                    <label className={THEME_LABEL} htmlFor="importe-mercancia-nacional">Importe de mercancía</label>
                                    <input id="importe-mercancia-nacional" type="number" min="0.01" step="0.01" inputMode="decimal"
                                        className={campo} value={totalMercancia} disabled={avanzado || operandoNacional}
                                        onChange={(evento) => setTotalMercancia(evento.target.value)}
                                    />
                                </div>
                            )}
                            <div className="min-w-0">
                                <label className={THEME_LABEL} htmlFor="modalidad-tienda">
                                    Modalidad
                                </label>
                                <div className="theme-field-with-icon theme-field-with-icon--has-trailing">
                                    <Truck className="theme-field-icon" aria-hidden="true" />
                                    <select
                                        id="modalidad-tienda"
                                        className={`${THEME_SELECT} w-full min-h-[44px] text-base`}
                                        value={transito || modalidad}
                                        disabled={conversion
                                            ? Boolean(resultadoConversion) || ocupadoConversion || !reservaConversion?.reservaOk
                                            : avanzado || guardando || enviando || municipal?.bloqueado || municipal?.guardando
                                                || (modalidad === 'nacional' && Boolean(pedido?.pesaje_solicitado_at))}
                                        onChange={(evento) => cambiarModalidad(evento.target.value)}
                                    >
                                        <option value="">{conversion ? 'Modalidad actual' : 'Elegir'}</option>
                                        {(conversion ? destinosConversion.map((item) => [item.codigo, item.nombre, !item.habilitado, item.motivo_bloqueo]) : OPCIONES_MODALIDAD.map(([valor, titulo]) => [valor, titulo, false, null])).map(([valor, titulo, bloqueado, motivoBloqueo]) => (
                                            <option key={valor} value={valor} disabled={Boolean(bloqueado)}>
                                                {bloqueado && motivoBloqueo ? `${titulo} · ${motivoBloqueo}` : titulo}
                                            </option>
                                        ))}
                                    </select>
                                    <ChevronDown className="theme-field-with-icon__trailing" aria-hidden="true" />
                                </div>
                            </div>
                            {nombreCliente && (
                                <div className="registrar-tienda-cliente">
                                    <span
                                        className={`${GELIA_ICON_BOX} registrar-tienda-cliente-icono`}
                                        aria-hidden="true"
                                    >
                                        <UserRound size={20} />
                                    </span>
                                    <div className="registrar-tienda-cliente-identidad min-w-0">
                                        <p className="registrar-tienda-antetitulo">Cliente seleccionado</p>
                                        <p className="registrar-tienda-cliente-nombre">{nombreCliente}</p>
                                    </div>
                                    <span className={claseEtiquetaLista(listaCliente || 'Público')}>
                                        {listaCliente || 'Sin lista'}
                                    </span>
                                </div>
                            )}
                        </div>
                    </section>
                    <div className="registrar-tienda-atencion">
                        {(transito || modalidad) === 'tienda' && !conversion && (
                            <section
                                className="registrar-tienda-panel"
                                aria-labelledby="registrar-tienda-atencion-titulo"
                            >
                                <div className="registrar-tienda-seccion-titulo">
                                    <Package size={18} aria-hidden="true" />
                                    <h3 id="registrar-tienda-atencion-titulo">Atención en tienda</h3>
                                </div>
                                <p className="registrar-tienda-ayuda">Elige el destino de esta solicitud.</p>
                                <div className="registrar-tienda-casos">
                                    {DESTINOS_TIENDA.map((opcion) => (
                                        <button
                                            key={opcion.id}
                                            type="button"
                                            className="registrar-tienda-destino"
                                            aria-pressed={caso === opcion.id || (caso === 'mismo_dia_pdv' && opcion.id === 'consultar_cedis')}
                                            disabled={enviada || avanzado || enviando || guardando}
                                            onClick={() => {
                                                setCaso(opcion.id);
                                                setArea(opcion.area);
                                                if (opcion.area === 'preparacion_tienda' && almacenes.length === 1) {
                                                    setAlmacenId(String(almacenes[0].id));
                                                }
                                                if (opcion.area !== 'preparacion_tienda') setAlmacenId('');
                                            }}
                                        >
                                            <span className="registrar-tienda-caso-icono" aria-hidden="true">
                                                <opcion.icono size={19} />
                                            </span>
                                            <span className="registrar-tienda-caso-texto">
                                                <span className="registrar-tienda-caso-titulo">{opcion.titulo}</span>
                                                <span className="registrar-tienda-caso-detalle">{opcion.detalle}</span>
                                            </span>
                                            <span className="registrar-tienda-caso-marca" aria-hidden="true">
                                                {(caso === opcion.id || (caso === 'mismo_dia_pdv' && opcion.id === 'consultar_cedis')) && <Check size={12} />}
                                            </span>
                                        </button>
                                    ))}
                                </div>
                                {modalidad === 'tienda' && area === 'control_pedidos' && !enviada && <label className={`${THEME_LABEL} block mt-3`} htmlFor="sucursal-md-inicial">Sucursal de destino<select id="sucursal-md-inicial" className={`${THEME_SELECT} w-full mt-1`} value={sucursalMd} disabled={enviada || avanzado || enviando || guardando} onChange={evento => setSucursalMd(evento.target.value)}><option value="">Elegir sucursal</option>{(catalogos.sucursales || []).map(sucursal => <option key={sucursal.id} value={String(sucursal.id)}>{sucursal.nombre}</option>)}</select></label>}
                                {area === 'preparacion_tienda' && almacenes.length > 0 && (
                                    <div className="mt-2">
                                        <label className={THEME_LABEL} htmlFor="almacen-tienda">
                                            Sucursal de consulta
                                        </label>
                                        <select
                                            id="almacen-tienda"
                                            className={`${THEME_SELECT} w-full min-h-[44px] text-base`}
                                            value={String(almacenId)}
                                            disabled={enviada || avanzado || enviando || guardando}
                                            onChange={(evento) => setAlmacenId(evento.target.value)}
                                        >
                                            <option value="">Elegir</option>
                                            {almacenes.map((almacen) => (
                                                <option key={almacen.id} value={String(almacen.id)}>
                                                    {almacen.nombre}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                )}
                                {area === 'preparacion_tienda' && almacenes.length === 0 && (
                                    <p className="text-sm theme-text-main mt-2 mb-0" role="status">
                                        {MENSAJE_SIN_ALMACEN}
                                    </p>
                                )}
                            </section>
                        )}
                        <section
                            className={`registrar-tienda-panel registrar-tienda-documento ${nacional ? 'registrar-nacional-documento' : ''}`}
                            aria-labelledby="registrar-tienda-documento-titulo"
                        >
                            <div className="registrar-tienda-seccion-titulo">
                                <FileText size={18} aria-hidden="true" />
                                <h3 id="registrar-tienda-documento-titulo">Documento del pedido</h3>
                            </div>
                            <p className="registrar-tienda-ayuda">PDF, JPG, PNG o WEBP · Hasta 10 MB.</p>
                            <div
                                className={`registrar-tienda-archivo ${documento?.url ? 'registrar-tienda-archivo-adjunto' : ''}`}
                            >
                                {estadoArchivo === 'cargando' && (
                                    <p className="text-sm theme-text-main m-0" role="status">
                                        Cargando
                                    </p>
                                )}
                                {!nacional && !documento?.url && estadoArchivo !== 'cargando' && (
                                    <div className="registrar-tienda-archivo-vacio">
                                        <div
                                            className={`${GELIA_ICON_BOX} registrar-tienda-emblema`}
                                            aria-hidden="true"
                                        >
                                            <FileUpIcon size={26} />
                                        </div>
                                        <p className="text-sm font-semibold theme-text-main m-0">Adjunta el pedido</p>
                                        <p className="text-xs theme-text-muted m-0">
                                            Selecciona un archivo o pega una imagen.
                                        </p>
                                    </div>
                                )}
                                {nacional && documento?.url && estadoArchivo !== 'cargando' && (
                                    <div className="registrar-tienda-archivo-listo w-full text-left">
                                        <div className="flex min-w-0 items-center gap-3">
                                            {esImagen(documento)
                                                ? <img src={documento.url} alt="" className="w-14 h-14 shrink-0 object-contain rounded" />
                                                : <span className={GELIA_ICON_BOX}><FileText size={18} aria-hidden="true" /></span>}
                                            <div className="min-w-0">
                                                <p className="m-0 break-words text-sm theme-text-main" role="status">{documento.nombre_original || 'Archivo del pedido'}</p>
                                                <p className="m-0 text-xs theme-text-muted">{esImagen(documento) ? 'Imagen' : 'PDF'}</p>
                                            </div>
                                        </div>
                                        {!esImagen(documento) && (
                                            <VisorPdfPaginas
                                                url={documento.url}
                                                titulo={documento.nombre_original || 'Pedido'}
                                                maxHeight="14rem"
                                            />
                                        )}
                                    </div>
                                )}
                                {!nacional && documento?.url && estadoArchivo !== 'cargando' && (
                                    <div className="registrar-tienda-archivo-listo w-full text-left">
                                        <p className="text-sm theme-text-main m-0 mb-2 break-words" role="status">
                                            Listo. {documento.nombre_original || 'Archivo del pedido'}
                                        </p>
                                        {esImagen(documento) ? (
                                            <img
                                                src={documento.url}
                                                alt={documento.nombre_original || 'Pedido'}
                                                className="registrar-tienda-vista-previa"
                                            />
                                        ) : (
                                            <VisorPdfPaginas
                                                url={documento.url}
                                                titulo={documento.nombre_original || 'Pedido'}
                                                maxHeight="14rem"
                                            />
                                        )}
                                    </div>
                                )}
                                {errorArchivo && (
                                    <p className="text-sm theme-text-main mt-3 mb-0 break-words" role="alert">
                                        {errorArchivo}
                                    </p>
                                )}
                                <div className="registrar-tienda-archivo-acciones mt-3">
                                    <button
                                        type="button"
                                        className={`${GELIA_BTN_OUTLINE} min-h-[44px]`}
                                        disabled={conversion || estadoArchivo === 'cargando' || municipal?.bloqueado || municipal?.guardando || (nacional && revisionSolicitada)}
                                        onClick={() => archivoRef.current?.click()}
                                    >
                                        <FileUpIcon size={18} aria-hidden="true" />
                                        {documento?.url ? 'Reemplazar' : 'Adjuntar PDF o imagen'}
                                    </button>
                                    {documento?.url && (
                                        <>
                                            <button
                                                type="button"
                                                className={`${GELIA_BTN_OUTLINE} min-h-[44px]`}
                                                onClick={() => setAmpliado(true)}
                                            >
                                                <ExpandIcon size={18} aria-hidden="true" />
                                                Ampliar
                                            </button>
                                            <button
                                                type="button"
                                                className={`${GELIA_BTN_OUTLINE} min-h-[44px]`}
                                                disabled={conversion || estadoArchivo === 'cargando' || municipal?.bloqueado || municipal?.guardando || (nacional && revisionSolicitada)}
                                                onClick={nacional ? () => setConfirmarRetiroSoporteNacional(true) : retirarArchivo}
                                            >
                                                <Trash2Icon size={18} aria-hidden="true" />
                                                Retirar
                                            </button>
                                        </>
                                    )}
                                </div>
                                {nacional && confirmarRetiroSoporteNacional && (
                                    <div className="mt-3 space-y-2" role="alert">
                                        <p className="m-0 text-sm break-words">¿Retirar {documento?.nombre_original || 'el documento del pedido'}?</p>
                                        <div className="flex flex-wrap gap-2">
                                            <button type="button" className={GELIA_BTN_OUTLINE + ' min-h-[44px]'} onClick={() => setConfirmarRetiroSoporteNacional(false)}>Conservar archivo</button>
                                            <button type="button" className={GELIA_BTN_OUTLINE + ' min-h-[44px]'} onClick={retirarArchivo}>Retirar archivo</button>
                                        </div>
                                    </div>
                                )}
                                <input
                                    ref={archivoRef}
                                    type="file"
                                    accept="application/pdf,.pdf,image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                                    className="hidden"
                                    onChange={elegirArchivo}
                                />
                            </div>
                        </section>
                    </div>
                    {nacional?.contenido}
                    {(!conversion || (modalidad === 'municipal' && !resultadoConversion)) && municipal?.contenido}
                    {conversion && modalidad === 'municipal' && !resultadoConversion && (
                        <>
                            <VistaPreviaConversion
                                activo={Boolean(reservaConversion?.reservaOk)}
                                pedidoId={pedidoId}
                                instanciaId={reservaConversion?.instanciaId?.()}
                                captura={municipal?.captura}
                                onFlags={(siguientes) => siguientes && reservaConversion?.setFlags?.(siguientes)}
                                onError={(texto) => reservaConversion?.setError?.(texto)}
                            />
                            <PanelConversionModalidad
                                id={conversionId}
                                flags={flagsConversion || {}}
                                captura={municipal?.captura || {}}
                                motivo={motivoConversion}
                                onMotivo={setMotivoConversion}
                                errorCaptura={errorCapturaConversion}
                                onErrorCaptura={setErrorCapturaConversion}
                                archivos={archivosConversion}
                                previews={previewsConversion}
                                onEvidencias={(archivos, previews) => { setArchivosConversion(archivos); setPreviewsConversion(previews); }}
                                onVer={(documentos, indice) => setVistaConversion({ documentos, indice })}
                                ocupado={ocupadoConversion}
                            />
                        </>
                    )}
                    {resultadoConversion && pedidoSeguimiento && (
                        <SeguimientoSolicitudMunicipal
                            pedido={pedidoSeguimiento}
                            onPedidoActualizado={(lectura) => {
                                const siguiente = { ...pedidoSeguimiento, ...lectura };
                                setPedido(siguiente);
                                onPedidoCreado?.(siguiente);
                                onActualizar?.();
                            }}
                        />
                    )}
                    {modalidad === 'tienda' && (enviada || respuestaDeTiendaEmitida(pedido)) && (
                        <>
                            {!conversion && !lecturaOperativaLista && <div className="flex flex-wrap items-center gap-2 text-sm theme-text-muted" role="status"><span>Consultando los cambios guardados antes de habilitar ajustes.</span><button type="button" className={GELIA_BTN_OUTLINE} onClick={() => setRevisionLecturaOperativa(n => n + 1)}>Actualizar cambios guardados</button></div>}
                            <SeguimientoSolicitudTienda
                                destinoAccion={conversion ? null : destinoAccion}
                                soloLectura={conversion || !lecturaOperativaLista}
                                pedidoId={pedidoId}
                                pedido={{ ...pedido, solicitud_preparacion: solicitudViva }}
                                onSolicitud={actualizarSolicitud}
                                onPedidoActualizado={adoptarLectura}
                                onConsultaReabierta={() => { setConsultaCerrada(false); refrescarRef.current?.(); }}
                                area={area}
                                nombreAlmacen={nombreAlmacenVisible}
                                revisiones={pedido?.revisiones_producto || pedido?.revisionesProducto || []}
                                consultaCerrada={consultaCerrada}
                                onConsultaCerrada={(lectura) => { adoptarLectura(lectura, true); setConsultaCerrada(true); refrescarRef.current?.(); }}
                            />
                            <SeguimientoTransferenciaTienda pedido={{ ...pedido, id: pedidoId, solicitud_preparacion: solicitudViva }} sucursales={catalogos.sucursales || []} onActualizar={() => refrescarRef.current?.()} />
                            {!conversion && <ContinuidadTienda
                                pedidoId={pedidoId}
                                solicitud={solicitudViva}
                                sucursales={catalogos.sucursales || []}
                                notaMismoDia={catalogos.nota_mismo_dia}
                                puedeResolverTraslado={superAdmin || (auth?.user?.permissions || []).includes('control_pedidos.tienda.autorizar_segundo_traslado')}
                                puedeOperarTienda={superAdmin || (auth?.user?.permissions || []).includes('control_pedidos.tienda.responder') || (auth?.user?.permissions || []).includes('control_pedidos.tienda.trasladar')}
                                puedeCedis={superAdmin || (auth?.user?.permissions || []).includes('control_pedidos.cedis')}
                                mostrarCustodia={false}
                                consultaCerrada={consultaCerrada}
                                importes={{
                                    destinoAccion,
                                    lecturaLista: lecturaOperativaLista,
                                    encolarGuardado: encolar,
                                    onEstadoConfirmacion: setProcesosConfirmacionListos,
                                    clienteId,
                                    totalMercancia,
                                    onTotalMercancia: setTotalMercancia,
                                    aplicaSaf,
                                    onAplicaSaf: (valor) => { setSafTocado(true); setAplicaSaf(valor); },
                                    saldoFavor,
                                    onSaldoFavor: (valor) => { setSafTocado(true); setSaldoFavor(valor); },
                                    safAplicaciones,
                                    onSafAplicaciones: (filas) => { setSafTocado(true); setSafAplicaciones(filas); },
                                    costos: {
                                        costo_envio: confirmados.costo_envio,
                                        aplica_seguro: confirmados.aplica_seguro,
                                        costo_seguro: confirmados.costo_seguro,
                                    },
                                    importesListos,
                                    consultaCerrada,
                                    estatusRechazado: pedido?.estatus?.fase_ciclo === 'RECHAZADO_VENDEDORA',
                                    bancos: bancosContexto,
                                    revisionEsperada: confirmados.revision_registro,
                                    totalMercanciaConfirmado: confirmados.total_mercancia,
                                    saldoAFavorConfirmado: confirmados.saldo_a_favor,
                                    conservar: Boolean(solicitudViva?.cobertura_tienda)
                                        || Number(confirmados.saldo_a_favor) > 0
                                        || (Number(canonMonto(confirmados.total_mercancia)) > 0 && !consultaCerrada),
                                    onLectura: adoptarLectura,
                                    onSolicitud: actualizarSolicitud,
                                }}
                            />}
                            {!conversion && <ConfirmacionRevisionTienda procesosListos={lecturaOperativaLista && procesosConfirmacionListos} destinoAccion={destinoAccion} onAviso={setAvisoConfirmacion} antesDeEnviar={asegurarGuardado} pedido={{ ...pedido, id: pedidoId, solicitud_preparacion: solicitudViva }} sucursales={catalogos.sucursales || []} consultaCerrada={consultaCerrada} importesListos={importesListos} onLectura={adoptarLectura} />}
                        </>
                    )}
                    {modalidad === 'tienda' && borradorConfirmado && !enviada && (
                        <p className="text-sm theme-text-muted mt-4 mb-0" role="status">
                            No se creó una tarea.
                        </p>
                    )}
                    {aviso && !enviada && (
                        <p className="text-sm theme-text-main mt-4 mb-0 break-words" role="status">
                            {aviso}
                        </p>
                    )}
                </div>
                <footer className="gelia-modal-footer registrar-tienda-pie">
                    {conversion ? (
                    <div className="registrar-tienda-pie-acciones">
                        {resultadoConversion ? (
                            <button type="button" className={THEME_BTN_PRIMARY} onClick={onClose}>Cerrar</button>
                        ) : (
                            <button
                                type="button"
                                className={`${THEME_BTN_PRIMARY} min-h-[44px]`}
                                disabled={ocupadoConversion || !reservaConversion?.reservaOk || modalidad !== 'municipal'}
                                onClick={confirmarConversion}
                            >
                                <Package size={16} aria-hidden />{ocupadoConversion ? 'Confirmando…' : 'Confirmar cambio de modalidad'}
                            </button>
                        )}
                    </div>
                    ) : municipal ? municipal.pie : <>
                    <div className="registrar-tienda-pie-estado" role="status" aria-live="polite">
                        {estadoGuardado && (
                            <span className="registrar-tienda-guardado">
                                {
                                    {
                                        guardando: 'Guardando…',
                                        guardado: 'Guardado',
                                        pendiente: 'Cambios pendientes',
                                        conflicto: 'Cambio en otra sesión',
                                        error: 'Error al guardar',
                                    }[estadoGuardado]
                                }
                            </span>
                        )}
                        {modalidad === 'tienda' && !revisionSolicitada && motivo && (
                            <p id="registrar-tienda-motivo" className="text-xs theme-text-muted m-0">
                                {motivo}
                            </p>
                        )}
                    </div>
                    <div className="registrar-tienda-pie-acciones">
                        <BotonBorrador
                            guardando={guardando || nacional?.enviando}
                            confirmado={borradorConfirmado && !nacionalSucia}
                            deshabilitado={revisionSolicitada || enviando || estadoArchivo === 'cargando'}
                            onClick={nacional?.guardarBorrador || (() => guardar())}
                        />
                        {modalidad === 'tienda' && !revisionSolicitada && (
                            <button
                                type="button"
                                className={THEME_BTN_PRIMARY}
                                aria-describedby={motivo ? 'registrar-tienda-motivo' : undefined}
                                disabled={avanzado || guardando || enviando || estadoArchivo === 'cargando' || Boolean(motivo)}
                                onClick={solicitar}
                            >
                                {enviando ? 'Enviando…' : ETIQUETA_AREA[area] || 'Solicitar en tienda'}
                                <ArrowRight size={16} aria-hidden="true" />
                            </button>
                        )}
                        {nacional?.accion || <div ref={setDestinoAccion} className="registrar-tienda-accion-principal" />}
                    </div>
                    </>}
                </footer>
            </div>
            <ModalVistaPreviaDocumento
                abierto={ampliado && Boolean(documento?.url)}
                documento={documento}
                onClose={() => setAmpliado(false)}
            />
            <ModalVistaPreviaDocumento
                abierto={Boolean(vistaConversion)}
                documentos={vistaConversion?.documentos || []}
                indice={vistaConversion?.indice || 0}
                onClose={() => setVistaConversion(null)}
            />
        </div>,
        document.body,
    );
    };

    if (conversion) {
        return (
            <FormularioRegistrarMunicipal
                abierto={abierto}
                modo="conversion"
                pedido={{ ...pedido, id: pedidoId, cliente_id: clienteId, solicitud_preparacion: solicitudViva }}
                catalogos={catalogos}
                ocupado={ocupadoConversion}
            >
                {renderFormulario}
            </FormularioRegistrarMunicipal>
        );
    }
    if (modalidad === 'nacional') {
        return (
            <FormularioRegistrarNacional
                pedido={{
                    ...pedido,
                    id: pedidoId,
                    folio_remision: folio,
                    cliente_id: clienteId,
                    departamento_operacion_id: departamentoId,
                    soporte_pedido: documento?.url ? documento : null,
                    solicitud_preparacion: solicitudViva,
                    catalogo_paqueteria_id: pedido?.catalogo_paqueteria_id,
                }}
                catalogos={catalogos}
                guardar={guardarNacional}
                onPedido={aplicarRespuesta}
                onVer={(documentos, indice) => setVistaConversion({ documentos, indice })}
                onDirtyChange={setNacionalSucia}
                onBusyChange={setOperandoNacional}
                bloqueado={estadoArchivo === 'cargando' || guardando}
                importePendiente={canonMonto(totalMercancia) !== null && !mismoMonto(totalMercancia, confirmados.total_mercancia)}
            >
                {(nacional) => renderFormulario(null, nacional)}
            </FormularioRegistrarNacional>
        );
    }
    if (modalidad !== 'municipal') return renderFormulario();
    const tieneArchivoMunicipal = Boolean(documento?.id || archivoPendiente || estadoArchivo === 'cargando');
    const motivoComun = !clienteId ? 'Seleccione el cliente.' : !departamentoId ? 'Indique el departamento.'
        : !folio.trim() ? 'Indique el folio de Wizerp.' : !tieneArchivoMunicipal ? (errorArchivo || MENSAJE_ARCHIVO)
        : estadoGuardado === 'conflicto' ? 'Revise el cambio en otra sesión antes de enviar la consulta.' : '';
    return <FormularioRegistrarMunicipal
        key={String(clienteId)}
        abierto={abierto}
        pedido={{ ...pedido, id: pedidoId, cliente_id: clienteId, solicitud_preparacion: solicitudViva }}
        catalogos={catalogos}
        antesDeGuardar={confirmarCapturaComun}
        asegurarDocumento={asegurarDocumentoMunicipal}
        onOcupado={setOperandoMunicipal}
        motivoComun={motivoComun}
        ocupado={guardando || enviando}
        onPedidoCreado={lectura => { aplicarRespuesta(lectura); onPedidoCreado?.(lectura); }}
        onLectura={adoptarConsultaMunicipal}
    >{renderFormulario}</FormularioRegistrarMunicipal>;
}
