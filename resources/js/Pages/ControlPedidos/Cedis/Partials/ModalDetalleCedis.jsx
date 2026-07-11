import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import {
    X, CheckCircle2, AlertTriangle, FileText, ExternalLink, RotateCcw,
} from 'lucide-react';
import {
    badgeClaseEstatusPedido,
    badgeEmpaqueSemantico,
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

export default function ModalDetalleCedis({ abierto, onClose, pedido: pedidoInicial, onReportarIncidencia }) {
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
    const badgeEstatus = badgeClaseEstatusPedido(pedido.estatus);
    const badgeEmpaque = badgeEmpaqueSemantico(fase);
    const comprobantes = comprobantesDe(pedido);
    const remision = remisionDe(pedido);
    const esEmpacado = fase === 'EN_RUTA';
    const esIncidencia = fase === 'INCIDENCIA_CEDIS';
    const puedeEmpacar = fase === 'EN_CEDIS' || fase === 'INCIDENCIA_CEDIS';

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

    const marcarEmpacado = () => {
        if (!window.confirm('¿Confirmar que el pedido fue empacado?')) return;
        setProcesando(true);
        router.post(route('control_pedidos.cedis.marcar_empacado', pedido.id), {}, {
            preserveScroll: true,
            onSuccess: () => { recargarPedido(); onClose(); },
            onFinish: () => setProcesando(false),
        });
    };

    const revertirEmpacado = () => {
        if (!window.confirm('¿Revertir el empaque de este pedido?')) return;
        setProcesando(true);
        router.post(route('control_pedidos.cedis.revertir_empacado', pedido.id), {}, {
            preserveScroll: true,
            onSuccess: recargarPedido,
            onFinish: () => setProcesando(false),
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
                        <h2 className="text-xl font-black italic uppercase theme-text-main m-0">Detalle — {pedido.folio}</h2>
                        <div className="flex flex-wrap gap-2 mt-2">
                            <span className={badgeEstatus.className} style={badgeEstatus.style}>
                                {pedido.estatus?.nombre_visual}
                            </span>
                            <span className={badgeEmpaque.className} style={badgeEmpaque.style}>
                                {badgeEmpaque.label}
                            </span>
                        </div>
                    </div>
                    <button type="button" onClick={onClose} className="p-2 rounded-full theme-text-muted hover:theme-text-main outline-none shrink-0" aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>

                <div className="gelia-modal-body p-5 md:p-6 space-y-6">
                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>Estatus de empaque</p>
                        <div className={`p-4 rounded-xl border theme-border ${esIncidencia ? 'bg-orange-500/10 border-orange-500/30' : esEmpacado ? 'bg-emerald-500/10 border-emerald-500/30' : 'theme-element'}`}>
                            {esEmpacado && pedido.empacado_at && (
                                <p className="text-sm font-bold text-emerald-600 m-0 flex items-center gap-2">
                                    <CheckCircle2 className="w-4 h-4" />
                                    Empacado por {(pedido.empacado_por?.name || pedido.empacadoPor?.name) || '—'} el {new Date(pedido.empacado_at).toLocaleString('es-MX')}
                                </p>
                            )}
                            {esIncidencia && (
                                <>
                                    <p className="text-sm font-bold text-orange-600 m-0 flex items-center gap-2">
                                        <AlertTriangle className="w-4 h-4" /> Con detalle / incidencia
                                    </p>
                                    <p className="text-sm font-bold theme-text-main mt-2 m-0">{pedido.detalle_incidencia_empaque}</p>
                                    {pedido.incidencia_empaque_at && (
                                        <p className="text-xs theme-text-muted font-bold mt-2 m-0">
                                            Reportado por {(pedido.incidencia_empaque_por?.name || pedido.incidenciaEmpaquePor?.name) || '—'} el {new Date(pedido.incidencia_empaque_at).toLocaleString('es-MX')}
                                        </p>
                                    )}
                                </>
                            )}
                            {fase === 'EN_CEDIS' && (
                                <p className="text-sm font-bold theme-text-main m-0">Pendiente de empaque en almacén.</p>
                            )}
                        </div>
                    </section>

                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>Datos del cliente</p>
                        <div className="grid grid-cols-2 gap-4">
                            <Campo label="Nombre" value={pedido.cliente?.nombre} />
                            <Campo label="N° Cliente" value={pedido.cliente?.numero_cliente} />
                            <Campo label="Almacén" value={pedido.almacen_salida?.nombre} />
                            <Campo label="Saldo a favor" value={Number(pedido.saldo_a_favor) > 0 ? formatearMoneda(pedido.saldo_a_favor) : '—'} />
                            <Campo label="Factura" value={pedido.requiere_factura ? 'Sí' : 'No'} />
                        </div>
                    </section>

                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>Envío y costos</p>
                        <div className="grid grid-cols-2 gap-4">
                            <Campo label="Paquetería" value={pedido.paqueteria?.nombre} />
                            <Campo label="N° de cajas" value={pedido.numero_cajas} />
                            <Campo label="Tipo de guía" value={pedido.tipo_guia?.nombre} />
                            <Campo label="Peso real" value={pedido.peso_real_kg != null ? `${pedido.peso_real_kg} kg` : null} />
                            <Campo label="Seguro" value={pedido.aplica_seguro ? formatearMoneda(pedido.costo_seguro) : 'No aplica'} />
                        </div>
                        <div className="mt-4 p-4 rounded-xl border theme-border theme-element space-y-2 text-sm">
                            <div className="flex justify-between theme-text-muted font-bold"><span>Mercancía</span><span>{formatearMoneda(pedido.total_mercancia)}</span></div>
                            <div className="flex justify-between theme-text-muted font-bold"><span>Envío</span><span>{formatearMoneda(pedido.costo_envio)}</span></div>
                            {pedido.aplica_seguro && (
                                <div className="flex justify-between theme-text-muted font-bold"><span>Seguro</span><span>{formatearMoneda(pedido.costo_seguro)}</span></div>
                            )}
                            <div className="flex justify-between font-black pt-2 border-t theme-border" style={{ color: 'var(--color-primario)' }}>
                                <span>Total</span><span>{formatearMoneda(pedido.total_a_cobrar)}</span>
                            </div>
                        </div>
                    </section>

                    <section className={SECCION_WRAP}>
                        <p className={SECCION}>Datos de envío</p>
                        <div className="space-y-3">
                            <Campo label="Domicilio" value={pedido.domicilio_entrega} />
                            <Campo label="Código postal" value={pedido.codigo_postal} />
                            <Campo label="Envío tienda" value={envioTienda} />
                            <Campo label="Reexpedición / Zona" value={pedido.zona?.nombre} />
                            {pedido.es_resguardo && <Campo label="Resguardo" value="Sí" />}
                            {pedido.envia_a_otra_persona && <Campo label="Destinatario alterno" value={pedido.envia_otra_persona} />}
                        </div>
                    </section>

                    {pedido.comentarios_drive && (
                        <section className={SECCION_WRAP}>
                            <p className={SECCION}>Comentarios para Drive</p>
                            <p className="text-sm font-bold theme-text-main m-0">{pedido.comentarios_drive}</p>
                        </section>
                    )}

                    {(comprobantes.length > 0 || remision) && (
                        <section className={SECCION_WRAP}>
                            <p className={SECCION}>Documentos adjuntos</p>
                            {comprobantes.length > 0 && (
                                <div className="mb-4">
                                    <p className="text-[9px] font-black uppercase theme-text-muted mb-2">Comprobantes</p>
                                    <div className="flex flex-wrap gap-2">
                                        {comprobantes.map((doc) => (
                                            <a key={doc.id} href={doc.url} target="_blank" rel="noreferrer" className="block w-20 h-20 rounded-xl overflow-hidden border theme-border theme-element">
                                                <img src={doc.url} alt={doc.nombre_original} className="w-full h-full object-cover" />
                                            </a>
                                        ))}
                                    </div>
                                </div>
                            )}
                            {remision && (
                                <div className="flex items-center gap-3 p-4 rounded-xl border theme-border theme-element">
                                    <FileText className="w-8 h-8 shrink-0" style={{ color: 'var(--color-primario)' }} />
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-bold theme-text-main m-0 truncate">{remision.nombre_original}</p>
                                        <a href={remision.url} target="_blank" rel="noreferrer" className="text-xs font-bold inline-flex items-center gap-1 mt-1" style={{ color: 'var(--color-primario)' }}>
                                            <ExternalLink className="w-3 h-3" /> Ver remisión PDF
                                        </a>
                                    </div>
                                </div>
                            )}
                        </section>
                    )}
                </div>

                <div className="gelia-modal-footer flex flex-wrap gap-3 p-5 md:p-6 border-t theme-border shrink-0">
                    <button type="button" onClick={onClose} className={`${BTN_SECONDARY} theme-element border theme-border outline-none`}>
                        Cerrar
                    </button>
                    {fase === 'EN_CEDIS' && (
                        <button
                            type="button"
                            onClick={() => { onClose(); onReportarIncidencia(pedido); }}
                            disabled={procesando}
                            className={`${BTN_SECONDARY} theme-element border border-orange-500/40 text-orange-600 outline-none`}
                        >
                            <AlertTriangle className="w-4 h-4 inline mr-1" /> Pendiente de empaque
                        </button>
                    )}
                    {esEmpacado && (
                        <button type="button" onClick={revertirEmpacado} disabled={procesando} className={`${BTN_SECONDARY} flex items-center gap-2 outline-none`}>
                            <RotateCcw className="w-4 h-4" /> Revertir
                        </button>
                    )}
                    {puedeEmpacar && (
                        <button
                            type="button"
                            onClick={marcarEmpacado}
                            disabled={procesando}
                            className={`${BTN_PRIMARY} flex items-center gap-2 outline-none disabled:opacity-50 ml-auto`}
                        >
                            <CheckCircle2 className="w-4 h-4" /> Marcar empacado
                        </button>
                    )}
                </div>
            </div>
        </div>,
        document.body
    );
}
