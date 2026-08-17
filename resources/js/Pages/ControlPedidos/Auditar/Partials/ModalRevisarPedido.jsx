import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { router, usePage } from '@inertiajs/react';
import {
    X, CheckCircle2, AlertTriangle, FileText, Upload, Trash2, MapPin,
} from 'lucide-react';
import {
    badgeAuditoriaSemantico,
    badgeEstatusEnvio,
    badgeConComplementos,
    badgeCorregirRemision,
    badgePendienteReRevision,
    badgeHitoAuditoria,
    esPendienteReRevision,
    formatearMoneda,
    etiquetaAlmacen,
    etiquetaCostoEnvio,
    formatearFechaNegocio,
    formatearFechaHoraAuditoria,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
    anexoEnvioPendienteDe,
    puedeAnexarPagoEnvio,
    LABELS_ESTATUS_ENVIO,
    LABELS_ESTADO_SAF_APLICACION,
    etiquetaCodigo,
    tieneErrorRemision,
} from '../../Partials/pedidosBmaStyles';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import ModalVistaPreviaDocumento, { MiniaturaDocumento } from '../../Partials/ModalVistaPreviaDocumento';
import ModalConfirmarAccion from '../../Partials/ModalConfirmarAccion';
import ModalMotivoRechazo from '../../Partials/ModalMotivoRechazo';
import ModalReportarErrorDatos from '../../Partials/ModalReportarErrorDatos';
import ModalAlertaPedido from '../../Partials/ModalAlertaPedido';
import SeccionGuiaRastreo from '../../Partials/SeccionGuiaRastreo';
import DireccionPedidoResumen from '../../Partials/DireccionPedidoResumen';
import { codigoDireccionCliente } from '../../Partials/codigoDireccionCliente';
import ModalCambiarDireccion from '../../Partials/ModalCambiarDireccion';
import ModalLiberarResguardoAbierto from './ModalLiberarResguardoAbierto';
import SeccionPagosExhibicion from '../../Partials/SeccionPagosExhibicion';
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

