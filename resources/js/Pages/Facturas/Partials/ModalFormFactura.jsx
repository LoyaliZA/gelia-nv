import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { X, Receipt, Search, Download, FileSpreadsheet, AlertOctagon, ExternalLink, RotateCcw } from 'lucide-react';
import ZonaAdjuntoVoucher from './ZonaAdjuntoVoucher';
import { FACTURA_ACCENT, BTN_PRIMARY, urlArchivoFactura, esImagenVoucher, esPdfVoucher } from './facturasStyles';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';

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

export default function ModalFormFactura({ onClose, onExito, modoEdicion = false, facturaAEditar = null }) {
    const [vouchers, setVouchers] = useState([]);
    const [vouchersConservarIds, setVouchersConservarIds] = useState(() =>
        (modoEdicion && facturaAEditar ? (facturaAEditar.vouchers || []).map(v => v.id) : [])
    );
    const [quitarExcel, setQuitarExcel] = useState(false);
    const [busquedaCliente, setBusquedaCliente] = useState(() => busquedaClienteDesdeFactura(facturaAEditar));
    const [listaClientes, setListaClientes] = useState([]);
    const [buscandoCliente, setBuscandoCliente] = useState(false);
    const [mostrarDropdown, setMostrarDropdown] = useState(false);
    const [dragExcel, setDragExcel] = useState(false);
    const excelInputRef = useRef(null);
    const debounceRef = useRef(null);
    const abortBusquedaCliente = useRef(null);

    const { data, setData, post, processing, errors, transform } = useForm({
        razon_social: modoEdicion ? razonSocialDesdeFactura(facturaAEditar) : '',
        numero_cliente: modoEdicion
            ? (facturaAEditar?.cliente?.numero_cliente || facturaAEditar?.datos_fiscales?.numero_cliente || '')
            : '',
        observaciones_vendedor: modoEdicion ? (facturaAEditar?.observaciones_vendedor || '') : '',
        archivo_fiscal: null,
    });

    useEffect(() => {
        if (!modoEdicion || !facturaAEditar) return;

        setData({
            razon_social: razonSocialDesdeFactura(facturaAEditar),
            numero_cliente: facturaAEditar.cliente?.numero_cliente || facturaAEditar.datos_fiscales?.numero_cliente || '',
            observaciones_vendedor: facturaAEditar.observaciones_vendedor || '',
            archivo_fiscal: null,
        });
        setBusquedaCliente(busquedaClienteDesdeFactura(facturaAEditar));
        setVouchers([]);
        setVouchersConservarIds((facturaAEditar.vouchers || []).map(v => v.id));
        setQuitarExcel(false);
    }, [modoEdicion, facturaAEditar?.id, setData]);

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
                    params: { q: busquedaCliente.trim() },
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

    const seleccionarCliente = (c) => {
        setData(prev => ({
            ...prev,
            numero_cliente: c.numero_cliente,
            razon_social: c.nombre_razon_social || c.nombre || prev.razon_social,
        }));
        setBusquedaCliente(`${c.numero_cliente} - ${c.nombre}`);
        setMostrarDropdown(false);
    };

    const vouchersExistentesUi = useMemo(() => {
        if (!modoEdicion || !facturaAEditar) return [];

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
    }, [modoEdicion, facturaAEditar, vouchersConservarIds]);

    const tieneExcelActual = modoEdicion && facturaAEditar?.tiene_archivo_fiscal && !quitarExcel && !data.archivo_fiscal;

    const enviar = (e) => {
        e.preventDefault();
        const config = {
            forceFormData: true,
            onSuccess: () => {
                onExito?.();
                onClose();
            },
        };

        if (modoEdicion) {
            transform(d => {
                const payload = { ...d, _method: 'put', vouchers_conservar: vouchersConservarIds };
                if (vouchers.length > 0) payload.vouchers = vouchers;
                if (quitarExcel) payload.eliminar_archivo_fiscal = '1';
                return payload;
            });
            post(route('facturas.reparar', facturaAEditar.id), config);
        } else {
            transform(d => ({ ...d, vouchers }));
            post(route('facturas.store'), config);
        }
    };

    const totalVouchers = modoEdicion
        ? vouchersConservarIds.length + vouchers.length
        : vouchers.length;
    const puedeEnviar = !processing && totalVouchers >= 1;

    const voucherError = errors.vouchers || errors['vouchers.0'] || errors.vouchers_conservar;

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
                                {modoEdicion ? 'Reparar Solicitud de Factura_' : 'Nueva Solicitud de Factura_'}
                            </h2>
                            {modoEdicion && facturaAEditar?.folio && (
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

                    <div className="space-y-2 relative">
                        <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Cliente (opcional)_</label>
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
                    </div>

                    <div className="space-y-2">
                        <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest ml-1">Razón Social_</label>
                        <input
                            required
                            value={data.razon_social}
                            onChange={e => setData('razon_social', e.target.value)}
                            className="w-full px-4 py-3 theme-surface border theme-border rounded-xl theme-text-main text-sm font-bold outline-none"
                            placeholder="Nombre o razón social a facturar"
                        />
                        {errors.razon_social && <p className="text-xs text-red-500">{errors.razon_social}</p>}
                    </div>

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

                        {modoEdicion && quitarExcel && !data.archivo_fiscal && (
                            <div className="flex items-center justify-between gap-2 p-3 rounded-xl border border-amber-500/30 bg-amber-500/10">
                                <p className="text-[10px] font-bold text-amber-700 dark:text-amber-300 m-0">El Excel actual se eliminará al reenviar.</p>
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

                    <button
                        type="submit"
                        disabled={!puedeEnviar}
                        className={`${BTN_PRIMARY} w-full !py-4 disabled:opacity-50`}
                    >
                        {processing ? 'Enviando…' : (modoEdicion ? 'Reenviar corrección' : 'Enviar Solicitud')}
                    </button>
                </form>
            </div>
        </div>,
        document.body
    );
}
