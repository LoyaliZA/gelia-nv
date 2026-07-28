import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { X, Receipt, Search, Download, FileSpreadsheet, AlertOctagon, ExternalLink, RotateCcw, Link2, Copy, Check } from 'lucide-react';
import ZonaAdjuntoVoucher from './ZonaAdjuntoVoucher';
import { FACTURA_ACCENT, BTN_PRIMARY, BTN_SECONDARY, urlArchivoFactura, esImagenVoucher, esPdfVoucher } from './facturasStyles';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';

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

export default function ModalFormFactura({ onClose, onExito, modoEdicion = false, facturaAEditar = null }) {
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
    const excelInputRef = useRef(null);
    const debounceRef = useRef(null);
    const abortBusquedaCliente = useRef(null);

    const { data, setData, post, processing, errors, transform } = useForm({
        razon_social: facturaAEditar ? razonSocialDesdeFactura(facturaAEditar) : '',
        numero_cliente: facturaAEditar
            ? (facturaAEditar?.cliente?.numero_cliente || facturaAEditar?.datos_fiscales?.numero_cliente || '')
            : '',
        destinatario_tipo: facturaAEditar?.destinatario_tipo || 'cliente',
        observaciones_vendedor: facturaAEditar ? (facturaAEditar?.observaciones_vendedor || '') : '',
        archivo_fiscal: null,
        modo: 'pendiente',
        pedir_formulario: false,
        accion_formulario: 'register_first',
        campos_fiscales: CAMPOS_FISCALES.map(c => c.clave),
    });

    const tieneFiscalesCliente = useMemo(() => clienteTieneFiscales(clienteSeleccionado), [clienteSeleccionado]);

    useEffect(() => {
        if (!facturaAEditar) return;

        setData({
            razon_social: razonSocialDesdeFactura(facturaAEditar),
            numero_cliente: facturaAEditar.cliente?.numero_cliente || facturaAEditar.datos_fiscales?.numero_cliente || '',
            destinatario_tipo: facturaAEditar.destinatario_tipo || 'cliente',
            observaciones_vendedor: facturaAEditar.observaciones_vendedor || '',
            archivo_fiscal: null,
            modo: trabajandoBorrador ? 'borrador' : 'pendiente',
            pedir_formulario: false,
            accion_formulario: tieneFiscalesCliente ? 'update_fields' : 'register_first',
            campos_fiscales: facturaAEditar.campos_fiscales_solicitados || CAMPOS_FISCALES.map(c => c.clave),
        });
        setBusquedaCliente(busquedaClienteDesdeFactura(facturaAEditar));
        setClienteSeleccionado(facturaAEditar.cliente || null);
        setVouchers([]);
        setVouchersConservarIds((facturaAEditar.vouchers || []).map(v => v.id));
        setQuitarExcel(false);
        if (facturaAEditar.campos_fiscales_solicitados?.length) {
            setCamposSeleccionados([...facturaAEditar.campos_fiscales_solicitados]);
        }
    }, [facturaAEditar?.id, setData]);

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
        const accion = tieneFiscalesCliente && data.destinatario_tipo === 'cliente'
            ? 'update_fields'
            : 'register_first';
        setData('accion_formulario', accion);
        if (!tieneFiscalesCliente || data.destinatario_tipo === 'tercero') {
            setCamposSeleccionados(CAMPOS_FISCALES.map(c => c.clave));
        }
    }, [tieneFiscalesCliente, data.destinatario_tipo]);

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
        if (!facturaAEditar) return [];

        return (facturaAEditar.vouchers || [])
            .filter(v => vouchersConservarIds.includes(v.id))
            .map(v => {
                const indice = indiceVoucherEnFactura(facturaAEditar, v.id);
                const verUrl = indice >= 0 ? urlArchivoFactura(facturaAEditar.id, 'voucher', indice) : null;
                const esImagen = esImagenVoucher(v);
                return {
                    id: v.id,
                    label: v.nombre_original || `Voucher ${indice + 1}`,
                    previewUrl: esImagen && verUrl ? verUrl : null,
                    verUrl,
                    esPdf: esPdfVoucher(v),
                };
            });
    }, [facturaAEditar, vouchersConservarIds]);

    const tieneExcelActual = facturaAEditar?.tiene_archivo_fiscal && !quitarExcel && !data.archivo_fiscal;

    const totalVouchers = (trabajandoBorrador || modoEdicion)
        ? vouchersConservarIds.length + vouchers.length
        : vouchers.length;

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
                if (url) {
                    setEnlaceUrl(url);
                    copiarAlPortapapeles(url).then(ok => {
                        if (ok) {
                            setCopiado(true);
                            setTimeout(() => setCopiado(false), 2000);
                        }
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

    const puedeEnviarDirecto = !processing && totalVouchers >= 1;
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
                                    Elija el cliente de cuenta (quien solicita). La razón social a facturar es del tercero — distinta del nombre de la cuenta.
                                </p>
                            )}
                        </div>
                    ) : null}

                    <div className="space-y-2 relative">
                        <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">
                            {data.destinatario_tipo === 'tercero' ? 'Quién solicita (cliente de cuenta)_' : 'Cliente (opcional)_'}
                        </label>
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
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Quién solicita</p>
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
                            {tieneFiscalesCliente ? (
                                <dl className="grid grid-cols-1 sm:grid-cols-2 gap-2 m-0">
                                    {CAMPOS_FISCALES.map(({ clave, etiqueta }) => (
                                        <div key={clave}>
                                            <dt className="text-[9px] font-black uppercase theme-text-muted m-0">{etiqueta}</dt>
                                            <dd className="text-xs font-bold theme-text-main m-0 break-all">{clienteSeleccionado[clave] || '—'}</dd>
                                        </div>
                                    ))}
                                </dl>
                            ) : (
                                <p className="text-xs font-bold theme-text-muted m-0">Sin datos fiscales registrados. Puede pedirlos por formulario.</p>
                            )}
                        </div>
                    )}

                    {!(data.destinatario_tipo === 'tercero' && pedirFormulario) && (
                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">
                                {data.destinatario_tipo === 'tercero' ? 'Razón social a facturar (tercero)_' : 'Razón Social_'}
                            </label>
                            <input
                                required={!(data.destinatario_tipo === 'tercero' && pedirFormulario)}
                                value={data.razon_social}
                                onChange={e => setData('razon_social', e.target.value)}
                                className="w-full px-4 py-3 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none"
                                placeholder={data.destinatario_tipo === 'tercero' ? 'Nombre fiscal del tercero a facturar' : 'Nombre o razón social a facturar'}
                            />
                            {errors.razon_social && <p className="text-xs text-red-500">{errors.razon_social}</p>}
                        </div>
                    )}

                    {data.destinatario_tipo === 'tercero' && pedirFormulario && (
                        <div className="p-4 rounded-2xl border border-dashed theme-border space-y-1">
                            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Razón social a facturar</p>
                            <p className="text-xs font-bold theme-text-main m-0">
                                {facturaAEditar?.formulario_respondido_at && data.razon_social && data.razon_social !== 'Pendiente de formulario'
                                    ? data.razon_social
                                    : 'La capturará el tercero en el formulario público'}
                            </p>
                        </div>
                    )}

                    {(!modoEdicion || trabajandoBorrador) && (
                        <div className="space-y-3 p-4 rounded-2xl border theme-border">
                            <label className="flex items-start gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={pedirFormulario}
                                    onChange={e => setPedirFormulario(e.target.checked)}
                                    className="mt-1"
                                />
                                <span>
                                    <span className="text-[10px] font-black uppercase tracking-widest theme-text-main block">Pedir datos por formulario</span>
                                    <span className="text-[10px] theme-text-muted">
                                        Guarda borrador y genera link. Cuando respondan, usted adjunta el voucher y envía a encargada.
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
                        <div className="flex items-center justify-between ml-1">
                            <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">Excel datos fiscales (opcional)_</label>
                            <a href={route('facturas.plantilla_fiscales')} className="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-widest hover:underline" style={{ color: FACTURA_ACCENT }}>
                                <Download className="w-3.5 h-3.5" /> Plantilla
                            </a>
                        </div>

                        {tieneExcelActual && (
                            <div className="flex flex-wrap items-center gap-2 p-3 rounded-xl theme-element border theme-border bg-[color-mix(in_srgb,var(--color-primario)_6%,transparent)]">
                                <FileSpreadsheet className="w-5 h-5 shrink-0" style={{ color: FACTURA_ACCENT }} />
                                <div className="min-w-0 flex-1">
                                    <p className="text-[10px] font-bold theme-text-main m-0 truncate">Excel / CSV actual</p>
                                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Se conservará si no sube otro</p>
                                </div>
                                <a
                                    href={urlArchivoFactura(facturaAEditar.id, 'fiscal')}
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
                    </div>
                </form>
            </div>
        </div>,
        document.body
    );
}
