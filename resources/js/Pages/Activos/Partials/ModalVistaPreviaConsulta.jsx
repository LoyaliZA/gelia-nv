import React from 'react';
import { Link } from '@inertiajs/react';
import { Edit2, ExternalLink, X } from 'lucide-react';
import { MODAL_OVERLAY_CLASS, MODAL_SHELL_CLASS, renderActivosModal } from './activosFormStyles';

export default function ModalVistaPreviaConsulta({
    abierto,
    onCerrar,
    consultaUrl,
    puedeEditar = false,
    urlEditar = null,
}) {
    if (!abierto || !consultaUrl) return null;

    return renderActivosModal(
        <div className={`${MODAL_OVERLAY_CLASS} z-[550]`} onClick={onCerrar}>
            <div
                className={`${MODAL_SHELL_CLASS} w-full max-w-md max-h-[90dvh] flex flex-col p-0 overflow-hidden`}
                onClick={(e) => e.stopPropagation()}
            >
                <div className="flex items-center justify-between p-4 border-b theme-border shrink-0">
                    <div>
                        <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Vista previa consulta</h2>
                        <p className="text-[10px] theme-text-muted m-0 mt-0.5">Así verá quien escanee el QR</p>
                    </div>
                    <button type="button" onClick={onCerrar} className="p-2 rounded-xl theme-element border theme-border" aria-label="Cerrar">
                        <X className="w-4 h-4" />
                    </button>
                </div>

                <div className="flex-1 min-h-0 bg-zinc-100 dark:bg-zinc-900">
                    <iframe
                        src={consultaUrl}
                        title="Vista previa consulta pública"
                        className="w-full h-[min(520px,60dvh)] border-0"
                    />
                </div>

                <div className="p-4 border-t theme-border space-y-3 shrink-0">
                    <a
                        href={consultaUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex items-center justify-center gap-2 w-full min-h-[40px] rounded-xl theme-element border theme-border text-[10px] font-black uppercase theme-text-main hover:opacity-90"
                    >
                        <ExternalLink className="w-3.5 h-3.5" />
                        Abrir en pestaña nueva
                    </a>
                    {puedeEditar && urlEditar && (
                        <Link
                            href={urlEditar}
                            className="flex items-center justify-center gap-2 w-full min-h-[44px] rounded-xl text-white font-black uppercase text-[10px]"
                            style={{ backgroundColor: 'var(--color-primario)' }}
                        >
                            <Edit2 className="w-3.5 h-3.5" />
                            Ir a editar
                        </Link>
                    )}
                </div>
            </div>
        </div>,
    );
}
