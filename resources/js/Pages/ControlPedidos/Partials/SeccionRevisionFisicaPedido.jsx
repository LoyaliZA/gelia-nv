import React, { useState } from 'react';
import { badgeEstadoFisico, etiquetasInstanciaRevision, LABELS_RESOLUCION_SIN_EXISTENCIA, revisionSinExistenciaAbierta } from './pedidosBmaStyles';
import { MiniaturaDocumento } from './ModalVistaPreviaDocumento';
import ModalAtenderSinExistencia from './ModalAtenderSinExistencia';
import { BTN_SECONDARY } from './pedidosBmaStyles';

/**
 * Revisión física CEDIS (detalle + formulario vendedora).
 */
export default function SeccionRevisionFisicaPedido({
    pedido, onVerDoc, titulo = 'Revisión física CEDIS', puedeAtender = false, puedeCancelar = false,
}) {
    if (!pedido) return null;

    const [revisionActiva, setRevisionActiva] = useState(null);
    const badgeFisico = pedido.estado_fisico_general ? badgeEstadoFisico(pedido.estado_fisico_general) : null;
    const evidenciasCondicion = (pedido.documentos || []).filter((d) => d.tipo === 'evidencia_condicion');
    const revisiones = [...(pedido.revisiones_producto || pedido.revisionesProducto || [])]
        .sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0));
    const instancias = etiquetasInstanciaRevision(revisiones);
    const docsDeProducto = (revId) => evidenciasCondicion.filter(
        (d) => d.relacion_tipo === 'revision_producto' && String(d.relacion_id) === String(revId),
    );
    const revisionConDetalle = (r) => (
        r.estado_fisico !== 'bueno'
        || Boolean(r.comentario)
        || Boolean(r.unica_pieza)
        || Boolean(r.mejor_ejemplar)
        || docsDeProducto(r.id).length > 0
        || Boolean(r.resolucion)
    );
    const revisionesConDetalle = revisiones.filter(revisionConDetalle);
    const revisionesOk = revisiones.filter((r) => !revisionConDetalle(r));
    const indiceRevision = (r) => revisiones.findIndex((x) => x === r || (x.id && x.id === r.id));
    const evidenciasLote = evidenciasCondicion.filter(
        (d) => d.relacion_tipo === 'revision_general' || !d.relacion_tipo,
    );
    const evidenciasEnvio = evidenciasCondicion.filter((d) => d.relacion_tipo === 'envio_caja');
    const cajasOrdenadas = [...(pedido.cajas || [])].sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0));
    const etiquetaEnvioDoc = (doc) => {
        const idx = cajasOrdenadas.findIndex((c) => String(c.id) === String(doc.relacion_id));
        if (idx >= 0) return `Envío ${idx + 1}`;
        return doc.comentario || 'Envío';
    };
    const tieneRevisionFisica = Boolean(pedido.estado_fisico_general)
        || revisiones.length > 0
        || evidenciasLote.length > 0
        || evidenciasEnvio.length > 0
        || Boolean(pedido.tiene_observaciones_fisicas);
    const hayAbierta = revisiones.some(revisionSinExistenciaAbierta);

    if (!tieneRevisionFisica) return null;

    return (
        <div className="mt-4 space-y-4">
            <div className="flex flex-wrap items-center gap-2">
                <p className="text-[9px] font-black uppercase theme-text-muted m-0">{titulo}</p>
                {pedido.tiene_observaciones_fisicas && (
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wide bg-orange-500/15 text-orange-600">
                        Observaciones CEDIS
                    </span>
                )}
                {hayAbierta && (
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wide bg-sky-500/15 text-sky-600">
                        Pedido detenido — sin existencias
                    </span>
                )}
            </div>
            {hayAbierta && (
                <p className="text-xs font-bold text-sky-700 m-0">
                    CEDIS reportó piezas sin existencias. El pedido no avanza hasta que Ventas elija una acción por cada pieza.
                </p>
            )}
            {pedido.estado_fisico_general && (
                <div className="flex flex-wrap items-center gap-2">
                    {badgeFisico && (
                        <span className={badgeFisico.className} style={badgeFisico.style}>{badgeFisico.label}</span>
                    )}
                    {pedido.comentario_fisico_general && (
                        <p className="text-sm font-bold theme-text-main m-0">{pedido.comentario_fisico_general}</p>
                    )}
                </div>
            )}
            {revisionesConDetalle.length > 0 && (
                <div className="space-y-2">
                    <p className="text-[9px] font-black uppercase theme-text-muted m-0">Productos con detalle</p>
                    {revisionesConDetalle.map((r) => {
                        const b = badgeEstadoFisico(r.estado_fisico);
                        const docs = docsDeProducto(r.id);
                        const instancia = instancias[indiceRevision(r)];
                        const abierta = revisionSinExistenciaAbierta(r);
                        return (
                            <div key={r.id || `${r.descripcion_producto}-${r.estado_fisico}`} className="p-3 rounded-xl border theme-border space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    {instancia && (
                                        <span className="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-black tabular-nums theme-element border theme-border theme-text-main">
                                            {instancia}
                                        </span>
                                    )}
                                    <p className="text-xs font-black theme-text-main m-0">{r.descripcion_producto}</p>
                                    {b && <span className={b.className} style={b.style}>{b.label}</span>}
                                    {r.unica_pieza && <span className="text-[9px] font-black uppercase text-blue-600">Única pieza</span>}
                                    {r.mejor_ejemplar && <span className="text-[9px] font-black uppercase text-emerald-600">Mejor ejemplar</span>}
                                    {r.resolucion && (
                                        <span className="text-[9px] font-black uppercase text-sky-700">
                                            {r.resolucion_etiqueta || LABELS_RESOLUCION_SIN_EXISTENCIA[r.resolucion] || r.resolucion}
                                        </span>
                                    )}
                                </div>
                                {r.comentario && <p className="text-xs theme-text-muted font-bold m-0">{r.comentario}</p>}
                                {r.resolucion_nota && <p className="text-xs theme-text-muted font-bold m-0">Decisión: {r.resolucion_nota}</p>}
                                {r.estado_fisico === 'sin_existencia' && !r.resolucion && (
                                    <p className="text-[10px] font-black uppercase text-sky-600 m-0">
                                        Sin existencias en CEDIS — Ventas debe proceder (retirar / sustituir / contactar / esperar / cancelar).
                                    </p>
                                )}
                                {abierta && puedeAtender && (
                                    <button
                                        type="button"
                                        onClick={() => setRevisionActiva(r)}
                                        className={`${BTN_SECONDARY} text-xs min-h-[40px]`}
                                    >
                                        Atender pieza
                                    </button>
                                )}
                                {docs.length > 0 && onVerDoc && (
                                    <div className="flex flex-wrap gap-2">
                                        {docs.map((doc) => (
                                            <MiniaturaDocumento key={doc.id} documento={doc} onVer={onVerDoc} />
                                        ))}
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            )}
            {revisionesOk.length > 0 && (
                <div className="space-y-1">
                    <p className="text-[9px] font-black uppercase theme-text-muted m-0">Productos OK</p>
                    <p className="text-xs font-bold theme-text-main m-0">
                        {revisionesOk.map((r) => {
                            const tag = instancias[indiceRevision(r)];
                            return tag ? `${r.descripcion_producto} (${tag})` : r.descripcion_producto;
                        }).join(' · ')}
                    </p>
                </div>
            )}
            {evidenciasLote.length > 0 && onVerDoc && (
                <div className="space-y-2">
                    <p className="text-[9px] font-black uppercase theme-text-muted m-0">Evidencias del lote</p>
                    <div className="flex flex-wrap gap-2">
                        {evidenciasLote.map((doc) => (
                            <MiniaturaDocumento key={doc.id} documento={doc} onVer={onVerDoc} />
                        ))}
                    </div>
                </div>
            )}
            {evidenciasEnvio.length > 0 && onVerDoc && (
                <div className="space-y-2">
                    <p className="text-[9px] font-black uppercase theme-text-muted m-0">Foto por envío</p>
                    {evidenciasEnvio.map((doc) => (
                        <div key={doc.id} className="space-y-1">
                            <p className="text-[10px] font-black uppercase theme-text-muted m-0">{etiquetaEnvioDoc(doc)}</p>
                            <MiniaturaDocumento documento={doc} onVer={onVerDoc} />
                        </div>
                    ))}
                </div>
            )}
            <ModalAtenderSinExistencia
                abierto={Boolean(revisionActiva)}
                pedido={pedido}
                revision={revisionActiva}
                puedeCancelar={puedeCancelar}
                onClose={() => setRevisionActiva(null)}
            />
        </div>
    );
}
