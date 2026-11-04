import React, { useEffect, useMemo, useRef, useState } from 'react';
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
import useActualizacionesPedidos from './useActualizacionesPedidos';
import SeguimientoSolicitudTienda, { respuestaDeTiendaEmitida } from './SeguimientoSolicitudTienda';

const MENSAJE_ARCHIVO = 'Adjunta el PDF o la imagen del pedido.';
const MENSAJE_AREA = 'Elige el destino de la revisión.';
const MENSAJE_ALMACEN = 'Elige la sucursal o el almacén de tienda.';
const MENSAJE_SIN_ALMACEN = 'No hay un almacén de tienda para recibir el apartado.';
const MENSAJE_MODALIDAD = 'Este pedido ya tiene una instrucción, un pago o una remisión. La modalidad se conserva.';
const NOTA_TIENDA_MD =
    'Mismo día (T-02.1) es transferencia, con facultad por departamento o usuario, de lunes a viernes. El servidor acepta el reporte de pago hasta las 13:00. Elegirlo no confirma cobertura, preparación ni entrega. Las piezas ya disponibles en punto de venta y la continuación después del corte no tienen una regla automática.';
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
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
        'X-Requested-With': 'XMLHttpRequest',
    };
}

function mensajeDe(error, reserva) {
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
    onContinuarEnvio,
}) {
    const { auth } = usePage().props;
    const [pedido, setPedido] = useState(pedidoInicial);
    useEffect(() => { setPedido(pedidoInicial); }, [pedidoInicial]);
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
    const [consultaCerrada, setConsultaCerrada] = useState(Boolean(pedido?.consulta_cerrada || pedido?.consulta_cerrada_at));
    const [totalMercancia, setTotalMercancia] = useState(
        pedido?.total_mercancia === null || pedido?.total_mercancia === undefined ? '' : pedido.total_mercancia,
    );
    const [aplicaSaf, setAplicaSaf] = useState(Number(pedido?.saldo_a_favor || 0) > 0);
    const [saldoFavor, setSaldoFavor] = useState(Number(pedido?.saldo_a_favor || 0) > 0 ? pedido.saldo_a_favor : '');
    const [safAplicaciones, setSafAplicaciones] = useState(() => aplicacionesDe(pedido));
    const [safTocado, setSafTocado] = useState(false);
    const [solicitudViva, setSolicitudViva] = useState(solicitud);
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
    const [enviando, setEnviando] = useState(false);
    const [borradorConfirmado, setBorradorConfirmado] = useState(false);
    const [estadoGuardado, setEstadoGuardado] = useState(null);
    const clave = useRef(globalThis.crypto?.randomUUID?.() || `tienda-${Date.now()}`);
    const archivoRef = useRef(null);
    const avanzado = capturaAvanzada(pedido);
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
            }),
        [modalidad, clienteId, departamentoId, folio, documento, receptor, enviada],
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
        clienteId, numeroCliente, departamentoId, folio, modalidad, area, caso, almacenId,
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

    const aplicarRespuesta = (respuesta) => {
        if (!respuesta) return;
        if (respuesta.id) {
            pedidoIdRef.current = respuesta.id;
            setPedidoId(respuesta.id);
        }
        if (respuesta.revision_registro != null && respuesta.base_captura) {
            const siguiente = {
                revision_esperada: respuesta.revision_registro,
                base_captura: respuesta.base_captura,
            };
            baseRef.current = siguiente;
            setBase(siguiente);
        }
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
        try {
            const respuesta = await axios.post('/control-pedidos/autoguardar', cuerpo || payload(), {
                headers: cabeceras(),
            });
            if (ciclo !== cicloRef.current) return null;
            aplicarRespuesta(respuesta.data);
            huellaRef.current = huellaActual();
            setEstadoGuardado('guardado');
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

    const guardar = (cuerpo = null, silencioso = false) => encolar(() => ejecutarGuardar(cuerpo, silencioso));

    const subirArchivo = (file) => {
        if (!file || enviando) return;
        suspenderRef.current = true;
        setEstadoArchivo('cargando');
        setErrorArchivo('');
        setEstadoGuardado('guardando');
        return encolar(async () => {
            try {
                let id = pedidoIdRef.current;
                if (!id) {
                    const guardado = await ejecutarGuardar(null, true);
                    id = guardado?.id || null;
                }
                if (!id) {
                    setEstadoArchivo(documento?.url ? 'listo' : 'error');
                    setEstadoGuardado('error');
                    if (!documento?.url) setErrorArchivo('No se pudo preparar el borrador para adjuntar el archivo.');
                    return;
                }
                const cuerpo = new FormData();
                cuerpo.append('pdf_pedido', file);
                cuerpo.append('tipo_soporte', 'pdf_pedido');
                const { data } = await axios.post(route('control_pedidos.pdf_pedido.store', id), cuerpo, {
                    headers: cabeceras(),
                });
                if (!data?.documento?.url) {
                    setEstadoArchivo(documento?.url ? 'listo' : 'error');
                    setEstadoGuardado('error');
                    setErrorArchivo('No se pudo adjuntar el PDF o la imagen.');
                    return;
                }
                aplicarRespuesta(data);
                huellaRef.current = huellaActual();
                setDocumento(data.documento);
                setEstadoArchivo('listo');
                setErrorArchivo('');
                setEstadoGuardado('guardado');
            } catch (error) {
                setEstadoArchivo(documento?.url ? 'listo' : 'error');
                setEstadoGuardado('error');
                setErrorArchivo(mensajeDe(error, 'No se pudo adjuntar el PDF o la imagen.'));
            } finally {
                suspenderRef.current = false;
            }
        });
    };

    const elegirArchivo = (evento) => {
        const file = evento.target.files?.[0];
        evento.target.value = '';
        subirArchivo(file);
    };

    const pegarArchivo = (evento) => {
        const pegado = archivosImagenDesdeClipboard(evento.clipboardData);
        if (!pegado.length) return;
        evento.preventDefault();
        const imagen = pegado[0];
        subirArchivo(imagen);
    };

    const retirarArchivo = async () => {
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

    const continuarEnvio = (valor) =>
        encolar(async () => {
            if (avanzado || deseadaRef.current !== valor) {
                if (deseadaRef.current === 'tienda') setTransito('');
                return;
            }
            const codigo = CODIGO_ENVIO[valor];
            const guardado = await ejecutarGuardar(
                {
                    ...payload({ modalidad_entrega: valor, codigo_modalidad: codigo }),
                },
                true,
            );
            if (deseadaRef.current === 'tienda') {
                setTransito('');
                await ejecutarGuardar(null, true);
                return;
            }
            if (deseadaRef.current !== valor) return;
            if (!guardado?.id && !pedidoIdRef.current) {
                setTransito('');
                return;
            }
            const limpio = { ...(pedido || {}) };
            delete limpio.progreso;
            delete limpio.tarea_preparacion;
            onContinuarEnvio?.({
                ...limpio,
                id: guardado?.id || pedidoIdRef.current,
                folio: guardado?.folio || pedido?.folio,
                folio_remision: folio,
                cliente_id: clienteId || null,
                cliente: clienteId
                    ? { id: clienteId, numero_cliente: numeroCliente, nombre: nombreCliente }
                    : pedido?.cliente,
                departamento_operacion_id: departamentoId || null,
                origen_id: pedido?.origen_id || guardado?.origen_id || null,
                sucursal_destino_id: pedido?.sucursal_destino_id || null,
                revision_registro: guardado?.revision_registro,
                base_captura: guardado?.base_captura,
                documentos: documento?.url ? [documento] : pedido?.documentos || [],
                solicitud_preparacion: guardado?.solicitud_preparacion ?? {
                    modalidad_entrega: valor,
                    codigo_modalidad: codigo,
                },
                prioridad_md: Boolean(pedido?.prioridad_md),
                progreso: guardado?.progreso?.perfil === 'tienda' ? null : (guardado?.progreso ?? null),
            });
        });

    const cambiarModalidad = (valor) => {
        if (!valor) return;
        if (avanzado) {
            setAviso(MENSAJE_MODALIDAD);
            return;
        }
        deseadaRef.current = valor;
        if (valor === 'municipal' || valor === 'nacional') {
            setTransito(valor);
            continuarEnvio(valor);
            return;
        }
        setTransito('');
        setModalidad(valor);
        if (valor !== 'tienda') setArea('');
    };

    useEffect(() => {
        setConsultaCerrada(Boolean(pedido?.consulta_cerrada || pedido?.consulta_cerrada_at));
    }, [pedido?.id, pedido?.consulta_cerrada, pedido?.consulta_cerrada_at]);

    useEffect(() => {
        if (!abierto) return undefined;
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
                String(datos.folio || '').trim() ||
                datos.modalidad ||
                datos.area,
            );
            if (!hayContenido) return;
            if (huellaActual() !== huellaRef.current) setBorradorConfirmado(false);
            encolar(async () => {
                if (suspenderRef.current || huellaActual() === huellaRef.current) return;
                await ejecutarGuardar(null, true);
            });
        }, 700);
        return () => window.clearTimeout(timer);
    }, [abierto, clienteId, numeroCliente, departamentoId, folio, modalidad, area, caso, almacenId, totalMercancia, aplicaSaf, saldoFavor, safAplicaciones, safTocado]);

    const dialog = usePedidoDialog({
        abierto,
        onClose,
        bloqueado: guardando || enviando || estadoArchivo === 'cargando',
    });

    const refrescarRef = useRef(null);
    useActualizacionesPedidos({
        activo: abierto && Boolean(pedidoId), pedidoId,
        pausado: guardando || enviando || ['pendiente', 'guardando'].includes(estadoGuardado),
        onActualizar: () => refrescarRef.current?.(),
        onPendiente: () => setAviso('El pedido cambió. Se conserva su captura mientras termina de guardar.'),
    });
    if (!abierto) return null;

    const nombreAlmacenVisible = almacenes.find((almacen) => String(almacen.id) === String(almacenId))?.nombre || '';
    const importesListos = mismoMonto(totalMercancia, confirmados.total_mercancia)
        && mismoMonto(saldoDeDatos(datosRef.current), confirmados.saldo_a_favor)
        && canonMonto(totalMercancia) !== null
        && !conflicto
        && !['guardando', 'pendiente', 'error', 'conflicto'].includes(estadoGuardado);
    const bancosContexto = (catalogos.bancos || []).filter((banco) => (
        banco.compartido !== false
        || (banco.departamento_ids || []).some((id) => String(id) === String(departamentoId))
    ));
    const adoptarLectura = (lectura) => {
        if (!lectura) return;
        setPedido((actual) => ({ ...actual, ...lectura }));
        if (lectura.consulta_cerrada !== undefined) setConsultaCerrada(Boolean(lectura.consulta_cerrada));
        if (lectura.solicitud_preparacion) setSolicitudViva(lectura.solicitud_preparacion);
        const limpio = mismoMonto(datosRef.current.totalMercancia, confirmadosRef.current.total_mercancia)
            && mismoMonto(saldoDeDatos(datosRef.current), confirmadosRef.current.saldo_a_favor);
        if (!limpio) {
            if (lectura.revision_registro != null && Number(lectura.revision_registro) !== Number(confirmadosRef.current.revision_registro)) {
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
        if (lectura.revision_registro != null && lectura.base_captura) {
            const siguiente = { revision_esperada: lectura.revision_registro, base_captura: lectura.base_captura };
            baseRef.current = siguiente;
            setBase(siguiente);
        }
    };
    refrescarRef.current = async () => {
        try {
            const { data } = await axios.get(route('control_pedidos.pagos.resumen', pedidoId), { headers: { Accept: 'application/json' } });
            adoptarLectura(data);
        } catch {
            setAviso('No se pudo actualizar el pedido. Vuelva a abrirlo para consultar su estado.');
        }
    };
    const actualizarSolicitud = (siguiente) => {
        setSolicitudViva(siguiente);
        setPedido((actual) => ({ ...actual, solicitud_preparacion: siguiente }));
        const pendientes = (siguiente?.retiro_piezas || []).some((fila) => (fila.estado || 'pendiente') === 'pendiente')
            || (siguiente?.anexo_sin_remision || []).some((fila) => !fila.respondida_at);
        if (pendientes) setConsultaCerrada(false);
        refrescarRef.current?.();
    };
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

    return createPortal(
        <div className={THEME_MODAL_OVERLAY} data-gelia-modal="1">
            <div
                {...dialog}
                aria-labelledby="registrar-tienda-titulo"
                className={`${THEME_MODAL_SHELL} gelia-pedidos-dialog gelia-pedidos-formulario gelia-captura-inicial registrar-tienda-modal w-full`}
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
                            Registrar pedido
                        </h2>
                        <p className="registrar-tienda-descripcion">
                            Captura el pedido y elige el destino de la revisión.
                        </p>
                    </div>
                    <button
                        type="button"
                        className={`${GELIA_BTN_OUTLINE} registrar-tienda-cerrar`}
                        onClick={onClose}
                        disabled={guardando || enviando}
                        aria-label="Cerrar registro"
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
                    {avanzado && (
                        <p className="mb-4 text-sm theme-text-main m-0" role="status">
                            {MENSAJE_MODALIDAD}
                        </p>
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
                                        className={campo}
                                        value={folio}
                                        onChange={(evento) => setFolio(evento.target.value)}
                                        autoComplete="off"
                                        placeholder="Folio de Wizerp"
                                    />
                                </div>
                            </div>
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
                                        disabled={avanzado || guardando || enviando}
                                        onChange={(evento) => cambiarModalidad(evento.target.value)}
                                    >
                                        <option value="">Elegir</option>
                                        {OPCIONES_MODALIDAD.map(([valor, titulo]) => (
                                            <option key={valor} value={valor}>
                                                {titulo}
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
                        {(transito || modalidad) === 'tienda' && (
                            <section
                                className="registrar-tienda-panel"
                                aria-labelledby="registrar-tienda-atencion-titulo"
                            >
                                <div className="registrar-tienda-seccion-titulo">
                                    <Package size={18} aria-hidden="true" />
                                    <h3 id="registrar-tienda-atencion-titulo">Atención en tienda</h3>
                                </div>
                                <p className="registrar-tienda-ayuda">Elige el destino de esta solicitud.</p>
                                {caso === 'mismo_dia_pdv' && (
                                    <p className="text-sm theme-text-main m-0 mb-3" role="status">
                                        {NOTA_TIENDA_MD} Esta captura conserva esa intención y no la convierte en apartado ni en consulta.
                                    </p>
                                )}
                                <div className="registrar-tienda-casos">
                                    {DESTINOS_TIENDA.map((opcion) => (
                                        <button
                                            key={opcion.id}
                                            type="button"
                                            className="registrar-tienda-destino"
                                            aria-pressed={caso === opcion.id}
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
                                                {caso === opcion.id && <Check size={12} />}
                                            </span>
                                        </button>
                                    ))}
                                </div>
                                {area === 'preparacion_tienda' && almacenes.length === 1 && (
                                    <div className="m-0 mt-2" role="status">
                                        <div
                                            className={`gelia-estado-vivo gelia-estado-vivo--compacto inline-flex items-center gap-1 ${GELIA_ESTADO_VIVO_TONO.info}`}
                                        >
                                            <MapPinIcon size={14} aria-hidden="true" />
                                            {almacenes[0].nombre}
                                        </div>
                                    </div>
                                )}
                                {area === 'preparacion_tienda' && almacenes.length > 1 && (
                                    <div className="mt-2">
                                        <label className={THEME_LABEL} htmlFor="almacen-tienda">
                                            Sucursal o almacén
                                        </label>
                                        <select
                                            id="almacen-tienda"
                                            className={`${THEME_SELECT} w-full min-h-[44px] text-base`}
                                            value={String(almacenId)}
                                            disabled={enviada || enviando}
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
                            className="registrar-tienda-panel registrar-tienda-documento"
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
                                {!documento?.url && estadoArchivo !== 'cargando' && (
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
                                {documento?.url && estadoArchivo !== 'cargando' && (
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
                                        disabled={estadoArchivo === 'cargando'}
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
                                                disabled={estadoArchivo === 'cargando'}
                                                onClick={retirarArchivo}
                                            >
                                                <Trash2Icon size={18} aria-hidden="true" />
                                                Retirar
                                            </button>
                                        </>
                                    )}
                                </div>
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
                    {(enviada || respuestaDeTiendaEmitida(pedido)) && (
                        <>
                            <SeguimientoSolicitudTienda
                                pedidoId={pedidoId}
                                pedido={{ ...pedido, solicitud_preparacion: solicitudViva }}
                                onSolicitud={actualizarSolicitud}
                                area={area}
                                nombreAlmacen={nombreAlmacenVisible}
                                revisiones={pedido?.revisiones_producto || pedido?.revisionesProducto || []}
                                consultaCerrada={consultaCerrada}
                                onConsultaCerrada={() => { setConsultaCerrada(true); refrescarRef.current?.(); }}
                            />
                            <ContinuidadTienda
                                pedidoId={pedidoId}
                                solicitud={solicitudViva}
                                sucursales={catalogos.sucursales || []}
                                facultadMd={Boolean(registrarTienda.facultad_md)}
                                puedeResolverTraslado={superAdmin || (auth?.user?.permissions || []).includes('control_pedidos.tienda.autorizar_segundo_traslado')}
                                puedeOperarTienda={superAdmin || (auth?.user?.permissions || []).includes('control_pedidos.tienda.responder') || (auth?.user?.permissions || []).includes('control_pedidos.tienda.trasladar')}
                                puedeCedis={superAdmin || (auth?.user?.permissions || []).includes('control_pedidos.cedis')}
                                mostrarCustodia={false}
                                consultaCerrada={consultaCerrada}
                                importes={{
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
                            />
                        </>
                    )}
                    {borradorConfirmado && !enviada && (
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
                        {modalidad === 'tienda' && !enviada && motivo && (
                            <p id="registrar-tienda-motivo" className="text-xs theme-text-muted m-0">
                                {motivo}
                            </p>
                        )}
                    </div>
                    <div className="registrar-tienda-pie-acciones">
                        <BotonBorrador
                            guardando={guardando}
                            confirmado={borradorConfirmado}
                            deshabilitado={enviada || enviando}
                            onClick={() => guardar()}
                        />
                        {modalidad === 'tienda' && !enviada && (
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
                    </div>
                </footer>
            </div>
            <ModalVistaPreviaDocumento
                abierto={ampliado && Boolean(documento?.url)}
                documento={documento}
                onClose={() => setAmpliado(false)}
            />
        </div>,
        document.body,
    );
}
