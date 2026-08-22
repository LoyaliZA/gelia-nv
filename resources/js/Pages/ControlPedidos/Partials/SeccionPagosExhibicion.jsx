import React, { useEffect, useMemo, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { ImagePlus, Trash2 } from 'lucide-react';
import {
    formatearMoneda,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
    LABELS_FORMA_PAGO,
    etiquetaCodigo,
    badgeEstadoRevisionPago,
    badgeCoberturaPago,
    badgeRevisionPagoPedido,
    calcularResumenCoberturaPago,
    mensajePagoFaltante,
} from './pedidosBmaStyles';
import { THEME_SELECT } from '../../../utils/geliaTheme';
import InputMoneda from './InputMoneda';
import ModalVistaPreviaDocumento, { MiniaturaDocumento } from './ModalVistaPreviaDocumento';
import ModalMotivoRechazo from './ModalMotivoRechazo';
import { archivosImagenDesdeClipboard, documentoDesdeArchivoLocal } from './archivosDesdeClipboard';

const formaRequiereBanco = (forma, formasPago = []) => {
    const found = formasPago.find((f) => f.codigo === forma);
    if (found) return Boolean(found.requiere_banco);
    return forma === 'transferencia' || forma === 'deposito';
};

const ESTADOS_DEFINITIVOS = new Set(['verificado', 'con_observaciones', 'rechazado', 'confirmado', 'con_diferencia']);

const BTN_REV = {
    en_revision: 'inline-flex items-center px-2.5 py-1.5 rounded-lg border text-[9px] font-black uppercase tracking-wide outline-none bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-500/30 hover:opacity-90',
    verificado: 'inline-flex items-center px-2.5 py-1.5 rounded-lg border text-[9px] font-black uppercase tracking-wide outline-none bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30 hover:opacity-90',
    con_observaciones: 'inline-flex items-center px-2.5 py-1.5 rounded-lg border text-[9px] font-black uppercase tracking-wide outline-none bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30 hover:opacity-90',
    rechazado: 'inline-flex items-center px-2.5 py-1.5 rounded-lg border text-[9px] font-black uppercase tracking-wide outline-none bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30 hover:opacity-90',
};

const accionesVisiblesPara = (estado) => {
    if (ESTADOS_DEFINITIVOS.has(estado)) {
        return [{ key: 'en_revision', label: 'Reabrir' }];
    }
    if (estado === 'en_revision') {
        return [
            { key: 'verificado', label: 'Verificar' },
            { key: 'con_observaciones', label: 'Observaciones' },
            { key: 'rechazado', label: 'Rechazar' },
        ];
    }
    return [
        { key: 'en_revision', label: 'En revisión' },
        { key: 'verificado', label: 'Verificar' },
        { key: 'con_observaciones', label: 'Observaciones' },
        { key: 'rechazado', label: 'Rechazar' },
    ];
};

/**
 * Captura/consulta de exhibiciones de pago.
 * Default: pago único. Opción explícita para dividir entre métodos/bancos.
 */
export default function SeccionPagosExhibicion({
    pedidoId,
    bancos = [],
    formasPago = [],
    puedeRegistrar = false,
    puedeRevisar = false,
    puedeGenerarSaldo = false,
    rutaResumen = 'control_pedidos.pagos.resumen',
    rutaStore = 'control_pedidos.pagos.store',
    rutaUpdate = 'control_pedidos.pagos.update',
    rutaDestroy = 'control_pedidos.pagos.destroy',
    rutaRevisar = 'control_pedidos.pagos.revisar',
    rutaExcedente = 'control_pedidos.generar_saldo_excedente',
    onResumenChange = null,
    mensajeBloqueo = null,
    totalMercancia = null,
    costoEnvio = null,
    aplicaSeguro = null,
    costoSeguro = null,
    saldoAFavorAplicado = null,
}) {
    const [resumen, setResumen] = useState(null);
    const [pagos, setPagos] = useState([]);
    const [formas, setFormas] = useState(formasPago);
    const [cargando, setCargando] = useState(false);
    const [dividido, setDividido] = useState(false);
    const [editandoId, setEditandoId] = useState(null);
    const [docPreview, setDocPreview] = useState(null);
    const [revisionModal, setRevisionModal] = useState(null); // { pago, estado }
    const [comprobantePreviewUrl, setComprobantePreviewUrl] = useState(null);

    const form = useForm({
        monto: '',
        catalogo_banco_id: '',
        forma_pago: 'transferencia',
        referencia: '',
        fecha_pago: '',
        comprobante: null,
    });

    const requiereBanco = formaRequiereBanco(form.data.forma_pago, formas);

    const asignarComprobante = (file) => {
        setComprobantePreviewUrl((prev) => {
            if (prev) URL.revokeObjectURL(prev);
            return file ? URL.createObjectURL(file) : null;
        });
        form.setData('comprobante', file || null);
        form.clearErrors('comprobante');
    };

    const pegarComprobante = (e) => {
        const pasted = archivosImagenDesdeClipboard(e.clipboardData);
        if (!pasted.length) return;
        e.preventDefault();
        e.stopPropagation();
        const img = pasted[0];
        const file = new File([img], `comprobante-paste-${Date.now()}.png`, { type: img.type || 'image/png' });
        asignarComprobante(file);
    };

    const mezclarResumenVivo = (base, listaPagos) => {
        const tieneTotalesVivos = totalMercancia != null || saldoAFavorAplicado != null;
        if (!tieneTotalesVivos) return base;
        const pagado = (listaPagos || []).reduce((a, p) => a + Number(p.monto || 0), 0);
        const vivo = calcularResumenCoberturaPago({
            totalMercancia: totalMercancia ?? base?.total_a_cubrir ?? base?.total_final ?? 0,
            costoEnvio: costoEnvio ?? 0,
            aplicaSeguro: Boolean(aplicaSeguro),
            costoSeguro: costoSeguro ?? 0,
            saldoAFavorAplicado: saldoAFavorAplicado ?? base?.saldo_a_favor_aplicado ?? base?.saldos_aplicados ?? 0,
            totalPagado: pagado,
        });
        return {
            ...(base || {}),
            ...vivo,
            revision: base?.revision ?? null,
            fuentes_pago: base?.fuentes_pago,
            estado_pago: base?.estado_pago,
        };
    };

    const emitirResumenLocal = (lista) => {
        const next = mezclarResumenVivo(resumen, lista);
        setPagos(lista);
        setResumen(next);
        if (typeof onResumenChange === 'function') {
            onResumenChange(next, lista);
        }
    };

    const cargar = async () => {
        if (!pedidoId) return;
        setCargando(true);
        try {
            const res = await fetch(route(rutaResumen, pedidoId), {
                headers: { Accept: 'application/json' },
            });
            const json = await res.json();
            const nextPagos = json.pagos || [];
            setPagos(nextPagos);
            if (Array.isArray(json.formas_pago) && json.formas_pago.length) {
                setFormas(json.formas_pago);
            }
            const nextResumen = mezclarResumenVivo(json.resumen || null, nextPagos);
            setResumen(nextResumen);
            if (nextPagos.length > 1) {
                setDividido(true);
            }
            if (typeof onResumenChange === 'function') {
                onResumenChange(nextResumen, nextPagos);
            }
        } finally {
            setCargando(false);
        }
    };

    useEffect(() => {
        cargar();
    }, [pedidoId]);

    useEffect(() => {
        if (totalMercancia == null && saldoAFavorAplicado == null) return;
        if (!resumen && pagos.length === 0) return;
        const next = mezclarResumenVivo(resumen, pagos);
        setResumen(next);
        if (typeof onResumenChange === 'function') {
            onResumenChange(next, pagos);
        }
    }, [totalMercancia, costoEnvio, aplicaSeguro, costoSeguro, saldoAFavorAplicado]);

    useEffect(() => {
        if (formasPago?.length) setFormas(formasPago);
    }, [formasPago]);

    const opcionesForma = useMemo(() => {
        if (formas.length) return formas;
        return Object.entries(LABELS_FORMA_PAGO).map(([codigo, label]) => ({
            codigo,
            label,
            requiere_banco: formaRequiereBanco(codigo, []),
        }));
    }, [formas]);

    const resetForm = () => {
        form.reset('monto', 'referencia', 'comprobante', 'catalogo_banco_id');
        form.setData('forma_pago', 'transferencia');
        form.clearErrors();
        setEditandoId(null);
        setComprobantePreviewUrl((prev) => {
            if (prev) URL.revokeObjectURL(prev);
            return null;
        });
    };

    const registrar = (e) => {
        e.preventDefault();
        if (!editandoId && !form.data.comprobante) {
            form.setError('comprobante', 'Adjunte el comprobante de esta exhibición.');
            return;
        }
        if (requiereBanco && !form.data.catalogo_banco_id) {
            form.setError('catalogo_banco_id', 'Seleccione el banco receptor.');
            return;
        }

        const eraEdicion = Boolean(editandoId);
        const opts = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                resetForm();
                // Editar no implica dividir: si queda un solo pago, volver a modo único.
                if (eraEdicion && pagos.length <= 1) {
                    setDividido(false);
                }
                cargar();
            },
        };

        if (editandoId) {
            form.post(route(rutaUpdate, editandoId), opts);
            return;
        }

        form.post(route(rutaStore, pedidoId), opts);
    };

    const iniciarEdicion = (p) => {
        setEditandoId(p.id);
        // ponytail: no forzar «Dividir»; editar un pago único no abre modo multi-exhibición.
        form.setData({
            monto: String(p.monto ?? ''),
            catalogo_banco_id: p.catalogo_banco_id ? String(p.catalogo_banco_id) : '',
            forma_pago: p.forma_pago || 'transferencia',
            referencia: p.referencia || '',
            fecha_pago: '',
            comprobante: null,
        });
        form.clearErrors();
        setComprobantePreviewUrl((prev) => {
            if (prev) URL.revokeObjectURL(prev);
            return null;
        });
    };

    const eliminarPago = (p) => {
        if (!window.confirm(`¿Eliminar la exhibición #${p.numero_exhibicion} (${formatearMoneda(p.monto)})?`)) {
            return;
        }
        router.delete(route(rutaDestroy, p.id), {
            preserveScroll: true,
            onSuccess: () => {
                if (editandoId === p.id) resetForm();
                emitirResumenLocal(pagos.filter((x) => x.id !== p.id));
                cargar();
            },
        });
    };

    const revisarPago = (p, estado_revision, observaciones = null) => {
        const payload = { estado_revision };
        if (observaciones != null) payload.observaciones = observaciones;
        router.post(route(rutaRevisar, p.id), payload, {
            preserveScroll: true,
            onSuccess: () => {
                setRevisionModal(null);
                cargar();
            },
        });
    };

    const solicitarRevision = (p, estado) => {
        if (estado === 'con_observaciones' || estado === 'rechazado') {
            setRevisionModal({ pago: p, estado });
            return;
        }
        revisarPago(p, estado);
    };

    const intentarDesactivarDividido = () => {
        if (pagos.length <= 1) {
            setDividido(false);
            return;
        }
        const sobrantes = pagos.slice(1);
        const detalle = sobrantes
            .map((p) => `#${p.numero_exhibicion} ${formatearMoneda(p.monto)}`)
            .join(', ');
        const ok = window.confirm(
            `Al volver a pago único se eliminarán ${sobrantes.length} exhibición(es): ${detalle}. ¿Continuar?`
        );
        if (!ok) return;

        let pendientes = sobrantes.length;
        sobrantes.forEach((p) => {
            router.delete(route(rutaDestroy, p.id), {
                preserveScroll: true,
                onFinish: () => {
                    pendientes -= 1;
                    if (pendientes <= 0) {
                        setDividido(false);
                        cargar();
                    }
                },
            });
        });
    };

    const toggleDividido = (checked) => {
        if (checked) {
            setDividido(true);
            return;
        }
        intentarDesactivarDividido();
    };

    const comprobantesGaleria = useMemo(
        () => pagos
            .filter((p) => p.url)
            .map((p) => ({
                id: `pago-${p.id}`,
                url: p.url,
                nombre_original: p.nombre_original || `Comprobante #${p.numero_exhibicion}`,
                tipo: 'comprobante',
                comentario: [
                    `#${p.numero_exhibicion}`,
                    p.banco?.nombre || etiquetaCodigo(p.forma_pago, LABELS_FORMA_PAGO),
                    formatearMoneda(p.monto),
                    p.referencia ? `Ref. ${p.referencia}` : null,
                ].filter(Boolean).join(' · '),
                mime_type: p.mime_type,
            })),
        [pagos]
    );

    const abrirComprobante = (p) => {
        const i = comprobantesGaleria.findIndex((d) => d.id === `pago-${p.id}`);
        setDocPreview({ indice: i >= 0 ? i : 0 });
    };

    if (!pedidoId) {
        return (
            <p className="text-xs theme-text-muted font-bold m-0">
                Guarde el borrador del pedido para registrar exhibiciones de pago.
            </p>
        );
    }

    const badgeCob = resumen?.cobertura ? badgeCoberturaPago(resumen.cobertura) : null;
    const badgeRev = resumen?.revision ? badgeRevisionPagoPedido(resumen.revision) : null;
    const mostrarFormulario = puedeRegistrar && (dividido || pagos.length === 0 || editandoId);
    const modoUnicoConPago = !dividido && pagos.length === 1 && !editandoId;
    const totalACubrir = resumen?.total_a_cubrir ?? resumen?.total_final;
    const totalPagado = resumen?.total_pagado ?? resumen?.total_recibido;
    const safAplicado = resumen?.saldo_a_favor_aplicado ?? resumen?.saldos_aplicados ?? 0;
    const pendiente = Number(resumen?.pendiente || 0);
    const excedenteGenerado = Number(resumen?.excedente_generado ?? resumen?.excedente ?? 0);

    return (
        <div className="space-y-3" onPaste={mostrarFormulario ? pegarComprobante : undefined}>
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p className={`${THEME_LABEL} mb-0`}>Exhibiciones de pago</p>
                    <p className="text-[10px] theme-text-muted font-bold m-0 -mt-1">
                        {dividido
                            ? 'Un abono por banco o método. Cada exhibición lleva su comprobante.'
                            : 'Pago único: una sola fuente de pago con su comprobante.'}
                    </p>
                </div>
                {puedeRegistrar && (
                    <label className="flex items-center gap-2 text-[10px] font-black uppercase tracking-wide theme-text-main cursor-pointer">
                        <input
                            type="checkbox"
                            checked={dividido}
                            onChange={(e) => toggleDividido(e.target.checked)}
                            className="rounded border theme-border"
                        />
                        Dividir el pago entre diferentes métodos o bancos
                    </label>
                )}
            </div>
            {mensajeBloqueo && (
                <p className="text-xs theme-text-muted font-bold m-0">{mensajeBloqueo}</p>
            )}
            {cargando && <p className="text-xs theme-text-muted font-bold m-0">Cargando…</p>}
            {pagos.length > 0 && (
                <div className="space-y-1.5">
                    {pagos.map((p) => {
                        const badgeRevRow = badgeEstadoRevisionPago(p.estado_revision);
                        const forma = etiquetaCodigo(p.forma_pago, LABELS_FORMA_PAGO);
                        return (
                            <div
                                key={p.id}
                                className="flex flex-wrap items-center justify-between gap-2 text-sm border theme-border theme-element rounded-xl px-3 py-2.5"
                            >
                                <div className="flex flex-wrap items-center gap-2 min-w-0">
                                    {p.url && (
                                        <MiniaturaDocumento
                                            documento={{
                                                id: `pago-${p.id}`,
                                                url: p.url,
                                                nombre_original: p.nombre_original || `Comprobante #${p.numero_exhibicion}`,
                                                mime_type: p.mime_type,
                                                tipo: 'comprobante',
                                            }}
                                            onVer={() => abrirComprobante(p)}
                                            className="block w-12 h-12 rounded-lg overflow-hidden border theme-border theme-element cursor-pointer shrink-0"
                                        />
                                    )}
                                    <span className="font-bold theme-text-main">
                                        #{p.numero_exhibicion} · {p.banco?.nombre || forma || '—'}
                                    </span>
                                    <span className={badgeRevRow.className} style={badgeRevRow.style}>{badgeRevRow.label}</span>
                                    {p.url && (
                                        <button
                                            type="button"
                                            onClick={() => abrirComprobante(p)}
                                            className="text-[10px] font-black uppercase outline-none"
                                            style={{ color: 'var(--color-primario)' }}
                                        >
                                            Ver
                                        </button>
                                    )}
                                </div>
                                <div className="flex flex-col items-end gap-1.5 shrink-0">
                                    <div className="flex items-center gap-2">
                                        <span className="font-black" style={{ color: 'var(--color-primario)' }}>
                                            {formatearMoneda(p.monto)}
                                        </span>
                                        {puedeRevisar && (
                                            <div className="flex flex-wrap justify-end gap-1.5">
                                                {accionesVisiblesPara(p.estado_revision).map((accion) => (
                                                    <button
                                                        key={accion.key}
                                                        type="button"
                                                        className={BTN_REV[accion.key] || BTN_REV.en_revision}
                                                        onClick={() => solicitarRevision(p, accion.key)}
                                                    >
                                                        {accion.label}
                                                    </button>
                                                ))}
                                            </div>
                                        )}
                                        {puedeRegistrar && (
                                            <>
                                                <button
                                                    type="button"
                                                    className="text-[10px] font-black uppercase theme-text-muted"
                                                    onClick={() => iniciarEdicion(p)}
                                                >
                                                    Editar
                                                </button>
                                                {(dividido || pagos.length > 1) && (
                                                    <button
                                                        type="button"
                                                        className="text-rose-500"
                                                        title="Eliminar"
                                                        onClick={() => eliminarPago(p)}
                                                    >
                                                        <Trash2 className="w-3.5 h-3.5" />
                                                    </button>
                                                )}
                                            </>
                                        )}
                                    </div>
                                    {p.observaciones && (
                                        <p className="text-[10px] theme-text-muted font-bold m-0 max-w-xs text-right">
                                            {p.observaciones}
                                        </p>
                                    )}
                                </div>
                            </div>
                        );
                    })}
                </div>
            )}
            {modoUnicoConPago && puedeRegistrar && (
                <p className="text-[10px] theme-text-muted font-bold m-0">
                    Para agregar otra fuente, active «Dividir el pago…». Use Editar para corregir la existente.
                </p>
            )}
            {resumen && (
                <div className="grid grid-cols-2 gap-2 p-3 rounded-xl border theme-border theme-element text-xs font-bold">
                    <div className="theme-text-muted col-span-2">
                        Total a cubrir:{' '}
                        <strong className="theme-text-main">{formatearMoneda(totalACubrir)}</strong>
                    </div>
                    <div className="theme-text-muted col-span-2">
                        Total pagado por el cliente:{' '}
                        <strong className="theme-text-main">{formatearMoneda(totalPagado)}</strong>
                    </div>
                    {pendiente > 0.01 ? (
                        <div className="col-span-2 text-amber-700 dark:text-amber-400">
                            Saldo pendiente:{' '}
                            <strong>{formatearMoneda(pendiente)}</strong>
                        </div>
                    ) : excedenteGenerado > 0.01 ? (
                        <div className="col-span-2" style={{ color: 'var(--color-info)' }}>
                            Excedente generado (este pedido):{' '}
                            <strong>{formatearMoneda(excedenteGenerado)}</strong>
                        </div>
                    ) : (
                        <div className="theme-text-muted col-span-2">
                            Saldo pendiente:{' '}
                            <strong className="theme-text-main">{formatearMoneda(0)}</strong>
                        </div>
                    )}
                    {Number(safAplicado) > 0.01 && (
                        <div className="col-span-2" style={{ color: 'var(--color-exito)' }}>
                            Saldo a favor aplicado:{' '}
                            <strong>- {formatearMoneda(safAplicado)}</strong>
                        </div>
                    )}
                    {pendiente > 0.01 && (
                        <p className="col-span-2 text-[11px] font-black text-amber-700 dark:text-amber-400 m-0">
                            {mensajePagoFaltante(pendiente)}
                        </p>
                    )}
                    <div className="theme-text-muted col-span-2 flex flex-wrap items-center gap-2">
                        {badgeCob && (
                            <span className={badgeCob.className} style={badgeCob.style}>{badgeCob.label}</span>
                        )}
                        {badgeRev && (
                            <span className={badgeRev.className} style={badgeRev.style}>{badgeRev.label}</span>
                        )}
                    </div>
                </div>
            )}
            {mostrarFormulario && (
                <form
                    onSubmit={registrar}
                    onPaste={pegarComprobante}
                    className="grid grid-cols-1 md:grid-cols-2 gap-3 border theme-border theme-element rounded-xl p-4"
                >
                    <div>
                        <label className={`${THEME_LABEL} mb-1.5 block`}>Monto</label>
                        <InputMoneda
                            value={form.data.monto}
                            onChange={(v) => form.setData('monto', v)}
                            className="w-full py-2.5"
                        />
                    </div>
                    <div>
                        <label className={`${THEME_LABEL} mb-1.5 block`}>Forma de pago</label>
                        <select
                            className={`${THEME_SELECT} w-full py-2.5`}
                            value={form.data.forma_pago}
                            onChange={(e) => {
                                const next = e.target.value;
                                form.setData({
                                    ...form.data,
                                    forma_pago: next,
                                    catalogo_banco_id: formaRequiereBanco(next, formas) ? form.data.catalogo_banco_id : '',
                                });
                            }}
                        >
                            {opcionesForma.map((f) => (
                                <option key={f.codigo} value={f.codigo}>{f.label}</option>
                            ))}
                        </select>
                    </div>
                    {requiereBanco && (
                        <div>
                            <label className={`${THEME_LABEL} mb-1.5 block`}>Banco receptor</label>
                            <select
                                className={`${THEME_SELECT} w-full py-2.5`}
                                value={form.data.catalogo_banco_id}
                                onChange={(e) => form.setData('catalogo_banco_id', e.target.value)}
                            >
                                <option value="">Seleccionar…</option>
                                {bancos.map((b) => (
                                    <option key={b.id} value={b.id}>{b.nombre}</option>
                                ))}
                            </select>
                            {form.errors.catalogo_banco_id && (
                                <p className="text-[10px] text-red-500 font-bold mt-1 m-0">{form.errors.catalogo_banco_id}</p>
                            )}
                        </div>
                    )}
                    <div className="md:col-span-2">
                        <label className={`${THEME_LABEL} mb-1.5 block`}>
                            {editandoId ? 'Comprobante (opcional al editar)' : 'Comprobante (obligatorio)'}
                        </label>
                        <p className="text-[10px] theme-text-muted font-bold m-0 mb-1.5 -mt-0.5">
                            La referencia del cliente aparece en el comprobante. Puede pegar una captura (Ctrl+V).
                        </p>
                        <div className="flex flex-wrap items-center gap-3">
                            <label className="flex items-center gap-2 px-4 py-3 border theme-border border-dashed rounded-xl cursor-pointer w-fit theme-element theme-text-main">
                                <ImagePlus className="w-4 h-4 theme-text-muted" />
                                <span className="text-xs font-black uppercase">
                                    {form.data.comprobante?.name || 'Adjuntar comprobante'}
                                </span>
                                <input
                                    type="file"
                                    accept="image/*,application/pdf"
                                    className="hidden"
                                    onChange={(e) => {
                                        asignarComprobante(e.target.files?.[0] || null);
                                        e.target.value = '';
                                    }}
                                />
                            </label>
                            {form.data.comprobante && comprobantePreviewUrl && (
                                <MiniaturaDocumento
                                    documento={documentoDesdeArchivoLocal(form.data.comprobante, comprobantePreviewUrl)}
                                    onVer={(doc) => setDocPreview({
                                        indice: 0,
                                        documentos: [doc],
                                    })}
                                />
                            )}
                        </div>
                        {form.errors.comprobante && (
                            <p className="text-[10px] text-red-500 font-bold mt-1 m-0">{form.errors.comprobante}</p>
                        )}
                    </div>
                    <div className="md:col-span-2 flex flex-wrap gap-2">
                        <button
                            type="submit"
                            disabled={form.processing}
                            className={`${BTN_PRIMARY} outline-none disabled:opacity-50`}
                        >
                            {editandoId ? 'Guardar cambios' : (dividido ? 'Agregar exhibición' : 'Registrar pago')}
                        </button>
                        {editandoId && (
                            <button
                                type="button"
                                className={`${BTN_SECONDARY} outline-none`}
                                onClick={resetForm}
                            >
                                Cancelar edición
                            </button>
                        )}
                    </div>
                </form>
            )}
            {puedeGenerarSaldo && excedenteGenerado > 0 && (
                <button
                    type="button"
                    className={`${BTN_SECONDARY} outline-none`}
                    onClick={() => router.post(route(rutaExcedente, pedidoId))}
                >
                    Generar saldo a favor por excedente de este pedido ({formatearMoneda(excedenteGenerado)})
                </button>
            )}
            {!puedeGenerarSaldo && excedenteGenerado > 0 && (
                <p className="text-[10px] theme-text-muted font-bold m-0">
                    Excedente de este pedido: el saldo a favor se genera al registrar el pago o al enviar el pedido y estará disponible a partir del siguiente.
                </p>
            )}
            <ModalVistaPreviaDocumento
                abierto={Boolean(docPreview)}
                documentos={docPreview?.documentos || comprobantesGaleria}
                indice={docPreview?.indice || 0}
                onClose={() => setDocPreview(null)}
                onChangeIndice={(i) => setDocPreview((prev) => ({ ...(prev || {}), indice: i }))}
            />
            <ModalMotivoRechazo
                abierto={Boolean(revisionModal)}
                onClose={() => setRevisionModal(null)}
                titulo={revisionModal?.estado === 'rechazado' ? 'Rechazar exhibición' : 'Observaciones de exhibición'}
                descripcion={
                    revisionModal?.estado === 'rechazado'
                        ? 'Solo marca esta exhibición como rechazada. No devuelve el pedido a la vendedora.'
                        : 'Describe la diferencia u observación encontrada en esta exhibición.'
                }
                labelCampo={revisionModal?.estado === 'rechazado' ? 'Motivo del rechazo' : 'Observaciones'}
                placeholder={
                    revisionModal?.estado === 'rechazado'
                        ? '¿Por qué se rechaza esta exhibición?'
                        : 'Detalle de la observación…'
                }
                confirmLabel="Guardar"
                onConfirm={(texto) => {
                    if (!revisionModal) return;
                    revisarPago(revisionModal.pago, revisionModal.estado, texto);
                }}
            />
        </div>
    );
}
