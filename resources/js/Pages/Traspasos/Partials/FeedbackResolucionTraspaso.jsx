import React, { useState } from 'react';
import { createPortal } from 'react-dom';
import { CheckCircle2, AlertOctagon, AlertTriangle, FileText, Camera, X } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '../../../utils/geliaTheme';
import {
    BTN_CERRAR_MODAL,
    META_BLOQUE_EXITO,
    MODAL_HEADER,
    PANEL_INCIDENCIA,
    PANEL_RESPUESTA_EXITO,
    PANEL_RESPUESTA_PELIGRO,
    TITULO_SECCION_AVISO,
    TITULO_SECCION_EXITO,
    TITULO_SECCION_PELIGRO,
} from './traspasosStyles';

function urlEvidencia(traspaso) {
    if (!traspaso?.tiene_evidencia_respuesta && !traspaso?.evidencia_respuesta_path) return null;
    return route('traspasos.evidencia', traspaso.id);
}

const ESTADOS_INCIDENCIA = ['regular', 'malo', 'danado', 'sin_existencia'];

function lineasConDetalle(traspaso) {
    const lineas = [];
    for (const p of traspaso.productos || []) {
        for (const rev of p.revisiones || []) {
            if (!ESTADOS_INCIDENCIA.includes(rev.estado_fisico)) continue;
            const paths = rev.evidencia_paths || [];
            lineas.push({
                id: rev.id,
                sku: p.sku,
                descripcion: p.descripcion,
                motivo: (rev.comentario || rev.estado_fisico || '').trim(),
                autor: rev.registrado_por?.name || rev.registradoPor?.name || 'Operación',
                fotos: paths.map((_, i) => route('traspasos.revision_foto', [rev.id, i])),
            });
        }
    }

    return lineas;
}

