import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { X, Scale, Plus, Trash2, FileText } from 'lucide-react';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
    formatearFechaNegocio,
    LABELS_MOTIVO_REPESAJE,
} from '../../Partials/pedidosBmaStyles';
import { THEME_INPUT, THEME_SELECT } from '../../../../utils/geliaTheme';
import EncabezadoFolioPedido from '../../Partials/EncabezadoFolioPedido';
import AvisoOperativoPedido from '../../Partials/AvisoOperativoPedido';
import ModalAlertaPedido from '../../Partials/ModalAlertaPedido';

const SECCION = `${THEME_LABEL} mb-2 block`;

const lineaVacia = () => ({ catalogo_tipo_caja_id: '', cantidad: '1' });

export default function ModalResponderPesaje({
    abierto, onClose, pedido, tiposCaja = [],
}) {
    const [peso, setPeso] = useState('');
    const [lineas, setLineas] = useState([lineaVacia()]);
    const [procesando, setProcesando] = useState(false);
    const [alerta, setAlerta] = useState({ abierto: false, tipo: 'error', titulo: '', mensaje: '' });

    useEffect(() => {
        if (abierto && pedido) {
            setPeso('');
            setLineas([lineaVacia()]);
            setProcesando(false);
            setAlerta({ abierto: false, tipo: 'error', titulo: '', mensaje: '' });
        }
    }, [abierto, pedido?.id]);

    if (!abierto || !pedido) return null;

    const pdfPedido = (pedido.documentos || []).find((d) => d.tipo === 'pdf_pedido');

    const actualizarLinea = (idx, campo, valor) => {
        setLineas((prev) => prev.map((l, i) => (i === idx ? { ...l, [campo]: valor } : l)));
    };

    const agregarLinea = () => setLineas((prev) => [...prev, lineaVacia()]);

    const quitarLinea = (idx) => {
        setLineas((prev) => (prev.length <= 1 ? prev : prev.filter((_, i) => i !== idx)));
    };

    const confirmar = () => {
        const pesoNum = Number(peso);
        if (peso === '' || Number.isNaN(pesoNum) || pesoNum < 0) {
            setAlerta({ abierto: true, tipo: 'error', titulo: 'Peso inválido', mensaje: 'Indique el peso real en kg.' });
            return;
        }
        const cajas = lineas
            .map((l) => ({
                catalogo_tipo_caja_id: Number(l.catalogo_tipo_caja_id),
                cantidad: Number(l.cantidad),
            }))
            .filter((l) => l.catalogo_tipo_caja_id > 0 && l.cantidad > 0);

        if (cajas.length === 0) {
            setAlerta({ abierto: true, tipo: 'error', titulo: 'Cajas', mensaje: 'Indique al menos una caja con cantidad.' });
            return;
        }

        setProcesando(true);
        router.post(route('control_pedidos.cedis.responder_pesaje', pedido.id), {
            peso_real_kg: pesoNum,
            cajas,
        }, {
            preserveScroll: true,
            onFinish: () => setProcesando(false),
            onSuccess: (page) => {
                if (page?.props?.flash?.error) {
                    setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: page.props.flash.error });
                    return;
                }
                onClose();
            },
            onError: (errs) => {
                const msg = Object.values(errs || {})[0];
                setAlerta({ abierto: true, tipo: 'error', titulo: 'Error', mensaje: typeof msg === 'string' ? msg : 'No se pudo guardar el pesaje.' });
            },
        });
    };

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
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 mb-1">Responder pesaje</p>
                            <EncabezadoFolioPedido pedido={pedido} />
                            <p className="text-xs theme-text-muted m-0 mt-1">
                                {pedido.cliente?.nombre || '—'} · {formatearFechaNegocio(pedido.fecha)}
                            </p>
                        </div>
                        <button type="button" onClick={onClose} className="p-2 rounded-xl theme-element border theme-border outline-none shrink-0" aria-label="Cerrar">
                            <X className="w-4 h-4" />
                        </button>
                    </div>

                    <div className="gelia-modal-body p-5 md:p-6 space-y-5">
                        {pedido.motivo_repesaje && (
                            <AvisoOperativoPedido label="Re-pesaje" tono="warning" icon={Scale}>
                                Motivo: {LABELS_MOTIVO_REPESAJE[pedido.motivo_repesaje] || pedido.motivo_repesaje}
                            </AvisoOperativoPedido>
                        )}

                        <div>
                            <div className="flex items-center justify-between gap-2 mb-2">
                                <label className={`${SECCION} m-0`}>PDF del pedido</label>
                                {pdfPedido?.url && (
                                    <a
                                        href={pdfPedido.url}
                                        download={pdfPedido.nombre_original || undefined}
                                        className={`${BTN_SECONDARY} inline-flex items-center gap-2 text-xs`}
                                    >
                                        <FileText className="w-4 h-4" /> Descargar
                                    </a>
                                )}
                            </div>
                            {pdfPedido?.url ? (
                                <iframe
                                    src={pdfPedido.url}
                                    title={pdfPedido.nombre_original || 'PDF del pedido'}
                                    className="w-full border theme-border rounded-xl bg-black/5"
                                    style={{ height: 'min(55vh, 520px)' }}
                                />
                            ) : (
                                <p className="text-sm theme-text-muted m-0">Sin PDF adjunto</p>
                            )}
                        </div>

                        <div>
                            <label className={SECCION}>Peso real (kg)</label>
                            <input
                                type="number"
                                step="0.0001"
                                min="0"
                                value={peso}
                                onChange={(e) => setPeso(e.target.value)}
                                className={`${THEME_INPUT} w-full py-3`}
                                placeholder="0.0000"
                            />
                        </div>

                        <div>
                            <div className="flex items-center justify-between gap-2 mb-2">
                                <label className={`${SECCION} m-0`}>Cajas</label>
                                <button type="button" onClick={agregarLinea} className={`${BTN_SECONDARY} text-xs flex items-center gap-1 outline-none`}>
                                    <Plus className="w-3 h-3" /> Otra caja
                                </button>
                            </div>
                            <div className="space-y-3">
                                {lineas.map((linea, idx) => (
                                    <div key={idx} className="flex flex-wrap gap-2 items-end">
                                        <div className="flex-1 min-w-[160px]">
                                            <label className={SECCION}>Tipo</label>
                                            <select
                                                value={linea.catalogo_tipo_caja_id}
                                                onChange={(e) => actualizarLinea(idx, 'catalogo_tipo_caja_id', e.target.value)}
                                                className={`${THEME_SELECT} w-full py-3`}
                                            >
                                                <option value="">Seleccionar…</option>
                                                {tiposCaja.map((c) => (
                                                    <option key={c.id} value={c.id}>{c.nombre}</option>
                                                ))}
                                            </select>
                                        </div>
                                        <div className="w-28">
                                            <label className={SECCION}>Cantidad</label>
                                            <input
                                                type="number"
                                                min="1"
                                                max="999"
                                                value={linea.cantidad}
                                                onChange={(e) => actualizarLinea(idx, 'cantidad', e.target.value)}
                                                className={`${THEME_INPUT} w-full py-3`}
                                            />
                                        </div>
                                        <button type="button" onClick={() => quitarLinea(idx)} disabled={lineas.length <= 1} className="p-3 rounded-xl border theme-border theme-element outline-none disabled:opacity-40 mb-0.5" aria-label="Quitar caja">
                                            <Trash2 className="w-4 h-4" />
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div className="gelia-modal-footer flex flex-col-reverse sm:flex-row flex-wrap gap-3 sm:justify-end p-5 md:p-6 border-t theme-border shrink-0">
                        <button type="button" onClick={onClose} className={`${BTN_SECONDARY} outline-none`} disabled={procesando}>Cancelar</button>
                        <button type="button" onClick={confirmar} disabled={procesando} className={`${BTN_PRIMARY} flex items-center justify-center gap-2 outline-none`}>
                            <Scale className="w-4 h-4" /> {procesando ? 'Guardando…' : 'Registrar pesaje'}
                        </button>
                    </div>
                </div>
            </div>
            <ModalAlertaPedido
                abierto={alerta.abierto}
                tipo={alerta.tipo}
                titulo={alerta.titulo}
                mensaje={alerta.mensaje}
                onClose={() => setAlerta((a) => ({ ...a, abierto: false }))}
            />
        </>,
        document.body
    );
}
