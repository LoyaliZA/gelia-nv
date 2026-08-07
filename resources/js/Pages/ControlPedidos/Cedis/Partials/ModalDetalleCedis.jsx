import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import {
    X, CheckCircle2, AlertTriangle, FileText, User, Truck, PackageCheck,
} from 'lucide-react';
import {
    badgeEmpaqueSemantico,
    badgeRetrasoGuia,
    badgeConComplementos,
    complementosDe,
    esPedidoEmpacadoCedis,
    formatearMoneda,
    etiquetaAlmacen,
    etiquetaCostoEnvio,
    formatearFechaHoraAuditoria,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
    tieneGuiaPdfDisponible,
} from '../../Partials/pedidosBmaStyles';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import DireccionPedidoResumen from '../../Partials/DireccionPedidoResumen';
import { codigoDireccionCliente } from '../../Partials/codigoDireccionCliente';
import ModalVistaPreviaDocumento, { MiniaturaDocumento } from '../../Partials/ModalVistaPreviaDocumento';
import ModalConfirmarAccion from '../../Partials/ModalConfirmarAccion';
import ModalAlertaPedido from '../../Partials/ModalAlertaPedido';
import SeccionGuiaRastreo from '../../Partials/SeccionGuiaRastreo';
import AvisoOperativoPedido from '../../Partials/AvisoOperativoPedido';
import ListaErroresPedido from '../../Partials/ListaErroresPedido';

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
    abierto, onClose, pedido: pedidoInicial, onReportarErrorDatos, onMarcarApartado,
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
    const badgeEmpaque = badgeEmpaqueSemantico(fase, pedido.es_resguardo, Boolean(pedido.resguardo_apartado_at));
    const badgeRetraso = pedido.guia_retraso ? badgeRetrasoGuia() : null;
    const badgeComp = badgeConComplementos(pedido);
    const complementos = complementosDe(pedido);
    const comprobantes = comprobantesDe(pedido);
    const remision = remisionDe(pedido);
    const evidenciasApartado = (pedido?.documentos || []).filter((d) => d.tipo === 'evidencia_apartado');
    const esErrorCedis = fase === 'INCIDENCIA_CEDIS';
    const esEmpacado = esPedidoEmpacadoCedis(fase);
    const puedeEmpacar = (fase === 'EN_CEDIS' || fase === 'INCIDENCIA_CEDIS') && !pedido.es_resguardo;
    const puedeMarcarEnviado = fase === 'PENDIENTE_DE_ENVIO';
    const puedeReportarError = ['EN_CEDIS', 'INCIDENCIA_CEDIS', 'PENDIENTE_DE_GUIA', 'PENDIENTE_DE_ENVIO'].includes(fase) && !pedido.es_resguardo;
    const puedeApartar = Boolean(pedido.es_resguardo) && fase === 'EN_CEDIS' && !pedido.resguardo_apartado_at;
    const mostrarGuia = tieneGuiaPdfDisponible(pedido) || Boolean(pedido.numero_rastreo)
        || fase === 'PENDIENTE_DE_ENVIO' || fase === 'ENVIADO';

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
        ? {
            titulo: complementos.length ? 'Confirmar empaque del grupo' : 'Confirmar empaque',
            mensaje: complementos.length
                ? `Se empacará ${pedido.folio} y ${complementos.length} complemento(s).`
                : '¿Confirmar que el pedido fue empacado?',
            etiquetaConfirmar: complementos.length ? 'Empacar grupo' : 'Marcar empacado',
            variante: 'primary',
        }
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
                    <div className="p-4 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                        <div className="min-w-0">
                            <p className="text-[10px] font-black uppercase theme-text-muted m-0 mb-1">Detalle CEDIS</p>
                            <EncabezadoFolioPedido pedido={pedido} size="lg" />
                            {pedido.vendedor?.name && (
                                <p className="text-xs font-bold theme-text-muted mt-2 m-0 flex items-center gap-1">
                                    <User className="w-3.5 h-3.5" /> Capturado por: {pedido.vendedor.name}
                                </p>
                            )}
                            <div className="flex flex-wrap gap-2 mt-2">
                                <span className={badgeEmpaque.className} style={badgeEmpaque.style}>
                                    {badgeEmpaque.label}
                                </span>
                                {badgeRetraso && (
                                    <span className={badgeRetraso.className} style={badgeRetraso.style}>
                                        {badgeRetraso.label}
                                    </span>
                                )}
                                {badgeComp && (
                                    <span className={badgeComp.className} style={badgeComp.style}>
                                        {badgeComp.label}
                                    </span>
                                )}
                            </div>
                        </div>
                        <button type="button" onClick={onClose} className="p-2 min-h-[44px] min-w-[44px] rounded-full theme-text-muted hover:theme-text-main outline-none shrink-0 inline-flex items-center justify-center" aria-label="Cerrar">
                            <X className="w-5 h-5" />
                        </button>
                    </div>

                    <div className="gelia-modal-body p-4 md:p-6 space-y-6">
                        {/* 1. Estatus / avisos / errores */}
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
                                {esErrorCedis && (
                                    <AvisoOperativoPedido
                                        label="Error reportado"
                                        tono="danger"
                                        icon={AlertTriangle}
                                    >
                                        {pedido.detalle_incidencia_empaque || pedido.detalle_error_datos || 'Error CEDIS reportado'}
                                        {pedido.incidencia_empaque_at && (
                                            <span className="block text-sm font-bold mt-1 opacity-80 font-mono">
                                                {(pedido.incidencia_empaque_por?.name || pedido.incidenciaEmpaquePor?.name) || '—'}
                                                {' · '}
                                                {formatearFechaHoraAuditoria(pedido.incidencia_empaque_at)}
                                            </span>
                                        )}
                                    </AvisoOperativoPedido>
                                )}
                                <ListaErroresPedido errores={pedido.errores} />
                                {!pedido.es_resguardo && !esEmpacado && !esErrorCedis && fase === 'EN_CEDIS' && (
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
                            </div>
                        </section>

                        {/* 2. Nota de compra + guía */}
                        <section className={SECCION_WRAP}>
                            <p className={SECCION}>Empaque y guía</p>
                            <div className="space-y-3">
                                <AvisoOperativoPedido
                                    label="Nota de compra en el envío"
                                    tono={pedido.anexar_remision ? 'success' : 'warning'}
                                    icon={FileText}
                                >
                                    {pedido.anexar_remision
                                        ? 'Incluir nota de compra en el paquete'
                                        : 'No incluir nota de compra (dropshipping)'}
                                </AvisoOperativoPedido>
                                {mostrarGuia && (
                                    <SeccionGuiaRastreo pedido={pedido} onVerPdf={setDocPreview} />
                                )}
                            </div>
                        </section>

                        {/* 3. Documentos */}
                        {(comprobantes.length > 0 || remision || evidenciasApartado.length > 0 || complementos.length > 0) && (
                            <section className={SECCION_WRAP}>
                                <p className={SECCION}>Documentos</p>
                                {remision && (
                                    <div className="flex items-center gap-3 p-4 rounded-xl border theme-border theme-element mb-3">
                                        <FileText className="w-8 h-8 shrink-0" style={{ color: 'var(--color-primario)' }} />
                                        <div className="min-w-0 flex-1">
                                            <p className="text-sm font-bold theme-text-main m-0 truncate">{remision.nombre_original}</p>
                                            <button
                                                type="button"
                                                onClick={() => setDocPreview(remision)}
                                                className="text-xs font-bold inline-flex items-center gap-1 mt-1 outline-none min-h-[40px]"
                                                style={{ color: 'var(--color-primario)' }}
                                            >
                                                Ver remisión PDF
                                            </button>
                                        </div>
                                    </div>
                                )}
                                {comprobantes.length > 0 && (
                                    <div className="mb-3">
                                        <p className="text-[9px] font-black uppercase theme-text-muted mb-2">Comprobantes</p>
                                        <div className="flex flex-wrap gap-2">
                                            {comprobantes.map((doc) => (
                                                <MiniaturaDocumento key={doc.id} documento={doc} onVer={setDocPreview} />
                                            ))}
                                        </div>
                                    </div>
                                )}
                                {evidenciasApartado.length > 0 && (
                                    <div className="mb-3">
                                        <p className="text-[9px] font-black uppercase theme-text-muted mb-2">Evidencia de apartado</p>
                                        <div className="flex flex-wrap gap-2">
                                            {evidenciasApartado.map((doc) => (
                                                <MiniaturaDocumento key={doc.id} documento={doc} onVer={setDocPreview} />
                                            ))}
                                        </div>
                                    </div>
                                )}
                                {complementos.length > 0 && (
                                    <div className="space-y-3">
                                        <p className="text-[9px] font-black uppercase theme-text-muted m-0">Remisiones del grupo</p>
                                        {[pedido, ...complementos].map((p) => {
                                            const rem = remisionDe(p);
                                            return (
                                                <div key={p.id} className="p-3 rounded-xl border theme-border theme-element space-y-2">
                                                    <p className="text-sm font-black theme-text-main m-0">
                                                        {p.folio}
                                                        {p.folio_remision ? ` · ${p.folio_remision}` : ''}
                                                        {p.id === pedido.id ? ' · principal' : ' · complemento'}
                                                    </p>
                                                    <p className="text-[10px] theme-text-muted font-bold m-0">
                                                        {formatearMoneda(p.total_mercancia)}
                                                    </p>
                                                    {rem && (
                                                        <button
                                                            type="button"
                                                            onClick={() => setDocPreview(rem)}
                                                            className="text-xs font-bold inline-flex items-center gap-1 outline-none min-h-[40px]"
                                                            style={{ color: 'var(--color-primario)' }}
                                                        >
                                                            <FileText className="w-3.5 h-3.5" /> Ver remisión
                                                        </button>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                            </section>
                        )}

                        {/* 4. Datos operativos */}
                        <section className={SECCION_WRAP}>
                            <p className={SECCION}>Datos operativos</p>
                            <div className="grid grid-cols-2 gap-4">
                                <Campo label="Cliente" value={pedido.cliente?.nombre} />
                                <Campo label="N° Cliente" value={pedido.cliente?.numero_cliente} />
                                <Campo label="Origen" value={pedido.origen?.nombre} />
                                <Campo label="Almacén" value={etiquetaAlmacen(pedido.almacen)} />
                                <Campo label="Paquetería" value={pedido.paqueteria?.nombre} />
                                <Campo label="N° de cajas" value={pedido.numero_cajas} />
                                <Campo label="Tipo de guía" value={pedido.tipo_guia?.nombre} />
                                <Campo label="Peso real" value={pedido.peso_real_kg != null ? `${pedido.peso_real_kg} kg` : null} />
                                <Campo label="Registrado" value={formatearFechaHoraAuditoria(pedido.created_at)} />
                                <Campo label="Seguro" value={pedido.aplica_seguro ? formatearMoneda(pedido.costo_seguro) : 'No aplica'} />
                            </div>
                            {(pedido.cajas || []).length > 0 && (
                                <div className="mt-3 space-y-2">
                                    <p className="text-[9px] font-black uppercase theme-text-muted m-0">Detalle de envíos (pesaje)</p>
                                    {[...(pedido.cajas || [])].sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0)).map((c, idx) => (
                                        <p key={c.id || idx} className="text-xs font-bold theme-text-main m-0">
                                            Envío {idx + 1}: {c.tipo_caja?.nombre || 'Caja'}
                                            {c.peso_real_kg != null ? ` · real ${c.peso_real_kg} kg` : ''}
                                            {c.peso_cobrado_kg != null ? ` · cobrado ${c.peso_cobrado_kg} kg` : ''}
                                        </p>
                                    ))}
                                </div>
                            )}
                            {pedido.comentarios_drive && (
                                <div className="mt-4">
                                    <p className="text-[9px] font-black uppercase theme-text-muted m-0 mb-1">Comentarios para Drive</p>
                                    <p className="text-sm font-bold theme-text-main m-0">{pedido.comentarios_drive}</p>
                                </div>
                            )}
                        </section>

                        {/* 5. Dirección / costos (secundario) */}
                        <section className={SECCION_WRAP}>
                            <p className={SECCION}>Dirección y costos</p>
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
                                {pedido.es_resguardo && (
                                    <Campo
                                        label="Resguardo"
                                        value={pedido.resguardo_apartado_at
                                            ? `Apartado · ${formatearFechaHoraAuditoria(pedido.resguardo_apartado_at)}`
                                            : 'Sí — pendiente de apartar'}
                                    />
                                )}
                                {pedido.detalle_resguardo_apartado && (
                                    <Campo label="Nota apartado" value={pedido.detalle_resguardo_apartado} />
                                )}
                                {pedido.envia_a_otra_persona && <Campo label="Destinatario alterno" value={pedido.envia_otra_persona} />}
                                <div className="mt-2 p-4 rounded-xl border theme-border theme-element space-y-2 text-sm">
                                    <div className="flex justify-between theme-text-muted font-bold"><span>Total de mercancía</span><span>{formatearMoneda(pedido.total_mercancia)}</span></div>
                                    <div className="flex justify-between theme-text-muted font-bold">
                                        <span>{etiquetaCostoEnvio(pedido.paqueteria)}</span>
                                        <span>{formatearMoneda(pedido.costo_envio)}</span>
                                    </div>
                                    <div className="flex justify-between theme-text-muted font-bold">
                                        <span>Costo del seguro</span>
                                        <span>{pedido.aplica_seguro ? formatearMoneda(pedido.costo_seguro) : formatearMoneda(0)}</span>
                                    </div>
                                    <div className="flex justify-between text-emerald-600 font-bold">
                                        <span>Saldo a favor aplicado</span>
                                        <span>- {formatearMoneda(pedido.saldo_a_favor)}</span>
                                    </div>
                                    <div className="flex justify-between font-black pt-2 border-t theme-border" style={{ color: 'var(--color-primario)' }}>
                                        <span>Total final del pedido</span><span>{formatearMoneda(pedido.total_a_cobrar)}</span>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div className="gelia-modal-footer flex flex-col-reverse sm:flex-row sm:flex-wrap gap-3 p-4 md:p-6 border-t theme-border shrink-0">
                        <button type="button" onClick={onClose} className={`${BTN_SECONDARY} theme-element border theme-border outline-none min-h-[44px] w-full sm:w-auto`}>
                            Cerrar
                        </button>
                        {puedeReportarError && (
                            <button
                                type="button"
                                onClick={() => onReportarErrorDatos?.(pedido)}
                                disabled={procesando}
                                className={`${BTN_SECONDARY} theme-element border border-orange-500/40 text-orange-600 outline-none min-h-[44px] w-full sm:w-auto`}
                            >
                                <AlertTriangle className="w-4 h-4 inline mr-1" /> Reportar error
                            </button>
                        )}
                        {puedeApartar && (
                            <button
                                type="button"
                                onClick={() => { onClose(); onMarcarApartado?.(pedido); }}
                                disabled={procesando}
                                className={`${BTN_PRIMARY} flex items-center justify-center gap-2 outline-none disabled:opacity-50 min-h-[44px] w-full sm:w-auto`}
                            >
                                <PackageCheck className="w-4 h-4" /> Marcar apartado
                            </button>
                        )}
                        {puedeMarcarEnviado && (
                            <button
                                type="button"
                                onClick={() => setConfirmacion('enviar')}
                                disabled={procesando}
                                className={`${BTN_PRIMARY} flex items-center justify-center gap-2 outline-none disabled:opacity-50 min-h-[44px] w-full sm:w-auto sm:ml-auto`}
                            >
                                <Truck className="w-4 h-4" /> Marcar enviado
                            </button>
                        )}
                        {puedeEmpacar && (
                            <button
                                type="button"
                                onClick={() => setConfirmacion('empacar')}
                                disabled={procesando}
                                className={`${BTN_PRIMARY} flex items-center justify-center gap-2 outline-none disabled:opacity-50 min-h-[44px] w-full sm:w-auto sm:ml-auto`}
                            >
                                <CheckCircle2 className="w-4 h-4" /> {complementos.length ? 'Empacar grupo' : 'Marcar empacado'}
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
