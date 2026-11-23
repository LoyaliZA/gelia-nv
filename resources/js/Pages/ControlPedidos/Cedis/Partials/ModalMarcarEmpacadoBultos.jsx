import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { X, PackageCheck, ImagePlus, Camera, Trash2, Plus, FileText, Link2 } from 'lucide-react';
import IntegracionFisicaConjunto from '../../Municipal/IntegracionFisicaConjunto';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    BTN_PRIMARY,
    BTN_SECONDARY,
} from '../../Partials/pedidosBmaStyles';
import {
    complementosDe,
    etiquetaSucursal,
    pedidosRequierenBultosEmpaque,
} from '../../Partials/pedidosBmaStyles';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import ModalConfirmarAccion from '../../Partials/ModalConfirmarAccion';
import { esDispositivoCampo } from '../../../Activos/Partials/useDispositivoCampo';
import usePedidoDialog from '../../Partials/usePedidoDialog';

// Mismo alcance que CustodiaDocumentalTienda del servidor.
export const empaqueSoloBolsa = pedido => pedido?.solicitud_preparacion?.modalidad_entrega === 'tienda'
    && ['transferencia', 'credito'].includes(pedido?.solicitud_preparacion?.cobertura_tienda?.tipo);

const filaVacia = () => ({
    foto_bulto: null,
    foto_ticket: null,
    usar_remision_pdf: false,
    preview_bulto: null,
    preview_ticket: null,
});

function CapturaFoto({ etiqueta, preview, onSeleccionar, inputRef, camaraRef, disabled }) {
    const [error, setError] = useState('');
    const seleccionar = archivo => {
        if (!archivo || disabled) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(archivo.type) || archivo.size > 5 * 1024 * 1024) {
            setError('Usa una foto JPG, PNG o WEBP de hasta 5 MB.');
            return;
        }
        setError('');
        onSeleccionar(archivo);
    };
    return (
        <div className="gelia-empaque-captura space-y-2">
            <p className="text-xs font-semibold theme-text-muted m-0">{etiqueta}</p>
            <div className="flex flex-col gap-2">
                <button
                    type="button"
                    disabled={disabled}
                    aria-label={`${preview ? 'Volver a tomar foto' : 'Tomar foto'}: ${etiqueta}`}
                    onClick={() => camaraRef.current?.click()}
                    className="gelia-empaque-camara"
                >
                    {preview ? <img src={preview} alt={etiqueta} /> : <Camera className="w-8 h-8" aria-hidden="true" />}
                    <span><Camera className="w-4 h-4" aria-hidden="true" />{preview ? 'Volver a tomar foto' : 'Tomar foto'}</span>
                </button>
                <button
                    type="button"
                    disabled={disabled}
                    onClick={() => inputRef.current?.click()}
                    className={`${BTN_SECONDARY} min-h-[44px] w-full inline-flex items-center justify-center gap-2 text-xs`}
                >
                    <ImagePlus className="w-4 h-4 shrink-0" aria-hidden="true" /> {preview ? 'Cambiar desde galería' : 'Elegir de galería'}
                </button>
                <input
                    ref={camaraRef}
                    type="file"
                    accept="image/*"
                    capture="environment"
                    aria-label={`Cámara: ${etiqueta}`}
                    disabled={disabled}
                    className="hidden"
                    onChange={(e) => {
                        seleccionar(e.target.files?.[0]);
                        e.target.value = '';
                    }}
                />
                <input
                    ref={inputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/jpg"
                    aria-label={`Galería: ${etiqueta}`}
                    disabled={disabled}
                    className="hidden"
                    onChange={(e) => {
                        seleccionar(e.target.files?.[0]);
                        e.target.value = '';
                    }}
                />
            </div>
            {error && <p role="alert" className="text-xs theme-text-peligro m-0">{error}</p>}
        </div>
    );
}

export const remisionPrincipalPdf = (pedido) => (pedido?.documentos || []).find((doc) => doc.tipo === 'remision' && doc.url) || null;

