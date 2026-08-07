import React from 'react';
import { createPortal } from 'react-dom';
import { X, History, Paperclip } from 'lucide-react';
import {
    badgeEstatusPedido,
    formatearFechaHoraAuditoria,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
} from './pedidosBmaStyles';
import EncabezadoFolioPedido from './EncabezadoFolioPedido';

function labelEstatus(estatus) {
    if (!estatus) return '—';
    return estatus.nombre_visual || estatus.nombre || estatus.fase_ciclo || '—';
}

function labelAccion(h) {
    return h.accion_etiqueta || h.accionEtiqueta || h.accion || h.comentarios || 'Movimiento';
}

function contextoActor(h) {
    const partes = [h.rol, h.departamento].filter(Boolean);
    return partes.length ? partes.join(' · ') : null;
}

export default function ModalBitacoraPedido({ abierto, onClose, pedido }) {
    if (!abierto || !pedido) return null;

    const historial = pedido.historial || [];

    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-start sm:items-center py-4 sm:py-6`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} max-w-2xl w-full flex flex-col`}
                style={{ maxHeight: 'calc(100dvh - 2rem)' }}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="p-5 md:p-6 border-b theme-border flex justify-between items-start gap-3 shrink-0">
                    <div className="flex items-center gap-2 min-w-0">
                        <History className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        <div className="min-w-0">
                            <h2 className="text-lg font-black italic uppercase theme-text-main m-0">Bitácora</h2>
                            <EncabezadoFolioPedido pedido={pedido} size="sm" className="mt-1" />
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        className="p-2 rounded-full theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 transition-colors outline-none shrink-0"
                        aria-label="Cerrar"
                    >
                        <X className="w-5 h-5" />
                    </button>
                </div>
                <div className="gelia-modal-body p-5 md:p-6">
                    {historial.length === 0 ? (
                        <p className="text-sm theme-text-muted font-bold uppercase m-0">Sin movimientos registrados_</p>
                    ) : (
                        <div className="space-y-4">
                            {historial.map((h) => {
                                const estatusNuevo = h.estatus_nuevo || h.estatusNuevo;
                                const estatusAnterior = h.estatus_anterior || h.estatusAnterior;
                                const badgeNuevo = badgeEstatusPedido(estatusNuevo);
                                const badgeAnt = badgeEstatusPedido(estatusAnterior);
                                const actor = contextoActor(h);
                                const evidenciaRuta = h.evidencia_ruta || h.evidenciaRuta;
                                const evidenciaNombre = h.evidencia_nombre || h.evidenciaNombre || 'Ver archivo';
                                const accionLabel = labelAccion(h);
                                const comentarioEsAccion = h.comentarios && h.comentarios === accionLabel;

                                return (
                                    <div key={h.id} className="p-4 rounded-xl border theme-border theme-element">
                                        <div className="flex justify-between items-start gap-2">
                                            <p className="text-xs font-black uppercase theme-text-main m-0 leading-snug">
                                                {accionLabel}
                                            </p>
                                            <span className="text-[9px] theme-text-muted font-bold shrink-0 font-mono">
                                                {formatearFechaHoraAuditoria(h.created_at)}
                                            </span>
                                        </div>

                                        <div className="flex flex-wrap items-center gap-1.5 mt-2">
                                            {estatusAnterior ? (
                                                <>
                                                    <span className={badgeAnt.className} style={badgeAnt.style}>
                                                        {badgeAnt.label}
                                                    </span>
                                                    <span className="text-[10px] theme-text-muted font-bold">→</span>
                                                </>
                                            ) : null}
                                            <span className={badgeNuevo.className} style={badgeNuevo.style}>
                                                {badgeNuevo.label || labelEstatus(estatusNuevo)}
                                            </span>
                                        </div>

                                        <p className="text-[10px] font-bold theme-text-muted mt-2 m-0">
                                            {h.usuario?.name || 'Usuario'}
                                            {actor ? (
                                                <span className="font-semibold opacity-80"> · {actor}</span>
                                            ) : null}
                                        </p>

                                        {h.comentarios && !comentarioEsAccion ? (
                                            <p className="text-xs theme-text-main mt-1 m-0">{h.comentarios}</p>
                                        ) : null}

                                        {evidenciaRuta ? (
                                            <a
                                                href={`/storage/${evidenciaRuta}`}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="inline-flex items-center gap-1 mt-2 text-[10px] font-bold uppercase"
                                                style={{ color: 'var(--color-primario)' }}
                                            >
                                                <Paperclip className="w-3 h-3" />
                                                {evidenciaNombre}
                                            </a>
                                        ) : null}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </div>,
        document.body
    );
}
