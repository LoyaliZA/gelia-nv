import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import {
    X, CheckCircle2, AlertTriangle, FileText, Upload, Trash2, ExternalLink,
} from 'lucide-react';
import {
    badgeAuditoriaSemantico,
    formatearMoneda,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
} from '../../Partials/pedidosBmaStyles';

const SECCION = `${THEME_LABEL} mb-3 block`;
const SECCION_WRAP = 'border-b theme-border pb-6 last:border-0';

const Campo = ({ label, value }) => (
    <div>
        <p className="text-[9px] font-black uppercase theme-text-muted m-0">{label}</p>
        <p className="text-sm font-bold theme-text-main m-0 mt-0.5">{value ?? '—'}</p>
    </div>
);

const comprobantesDe = (pedido) => (pedido?.documentos || []).filter((d) => d.tipo === 'comprobante' || !d.tipo);
const remisionDe = (pedido) => (pedido?.documentos || []).find((d) => d.tipo === 'remision');

export default function ModalRevisarPedido({ abierto, onClose, pedido: pedidoInicial }) {
    const [pedido, setPedido] = useState(pedidoInicial);
    const [procesando, setProcesando] = useState(false);

    useEffect(() => {
        if (abierto && pedidoInicial) {
            setPedido(pedidoInicial);
            setProcesando(false);
        }
    }, [abierto, pedidoInicial?.id]);

    if (!abierto || !pedido) return null;

    const fase = pedido.estatus?.fase_ciclo;
    const badge = badgeAuditoriaSemantico(fase);
    const esPendiente = fase === 'PENDIENTE_AUXILIAR';
    const esRechazado = fase === 'RECHAZADO_VENDEDORA';
    const comprobantes = comprobantesDe(pedido);
    const remision = remisionDe(pedido);
    const pagoValidado = Boolean(pedido.pago_validado_at);
    const puedeAprobar = esPendiente && pagoValidado && Boolean(remision);

    const envioTienda = pedido.envio_tienda?.es_otro
        ? pedido.envio_tienda_otro || pedido.envio_tienda?.nombre
        : pedido.envio_tienda?.nombre;

    const recargarPedido = () => {
        router.reload({
            only: ['pedidos'],
            preserveScroll: true,
            onSuccess: (page) => {
                const actualizado = page.props.pedidos?.data?.find((p) => p.id === pedido.id);
                if (actualizado) setPedido(actualizado);
            },
        });
    };

    const validarPago = () => {
        setProcesando(true);
        router.post(route('control_pedidos.auditar.validar_pago', pedido.id), {}, {
            preserveScroll: true,
            onSuccess: recargarPedido,
            onFinish: () => setProcesando(false),
        });
    };

    const subirRemision = (archivo) => {
        if (!archivo) return;
        const formData = new FormData();
        formData.append('remision', archivo);
        router.post(route('control_pedidos.auditar.remision.store', pedido.id), formData, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: recargarPedido,
        });
    };

    const eliminarRemision = () => {
        if (!window.confirm('¿Eliminar la remisión adjunta?')) return;
        router.delete(route('control_pedidos.auditar.remision.destroy', pedido.id), {
            preserveScroll: true,
            onSuccess: recargarPedido,
        });
    };

    const reportarProblema = () => {
        const motivo = window.prompt('Motivo del reporte (se devolverá a la vendedora):');
        if (!motivo || motivo.trim().length < 5) return;
        router.post(route('control_pedidos.auditar.rechazar', pedido.id), { motivo: motivo.trim() }, {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    const aprobar = () => {
        if (!puedeAprobar) return;
        if (!window.confirm('¿Aprobar y enviar este pedido a Registro General?')) return;
        router.post(route('control_pedidos.auditar.aprobar', pedido.id), {}, {
            preserveScroll: true,
            onSuccess: () => onClose(),
        });
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-3xl w-full flex flex-col`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <div className="min-w-0">
                        <h2 className="text-xl font-black italic uppercase theme-text-main m-0">Revisar — {pedido.folio}</h2>
                        <span className={`${badge.className} mt-2 inline-flex`} style={badge.style}>{badge.label}</span>
                    </div>
                    <button type="button" onClick={onClose} className="p-2 rounded-full theme-text-muted hover:theme-text-main outline-none shrink-0" aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="gelia-modal-body p-5 md:p-6 space-y-6">
                    {/* 1. Estado y alertas */}
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>1. Estado y alertas</p>
                        <div className={`p-4 rounded-xl border theme-border ${esRechazado ? 'bg-red-500/10 border-red-500/30' : 'theme-element'}`}>
                            <p className="text-sm font-bold theme-text-main m-0">
                                Estado actual: <span style={{ color: badge.style?.color }}>{badge.label}</span>
                            </p>
                            {esRechazado && pedido.motivo_rechazo && (
                                <p className="text-sm text-red-500 font-bold mt-2 m-0 flex items-start gap-2">
                                    <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" />
                                    Motivo del reporte: {pedido.motivo_rechazo}
                                </p>
                            )}
                            {pagoValidado && (
                                <p className="text-xs text-emerald-600 font-bold mt-2 m-0 flex items-center gap-1">
                                    <CheckCircle2 className="w-4 h-4" />
                                    Pago validado el {new Date(pedido.pago_validado_at).toLocaleString('es-MX')}
                                    {(pedido.pago_validado_por?.name || pedido.pagoValidadoPor?.name) && ` por ${pedido.pago_validado_por?.name || pedido.pagoValidadoPor?.name}`}
                                </p>
                            )}
                        </div>
                    </section>

                    {/* 2. Datos generales */}
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>2. Datos generales</p>
                        <div className="grid grid-cols-2 gap-4">
                            <Campo label="N° Cliente" value={pedido.cliente?.numero_cliente} />
                            <Campo label="Nombre" value={pedido.cliente?.nombre} />
                            <Campo label="Folio" value={pedido.folio} />
                            <Campo label="Fecha" value={pedido.fecha ? new Date(pedido.fecha).toLocaleDateString('es-MX') : ''} />
                            <Campo label="Almacén" value={pedido.almacen_salida?.nombre} />
                            <Campo label="Banco" value={pedido.banco?.nombre} />
                            <Campo label="Factura" value={pedido.requiere_factura ? 'Sí' : 'No'} />
                            <Campo label="Saldo a favor" value={Number(pedido.saldo_a_favor) > 0 ? formatearMoneda(pedido.saldo_a_favor) : '—'} />
                            <Campo label="N° de cajas" value={pedido.numero_cajas} />
                            <Campo label="Vendedora" value={pedido.vendedor?.name} />
                        </div>
                    </section>

                    {/* 3. Pago del cliente */}
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>3. Pago del cliente</p>
                        <div className="grid grid-cols-2 gap-4 mb-4">
                            <Campo label="Monto pagado" value={formatearMoneda(pedido.total_a_cobrar)} />
                            <Campo label="Método de pago" value={pedido.banco?.nombre} />
                        </div>
                        {comprobantes.length > 0 ? (
                            <div className="flex flex-wrap gap-2 mb-4">
                                {comprobantes.map((doc) => (
                                    <a key={doc.id} href={doc.url} target="_blank" rel="noreferrer" className="block w-20 h-20 rounded-xl overflow-hidden border theme-border theme-element">
                                        <img src={doc.url} alt={doc.nombre_original} className="w-full h-full object-cover" />
                                    </a>
                                ))}
                            </div>
                        ) : (
                            <p className="text-xs theme-text-muted font-bold italic mb-4">Sin comprobantes adjuntos</p>
                        )}
                        {esPendiente && (
                            <button
                                type="button"
                                onClick={validarPago}
                                disabled={procesando || pagoValidado || comprobantes.length === 0}
                                className={`${BTN_PRIMARY} flex items-center gap-2 outline-none disabled:opacity-50`}
                            >
                                <CheckCircle2 className="w-4 h-4" />
                                {pagoValidado ? 'Pago validado' : 'Validar pago'}
                            </button>
                        )}
                    </section>

                    {/* 4. Envío, costos y dirección */}
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>4. Envío, costos y dirección</p>
                        <div className="grid grid-cols-2 gap-4">
                            <Campo label="Paquetería" value={pedido.paqueteria?.nombre} />
                            <Campo label="Guía / Rastreo" value={pedido.numero_rastreo || pedido.tipo_guia?.nombre} />
                            <Campo label="Tipo caja" value={pedido.tipo_caja?.nombre} />
                            <Campo label="Peso real" value={pedido.peso_real_kg != null ? `${pedido.peso_real_kg} kg` : null} />
                            <Campo label="Envío tienda" value={envioTienda} />
                            <Campo label="Reexpedición" value={pedido.zona?.nombre} />
                        </div>
                        <div className="mt-4 p-4 rounded-xl border theme-border theme-element space-y-2 text-sm">
                            <div className="flex justify-between theme-text-muted font-bold"><span>Mercancía</span><span>{formatearMoneda(pedido.total_mercancia)}</span></div>
                            <div className="flex justify-between theme-text-muted font-bold"><span>Envío</span><span>{formatearMoneda(pedido.costo_envio)}</span></div>
                            {pedido.aplica_seguro && (
                                <div className="flex justify-between theme-text-muted font-bold"><span>Seguro</span><span>{formatearMoneda(pedido.costo_seguro)}</span></div>
                            )}
                            {Number(pedido.saldo_a_favor) > 0 && (
                                <div className="flex justify-between text-emerald-600 font-bold"><span>Saldo a favor</span><span>- {formatearMoneda(pedido.saldo_a_favor)}</span></div>
                            )}
                            <div className="flex justify-between font-black pt-2 border-t theme-border" style={{ color: 'var(--color-primario)' }}>
                                <span>Total</span><span>{formatearMoneda(pedido.total_a_cobrar)}</span>
                            </div>
                        </div>
                        <div className="mt-4 space-y-3">
                            <Campo label="Domicilio de envío" value={pedido.domicilio_entrega} />
                            {pedido.envia_a_otra_persona && <Campo label="Destinatario alterno" value={pedido.envia_otra_persona} />}
                            <Campo label="Comentarios" value={pedido.comentarios_drive} />
                        </div>
                    </section>

                    {/* 5. Remisión */}
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>5. Remisión</p>
                        {remision ? (
                            <div className="space-y-3">
                                <div className="flex items-center gap-3 p-4 rounded-xl border theme-border theme-element">
                                    <FileText className="w-8 h-8 shrink-0" style={{ color: 'var(--color-primario)' }} />
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-bold theme-text-main m-0 truncate">{remision.nombre_original}</p>
                                        <a href={remision.url} target="_blank" rel="noreferrer" className="text-xs font-bold inline-flex items-center gap-1 mt-1" style={{ color: 'var(--color-primario)' }}>
                                            <ExternalLink className="w-3 h-3" /> Ver PDF
                                        </a>
                                    </div>
                                    {esPendiente && (
                                        <button type="button" onClick={eliminarRemision} className="p-2 rounded-lg hover:bg-red-500/10 outline-none" title="Eliminar">
                                            <Trash2 className="w-4 h-4 text-red-500" />
                                        </button>
                                    )}
                                </div>
                                <iframe src={remision.url} title="Vista previa remisión" className="w-full h-64 rounded-xl border theme-border" />
                            </div>
                        ) : esPendiente ? (
                            <label className="flex flex-col items-center justify-center gap-2 p-8 border-2 border-dashed theme-border rounded-xl cursor-pointer theme-element hover:border-[var(--color-primario)]">
                                <Upload className="w-8 h-8 theme-text-muted" />
                                <span className="text-xs font-black uppercase theme-text-main">Adjuntar remisión PDF</span>
                                <span className="text-[10px] theme-text-muted">Solo archivos .pdf</span>
                                <input
                                    type="file"
                                    accept=".pdf,application/pdf"
                                    className="hidden"
                                    onChange={(e) => subirRemision(e.target.files?.[0])}
                                />
                            </label>
                        ) : (
                            <p className="text-xs theme-text-muted font-bold italic">Sin remisión adjunta</p>
                        )}
                    </section>
                </div>

                {/* 6. Controles finales */}
                <div className="gelia-modal-footer flex flex-wrap gap-3 p-5 md:p-6 border-t theme-border shrink-0">
                    <button type="button" onClick={onClose} className={`${BTN_SECONDARY} theme-element border theme-border outline-none`}>
                        Cerrar
                    </button>
                    {esPendiente && (
                        <>
                            <button type="button" onClick={reportarProblema} disabled={procesando} className={`${BTN_SECONDARY} theme-element border border-red-500/40 text-red-500 outline-none`}>
                                Reportar problema
                            </button>
                            <button
                                type="button"
                                onClick={aprobar}
                                disabled={!puedeAprobar || procesando}
                                className={`${BTN_PRIMARY} flex items-center gap-2 outline-none disabled:opacity-50 ml-auto`}
                                title={!pagoValidado ? 'Valide el pago primero' : !remision ? 'Adjunte la remisión PDF' : ''}
                            >
                                <CheckCircle2 className="w-4 h-4" /> Aprobar y enviar a Registro General
                            </button>
                        </>
                    )}
                </div>
            </div>
        </div>,
        document.body
    );
}
