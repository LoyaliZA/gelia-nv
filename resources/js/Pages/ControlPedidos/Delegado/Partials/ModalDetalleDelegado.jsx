import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import {
    X, Check, Upload, Eye, Trash2, AlertTriangle, User, Clock,
} from 'lucide-react';
import { THEME_INPUT } from '../../../../utils/geliaTheme';
import {
    badgeEstatusPedido,
    badgeRetrasoGuia,
    badgeResguardoSemantico,
    formatearMoneda,
    etiquetaAlmacen,
    formatearFechaHoraAuditoria,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
    guiaPdfDe,
} from '../../Partials/pedidosBmaStyles';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import DireccionPedidoResumen from '../../Partials/DireccionPedidoResumen';
import { codigoDireccionCliente } from '../../Partials/codigoDireccionCliente';
import ModalVistaPreviaDocumento, { MiniaturaDocumento } from '../../Partials/ModalVistaPreviaDocumento';
import BotonCopiar from '../../Partials/BotonCopiar';

const PROPS_LISTADO = ['pedidos', 'metricas', 'filtros'];
const SECCION = `${THEME_LABEL} mb-3 block`;
const SECCION_WRAP = 'border-b theme-border pb-6 last:border-0';

const CampoConCopia = ({ label, value, copiar = true }) => (
    <div>
        <p className="text-[9px] font-black uppercase theme-text-muted m-0">{label}</p>
        <div className="flex items-start gap-2 mt-0.5 flex-wrap">
            <p className="text-sm font-bold theme-text-main m-0 break-words flex-1 min-w-0">{value || '—'}</p>
            {copiar && value ? <BotonCopiar texto={value} /> : null}
        </div>
    </div>
);

const remisionDe = (pedido) => (pedido?.documentos || []).find((d) => d.tipo === 'remision');
const comprobantesDe = (pedido) => (pedido?.documentos || []).filter((d) => d.tipo === 'comprobante' || !d.tipo);

const modoAccionGuia = (pedido) => {
    if (pedido.es_resguardo) return 'resguardo';
    const fase = pedido.estatus?.fase_ciclo;
    if (fase === 'EN_CEDIS' && pedido.numero_rastreo && !pedido.empacado_at) return 'solo_lectura';
    if (fase === 'PENDIENTE_DE_ENVIO' && pedido.numero_rastreo) return 'correccion';
    if (fase === 'ENVIADO') return 'solo_lectura';
    if ((fase === 'PENDIENTE_DE_GUIA' || fase === 'EN_CEDIS') && !pedido.numero_rastreo) return 'asignar';
    return 'solo_lectura';
};

function CampoAsignarGuia({ pedido, onDone }) {
    const [guia, setGuia] = useState('');
    const [procesando, setProcesando] = useState(false);

    const guardar = (e) => {
        e?.preventDefault?.();
        const valor = guia.trim();
        if (!valor || procesando) return;
        setProcesando(true);
        router.post(route('control_pedidos.delegado.asignar_guia', pedido.id), { numero_rastreo: valor }, {
            preserveScroll: true,
            only: PROPS_LISTADO,
            onSuccess: () => onDone?.(),
            onFinish: () => setProcesando(false),
        });
    };

    return (
        <form onSubmit={guardar} className="flex flex-col sm:flex-row gap-2 items-stretch">
            <input
                type="text"
                value={guia}
                onChange={(e) => setGuia(e.target.value)}
                placeholder="Número de guía"
                className={`${THEME_INPUT} py-3 text-sm font-bold font-mono w-full`}
                disabled={procesando}
            />
            <button type="submit" disabled={procesando || !guia.trim()} className={`${BTN_PRIMARY} flex items-center justify-center gap-1.5 text-[10px] outline-none disabled:opacity-50 shrink-0 px-4`}>
                <Check className="w-3.5 h-3.5" />
                {procesando ? 'Guardando…' : 'Asignar'}
            </button>
        </form>
    );
}

