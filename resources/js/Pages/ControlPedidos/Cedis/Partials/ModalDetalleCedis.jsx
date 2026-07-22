import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import {
    X, CheckCircle2, AlertTriangle, FileText, User, Truck, PackageCheck,
} from 'lucide-react';
import {
    badgeEstatusPedido,
    badgeEmpaqueSemantico,
    badgeRetrasoGuia,
    esPedidoEmpacadoCedis,
    formatearMoneda,
    etiquetaAlmacen,
    formatearFechaHoraAuditoria,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
} from '../../Partials/pedidosBmaStyles';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import DireccionPedidoResumen from '../../Partials/DireccionPedidoResumen';
import { codigoDireccionCliente } from '../../Partials/codigoDireccionCliente';
import ModalVistaPreviaDocumento, { MiniaturaDocumento } from '../../Partials/ModalVistaPreviaDocumento';
import ModalConfirmarAccion from '../../Partials/ModalConfirmarAccion';
import ModalAlertaPedido from '../../Partials/ModalAlertaPedido';
import SeccionGuiaRastreo from '../../Partials/SeccionGuiaRastreo';
import AvisoOperativoPedido from '../../Partials/AvisoOperativoPedido';

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

export default function ModalDetalleCedis({
    abierto, onClose, pedido: pedidoInicial, onReportarIncidencia, onReportarErrorDatos, onMarcarApartado,
}) {
    const [pedido, setPedido] = useState(pedidoInicial);
    const [procesando, setProcesando] = useState(false);
    const [docPreview, setDocPreview] = useState(null);
    const [confirmacion, setConfirmacion] = useState(null);
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'success', titulo: '', mensaje: '' });

    useEffect(() => {
        if (abierto && pedidoInicial) {
            setPedido(pedidoInicial);
            setProcesando(false);
            setConfirmacion(null);
            setDocPreview(null);
        }
    }, [abierto, pedidoInicial?.id]);

    if (!abierto || !pedido) return null;

    const fase = pedido.estatus?.fase_ciclo;
    const badgeEstatus = badgeEstatusPedido(pedido.estatus);
    const badgeEmpaque = badgeEmpaqueSemantico(fase, pedido.es_resguardo, Boolean(pedido.resguardo_apartado_at));
    const badgeRetraso = pedido.guia_retraso ? badgeRetrasoGuia() : null;
    const comprobantes = comprobantesDe(pedido);
    const remision = remisionDe(pedido);
    const evidenciasApartado = (pedido?.documentos || []).filter((d) => d.tipo === 'evidencia_apartado');
    const esIncidencia = fase === 'INCIDENCIA_CEDIS';
    const esEmpacado = esPedidoEmpacadoCedis(fase);
    const puedeEmpacar = (fase === 'EN_CEDIS' || fase === 'INCIDENCIA_CEDIS') && !pedido.es_resguardo;
    const puedeMarcarEnviado = fase === 'PENDIENTE_DE_ENVIO';
    const puedeErrorDatos = ['EN_CEDIS', 'PENDIENTE_DE_GUIA', 'PENDIENTE_DE_ENVIO'].includes(fase) && !pedido.es_resguardo;
    const puedeApartar = Boolean(pedido.es_resguardo) && fase === 'EN_CEDIS' && !pedido.resguardo_apartado_at;


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

    const ejecutarConfirmacion = () => {
        const accion = confirmacion;
        setConfirmacion(null);

        if (accion === 'empacar') {
            setProcesando(true);
            router.post(route('control_pedidos.cedis.marcar_empacado', pedido.id), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    setAlerta({ abierto: true, tipo: 'success', titulo: 'Empacado', mensaje: 'Pedido marcado como empacado.' });
                    onClose();
                },
                onError: () => setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: 'No se pudo marcar como empacado.' }),
                onFinish: () => setProcesando(false),
            });
            return;
        }

        if (accion === 'enviar') {
            setProcesando(true);
            router.post(route('control_pedidos.cedis.marcar_enviado', pedido.id), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    setAlerta({ abierto: true, tipo: 'success', titulo: 'Enviado', mensaje: 'Pedido marcado como enviado.' });
                    onClose();
                },
                onError: () => setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: 'No se pudo marcar como enviado.' }),
                onFinish: () => setProcesando(false),
            });
        }
    };

    const cfgConfirm = confirmacion === 'empacar'
        ? { titulo: 'Confirmar empaque', mensaje: '¿Confirmar que el pedido fue empacado?', etiquetaConfirmar: 'Marcar empacado', variante: 'primary' }
        : confirmacion === 'enviar'
            ? { titulo: 'Confirmar envío', mensaje: 'Al confirmar, el pedido sale a recolección y el estado se actualiza para auxiliar, CEDIS y logística.', etiquetaConfirmar: 'Marcar enviado', variante: 'primary' }
            : null;

    return createPortal(
        <>
            <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
                <div
                    className={`${THEME_MODAL_SHELL} max-w-3xl w-full flex flex-col`}
                    style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                    onClick={(e) => e.stopPropagation()}
                >
                    <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                        <div className="min-w-0">
                            <p className="text-[10px] font-black uppercase theme-text-muted m-0 mb-1">Detalle CEDIS</p>
                            <EncabezadoFolioPedido pedido={pedido} size="lg" />
                            {pedido.vendedor?.name && (
                                <p className="text-xs font-bold theme-text-muted mt-2 m-0 flex items-center gap-1">
                                    <User className="w-3.5 h-3.5" /> Capturado por: {pedido.vendedor.name}
                                </p>
                            )}
                            <div className="flex flex-wrap gap-2 mt-2">
                                <span className={badgeEstatus.className} style={badgeEstatus.style}>
                                    {badgeEstatus.label}
                                </span>
                                <span className={badgeEmpaque.className} style={badgeEmpaque.style}>
                                    {badgeEmpaque.label}
                                </span>
                                {badgeRetraso && (
                                    <span className={badgeRetraso.className} style={badgeRetraso.style}>
                                        {badgeRetraso.label}
                                    </span>
                                )}
                            </div>
                        </div>
                        <button type="button" onClick={onClose} className="p-2 rounded-full theme-text-muted hover:theme-text-main outline-none shrink-0" aria-label="Cerrar">
                            <X className="w-5 h-5" />
                        </button>
                    </div>

                    <div className="gelia-modal-body p-5 md:p-6 space-y-6">
                        <section className={SECCION_WRAP}>
                            <p className={SECCION}>Estatus de empaque</p>
                            <div className="space-y-3">
                                {(fase === 'EN_CEDIS' || fase === 'INCIDENCIA_CEDIS') && pedido.es_resguardo && (
                                    <AvisoOperativoPedido
                                        label="Resguardo"
                                        tono="blue"
                                        icon={PackageCheck}
                                    >
                                        {pedido.resguardo_apartado_at
                                            ? 'Resguardo apartado — empaque bloqueado'
                                            : 'Empaque bloqueado — en resguardo'}
                                    </AvisoOperativoPedido>
                                )}
                                {esEmpacado && pedido.empacado_at && (
                                    <AvisoOperativoPedido
                                        label="Empaque"
                                        tono="success"
                                        icon={CheckCircle2}
                                    >
                                        Empacado por {(pedido.empacado_por?.name || pedido.empacadoPor?.name) || '—'}
                                        <span className="block text-sm font-bold mt-1 opacity-80 font-mono">
                                            {formatearFechaHoraAuditoria(pedido.empacado_at)}
                                        </span>
                                    </AvisoOperativoPedido>
                                )}
                                {esIncidencia && (
                                    <AvisoOperativoPedido
                                        label="Incidencia"
                                        tono="danger"
                                        icon={AlertTriangle}
                                    >
                                        {pedido.detalle_incidencia_empaque || 'Con detalle / incidencia'}
                                        {pedido.incidencia_empaque_at && (
                                            <span className="block text-sm font-bold mt-1 opacity-80 font-mono">
                                                {(pedido.incidencia_empaque_por?.name || pedido.incidenciaEmpaquePor?.name) || '—'}
                                                {' · '}
                                                {formatearFechaHoraAuditoria(pedido.incidencia_empaque_at)}
                                            </span>
                                        )}
                                    </AvisoOperativoPedido>
                                )}
                                {!pedido.es_resguardo && !esEmpacado && !esIncidencia && fase === 'EN_CEDIS' && (
                                    <AvisoOperativoPedido label="Estatus" tono="warning">
                                        Pendiente de empaque en almacén
                                    </AvisoOperativoPedido>
                                )}
                                {fase === 'PENDIENTE_DE_GUIA' && (
                                    <AvisoOperativoPedido label="Estatus" tono="info">
                                        Esperando captura de guía por delegado
                                    </AvisoOperativoPedido>
                                )}
                                {fase === 'PENDIENTE_DE_ENVIO' && (
                                    <AvisoOperativoPedido label="Estatus" tono="info">
                                        Listo para verificación y envío en almacén
                                    </AvisoOperativoPedido>
                                )}
                                {(fase === 'PENDIENTE_DE_ENVIO' || fase === 'ENVIADO') && (
                                    <SeccionGuiaRastreo pedido={pedido} onVerPdf={setDocPreview} />
                                )}
                            </div>
                        </section>

                        <section className={SECCION_WRAP}>
                            <p className={SECCION}>Datos del cliente</p>
                            <div className="grid grid-cols-2 gap-4">
                                <Campo label="Nombre" value={pedido.cliente?.nombre} />
                                <Campo label="N° Cliente" value={pedido.cliente?.numero_cliente} />
                                <Campo label="Origen" value={pedido.origen?.nombre} />
                                <Campo label="Almacén" value={etiquetaAlmacen(pedido.almacen)} />
                                <Campo label="Registrado" value={formatearFechaHoraAuditoria(pedido.created_at)} />
                                <Campo label="Saldo a favor" value={Number(pedido.saldo_a_favor) > 0 ? formatearMoneda(pedido.saldo_a_favor) : '—'} />
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
                                <DireccionPedidoResumen
                                    direccion={pedido.direccion_vigente || pedido.direccionVigente}
                                    domicilioLegacy={pedido.domicilio_entrega}
                                    codigoPostal={pedido.codigo_postal}
                                codigoDireccion={codigoDireccionCliente(
                                    pedido.cliente?.numero_cliente,
                                    (pedido.direccion_vigente || pedido.direccionVigente)?.numero_direccion,
                                )}
                                />
                                <Campo label="Código postal" value={pedido.codigo_postal} />
                                <Campo label="Reexpedición / Zona" value={pedido.zona?.nombre} />
                                <Campo label="Anexar remisión" value={pedido.anexar_remision ? 'Sí' : 'No'} />
                                {pedido.es_resguardo && (
                                    <Campo
                                        label="Resguardo"
                                        value={pedido.resguardo_apartado_at
                                            ? `Apartado${pedido.resguardo_apartado_at ? ` · ${formatearFechaHoraAuditoria(pedido.resguardo_apartado_at)}` : ''}`
                                            : 'Sí — pendiente de apartar'}
                                    />
                                )}
                                {pedido.detalle_resguardo_apartado && (
                                    <Campo label="Nota apartado" value={pedido.detalle_resguardo_apartado} />
                                )}
                                {pedido.envia_a_otra_persona && <Campo label="Destinatario alterno" value={pedido.envia_otra_persona} />}
                            </div>
                        </section>

                        {evidenciasApartado.length > 0 && (
                            <section className={SECCION_WRAP}>
                                <p className={SECCION}>Evidencia de apartado</p>
                                <div className="flex flex-wrap gap-2">
                                    {evidenciasApartado.map((doc) => (
                                        <MiniaturaDocumento key={doc.id} documento={doc} onVer={setDocPreview} />
                                    ))}
                                </div>
                            </section>
                        )}

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
                                                <MiniaturaDocumento key={doc.id} documento={doc} onVer={setDocPreview} />
                                            ))}
                                        </div>
                                    </div>
                                )}
                                {remision && (
                                    <div className="flex items-center gap-3 p-4 rounded-xl border theme-border theme-element">
                                        <FileText className="w-8 h-8 shrink-0" style={{ color: 'var(--color-primario)' }} />
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-bold theme-text-main m-0 truncate">{remision.nombre_original}</p>
                                            <button
                                                type="button"
                                                onClick={() => setDocPreview(remision)}
                                                className="text-xs font-bold inline-flex items-center gap-1 mt-1 outline-none"
                                                style={{ color: 'var(--color-primario)' }}
                                            >
                                                Ver remisión PDF
                                            </button>
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
                                <AlertTriangle className="w-4 h-4 inline mr-1" /> Incidencia empaque
                            </button>
                        )}
                        {puedeErrorDatos && (
                            <button
                                type="button"
                                onClick={() => { onClose(); onReportarErrorDatos?.(pedido); }}
                                disabled={procesando}
                                className={`${BTN_SECONDARY} theme-element border border-orange-500/40 text-orange-600 outline-none`}
                            >
                                <AlertTriangle className="w-4 h-4 inline mr-1" /> Error de datos
                            </button>
                        )}
                        {puedeMarcarEnviado && (
                            <button
                                type="button"
                                onClick={() => setConfirmacion('enviar')}
                                disabled={procesando}
                                className={`${BTN_PRIMARY} flex items-center gap-2 outline-none disabled:opacity-50 ml-auto`}
                            >
                                <Truck className="w-4 h-4" /> Marcar enviado
                            </button>
                        )}
                        {puedeEmpacar && (
                            <button
                                type="button"
                                onClick={() => setConfirmacion('empacar')}
                                disabled={procesando}
                                className={`${BTN_PRIMARY} flex items-center gap-2 outline-none disabled:opacity-50 ml-auto`}
                            >
                                <CheckCircle2 className="w-4 h-4" /> Marcar empacado
                            </button>
                        )}
                        {puedeApartar && (
                            <button
                                type="button"
                                onClick={() => { onClose(); onMarcarApartado?.(pedido); }}
                                disabled={procesando}
                                className={`${BTN_PRIMARY} flex items-center gap-2 outline-none disabled:opacity-50`}
                            >
                                <PackageCheck className="w-4 h-4" /> Marcar apartado
                            </button>
                        )}
                    </div>
                </div>
            </div>

            <ModalVistaPreviaDocumento abierto={Boolean(docPreview)} documento={docPreview} onClose={() => setDocPreview(null)} />
            <ModalConfirmarAccion
                abierto={Boolean(cfgConfirm)}
                titulo={cfgConfirm?.titulo}
                mensaje={cfgConfirm?.mensaje}
                etiquetaConfirmar={cfgConfirm?.etiquetaConfirmar}
                variante={cfgConfirm?.variante}
                onClose={() => setConfirmacion(null)}
                onConfirm={ejecutarConfirmacion}
            />
            <ModalAlertaPedido
                abierto={alerta.abierto}
                tipo={alerta.tipo}
                titulo={alerta.titulo}
                mensaje={alerta.mensaje}
                onClose={() => setAlerta({ ...alerta, abierto: false })}
            />
        </>,
        document.body
    );
}
