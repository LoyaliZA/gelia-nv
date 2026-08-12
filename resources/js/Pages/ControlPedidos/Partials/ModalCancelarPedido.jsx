import React, { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { router } from '@inertiajs/react';
import { X, Ban } from 'lucide-react';
import {
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    THEME_LABEL,
    BTN_PRIMARY,
    BTN_SECONDARY,
    formatearMoneda,
} from './pedidosBmaStyles';
import { THEME_SELECT, THEME_TEXTAREA } from '../../../utils/geliaTheme';
import EncabezadoFolioPedido from './EncabezadoFolioPedido';

const SECCION = `${THEME_LABEL} mb-2 block`;

export default function ModalCancelarPedido({ abierto, onClose, pedido }) {
    const [motivo, setMotivo] = useState('');
    const [comentario, setComentario] = useState('');
    const [preview, setPreview] = useState(null);
    const [motivos, setMotivos] = useState({});
    const [cargando, setCargando] = useState(false);
    const [procesando, setProcesando] = useState(false);
    const [error, setError] = useState('');

    useEffect(() => {
        if (!abierto || !pedido?.id) return undefined;
        setMotivo('');
        setComentario('');
        setError('');
        setPreview(null);
        setCargando(true);
        let cancelled = false;
        fetch(route('control_pedidos.cancelar.preview', pedido.id), { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((json) => {
                if (cancelled) return;
                setPreview(json.preview || null);
                setMotivos(json.motivos || {});
            })
            .catch(() => {
                if (!cancelled) setError('No se pudo cargar la vista previa de cancelación.');
            })
            .finally(() => {
                if (!cancelled) setCargando(false);
            });
        return () => { cancelled = true; };
    }, [abierto, pedido?.id]);

    if (!abierto || !pedido) return null;

    const confirmar = () => {
        if (!motivo) {
            setError('Seleccione el motivo de cancelación.');
            return;
        }
        if (motivo === 'otro' && !comentario.trim()) {
            setError('El motivo «Otro» requiere comentario.');
            return;
        }
        setProcesando(true);
        setError('');
        router.post(route('control_pedidos.cancelar', pedido.id), {
            motivo,
            comentario,
            resolucion_financiera: (preview?.total_pagos || 0) > 0.01 ? 'pendiente_resolucion' : 'ninguna',
        }, {
            preserveScroll: true,
            onFinish: () => setProcesando(false),
            onSuccess: (page) => {
                if (page?.props?.flash?.error) {
                    setError(page.props.flash.error);
                    return;
                }
                onClose();
            },
            onError: (errs) => {
                const msg = Object.values(errs || {})[0];
                setError(typeof msg === 'string' ? msg : 'No se pudo cancelar.');
            },
        });
    };

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} style={{ zIndex: 'calc(var(--gelia-z-modal) + 10)' }} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-lg w-full flex flex-col`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <div className="min-w-0">
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 mb-1">Cancelar pedido</p>
                        <EncabezadoFolioPedido pedido={pedido} />
                    </div>
                    <button type="button" onClick={onClose} className="p-2 rounded-full outline-none" aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>
                <div className="gelia-modal-body p-5 space-y-4">
                    {cargando && <p className="text-xs font-bold theme-text-muted m-0">Cargando…</p>}
                    {preview && !preview.puede && (
                        <p className="text-sm font-bold text-red-600 m-0">{preview.motivo_bloqueo}</p>
                    )}
                    {preview?.puede && (
                        <>
                            <div className="p-3 rounded-xl border theme-border space-y-1 text-xs font-bold theme-text-muted">
                                <p className="m-0 theme-text-main uppercase text-[10px] font-black">Efectos</p>
                                <p className="m-0">Fase actual: {preview.fase || '—'}</p>
                                <p className="m-0">Pagos registrados: {formatearMoneda(preview.total_pagos)}</p>
                                <p className="m-0">SAF aplicado/reservado: {formatearMoneda(preview.saf_aplicado)}</p>
                                {preview.es_resguardo && <p className="m-0 text-blue-600">Pedido en resguardo{preview.tiene_apartado ? ' (apartado CEDIS)' : ''}.</p>}
                                {(preview.productos || []).map((t) => (
                                    <p key={t} className="m-0">· {t}</p>
                                ))}
                                {preview.total_pagos > 0.01 && (
                                    <p className="m-0 text-amber-700">
                                        Resolución financiera: pendiente (devolución/SAF se gestiona aparte).
                                    </p>
                                )}
                            </div>
                            <div>
                                <label className={SECCION}>Motivo *</label>
                                <select value={motivo} onChange={(e) => setMotivo(e.target.value)} className={`${THEME_SELECT} w-full py-3`}>
                                    <option value="">Seleccionar…</option>
                                    {Object.entries(motivos).map(([k, v]) => (
                                        <option key={k} value={k}>{v}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className={SECCION}>Comentario{motivo === 'otro' ? ' *' : ''}</label>
                                <textarea value={comentario} onChange={(e) => setComentario(e.target.value)} className={`${THEME_TEXTAREA} w-full py-3 min-h-[80px]`} placeholder="Detalle de la cancelación…" />
                            </div>
                        </>
                    )}
                    {error && <p className="text-xs font-bold text-red-600 m-0">{error}</p>}
                </div>
                <div className="gelia-modal-footer p-5 border-t theme-border flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                    <button type="button" onClick={onClose} className={`${BTN_SECONDARY} min-h-[44px]`} disabled={procesando}>Cerrar</button>
                    {preview?.puede && (
                        <button type="button" onClick={confirmar} disabled={procesando || cargando} className={`${BTN_PRIMARY} min-h-[44px] inline-flex items-center justify-center gap-2 bg-red-600 hover:bg-red-700`}>
                            <Ban className="w-4 h-4" /> {procesando ? 'Cancelando…' : 'Confirmar cancelación'}
                        </button>
                    )}
                </div>
            </div>
        </div>,
        document.body
    );
}
