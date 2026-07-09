import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import {
    X, Search, Save, Send, MessageCircle, RotateCcw, ImagePlus, Trash2, AlertTriangle, MapPin,
} from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';
import { THEME_INPUT, THEME_SELECT, THEME_TEXTAREA } from '../../../utils/geliaTheme';
import {
    calcularTotalCobrar,
    formatearMoneda,
    textoWhatsAppPedido,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
} from './pedidosBmaStyles';

const SECCION = `${THEME_LABEL} mb-3 block`;
const SECCION_WRAP = 'border-b theme-border pb-8 last:border-0';

export default function ModalFormPedido({ abierto, onClose, pedido = null, catalogos = {} }) {
    const modoEdicion = Boolean(pedido?.id);
    const [listaClientes, setListaClientes] = useState([]);
    const [mostrarDropdown, setMostrarDropdown] = useState(false);
    const [buscandoCliente, setBuscandoCliente] = useState(false);
    const [infoCliente, setInfoCliente] = useState(pedido?.cliente || null);
    const [alertaDireccion, setAlertaDireccion] = useState(false);
    const [msgDireccion, setMsgDireccion] = useState('');
    const [cargandoDireccion, setCargandoDireccion] = useState(false);
    const [previews, setPreviews] = useState([]);
    const [docsEliminar, setDocsEliminar] = useState([]);
    const [pesoVolumetrico, setPesoVolumetrico] = useState(pedido?.peso_volumetrico_kg ?? '');
    const temporizadorBusqueda = useRef(null);
    const abortBusqueda = useRef(null);

    const { data, setData, post, processing, reset, errors, transform } = useForm({
        cliente_id: pedido?.cliente_id || '',
        numero_cliente: pedido?.cliente?.numero_cliente || '',
        fecha: pedido?.fecha?.slice?.(0, 10) || new Date().toISOString().slice(0, 10),
        catalogo_banco_id: pedido?.catalogo_banco_id || '',
        requiere_factura: pedido?.requiere_factura || false,
        catalogo_almacen_salida_id: pedido?.catalogo_almacen_salida_id || '',
        catalogo_tipo_caja_id: pedido?.catalogo_tipo_caja_id || '',
        numero_cajas: pedido?.numero_cajas ?? 1,
        peso_real_kg: pedido?.peso_real_kg ?? '',
        peso_con_productos_kg: pedido?.peso_con_productos_kg ?? '',
        catalogo_tipo_guia_id: pedido?.catalogo_tipo_guia_id || '',
        codigo_postal: pedido?.codigo_postal || '',
        domicilio_entrega: pedido?.domicilio_entrega || '',
        total_mercancia: pedido?.total_mercancia ?? 0,
        catalogo_envio_tienda_id: pedido?.catalogo_envio_tienda_id || '',
        envio_tienda_otro: pedido?.envio_tienda_otro || '',
        catalogo_paqueteria_id: pedido?.catalogo_paqueteria_id || '',
        costo_envio: pedido?.costo_envio ?? 0,
        aplica_saldo_favor: Number(pedido?.saldo_a_favor || 0) > 0,
        saldo_a_favor: pedido?.saldo_a_favor ?? 0,
        aplica_seguro: pedido?.aplica_seguro || false,
        costo_seguro: pedido?.costo_seguro ?? 0,
        envia_a_otra_persona: pedido?.envia_a_otra_persona || false,
        envia_otra_persona: pedido?.envia_otra_persona || '',
        es_resguardo: pedido?.es_resguardo || false,
        catalogo_zona_id: pedido?.catalogo_zona_id || '',
        comentarios_drive: pedido?.comentarios_drive || '',
        comprobantes: [],
        documentos_eliminar: [],
        enviar: false,
    });

    const envioTiendaOtro = (catalogos.envios_tienda || []).find(
        (e) => String(e.id) === String(data.catalogo_envio_tienda_id)
    )?.es_otro;

    const totalCobrar = calcularTotalCobrar(
        data.total_mercancia, data.costo_envio, data.aplica_seguro, data.costo_seguro,
        data.aplica_saldo_favor ? data.saldo_a_favor : 0
    );

    useEffect(() => {
        if (!abierto) return;
        if (pedido) {
            setInfoCliente(pedido.cliente || null);
            setPesoVolumetrico(pedido.peso_volumetrico_kg ?? '');
            setAlertaDireccion(false);
            setMsgDireccion('');
            setDocsEliminar([]);
            setPreviews([]);
        } else {
            reset();
            setInfoCliente(null);
            setPesoVolumetrico('');
            setAlertaDireccion(false);
            setMsgDireccion('');
            setPreviews([]);
            setDocsEliminar([]);
        }
    }, [abierto, pedido?.id]);

    useEffect(() => {
        if (!data.catalogo_tipo_caja_id) {
            setPesoVolumetrico('');
            return;
        }
        const caja = (catalogos.tipos_caja || []).find((c) => String(c.id) === String(data.catalogo_tipo_caja_id));
        setPesoVolumetrico(caja?.peso_volumetrico ?? '');
    }, [data.catalogo_tipo_caja_id, catalogos.tipos_caja]);

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

    const cargarDireccionCliente = async (clienteId, { silencioso = false } = {}) => {
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
            if (response.data?.tiene_direccion) {
                setData('domicilio_entrega', response.data.domicilio_entrega || '');
                setData('codigo_postal', response.data.codigo_postal || '');
                setAlertaDireccion(true);
                setMsgDireccion('');
            } else {
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
        if (paq?.costo_seguro_default != null) {
            setData('costo_seguro', paq.costo_seguro_default);
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

    const guardar = (enviar = false) => {
        const config = { forceFormData: true, preserveScroll: true, onSuccess: () => { onClose(); reset(); } };
        if (modoEdicion) {
            transform((d) => ({ ...d, _method: 'put', enviar, saldo_a_favor: d.aplica_saldo_favor ? d.saldo_a_favor : 0 }));
            post(route('control_pedidos.update', pedido.id), config);
        } else {
            transform((d) => ({ ...d, enviar, saldo_a_favor: d.aplica_saldo_favor ? d.saldo_a_favor : 0 }));
            post(route('control_pedidos.store'), config);
        }
    };

    const compartirWhatsApp = () => {
        if (!pedido) return;
        window.open(`https://wa.me/?text=${textoWhatsAppPedido(pedido)}`, '_blank');
    };

    if (!abierto) return null;

    const docsExistentes = (pedido?.documentos || []).filter((d) => !docsEliminar.includes(d.id));

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-4xl w-full flex flex-col text-left`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
                onPaste={handlePaste}
            >
                <GeliaLoader isVisible={processing} message="Guardando pedido_" />
                <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <h2 className="text-xl md:text-2xl font-black italic theme-text-main uppercase tracking-tighter m-0">
                        {modoEdicion ? 'Editar Pedido_' : 'Nuevo Pedido_'}
                    </h2>
                    <button type="button" onClick={onClose} className="p-2 rounded-full theme-text-muted hover:theme-text-main outline-none shrink-0" aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="gelia-modal-body p-5 md:p-8 space-y-8">
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
                                <label className={SECCION}>Folio</label>
                                <input type="text" readOnly value={pedido?.folio || 'Auto-generado'} className={`${THEME_INPUT} w-full py-3 opacity-60`} />
                            </div>
                            <div>
                                <label className={SECCION}>Status</label>
                                <input
                                    type="text"
                                    readOnly
                                    value={pedido?.estatus?.nombre_visual || 'Borrador'}
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
                            <label className="flex items-center gap-2 cursor-pointer theme-text-main md:col-span-2">
                                <input type="checkbox" checked={data.requiere_factura} onChange={(e) => setData('requiere_factura', e.target.checked)} />
                                <span className="text-sm font-bold">Requiere factura</span>
                            </label>
                        </div>
                    </section>

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
                                <label className={SECCION}>Peso volumétrico (kg)</label>
                                <input type="text" readOnly value={pesoVolumetrico !== '' ? pesoVolumetrico : '—'} className={`${THEME_INPUT} w-full py-3 opacity-60`} />
                            </div>
                            <div>
                                <label className={SECCION}>Peso real (kg)</label>
                                <input type="number" step="0.0001" min="0" placeholder="0.0000" value={data.peso_real_kg} onChange={(e) => setData('peso_real_kg', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                            </div>
                            <div>
                                <label className={SECCION}>Tipo de guía</label>
                                <select value={data.catalogo_tipo_guia_id} onChange={(e) => setData('catalogo_tipo_guia_id', e.target.value)} className={`${THEME_SELECT} w-full py-3`}>
                                    <option value="">Seleccionar...</option>
                                    {(catalogos.tipos_guia || []).map((g) => <option key={g.id} value={g.id}>{g.nombre}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className={SECCION}>Número de cajas</label>
                                <input type="number" min="1" placeholder="1" value={data.numero_cajas} onChange={(e) => setData('numero_cajas', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                            </div>
                            <div>
                                <label className={SECCION}>Peso con productos (kg)</label>
                                <input type="number" step="0.0001" min="0" placeholder="0.0000" value={data.peso_con_productos_kg} onChange={(e) => setData('peso_con_productos_kg', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                            </div>
                        </div>
                    </section>

                    {/* 3. Dirección de envío */}
                    <section className={SECCION_WRAP}>
                        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-3">
                            <p className={`${THEME_LABEL} m-0`}>3. Dirección de envío</p>
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
                        </div>
                        {alertaDireccion && (
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
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label className={SECCION}>C.P.</label>
                                <input type="text" placeholder="Código postal" value={data.codigo_postal} onChange={(e) => setData('codigo_postal', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                            </div>
                            <div className="md:col-span-2">
                                <label className={SECCION}>Domicilio de entrega</label>
                                <textarea placeholder="Calle, colonia, municipio, estado..." value={data.domicilio_entrega} onChange={(e) => setData('domicilio_entrega', e.target.value)} className={`${THEME_TEXTAREA} w-full py-3 min-h-[80px]`} />
                            </div>
                        </div>
                    </section>

                    {/* 4. Envío y costos */}
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>4. Envío y costos</p>
                        <div className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className={SECCION}>Total mercancía</label>
                                    <input type="number" step="0.01" min="0" placeholder="0.00" value={data.total_mercancia} onChange={(e) => setData('total_mercancia', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                                </div>
                                <div>
                                    <label className={SECCION}>Envío / Tienda</label>
                                    <select value={data.catalogo_envio_tienda_id} onChange={(e) => setData('catalogo_envio_tienda_id', e.target.value)} className={`${THEME_SELECT} w-full py-3`}>
                                        <option value="">Seleccionar...</option>
                                        {(catalogos.envios_tienda || []).map((e) => <option key={e.id} value={e.id}>{e.nombre}</option>)}
                                    </select>
                                </div>
                                {envioTiendaOtro && (
                                    <div className="md:col-span-2">
                                        <label className={SECCION}>Especifique envío / tienda</label>
                                        <input type="text" placeholder="Descripción" value={data.envio_tienda_otro} onChange={(e) => setData('envio_tienda_otro', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                                    </div>
                                )}
                                <div>
                                    <label className={SECCION}>Almacén de salida</label>
                                    <select value={data.catalogo_almacen_salida_id} onChange={(e) => setData('catalogo_almacen_salida_id', e.target.value)} className={`${THEME_SELECT} w-full py-3`}>
                                        <option value="">Seleccionar...</option>
                                        {(catalogos.almacenes_salida || []).map((a) => <option key={a.id} value={a.id}>{a.nombre}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className={SECCION}>Paquetería</label>
                                    <select value={data.catalogo_paqueteria_id} onChange={(e) => manejarPaqueteria(e.target.value)} className={`${THEME_SELECT} w-full py-3`}>
                                        <option value="">Seleccionar...</option>
                                        {(catalogos.paqueterias || []).map((p) => <option key={p.id} value={p.id}>{p.nombre}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className={SECCION}>Costo de envío</label>
                                    <input type="number" step="0.01" min="0" placeholder="0.00" value={data.costo_envio} onChange={(e) => setData('costo_envio', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                                </div>
                                <div>
                                    <label className={SECCION}>Tipo de resguardo</label>
                                    <select value={data.es_resguardo ? '1' : '0'} onChange={(e) => setData('es_resguardo', e.target.value === '1')} className={`${THEME_SELECT} w-full py-3`}>
                                        <option value="0">Sin resguardo</option>
                                        <option value="1">Con resguardo</option>
                                    </select>
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
                                <label className="flex items-center gap-2 theme-text-main cursor-pointer">
                                    <input type="checkbox" checked={data.aplica_seguro} onChange={(e) => setData('aplica_seguro', e.target.checked)} />
                                    <span className="text-sm font-bold">Con seguro</span>
                                </label>
                                <label className="flex items-center gap-2 theme-text-main cursor-pointer">
                                    <input type="checkbox" checked={data.envia_a_otra_persona} onChange={(e) => setData('envia_a_otra_persona', e.target.checked)} />
                                    <span className="text-sm font-bold">Enviar a otra persona</span>
                                </label>
                            </div>

                            {(data.aplica_saldo_favor || data.aplica_seguro || data.envia_a_otra_persona) && (
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    {data.aplica_saldo_favor && (
                                        <div>
                                            <label className={SECCION}>Monto saldo a favor</label>
                                            <input type="number" step="0.01" min="0" placeholder="0.00" value={data.saldo_a_favor} onChange={(e) => setData('saldo_a_favor', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                                        </div>
                                    )}
                                    {data.aplica_seguro && (
                                        <div>
                                            <label className={SECCION}>Costo de seguro</label>
                                            <input type="number" step="0.01" min="0" placeholder="0.00" value={data.costo_seguro} onChange={(e) => setData('costo_seguro', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                                        </div>
                                    )}
                                    {data.envia_a_otra_persona && (
                                        <div className="md:col-span-2">
                                            <label className={SECCION}>Nombre del destinatario</label>
                                            <input type="text" placeholder="Nombre completo" value={data.envia_otra_persona} onChange={(e) => setData('envia_otra_persona', e.target.value)} className={`${THEME_INPUT} w-full py-3`} />
                                        </div>
                                    )}
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
                        </div>
                    </section>

                    {/* 5. Desglose de montos */}
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>5. Desglose de montos</p>
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

                    <section className="gelia-modal-footer flex flex-wrap gap-3 p-5 md:p-6 -mx-5 md:-mx-8 -mb-5 md:-mb-8">
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
                        <button type="button" onClick={() => { reset(); setPreviews([]); setInfoCliente(null); setAlertaDireccion(false); setMsgDireccion(''); }} className={`${BTN_SECONDARY} theme-element border theme-border flex items-center gap-2 outline-none`}>
                            <RotateCcw className="w-4 h-4" /> Limpiar
                        </button>
                    </section>
                    {Object.keys(errors).length > 0 && (
                        <p className="text-xs text-red-500 font-bold">Revise los campos del formulario.</p>
                    )}
                </div>
            </div>
        </div>,
        document.body
    );
}