export default function ModalRevisarPedido({ abierto, onClose, pedido: pedidoInicial, bancos = [] }) {
    const { auth } = usePage().props;
    const permisos = auth?.user?.permissions || [];
    const can = (p) => permisos.includes(p) || auth?.user?.roles?.includes('Super Admin');
    const [pedido, setPedido] = useState(pedidoInicial);
    const [procesando, setProcesando] = useState(false);
    const [docPreview, setDocPreview] = useState(null);
    const [confirmacion, setConfirmacion] = useState(null);
    const [motivoAnexoAbierto, setMotivoAnexoAbierto] = useState(false);
    const [errorDatosAbierto, setErrorDatosAbierto] = useState(false);
    const [liberarCapturaAbierto, setLiberarCapturaAbierto] = useState(false);
    const [cambiarDir, setCambiarDir] = useState(false);
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'success', titulo: '', mensaje: '' });

    useEffect(() => {
        if (abierto && pedidoInicial) {
            setPedido(pedidoInicial);
            setProcesando(false);
            setConfirmacion(null);
            setMotivoAnexoAbierto(false);
            setErrorDatosAbierto(false);
            setLiberarCapturaAbierto(false);
            setDocPreview(null);
        }
    }, [abierto, pedidoInicial?.id]);

    if (!abierto || !pedido) return null;

    const fase = pedido.estatus?.fase_ciclo;
    const badge = badgeAuditoriaSemantico(fase, pedido.es_resguardo);
    const badgeHito = badgeHitoAuditoria(pedido.hito_auditoria);
    const badgeEnvio = badgeEstatusEnvio(pedido.estatus_envio, { faseCiclo: fase });
    const badgeComp = badgeConComplementos(pedido);
    const badgeRemision = tieneErrorRemision(pedido) ? badgeCorregirRemision() : null;
    const badgeReRevision = esPendienteReRevision(pedido) ? badgePendienteReRevision() : null;
    const esPendiente = fase === 'PENDIENTE_AUXILIAR';
    const puedeLiberarResguardo = Boolean(pedido.es_resguardo) && (esPendiente || fase === 'EN_CEDIS');
    const requiereCapturaLiberacion = Boolean(pedido.es_resguardo)
        && (pedido.tipo_operacion_envio?.codigo === 'RESGUARDO_ABIERTO'
            || pedido.estatus_envio === 'pendiente_liberacion');
    const esRechazado = fase === 'RECHAZADO_VENDEDORA';
    const comprobantes = comprobantesDe(pedido);
    const remision = remisionDe(pedido);
    const pagoValidado = Boolean(pedido.pago_validado_at);
    const puedeAprobar = esPendiente && pagoValidado && Boolean(remision);
    const anexoPendiente = anexoEnvioPendienteDe(pedido);
    const puedeRevisarAnexo = Boolean(anexoPendiente) && pedido.estatus_envio === 'pendiente_revision_anexo';
    const puedeAnexar = puedeAnexarPagoEnvio(pedido);


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
            return;
        }

        if (accion === 'aprobar_anexo') {
            setProcesando(true);
            router.post(route('control_pedidos.auditar.anexo_envio.aprobar', pedido.id), {}, {
                preserveScroll: true,
                onSuccess: () => recargarPedido('Anexo de envío aprobado.'),
                onError: () => setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: 'No se pudo aprobar el anexo.' }),
                onFinish: () => setProcesando(false),
            });
        }
    };

    const enviarRechazoAnexo = (motivo) => {
        setMotivoAnexoAbierto(false);
        setProcesando(true);
        router.post(route('control_pedidos.auditar.anexo_envio.rechazar', pedido.id), { motivo }, {
            preserveScroll: true,
            onSuccess: () => recargarPedido('Anexo de envío rechazado.'),
            onError: () => setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: 'No se pudo rechazar el anexo.' }),
            onFinish: () => setProcesando(false),
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
        aprobar_anexo: {
            titulo: 'Aprobar anexo de envío',
            mensaje: '¿Aprobar el pago de envío y actualizar el costo del pedido?',
            etiquetaConfirmar: 'Aprobar anexo',
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
                            <div className="flex flex-wrap gap-2 mt-2">
                                <span className={`${badge.className} inline-flex`} style={badge.style}>{badge.label}</span>
                                {badgeHito && (
                                    <span className={`${badgeHito.className} inline-flex`} style={badgeHito.style}>{badgeHito.label}</span>
                                )}
                                {badgeReRevision && (
                                    <span className={`${badgeReRevision.className} inline-flex`} style={badgeReRevision.style}>{badgeReRevision.label}</span>
                                )}
                                {badgeRemision && (
                                    <span className={`${badgeRemision.className} inline-flex`} style={badgeRemision.style}>{badgeRemision.label}</span>
                                )}
                            </div>
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
                                    {badgeHito ? ` · ${badgeHito.label}` : ''}
                                </p>
                                {pedido.origen?.nombre && (
                                    <p className="text-xs font-black uppercase text-blue-600 mt-2 m-0">Tipo: {pedido.origen.nombre}</p>
                                )}
                                {pedido.es_resguardo && (
                                    <p className="text-xs font-black uppercase text-blue-600 mt-1 m-0">En resguardo — mercancía bloqueada en almacén</p>
                                )}
                                {badgeReRevision && (
                                    <p className="text-[10px] text-emerald-600 font-bold mt-2 m-0">
                                        Corregido y reenviado — dar luz verde si todo está en orden
                                    </p>
                                )}
                                {badgeRemision && (
                                    <div className="mt-3 p-3 rounded-xl bg-orange-500/10 border border-orange-500/30 space-y-1">
                                        <p className="text-sm font-bold text-orange-600 m-0 flex items-center gap-2">
                                            <AlertTriangle className="w-4 h-4" /> Corregir remisión
                                        </p>
                                        <p className="text-sm font-bold theme-text-main m-0">
                                            {pedido.detalle_error_datos || pedido.motivo_rechazo || 'CEDIS/Delegado reportó error en la remisión. Suba la remisión correcta y apruebe.'}
                                        </p>
                                    </div>
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
                                <div className="mt-3">
                                    <ListaErroresPedido errores={pedido.errores} />
                                </div>
                                {can('control_pedidos.auditar') && (pedido.saf_incidencias_abiertas || []).length > 0 && (
                                    <div className="mt-3 p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 space-y-2">
                                        <p className="text-sm font-bold text-amber-700 m-0 flex items-center gap-2">
                                            <AlertTriangle className="w-4 h-4" /> Alerta de saldos a favor (no detiene el pedido)
                                        </p>
                                        {(pedido.saf_incidencias_abiertas || []).map((inc) => (
                                            <div key={inc.id} className="space-y-2">
                                                <p className="text-sm font-bold theme-text-main m-0">{inc.descripcion}</p>
                                                <p className="text-[10px] theme-text-muted font-bold m-0 uppercase tracking-wide">
                                                    Tipo: {inc.tipo || '—'} · Corrige el monto si aplica, deja nota y continúa
                                                </p>
                                                <button
                                                    type="button"
                                                    className={`${BTN_SECONDARY} text-xs`}
                                                    disabled={procesando}
                                                    onClick={() => {
                                                        setProcesando(true);
                                                        router.post(
                                                            route('control_pedidos.auditar.incidencias_saf.resolver', {
                                                                pedidoBma: pedido.id,
                                                                incidencia: inc.id,
                                                            }),
                                                            { nota: 'Revisado en auditoría; se continúa con la incidencia.' },
                                                            {
                                                                preserveScroll: true,
                                                                onFinish: () => setProcesando(false),
                                                                onSuccess: () => {
                                                                    setPedido((prev) => ({
                                                                        ...prev,
                                                                        saf_incidencias_abiertas: (prev.saf_incidencias_abiertas || [])
                                                                            .filter((x) => x.id !== inc.id),
                                                                        tiene_alerta_saf: false,
                                                                    }));
                                                                },
                                                            }
                                                        );
                                                    }}
                                                >
                                                    Marcar revisado y continuar
                                                </button>
                                            </div>
                                        ))}
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
                                <Campo label="Folio de Pedido" value={pedido.folio_remision} />
                                <Campo label="Folio interno" value={pedido.folio} />
                                <Campo label="Fecha pedido" value={formatearFechaNegocio(pedido.fecha)} />
                                <Campo label="Registrado" value={formatearFechaHoraAuditoria(pedido.created_at)} />
                                <Campo label="Almacén" value={etiquetaAlmacen(pedido.almacen)} />
                                <Campo label="Tipo de pedido" value={pedido.origen?.nombre} />
                                <Campo
                                    label="Pagos / bancos"
                                    value={(pedido.fuentes_pago?.length
                                        ? pedido.fuentes_pago
                                        : (pedido.banco?.nombre ? [pedido.banco.nombre] : [])
                                    ).join(', ') || '—'}
                                />
                                <Campo label="Nota de compra en el envío" value={pedido.anexar_remision ? 'Sí' : 'No'} />
                                <Campo label="Saldo a favor aplicado" value={Number(pedido.saldo_a_favor) > 0 ? formatearMoneda(pedido.saldo_a_favor) : '—'} />
                                <Campo label="N° de cajas" value={pedido.numero_cajas} />
                                <Campo label="Capturado por" value={pedido.vendedor?.name} />
                            </div>
                        </section>

                        <section className={SECCION_WRAP}>
                            <p className={SECCION}>3. Pago del cliente</p>
                            <div className="grid grid-cols-2 gap-4 mb-4">
                                <Campo label="Monto a cobrar" value={formatearMoneda(pedido.total_a_cobrar)} />
                                <Campo
                                    label="Fuentes de pago"
                                    value={(pedido.fuentes_pago?.length
                                        ? pedido.fuentes_pago
                                        : (pedido.banco?.nombre ? [pedido.banco.nombre] : [])
                                    ).join(', ') || '—'}
                                />
                            </div>
                            {comprobantes.length > 0 && (
                                <div className="flex flex-wrap gap-2 mb-4">
                                    {comprobantes.map((doc) => (
                                        <MiniaturaDocumento key={doc.id} documento={doc} onVer={setDocPreview} />
                                    ))}
                                </div>
                            )}
                            {(pedido.saf_aplicaciones || []).filter((a) => a.estado !== 'liberado').length > 0 && (
                                <div className="mt-4 space-y-2">
                                    <p className="text-xs font-bold theme-text-muted uppercase">Saldos a favor aplicados/reservados</p>
                                    {(pedido.saf_aplicaciones || []).filter((a) => a.estado !== 'liberado').map((a) => (
                                        <div key={a.id} className="flex justify-between text-sm border theme-border theme-element rounded-xl px-3 py-2">
                                            <span className="font-bold theme-text-main">
                                                {a.credito?.folio || a.saf_credito_id}
                                                {' · '}
                                                {etiquetaCodigo(a.estado, LABELS_ESTADO_SAF_APLICACION)}
                                            </span>
                                            <span className="font-black" style={{ color: 'var(--color-primario)' }}>-{formatearMoneda(a.monto)}</span>
                                        </div>
                                    ))}
                                </div>
                            )}
                            <div className="mt-4">
                                <SeccionPagosExhibicion
                                    pedidoId={pedido.id}
                                    bancos={bancos}
                                    puedeRegistrar={false}
                                    puedeRevisar={esPendiente}
                                    puedeGenerarSaldo={false}
                                    rutaResumen="control_pedidos.pagos.resumen_auditoria"
                                    totalMercancia={pedido.total_mercancia}
                                    costoEnvio={pedido.costo_envio}
                                    aplicaSeguro={Boolean(pedido.aplica_seguro)}
                                    costoSeguro={pedido.costo_seguro}
                                    saldoAFavorAplicado={pedido.saldo_a_favor}
                                    mensajeBloqueo={esPendiente
                                        ? 'Revise cada exhibición. Todas deben quedar verificadas antes de validar el pago.'
                                        : null}
                                />
                            </div>
                            {esPendiente && (
                                <div className="mt-4">
                                    <button
                                        type="button"
                                        onClick={validarPago}
                                        disabled={procesando || pagoValidado}
                                        className={`${BTN_PRIMARY} flex items-center gap-2 outline-none disabled:opacity-50`}
                                    >
                                        <CheckCircle2 className="w-4 h-4" />
                                        {pagoValidado ? 'Pago validado' : 'Validar pago'}
                                    </button>
                                </div>
                            )}
                        </section>

                        <section className={SECCION_WRAP}>
                            <p className={SECCION}>4. Envío, costos y dirección</p>
                            <div className="grid grid-cols-2 gap-4 mb-3">
                                <Campo label="Tipo operación" value={pedido.tipo_operacion_envio?.nombre} />
                                <Campo label="Estatus envío" value={LABELS_ESTATUS_ENVIO[pedido.estatus_envio] || pedido.estatus_envio} />
                            </div>
                            {badgeEnvio && (
                                <span className={`${badgeEnvio.className} mb-3 inline-flex`} style={badgeEnvio.style}>{badgeEnvio.label}</span>
                            )}
                            {badgeComp && (
                                <span className={`${badgeComp.className} mb-3 ml-2 inline-flex`} style={badgeComp.style}>{badgeComp.label}</span>
                            )}
                            <div className="grid grid-cols-2 gap-4">
                                <Campo label="Paquetería" value={pedido.paqueteria?.nombre} />
                                <Campo label="Tipo caja" value={pedido.tipo_caja?.nombre} />
                                <Campo label="Peso real" value={pedido.peso_real_kg != null ? `${pedido.peso_real_kg} kg` : null} />
                                <Campo label="N° cajas" value={pedido.numero_cajas} />
                                <Campo label="Reexpedición" value={pedido.zona?.nombre} />
                                <Campo label="Pesaje CEDIS" value={pedido.pesaje_respondido_at ? 'Respondido' : (pedido.estatus_envio === 'pendiente_pesaje' ? 'Pendiente' : '—')} />
                            </div>
                            {(pedido.cajas || []).length > 0 && (
                                <div className="mt-3 space-y-2">
                                    <p className="text-[9px] font-black uppercase theme-text-muted m-0">Detalle de envíos</p>
                                    {[...(pedido.cajas || [])].sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0)).map((c, idx) => (
                                        <p key={c.id || idx} className="text-xs font-bold theme-text-main m-0">
                                            Envío {idx + 1}: {c.tipo_caja?.nombre || 'Caja'}
                                            {c.peso_real_kg != null ? ` · real ${c.peso_real_kg} kg` : ''}
                                            {c.peso_cobrado_kg != null ? ` · cobrado ${c.peso_cobrado_kg} kg` : ''}
                                        </p>
                                    ))}
                                </div>
                            )}
                            {(pedido.documentos || []).some((d) => d.tipo === 'pdf_pedido') && (
                                <div className="mt-3">
                                    <button
                                        type="button"
                                        onClick={() => setDocPreview((pedido.documentos || []).find((d) => d.tipo === 'pdf_pedido'))}
                                        className={`${BTN_SECONDARY} text-xs outline-none`}
                                    >
                                        Ver PDF del pedido
                                    </button>
                                </div>
                            )}
                            <div className="mt-4 p-4 rounded-xl border theme-border theme-element space-y-2 text-sm">
                                <div className="flex justify-between theme-text-muted font-bold"><span>Total de mercancía</span><span>{formatearMoneda(pedido.total_mercancia)}</span></div>
                                <div className="flex justify-between theme-text-muted font-bold">
                                    <span>{etiquetaCostoEnvio(pedido.paqueteria)}</span>
                                    <span>{formatearMoneda(pedido.costo_envio)}</span>
                                </div>
                                <div className="flex justify-between theme-text-muted font-bold">
                                    <span>Costo del seguro</span>
                                    <span>{pedido.aplica_seguro ? formatearMoneda(pedido.costo_seguro) : formatearMoneda(0)}</span>
                                </div>
                                <div className="flex justify-between theme-text-muted font-bold">
                                    <span>Total a cubrir</span>
                                    <span>{formatearMoneda(
                                        Number(pedido.total_mercancia || 0)
                                        + Number(pedido.costo_envio || 0)
                                        + (pedido.aplica_seguro ? Number(pedido.costo_seguro || 0) : 0)
                                    )}</span>
                                </div>
                                <div className="flex justify-between text-emerald-600 font-bold">
                                    <span>Saldo a favor aplicado</span>
                                    <span>- {formatearMoneda(pedido.saldo_a_favor)}</span>
                                </div>
                                <div className="flex justify-between font-black pt-2 border-t theme-border" style={{ color: 'var(--color-primario)' }}>
                                    <span>Total a cobrar ahora</span><span>{formatearMoneda(pedido.total_a_cobrar)}</span>
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

                        {(anexoPendiente || puedeAnexar || (pedido.anexos_envio || []).length > 0) && (
                            <section className={SECCION_WRAP}>
                                <p className={SECCION}>Anexo de pago de envío</p>
                                {anexoPendiente ? (
                                    <div className="space-y-3">
                                        <div className="grid grid-cols-2 gap-4">
                                            <Campo label="Monto" value={formatearMoneda(anexoPendiente.monto)} />
                                            <Campo label="Banco" value={anexoPendiente.banco?.nombre} />
                                            <Campo label="Registrado por" value={anexoPendiente.registrado_por?.name} />
                                            <Campo label="Fecha" value={formatearFechaHoraAuditoria(anexoPendiente.created_at)} />
                                        </div>
                                        {anexoPendiente.comentarios && (
                                            <Campo label="Comentarios" value={anexoPendiente.comentarios} />
                                        )}
                                        <MiniaturaDocumento
                                            documento={{
                                                ...anexoPendiente,
                                                tipo: 'comprobante',
                                                nombre_original: anexoPendiente.nombre_original || 'Comprobante envío',
                                            }}
                                            onVer={setDocPreview}
                                        />
                                        {puedeRevisarAnexo && (
                                            <div className="flex flex-wrap gap-2 pt-2">
                                                <button
                                                    type="button"
                                                    onClick={() => setConfirmacion({ accion: 'aprobar_anexo' })}
                                                    disabled={procesando}
                                                    className={`${BTN_PRIMARY} outline-none`}
                                                >
                                                    Aprobar anexo
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => setMotivoAnexoAbierto(true)}
                                                    disabled={procesando}
                                                    className={`${BTN_SECONDARY} border border-red-500/40 text-red-500 outline-none`}
                                                >
                                                    Rechazar anexo
                                                </button>
                                            </div>
                                        )}
                                    </div>
                                ) : (
                                    <p className="text-xs theme-text-muted font-bold italic m-0">
                                        {puedeAnexar
                                            ? 'Sin anexo pendiente. Use la acción Anexar pago de envío desde el listado.'
                                            : 'Sin anexo de envío pendiente.'}
                                    </p>
                                )}
                                {(pedido.anexos_envio || []).filter((a) => a.estatus !== 'pendiente').length > 0 && (
                                    <div className="mt-4 space-y-2">
                                        <p className="text-[9px] font-black uppercase theme-text-muted m-0">Historial de anexos</p>
                                        {(pedido.anexos_envio || []).filter((a) => a.estatus !== 'pendiente').map((a) => (
                                            <p key={a.id} className="text-xs font-bold theme-text-muted m-0">
                                                {a.estatus} · {formatearMoneda(a.monto)} · {formatearFechaHoraAuditoria(a.created_at)}
                                                {a.motivo_rechazo ? ` · ${a.motivo_rechazo}` : ''}
                                            </p>
                                        ))}
                                    </div>
                                )}
                            </section>
                        )}

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
                                onClick={() => {
                                    if (requiereCapturaLiberacion) {
                                        setLiberarCapturaAbierto(true);
                                    } else {
                                        setConfirmacion({ accion: 'liberar' });
                                    }
                                }}
                                disabled={procesando}
                                className={`${BTN_SECONDARY} theme-element border border-blue-500/40 text-blue-600 outline-none`}
                            >
                                Liberar resguardo
                            </button>
                        )}
                        {esPendiente && (
                            <>
                                <button type="button" onClick={() => setErrorDatosAbierto(true)} disabled={procesando} className={`${BTN_SECONDARY} theme-element border border-red-500/40 text-red-500 outline-none`}>
                                    Reportar error
                                </button>
                                <button
                                    type="button"
                                    onClick={() => setConfirmacion({ accion: 'aprobar' })}
                                    disabled={!puedeAprobar || procesando}
                                    className={`${BTN_PRIMARY} flex items-center gap-2 outline-none disabled:opacity-50 ml-auto`}
                                    title={!pagoValidado ? 'Valide el pago antes de aprobar' : !remision ? 'Adjunte la remisión PDF antes de aprobar' : ''}
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
            <ModalReportarErrorDatos
                abierto={errorDatosAbierto}
                pedido={pedido}
                origen="auditar"
                onClose={() => {
                    setErrorDatosAbierto(false);
                    onClose();
                }}
            />
            <ModalMotivoRechazo
                abierto={motivoAnexoAbierto}
                onClose={() => setMotivoAnexoAbierto(false)}
                onConfirm={enviarRechazoAnexo}
                titulo="Rechazar anexo de envío"
            />
            <ModalAlertaPedido
                abierto={alerta.abierto}
                tipo={alerta.tipo}
                titulo={alerta.titulo}
                mensaje={alerta.mensaje}
                onClose={() => setAlerta({ ...alerta, abierto: false })}
            />
            <ModalCambiarDireccion abierto={cambiarDir} onClose={() => setCambiarDir(false)} pedido={pedido} />
            <ModalLiberarResguardoAbierto
                abierto={liberarCapturaAbierto}
                pedido={pedido}
                bancos={bancos}
                onClose={() => setLiberarCapturaAbierto(false)}
                onSuccess={() => {
                    setLiberarCapturaAbierto(false);
                    recargarPedido('Resguardo liberado correctamente.');
                }}
            />
        </>,
        document.body
    );
}
