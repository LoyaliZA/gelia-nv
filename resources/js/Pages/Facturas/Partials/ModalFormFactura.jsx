import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useForm } from '@inertiajs/react';
import axios from 'axios';
import { X, Receipt, Search, Download, FileSpreadsheet } from 'lucide-react';
import ZonaAdjuntoVoucher from './ZonaAdjuntoVoucher';
import { FACTURA_ACCENT, BTN_PRIMARY } from './facturasStyles';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';

export default function ModalFormFactura({ onClose, onExito }) {
    const [vouchers, setVouchers] = useState([]);
    const [busquedaCliente, setBusquedaCliente] = useState('');
    const [listaClientes, setListaClientes] = useState([]);
    const [buscandoCliente, setBuscandoCliente] = useState(false);
    const [mostrarDropdown, setMostrarDropdown] = useState(false);
    const [dragExcel, setDragExcel] = useState(false);
    const excelInputRef = useRef(null);
    const debounceRef = useRef(null);
    const abortBusquedaCliente = useRef(null);

    const { data, setData, post, processing, errors, transform } = useForm({
        razon_social: '',
        numero_cliente: '',
        observaciones_vendedor: '',
        archivo_fiscal: null,
    });

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

    const enviar = (e) => {
        e.preventDefault();
        transform(d => ({ ...d, vouchers }));
        post(route('facturas.store'), {
            forceFormData: true,
            onSuccess: () => {
                onExito?.();
                onClose();
            },
        });
    };

    const voucherError = errors.vouchers || errors['vouchers.0'];

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
                        <h2 className="text-lg font-black italic theme-text-main uppercase tracking-tighter m-0">Nueva Solicitud de Factura_</h2>
                    </div>
                    <button type="button" onClick={onClose} className="p-2 theme-text-muted hover:theme-text-main rounded-full hover:bg-black/5 dark:hover:bg-white/5 transition-colors outline-none shrink-0"><X className="w-5 h-5" /></button>
                </div>

                <form onSubmit={enviar} className="gelia-modal-body p-5 md:p-6 overflow-y-auto custom-scrollbar flex-1 min-h-0 space-y-6">
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
                        <div
                            className="border-2 border-dashed theme-border rounded-2xl p-5 flex flex-col items-center gap-2 cursor-pointer"
                            style={{ borderColor: dragExcel ? FACTURA_ACCENT : undefined }}
                            onDragOver={e => { e.preventDefault(); setDragExcel(true); }}
                            onDragLeave={() => setDragExcel(false)}
                            onDrop={e => { e.preventDefault(); setDragExcel(false); if (e.dataTransfer.files[0]) setData('archivo_fiscal', e.dataTransfer.files[0]); }}
                            onClick={() => excelInputRef.current?.click()}
                        >
                            <FileSpreadsheet className="w-7 h-7 theme-text-muted" style={{ color: data.archivo_fiscal ? FACTURA_ACCENT : undefined }} />
                            <p className="text-[10px] font-black theme-text-main uppercase">Arrastra o selecciona Excel / CSV</p>
                            {data.archivo_fiscal && <p className="text-[10px] font-bold" style={{ color: 'var(--color-primario)' }}>{data.archivo_fiscal.name}</p>}
                            <input ref={excelInputRef} type="file" className="hidden" accept=".xlsx,.xls,.csv" onChange={e => setData('archivo_fiscal', e.target.files[0] || null)} />
                        </div>
                        {errors.archivo_fiscal && <p className="text-xs text-red-500">{errors.archivo_fiscal}</p>}
                    </div>

                    <ZonaAdjuntoVoucher vouchers={vouchers} onChange={setVouchers} error={voucherError} />

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
                        disabled={processing || vouchers.length === 0}
                        className={`${BTN_PRIMARY} w-full !py-4 disabled:opacity-50`}
                    >
                        {processing ? 'Enviando…' : 'Enviar Solicitud'}
                    </button>
                </form>
            </div>
        </div>,
        document.body
    );
}
