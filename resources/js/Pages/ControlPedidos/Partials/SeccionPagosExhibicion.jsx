import React, { useEffect, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { ImagePlus } from 'lucide-react';
import {
    formatearMoneda,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
    LABELS_FORMA_PAGO,
    etiquetaCodigo,
    badgeEstadoRevisionPago,
    badgeEstadoPagoPedido,
} from './pedidosBmaStyles';
import { THEME_INPUT, THEME_SELECT } from '../../../utils/geliaTheme';
import InputMoneda from './InputMoneda';

/**
 * Captura/consulta de exhibiciones de pago y sugerencia de saldo por excedente.
 */
export default function SeccionPagosExhibicion({
    pedidoId,
    bancos = [],
    puedeRegistrar = false,
    puedeGenerarSaldo = false,
    rutaResumen = 'control_pedidos.pagos.resumen',
    rutaStore = 'control_pedidos.pagos.store',
    rutaExcedente = 'control_pedidos.generar_saldo_excedente',
    onResumenChange = null,
    mensajeBloqueo = null,
}) {
    const [resumen, setResumen] = useState(null);
    const [pagos, setPagos] = useState([]);
    const [cargando, setCargando] = useState(false);

    const form = useForm({
        monto: '',
        catalogo_banco_id: '',
        forma_pago: 'transferencia',
        referencia: '',
        fecha_pago: '',
        comprobante: null,
    });

    const cargar = async () => {
        if (!pedidoId) return;
        setCargando(true);
        try {
            const res = await fetch(route(rutaResumen, pedidoId), {
                headers: { Accept: 'application/json' },
            });
            const json = await res.json();
            setPagos(json.pagos || []);
            const nextResumen = json.resumen || null;
            setResumen(nextResumen);
            if (typeof onResumenChange === 'function') {
                onResumenChange(nextResumen);
            }
        } finally {
            setCargando(false);
        }
    };

    useEffect(() => {
        cargar();
    }, [pedidoId]);

    const registrar = (e) => {
        e.preventDefault();
        form.post(route(rutaStore, pedidoId), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset('monto', 'referencia', 'comprobante');
                cargar();
            },
        });
    };

    if (!pedidoId) {
        return (
            <p className="text-xs theme-text-muted font-bold m-0">
                Guarde el borrador del pedido para registrar exhibiciones de pago.
            </p>
        );
    }

    return (
        <div className="space-y-3">
            <p className={`${THEME_LABEL} mb-0`}>Exhibiciones de pago</p>
            {mensajeBloqueo && (
                <p className="text-xs theme-text-muted font-bold m-0">{mensajeBloqueo}</p>
            )}
            {cargando && <p className="text-xs theme-text-muted font-bold m-0">Cargando…</p>}
            {pagos.length > 0 && (
                <div className="space-y-1.5">
                    {pagos.map((p) => {
                        const badgeRev = badgeEstadoRevisionPago(p.estado_revision);
                        const forma = etiquetaCodigo(p.forma_pago, LABELS_FORMA_PAGO);
                        return (
                            <div
                                key={p.id}
                                className="flex flex-wrap items-center justify-between gap-2 text-sm border theme-border theme-element rounded-xl px-3 py-2.5"
                            >
                                <div className="flex flex-wrap items-center gap-2 min-w-0">
                                    <span className="font-bold theme-text-main">
                                        #{p.numero_exhibicion} · {p.banco?.nombre || forma || '—'}
                                    </span>
                                    <span className={badgeRev.className} style={badgeRev.style}>{badgeRev.label}</span>
                                </div>
                                <span className="font-black shrink-0" style={{ color: 'var(--color-primario)' }}>
                                    {formatearMoneda(p.monto)}
                                </span>
                            </div>
                        );
                    })}
                </div>
            )}
            {resumen && (
                <div className="grid grid-cols-2 gap-2 p-3 rounded-xl border theme-border theme-element text-xs font-bold">
                    <div className="theme-text-muted">
                        Recibido:{' '}
                        <strong className="theme-text-main">{formatearMoneda(resumen.total_recibido)}</strong>
                    </div>
                    <div className="theme-text-muted">
                        Pendiente:{' '}
                        <strong className="theme-text-main">{formatearMoneda(resumen.pendiente)}</strong>
                    </div>
                    <div className="theme-text-muted col-span-2 flex flex-wrap items-center gap-2">
                        Estado:{' '}
                        {(() => {
                            const badge = badgeEstadoPagoPedido(resumen.estado_pago);
                            return <span className={badge.className} style={badge.style}>{badge.label}</span>;
                        })()}
                    </div>
                    <div className="theme-text-muted">
                        Excedente:{' '}
                        <strong style={{ color: 'var(--color-primario)' }}>{formatearMoneda(resumen.excedente)}</strong>
                    </div>
                </div>
            )}
            {puedeRegistrar && (
                <form
                    onSubmit={registrar}
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
                        <label className={`${THEME_LABEL} mb-1.5 block`}>Banco</label>
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
                    </div>
                    <div>
                        <label className={`${THEME_LABEL} mb-1.5 block`}>Forma de pago</label>
                        <select
                            className={`${THEME_SELECT} w-full py-2.5`}
                            value={form.data.forma_pago}
                            onChange={(e) => form.setData('forma_pago', e.target.value)}
                        >
                            <option value="transferencia">Transferencia</option>
                            <option value="efectivo">Efectivo</option>
                            <option value="tarjeta">Tarjeta</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div>
                        <label className={`${THEME_LABEL} mb-1.5 block`}>Referencia</label>
                        <input
                            className={`${THEME_INPUT} w-full py-2.5`}
                            value={form.data.referencia}
                            onChange={(e) => form.setData('referencia', e.target.value)}
                        />
                    </div>
                    <div className="md:col-span-2">
                        <label className={`${THEME_LABEL} mb-1.5 block`}>Comprobante</label>
                        <label className="flex items-center gap-2 px-4 py-3 border theme-border border-dashed rounded-xl cursor-pointer w-fit theme-element theme-text-main">
                            <ImagePlus className="w-4 h-4 theme-text-muted" />
                            <span className="text-xs font-black uppercase">
                                {form.data.comprobante?.name || 'Adjuntar comprobante'}
                            </span>
                            <input
                                type="file"
                                accept="image/*,application/pdf"
                                className="hidden"
                                onChange={(e) => form.setData('comprobante', e.target.files?.[0] || null)}
                            />
                        </label>
                    </div>
                    <div className="md:col-span-2">
                        <button
                            type="submit"
                            disabled={form.processing}
                            className={`${BTN_PRIMARY} outline-none disabled:opacity-50`}
                        >
                            Agregar exhibición
                        </button>
                    </div>
                </form>
            )}
            {puedeGenerarSaldo && resumen?.excedente > 0 && (
                <button
                    type="button"
                    className={`${BTN_SECONDARY} outline-none`}
                    onClick={() => router.post(route(rutaExcedente, pedidoId))}
                >
                    Generar saldo a favor por excedente ({formatearMoneda(resumen.excedente)})
                </button>
            )}
        </div>
    );
}
