import React, { useState } from 'react';
import { ChevronDown, ChevronUp, Paperclip } from 'lucide-react';
import {
    badgeEstatusPedido,
    formatearFechaHoraAuditoria,
} from './pedidosBmaStyles';
import DetalleSnapshotBitacora from './DetalleSnapshotBitacora';

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

function tieneDetalle(h) {
    const snap = h.snapshot_json || h.snapshotJson;
    const evidencia = h.evidencia_ruta || h.evidenciaRuta;
    const comentario = h.comentarios;
    const accionLabel = labelAccion(h);
    const comentarioEsAccion = comentario && comentario === accionLabel;
    return Boolean(snap) || Boolean(evidencia) || (comentario && !comentarioEsAccion);
}

export default function TarjetaEntradaBitacora({
    entrada,
    onVerEvidencia = null,
    onVerArchivoSnapshot = null,
    compacto = false,
}) {
    const [expandido, setExpandido] = useState(false);
    const h = entrada;
    const estatusNuevo = h.estatus_nuevo || h.estatusNuevo;
    const estatusAnterior = h.estatus_anterior || h.estatusAnterior;
    const badgeNuevo = badgeEstatusPedido(estatusNuevo);
    const badgeAnt = badgeEstatusPedido(estatusAnterior);
    const actor = contextoActor(h);
    const evidenciaRuta = h.evidencia_ruta || h.evidenciaRuta;
    const evidenciaNombre = h.evidencia_nombre || h.evidenciaNombre || 'Ver archivo';
    const accionLabel = labelAccion(h);
    const comentarioEsAccion = h.comentarios && h.comentarios === accionLabel;
    const snap = h.snapshot_json || h.snapshotJson || null;
    const desplegable = tieneDetalle(h);

    return (
        <div className="rounded-xl border theme-border theme-element overflow-hidden">
            <button
                type="button"
                onClick={() => desplegable && setExpandido((v) => !v)}
                disabled={!desplegable}
                className={`w-full text-left p-4 outline-none ${desplegable ? 'hover:bg-black/[0.02] dark:hover:bg-white/[0.02]' : ''}`}
                aria-expanded={expandido}
            >
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

                {h.comentarios && !comentarioEsAccion && !compacto ? (
                    <p className="text-xs theme-text-main mt-1 m-0 line-clamp-2">{h.comentarios}</p>
                ) : null}

                {desplegable && (
                    <span className="inline-flex items-center gap-1 mt-2 text-[9px] font-black uppercase theme-text-muted">
                        {expandido ? <ChevronUp className="w-3 h-3" /> : <ChevronDown className="w-3 h-3" />}
                        {expandido ? 'Ocultar detalle' : 'Ver captura del movimiento'}
                    </span>
                )}
            </button>

            {expandido && (
                <div className="px-4 pb-4">
                    {h.comentarios && !comentarioEsAccion && compacto ? (
                        <p className="text-xs theme-text-main mb-3 m-0">{h.comentarios}</p>
                    ) : null}
                    {evidenciaRuta && onVerEvidencia ? (
                        <button
                            type="button"
                            onClick={onVerEvidencia}
                            className="inline-flex items-center gap-1 mb-3 text-[10px] font-bold uppercase outline-none"
                            style={{ color: 'var(--color-primario)' }}
                        >
                            <Paperclip className="w-3 h-3" />
                            {evidenciaNombre}
                        </button>
                    ) : null}
                    <DetalleSnapshotBitacora
                        snapshot={snap}
                        onVerArchivo={onVerArchivoSnapshot}
                    />
                </div>
            )}
        </div>
    );
}