/** Remisión del pedido principal frente a las remisiones anexadas: se muestran por separado. */
function RemisionesDelPedido({ pedido }) {
    const anexadas = pedido?.resguardo_envio?.vinculos || [];
    const principal = pedido.numero_remision || pedido.folio_remision || pedido.folio;
    return (
        <div className="rounded-xl border theme-border p-3 grid gap-2 text-sm theme-text-main">
            <p className="m-0"><span className="text-xs font-semibold theme-text-muted">Remisión principal</span><br /><strong>{principal}</strong></p>
            {anexadas.length > 0 && (
                <div>
                    <p className="text-xs font-semibold theme-text-muted m-0 mb-1">Remisiones anexadas ({anexadas.length})</p>
                    <ul className="m-0 p-0 list-none grid gap-1">
                        {anexadas.map((item) => (
                            <li key={item.origen_id} className="flex flex-wrap items-center gap-2">
                                <Link2 className="w-4 h-4 shrink-0" aria-hidden="true" />
                                <span>{item.remision || item.folio}</span>
                                <span className="text-xs rounded-full border theme-border px-2 py-0.5">Anexada</span>
                                <span className={`text-xs ${item.integrado ? 'theme-text-exito' : 'theme-text-aviso'}`}>{item.integrado ? 'Integrada' : 'Falta confirmar'}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}

function SeccionPedidoBultos({ pedido, bultos, onChange, disabled, mostrarPedido }) {
    const soloBolsa = empaqueSoloBolsa(pedido);
    const actualizarFila = (idx, patch) => {
        onChange(bultos.map((fila, i) => (i === idx ? { ...fila, ...patch } : fila)));
    };

    const quitarFila = (idx) => {
        const fila = bultos[idx];
        if (fila?.preview_bulto) URL.revokeObjectURL(fila.preview_bulto);
        if (fila?.preview_ticket) URL.revokeObjectURL(fila.preview_ticket);
        onChange(bultos.filter((_, i) => i !== idx));
    };

    const agregarFila = () => {
        if (bultos.length >= 50) return;
        onChange([...bultos, filaVacia()]);
    };

    const sucursal = pedido.sucursal_destino || pedido.sucursalDestino;

    return (
        <section className="space-y-3">
            {mostrarPedido && <div className="rounded-xl border theme-border p-3 theme-element">
                <EncabezadoFolioPedido pedido={pedido} size="sm" />
                {sucursal && (
                    <p className="text-[10px] font-bold theme-text-muted m-0 mt-1">
                        Destino: {etiquetaSucursal(sucursal)}
                    </p>
                )}
            </div>}

            <RemisionesDelPedido pedido={pedido} />

            {bultos.map((fila, idx) => (
                <BultoFila
                    key={`${pedido.id}-bulto-${idx}`}
                    numero={idx + 1}
                    soloBolsa={soloBolsa}
                    remisionPdf={remisionPrincipalPdf(pedido)}
                    disabled={disabled}
                    fila={fila}
                    puedeQuitar={bultos.length > 1}
                    onQuitar={() => quitarFila(idx)}
                    onFotoBulto={(file) => {
                        if (fila.preview_bulto) URL.revokeObjectURL(fila.preview_bulto);
                        actualizarFila(idx, {
                            foto_bulto: file,
                            preview_bulto: file ? URL.createObjectURL(file) : null,
                        });
                    }}
                    onUsarPdf={(usar) => {
                        if (fila.preview_ticket) URL.revokeObjectURL(fila.preview_ticket);
                        actualizarFila(idx, { usar_remision_pdf: usar, foto_ticket: null, preview_ticket: null });
                    }}
                    onFotoTicket={(file) => {
                        if (fila.preview_ticket) URL.revokeObjectURL(fila.preview_ticket);
                        actualizarFila(idx, {
                            usar_remision_pdf: false,
                            foto_ticket: file,
                            preview_ticket: file ? URL.createObjectURL(file) : null,
                        });
                    }}
                />
            ))}

            <button
                type="button"
                onClick={agregarFila}
                disabled={disabled || bultos.length >= 50}
                className={`${BTN_SECONDARY} w-full min-h-[44px] inline-flex items-center justify-center gap-2 text-xs outline-none`}
            >
                <Plus className="w-4 h-4" /> {soloBolsa ? 'Agregar otra bolsa' : 'Agregar otra foto del pedido'}
            </button>
        </section>
    );
}

function BultoFila({ numero, fila, puedeQuitar, onQuitar, onFotoBulto, onFotoTicket, onUsarPdf, soloBolsa, remisionPdf = null, disabled }) {
    const inputBultoRef = useRef(null);
    const camaraBultoRef = useRef(null);
    const inputTicketRef = useRef(null);
    const camaraTicketRef = useRef(null);

    return (
        <div className="rounded-2xl border theme-border p-3 space-y-3 theme-element">
            <div className="flex items-center justify-between gap-2">
                <p className="text-sm font-semibold theme-text-main m-0">
                    {soloBolsa ? 'Bolsa' : 'Pedido'} {numero}
                </p>
                {puedeQuitar && (
                    <button
                        type="button"
                        disabled={disabled}
                        onClick={onQuitar}
                        className="p-2 min-h-[44px] min-w-[44px] rounded-lg text-red-500 hover:bg-red-500/10 outline-none inline-flex items-center justify-center"
                        aria-label={`Quitar ${soloBolsa ? 'bolsa' : 'pedido'} ${numero}`}
                    >
                        <Trash2 className="w-4 h-4" />
                    </button>
                )}
            </div>
            <div className={soloBolsa ? '' : 'grid grid-cols-1 sm:grid-cols-2 gap-3'}>
                <CapturaFoto
                    etiqueta={soloBolsa ? `Foto de la bolsa ${numero}` : 'Foto del pedido *'}
                    disabled={disabled}
                    preview={fila.preview_bulto}
                    onSeleccionar={onFotoBulto}
                    inputRef={inputBultoRef}
                    camaraRef={camaraBultoRef}
                />
                {!soloBolsa && (fila.usar_remision_pdf && remisionPdf ? (
                    <div className="gelia-empaque-captura space-y-2">
                        <p className="text-xs font-semibold theme-text-muted m-0">Remisión del pedido principal *</p>
                        <div className="rounded-xl border theme-border p-3 flex items-center gap-2 text-sm theme-text-main">
                            <FileText className="w-5 h-5 shrink-0" aria-hidden="true" />
                            <span className="min-w-0 break-words">{remisionPdf.nombre_original || 'Remisión en PDF'}</span>
                        </div>
                        <button type="button" disabled={disabled} onClick={() => onUsarPdf(false)} className={`${BTN_SECONDARY} min-h-[44px] w-full text-xs`}>Tomar foto de la remisión</button>
                    </div>
                ) : (
                    <div className="space-y-2">
                        <CapturaFoto
                            etiqueta="Foto de la remisión del pedido *"
                            disabled={disabled}
                            preview={fila.preview_ticket}
                            onSeleccionar={onFotoTicket}
                            inputRef={inputTicketRef}
                            camaraRef={camaraTicketRef}
                        />
                        {remisionPdf && <button type="button" disabled={disabled} onClick={() => onUsarPdf(true)} className={`${BTN_SECONDARY} min-h-[44px] w-full inline-flex items-center justify-center gap-2 text-xs`}><FileText className="w-4 h-4" aria-hidden="true" />Usar la remisión en PDF del pedido</button>}
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function ModalMarcarEmpacadoBultos({ abierto, onClose, pedido, onExito }) {
    const [bultosPorPedido, setBultosPorPedido] = useState({});
    const [procesando, setProcesando] = useState(false);
    const [error, setError] = useState('');
    const [esMovil, setEsMovil] = useState(false);
    const [confirmarCierre, setConfirmarCierre] = useState(false);
    // La confirmación de una remisión anexada responde con el estado vigente: se refleja aquí sin esperar a recargar el listado.
    const [integraciones, setIntegraciones] = useState({});
    const bultosRef = useRef(bultosPorPedido);
    bultosRef.current = bultosPorPedido;
    const liberarPreviews = () => Object.values(bultosRef.current).forEach(filas => filas?.forEach(fila => {
        if (fila.preview_bulto) URL.revokeObjectURL(fila.preview_bulto);
        if (fila.preview_ticket) URL.revokeObjectURL(fila.preview_ticket);
    }));
    const solicitarCierre = () => { if (!procesando) setConfirmarCierre(true); };
    const dialog = usePedidoDialog({ abierto: abierto && Boolean(pedido), onClose: solicitarCierre, bloqueado: procesando || confirmarCierre });

    const pedidosObjetivo = (pedido ? pedidosRequierenBultosEmpaque(pedido) : []).map((p) => (integraciones[p.id] ? { ...p, resguardo_envio: integraciones[p.id] } : p));

    useEffect(() => {
        if (!abierto || !pedido) return;

        liberarPreviews();
        const inicial = {};
        pedidosRequierenBultosEmpaque(pedido).forEach((p) => {
            inicial[p.id] = [filaVacia()];
        });
        setBultosPorPedido(inicial);
        setIntegraciones({});
        setError('');
        setProcesando(false);
        setConfirmarCierre(false);
        setEsMovil(esDispositivoCampo());
    }, [abierto, pedido?.id]);

    useEffect(() => () => liberarPreviews(), []);

    if (!abierto || !pedido) return null;

    const vinculosPendientes = pedidosObjetivo.flatMap((p) => (p.resguardo_envio?.vinculos || []).filter((item) => !item.integrado));
    const validar = () => {
        if (vinculosPendientes.length > 0) {
            return `Antes de empacar, confirme la integración de ${vinculosPendientes.length === 1 ? 'la remisión anexada pendiente' : `las ${vinculosPendientes.length} remisiones anexadas pendientes`}.`;
        }
        for (const p of pedidosObjetivo) {
            const filas = bultosPorPedido[p.id] || [];
            if (filas.length < 1) {
                return `Registre al menos una foto del pedido ${p.folio}.`;
            }
            for (let i = 0; i < filas.length; i += 1) {
                if (!filas[i].foto_bulto || (!empaqueSoloBolsa(p) && !filas[i].foto_ticket && !(filas[i].usar_remision_pdf && remisionPrincipalPdf(p)))) {
                    return empaqueSoloBolsa(p)
                        ? `Toma la foto de la bolsa ${i + 1} del pedido ${p.folio_remision || p.folio}.`
                        : `El pedido ${i + 1} de ${p.folio} requiere foto del pedido y de su remisión (o la remisión en PDF).`;
                }
            }
        }
        return '';
    };

    const enviar = (e) => {
        e.preventDefault();
        if (procesando) return;
        const msg = validar();
        if (msg) {
            setError(msg);
            return;
        }

        const formData = new FormData();
        pedidosObjetivo.forEach((p) => {
            (bultosPorPedido[p.id] || []).forEach((fila, idx) => {
                formData.append(`bultos_por_pedido[${p.id}][${idx}][foto_bulto]`, fila.foto_bulto);
                if (!empaqueSoloBolsa(p) && fila.foto_ticket) formData.append(`bultos_por_pedido[${p.id}][${idx}][foto_ticket]`, fila.foto_ticket);
                if (!empaqueSoloBolsa(p) && fila.usar_remision_pdf && !fila.foto_ticket) formData.append(`bultos_por_pedido[${p.id}][${idx}][usar_remision_pdf]`, '1');
            });
        });

        setProcesando(true);
        setError('');
        router.post(route('control_pedidos.cedis.marcar_empacado', pedido.id), formData, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                onExito?.();
                onClose();
            },
            onError: (errors) => {
                const first = errors.bultos_por_pedido
                    || errors['bultos_por_pedido.0']
                    || Object.values(errors)[0];
                setError(typeof first === 'string' ? first : 'No se pudo registrar el empaque.');
            },
            onFinish: () => setProcesando(false),
        });
    };

    const comps = complementosDe(pedido);
    const soloBolsas = pedidosObjetivo.every(empaqueSoloBolsa);
    const titulo = soloBolsas ? 'Fotografiar bolsas' : comps.length ? 'Empacar grupo' : 'Marcar empacado';

    return createPortal(
        <>
            <div className={`${THEME_MODAL_OVERLAY} gelia-empaque-overlay`} onClick={esMovil ? undefined : solicitarCierre}>
                <div
                    {...dialog}
                    aria-labelledby="empaque-bolsas-titulo"
                    className={`${THEME_MODAL_SHELL} gelia-empaque-dialog flex flex-col`}
                    onClick={(e) => e.stopPropagation()}
                >
                    <div className="gelia-empaque-cabecera border-b theme-border flex justify-between items-start gap-3 shrink-0">
                        <div className="min-w-0">
                            <h2 id="empaque-bolsas-titulo" className="text-lg font-semibold theme-text-main m-0 flex items-center gap-2">
                                <PackageCheck className="w-5 h-5 shrink-0 text-[var(--color-primario)]" aria-hidden="true" />
                                {titulo}
                            </h2>
                            <p className="text-sm theme-text-main font-semibold mt-1 sm:mt-2 mb-0 break-words">Pedido {pedido.folio_remision || pedido.folio}{pedido.cliente?.nombre ? ` · ${pedido.cliente.nombre}` : ''}</p>
                            {(pedido.sucursal_destino || pedido.sucursalDestino) && <p className="text-xs theme-text-muted mt-1 mb-0">Destino: {etiquetaSucursal(pedido.sucursal_destino || pedido.sucursalDestino)}</p>}
                        </div>
                        <button
                            type="button"
                            onClick={solicitarCierre}
                            disabled={procesando}
                            className="p-2 min-h-[44px] min-w-[44px] rounded-full theme-text-muted outline-none inline-flex items-center justify-center"
                            aria-label="Cerrar"
                        >
                            <X className="w-5 h-5" />
                        </button>
                    </div>

                    <form onSubmit={enviar} className="flex flex-col min-h-0 flex-1">
                        <div className="gelia-empaque-cuerpo space-y-4 overflow-y-auto flex-1 min-h-0">
                            {pedidosObjetivo.map((p) => (
                                <IntegracionFisicaConjunto key={`integra-${p.id}`} pedido={p} mecanismos={['resguardo_envio']} onActualizado={(nuevo) => { if (nuevo?.resguardo_envio) setIntegraciones((prev) => ({ ...prev, [p.id]: nuevo.resguardo_envio })); router.reload({ preserveScroll: true }); }} />
                            ))}
                            {pedidosObjetivo.map((p) => (
                                <SeccionPedidoBultos
                                    key={p.id}
                                    pedido={p}
                                    disabled={procesando}
                                    mostrarPedido={pedidosObjetivo.length > 1}
                                    bultos={bultosPorPedido[p.id] || [filaVacia()]}
                                    onChange={(filas) => setBultosPorPedido((prev) => ({ ...prev, [p.id]: filas }))}
                                />
                            ))}
                            <p className="hidden sm:block text-xs theme-text-muted m-0">JPG, PNG o WEBP · Hasta 5 MB por foto.</p>
                            {error && <p role="alert" className="text-sm theme-text-peligro font-semibold m-0">{error}</p>}
                        </div>

                        <div className="gelia-empaque-pie border-t theme-border flex gap-3 shrink-0">
                            <button type="button" disabled={procesando} onClick={solicitarCierre} className={`${BTN_SECONDARY} min-h-[44px]`}>
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                disabled={procesando || vinculosPendientes.length > 0}
                                className={`${BTN_PRIMARY} disabled:opacity-50 min-h-[44px] flex-1`}
                            >
                                {procesando ? 'Guardando…' : comps.length ? 'Empacar grupo' : 'Marcar empacado'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <ModalConfirmarAccion
                abierto={confirmarCierre}
                titulo="Descartar registro"
                mensaje={soloBolsas ? '¿Cerrar sin guardar las fotos de las bolsas?' : '¿Cerrar sin guardar las fotos del pedido?'}
                etiquetaConfirmar="Cerrar"
                variante="danger"
                onClose={() => setConfirmarCierre(false)}
                onConfirm={() => {
                    setConfirmarCierre(false);
                    onClose();
                }}
            />
        </>,
        document.body
    );
}
