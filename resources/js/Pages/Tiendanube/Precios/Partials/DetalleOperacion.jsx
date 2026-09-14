import React from 'react';
import { GELIA_BTN_OUTLINE } from '../../../../utils/geliaTheme';
import EtiquetaEvidencia from './EtiquetaEvidencia';
import PanelRestauracion from './PanelRestauracion';

function formatoImporte(valor) {
    if (valor === null || valor === undefined || valor === '') return '—';
    return valor;
}

export default function DetalleOperacion({
    detalle,
    onCerrar,
    onConciliar,
    onPreparar,
    onConfirmarRestauracion,
    preview,
    seleccionRestauracion,
    onToggleCampo,
    busy,
    puedeRestaurar,
    diagnostico,
}) {
    if (!detalle) {
        return null;
    }
    const op = detalle.operacion || {};
    const items = detalle.items || [];

    return (
        <div className="fixed inset-0 z-40 flex justify-end">
            <button type="button" className="flex-1 bg-black/40" aria-label="Cerrar detalle" onClick={onCerrar} />
            <aside className="w-full max-w-xl h-full overflow-auto theme-surface border-l theme-border p-4 space-y-3">
                <div className="flex items-start justify-between gap-2">
                    <div>
                        <h2 className="text-lg font-black italic uppercase m-0">{op.descripcion}</h2>
                        <p className="text-xs theme-text-muted m-0">{op.actor} · {op.productos} productos / {op.variantes} variantes</p>
                    </div>
                    <button type="button" onClick={onCerrar} className="text-xs underline">Volver a la lista</button>
                </div>
                <EtiquetaEvidencia codigo={op.evidencia} etiqueta={op.evidencia_etiqueta} ayuda={op.evidencia_ayuda} />
                {op.lote_origen_id && (
                    <p className="text-xs m-0">Compensa la operación {op.lote_origen_id}</p>
                )}
                {(detalle.compensaciones || []).length > 0 && (
                    <p className="text-xs m-0">Tiene {detalle.compensaciones.length} compensación(es). Cancelar una compensación no cancela la original.</p>
                )}

                <div className="flex flex-wrap gap-2">
                    <button type="button" className={GELIA_BTN_OUTLINE} disabled={busy} onClick={onConciliar}>Conciliar lectura API</button>
                    {puedeRestaurar && (
                        <button type="button" className={GELIA_BTN_OUTLINE} disabled={busy} onClick={onPreparar}>Preparar restauración</button>
                    )}
                </div>

                <div className="space-y-2">
                    {items.map((item) => (
                        <article key={item.id} className="border theme-border rounded-xl p-2 text-sm">
                            <p className="font-semibold m-0">{item.nombre}</p>
                            <p className="text-xs theme-text-muted m-0">SKU {item.sku || '—'}{item.excluido ? ' · excluida' : ''}</p>
                            {item.exclusion_motivo && <p className="text-xs m-0">{item.exclusion_motivo}</p>}
                            <dl className="grid grid-cols-3 gap-1 mt-1 text-xs">
                                {Object.entries(item.campos || {}).map(([clave, campo]) => (
                                    <div key={clave}>
                                        <dt className="uppercase tracking-widest text-[10px] theme-text-muted">{clave}</dt>
                                        <dd className="m-0">
                                            {formatoImporte(campo.actual)} → {campo.intencion === 'eliminar' ? 'eliminar' : formatoImporte(campo.propuesto)}
                                            {campo.confirmado !== undefined && campo.confirmado !== null && (
                                                <span className="block">confirmado {formatoImporte(campo.confirmado)}</span>
                                            )}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </article>
                    ))}
                </div>

                <PanelRestauracion
                    preview={preview}
                    seleccion={seleccionRestauracion}
                    onToggleCampo={onToggleCampo}
                    onConfirmar={onConfirmarRestauracion}
                    busy={busy}
                    puedeRestaurar={puedeRestaurar}
                />

                {diagnostico && detalle.diagnostico && (
                    <details className="text-xs">
                        <summary>Diagnóstico técnico</summary>
                        <pre className="whitespace-pre-wrap">{JSON.stringify(detalle.diagnostico, null, 2)}</pre>
                    </details>
                )}
            </aside>
        </div>
    );
}
