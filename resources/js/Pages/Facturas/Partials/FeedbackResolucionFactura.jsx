import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { CheckCircle2, AlertOctagon, MessageSquare, FileText } from 'lucide-react';
import { THEME_MODAL_OVERLAY } from '../../../utils/geliaTheme';
import { urlArchivoFactura } from './facturasStyles';
import { nombreEstadoFactura } from './facturasFiltros';

const chipEvidencia = 'inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border theme-element theme-border hover:border-[var(--color-primario)] transition-colors';

const VisorEvidenciaUrl = ({ url, esPdf }) => {
    const [isHovered, setIsHovered] = useState(false);
    if (!url) return null;

    if (esPdf) {
        return (
            <a href={url} target="_blank" rel="noopener noreferrer" className={chipEvidencia}>
                <span className="text-[9px] font-black uppercase tracking-widest" style={{ color: 'var(--color-primario)' }}>Ver PDF</span>
            </a>
        );
    }

    return (
        <div className="inline-block" onMouseEnter={() => setIsHovered(true)} onMouseLeave={() => setIsHovered(false)}>
            <div className={`${chipEvidencia} cursor-pointer`}>
                <img src={url} className="w-5 h-5 object-cover rounded shadow-sm" alt="Miniatura" />
                <span className="text-[9px] font-black uppercase tracking-widest" style={{ color: 'var(--color-primario)' }}>Ver evidencia</span>
            </div>
            {isHovered && createPortal(
                <div className={`${THEME_MODAL_OVERLAY} items-center justify-center pointer-events-none`}>
                    <img src={url} alt="Evidencia expandida" className="max-w-full max-h-full object-contain rounded-2xl shadow-2xl" />
                </div>,
                document.body
            )}
        </div>
    );
};

export default function FeedbackResolucionFactura({ factura }) {
    const estadoNombre = nombreEstadoFactura(factura);
    const esError = estadoNombre === 'Incorrecta';
    const esAprobada = estadoNombre === 'Respondida' || estadoNombre === 'Verificada';

    const auditoriasOrdenadas = [...(factura.auditorias || [])].sort((a, b) => b.id - a.id);
    const ultimaAuditoria = auditoriasOrdenadas.find(
        (a) => !a.motivo_reporte?.toUpperCase().includes('AUTOMÁTICAMENTE') && !a.motivo_reporte?.includes('Creación de solicitud')
    );

    const tieneObservacion = factura.observaciones_vendedor?.trim();
    const motivo = factura.motivo_respuesta?.trim() || ultimaAuditoria?.motivo_reporte?.trim();
    const tieneEvidenciaError = Boolean(factura.evidencia_error_path);
    const urlEvidenciaError = tieneEvidenciaError ? urlArchivoFactura(factura.id, 'evidencia_error') : null;
    const esPdfEvidencia = factura.evidencia_error_path?.toLowerCase().endsWith('.pdf');

    if (!tieneObservacion && !motivo && !tieneEvidenciaError && !(esAprobada && factura.tiene_pdf_emitido)) {
        return null;
    }

    const colorContenedor = esError ? 'bg-red-500/10 border-red-500/25' : 'bg-emerald-500/10 border-emerald-500/25';
    const Icono = esError ? AlertOctagon : CheckCircle2;
    const colorIcono = esError ? 'text-red-500' : 'text-emerald-500';
    const colorTexto = esError ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400';
    const autor = ultimaAuditoria?.usuario?.name?.trim();
    const estadoAnteriorNombre = ultimaAuditoria?.estado_anterior?.nombre
        || ultimaAuditoria?.estadoAnterior?.nombre
        || '';
    const esRespuestaACorreccion = esAprobada && estadoAnteriorNombre === 'Incorrecta';
    const titulo = esError
        ? (autor ? `Error reportado por ${autor}` : 'Corrección requerida')
        : esRespuestaACorreccion
            ? (autor ? `Respuesta a corrección · ${autor}` : 'Respuesta a corrección')
            : (autor ? `Respuesta de ${autor}` : 'Respuesta');

    return (
        <div className="mb-4 flex flex-col gap-2">
            {tieneObservacion && (
                <div className="p-3 rounded-2xl border theme-element theme-border flex items-start gap-2">
                    <MessageSquare className="w-4 h-4 theme-text-muted mt-0.5 shrink-0" />
                    <div className="min-w-0">
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-0.5 m-0">Nota de vendedora</p>
                        <p className="text-xs font-bold theme-text-main italic leading-snug m-0 break-words">{factura.observaciones_vendedor}</p>
                    </div>
                </div>
            )}

            {(esError || esAprobada) && (motivo || tieneEvidenciaError || (esAprobada && factura.tiene_pdf_emitido)) && (
                <div className={`p-3 rounded-2xl border flex flex-col gap-2 ${colorContenedor}`}>
                    <div className="flex items-start gap-2 min-w-0">
                        <Icono className={`w-4 h-4 shrink-0 mt-0.5 ${colorIcono}`} />
                        <div className="flex-1 min-w-0">
                            <p className={`text-[9px] font-black uppercase tracking-widest mb-0.5 m-0 ${colorTexto}`}>
                                {titulo}
                            </p>
                            {motivo && (
                                <p className="text-xs font-bold italic leading-tight theme-text-main m-0 break-words whitespace-pre-wrap">
                                    {motivo}
                                </p>
                            )}
                            {factura.respondida_por?.name && esAprobada && (
                                <p className="text-[9px] font-bold theme-text-muted mt-1 m-0">
                                    Por: {factura.respondida_por.name}
                                </p>
                            )}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2 mt-1">
                        {tieneEvidenciaError && urlEvidenciaError && (
                            <VisorEvidenciaUrl url={urlEvidenciaError} esPdf={esPdfEvidencia} />
                        )}
                        {esAprobada && factura.tiene_pdf_emitido && (
                            <a
                                href={urlArchivoFactura(factura.id, 'pdf')}
                                target="_blank"
                                rel="noopener noreferrer"
                                className={chipEvidencia}
                            >
                                <FileText className="w-3.5 h-3.5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                                <span className="text-[9px] font-black uppercase tracking-widest" style={{ color: 'var(--color-primario)' }}>Ver PDF emitido</span>
                            </a>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
