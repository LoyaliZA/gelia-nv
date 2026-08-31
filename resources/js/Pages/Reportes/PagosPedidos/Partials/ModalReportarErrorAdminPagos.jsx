import React, { useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { AlertTriangle, X } from 'lucide-react';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL } from '@/utils/geliaTheme';
import { BTN_NEUTRAL, BTN_ERR, GRUPO_ACCIONES } from './pagosPedidosStyles';
import { reportarErrorAdmin } from './accionesAdminPagos';

export default function ModalReportarErrorAdminPagos({
    abierto,
    onCerrar,
    cierreId,
    itemId = null,
    titulo,
    subtitulo,
    onExito,
}) {
    const [comentario, setComentario] = useState('');
    const [archivo, setArchivo] = useState(null);
    const [enviando, setEnviando] = useState(false);
    const [error, setError] = useState(null);
    const inputRef = useRef(null);

    if (!abierto || typeof document === 'undefined') return null;

    const cerrar = () => {
        if (enviando) return;
        setComentario('');
        setArchivo(null);
        setError(null);
        onCerrar();
    };

    const enviar = async (e) => {
        e.preventDefault();
        setError(null);
        if (comentario.trim().length < 10) {
            setError('Describa el error con al menos 10 caracteres.');
            return;
        }
        if (!archivo) {
            setError('Debe adjuntar evidencia (imagen o PDF).');
            return;
        }
        setEnviando(true);
        try {
            const resp = await reportarErrorAdmin({
                cierreId,
                itemId,
                comentario: comentario.trim(),
                evidencia: archivo,
            });
            onExito?.(resp);
            setComentario('');
            setArchivo(null);
            onCerrar();
        } catch (err) {
            setError(err.message || 'Error al reportar.');
        } finally {
            setEnviando(false);
        }
    };

    return createPortal(
        <div
            className={THEME_MODAL_OVERLAY}
            role="dialog"
            aria-modal="true"
            onClick={cerrar}
        >
            <div
                className={`${THEME_MODAL_SHELL} w-full max-w-lg flex flex-col max-h-[min(90dvh,640px)]`}
                onClick={(ev) => ev.stopPropagation()}
            >
                <div className="flex items-start justify-between gap-3 p-5 border-b theme-border shrink-0">
                    <div className="flex items-start gap-2 min-w-0">
                        <AlertTriangle className="w-5 h-5 shrink-0 text-red-600 dark:text-red-400" aria-hidden="true" />
                        <div className="min-w-0">
                            <h2 className="text-base font-bold theme-text-main m-0">{titulo}</h2>
                            {subtitulo && (
                                <p className="text-xs theme-text-muted m-0 mt-1">{subtitulo}</p>
                            )}
                        </div>
                    </div>
                    <button type="button" onClick={cerrar} className="p-1 rounded-full theme-text-muted hover:theme-text-main" aria-label="Cerrar">
                        <X className="w-5 h-5" />
                    </button>
                </div>
                <form onSubmit={enviar} className="flex flex-col flex-1 min-h-0">
                    <div className="p-5 space-y-4 overflow-y-auto flex-1">
                        <label className="block space-y-1.5">
                            <span className="text-xs font-semibold theme-text-main">Comentario del error *</span>
                            <textarea
                                value={comentario}
                                onChange={(ev) => setComentario(ev.target.value)}
                                rows={4}
                                className="w-full rounded-xl border theme-border theme-element theme-text-main text-[13px] p-3 resize-y min-h-[100px]"
                                placeholder="Describa qué encontró en la revisión administrativa…"
                                disabled={enviando}
                            />
                        </label>
                        <label className="block space-y-1.5">
                            <span className="text-xs font-semibold theme-text-main">Evidencia *</span>
                            <input
                                ref={inputRef}
                                type="file"
                                accept="image/jpeg,image/png,image/webp,application/pdf"
                                className="block w-full text-xs theme-text-main file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[color-mix(in_srgb,var(--color-primario)_15%,transparent)] file:text-[var(--color-primario)]"
                                onChange={(ev) => setArchivo(ev.target.files?.[0] || null)}
                                disabled={enviando}
                            />
                            <span className="text-[11px] theme-text-muted">Imagen o PDF, máx. 10 MB.</span>
                        </label>
                        {error && <p className="text-sm text-red-600 m-0">{error}</p>}
                    </div>
                    <div className={`p-5 border-t theme-border ${GRUPO_ACCIONES} justify-end shrink-0`}>
                        <button type="button" className={BTN_NEUTRAL} onClick={cerrar} disabled={enviando}>
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            className={BTN_ERR}
                            disabled={enviando}
                        >
                            {enviando ? 'Enviando…' : 'Reportar error'}
                        </button>
                    </div>
                </form>
            </div>
        </div>,
        document.body,
    );
}