function CampoActualizarGuia({ pedido, onDone }) {
    const [guia, setGuia] = useState(pedido.numero_rastreo || '');
    const [procesando, setProcesando] = useState(false);

    useEffect(() => {
        setGuia(pedido.numero_rastreo || '');
    }, [pedido.id, pedido.numero_rastreo]);

    const guardar = (e) => {
        e?.preventDefault?.();
        const valor = guia.trim();
        if (!valor || procesando || valor === pedido.numero_rastreo) return;
        setProcesando(true);
        router.post(route('control_pedidos.delegado.actualizar_guia', pedido.id), { numero_rastreo: valor }, {
            preserveScroll: true,
            only: PROPS_LISTADO,
            onSuccess: () => onDone?.(),
            onFinish: () => setProcesando(false),
        });
    };

    return (
        <div className="space-y-2">
            <div className="flex items-center gap-2 p-3 rounded-xl border border-amber-500/40 bg-amber-500/10">
                <Clock className="w-4 h-4 text-amber-600 shrink-0" />
                <p className="text-xs font-bold text-amber-700 m-0">
                    Corregir la guía marcará el pedido con retraso y notificará a CEDIS.
                </p>
            </div>
            <form onSubmit={guardar} className="flex flex-col sm:flex-row gap-2 items-stretch">
                <input
                    type="text"
                    value={guia}
                    onChange={(e) => setGuia(e.target.value)}
                    placeholder="Número de guía"
                    className={`${THEME_INPUT} py-3 text-sm font-bold font-mono w-full`}
                    disabled={procesando}
                />
                <button
                    type="submit"
                    disabled={procesando || !guia.trim() || guia.trim() === pedido.numero_rastreo}
                    className={`${BTN_PRIMARY} flex items-center justify-center gap-1.5 text-[10px] outline-none disabled:opacity-50 shrink-0 px-4`}
                >
                    <Check className="w-3.5 h-3.5" />
                    {procesando ? 'Guardando…' : 'Corregir'}
                </button>
            </form>
        </div>
    );
}

function CampoSubirGuiaPdf({ pedido, onVerPdf, soloLectura = false, onDone }) {
    const inputRef = useRef(null);
    const [procesando, setProcesando] = useState(false);
    const guiaPdf = guiaPdfDe(pedido);

    const subir = (e) => {
        const archivo = e.target.files?.[0];
        if (!archivo) return;
        setProcesando(true);
        router.post(route('control_pedidos.delegado.guia_pdf.store', pedido.id), { guia_pdf: archivo }, {
            forceFormData: true,
            preserveScroll: true,
            only: PROPS_LISTADO,
            onSuccess: () => onDone?.(),
            onFinish: () => {
                setProcesando(false);
                e.target.value = '';
            },
        });
    };

    const eliminar = () => {
        if (!guiaPdf || procesando) return;
        setProcesando(true);
        router.delete(route('control_pedidos.delegado.guia_pdf.destroy', pedido.id), {
            preserveScroll: true,
            only: PROPS_LISTADO,
            onSuccess: () => onDone?.(),
            onFinish: () => setProcesando(false),
        });
    };

    return (
        <div className="space-y-2">
            <div className="flex flex-wrap gap-2">
                {!soloLectura && (
                    <button type="button" onClick={() => inputRef.current?.click()} disabled={procesando} className={`${BTN_SECONDARY} flex items-center gap-1.5 text-[10px] outline-none`}>
                        <Upload className="w-3.5 h-3.5" />
                        {guiaPdf ? 'Reemplazar PDF' : 'Subir guía PDF'}
                    </button>
                )}
                {guiaPdf && (
                    <>
                        <button type="button" onClick={() => onVerPdf(guiaPdf)} className={`${BTN_SECONDARY} flex items-center gap-1.5 text-[10px] outline-none`}>
                            <Eye className="w-3.5 h-3.5" /> Ver PDF
                        </button>
                        {!soloLectura && (
                            <button type="button" onClick={eliminar} disabled={procesando} className={`${BTN_SECONDARY} flex items-center gap-1.5 text-[10px] outline-none text-red-500`}>
                                <Trash2 className="w-3.5 h-3.5" />
                            </button>
                        )}
                    </>
                )}
            </div>
            {guiaPdf && <p className="text-[9px] font-bold theme-text-muted m-0 truncate">{guiaPdf.nombre_original}</p>}
            {!soloLectura && <input ref={inputRef} type="file" accept=".pdf,application/pdf" className="hidden" onChange={subir} />}
        </div>
    );
}

