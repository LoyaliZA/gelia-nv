import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { X, Receipt, Search, Download, FileSpreadsheet, AlertOctagon, ExternalLink, RotateCcw, Link2, Copy, Check } from 'lucide-react';
import ZonaAdjuntoVoucher from './ZonaAdjuntoVoucher';
import { FACTURA_ACCENT, BTN_PRIMARY, BTN_SECONDARY, urlArchivoFactura, esImagenVoucher, esPdfVoucher, receptorFiscalDeFactura } from './facturasStyles';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';
import { normalizarRazonSocial, normalizarRazonSocialAlEscribir } from '../../../utils/reglasCatalogosFiscales';

const CAMPOS_FISCALES = [
    { clave: 'rfc', etiqueta: 'RFC' },
    { clave: 'codigo_postal', etiqueta: 'Código Postal' },
    { clave: 'regimen_fiscal', etiqueta: 'Régimen Fiscal' },
    { clave: 'correo_electronico', etiqueta: 'Correo Electrónico' },
    { clave: 'uso_factura', etiqueta: 'Uso de CFDI' },
    { clave: 'nombre_razon_social', etiqueta: 'Nombre (Razón Social)' },
    { clave: 'telefono', etiqueta: 'Número Telefónico' },
];

function indiceVoucherEnFactura(factura, voucherId) {
    const lista = [...(factura?.vouchers || [])].sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0));
    return lista.findIndex(v => v.id === voucherId);
}

function razonSocialDesdeFactura(factura) {
    if (!factura) return '';
    return factura.razon_social
        || factura.datos_fiscales?.nombre_razon_social
        || factura.cliente?.nombre_razon_social
        || factura.cliente?.nombre
        || '';
}

function busquedaClienteDesdeFactura(factura) {
    if (!factura) return '';
    const nc = factura.cliente?.numero_cliente || factura.datos_fiscales?.numero_cliente;
    const nombre = factura.cliente?.nombre || razonSocialDesdeFactura(factura);
    if (nc) return `${nc} — ${nombre}`;
    return razonSocialDesdeFactura(factura);
}

function clienteTieneFiscales(c) {
    if (!c) return false;
    return !!(String(c.rfc || '').trim() || String(c.nombre_razon_social || '').trim());
}

function snapshotDatosFiscales(factura) {
    return { ...(factura?.datos_fiscales || {}) };
}

function calcularResaltesFiscales(antes, despues, solicitados) {
    const keys = (solicitados?.length ? solicitados : CAMPOS_FISCALES.map(c => c.clave));
    const out = {};
    for (const k of keys) {
        const a = String(antes?.[k] ?? '').trim();
        const b = String(despues?.[k] ?? '').trim();
        if (b && a !== b) {
            out[k] = a ? 'actualizado' : 'nuevo';
        }
    }
    return out;
}

function CampoFiscalResaltado({ etiqueta, valor, resalte }) {
    const marcado = Boolean(resalte);
    return (
        <div
            className={marcado ? 'rounded-lg p-2 border' : undefined}
            style={marcado ? {
                borderColor: FACTURA_ACCENT,
                background: 'color-mix(in srgb, var(--color-primario) 10%, transparent)',
            } : undefined}
        >
            <dt className="text-[9px] font-black uppercase theme-text-muted m-0 flex items-center gap-2">
                {etiqueta}
                {marcado && (
                    <span className="normal-case tracking-normal font-black" style={{ color: FACTURA_ACCENT }}>
                        {resalte === 'nuevo' ? 'Nuevo' : 'Actualizado'}
                    </span>
                )}
            </dt>
            <dd className="text-xs font-bold theme-text-main m-0 break-all">{valor || '—'}</dd>
        </div>
    );
}

async function copiarAlPortapapeles(texto) {
    if (!texto) return false;
    if (navigator.clipboard?.writeText && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(texto);
            return true;
        } catch {
            /* fallthrough */
        }
    }
    try {
        const textArea = document.createElement('textarea');
        textArea.value = texto;
        textArea.setAttribute('readonly', '');
        textArea.style.position = 'fixed';
        textArea.style.left = '-9999px';
        document.body.appendChild(textArea);
        textArea.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(textArea);
        return ok;
    } catch {
        return false;
    }
}

