import React, { useId } from 'react';
import { Download, ExternalLink } from 'lucide-react';
import { EstadoBadge } from './GeliaAiEstado';
import { formatearNumero, resultadoDesdeReporte } from './geliaAiEstados';

const MAX_DETALLES = 8;

/** Resultado real devuelto por el servidor tras ejecutar una propuesta. */
export default function ResultadoAccionCard({ reporte = {}, enlaceImportaciones = null }) {
    const tituloId = useId();
    const info = resultadoDesdeReporte(reporte);
    const detalles = Array.isArray(reporte.detalles) ? reporte.detalles : [];
    const conteos = reporte.conteos || null;

    return (
        <section className="gelia-ai-card" data-estado="resultado" aria-labelledby={tituloId}>
            <div className="gelia-ai-card-head">
                <EstadoBadge etiqueta={info.etiqueta} tono={info.tono} icono={info.tono === 'exito' ? 'ok' : 'reloj'} />
            </div>
            <h3 id={tituloId} className="gelia-ai-card-title">{reporte.resumen || 'Acción terminada.'}</h3>

            {conteos && (
                <p className="gelia-ai-card-text">
                    <span className="tabular-nums">{formatearNumero(conteos.ok)}</span> filas generadas
                    {Number(conteos.error) > 0 && (
                        <> · <span className="tabular-nums">{formatearNumero(conteos.error)}</span> con inconsistencia</>
                    )}
                </p>
            )}
            {info.nota && <p className="gelia-ai-card-note">{info.nota}</p>}

            {detalles.length > 0 && (
                <ul className="gelia-ai-card-warn" aria-label="Avisos del resultado">
                    {detalles.slice(0, MAX_DETALLES).map((d, i) => (
                        <li key={`${d.sku || ''}-${i}`}>
                            <span><span className="font-semibold">{d.sku || 'Sin SKU'}</span>: {d.error}</span>
                        </li>
                    ))}
                    {detalles.length > MAX_DETALLES && <li><span>…y {detalles.length - MAX_DETALLES} más.</span></li>}
                </ul>
            )}

            {(reporte.download_url || (reporte.estado === 'en_cola' && enlaceImportaciones)) && (
                <div className="gelia-ai-card-actions">
                    {reporte.download_url && (
                        <a href={reporte.download_url} className="gelia-ai-btn gelia-ai-btn--primary">
                            <Download className="w-3.5 h-3.5" aria-hidden />
                            Descargar listado
                        </a>
                    )}
                    {reporte.estado === 'en_cola' && enlaceImportaciones && (
                        <a href={enlaceImportaciones} className="gelia-ai-btn">
                            <ExternalLink className="w-3.5 h-3.5" aria-hidden />
                            Ver importaciones
                        </a>
                    )}
                </div>
            )}
        </section>
    );
}