export default function ModalDetalleDelegado({
    abierto, onClose, pedido: pedidoInicial, onReportarError,
}) {
    const [pedido, setPedido] = useState(pedidoInicial);
    const [docPreview, setDocPreview] = useState(null);

    useEffect(() => {
        if (abierto && pedidoInicial) {
            setPedido(pedidoInicial);
            setDocPreview(null);
        }
    }, [abierto, pedidoInicial?.id, pedidoInicial?.numero_rastreo, pedidoInicial?.guia_subida_at]);

    if (!abierto || !pedido) return null;

    const fase = pedido.estatus?.fase_ciclo;
    const badgeEstatus = badgeEstatusPedido(pedido.estatus, { esResguardo: pedido.es_resguardo });
    const badgeRetraso = pedido.guia_retraso ? badgeRetrasoGuia() : null;
    const badgeResguardo = pedido.es_resguardo ? badgeResguardoSemantico() : null;
    const remision = remisionDe(pedido);
    const comprobantes = comprobantesDe(pedido);
    const dir = pedido.direccion_vigente || pedido.direccionVigente;
    const modo = modoAccionGuia(pedido);
    const puedeReportar = ['PENDIENTE_DE_GUIA', 'PENDIENTE_DE_ENVIO', 'EN_CEDIS'].includes(fase) && !pedido.es_resguardo;

    const recargarPedido = () => {
        router.reload({
            only: PROPS_LISTADO,
            preserveScroll: true,
            onSuccess: (page) => {
                const actualizado = page.props.pedidos?.data?.find((p) => p.id === pedido.id);
                if (actualizado) setPedido(actualizado);
                else onClose();
            },
        });
    };

    const domicilioTexto = dir
        ? [dir.calle, dir.numero_exterior, dir.colonia, dir.ciudad || dir.municipio, dir.estado, dir.codigo_postal]
            .filter(Boolean).join(', ')
        : pedido.domicilio_entrega;

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
                            <p className="text-[10px] font-black uppercase theme-text-muted m-0 mb-1">Detalle guía</p>
                            <EncabezadoFolioPedido pedido={pedido} size="lg" />
                            {pedido.vendedor?.name && (
                                <p className="text-xs font-bold theme-text-muted mt-2 m-0 flex items-center gap-1">
                                    <User className="w-3.5 h-3.5" /> {pedido.vendedor.name}
                                </p>
                            )}
                            <div className="flex flex-wrap gap-2 mt-2">
                                <span className={badgeEstatus.className} style={badgeEstatus.style}>{badgeEstatus.label}</span>
                                {badgeRetraso && <span className={badgeRetraso.className} style={badgeRetraso.style}>{badgeRetraso.label}</span>}
                                {badgeResguardo && <span className={badgeResguardo.className} style={badgeResguardo.style}>{badgeResguardo.label}</span>}
                            </div>
                        </div>
                        <button type="button" onClick={onClose} className="p-2 rounded-full theme-text-muted outline-none shrink-0" aria-label="Cerrar">
                            <X className="w-5 h-5" />
                        </button>
                    </div>

                    <div className="gelia-modal-body p-5 md:p-6 space-y-6 overflow-y-auto">
                        <section className={SECCION_WRAP}>
                            <p className={SECCION}>Identificación</p>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <CampoConCopia label="ID pedido" value={pedido.id} />
                                <CampoConCopia label="Folio" value={pedido.folio} />
                                <CampoConCopia label="Folio remisión" value={pedido.folio_remision} />
                                <CampoConCopia label="N° Cliente" value={pedido.cliente?.numero_cliente} />
                                <CampoConCopia label="Cliente" value={pedido.cliente?.nombre} />
                                <CampoConCopia label="Almacén" value={etiquetaAlmacen(pedido.almacen)} copiar={false} />
                            </div>
                        </section>

                        <section className={SECCION_WRAP}>
                            <p className={SECCION}>Envío</p>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                                <CampoConCopia label="Paquetería" value={pedido.paqueteria?.nombre} />
                                <CampoConCopia label="Tipo de guía" value={pedido.tipo_guia?.nombre || pedido.tipoGuia?.nombre} />
                                <CampoConCopia label="Código postal" value={pedido.codigo_postal || dir?.codigo_postal} />
                                <CampoConCopia label="Destinatario" value={dir?.nombre_destinatario || pedido.envia_otra_persona} />
                                <CampoConCopia label="Teléfono" value={dir?.telefono_destinatario} />
                                <CampoConCopia label="Referencias" value={dir?.referencias} />
                            </div>
                            <DireccionPedidoResumen
                                direccion={dir}
                                domicilioLegacy={pedido.domicilio_entrega}
                                codigoPostal={pedido.codigo_postal}
                                codigoDireccion={codigoDireccionCliente(pedido.cliente?.numero_cliente, dir?.numero_direccion)}
                            />
                            {domicilioTexto && (
                                <div className="mt-3">
                                    <BotonCopiar texto={domicilioTexto} etiqueta="Copiar domicilio completo" />
                                </div>
                            )}
                        </section>

                        <section className={SECCION_WRAP}>
                            <p className={SECCION}>Costos</p>
                            <div className="p-4 rounded-xl border theme-border theme-element space-y-2 text-sm">
                                <div className="flex justify-between theme-text-muted font-bold"><span>Mercancía</span><span>{formatearMoneda(pedido.total_mercancia)}</span></div>
                                <div className="flex justify-between theme-text-muted font-bold"><span>Envío</span><span>{formatearMoneda(pedido.costo_envio)}</span></div>
                                <div className="flex justify-between font-black pt-2 border-t theme-border" style={{ color: 'var(--color-primario)' }}>
                                    <span>Total</span><span>{formatearMoneda(pedido.total_a_cobrar)}</span>
                                </div>
                            </div>
                        </section>

                        {(comprobantes.length > 0 || remision) && (
                            <section className={SECCION_WRAP}>
                                <p className={SECCION}>Documentos</p>
                                {comprobantes.length > 0 && (
                                    <div className="flex flex-wrap gap-2 mb-3">
                                        {comprobantes.map((doc) => (
                                            <MiniaturaDocumento key={doc.id} documento={doc} onVer={setDocPreview} />
                                        ))}
                                    </div>
                                )}
                                {remision && (
                                    <button type="button" onClick={() => setDocPreview(remision)} className={`${BTN_SECONDARY} text-xs outline-none`}>
                                        Ver remisión
                                    </button>
                                )}
                            </section>
                        )}

                        <section className={SECCION_WRAP}>
                            <p className={SECCION}>Guía de rastreo</p>
                            {pedido.numero_rastreo && (
                                <div className="mb-4 flex items-center gap-2 flex-wrap">
                                    <p className="text-lg font-black font-mono theme-text-main m-0 break-all">{pedido.numero_rastreo}</p>
                                    <BotonCopiar texto={pedido.numero_rastreo} />
                                </div>
                            )}
                            {pedido.guia_corregida_at && (
                                <p className="text-[10px] font-bold theme-text-muted m-0 mb-3 font-mono">
                                    Corregida: {formatearFechaHoraAuditoria(pedido.guia_corregida_at)}
                                    {(pedido.guia_corregida_por?.name || pedido.guiaCorregidaPor?.name)
                                        ? ` · ${pedido.guia_corregida_por?.name || pedido.guiaCorregidaPor?.name}`
                                        : ''}
                                </p>
                            )}

                            {modo === 'resguardo' && (
                                <p className="text-sm font-bold text-blue-600 m-0">En resguardo — la guía se habilita al liberar.</p>
                            )}
                            {modo === 'solo_lectura' && !pedido.numero_rastreo && (
                                <p className="text-sm theme-text-muted font-bold m-0">Sin guía capturada.</p>
                            )}
                            {modo === 'asignar' && <CampoAsignarGuia pedido={pedido} onDone={recargarPedido} />}
                            {modo === 'correccion' && <CampoActualizarGuia pedido={pedido} onDone={recargarPedido} />}
                            {(modo === 'asignar' || modo === 'correccion' || pedido.numero_rastreo) && (
                                <div className="mt-4">
                                    <CampoSubirGuiaPdf
                                        pedido={pedido}
                                        onVerPdf={setDocPreview}
                                        soloLectura={modo === 'solo_lectura' || modo === 'resguardo'}
                                        onDone={recargarPedido}
                                    />
                                </div>
                            )}
                        </section>
                    </div>

                    <div className="gelia-modal-footer flex flex-wrap gap-3 p-5 md:p-6 border-t theme-border shrink-0">
                        <button type="button" onClick={onClose} className={`${BTN_SECONDARY} outline-none`}>Cerrar</button>
                        {puedeReportar && (
                            <button
                                type="button"
                                onClick={() => { onClose(); onReportarError?.(pedido); }}
                                className={`${BTN_SECONDARY} border border-orange-500/40 text-orange-600 outline-none ml-auto`}
                            >
                                <AlertTriangle className="w-4 h-4 inline mr-1" /> Reportar error de datos
                            </button>
                        )}
                    </div>
                </div>
            </div>
            <ModalVistaPreviaDocumento abierto={Boolean(docPreview)} documento={docPreview} onClose={() => setDocPreview(null)} />
        </>,
        document.body
    );
}
