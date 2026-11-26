import React from 'react';
import { FileText, X } from 'lucide-react';

/**
 * Cabecera de modal del workspace Solicitudes: el icono y el tono anuncian la acción;
 * el título confirma.
 */
export default function CabeceraFlujoSolicitud({
    icono: Icono = FileText,
    tono = 'primario',
    titulo,
    subtitulo,
    closeLabel = 'Cerrar',
    disabled = false,
}) {
    return (
        <header className="gelia-workflow-header">
            <span className="gelia-flujo-icono" data-tono={tono} aria-hidden="true">
                <Icono className="h-5 w-5" />
            </span>
            <div className="gelia-workflow-header-copy min-w-0 flex-1">
                <h2 className="gelia-solicitudes-titulo m-0">{titulo}</h2>
                {subtitulo ? <p className="m-0 mt-1 text-sm theme-text-muted">{subtitulo}</p> : null}
            </div>
            <button
                type="button"
                data-dialog-close
                disabled={disabled}
                className="gelia-workflow-close"
                aria-label={closeLabel}
            >
                <X className="w-5 h-5" aria-hidden="true" />
            </button>
        </header>
    );
}
