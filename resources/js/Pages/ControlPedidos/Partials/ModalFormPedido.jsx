import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, usePage, router } from '@inertiajs/react';
import axios from 'axios';
import {
    X, Search, Save, Send, MessageCircle, RotateCcw, ImagePlus, Trash2, AlertTriangle, MapPin, PenLine, Link2, Cloud, HardDrive,
} from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';
import { THEME_INPUT, THEME_SELECT, THEME_TEXTAREA } from '../../../utils/geliaTheme';
import InputMoneda from './InputMoneda';
import DireccionPedidoResumen from './DireccionPedidoResumen';
import { codigoDireccionCliente, labelOpcionDireccion } from './codigoDireccionCliente';
import {
    calcularTotalCobrar,
    calcCostoSeguro,
    paqueteriaTieneCobertura,
    formatearMoneda,
    etiquetaAlmacen,
    textoWhatsAppPedido,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
    validarCamposEnvioPedido,
    etiquetaEstatusPedido,
} from './pedidosBmaStyles';
import ModalAlertaPedido from './ModalAlertaPedido';
import ModalGenerarLinkDireccion from './ModalGenerarLinkDireccion';
import { resolverReexpedicionForm } from './resolverReexpedicionForm';

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

function formDefaults(pedido = null) {
    return {
        origen_id: pedido?.origen_id || '',
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
        aplica_seguro: pedido?.aplica_seguro || false,
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

export default function ModalFormPedido({ abierto, onClose, pedido = null, catalogos = {}, direccionesNormalizadas = false }) {
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
    const [dimsCaja, setDimsCaja] = useState({ largo: null, ancho: null, alto: null });
    const [alertaEnvio, setAlertaEnvio] = useState({ abierto: false, mensaje: '' });
    const [modalLinkDireccion, setModalLinkDireccion] = useState(false);
    const temporizadorBusqueda = useRef(null);
    const abortBusqueda = useRef(null);
    const costoReexpedicionAplicado = useRef(0);
    const matchReexpedicionKey = useRef(null);
    const pedidoBdIdRef = useRef(pedido?.id || null);
    const ultimoFingerprintBd = useRef('');
    const autoguardandoBd = useRef(false);
    const [pedidoBdId, setPedidoBdId] = useState(pedido?.id || null);
    const [estadoAuto, setEstadoAuto] = useState({ local: null, bd: null });

    const { data, setData, post, processing, reset, errors, transform } = useForm(formDefaults(pedido));

    const puedeAutoguardarBd = !pedido || Boolean(pedido?.estatus?.fase_ciclo === 'BORRADOR' || pedido?.estatus?.fase_ciclo === 'RECHAZADO_VENDEDORA');

    const paqueteriaSeleccionada = (catalogos.paqueterias || []).find(
        (p) => String(p.id) === String(data.catalogo_paqueteria_id)
    );
    const origenSeleccionado = (catalogos.origenes || []).find(
        (o) => String(o.id) === String(data.origen_id)
    );
    const requiereLogistica = origenSeleccionado?.requiere_logistica ?? false;
    const tieneCoberturaSeguro = paqueteriaTieneCobertura(paqueteriaSeleccionada?.nombre);

    const totalCobrar = calcularTotalCobrar(
        data.total_mercancia, data.costo_envio, data.aplica_seguro, data.costo_seguro,
        data.aplica_saldo_favor ? data.saldo_a_favor : 0
    );

    useEffect(() => {
        if (!abierto) return;
        costoReexpedicionAplicado.current = 0;
        matchReexpedicionKey.current = null;
        ultimoFingerprintBd.current = '';
        if (pedido) {
            pedidoBdIdRef.current = pedido.id;
            setPedidoBdId(pedido.id);
            setData(formDefaults(pedido));
            setInfoCliente(pedido.cliente || null);
            setPesoVolumetrico(pedido.peso_volumetrico_kg ?? '');
            setDimsCaja({
                largo: pedido.tipo_caja?.largo ?? null,
                ancho: pedido.tipo_caja?.ancho ?? null,
                alto: pedido.tipo_caja?.alto ?? null,
            });
            setAlertaDireccion(false);
            setMsgDireccion('');
            setDocsEliminar([]);
            setPreviews([]);
            setAlertaEnvio({ abierto: false, mensaje: '' });
            setDireccionesCliente([]);
            setMostrarExcepcion(false);
            setEstadoAuto({ local: null, bd: null });
            if (pedido.cliente_id) {
                cargarDireccionCliente(pedido.cliente_id, {
                    silencioso: true,
                    conservarSeleccion: true,
                    direccionId: pedido.cliente_direccion_id,
                });
            }
        } else {
            const borrador = leerBorradorLocal();
            const idBd = borrador?.pedido_id || null;
            pedidoBdIdRef.current = idBd;
            setPedidoBdId(idBd);
            if (borrador) {
                const { pedido_id: _pid, _nombre_cliente: nombreCli, ...restoBorrador } = borrador;
                setData({ ...formDefaults(), ...restoBorrador, comprobantes: [], documentos_eliminar: [], enviar: false });
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
                setData(formDefaults());
                setInfoCliente(null);
            }
            setPesoVolumetrico('');
            setDimsCaja({ largo: null, ancho: null, alto: null });
            setAlertaDireccion(false);
            setMsgDireccion('');
            setPreviews([]);
            setDocsEliminar([]);
            setAlertaEnvio({ abierto: false, mensaje: '' });
            if (!borrador?.cliente_id) {
                setDireccionesCliente([]);
            }
            setMostrarExcepcion(Boolean(borrador?.direccion_manual_excepcion));
            setEstadoAuto({ local: borrador ? 'Borrador local recuperado' : null, bd: idBd ? `Borrador #${idBd}` : null });
        }
    }, [abierto, pedido?.id]);

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
                payload.saldo_a_favor = data.aplica_saldo_favor ? data.saldo_a_favor : 0;
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
                ultimoFingerprintBd.current = fpNow;
                if (!modoEdicion) {
                    guardarBorradorLocal({
                        ...data,
                        pedido_id: res.id,
                        _nombre_cliente: infoCliente?.nombre || '',
                    });
                }
                setEstadoAuto((s) => ({
                    ...s,
                    bd: `Servidor · ${res.folio || `#${res.id}`}`,
                }));
                if (eraNuevo) {
                    router.reload({ only: ['pedidos', 'metricas'], preserveState: true, preserveScroll: true });
                }
            } catch (err) {
                const msg = err?.response?.data?.message || 'No se pudo autoguardar en servidor';
                setEstadoAuto((s) => ({ ...s, bd: `Servidor · error` }));
                console.warn('[autoguardar pedido]', msg);
            } finally {
                autoguardandoBd.current = false;
            }
        }, AUTOSAVE_BD_MS);

        return () => clearTimeout(timer);
    }, [data, abierto, puedeAutoguardarBd, processing, modoEdicion, infoCliente?.nombre]);

    useEffect(() => {
        if (!data.catalogo_tipo_caja_id) {
            setPesoVolumetrico('');
            setDimsCaja({ largo: null, ancho: null, alto: null });
            return;
        }
        const caja = (catalogos.tipos_caja || []).find((c) => String(c.id) === String(data.catalogo_tipo_caja_id));
        setPesoVolumetrico(caja?.peso_volumetrico ?? '');
        setDimsCaja({
            largo: caja?.largo ?? null,
            ancho: caja?.ancho ?? null,
            alto: caja?.alto ?? null,
        });
    }, [data.catalogo_tipo_caja_id, catalogos.tipos_caja]);

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

        const paq = (catalogos.paqueterias || []).find((p) => String(p.id) === String(data.catalogo_paqueteria_id));
        const costo = calcCostoSeguro(paq?.nombre, data.costo_envio, data.total_mercancia);
        setData('costo_seguro', costo);

        if (!paqueteriaTieneCobertura(paq?.nombre)) {
            setData('aplica_seguro', false);
        }
    }, [data.catalogo_paqueteria_id, data.costo_envio, data.total_mercancia, catalogos.paqueterias]);

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

    const cargarDireccionCliente = async (clienteId, { silencioso = false, conservarSeleccion = false, direccionId = null } = {}) => {
        if (!clienteId) {
            if (!silencioso) {
                setMsgDireccion('Seleccione un cliente primero para rellenar la dirección.');
                setAlertaDireccion(false);
            }
            return;
        }

        setCargandoDireccion(true);
        setMsgDireccion('');
        try {
            const response = await axios.get(`/api/clientes/id/${clienteId}/direccion-envio`);
            const usarNormalizadas = direccionesNormalizadas || response.data?.direcciones_normalizadas;
            if (usarNormalizadas) {
                const dirs = response.data?.direcciones || [];
                setDireccionesCliente(dirs);
                const idSeleccion = conservarSeleccion
                    ? (direccionId || data.cliente_direccion_id)
                    : null;
                const seleccionada = idSeleccion
                    ? dirs.find((d) => String(d.id) === String(idSeleccion))
                    : null;
                const principal = seleccionada || dirs.find((d) => d.es_principal) || dirs[0];
                if (principal) {
                    setData('cliente_direccion_id', principal.id);
                    setData('domicilio_entrega', principal.direccion_resumida || '');
                    setData('codigo_postal', principal.codigo_postal || '');
                    if (principal.anexa_remision) {
                        setData('anexar_remision', true);
                    }
                    setAlertaDireccion(!conservarSeleccion);
                    setMsgDireccion('');
                } else {
                    setData('cliente_direccion_id', '');
                    setAlertaDireccion(false);
                    if (!silencioso) {
                        setMsgDireccion('Este cliente no tiene direcciones verificadas. Solicite el registro o use excepción autorizada.');
                    }
                }
            } else if (response.data?.tiene_direccion) {
                setDireccionesCliente([]);
                setData('domicilio_entrega', response.data.domicilio_entrega || '');
                setData('codigo_postal', response.data.codigo_postal || '');
                setAlertaDireccion(true);
                setMsgDireccion('');
            } else {
                setDireccionesCliente([]);
                setAlertaDireccion(false);
                if (!silencioso) {
                    setMsgDireccion('Este cliente no tiene dirección registrada. Capture los datos manualmente.');
                }
            }
        } catch {
            setAlertaDireccion(false);
            if (!silencioso) {
                setMsgDireccion('No se pudo obtener la dirección del cliente. Capture los datos manualmente.');
            }
        } finally {
            setCargandoDireccion(false);
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
        setData('numero_cliente', cliente.numero_cliente);
        setData('cliente_id', cliente.id);
        setInfoCliente(cliente);
        setMostrarDropdown(false);
        setMsgDireccion('');
        cargarDireccionCliente(cliente.id, { silencioso: true });
    };

    const rellenarDireccionManual = () => {
        cargarDireccionCliente(data.cliente_id || infoCliente?.id);
    };

    const manejarPaqueteria = (id) => {
        setData('catalogo_paqueteria_id', id);
        const paq = (catalogos.paqueterias || []).find((p) => String(p.id) === String(id));
        if (!paqueteriaTieneCobertura(paq?.nombre)) {
            setData('aplica_seguro', false);
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

    const guardar = (enviarPedido = false) => {
        setAlertaEnvio({ abierto: false, mensaje: '' });

        if (enviarPedido) {
            const comprobantesExistentes = modoEdicion
                ? (pedido?.documentos || []).filter((d) => !docsEliminar.includes(d.id)).length
                : 0;
            const { valido, mensaje } = validarCamposEnvioPedido(data, {
                comprobantesExistentes,
                requiereLogistica,
                direccionesNormalizadas,
            });
            if (!valido) {
                setAlertaEnvio({ abierto: true, mensaje });
                return;
            }
        }

        const config = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: (page) => {
                if (page?.props?.flash?.error) {
                    setAlertaEnvio({ abierto: true, mensaje: page.props.flash.error });
                    return;
                }
                limpiarBorradorLocal();
                pedidoBdIdRef.current = null;
                setPedidoBdId(null);
                onClose();
                reset();
            },
        };
        const idDestino = modoEdicion ? pedido.id : pedidoBdIdRef.current;
        if (idDestino) {
            transform((d) => ({
                ...d,
                _method: 'put',
                enviar: enviarPedido,
                saldo_a_favor: d.aplica_saldo_favor ? d.saldo_a_favor : 0,
                comentarios_drive: d.direccion_manual_excepcion && d.motivo_direccion_manual
                    ? `${d.comentarios_drive || ''}\n[Excepción dirección] ${d.motivo_direccion_manual}`.trim()
                    : d.comentarios_drive,
            }));
            post(route('control_pedidos.update', idDestino), config);
        } else {
            transform((d) => ({
                ...d,
                enviar: enviarPedido,
                saldo_a_favor: d.aplica_saldo_favor ? d.saldo_a_favor : 0,
                comentarios_drive: d.direccion_manual_excepcion && d.motivo_direccion_manual
                    ? `${d.comentarios_drive || ''}\n[Excepción dirección] ${d.motivo_direccion_manual}`.trim()
                    : d.comentarios_drive,
            }));
            post(route('control_pedidos.store'), config);
        }
    };

    const compartirWhatsApp = () => {
        if (!pedido) return;
        window.open(`https://wa.me/?text=${textoWhatsAppPedido(pedido)}`, '_blank');
    };

    if (!abierto) return null;

    const docsExistentes = (pedido?.documentos || []).filter((d) => !docsEliminar.includes(d.id));
    const camposIncorrectos = Array.isArray(pedido?.campos_incorrectos) ? pedido.campos_incorrectos : [];
    const esCampoIncorrecto = (key) => camposIncorrectos.includes(key);
    const wrapIncorrecto = (key) => (esCampoIncorrecto(key)
        ? 'rounded-xl ring-2 ring-orange-500/70 bg-orange-500/10 p-2'
        : '');
    const etiquetasIncorrectas = {
        domicilio: 'Domicilio',
        destinatario: 'Destinatario',
        telefono: 'Teléfono',
        paqueteria: 'Paquetería',
        tipo_guia: 'Tipo de guía',
        referencia: 'Referencias',
        codigo_postal: 'Código postal',
        ciudad_estado: 'Ciudad / estado',
        numero_rastreo: 'Número de guía',
        guia_pdf: 'PDF de guía',
    };

    const modal = createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
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

                    {/* 0. Origen y resguardo */}
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>Origen del pedido</p>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className={SECCION}>Origen</label>
                                <select value={data.origen_id} onChange={(e) => setData('origen_id', e.target.value)} className={`${THEME_SELECT} w-full py-3`}>
                                    <option value="">Seleccionar...</option>
                                    {(catalogos.origenes || []).map((o) => (
                                        <option key={o.id} value={o.id}>{o.nombre}</option>
                                    ))}
                                </select>
                            </div>
                            <div className="flex items-end">
                                <label className="flex items-center gap-3 cursor-pointer theme-text-main p-3 rounded-xl border theme-border w-full">
                                    <input
                                        type="checkbox"
                                        checked={data.es_resguardo}
                                        onChange={(e) => setData('es_resguardo', e.target.checked)}
                                        className="w-4 h-4"
                                    />
                                    <span className="text-sm font-bold">¿Dejar en resguardo?</span>
                                </label>
                            </div>
                        </div>
                    </section>

                    {/* 1. Datos generales y cliente */}
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>1. Datos generales y cliente</p>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div className="md:col-span-2 relative">
                                <div className="theme-field-with-icon">
                                    <Search className="theme-field-icon w-4 h-4" />
                                    <input type="text" value={data.numero_cliente} onChange={(e) => manejarBusquedaCliente(e.target.value)} placeholder="Buscar cliente..." className={`${THEME_INPUT} w-full py-3`} />
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
                            <div>
                                <label className={SECCION}>Banco</label>
                                <select value={data.catalogo_banco_id} onChange={(e) => setData('catalogo_banco_id', e.target.value)} className={`${THEME_SELECT} w-full py-3`}>
                                    <option value="">Banco de recepción...</option>
                                    {(catalogos.bancos || []).map((b) => <option key={b.id} value={b.id}>{b.nombre}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className={SECCION}>Almacén de salida</label>
                                <select value={data.almacen_id} onChange={(e) => setData('almacen_id', e.target.value)} className={`${THEME_SELECT} w-full py-3`}>
                                    <option value="">Seleccionar...</option>
                                    {(catalogos.almacenes || []).map((a) => (
                                        <option key={a.id} value={a.id}>{etiquetaAlmacen(a)}</option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </section>

                    {requiereLogistica && (
                    <>
                    {/* 2. Peso y cajas */}
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>2. Peso y cajas</p>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className={SECCION}>Tipo de caja</label>
                                <select value={data.catalogo_tipo_caja_id} onChange={(e) => setData('catalogo_tipo_caja_id', e.target.value)} className={`${THEME_SELECT} w-full py-3`}>
                                    <option value="">Seleccionar...</option>
                                    {(catalogos.tipos_caja || []).map((c) => <option key={c.id} value={c.id}>{c.nombre}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className={SECCION}>Peso real (kg)</label>
                                <input type="number" step="0.0001" min="0" placeholder="0.0000" value={data.peso_real_kg} onChange={(e) => setData('peso_real_kg', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                            </div>
                            <div>
                                <label className={SECCION}>Peso volumétrico (kg)</label>
                                <input type="text" readOnly value={pesoVolumetrico !== '' ? pesoVolumetrico : '—'} className={`${THEME_INPUT} w-full py-3 opacity-60`} />
                            </div>
                            <div>
                                <label className={SECCION}>Largo (cm)</label>
                                <input type="text" readOnly value={dimsCaja.largo != null ? dimsCaja.largo : '—'} className={`${THEME_INPUT} w-full py-3 opacity-60`} />
                            </div>
                            <div>
                                <label className={SECCION}>Ancho (cm)</label>
                                <input type="text" readOnly value={dimsCaja.ancho != null ? dimsCaja.ancho : '—'} className={`${THEME_INPUT} w-full py-3 opacity-60`} />
                            </div>
                            <div>
                                <label className={SECCION}>Alto (cm)</label>
                                <input type="text" readOnly value={dimsCaja.alto != null ? dimsCaja.alto : '—'} className={`${THEME_INPUT} w-full py-3 opacity-60`} />
                            </div>
                            <div className={wrapIncorrecto('tipo_guia')}>
                                <label className={SECCION}>Tipo de guía</label>
                                <select value={data.catalogo_tipo_guia_id} onChange={(e) => setData('catalogo_tipo_guia_id', e.target.value)} className={`${THEME_SELECT} w-full py-3`}>
                                    <option value="">Seleccionar...</option>
                                    {(catalogos.tipos_guia || []).map((g) => <option key={g.id} value={g.id}>{g.nombre}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className={SECCION}>Número de cajas</label>
                                <select value={data.numero_cajas === '' || data.numero_cajas == null ? '' : String(data.numero_cajas)} onChange={(e) => setData('numero_cajas', e.target.value)} className={`${THEME_SELECT} w-full py-3`}>
                                    <option value="">Seleccionar...</option>
                                    <option value="0">N/A</option>
                                    {Array.from({ length: 20 }, (_, i) => i + 1).map((n) => (
                                        <option key={n} value={String(n)}>{n}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className={SECCION}>Peso cobrado guía (kg)</label>
                                <input type="number" step="0.0001" min="0" placeholder="0.0000" value={data.peso_cobrado_guia_kg} onChange={(e) => setData('peso_cobrado_guia_kg', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                            </div>
                        </div>
                    </section>

                    {/* 3. Dirección de envío */}
                    <section className={SECCION_WRAP}>
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
                            <p className={`${THEME_LABEL} m-0`}>3. Dirección de envío</p>
                            {!direccionesNormalizadas && (
                                <button
                                    type="button"
                                    onClick={rellenarDireccionManual}
                                    disabled={cargandoDireccion || !(data.cliente_id || infoCliente?.id)}
                                    className={`${BTN_SECONDARY} theme-element border theme-border flex items-center gap-2 outline-none disabled:opacity-50 shrink-0`}
                                    title={!(data.cliente_id || infoCliente?.id) ? 'Seleccione un cliente primero' : 'Rellenar con la dirección del cliente'}
                                >
                                    <MapPin className="w-4 h-4" />
                                    {cargandoDireccion ? 'Cargando...' : 'Rellenar dirección'}
                                </button>
                            )}
                        </div>
                        {alertaDireccion && !direccionesNormalizadas && (
                            <div className="mb-4 p-4 rounded-xl border border-amber-500/40 bg-amber-500/10 flex items-start gap-3">
                                <AlertTriangle className="w-5 h-5 text-amber-500 shrink-0 mt-0.5" />
                                <p className="text-xs font-bold theme-text-main m-0">Se cargó la dirección del cliente. Verifique que los datos sean correctos; puede editarlos manualmente.</p>
                            </div>
                        )}
                        {msgDireccion && !alertaDireccion && (
                            <div className="mb-4 p-4 rounded-xl border theme-border theme-element flex items-start gap-3">
                                <AlertTriangle className="w-5 h-5 theme-text-muted shrink-0 mt-0.5" />
                                <p className="text-xs font-bold theme-text-main m-0">{msgDireccion}</p>
                            </div>
                        )}
                        <div className={`grid grid-cols-1 md:grid-cols-2 gap-4 ${(esCampoIncorrecto('domicilio') || esCampoIncorrecto('ciudad_estado') || esCampoIncorrecto('referencia') || esCampoIncorrecto('destinatario') || esCampoIncorrecto('telefono')) ? 'rounded-xl ring-2 ring-orange-500/40 bg-orange-500/5 p-3' : ''}`}>
                            <div className={wrapIncorrecto('codigo_postal')}>
                                <label className={SECCION}>C.P.</label>
                                <input type="text" placeholder="Código postal" value={data.codigo_postal} onChange={(e) => setData('codigo_postal', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                            </div>
                            {direccionesNormalizadas && puedeSeleccionar ? (
                                <div className="md:col-span-2 space-y-3">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <label className={`${SECCION} m-0`}>Seleccionar dirección de envío</label>
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
                                    {direccionesCliente.length > 0 && !mostrarExcepcion ? (
                                        <>
                                            <select
                                                value={data.cliente_direccion_id}
                                                onChange={(e) => {
                                                    const id = e.target.value;
                                                    setData('cliente_direccion_id', id);
                                                    setData('direccion_manual_excepcion', false);
                                                    const sel = direccionesCliente.find((d) => String(d.id) === String(id));
                                                    if (sel) {
                                                        setData('domicilio_entrega', sel.direccion_resumida || '');
                                                        setData('codigo_postal', sel.codigo_postal || '');
                                                        if (sel.anexa_remision) {
                                                            setData('anexar_remision', true);
                                                        }
                                                    }
                                                }}
                                                className={`${THEME_SELECT} w-full py-3`}
                                            >
                                                <option value="">Seleccionar dirección de envío…</option>
                                                {direccionesCliente.map((d) => (
                                                    <option key={d.id} value={d.id}>
                                                        {labelOpcionDireccion(data.numero_cliente || infoCliente?.numero_cliente, d)}
                                                    </option>
                                                ))}
                                            </select>
                                            {direccionesCliente.find((d) => String(d.id) === String(data.cliente_direccion_id)) && (
                                                <DireccionPedidoResumen
                                                    direccion={direccionesCliente.find((d) => String(d.id) === String(data.cliente_direccion_id))}
                                                    codigoDireccion={codigoDireccionCliente(
                                                        data.numero_cliente || infoCliente?.numero_cliente,
                                                        direccionesCliente.find((d) => String(d.id) === String(data.cliente_direccion_id))?.numero_direccion,
                                                    )}
                                                />
                                            )}
                                        </>
                                    ) : null}

                                    {(direccionesCliente.length === 0 || mostrarExcepcion) && (
                                        <div className="rounded-xl border border-amber-500/30 bg-amber-500/5 p-4 space-y-3">
                                            <p className="text-xs font-bold theme-text-main m-0">
                                                {direccionesCliente.length === 0
                                                    ? 'Este cliente no tiene direcciones verificadas.'
                                                    : 'Captura de excepción manual autorizada.'}
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
                                            }}
                                        >
                                            Usar excepción manual en su lugar
                                        </button>
                                    )}
                                </div>
                            ) : (
                                <div className="md:col-span-2">
                                    <label className={SECCION}>Domicilio de entrega</label>
                                    <textarea placeholder="Calle, colonia, municipio, estado..." value={data.domicilio_entrega} onChange={(e) => setData('domicilio_entrega', e.target.value)} className={`${THEME_TEXTAREA} w-full py-3 min-h-[80px]`} />
                                </div>
                            )}
                        </div>
                    </section>

                    {/* 4. Envío y costos (logística) */}
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>4. Envío y costos</p>
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className={SECCION}>Total mercancía</label>
                                    <InputMoneda value={data.total_mercancia} onChange={(v) => setData('total_mercancia', v)} className="w-full py-3" />
                                </div>
                                <div>
                                    <label className={SECCION}>Paquetería</label>
                                    <div className={wrapIncorrecto('paqueteria')}>
                                    <select value={data.catalogo_paqueteria_id} onChange={(e) => manejarPaqueteria(e.target.value)} className={`${THEME_SELECT} w-full py-3`}>
                                        <option value="">Seleccionar...</option>
                                        {(catalogos.paqueterias || []).map((p) => <option key={p.id} value={p.id}>{p.nombre}</option>)}
                                    </select>
                                    </div>
                                </div>
                                {!tieneCoberturaSeguro && data.catalogo_paqueteria_id && (
                                    <div id="seg-warn" className="md:col-span-2 flex items-start gap-2 p-3 rounded-xl border border-amber-500/40 bg-amber-500/10">
                                        <AlertTriangle className="w-4 h-4 text-amber-600 shrink-0 mt-0.5" />
                                        <p className="text-xs font-bold text-amber-700 dark:text-amber-400 m-0">
                                            Este transporte no cuenta con cobertura de seguro.
                                        </p>
                                    </div>
                                )}
                                <div>
                                    <label className={SECCION}>Costo de envío</label>
                                    <InputMoneda value={data.costo_envio} onChange={(v) => setData('costo_envio', v)} className="w-full py-3" placeholder="" />
                                </div>
                                <div>
                                    <label className={SECCION}>Reexpedición</label>
                                    <select value={data.catalogo_zona_id} onChange={(e) => setData('catalogo_zona_id', e.target.value)} className={`${THEME_SELECT} w-full py-3`}>
                                        <option value="">Seleccionar...</option>
                                        {(catalogos.zonas || []).map((z) => <option key={z.id} value={z.id}>{z.nombre}</option>)}
                                    </select>
                                </div>
                            </div>

                            <div className="flex flex-wrap items-center gap-x-6 gap-y-3 p-4 rounded-xl border theme-border theme-element">
                                <label className="flex items-center gap-2 theme-text-main cursor-pointer">
                                    <input type="checkbox" checked={data.aplica_saldo_favor} onChange={(e) => setData('aplica_saldo_favor', e.target.checked)} />
                                    <span className="text-sm font-bold">Saldo a favor</span>
                                </label>
                                {tieneCoberturaSeguro && (
                                    <label className="flex items-center gap-2 theme-text-main cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={data.aplica_seguro}
                                            onChange={(e) => setData('aplica_seguro', e.target.checked)}
                                        />
                                        <span className="text-sm font-bold">Con seguro</span>
                                    </label>
                                )}
                                <label className="flex items-center gap-2 theme-text-main cursor-pointer">
                                    <input type="checkbox" checked={data.envia_a_otra_persona} onChange={(e) => setData('envia_a_otra_persona', e.target.checked)} />
                                    <span className="text-sm font-bold">Enviar a otra persona</span>
                                </label>
                            </div>

                            {(data.aplica_saldo_favor || data.aplica_seguro || data.envia_a_otra_persona || tieneCoberturaSeguro) && (
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    {data.aplica_saldo_favor && (
                                        <div>
                                            <label className={SECCION}>Monto saldo a favor</label>
                                            <InputMoneda value={data.saldo_a_favor} onChange={(v) => setData('saldo_a_favor', v)} className="w-full py-3" />
                                        </div>
                                    )}
                                    {tieneCoberturaSeguro && (
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

                    {/* 5. Evidencias y comentarios */}
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>{requiereLogistica ? '5. Evidencias y comentarios' : '2. Evidencias y comentarios'}</p>
                        <div className="space-y-4">
                            {!requiereLogistica && (
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label className={SECCION}>Total mercancía</label>
                                        <InputMoneda value={data.total_mercancia} onChange={(v) => setData('total_mercancia', v)} className="w-full py-3" />
                                    </div>
                                </div>
                            )}
                            <div>
                                <label className={SECCION}>Evidencias / Comprobantes</label>
                                <p className="text-[10px] theme-text-muted font-bold mb-3 -mt-1">Adjunte archivos o use Ctrl+V para pegar capturas.</p>
                                <label className="flex items-center gap-2 px-4 py-3 border theme-border border-dashed rounded-xl cursor-pointer w-fit theme-element theme-text-main">
                                    <ImagePlus className="w-4 h-4 theme-text-muted" />
                                    <span className="text-xs font-black uppercase">Adjuntar comprobantes</span>
                                    <input type="file" accept="image/*" multiple className="hidden" onChange={manejarArchivos} />
                                </label>
                                <div className="flex flex-wrap gap-3 mt-3">
                                    {docsExistentes.map((doc) => (
                                        <div key={doc.id} className="relative w-20 h-20 rounded-xl overflow-hidden border theme-border">
                                            <img src={doc.url} alt={doc.nombre_original} className="w-full h-full object-cover" />
                                            <button type="button" onClick={() => toggleEliminarDoc(doc.id)} className="absolute top-1 right-1 p-1 bg-red-500 text-white rounded-lg outline-none">
                                                <Trash2 className="w-3 h-3" />
                                            </button>
                                        </div>
                                    ))}
                                    {previews.map((p, i) => (
                                        <div key={p.url} className="relative w-20 h-20 rounded-xl overflow-hidden border theme-border">
                                            <img src={p.url} alt={p.name} className="w-full h-full object-cover" />
                                            <button type="button" onClick={() => quitarPreviewNuevo(i)} className="absolute top-1 right-1 p-1 bg-red-500 text-white rounded-lg outline-none">
                                                <Trash2 className="w-3 h-3" />
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            </div>
                            <div>
                                <label className={SECCION}>Comentarios para Drive / Almacén</label>
                                <textarea placeholder="Notas adicionales..." value={data.comentarios_drive} onChange={(e) => setData('comentarios_drive', e.target.value)} className={`${THEME_TEXTAREA} w-full py-3 min-h-[80px]`} />
                            </div>
                            <div>
                                <label className={SECCION}>Anexar remisión</label>
                                <select
                                    value={data.anexar_remision ? '1' : '0'}
                                    onChange={(e) => setData('anexar_remision', e.target.value === '1')}
                                    className={`${THEME_SELECT} w-full py-3 max-w-xs`}
                                >
                                    <option value="0">NO</option>
                                    <option value="1">SÍ</option>
                                </select>
                            </div>
                        </div>
                    </section>

                    {/* Desglose de montos */}
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>{requiereLogistica ? '6. Desglose de montos' : '3. Desglose de montos'}</p>
                        <div className="space-y-2 text-sm">
                            <div className="flex justify-between theme-text-muted font-bold"><span>Total mercancía</span><span>{formatearMoneda(data.total_mercancia)}</span></div>
                            <div className="flex justify-between theme-text-muted font-bold"><span>Envío</span><span>{formatearMoneda(data.costo_envio)}</span></div>
                            {data.aplica_seguro && (
                                <div className="flex justify-between theme-text-muted font-bold"><span>Seguro</span><span>{formatearMoneda(data.costo_seguro)}</span></div>
                            )}
                            {data.aplica_saldo_favor && (
                                <div className="flex justify-between text-emerald-600 font-bold"><span>Saldo a favor</span><span>- {formatearMoneda(data.saldo_a_favor)}</span></div>
                            )}
                        </div>
                        <div className="mt-4 p-4 rounded-2xl border-2" style={{ borderColor: 'var(--color-primario)' }}>
                            <p className="text-[10px] font-black uppercase theme-text-muted m-0">Total final</p>
                            <p className="text-2xl font-black m-0" style={{ color: 'var(--color-primario)' }}>{formatearMoneda(totalCobrar)}</p>
                        </div>
                    </section>

                    <section className="gelia-modal-footer flex flex-col gap-3 p-5 md:p-6 -mx-5 md:-mx-8 -mb-5 md:-mb-8">
                        <div className="flex flex-wrap gap-3">
                        <button type="button" onClick={() => guardar(true)} disabled={processing} className={`${BTN_PRIMARY} flex items-center gap-2 outline-none`}>
                            <Send className="w-4 h-4" /> Enviar pedido
                        </button>
                        <button type="button" onClick={() => guardar(false)} disabled={processing} className={`${BTN_SECONDARY} theme-element border theme-border flex items-center gap-2 outline-none`}>
                            <Save className="w-4 h-4" /> Guardar borrador
                        </button>
                        {modoEdicion && (
                            <button type="button" onClick={compartirWhatsApp} className={`${BTN_SECONDARY} theme-element border theme-border flex items-center gap-2 outline-none`}>
                                <MessageCircle className="w-4 h-4" /> WhatsApp
                            </button>
                        )}
                        <button type="button" onClick={() => {
                            setData(formDefaults(pedido));
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
                    {Object.keys(errors).length > 0 && (
                        <p className="text-xs text-red-500 font-bold">Revise los campos del formulario.</p>
                    )}
                </div>
            </div>
        </div>,
        document.body
    );

    return (
        <>
            {modal}
            <ModalGenerarLinkDireccion
                abierto={modalLinkDireccion}
                onClose={() => setModalLinkDireccion(false)}
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
                abierto={alertaEnvio.abierto}
                tipo="error"
                titulo="Campos incompletos"
                mensaje={alertaEnvio.mensaje}
                onClose={() => setAlertaEnvio({ abierto: false, mensaje: '' })}
            />
        </>
    );
}
