import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { CheckCircle2, AlertOctagon, MessageSquare } from 'lucide-react';

const VisorImagenHover = ({ path }) => {
    const [isHovered, setIsHovered] = useState(false);
    if (!path) return null;

    const imageUrl = `/storage/${path}`;
    const esPdf = path.toLowerCase().endsWith('.pdf');

    if (esPdf) {
        return (
            <a
                href={imageUrl}
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border bg-black/5 dark:bg-white/5 border-black/10 dark:border-white/10 hover:border-emerald-500 transition-colors"
            >
                <span className="text-[9px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400">Ver PDF</span>
            </a>
        );
    }

    return (
        <div className="inline-block" onMouseEnter={() => setIsHovered(true)} onMouseLeave={() => setIsHovered(false)}>
            <div className="flex items-center gap-2 px-3 py-1.5 rounded-lg border bg-black/5 dark:bg-white/5 border-black/10 dark:border-white/10 cursor-pointer hover:border-emerald-500 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors">
                <img src={imageUrl} className="w-5 h-5 object-cover rounded shadow-sm" alt="Miniatura" />
                <span className="text-[9px] font-black uppercase tracking-widest text-emerald-600 dark:text-emerald-400">Ver Resolución</span>
            </div>
            {isHovered && createPortal(
                <div className="fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 backdrop-blur-md pointer-events-none animate-fade-in p-4 md:p-8">
                    <img src={imageUrl} alt="Evidencia expandida" className="max-w-full max-h-full object-contain rounded-2xl shadow-[0_0_50px_rgba(0,0,0,0.5)]" />
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
    const ultimaAuditoria = auditoriasOrdenadas.find(a => !a.motivo_reporte?.toUpperCase().includes('AUTOMÁTICAMENTE'));

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
    const titulo = esError ? 'Corrección Requerida' : 'Resolución Admin';

    return (
        <div className="mb-4 flex flex-col gap-2">
            {tieneObservacion && (
                <div className="p-3 rounded-2xl border theme-element theme-border flex items-start gap-2 shadow-sm">
                    <MessageSquare className="w-4 h-4 theme-text-muted mt-0.5 shrink-0" />
                    <div>
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-0.5">Nota de Vendedora</p>
                        <p className="text-xs font-bold theme-text-main italic leading-snug m-0">{solicitud.observaciones_vendedor}</p>
                    </div>
                </div>
            )}

            {(esError || esAprobada) && (ultimaAuditoria?.motivo_reporte || evidenciaAdmin) && (
                <div className={`p-3 rounded-2xl border flex flex-col gap-2 shadow-sm ${colorContenedor}`}>
                    <div className="flex items-start gap-2">
                        <Icono className={`w-4 h-4 shrink-0 mt-0.5 ${colorIcono}`} />
                        <div className="flex-1 min-w-0">
                            <p className={`text-[9px] font-black uppercase tracking-widest mb-0.5 ${colorTexto}`}>
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