const VisorEvidenciaHover = ({ url, etiqueta = 'Ver captura' }) => {
    const [isHovered, setIsHovered] = useState(false);
    if (!url) return null;

    return (
        <div
            className={`${META_BLOQUE_EXITO} cursor-pointer hover:border-[color-mix(in_srgb,var(--color-exito)_55%,transparent)] transition-colors`}
            onMouseEnter={() => setIsHovered(true)}
            onMouseLeave={() => setIsHovered(false)}
        >
            <span className={`${TITULO_SECCION_EXITO} m-0 inline-flex items-center gap-1`}>
                <Camera className="w-3 h-3 shrink-0" /> Evidencia
            </span>
            <span className="inline-flex items-center gap-2 mt-0.5">
                <img src={url} className="w-8 h-8 object-cover rounded-lg shadow-sm border border-[color-mix(in_srgb,var(--color-exito)_30%,transparent)]" alt="Miniatura" />
                <span className="text-sm font-black theme-text-main leading-tight">{etiqueta}</span>
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

const VisorFotoDano = ({ url }) => {
    const [isHovered, setIsHovered] = useState(false);
    if (!url) return null;

    return (
        <div
            className="inline-block cursor-pointer"
            onMouseEnter={() => setIsHovered(true)}
            onMouseLeave={() => setIsHovered(false)}
        >
            <img
                src={url}
                alt="Detalle/daño"
                className="w-14 h-14 object-cover rounded-xl border border-[color-mix(in_srgb,var(--color-aviso)_40%,transparent)] shadow-sm"
            />
            {isHovered && createPortal(
                <div className={`${THEME_MODAL_OVERLAY} items-center justify-center pointer-events-none`}>
                    <img src={url} alt="Detalle/daño expandido" className="max-w-full max-h-full object-contain rounded-2xl shadow-2xl" />
                </div>,
                document.body,
            )}
        </div>
    );
};

function BloqueLineaDano({ linea }) {
    return (
        <div className={PANEL_INCIDENCIA}>
            <div className="flex items-start gap-2">
                <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5 theme-text-aviso" />
                <div className="min-w-0 flex-1">
                    <p className={`${TITULO_SECCION_AVISO} m-0 mb-1`}>
                        Detalle/daño · {linea.sku} — {linea.descripcion}
                    </p>
                    <p className="text-[9px] font-bold theme-text-muted m-0 mb-1">
                        Por {linea.autor}
                    </p>
                    {linea.motivo && (
                        <p className="text-xs font-bold italic theme-text-main m-0 whitespace-pre-wrap break-words">
                            {linea.motivo}
                        </p>
                    )}
                    {linea.fotos.length > 0 && (
                        <div className="flex flex-wrap gap-2 mt-2">
                            {linea.fotos.map((url) => (
                                <VisorFotoDano key={url} url={url} />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

function ModalDetallesDano({ traspaso, lineas, onClose }) {
    return createPortal(
        <div className={`${THEME_MODAL_OVERLAY} items-end sm:items-center p-0 sm:p-4`} onClick={onClose}>
            <div
                className={`${THEME_MODAL_SHELL} w-full max-w-lg max-h-[92dvh] flex flex-col rounded-t-3xl sm:rounded-3xl`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className={MODAL_HEADER}>
                    <div className="min-w-0">
                        <h2 className="text-lg font-black italic uppercase theme-text-main m-0 flex items-center gap-2">
                            <AlertTriangle className="w-5 h-5 shrink-0 theme-text-aviso" />
                            Detalles / daños
                        </h2>
                        <p className="text-xs theme-text-muted font-bold mt-1 m-0">{traspaso.folio} · {lineas.length} productos</p>
                    </div>
                    <button type="button" onClick={onClose} className={BTN_CERRAR_MODAL} aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>
                <div className="flex-1 overflow-y-auto p-5 space-y-3 custom-scrollbar">
                    {lineas.map((linea) => (
                        <BloqueLineaDano key={linea.id} linea={linea} />
                    ))}
                </div>
            </div>
        </div>,
        document.body,
    );
}

export default function FeedbackResolucionTraspaso({ traspaso }) {
    const [modalDanos, setModalDanos] = useState(false);
    const estadoNombre = traspaso.estado?.nombre || '';
    const esError = estadoNombre === 'Incorrecta';
    const esAprobada = estadoNombre === 'Respondida' || estadoNombre === 'Verificada';

    const auditoriasOrdenadas = [...(traspaso.auditorias || [])].sort((a, b) => b.id - a.id);
    const ultimaAuditoria = auditoriasOrdenadas.find(
        (a) => !String(a.motivo_reporte || '').includes('Creación de solicitud')
            && !String(a.motivo_reporte || '').includes('CEDIS reportó detalle'),
    );

    const folio = traspaso.folio_traspaso?.trim();
    const motivo = (traspaso.motivo_respuesta || ultimaAuditoria?.motivo_reporte || '').trim();
    const urlEvid = urlEvidencia(traspaso);
    const autor = ultimaAuditoria?.usuario?.name?.trim()
        || traspaso.respondida_por?.name?.trim()
        || traspaso.respondidaPor?.name?.trim();

    const lineasDano = lineasConDetalle(traspaso);
    const tieneDano = lineasDano.length > 0 || Boolean(traspaso.tiene_detalle_dano);
    const muchosDanos = lineasDano.length > 1;

    if (!esError && !esAprobada && !tieneDano) return null;
    if (!folio && !motivo && !urlEvid && lineasDano.length === 0) return null;

    const panelRespuesta = esError ? PANEL_RESPUESTA_PELIGRO : PANEL_RESPUESTA_EXITO;
    const tituloRespuesta = esError ? TITULO_SECCION_PELIGRO : TITULO_SECCION_EXITO;
    const Icono = esError ? AlertOctagon : CheckCircle2;
    const iconoRespuesta = esError ? 'theme-text-peligro' : 'theme-text-exito';
    const titulo = esError
        ? (autor ? `Error reportado por ${autor}` : 'Corrección requerida')
        : (autor ? `Respuesta de ${autor}` : 'Respuesta');

    return (
        <div className="flex flex-col gap-2">
            {(esError || esAprobada) && (folio || motivo || urlEvid) && (
                <div className={panelRespuesta}>
                    <div className="flex items-start gap-2 min-w-0">
                        <Icono className={`w-4 h-4 shrink-0 mt-0.5 ${iconoRespuesta}`} />
                        <div className="flex-1 min-w-0">
                            <p className={`${tituloRespuesta} mb-1.5 m-0`}>
                                {titulo}
                            </p>

                            {(folio || urlEvid) && (
                                <div className="flex flex-wrap items-stretch gap-2">
                                    {folio && (
                                        <div className={META_BLOQUE_EXITO}>
                                            <span className={`${TITULO_SECCION_EXITO} m-0 inline-flex items-center gap-1`}>
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
            )}

            {lineasDano.length === 1 && <BloqueLineaDano linea={lineasDano[0]} />}

            {muchosDanos && (
                <button
                    type="button"
                    onClick={() => setModalDanos(true)}
                    className={`w-full ${PANEL_INCIDENCIA} text-left outline-none hover:border-[color-mix(in_srgb,var(--color-aviso)_55%,transparent)] transition-colors`}
                >
                    <span className="flex items-start gap-2">
                        <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5 theme-text-aviso" />
                        <span className="min-w-0">
                            <span className={`block ${TITULO_SECCION_AVISO} mb-1`}>
                                {lineasDano.length} productos con detalle/daño
                            </span>
                            <span className="block text-xs font-bold theme-text-main">
                                Toca para ver el detalle completo
                            </span>
                        </span>
                    </span>
                </button>
            )}

            {modalDanos && (
                <ModalDetallesDano
                    traspaso={traspaso}
                    lineas={lineasDano}
                    onClose={() => setModalDanos(false)}
                />
            )}
        </div>
    );
}
