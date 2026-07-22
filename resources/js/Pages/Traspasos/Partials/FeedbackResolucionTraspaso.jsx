import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { CheckCircle2, AlertOctagon, FileText, Camera } from 'lucide-react';
import { THEME_MODAL_OVERLAY } from '../../../utils/geliaTheme';

function urlEvidencia(traspaso) {
    if (!traspaso?.tiene_evidencia_respuesta && !traspaso?.evidencia_respuesta_path) return null;
    return route('traspasos.evidencia', traspaso.id);
}

const bloqueMeta = 'inline-flex flex-col gap-0.5 px-3 py-2 rounded-xl border border-emerald-500/40 bg-emerald-500/15 dark:bg-emerald-500/20 min-w-0';

const VisorEvidenciaHover = ({ url }) => {
    const [isHovered, setIsHovered] = useState(false);
    if (!url) return null;

    return (
        <div
            className={`${bloqueMeta} cursor-pointer hover:border-emerald-500 transition-colors`}
            onMouseEnter={() => setIsHovered(true)}
            onMouseLeave={() => setIsHovered(false)}
        >
            <span className="text-[9px] font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-300 m-0 inline-flex items-center gap-1">
                <Camera className="w-3 h-3 shrink-0" /> Evidencia
            </span>
            <span className="inline-flex items-center gap-2 mt-0.5">
                <img src={url} className="w-8 h-8 object-cover rounded-lg shadow-sm border border-emerald-500/30" alt="Miniatura" />
                <span className="text-sm font-black theme-text-main leading-tight">Ver captura</span>
            </span>
            {isHovered && createPortal(
                <div className={`${THEME_MODAL_OVERLAY} items-center justify-center pointer-events-none`}>
                    <img
                        src={url}
                        alt="Evidencia expandida"
                        className="max-w-full max-h-full object-contain rounded-2xl shadow-2xl"
                    />
                </div>,
                document.body,
            )}
        </div>
    );
};

export default function FeedbackResolucionTraspaso({ traspaso }) {
    const estadoNombre = traspaso.estado?.nombre || '';
    const esError = estadoNombre === 'Incorrecta';
    const esAprobada = estadoNombre === 'Respondida' || estadoNombre === 'Verificada';

    const auditoriasOrdenadas = [...(traspaso.auditorias || [])].sort((a, b) => b.id - a.id);
    const ultimaAuditoria = auditoriasOrdenadas.find(
        (a) => !String(a.motivo_reporte || '').includes('Creación de solicitud'),
    );

    const folio = traspaso.folio_traspaso?.trim();
    const motivo = (traspaso.motivo_respuesta || ultimaAuditoria?.motivo_reporte || '').trim();
    const urlEvid = urlEvidencia(traspaso);
    const autor = ultimaAuditoria?.usuario?.name?.trim()
        || traspaso.respondida_por?.name?.trim()
        || traspaso.respondidaPor?.name?.trim();

    if (!esError && !esAprobada) return null;
    if (!folio && !motivo && !urlEvid) return null;

    const colorContenedor = esError ? 'bg-red-500/10 border-red-500/25' : 'bg-emerald-500/10 border-emerald-500/25';
    const Icono = esError ? AlertOctagon : CheckCircle2;
    const colorIcono = esError ? 'text-red-500' : 'text-emerald-500';
    const colorTexto = esError ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400';
    const titulo = esError
        ? (autor ? `Error reportado por ${autor}` : 'Corrección requerida')
        : (autor ? `Respuesta de ${autor}` : 'Respuesta');

    return (
        <div className={`p-3 rounded-2xl border flex flex-col gap-2 ${colorContenedor}`}>
            <div className="flex items-start gap-2 min-w-0">
                <Icono className={`w-4 h-4 shrink-0 mt-0.5 ${colorIcono}`} />
                <div className="flex-1 min-w-0">
                    <p className={`text-[9px] font-black uppercase tracking-widest mb-1.5 m-0 ${colorTexto}`}>
                        {titulo}
                    </p>

                    {(folio || urlEvid) && (
                        <div className="flex flex-wrap items-stretch gap-2">
                            {folio && (
                                <div className={bloqueMeta}>
                                    <span className="text-[9px] font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-300 m-0 inline-flex items-center gap-1">
                                        <FileText className="w-3 h-3 shrink-0" /> Folio de respuesta
                                    </span>
                                    <span className="text-base md:text-lg font-black tracking-wide theme-text-main m-0 break-all leading-tight">
                                        {folio}
                                    </span>
                                </div>
                            )}
                            {urlEvid && <VisorEvidenciaHover url={urlEvid} />}
                        </div>
                    )}

                    {motivo && (
                        <p className="text-xs font-bold italic leading-tight theme-text-main mt-2 mb-0 break-words whitespace-pre-wrap">
                            {motivo}
                        </p>
                    )}
                </div>
            </div>
        </div>
    );
}
