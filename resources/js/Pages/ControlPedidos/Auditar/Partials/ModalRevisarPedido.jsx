import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { router, usePage } from '@inertiajs/react';
import {
    X, CheckCircle2, AlertTriangle, FileText, Upload, Trash2, MapPin,
} from 'lucide-react';
import {
    badgeAuditoriaSemantico,
    formatearMoneda,
    etiquetaAlmacen,
    formatearFechaNegocio,
    formatearFechaHoraAuditoria,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
} from '../../Partials/pedidosBmaStyles';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import ModalVistaPreviaDocumento, { MiniaturaDocumento } from '../../Partials/ModalVistaPreviaDocumento';
import ModalConfirmarAccion from '../../Partials/ModalConfirmarAccion';
import ModalMotivoRechazo from '../../Partials/ModalMotivoRechazo';
import ModalAlertaPedido from '../../Partials/ModalAlertaPedido';
import SeccionGuiaRastreo from '../../Partials/SeccionGuiaRastreo';
import DireccionPedidoResumen from '../../Partials/DireccionPedidoResumen';
import { codigoDireccionCliente } from '../../Partials/codigoDireccionCliente';
import ModalCambiarDireccion from '../../Partials/ModalCambiarDireccion';

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
    const { auth } = usePage().props;
    const permisos = auth?.user?.permissions || [];
    const can = (p) => permisos.includes(p) || auth?.user?.roles?.includes('Super Admin');
    const [pedido, setPedido] = useState(pedidoInicial);
    const [procesando, setProcesando] = useState(false);
    const [docPreview, setDocPreview] = useState(null);
    const [confirmacion, setConfirmacion] = useState(null);
    const [motivoRechazoAbierto, setMotivoRechazoAbierto] = useState(false);
    const [cambiarDir, setCambiarDir] = useState(false);
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'success', titulo: '', mensaje: '' });

    useEffect(() => {
        if (abierto && pedidoInicial) {
            setPedido(pedidoInicial);
            setProcesando(false);
            setConfirmacion(null);
            setMotivoRechazoAbierto(false);
            setDocPreview(null);
        }
    }, [abierto, pedidoInicial?.id]);

    if (!abierto || !pedido) return null;

    const fase = pedido.estatus?.fase_ciclo;
    const badge = badgeAuditoriaSemantico(fase, pedido.es_resguardo);
    const esPendiente = fase === 'PENDIENTE_AUXILIAR';
    const puedeLiberarResguardo = Boolean(pedido.es_resguardo) && (esPendiente || fase === 'EN_CEDIS');
    const esRechazado = fase === 'RECHAZADO_VENDEDORA';
    const comprobantes = comprobantesDe(pedido);
    const remision = remisionDe(pedido);
    const pagoValidado = Boolean(pedido.pago_validado_at);
    const puedeAprobar = esPendiente && pagoValidado && Boolean(remision);


    const recargarPedido = (mensajeExito = null) => {
        router.reload({
            only: ['pedidos'],
            preserveScroll: true,
            onSuccess: (page) => {
                const actualizado = page.props.pedidos?.data?.find((p) => p.id === pedido.id);
                if (actualizado) setPedido(actualizado);
                if (mensajeExito) {
                    setAlerta({ abierto: true, tipo: 'success', titulo: 'Operación exitosa', mensaje: mensajeExito });
                }
            },
        });
    };

    const validarPago = () => {
        setProcesando(true);
        router.post(route('control_pedidos.auditar.validar_pago', pedido.id), {}, {
            preserveScroll: true,
            onSuccess: () => recargarPedido('Pago validado correctamente.'),
            onError: (errors) => setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: errors?.message || 'No se pudo validar el pago.' }),
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
            onSuccess: () => recargarPedido('Remisión adjuntada correctamente.'),
            onError: () => setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: 'No se pudo adjuntar la remisión.' }),
        });
    };

    const ejecutarConfirmacion = () => {
        const accion = confirmacion?.accion;
        setConfirmacion(null);

        if (accion === 'eliminar_remision') {
            router.delete(route('control_pedidos.auditar.remision.destroy', pedido.id), {
                preserveScroll: true,
                onSuccess: () => recargarPedido('Remisión eliminada.'),
                onError: () => setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: 'No se pudo eliminar la remisión.' }),
            });
            return;
        }

        if (accion === 'aprobar') {
            if (!puedeAprobar) return;
            setProcesando(true);
            router.post(route('control_pedidos.auditar.aprobar', pedido.id), {}, {
                preserveScroll: true,
                onSuccess: () => {
                    setAlerta({ abierto: true, tipo: 'success', titulo: 'Pedido aprobado', mensaje: 'Pedido aprobado y enviado a Registro General.' });
                    onClose();
                },
                onError: () => setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: 'No se pudo aprobar el pedido.' }),
                onFinish: () => setProcesando(false),
            });
            return;
        }

        if (accion === 'liberar') {
            setProcesando(true);
            router.post(route('control_pedidos.auditar.liberar_resguardo', pedido.id), {}, {
                preserveScroll: true,
                onSuccess: () => recargarPedido('Resguardo liberado correctamente.'),
                onError: () => setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: 'No se pudo liberar el resguardo.' }),
                onFinish: () => setProcesando(false),
            });
        }
    };

    const enviarRechazo = (motivo) => {
        setMotivoRechazoAbierto(false);
        router.post(route('control_pedidos.auditar.rechazar', pedido.id), { motivo }, {
            preserveScroll: true,
            onSuccess: () => {
                setAlerta({ abierto: true, tipo: 'success', titulo: 'Pedido rechazado', mensaje: 'Pedido devuelto a la vendedora.' });
                onClose();
            },
            onError: () => setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: 'No se pudo rechazar el pedido.' }),
        });
    };

    const confirmaciones = {
        eliminar_remision: {
            titulo: 'Eliminar remisión',
            mensaje: '¿Eliminar la remisión adjunta?',
            etiquetaConfirmar: 'Eliminar',
            variante: 'danger',
        },
        aprobar: {
            titulo: 'Aprobar pedido',
            mensaje: '¿Aprobar y enviar este pedido a Registro General?',
            etiquetaConfirmar: 'Aprobar',
            variante: 'primary',
        },
        liberar: {
            titulo: 'Liberar resguardo',
            mensaje: '¿Liberar el resguardo de este pedido?',
            etiquetaConfirmar: 'Liberar',
            variante: 'primary',
        },
    };

    const cfgConfirm = confirmacion ? confirmaciones[confirmacion.accion] : null;

    return createPortal(
        <>
            <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
                <div
                    className={`${THEME_MODAL_SHELL} max-w-3xl w-full flex flex-col ${pedido.es_resguardo ? 'ring-2 ring-blue-500/50' : ''}`}
                    style={{
                        maxHeight: 'calc(100dvh - 2rem)',
                        ...(pedido.es_resguardo ? { backgroundColor: 'color-mix(in srgb, #3B82F6 6%, var(--color-surface))' } : {}),
                    }}
                    onClick={(e) => e.stopPropagation()}
                >
                    <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                        <div className="min-w-0">
                            <p className="text-[10px] font-black uppercase theme-text-muted m-0 mb-1">Revisar pedido</p>
                            <EncabezadoFolioPedido pedido={pedido} size="lg" />
                            <span className={`${badge.className} mt-2 inline-flex`} style={badge.style}>{badge.label}</span>
                        </div>
                        <button type="button" onClick={onClose} className="p-2 rounded-full theme-text-muted hover:theme-text-main outline-none shrink-0" aria-label="Cerrar">
                            <X className="w-5 h-5" />
                        </button>
                    </div>

                    <div className="gelia-modal-body p-5 md:p-6 space-y-6">
                        <section className={SECCION_WRAP}>
                            <p className={SECCION}>1. Estado y alertas</p>
                            <div className={`p-4 rounded-xl border theme-border ${esRechazado ? 'bg-red-500/10 border-red-500/30' : pedido.es_resguardo ? 'bg-blue-500/10 border-blue-500/30' : 'theme-element'}`}>
                                <p className="text-sm font-bold theme-text-main m-0">
                                    Estado actual: <span style={{ color: badge.style?.color }}>{badge.label}</span>
                                </p>
                                {pedido.origen?.nombre && (
                                    <p className="text-xs font-black uppercase text-blue-600 mt-2 m-0">Origen: {pedido.origen.nombre}</p>
                                )}
                                {pedido.es_resguardo && (
                                    <p className="text-xs font-black uppercase text-blue-600 mt-1 m-0">En resguardo — mercancía bloqueada en almacén</p>
                                )}
                                {esRechazado && pedido.motivo_rechazo && (
                                    <p className="text-sm text-red-500 font-bold mt-2 m-0 flex items-start gap-2">
                                        <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" />
                                        Motivo del reporte: {pedido.motivo_rechazo}
                                    </p>
                                )}
                                {(fase === 'INCIDENCIA_CEDIS' || pedido.detalle_incidencia_empaque) && (
                                    <div className="mt-3 p-3 rounded-xl bg-orange-500/10 border border-orange-500/30 space-y-1">
                                        <p className="text-sm font-bold text-orange-600 m-0 flex items-center gap-2">
                                            <AlertTriangle className="w-4 h-4" /> Error reportado en CEDIS
                                        </p>
                                        <p className="text-sm font-bold theme-text-main m-0">{pedido.detalle_incidencia_empaque}</p>
                                        {pedido.incidencia_empaque_at && (
                                            <p className="text-xs theme-text-muted font-bold m-0 font-mono">
                                                Reportado por {(pedido.incidencia_empaque_por?.name || pedido.incidenciaEmpaquePor?.name) || '—'} el {formatearFechaHoraAuditoria(pedido.incidencia_empaque_at)}
                                            </p>
                                        )}
                                    </div>
                                )}
                                {pagoValidado && (
                                    <p className="text-xs text-emerald-600 font-bold mt-2 m-0 flex items-center gap-1">
                                        <CheckCircle2 className="w-4 h-4" />
                                        Pago validado el {formatearFechaHoraAuditoria(pedido.pago_validado_at)}
                                        {(pedido.pago_validado_por?.name || pedido.pagoValidadoPor?.name) && ` por ${pedido.pago_validado_por?.name || pedido.pagoValidadoPor?.name}`}
                                    </p>
                                )}
                            </div>
                        </section>

                        <section className={SECCION_WRAP}>
                            <p className={SECCION}>2. Datos generales</p>
                            <div className="grid grid-cols-2 gap-4">
                                <Campo label="N° Cliente" value={pedido.cliente?.numero_cliente} />
                                <Campo label="Nombre" value={pedido.cliente?.nombre} />
                                <Campo label="Folio remisión" value={pedido.folio_remision} />
                                <Campo label="Folio interno" value={pedido.folio} />
                                <Campo label="Fecha pedido" value={formatearFechaNegocio(pedido.fecha)} />
                                <Campo label="Registrado" value={formatearFechaHoraAuditoria(pedido.created_at)} />
                                <Campo label="Almacén" value={etiquetaAlmacen(pedido.almacen)} />
                                <Campo label="Origen" value={pedido.origen?.nombre} />
                                <Campo label="Banco" value={pedido.banco?.nombre} />
                                <Campo label="Anexar remisión" value={pedido.anexar_remision ? 'Sí' : 'No'} />
                                <Campo label="Saldo a favor" value={Number(pedido.saldo_a_favor) > 0 ? formatearMoneda(pedido.saldo_a_favor) : '—'} />
                                <Campo label="N° de cajas" value={pedido.numero_cajas} />
                                <Campo label="Capturado por" value={pedido.vendedor?.name} />
                            </div>
                        </section>

                        <section className={SECCION_WRAP}>
                            <p className={SECCION}>3. Pago del cliente</p>
                            <div className="grid grid-cols-2 gap-4 mb-4">
                                <Campo label="Monto pagado" value={formatearMoneda(pedido.total_a_cobrar)} />
                                <Campo label="Método de pago" value={pedido.banco?.nombre} />
                            </div>
                            {comprobantes.length > 0 ? (
                                <div className="flex flex-wrap gap-2 mb-4">
                                    {comprobantes.map((doc) => (
                                        <MiniaturaDocumento key={doc.id} documento={doc} onVer={setDocPreview} />
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

                        <section className={SECCION_WRAP}>
                            <p className={SECCION}>4. Envío, costos y dirección</p>
                            <div className="grid grid-cols-2 gap-4">
                                <Campo label="Paquetería" value={pedido.paqueteria?.nombre} />
                                <Campo label="Tipo caja" value={pedido.tipo_caja?.nombre} />
                                <Campo label="Peso real" value={pedido.peso_real_kg != null ? `${pedido.peso_real_kg} kg` : null} />
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
                            <SeccionGuiaRastreo pedido={pedido} onVerPdf={setDocPreview} compact />
                            <div className="mt-4 space-y-3">
                                <div className="flex items-center justify-between gap-2">
                                    <p className="text-[9px] font-black uppercase theme-text-muted m-0">Domicilio de envío</p>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {can('clientes.direcciones.ver') && pedido.cliente?.id && (
                                            <a
                                                href={route('control_pedidos.direcciones.cliente', pedido.cliente.id)}
                                                target="_blank"
                                                rel="noreferrer"
                                                className={`${BTN_SECONDARY} text-xs py-1.5 px-2 inline-flex items-center gap-1`}
                                            >
                                                <MapPin className="w-3.5 h-3.5" /> Gestionar direcciones
                                            </a>
                                        )}
                                        {can('control_pedidos.direccion.cambiar') && (
                                            <button type="button" className={`${BTN_SECONDARY} text-xs py-1.5 px-2 inline-flex items-center gap-1`} onClick={() => setCambiarDir(true)}>
                                                <MapPin className="w-3.5 h-3.5" /> Cambiar
                                            </button>
                                        )}
                                    </div>
                                </div>
                                <DireccionPedidoResumen
                                    direccion={pedido.direccion_vigente || pedido.direccionVigente}
                                    domicilioLegacy={pedido.domicilio_entrega}
                                    codigoPostal={pedido.codigo_postal}
                                codigoDireccion={codigoDireccionCliente(
                                    pedido.cliente?.numero_cliente,
                                    (pedido.direccion_vigente || pedido.direccionVigente)?.numero_direccion,
                                )}
                                />
                                {pedido.envia_a_otra_persona && <Campo label="Destinatario alterno" value={pedido.envia_otra_persona} />}
                                <Campo label="Comentarios" value={pedido.comentarios_drive} />
                            </div>
                        </section>

                        <section className={SECCION_WRAP}>
                            <p className={SECCION}>5. Remisión</p>
                            {remision ? (
                                <div className="space-y-3">
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
                                                Ver PDF
                                            </button>
                                        </div>
                                        {esPendiente && (
                                            <button
                                                type="button"
                                                onClick={() => setConfirmacion({ accion: 'eliminar_remision' })}
                                                className="p-2 rounded-lg hover:bg-red-500/10 outline-none"
                                                title="Eliminar"
                                            >
                                                <Trash2 className="w-4 h-4 text-red-500" />
                                            </button>
                                        )}
                                    </div>
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

                    <div className="gelia-modal-footer flex flex-wrap gap-3 p-5 md:p-6 border-t theme-border shrink-0">
                        <button type="button" onClick={onClose} className={`${BTN_SECONDARY} theme-element border theme-border outline-none`}>
                            Cerrar
                        </button>
                        {puedeLiberarResguardo && (
                            <button
                                type="button"
                                onClick={() => setConfirmacion({ accion: 'liberar' })}
                                disabled={procesando}
                                className={`${BTN_SECONDARY} theme-element border border-blue-500/40 text-blue-600 outline-none`}
                            >
                                Liberar resguardo
                            </button>
                        )}
                        {esPendiente && (
                            <>
                                <button type="button" onClick={() => setMotivoRechazoAbierto(true)} disabled={procesando} className={`${BTN_SECONDARY} theme-element border border-red-500/40 text-red-500 outline-none`}>
                                    Reportar problema
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setConfirmacion({ accion: 'aprobar' })}
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
            <ModalMotivoRechazo
                abierto={motivoRechazoAbierto}
                onClose={() => setMotivoRechazoAbierto(false)}
                onConfirm={enviarRechazo}
            />
            <ModalAlertaPedido
                abierto={alerta.abierto}
                tipo={alerta.tipo}
                titulo={alerta.titulo}
                mensaje={alerta.mensaje}
                onClose={() => setAlerta({ ...alerta, abierto: false })}
            />
            <ModalCambiarDireccion abierto={cambiarDir} onClose={() => setCambiarDir(false)} pedido={pedido} />
        </>,
        document.body
    );
}
