import React, { useState } from 'react';
import { ClipboardCheck, AlertTriangle } from 'lucide-react';
import ListaProductosOk, { MAX_PRODUCTOS_OK_ABIERTOS } from './ListaProductosOk';
import { badgeEstadoFisico, etiquetasInstanciaRevision, LABELS_RESOLUCION_SIN_EXISTENCIA, revisionSinExistenciaAbierta, formatearFechaHoraAuditoria, BTN_SECONDARY } from './pedidosBmaStyles';
import { MiniaturaDocumento } from './ModalVistaPreviaDocumento';
import ModalAtenderSinExistencia from './ModalAtenderSinExistencia';


/**
 * Revisión física CEDIS (detalle + formulario vendedora).
 */
export default function SeccionRevisionFisicaPedido({
    pedido, onVerDoc, onVerGaleria, titulo = 'Revisión física CEDIS', puedeAtender = false, puedeCancelar = false,
}) {
    const [revisionActiva, setRevisionActiva] = useState(null);
    if (!pedido) return null;
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
    const revisionesConDetalle = revisiones.filter(revisionConDetalle).sort((a, b) => Number(revisionSinExistenciaAbierta(b)) - Number(revisionSinExistenciaAbierta(a)));
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
    const abiertas = revisiones.filter(revisionSinExistenciaAbierta).length;
    const hayAbierta = abiertas > 0;
    const gruposEnvio = [...new Set(evidenciasEnvio.map((doc) => String(doc.relacion_id || 'general')))]
        .map((id) => evidenciasEnvio.filter((doc) => String(doc.relacion_id || 'general') === id));
    const verDoc = (docs, indice) => onVerGaleria ? onVerGaleria(docs, indice) : onVerDoc?.(docs[indice]);

    if (!tieneRevisionFisica) return null;

    const productosOkCompactos = revisionesOk.length > MAX_PRODUCTOS_OK_ABIERTOS;

    return (
        <section className="gelia-pedidos-revision space-y-4" aria-label={titulo}>
            <div className="flex flex-wrap items-center gap-2">
                <h3 className="gelia-pedidos-seccion-titulo flex items-center gap-2"><ClipboardCheck className="w-4 h-4 theme-text-muted" aria-hidden="true" />{titulo}</h3>
                {pedido.tiene_observaciones_fisicas && (
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold gelia-estado-vivo gelia-estado-vivo--aviso">
                        Observaciones CEDIS
                    </span>
                )}
                {hayAbierta && (
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold gelia-estado-vivo gelia-estado-vivo--info">
                        Pedido detenido — sin existencias
                    </span>
                )}
            </div>
            <div className="gelia-pedidos-revision-resumen">
                <span><strong className="theme-text-main">{revisiones.length}</strong> piezas revisadas</span>
                <span><strong className="theme-text-main">{revisionesOk.length}</strong> sin observaciones</span>
                <span><strong className="theme-text-main">{revisionesConDetalle.length}</strong> con detalle</span>
                {pedido.pesaje_respondido_at && <span>Recibido {formatearFechaHoraAuditoria(pedido.pesaje_respondido_at)}</span>}
                {(pedido.pesaje_respondido_por?.name || pedido.pesajeRespondidoPor?.name) && <span>Por {pedido.pesaje_respondido_por?.name || pedido.pesajeRespondidoPor?.name}</span>}
            </div>
            {hayAbierta && (
                <div className="gelia-estado-vivo gelia-estado-vivo--aviso p-3 rounded-xl flex items-start gap-2">
                    <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" aria-hidden="true" />
                    <p className="text-sm m-0">{abiertas} {abiertas === 1 ? 'pieza requiere' : 'piezas requieren'} una decisión de Ventas. Atiende cada pieza sin existencias para que el pedido pueda avanzar.</p>
                </div>
            )}
            {pedido.estado_fisico_general && (
                <div className="flex flex-wrap items-center gap-2">
                    {badgeFisico && (
                        <span className={badgeFisico.className} style={badgeFisico.style}>{badgeFisico.label}</span>
                    )}
                    {pedido.comentario_fisico_general && (
                        <p className="text-sm font-medium theme-text-main m-0">{pedido.comentario_fisico_general}</p>
                    )}
                </div>
            )}
            {revisionesConDetalle.length > 0 && (
                <div className="space-y-2">
                    <h4 className="text-sm font-semibold theme-text-main m-0">Productos con detalle</h4>
                    {revisionesConDetalle.map((r) => {
                        const b = badgeEstadoFisico(r.estado_fisico);
                        const docs = docsDeProducto(r.id);
                        const instancia = instancias[indiceRevision(r)];
                        const abierta = revisionSinExistenciaAbierta(r);
                        return (
                            <div key={r.id || `${r.descripcion_producto}-${r.estado_fisico}`} className="p-3 rounded-xl border theme-border space-y-2">
                                <div className="flex flex-wrap items-center gap-2">
                                    {instancia && (
                                        <span className="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-semibold tabular-nums theme-element border theme-border theme-text-main">
                                            {instancia}
                                        </span>
                                    )}
                                    <p className="text-sm font-semibold theme-text-main m-0">{r.descripcion_producto}</p>
                                    {b && <span className={b.className} style={b.style}>{b.label}</span>}
                                    {r.unica_pieza && <span className="text-xs font-semibold theme-text-info">Única pieza</span>}
                                    {r.mejor_ejemplar && <span className="text-xs font-semibold theme-text-exito">Mejor ejemplar</span>}
                                    {r.resolucion && (
                                        <span className="text-xs font-semibold theme-text-info">
                                            {r.resolucion_etiqueta || LABELS_RESOLUCION_SIN_EXISTENCIA[r.resolucion] || r.resolucion}
                                        </span>
                                    )}
                                </div>
                                {r.comentario && <p className="text-xs theme-text-muted font-medium m-0">{r.comentario}</p>}
                                {r.resolucion_nota && <p className="text-xs theme-text-muted font-medium m-0">Decisión: {r.resolucion_nota}</p>}
                                {r.estado_fisico === 'sin_existencia' && !r.resolucion && (
                                    <p className="text-xs font-semibold theme-text-info m-0">
                                        Elige cómo proceder con esta pieza sin existencias.
                                    </p>
                                )}
                                {abierta && puedeAtender && (
                                    <button
                                        type="button"
                                        onClick={() => setRevisionActiva(r)}
                                        className={`${BTN_SECONDARY} text-xs min-h-[40px]`}
                                    >
                                        Resolver pieza sin existencias
                                    </button>
                                )}
                                {docs.length > 0 && onVerDoc && (
                                    <div className="flex flex-wrap gap-2">
                                        {docs.map((doc, indice) => (
                                            <MiniaturaDocumento key={doc.id} documento={doc} onVer={() => verDoc(docs, indice)} />
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
                    <h4 className="text-sm font-semibold theme-text-main m-0">Productos OK</h4>
                    <ListaProductosOk productos={revisionesOk} compactos={productosOkCompactos}
                        etiquetaInstancia={(r) => instancias[indiceRevision(r)]} />
                </div>
            )}
            {evidenciasLote.length > 0 && onVerDoc && (
                <div className="space-y-2">
                    <h4 className="text-sm font-semibold theme-text-main m-0">Evidencias del lote</h4>
                    <div className="flex flex-wrap gap-2">
                        {evidenciasLote.map((doc, indice) => (
                            <MiniaturaDocumento key={doc.id} documento={doc} onVer={() => verDoc(evidenciasLote, indice)} />
                        ))}
                    </div>
                </div>
            )}
            {evidenciasEnvio.length > 0 && onVerDoc && (
                <div className="space-y-2">
                    <h4 className="text-sm font-semibold theme-text-main m-0">Foto por envío</h4>
                    {gruposEnvio.map((docs) => (
                        <div key={docs[0].relacion_id || 'general'} className="rounded-xl border theme-border p-3 space-y-2">
                            <p className="text-xs font-semibold theme-text-muted m-0">{etiquetaEnvioDoc(docs[0])} · {docs.length} {docs.length === 1 ? 'evidencia' : 'evidencias'}</p>
                            <div className="flex flex-wrap gap-2">{docs.map((doc, indice) => <MiniaturaDocumento key={doc.id} documento={doc} onVer={() => verDoc(docs, indice)} />)}</div>
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
        </section>
    );
}
