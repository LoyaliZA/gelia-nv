import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { router, usePage } from '@inertiajs/react';
import axios from 'axios';
import {
    X, CheckCircle2, FileText, Upload, Trash2, Loader2,
} from 'lucide-react';
import {
    badgeAuditoriaSemantico,
    badgeAuditoriaRevision,
    badgeEstatusEnvio,
    badgeConComplementos,
    badgeCorregirRemision,
    badgeHitoAuditoria,
    esPendienteReRevision,
    formatearMoneda,
    etiquetaCostoEnvio,
    etiquetaOrigenGuia,
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
import TarjetaEnvioPedido from '../../Partials/TarjetaEnvioPedido';
import ModalVistaPreviaDocumento, { MiniaturaDocumento } from '../../Partials/ModalVistaPreviaDocumento';
import ModalConfirmarAccion from '../../Partials/ModalConfirmarAccion';
import ModalMotivoRechazo from '../../Partials/ModalMotivoRechazo';
import ModalReportarErrorDatos from '../../Partials/ModalReportarErrorDatos';
import ModalAlertaPedido from '../../Partials/ModalAlertaPedido';
import SeccionGuiaRastreo from '../../Partials/SeccionGuiaRastreo';
import ModalLiberarResguardoAbierto from './ModalLiberarResguardoAbierto';
import SeccionPagosExhibicion from '../../Partials/SeccionPagosExhibicion';
import EncabezadoRevisionPedido from './EncabezadoRevisionPedido';
import ResumenCoberturaPedido from './ResumenCoberturaPedido';
import DatosGeneralesAuditoria from './DatosGeneralesAuditoria';
import BarraAccionesRevision from './BarraAccionesRevision';
import { THEME_INPUT } from '../../../../utils/geliaTheme';

const SECCION = `${THEME_LABEL} mb-3 block`;
const SECCION_WRAP = 'border-b theme-border pb-6 last:border-0';

const Campo = ({ label, value }) => (
    <div>
        <p className="text-[9px] font-black uppercase theme-text-muted m-0">{label}</p>
        <p className="text-sm font-bold theme-text-main m-0 mt-0.5">{value ?? '—'}</p>
    </div>
);

const remisionDe = (pedido) => (pedido?.documentos || []).find((d) => d.tipo === 'remision');

export default function ModalRevisarPedido({ abierto, onClose, pedido: pedidoInicial, bancos = [], catalogos = {} }) {
    const { auth } = usePage().props;
    const permisos = auth?.user?.permissions || [];
    const can = (p) => permisos.includes(p) || auth?.user?.roles?.includes('Super Admin');
    const detalleCajasUi = Boolean(catalogos?.envios_config?.detalle_cajas);
    const [pedido, setPedido] = useState(pedidoInicial);
    const cajasActivas = (pedido?.cajas || []).filter((c) => c.estado_operativo !== 'retirada');
    const totalesEnvio = pedido?.totales_envio || {};
    const desgloseRequerido = Boolean(totalesEnvio.requiere_desglose);
    const fuenteEnvioDetalle = totalesEnvio.fuente === 'detalle_cajas'
        || (detalleCajasUi
            && cajasActivas.length > 0
            && cajasActivas.every((c) => c.costo_envio !== null && c.costo_envio !== '' && c.costo_envio !== undefined));
    const desgloseIncompleto = Boolean(totalesEnvio.incompleto)
        || (desgloseRequerido && !fuenteEnvioDetalle);
    const chipFuenteEnvio = fuenteEnvioDetalle
        ? { label: 'Detalle por caja', ok: true }
        : desgloseRequerido
            ? { label: 'Desglose pendiente', ok: false }
            : { label: 'Legado', ok: false };
    const tienePesajeRespondido = Boolean(pedido?.pesaje_respondido_at)
        || pedido?.estatus_envio === 'pesaje_listo'
        || cajasActivas.length > 0;
    const [procesando, setProcesando] = useState(false);
    const [docPreview, setDocPreview] = useState(null);
    const [confirmacion, setConfirmacion] = useState(null);
    const [motivoAnexoAbierto, setMotivoAnexoAbierto] = useState(false);
    const [errorDatosAbierto, setErrorDatosAbierto] = useState(false);
    const [liberarCapturaAbierto, setLiberarCapturaAbierto] = useState(false);
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'success', titulo: '', mensaje: '' });
    const [folioRemision, setFolioRemision] = useState('');
    const [rechazoPagos, setRechazoPagos] = useState({ abierto: false, ids: [], motivo: '' });
    const [bloqueosPago, setBloqueosPago] = useState([]);
    const [resumenCobertura, setResumenCobertura] = useState(null);

    useEffect(() => {
        if (abierto && pedidoInicial) {
            setPedido(pedidoInicial);
            setFolioRemision(pedidoInicial.folio_remision || '');
            setProcesando(false);
            setConfirmacion(null);
            setMotivoAnexoAbierto(false);
            setErrorDatosAbierto(false);
            setLiberarCapturaAbierto(false);
            setDocPreview(null);
            setResumenCobertura(null);
            setBloqueosPago([]);
        }
    }, [abierto, pedidoInicial?.id]);

    useEffect(() => {
        if (!abierto || !pedidoInicial?.id) return undefined;
        if (pedidoInicial?.estatus?.fase_ciclo !== 'PENDIENTE_AUXILIAR') return undefined;
        const url = route('control_pedidos.auditar.revision_en_curso', pedidoInicial.id);
        const ping = () => axios.post(url).catch(() => {});
        const soltar = () => {
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': token || '',
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
                credentials: 'same-origin',
                keepalive: true,
            }).catch(() => {});
        };
        ping();
        const t = setInterval(ping, 15000);
        window.addEventListener('pagehide', soltar);
        return () => {
            clearInterval(t);
            window.removeEventListener('pagehide', soltar);
            soltar();
        };
    }, [abierto, pedidoInicial?.id, pedidoInicial?.estatus?.fase_ciclo]);

    if (!abierto || !pedido) return null;

    const fase = pedido.estatus?.fase_ciclo;
    const badge = badgeAuditoriaRevision(pedido) || badgeAuditoriaSemantico(fase, pedido.es_resguardo);
    const badgeHito = badgeHitoAuditoria(pedido.hito_auditoria);
    const badgeEnvio = badgeEstatusEnvio(pedido.estatus_envio, { faseCiclo: fase });
    const badgeComp = badgeConComplementos(pedido);
    const badgeRemision = tieneErrorRemision(pedido) ? badgeCorregirRemision() : null;
    const reRevision = esPendienteReRevision(pedido);
    const esPendiente = fase === 'PENDIENTE_AUXILIAR';
    const puedeLiberarResguardo = Boolean(pedido.es_resguardo)
        && (esPendiente || fase === 'EN_CEDIS')
        && can('control_pedidos.liberar_resguardo');
    const requiereCapturaLiberacion = Boolean(pedido.es_resguardo)
        && (pedido.tipo_operacion_envio?.codigo === 'RESGUARDO_ABIERTO'
            || pedido.estatus_envio === 'pendiente_liberacion');
    const esRechazado = fase === 'RECHAZADO_VENDEDORA';
    const remision = remisionDe(pedido);
    const pagoValidado = Boolean(pedido.pago_validado_at);
    const puedeAprobar = esPendiente && pagoValidado && Boolean(remision)
        && can('control_pedidos.auditar.aprobar');
    const muestraAprobar = esPendiente && can('control_pedidos.auditar.aprobar');
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
        if (bloqueosPago.length > 0) {
            setAlerta({
                abierto: true,
                tipo: 'error',
                titulo: 'No se puede validar',
                mensaje: bloqueosPago[0],
            });
            return;
        }
        setProcesando(true);
        router.post(route('control_pedidos.auditar.validar_pago', pedido.id), {}, {
            preserveScroll: true,
            onSuccess: () => recargarPedido('Pago validado correctamente.'),
            onError: (errors) => setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: errors?.message || 'No se pudo validar el pago.' }),
            onFinish: () => setProcesando(false),
        });
    };

    const confirmarRechazoPagos = () => {
        if (!rechazoPagos.ids.length || (rechazoPagos.motivo || '').trim().length < 5) {
            setAlerta({
                abierto: true,
                tipo: 'error',
                titulo: 'Rechazo',
                mensaje: 'Seleccione exhibiciones e indique un motivo (mínimo 5 caracteres).',
            });
            return;
        }
        setProcesando(true);
        router.post(route('control_pedidos.pagos.rechazar', pedido.id), {
            pago_ids: rechazoPagos.ids,
            motivo: rechazoPagos.motivo.trim(),
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setRechazoPagos({ abierto: false, ids: [], motivo: '' });
                setAlerta({ abierto: true, tipo: 'success', titulo: 'Pagos rechazados', mensaje: 'La vendedora debe sustituir los comprobantes.' });
                onClose();
            },
            onError: (errors) => setAlerta({
                abierto: true,
                tipo: 'error',
                titulo: 'Error',
                mensaje: errors?.motivo || errors?.pago_ids || 'No se pudieron rechazar las exhibiciones.',
            }),
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

    const guardarFolioRemision = () => {
        const limpio = folioRemision.trim();
        if (!limpio) {
            setAlerta({ abierto: true, tipo: 'error', titulo: 'Folio', mensaje: 'Indique el folio de pedido (Wizerp).' });
            return;
        }
        if (limpio === String(pedido.folio_remision || '')) return;
        setProcesando(true);
        router.put(route('control_pedidos.auditar.folio_remision.update', pedido.id), {
            folio_remision: limpio,
        }, {
            preserveScroll: true,
            onFinish: () => setProcesando(false),
            onSuccess: () => recargarPedido('Folio de pedido actualizado.'),
            onError: (errors) => setAlerta({
                abierto: true,
                tipo: 'error',
                titulo: 'Folio',
                mensaje: errors?.folio_remision || 'No se pudo actualizar el folio.',
            }),
        });
    };

    const resolverSaf = (inc) => {
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
                    <EncabezadoRevisionPedido
                        pedido={pedido}
                        badge={badge}
                        badgeHito={badgeHito}
                        badgeRemision={badgeRemision}
                        reRevision={reRevision}
                        esRechazado={esRechazado}
                        pagoValidado={pagoValidado}
                        procesando={procesando}
                        can={can}
                        onClose={onClose}
                        onResolverSaf={resolverSaf}
                    />

                    <div className="gelia-modal-body p-5 md:p-6 space-y-6">
                        <section className={SECCION_WRAP}>
                            <p className={SECCION}>1. Cobertura</p>
                            <ResumenCoberturaPedido resumen={resumenCobertura} bloqueos={bloqueosPago} />
                        </section>

                        <section className={SECCION_WRAP}>
                            <p className={SECCION}>2. Exhibiciones y decisión</p>
                            {(pedido.saf_aplicaciones || []).filter((a) => a.estado !== 'liberado').length > 0 && (
                                <div className="mb-4 space-y-2">
                                    <p className="text-xs font-bold theme-text-muted uppercase m-0">Saldos a favor aplicados/reservados</p>
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
                            <SeccionPagosExhibicion
                                pedidoId={pedido.id}
                                bancos={bancos}
                                puedeRegistrar={false}
                                puedeRevisar={false}
                                puedeGenerarSaldo={false}
                                modoAuxiliarSimplificado
                                ocultarResumen
                                rutaResumen="control_pedidos.pagos.resumen_auditoria"
                                totalMercancia={pedido.total_mercancia}
                                costoEnvio={pedido.costo_envio}
                                aplicaSeguro={Boolean(pedido.aplica_seguro)}
                                costoSeguro={pedido.costo_seguro}
                                saldoAFavorAplicado={pedido.saldo_a_favor}
                                onResumenChange={(r) => {
                                    setResumenCobertura(r);
                                    setBloqueosPago(r?.bloqueos || []);
                                }}
                                mensajeBloqueo={esPendiente
                                    ? 'Revise la cobertura. Use Rechazar pago o Validar pago.'
                                    : null}
                            />
                            {esPendiente && (
                                <div className="mt-4 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        onClick={() => setRechazoPagos({ abierto: true, ids: [], motivo: '' })}
                                        disabled={procesando || pagoValidado}
                                        className={`${BTN_SECONDARY} border border-red-500/40 text-red-500 outline-none disabled:opacity-50`}
                                    >
                                        Rechazar pago
                                    </button>
                                    <button
                                        type="button"
                                        onClick={validarPago}
                                        disabled={procesando || pagoValidado || bloqueosPago.length > 0}
                                        title={bloqueosPago[0] || undefined}
                                        className={`${BTN_PRIMARY} flex items-center gap-2 outline-none disabled:opacity-50`}
                                    >
                                        {procesando ? <Loader2 className="w-4 h-4 animate-spin" /> : <CheckCircle2 className="w-4 h-4" />}
                                        {pagoValidado ? 'Pago validado' : procesando ? 'Validando pago…' : 'Validar pago'}
                                    </button>
                                    {bloqueosPago.length > 0 && !pagoValidado && (
                                        <p className="w-full text-xs font-bold text-amber-600 m-0">{bloqueosPago[0]}</p>
                                    )}
                                </div>
                            )}
                        </section>

                        <section className={SECCION_WRAP}>
                            <p className={SECCION}>3. Envío y costos</p>
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
                                <Campo label="Origen de la guía" value={etiquetaOrigenGuia(pedido)} />
                                <Campo label="Reexpedición" value={pedido.zona?.nombre} />
                                <Campo label="Pesaje CEDIS" value={pedido.pesaje_respondido_at ? 'Respondido' : (pedido.estatus_envio === 'pendiente_pesaje' ? 'Pendiente' : '—')} />
                                <Campo label={etiquetaCostoEnvio(pedido.paqueteria)} value={formatearMoneda(pedido.costo_envio)} />
                                <Campo label="Costo del seguro" value={pedido.aplica_seguro ? formatearMoneda(pedido.costo_seguro) : formatearMoneda(0)} />
                                <Campo label="Total mercancía" value={formatearMoneda(pedido.total_mercancia)} />
                            </div>
                            {tienePesajeRespondido && (
                                <div className="mt-4 space-y-3">
                                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                                        Respuesta de cajas y pesos
                                    </p>
                                    {(pedido.pesaje_respondido_por?.name || pedido.pesajeRespondidoPor?.name) && (
                                        <p className="text-xs font-bold theme-text-main m-0">
                                            Respondió: {pedido.pesaje_respondido_por?.name || pedido.pesajeRespondidoPor?.name}
                                            {pedido.pesaje_respondido_at
                                                ? ` · ${formatearFechaNegocio(pedido.pesaje_respondido_at)}`
                                                : ''}
                                        </p>
                                    )}
                                    {cajasActivas.length > 0 && (
                                        <div className="space-y-3">
                                            <div className="flex items-center justify-between gap-2">
                                                <p className="text-[9px] font-black uppercase theme-text-muted m-0">Envíos (pesaje)</p>
                                                {detalleCajasUi && (
                                                    <span className={`text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-full ${chipFuenteEnvio.ok ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : desgloseRequerido ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300' : 'bg-slate-500/15 theme-text-muted'}`}>
                                                        {chipFuenteEnvio.label}
                                                    </span>
                                                )}
                                            </div>
                                            {desgloseIncompleto && desgloseRequerido && (
                                                <p className="text-xs font-bold text-amber-700 dark:text-amber-300 m-0">
                                                    Ventas debe capturar el costo de envío por caja.
                                                </p>
                                            )}
                                            {[...cajasActivas].sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0)).map((c, idx) => (
                                                <TarjetaEnvioPedido
                                                    key={c.uuid_operativo || c.id || idx}
                                                    caja={c}
                                                    indice={idx}
                                                    abiertoInicial={cajasActivas.length === 1}
                                                    modo="lectura"
                                                    incompleto={desgloseRequerido && (c.costo_envio === null || c.costo_envio === '' || c.costo_envio === undefined)}
                                                    documentos={[]}
                                                />
                                            ))}
                                            <div className="grid grid-cols-2 gap-4 pt-1">
                                                <Campo label="Núm. envíos" value={pedido.numero_cajas ?? cajasActivas.length} />
                                                <Campo label="Peso real total (kg)" value={pedido.peso_real_kg != null ? `${pedido.peso_real_kg}` : null} />
                                                <Campo label="Peso volumétrico total (kg)" value={pedido.peso_volumetrico_kg != null ? `${pedido.peso_volumetrico_kg}` : null} />
                                                <Campo label="Peso cobrado guía total (kg)" value={pedido.peso_cobrado_guia_kg != null ? `${pedido.peso_cobrado_guia_kg}` : null} />
                                            </div>
                                        </div>
                                    )}
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
                            <SeccionGuiaRastreo pedido={pedido} onVerPdf={setDocPreview} compact />
                        </section>

                        <section className={SECCION_WRAP}>
                            <p className={SECCION}>4. Datos generales</p>
                            <DatosGeneralesAuditoria pedido={pedido} />
                        </section>

                        {(anexoPendiente || puedeAnexar || (pedido.anexos_envio || []).length > 0) && (
                            <section className={SECCION_WRAP}>
                                <p className={SECCION}>5. Anexo de pago de envío</p>
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
                            <p className={SECCION}>6. Remisión</p>
                            {esPendiente && (
                                <div className="mb-4">
                                    <p className="text-[9px] font-black uppercase theme-text-muted m-0">Folio de pedido (WizeRP)</p>
                                    <div className="flex gap-2 mt-0.5">
                                        <input
                                            type="text"
                                            value={folioRemision}
                                            onChange={(e) => setFolioRemision(e.target.value)}
                                            className={`${THEME_INPUT} w-full py-2 text-sm`}
                                            placeholder="Folio Wizerp..."
                                        />
                                        <button
                                            type="button"
                                            onClick={guardarFolioRemision}
                                            disabled={procesando || folioRemision.trim() === String(pedido.folio_remision || '')}
                                            className={`${BTN_SECONDARY} shrink-0 text-[10px] font-black uppercase px-3 outline-none disabled:opacity-40`}
                                        >
                                            Guardar
                                        </button>
                                    </div>
                                </div>
                            )}
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

                    <BarraAccionesRevision
                        procesando={procesando}
                        puedeLiberarResguardo={puedeLiberarResguardo}
                        esPendiente={esPendiente}
                        muestraAprobar={muestraAprobar}
                        puedeAprobar={puedeAprobar}
                        pagoValidado={pagoValidado}
                        tieneRemision={Boolean(remision)}
                        onClose={onClose}
                        onLiberar={() => {
                            if (requiereCapturaLiberacion) {
                                setLiberarCapturaAbierto(true);
                            } else {
                                setConfirmacion({ accion: 'liberar' });
                            }
                        }}
                        onReportarError={() => setErrorDatosAbierto(true)}
                        onAprobar={() => setConfirmacion({ accion: 'aprobar' })}
                    />
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
            {rechazoPagos.abierto && createPortal(
                <div className={THEME_MODAL_OVERLAY} role="dialog" aria-modal="true">
                    <div className={`${THEME_MODAL_SHELL} max-w-lg w-full p-5 space-y-4`}>
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <p className="text-sm font-black uppercase theme-text-main m-0">Rechazar pago</p>
                                <p className="text-xs theme-text-muted font-bold m-0 mt-1">
                                    El comprobante se conservará; dejará de contar para la cobertura.
                                </p>
                            </div>
                            <button type="button" className="outline-none" onClick={() => setRechazoPagos({ abierto: false, ids: [], motivo: '' })}>
                                <X className="w-5 h-5" />
                            </button>
                        </div>
                        <div className="space-y-2 max-h-48 overflow-y-auto">
                            {(pedido.pagos_exhibicion || pedido.pagosExhibicion || [])
                                .filter((p) => p.activo_para_cobertura !== false)
                                .map((p) => (
                                    <label key={p.id} className="flex items-center gap-2 text-sm font-bold theme-text-main border theme-border rounded-xl px-3 py-2">
                                        <input
                                            type="checkbox"
                                            checked={rechazoPagos.ids.includes(p.id)}
                                            onChange={(e) => {
                                                setRechazoPagos((prev) => ({
                                                    ...prev,
                                                    ids: e.target.checked
                                                        ? [...prev.ids, p.id]
                                                        : prev.ids.filter((id) => id !== p.id),
                                                }));
                                            }}
                                        />
                                        <span>#{p.numero_exhibicion} · {formatearMoneda(p.monto)} · {p.nombre_original || p.banco?.nombre || '—'}</span>
                                    </label>
                                ))}
                        </div>
                        <div>
                            <label className={THEME_LABEL}>Motivo / observación</label>
                            <textarea
                                className={`${THEME_INPUT} w-full mt-1.5 min-h-[5rem] text-sm font-bold`}
                                value={rechazoPagos.motivo}
                                onChange={(e) => setRechazoPagos((prev) => ({ ...prev, motivo: e.target.value }))}
                                placeholder="Explique por qué se rechazan las exhibiciones seleccionadas"
                            />
                        </div>
                        <div className="flex justify-end gap-2">
                            <button type="button" className={BTN_SECONDARY} onClick={() => setRechazoPagos({ abierto: false, ids: [], motivo: '' })}>
                                Cerrar
                            </button>
                            <button
                                type="button"
                                className={`${BTN_PRIMARY} bg-red-600 flex items-center gap-2`}
                                disabled={procesando}
                                onClick={confirmarRechazoPagos}
                            >
                                {procesando && <Loader2 className="w-4 h-4 animate-spin" />}
                                Rechazar exhibiciones seleccionadas
                            </button>
                        </div>
                    </div>
                </div>,
                document.body
            )}
            <ModalAlertaPedido
                abierto={alerta.abierto}
                tipo={alerta.tipo}
                titulo={alerta.titulo}
                mensaje={alerta.mensaje}
                onClose={() => setAlerta({ ...alerta, abierto: false })}
            />
            <ModalLiberarResguardoAbierto
                abierto={liberarCapturaAbierto}
                pedido={pedido}
                bancos={bancos}
                catalogos={catalogos}
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