export default function ModalFormFactura({ onClose, onExito, onBorradorCreado, modoEdicion = false, facturaAEditar = null }) {
    const flash = usePage().props?.flash || {};
    const trabajandoBorrador = Boolean(facturaAEditar?.estado?.nombre === 'Borrador');

    const [vouchers, setVouchers] = useState([]);
    const [vouchersConservarIds, setVouchersConservarIds] = useState(() =>
        (facturaAEditar ? (facturaAEditar.vouchers || []).map(v => v.id) : [])
    );
    const [quitarExcel, setQuitarExcel] = useState(false);
    const [busquedaCliente, setBusquedaCliente] = useState(() => busquedaClienteDesdeFactura(facturaAEditar));
    const [listaClientes, setListaClientes] = useState([]);
    const [buscandoCliente, setBuscandoCliente] = useState(false);
    const [mostrarDropdown, setMostrarDropdown] = useState(false);
    const [dragExcel, setDragExcel] = useState(false);
    const [clienteSeleccionado, setClienteSeleccionado] = useState(facturaAEditar?.cliente || null);
    const [listaReceptores, setListaReceptores] = useState([]);
    const [buscandoReceptor, setBuscandoReceptor] = useState(false);
    const [errorBusquedaReceptor, setErrorBusquedaReceptor] = useState(null);
    const [mostrarDropdownReceptor, setMostrarDropdownReceptor] = useState(false);
    const [receptorSeleccionado, setReceptorSeleccionado] = useState(() => receptorFiscalDeFactura(facturaAEditar));
    const [preguntarVinculo, setPreguntarVinculo] = useState(false);
    const [pedirFormulario, setPedirFormulario] = useState(false);
    const [camposSeleccionados, setCamposSeleccionados] = useState(() =>
        facturaAEditar?.campos_fiscales_solicitados?.length
            ? [...facturaAEditar.campos_fiscales_solicitados]
            : CAMPOS_FISCALES.map(c => c.clave)
    );
    const [enlaceUrl, setEnlaceUrl] = useState(() => {
        const vigentes = (facturaAEditar?.enlaces_fiscales || facturaAEditar?.enlacesFiscales || [])
            .find(e => !e.usado_en && !e.revocado_en);
        return vigentes?.url || flash.enlace_fiscal_url || null;
    });
    const [copiado, setCopiado] = useState(false);
    const [facturaLive, setFacturaLive] = useState(facturaAEditar);
    const [camposResaltados, setCamposResaltados] = useState({});
    const [excelExpandido, setExcelExpandido] = useState(false);
    const excelInputRef = useRef(null);
    const debounceRef = useRef(null);
    const abortBusquedaCliente = useRef(null);
    const debounceReceptorRef = useRef(null);
    const abortBusquedaReceptor = useRef(null);
    const snapshotFiscalRef = useRef(snapshotDatosFiscales(facturaAEditar));
    const factura = facturaLive || facturaAEditar;

    const { data, setData, post, processing, errors, transform } = useForm({
        razon_social: facturaAEditar ? razonSocialDesdeFactura(facturaAEditar) : '',
        numero_cliente: facturaAEditar
            ? (facturaAEditar?.cliente?.numero_cliente || facturaAEditar?.datos_fiscales?.numero_cliente || '')
            : '',
        destinatario_tipo: facturaAEditar?.destinatario_tipo || 'cliente',
        receptor_fiscal_id: facturaAEditar?.receptor_fiscal_id || facturaAEditar?.receptor_fiscal?.id || '',
        vincular_receptor_cliente: false,
        observaciones_vendedor: facturaAEditar ? (facturaAEditar?.observaciones_vendedor || '') : '',
        archivo_fiscal: null,
        modo: 'pendiente',
        pedir_formulario: false,
        accion_formulario: 'register_first',
        campos_fiscales: CAMPOS_FISCALES.map(c => c.clave),
    });

    const tieneFiscalesCliente = useMemo(() => clienteTieneFiscales(clienteSeleccionado), [clienteSeleccionado]);

    useEffect(() => {
        if (!facturaAEditar) {
            setFacturaLive(null);
            setCamposResaltados({});
            snapshotFiscalRef.current = {};
            return;
        }

        setFacturaLive(facturaAEditar);
        setCamposResaltados({});
        snapshotFiscalRef.current = snapshotDatosFiscales(facturaAEditar);

        setData({
            razon_social: razonSocialDesdeFactura(facturaAEditar),
            numero_cliente: facturaAEditar.cliente?.numero_cliente || facturaAEditar.datos_fiscales?.numero_cliente || '',
            destinatario_tipo: facturaAEditar.destinatario_tipo || 'cliente',
            receptor_fiscal_id: facturaAEditar.receptor_fiscal_id || facturaAEditar.receptor_fiscal?.id || '',
            vincular_receptor_cliente: false,
            observaciones_vendedor: facturaAEditar.observaciones_vendedor || '',
            archivo_fiscal: null,
            modo: trabajandoBorrador ? 'borrador' : 'pendiente',
            pedir_formulario: false,
            accion_formulario: tieneFiscalesCliente ? 'update_fields' : 'register_first',
            campos_fiscales: facturaAEditar.campos_fiscales_solicitados || CAMPOS_FISCALES.map(c => c.clave),
        });
        setBusquedaCliente(busquedaClienteDesdeFactura(facturaAEditar));
        setClienteSeleccionado(facturaAEditar.cliente || null);
        setReceptorSeleccionado(receptorFiscalDeFactura(facturaAEditar));
        setVouchers([]);
        setVouchersConservarIds((facturaAEditar.vouchers || []).map(v => v.id));
        setQuitarExcel(false);
        setExcelExpandido(Boolean(facturaAEditar.tiene_archivo_fiscal));
        if (facturaAEditar.campos_fiscales_solicitados?.length) {
            setCamposSeleccionados([...facturaAEditar.campos_fiscales_solicitados]);
        }
        const vigentes = (facturaAEditar.enlaces_fiscales || facturaAEditar.enlacesFiscales || [])
            .find(e => !e.usado_en && !e.revocado_en);
        if (vigentes?.url) setEnlaceUrl(vigentes.url);
    }, [facturaAEditar?.id, setData]);

    // Live: misma solicitud, datos fiscales llegan vía Index/Echo sin remount.
    useEffect(() => {
        if (!facturaAEditar?.id || !facturaAEditar.formulario_respondido_at) return;
        if (facturaLive?.formulario_respondido_at
            && facturaLive.id === facturaAEditar.id
            && JSON.stringify(facturaLive.datos_fiscales || {}) === JSON.stringify(facturaAEditar.datos_fiscales || {})) {
            return;
        }

        const antes = snapshotFiscalRef.current || {};
        const despues = facturaAEditar.datos_fiscales || {};
        setCamposResaltados(calcularResaltesFiscales(
            antes,
            despues,
            facturaAEditar.campos_fiscales_solicitados,
        ));
        snapshotFiscalRef.current = snapshotDatosFiscales(facturaAEditar);
        setFacturaLive(facturaAEditar);
        setClienteSeleccionado(facturaAEditar.cliente || null);
        setReceptorSeleccionado(receptorFiscalDeFactura(facturaAEditar));
        setData(prev => ({
            ...prev,
            razon_social: razonSocialDesdeFactura(facturaAEditar) || prev.razon_social,
            numero_cliente: facturaAEditar.cliente?.numero_cliente
                || facturaAEditar.datos_fiscales?.numero_cliente
                || prev.numero_cliente,
            destinatario_tipo: facturaAEditar.destinatario_tipo || prev.destinatario_tipo,
            receptor_fiscal_id: facturaAEditar.receptor_fiscal_id
                || facturaAEditar.receptor_fiscal?.id
                || prev.receptor_fiscal_id,
        }));
        if (facturaAEditar.cliente) {
            setBusquedaCliente(busquedaClienteDesdeFactura(facturaAEditar));
        }
    }, [
        facturaAEditar?.id,
        facturaAEditar?.formulario_respondido_at,
        facturaAEditar?.razon_social,
        facturaAEditar?.datos_fiscales,
        setData,
    ]);

    useEffect(() => {
        const solicitudId = facturaAEditar?.id;
        if (!solicitudId || typeof window === 'undefined' || !window.Echo) return;

        const channel = window.Echo.private('solicitudes.facturas');
        const handler = async (payload) => {
            if (Number(payload.solicitud_id) !== Number(solicitudId)) return;
            if (payload.accion !== 'formulario_respondido' && payload.accion !== 'actualizada') return;

            try {
                const res = await axios.get(route('facturas.show', solicitudId));
                const f = res.data?.factura;
                if (!f) return;

                const antes = snapshotFiscalRef.current || {};
                const despues = f.datos_fiscales || {};
                setCamposResaltados(calcularResaltesFiscales(
                    antes,
                    despues,
                    f.campos_fiscales_solicitados,
                ));
                snapshotFiscalRef.current = snapshotDatosFiscales(f);
                setFacturaLive(f);
                setClienteSeleccionado(f.cliente || null);
                setReceptorSeleccionado(receptorFiscalDeFactura(f));
                setData(prev => ({
                    ...prev,
                    razon_social: razonSocialDesdeFactura(f) || prev.razon_social,
                    numero_cliente: f.cliente?.numero_cliente || f.datos_fiscales?.numero_cliente || prev.numero_cliente,
                    destinatario_tipo: f.destinatario_tipo || prev.destinatario_tipo,
                    receptor_fiscal_id: f.receptor_fiscal_id || f.receptor_fiscal?.id || prev.receptor_fiscal_id,
                    observaciones_vendedor: f.observaciones_vendedor ?? prev.observaciones_vendedor,
                }));
                if (f.cliente) {
                    setBusquedaCliente(busquedaClienteDesdeFactura(f));
                }
                onBorradorCreado?.(f);
            } catch {
                /* ignore */
            }
        };

        channel.listen('.solicitud-factura.actualizada', handler);
        return () => {
            channel.stopListening('.solicitud-factura.actualizada', handler);
        };
    }, [facturaAEditar?.id, setData, onBorradorCreado]);

    useEffect(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        if (busquedaCliente.trim().length < 2) {
            abortBusquedaCliente.current?.abort();
            setListaClientes([]);
            return;
        }
        debounceRef.current = setTimeout(async () => {
            abortBusquedaCliente.current?.abort();
            const controller = new AbortController();
            abortBusquedaCliente.current = controller;
            setBuscandoCliente(true);
            try {
                const res = await axios.get(route('api.clientes.index'), {
                    params: { q: busquedaCliente.trim(), con_fiscales: 1 },
                    signal: controller.signal,
                });
                setListaClientes(res.data || []);
            } catch (err) {
                if (!axios.isCancel(err) && err?.code !== 'ERR_CANCELED') {
                    setListaClientes([]);
                }
            } finally {
                if (!controller.signal.aborted) {
                    setBuscandoCliente(false);
                }
            }
        }, 400);
        return () => clearTimeout(debounceRef.current);
    }, [busquedaCliente]);

    useEffect(() => {
        if (data.destinatario_tipo !== 'tercero' || pedirFormulario) {
            abortBusquedaReceptor.current?.abort();
            setListaReceptores([]);
            setErrorBusquedaReceptor(null);
            return;
        }
        const q = String(data.razon_social || '').trim();
        const clienteId = clienteSeleccionado?.id || null;
        if (
            receptorSeleccionado
            && normalizarRazonSocial(q) === normalizarRazonSocial(receptorSeleccionado.nombre_razon_social)
        ) {
            setListaReceptores([]);
            setErrorBusquedaReceptor(null);
            return;
        }
        if (q.length < 2 && !clienteId) {
            abortBusquedaReceptor.current?.abort();
            setListaReceptores([]);
            setErrorBusquedaReceptor(null);
            return;
        }
        if (debounceReceptorRef.current) clearTimeout(debounceReceptorRef.current);
        debounceReceptorRef.current = setTimeout(async () => {
            abortBusquedaReceptor.current?.abort();
            const controller = new AbortController();
            abortBusquedaReceptor.current = controller;
            setBuscandoReceptor(true);
            setErrorBusquedaReceptor(null);
            try {
                const url = route('facturas.receptores.buscar');
                const res = await axios.get(url, {
                    params: { q: q.length >= 2 ? q : '', cliente_id: clienteId || undefined },
                    signal: controller.signal,
                });
                const rows = res.data?.data || [];
                setListaReceptores(rows);
                setMostrarDropdownReceptor(rows.length > 0);
            } catch (err) {
                if (axios.isCancel(err) || err?.code === 'ERR_CANCELED') {
                    return;
                }
                setListaReceptores([]);
                const status = err?.response?.status;
                setErrorBusquedaReceptor(
                    status === 403
                        ? 'Sin permiso para buscar terceros.'
                        : status === 404
                            ? 'Ruta de búsqueda no disponible (recargue la página).'
                            : 'No se pudo buscar en el padrón de terceros.'
                );
            } finally {
                if (!controller.signal.aborted) {
                    setBuscandoReceptor(false);
                }
            }
        }, 300);
        return () => {
            clearTimeout(debounceReceptorRef.current);
            abortBusquedaReceptor.current?.abort();
        };
    }, [data.razon_social, data.destinatario_tipo, clienteSeleccionado?.id, pedirFormulario, receptorSeleccionado]);

    useEffect(() => {
        const accion = tieneFiscalesCliente && data.destinatario_tipo === 'cliente'
            ? 'update_fields'
            : 'register_first';
        setData('accion_formulario', accion);
        if (!tieneFiscalesCliente || data.destinatario_tipo === 'tercero') {
            setCamposSeleccionados(CAMPOS_FISCALES.map(c => c.clave));
        }
    }, [tieneFiscalesCliente, data.destinatario_tipo]);

    const verificarVinculoReceptor = async (receptorId, cliente) => {
        if (!receptorId || !cliente?.id) return;
        try {
            const res = await axios.get(route('facturas.receptores.buscar'), {
                params: { cliente_id: cliente.id },
            });
            const vinculados = res.data?.vinculados_ids || [];
            if (!vinculados.includes(receptorId)) {
                setPreguntarVinculo(true);
            }
        } catch {
            /* ignore */
        }
    };

    const seleccionarCliente = (c) => {
        setClienteSeleccionado(c);
        setData(prev => ({
            ...prev,
            numero_cliente: c.numero_cliente,
            // Tercero: el nombre del cliente es quien solicita; la razón social viene del form.
            razon_social: prev.destinatario_tipo === 'tercero'
                ? (prev.razon_social || '')
                : (c.nombre_razon_social || c.nombre || prev.razon_social),
        }));
        setBusquedaCliente(`${c.numero_cliente} — ${c.nombre}`);
        setMostrarDropdown(false);
        if (data.destinatario_tipo === 'tercero' && receptorSeleccionado) {
            verificarVinculoReceptor(receptorSeleccionado.id, c);
        }
    };

    const seleccionarReceptor = (r) => {
        setReceptorSeleccionado(r);
        setData(prev => ({
            ...prev,
            receptor_fiscal_id: r.id,
            razon_social: normalizarRazonSocial(r.nombre_razon_social),
        }));
        setListaReceptores([]);
        setMostrarDropdownReceptor(false);
        if (clienteSeleccionado) {
            verificarVinculoReceptor(r.id, clienteSeleccionado);
        }
    };

    const toggleCampo = (clave) => {
        setCamposSeleccionados(prev => {
            if (prev.includes(clave)) {
                return prev.filter(k => k !== clave);
            }
            return [...prev, clave];
        });
    };

    const vouchersExistentesUi = useMemo(() => {
        if (!factura) return [];

        return (factura.vouchers || [])
            .filter(v => vouchersConservarIds.includes(v.id))
            .map(v => {
                const indice = indiceVoucherEnFactura(factura, v.id);
                const verUrl = indice >= 0 ? urlArchivoFactura(factura.id, 'voucher', indice) : null;
                const esImagen = esImagenVoucher(v);
                return {
                    id: v.id,
                    label: v.nombre_original || `Voucher ${indice + 1}`,
                    previewUrl: esImagen && verUrl ? verUrl : null,
                    verUrl,
                    esPdf: esPdfVoucher(v),
                };
            });
    }, [factura, vouchersConservarIds]);

    const tieneExcelActual = factura?.tiene_archivo_fiscal && !quitarExcel && !data.archivo_fiscal;

    const totalVouchers = (trabajandoBorrador || modoEdicion)
        ? vouchersConservarIds.length + vouchers.length
        : vouchers.length;

    const formPendiente = Boolean(factura?.formulario_enviado_at && !factura?.formulario_respondido_at);

    const previewFiscalFuente = useMemo(() => {
        if (data.destinatario_tipo === 'tercero') {
            return receptorSeleccionado || factura?.datos_fiscales || null;
        }
        if (factura?.datos_fiscales && Object.keys(factura.datos_fiscales).length) {
            return { ...clienteSeleccionado, ...factura.datos_fiscales };
        }
        return clienteSeleccionado;
    }, [data.destinatario_tipo, receptorSeleccionado, factura?.datos_fiscales, clienteSeleccionado]);

    const construirPayloadBase = (extras = {}) => ({
        ...data,
        ...extras,
        campos_fiscales: camposSeleccionados,
        pedir_formulario: pedirFormulario ? '1' : '0',
        accion_formulario: tieneFiscalesCliente && data.destinatario_tipo === 'cliente' && camposSeleccionados.length < CAMPOS_FISCALES.length
            ? 'update_fields'
            : (tieneFiscalesCliente && data.destinatario_tipo === 'cliente' ? 'update_fields' : 'register_first'),
    });

    const guardarBorrador = (e) => {
        e.preventDefault();
        if (pedirFormulario && camposSeleccionados.length === 0) return;

        const config = {
            forceFormData: true,
            onSuccess: (page) => {
                const url = page?.props?.flash?.enlace_fiscal_url;
                const borradorId = page?.props?.flash?.factura_borrador_id;
                if (url) {
                    setEnlaceUrl(url);
                    setFacturaLive(prev => ({
                        ...(prev || facturaAEditar || {}),
                        id: borradorId || prev?.id || facturaAEditar?.id,
                        formulario_enviado_at: new Date().toISOString(),
                        formulario_respondido_at: null,
                    }));
                    snapshotFiscalRef.current = snapshotDatosFiscales(facturaLive || facturaAEditar);
                    copiarAlPortapapeles(url).then(ok => {
                        if (ok) {
                            setCopiado(true);
                            setTimeout(() => setCopiado(false), 2000);
                        }
                    });
                }
                if (borradorId) {
                    axios.get(route('facturas.show', borradorId))
                        .then((res) => {
                            if (res.data?.factura) {
                                onBorradorCreado?.(res.data.factura);
                            }
                        })
                        .catch(() => {
                            onBorradorCreado?.({ id: borradorId });
                        });
                }
                onExito?.();
            },
        };

        if (trabajandoBorrador && facturaAEditar?.id) {
            transform(d => {
                const payload = construirPayloadBase({
                    ...d,
                    _method: 'put',
                    vouchers_conservar: vouchersConservarIds,
                    enviar_ahora: '0',
                });
                if (vouchers.length > 0) payload.vouchers = vouchers;
                if (quitarExcel) payload.eliminar_archivo_fiscal = '1';
                return payload;
            });
            post(route('facturas.borrador', facturaAEditar.id), config);
            return;
        }

        transform(d => ({
            ...construirPayloadBase(d),
            modo: 'borrador',
            vouchers,
        }));
        post(route('facturas.store'), config);
    };

    const enviar = (e) => {
        e.preventDefault();
        const config = {
            forceFormData: true,
            onSuccess: () => {
                onExito?.();
                onClose();
            },
        };

        if (modoEdicion && !trabajandoBorrador) {
            transform(d => {
                const payload = { ...d, _method: 'put', vouchers_conservar: vouchersConservarIds };
                if (vouchers.length > 0) payload.vouchers = vouchers;
                if (quitarExcel) payload.eliminar_archivo_fiscal = '1';
                return payload;
            });
            post(route('facturas.reparar', facturaAEditar.id), config);
            return;
        }

        if (trabajandoBorrador && facturaAEditar?.id) {
            transform(d => {
                const payload = construirPayloadBase({
                    ...d,
                    _method: 'put',
                    vouchers_conservar: vouchersConservarIds,
                    pedir_formulario: '0',
                    enviar_ahora: '1',
                });
                if (vouchers.length > 0) payload.vouchers = vouchers;
                if (quitarExcel) payload.eliminar_archivo_fiscal = '1';
                return payload;
            });
            post(route('facturas.borrador', facturaAEditar.id), config);
            return;
        }

        transform(d => ({
            ...construirPayloadBase({ ...d, modo: 'pendiente', pedir_formulario: '0' }),
            vouchers,
        }));
        post(route('facturas.store'), config);
    };

    const regenerarEnlace = async () => {
        if (!facturaAEditar?.id) return;
        try {
            const { data: res } = await axios.post(route('facturas.enlace_fiscal', facturaAEditar.id), {
                campos_fiscales: camposSeleccionados,
                accion_formulario: data.accion_formulario,
            });
            if (res?.url) {
                setEnlaceUrl(res.url);
                setFacturaLive(prev => ({
                    ...(prev || facturaAEditar || {}),
                    formulario_enviado_at: new Date().toISOString(),
                    formulario_respondido_at: null,
                }));
                snapshotFiscalRef.current = snapshotDatosFiscales(facturaLive || facturaAEditar);
                const ok = await copiarAlPortapapeles(res.url);
                if (ok) {
                    setCopiado(true);
                    setTimeout(() => setCopiado(false), 2000);
                }
            }
        } catch {
            /* ignore */
        }
    };

    const puedeEnviarDirecto = !processing && totalVouchers >= 1 && !formPendiente;
    const puedeGuardarBorrador = !processing
        && (!pedirFormulario || camposSeleccionados.length > 0)
        && (
            data.destinatario_tipo === 'tercero'
                ? Boolean(data.numero_cliente)
                : data.razon_social.trim().length >= 3
        );

    const voucherError = errors.vouchers || errors['vouchers.0'] || errors.vouchers_conservar;
    const titulo = modoEdicion && !trabajandoBorrador
        ? 'Reparar Solicitud de Factura_'
        : trabajandoBorrador
            ? 'Editar Borrador de Factura_'
            : 'Nueva Solicitud de Factura_';

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-2xl w-full flex flex-col text-left`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={e => e.stopPropagation()}
            >
                <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <div className="flex items-center gap-3 min-w-0">
                        <Receipt className="w-7 h-7 shrink-0" style={{ color: FACTURA_ACCENT }} />
                        <div className="min-w-0">
                            <h2 className="text-lg font-black italic theme-text-main uppercase tracking-tighter m-0">
                                {titulo}
                            </h2>
                            {facturaAEditar?.folio && (
                                <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-1 m-0">{facturaAEditar.folio}</p>
                            )}
                        </div>
                    </div>
                    <button type="button" onClick={onClose} className="p-2 theme-text-muted hover:theme-text-main rounded-full hover:bg-black/5 dark:hover:bg-white/5 transition-colors outline-none shrink-0"><X className="w-5 h-5" /></button>
                </div>

                <form onSubmit={enviar} className="gelia-modal-body p-5 md:p-6 overflow-y-auto custom-scrollbar flex-1 min-h-0 space-y-6">
                    {modoEdicion && facturaAEditar?.motivo_respuesta && (
                        <div className="p-4 rounded-2xl border border-red-500/30 bg-red-500/10 flex gap-3">
                            <AlertOctagon className="w-5 h-5 text-red-500 shrink-0 mt-0.5" />
                            <div>
                                <p className="text-[9px] font-black uppercase tracking-widest text-red-600 dark:text-red-400 m-0 mb-1">Motivo del error</p>
                                <p className="text-xs font-bold theme-text-main m-0 leading-snug">{facturaAEditar.motivo_respuesta}</p>
                            </div>
                        </div>
                    )}

                    {!modoEdicion || trabajandoBorrador ? (
                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Facturar a_</label>
                            <div className="grid grid-cols-2 gap-2">
                                {[
                                    { value: 'cliente', label: 'Cliente de cuenta' },
                                    { value: 'tercero', label: 'Un Tercero' },
                                ].map(opt => (
                                    <button
                                        key={opt.value}
                                        type="button"
                                        onClick={() => setData('destinatario_tipo', opt.value)}
                                        className={`px-3 py-3 rounded-xl border text-[10px] font-black uppercase tracking-widest outline-none transition-colors ${
                                            data.destinatario_tipo === opt.value
                                                ? 'border-[var(--color-primario)] bg-[color-mix(in_srgb,var(--color-primario)_12%,transparent)] theme-text-main'
                                                : 'theme-border theme-element theme-text-muted'
                                        }`}
                                    >
                                        {opt.label}
                                    </button>
                                ))}
                            </div>
                            {data.destinatario_tipo === 'tercero' && (
                                <p className="text-[10px] theme-text-muted m-0 ml-1">
                                    Separe la cuenta que compra de quien factura (tercero).
                                </p>
                            )}
                        </div>
                    ) : null}

                    <div className="space-y-2 relative">
                        <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">
                            {data.destinatario_tipo === 'tercero' ? 'Cuenta que compra_' : 'Cliente_'}
                        </label>
                        {data.destinatario_tipo === 'tercero' && (
                            <p className="text-[10px] theme-text-muted m-0 ml-1">
                                Número de cliente que presta la cuenta; no es quien factura.
                            </p>
                        )}
                        <div className="relative">
                            <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                            <input
                                type="text"
                                value={busquedaCliente}
                                onChange={e => { setBusquedaCliente(e.target.value); setMostrarDropdown(true); }}
                                onFocus={() => setMostrarDropdown(true)}
                                placeholder="Buscar por número o nombre…"
                                className="w-full pl-11 pr-4 py-3 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none"
                            />
                        </div>
                        {mostrarDropdown && listaClientes.length > 0 && (
                            <div className="absolute top-full mt-1 left-0 right-0 z-50 theme-surface border theme-border rounded-xl shadow-xl max-h-48 overflow-y-auto">
                                {listaClientes.map(c => (
                                    <button key={c.id} type="button" onClick={() => seleccionarCliente(c)} className="w-full text-left px-4 py-3 text-xs font-bold theme-text-main hover:bg-[color-mix(in_srgb,var(--color-primario)_10%,transparent)] outline-none">
                                        {c.numero_cliente} — {c.nombre}
                                    </button>
                                ))}
                            </div>
                        )}
                        {buscandoCliente && <p className="text-[10px] theme-text-muted m-0">Buscando…</p>}
                    </div>

                    {clienteSeleccionado && data.destinatario_tipo === 'tercero' && (
                        <div className="p-4 rounded-2xl border theme-border theme-element space-y-1">
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Cuenta que compra</p>
                            <p className="text-sm font-black theme-text-main m-0">
                                {clienteSeleccionado.numero_cliente} — {clienteSeleccionado.nombre}
                            </p>
                            <p className="text-[10px] theme-text-muted m-0">
                                Este nombre identifica la cuenta. No es la razón social a facturar.
                            </p>
                        </div>
                    )}

                    {clienteSeleccionado && data.destinatario_tipo === 'cliente' && (
                        <div className="p-4 rounded-2xl border theme-border theme-element space-y-2">
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Preview datos fiscales actuales</p>
                            {clienteTieneFiscales(previewFiscalFuente) || (previewFiscalFuente && Object.keys(camposResaltados).length > 0) ? (
                                <dl className="grid grid-cols-1 sm:grid-cols-2 gap-2 m-0">
                                    {CAMPOS_FISCALES.map(({ clave, etiqueta }) => (
                                        <CampoFiscalResaltado
                                            key={clave}
                                            etiqueta={etiqueta}
                                            valor={previewFiscalFuente?.[clave]}
                                            resalte={camposResaltados[clave]}
                                        />
                                    ))}
                                </dl>
                            ) : (
                                <p className="text-xs font-bold theme-text-muted m-0">Sin datos fiscales registrados. Puede pedirlos por formulario.</p>
                            )}
                        </div>
                    )}

                    {!(data.destinatario_tipo === 'tercero' && pedirFormulario && !factura?.formulario_respondido_at) && (
                        <div className="space-y-2 relative">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">
                                {data.destinatario_tipo === 'tercero' ? 'Quién factura (tercero)_' : 'Razón Social_'}
                            </label>
                            <div className="relative">
                                {data.destinatario_tipo === 'tercero' && (
                                    <Search className="absolute left-4 top-1/2 -translate-y-1/2 w-4 h-4 theme-text-muted" />
                                )}
                                <input
                                    required={!(data.destinatario_tipo === 'tercero' && pedirFormulario)}
                                    value={data.razon_social}
                                    onChange={(e) => {
                                        const val = normalizarRazonSocialAlEscribir(e.target.value);
                                        const coincide = receptorSeleccionado
                                            && normalizarRazonSocial(val) === normalizarRazonSocial(receptorSeleccionado.nombre_razon_social);
                                        if (receptorSeleccionado && !coincide) {
                                            setReceptorSeleccionado(null);
                                        }
                                        setData(prev => ({
                                            ...prev,
                                            razon_social: val,
                                            receptor_fiscal_id: coincide ? prev.receptor_fiscal_id : '',
                                        }));
                                        if (data.destinatario_tipo === 'tercero') {
                                            setMostrarDropdownReceptor(true);
                                        }
                                    }}
                                    onFocus={() => {
                                        if (data.destinatario_tipo === 'tercero' && listaReceptores.length > 0) {
                                            setMostrarDropdownReceptor(true);
                                        }
                                    }}
                                    onBlur={(e) => setData('razon_social', normalizarRazonSocial(e.target.value))}
                                    className={`w-full ${data.destinatario_tipo === 'tercero' ? 'pl-11' : 'px-4'} pr-4 py-3 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none`}
                                    placeholder={data.destinatario_tipo === 'tercero'
                                        ? 'Buscar por razón social, RFC o TF-…'
                                        : 'Nombre o razón social a facturar'}
                                    autoComplete="off"
                                />
                            </div>
                            {data.destinatario_tipo === 'tercero' && mostrarDropdownReceptor && listaReceptores.length > 0 && (
                                <div className="absolute top-full mt-1 left-0 right-0 z-50 theme-surface border theme-border rounded-xl shadow-xl max-h-48 overflow-y-auto">
                                    {listaReceptores.map(r => (
                                        <button
                                            key={r.id}
                                            type="button"
                                            onMouseDown={(e) => e.preventDefault()}
                                            onClick={() => seleccionarReceptor(r)}
                                            className="w-full text-left px-4 py-3 text-xs font-bold theme-text-main hover:bg-[color-mix(in_srgb,var(--color-primario)_10%,transparent)] outline-none"
                                        >
                                            {r.codigo_interno} — {r.nombre_razon_social}{r.rfc ? ` (${r.rfc})` : ''}
                                        </button>
                                    ))}
                                </div>
                            )}
                            {data.destinatario_tipo === 'tercero' && buscandoReceptor && (
                                <p className="text-[10px] theme-text-muted m-0">Buscando en padrón de terceros…</p>
                            )}
                            {data.destinatario_tipo === 'tercero' && errorBusquedaReceptor && (
                                <p className="text-[10px] text-red-500 font-bold m-0">{errorBusquedaReceptor}</p>
                            )}
                            {data.destinatario_tipo === 'tercero' && (receptorSeleccionado || (factura?.formulario_respondido_at && previewFiscalFuente)) && (
                                <div className="p-4 rounded-2xl border theme-border theme-element space-y-2">
                                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">
                                        Preview datos fiscales del tercero
                                    </p>
                                    {receptorSeleccionado?.codigo_interno && (
                                        <p className="text-[10px] font-black theme-text-main m-0">
                                            {receptorSeleccionado.codigo_interno}
                                        </p>
                                    )}
                                    <dl className="grid grid-cols-1 sm:grid-cols-2 gap-2 m-0">
                                        {CAMPOS_FISCALES.map(({ clave, etiqueta }) => (
                                            <CampoFiscalResaltado
                                                key={clave}
                                                etiqueta={etiqueta}
                                                valor={(receptorSeleccionado || previewFiscalFuente)?.[clave]}
                                                resalte={camposResaltados[clave]}
                                            />
                                        ))}
                                    </dl>
                                </div>
                            )}
                            {data.destinatario_tipo === 'tercero' && !receptorSeleccionado && String(data.razon_social || '').trim().length >= 2 && !buscandoReceptor && (
                                <p className="text-[10px] theme-text-muted m-0">
                                    Escriba para buscar un tercero registrado, o deje el nombre si es nuevo.
                                </p>
                            )}
                            {errors.razon_social && <p className="text-xs text-red-500">{errors.razon_social}</p>}
                        </div>
                    )}

                    {data.destinatario_tipo === 'tercero' && pedirFormulario && !factura?.formulario_respondido_at && (
                        <div className="p-4 rounded-2xl border border-dashed theme-border space-y-1">
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Quién factura (tercero)</p>
                            <p className="text-xs font-bold theme-text-main m-0">Pendiente del formulario</p>
                            <p className="text-[10px] theme-text-muted m-0">
                                La capturará el tercero en el formulario público — no se mezcla con el nombre de la cuenta.
                            </p>
                        </div>
                    )}

                    {(!modoEdicion || trabajandoBorrador) && (
                        <div
                            className="space-y-3 p-4 rounded-2xl border-2"
                            style={{
                                borderColor: 'color-mix(in srgb, var(--color-primario) 45%, transparent)',
                                background: 'color-mix(in srgb, var(--color-primario) 6%, transparent)',
                            }}
                        >
                            <label className="flex items-start gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={pedirFormulario}
                                    onChange={e => setPedirFormulario(e.target.checked)}
                                    className="mt-1"
                                />
                                <span>
                                    <span className="text-[10px] font-black uppercase tracking-widest theme-text-main block">
                                        Pedir datos por formulario
                                    </span>
                                    <span className="text-[10px] theme-text-muted">
                                        Camino principal: guarda borrador y genera link. Cuando respondan, adjunte el voucher y envíe a encargada.
                                    </span>
                                </span>
                            </label>

                            {pedirFormulario && (
                                <div className="space-y-2 pl-7">
                                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">
                                        {tieneFiscalesCliente && data.destinatario_tipo === 'cliente'
                                            ? 'Campos a actualizar'
                                            : 'Campos a solicitar (primera vez)'}
                                    </p>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                        {CAMPOS_FISCALES.map(({ clave, etiqueta }) => {
                                            const bloqueado = !tieneFiscalesCliente || data.destinatario_tipo === 'tercero';
                                            return (
                                                <label key={clave} className="flex items-center gap-2 text-xs font-bold theme-text-main">
                                                    <input
                                                        type="checkbox"
                                                        checked={camposSeleccionados.includes(clave)}
                                                        disabled={bloqueado}
                                                        onChange={() => toggleCampo(clave)}
                                                    />
                                                    {etiqueta}
                                                </label>
                                            );
                                        })}
                                    </div>
                                    {errors.campos_fiscales && <p className="text-xs text-red-500">{errors.campos_fiscales}</p>}
                                </div>
                            )}

                            {(enlaceUrl || trabajandoBorrador) && (
                                <div className="space-y-2 pl-0 pt-2 border-t theme-border">
                                    {enlaceUrl && (
                                        <div className="flex flex-wrap items-center gap-2">
                                            <input
                                                readOnly
                                                value={enlaceUrl}
                                                className="flex-1 min-w-0 px-3 py-2 rounded-xl theme-surface border theme-border text-[11px] font-mono theme-text-main"
                                            />
                                            <button
                                                type="button"
                                                className={`${BTN_SECONDARY} !py-2`}
                                                onClick={async () => {
                                                    const ok = await copiarAlPortapapeles(enlaceUrl);
                                                    if (ok) {
                                                        setCopiado(true);
                                                        setTimeout(() => setCopiado(false), 2000);
                                                    }
                                                }}
                                            >
                                                {copiado ? <Check className="w-4 h-4" /> : <Copy className="w-4 h-4" />}
                                                {copiado ? 'Copiado' : 'Copiar'}
                                            </button>
                                        </div>
                                    )}
                                    {trabajandoBorrador && (
                                        <button type="button" onClick={regenerarEnlace} className={`${BTN_SECONDARY} !py-2`}>
                                            <Link2 className="w-4 h-4" /> Regenerar enlace
                                        </button>
                                    )}
                                </div>
                            )}
                        </div>
                    )}

                    <div className="space-y-2">
                        <button
                            type="button"
                            onClick={() => setExcelExpandido(v => !v)}
                            className="w-full flex items-center justify-between gap-2 px-1 py-1 outline-none"
                        >
                            <span className="text-[10px] font-black uppercase theme-text-muted tracking-widest text-left">
                                Otra forma de cargar datos (Excel)_
                            </span>
                            <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">
                                {excelExpandido ? 'Ocultar' : 'Mostrar'}
                            </span>
                        </button>

                        {excelExpandido && (
                            <div className="space-y-2 p-4 rounded-2xl border theme-border">
                                <div className="flex items-center justify-between ml-1">
                                    <p className="text-[10px] theme-text-muted m-0">
                                        Usar Excel en lugar del formulario. Si ya hay link pendiente, primero debe responderse o regenerarse.
                                    </p>
                                    <a href={route('facturas.plantilla_fiscales')} className="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-widest hover:underline shrink-0 ml-2" style={{ color: FACTURA_ACCENT }}>
                                        <Download className="w-3.5 h-3.5" /> Plantilla
                                    </a>
                                </div>

                                {(data.archivo_fiscal || tieneExcelActual) && !pedirFormulario && (
                                    <p className="text-[10px] theme-text-muted m-0">
                                        Con Excel cargado, el formulario no es necesario.
                                    </p>
                                )}

                        {tieneExcelActual && (
                            <div className="flex flex-wrap items-center gap-2 p-3 rounded-xl theme-element border theme-border bg-[color-mix(in_srgb,var(--color-primario)_6%,transparent)]">
                                <FileSpreadsheet className="w-5 h-5 shrink-0" style={{ color: FACTURA_ACCENT }} />
                                <div className="min-w-0 flex-1">
                                    <p className="text-[10px] font-bold theme-text-main m-0 truncate">Excel / CSV actual</p>
                                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Se conservará si no sube otro</p>
                                </div>
                                <a
                                    href={urlArchivoFactura(factura.id, 'fiscal')}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="p-1.5 theme-text-muted hover:theme-text-main rounded-lg outline-none"
                                    title="Ver archivo"
                                >
                                    <ExternalLink className="w-4 h-4" />
                                </a>
                                <button
                                    type="button"
                                    onClick={() => setQuitarExcel(true)}
                                    className="text-[9px] font-black uppercase tracking-widest text-red-600 dark:text-red-400 px-2 py-1 rounded-lg hover:bg-red-500/10 outline-none"
                                >
                                    Quitar
                                </button>
                            </div>
                        )}

                        {(modoEdicion || trabajandoBorrador) && quitarExcel && !data.archivo_fiscal && (
                            <div className="flex items-center justify-between gap-2 p-3 rounded-xl border border-amber-500/30 bg-amber-500/10">
                                <p className="text-[10px] font-bold text-amber-700 dark:text-amber-300 m-0">El Excel actual se eliminará al guardar.</p>
                                <button
                                    type="button"
                                    onClick={() => setQuitarExcel(false)}
                                    className="inline-flex items-center gap-1 text-[9px] font-black uppercase tracking-widest text-amber-700 dark:text-amber-300 outline-none shrink-0"
                                >
                                    <RotateCcw className="w-3.5 h-3.5" /> Deshacer
                                </button>
                            </div>
                        )}

                        <div
                            className="border-2 border-dashed theme-border rounded-2xl p-5 flex flex-col items-center gap-2 cursor-pointer"
                            style={{ borderColor: dragExcel ? FACTURA_ACCENT : undefined }}
                            onDragOver={e => { e.preventDefault(); setDragExcel(true); }}
                            onDragLeave={() => setDragExcel(false)}
                            onDrop={e => {
                                e.preventDefault();
                                setDragExcel(false);
                                if (e.dataTransfer.files[0]) {
                                    setData('archivo_fiscal', e.dataTransfer.files[0]);
                                    setQuitarExcel(false);
                                }
                            }}
                            onClick={() => excelInputRef.current?.click()}
                        >
                            <FileSpreadsheet className="w-7 h-7 theme-text-muted" style={{ color: data.archivo_fiscal ? FACTURA_ACCENT : undefined }} />
                            <p className="text-[10px] font-black theme-text-main uppercase m-0">
                                {data.archivo_fiscal ? 'Reemplazar Excel / CSV' : 'Arrastra o selecciona Excel / CSV'}
                            </p>
                            {data.archivo_fiscal && (
                                <p className="text-[10px] font-bold m-0" style={{ color: 'var(--color-primario)' }}>{data.archivo_fiscal.name}</p>
                            )}
                            <input ref={excelInputRef} type="file" className="hidden" accept=".xlsx,.xls,.csv" onChange={e => {
                                setData('archivo_fiscal', e.target.files[0] || null);
                                if (e.target.files[0]) setQuitarExcel(false);
                            }} />
                        </div>
                        {errors.archivo_fiscal && <p className="text-xs text-red-500">{errors.archivo_fiscal}</p>}
                            </div>
                        )}
                    </div>

                    <ZonaAdjuntoVoucher
                        vouchers={vouchers}
                        onChange={setVouchers}
                        error={voucherError}
                        existentes={vouchersExistentesUi}
                        onQuitarExistente={(id) => setVouchersConservarIds(prev => prev.filter(x => x !== id))}
                    />

                    <div className="space-y-2">
                        <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Observaciones_</label>
                        <textarea
                            rows={3}
                            value={data.observaciones_vendedor}
                            onChange={e => setData('observaciones_vendedor', e.target.value)}
                            className="w-full p-4 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none resize-none"
                        />
                    </div>

                    {errors.borrador && <p className="text-xs text-red-500">{errors.borrador}</p>}

                    <div className="flex flex-col gap-2">
                        {(!modoEdicion || trabajandoBorrador) && (
                            <button
                                type="button"
                                disabled={!puedeGuardarBorrador}
                                onClick={guardarBorrador}
                                className={`${BTN_SECONDARY} w-full !py-4 disabled:opacity-50`}
                            >
                                {processing ? 'Guardando…' : (pedirFormulario ? 'Guardar borrador + generar link' : 'Guardar borrador')}
                            </button>
                        )}
                        <button
                            type="submit"
                            disabled={!puedeEnviarDirecto}
                            className={`${BTN_PRIMARY} w-full !py-4 disabled:opacity-50`}
                        >
                            {processing
                                ? 'Enviando…'
                                : (modoEdicion && !trabajandoBorrador
                                    ? 'Reenviar corrección'
                                    : 'Enviar a encargada')}
                        </button>
                        {formPendiente && (
                            <p className="text-[10px] font-bold theme-text-muted text-center m-0">
                                Espere a que respondan el formulario fiscal
                            </p>
                        )}
                        {errors.enviar_ahora && <p className="text-xs text-red-500 text-center">{errors.enviar_ahora}</p>}
                    </div>
                </form>

                {preguntarVinculo && (
                    <div className="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-4" onClick={() => setPreguntarVinculo(false)}>
                        <div className="theme-surface border theme-border rounded-2xl p-6 max-w-sm w-full space-y-4" onClick={e => e.stopPropagation()}>
                            <p className="text-sm font-bold theme-text-main m-0">
                                ¿Vincular este receptor fiscal al cliente {clienteSeleccionado?.nombre || 'seleccionado'} para reutilizarlo en próximas facturas?
                            </p>
                            <div className="flex gap-2 justify-end">
                                <button type="button" onClick={() => setPreguntarVinculo(false)} className={`${BTN_SECONDARY} !py-2`}>No</button>
                                <button
                                    type="button"
                                    onClick={() => { setData('vincular_receptor_cliente', true); setPreguntarVinculo(false); }}
                                    className={`${BTN_PRIMARY} !py-2`}
                                >
                                    Sí, vincular
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </div>,
        document.body
    );
}
