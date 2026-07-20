import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { CheckCircle2, AlertOctagon, MessageSquare } from 'lucide-react';
import { THEME_MODAL_OVERLAY } from '../../../utils/geliaTheme';

const chipEvidencia = 'inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border theme-element theme-border hover:border-[var(--color-primario)] transition-colors';

const VisorImagenHover = ({ path }) => {
    const [isHovered, setIsHovered] = useState(false);
    if (!path) return null;

    const imageUrl = `/storage/${path}`;
    const esPdf = path.toLowerCase().endsWith('.pdf');

    if (esPdf) {
        return (
            <a href={imageUrl} target="_blank" rel="noreferrer" className={chipEvidencia}>
                <span className="text-[9px] font-black uppercase tracking-widest" style={{ color: 'var(--color-primario)' }}>Ver PDF</span>
            </a>
        );
    }

    return (
        <div className="inline-block" onMouseEnter={() => setIsHovered(true)} onMouseLeave={() => setIsHovered(false)}>
            <div className={`${chipEvidencia} cursor-pointer`}>
                <img src={imageUrl} className="w-5 h-5 object-cover rounded shadow-sm" alt="Miniatura" />
                <span className="text-[9px] font-black uppercase tracking-widest" style={{ color: 'var(--color-primario)' }}>Ver resolución</span>
            </div>
            {isHovered && createPortal(
                <div className={`${THEME_MODAL_OVERLAY} items-center justify-center pointer-events-none`}>
                    <img src={imageUrl} alt="Evidencia expandida" className="max-w-full max-h-full object-contain rounded-2xl shadow-2xl" />
                </div>,
                document.body
            )}
        </div>
    );
};

export default function FeedbackResolucion({ solicitud }) {
    const esError = solicitud.estado?.nombre === 'Incorrecta';
    const esAprobada = solicitud.estado?.nombre === 'Respondida' || solicitud.estado?.nombre === 'Verificada';

    const auditoriasOrdenadas = [...(solicitud.auditorias || [])].sort((a, b) => b.id - a.id);
    const ultimaAuditoria = auditoriasOrdenadas.find((a) => !a.motivo_reporte?.toUpperCase().includes('AUTOMÁTICAMENTE'));

    const tieneObservacion = solicitud.observaciones_vendedor && solicitud.observaciones_vendedor.trim() !== '';
    const tieneFeedback = (esError || esAprobada) && ultimaAuditoria?.motivo_reporte;

    let evidenciaAdmin = solicitud.evidencia_respuesta_path;
    if (!evidenciaAdmin && ultimaAuditoria?.datos_snapshot) {
        const snap = typeof ultimaAuditoria.datos_snapshot === 'string'
            ? JSON.parse(ultimaAuditoria.datos_snapshot)
            : ultimaAuditoria.datos_snapshot;
        if (snap?.evidencia_respuesta_path) evidenciaAdmin = snap.evidencia_respuesta_path;
    }

    if (!tieneObservacion && !tieneFeedback && !evidenciaAdmin) return null;

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
                        <p className="text-xs font-bold theme-text-main italic leading-snug m-0 break-words">{solicitud.observaciones_vendedor}</p>
                    </div>
                </div>
            )}

            {(esError || esAprobada) && (ultimaAuditoria?.motivo_reporte || evidenciaAdmin) && (
                <div className={`p-3 rounded-2xl border flex flex-col gap-2 ${colorContenedor}`}>
                    <div className="flex items-start gap-2 min-w-0">
                        <Icono className={`w-4 h-4 shrink-0 mt-0.5 ${colorIcono}`} />
                        <div className="flex-1 min-w-0">
                            <p className={`text-[9px] font-black uppercase tracking-widest mb-0.5 m-0 ${colorTexto}`}>
                                {titulo}
                            </p>
                            {ultimaAuditoria?.motivo_reporte && (
                                <p className="text-xs font-bold italic leading-tight theme-text-main m-0 break-words">
                                    {ultimaAuditoria.motivo_reporte}
                                </p>
                            )}
                        </div>
                    </div>
                    {evidenciaAdmin && (
                        <div className="mt-1">
                            <VisorImagenHover path={evidenciaAdmin} />
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
