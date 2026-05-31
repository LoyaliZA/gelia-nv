import React from 'react';
import { CornerDownRight, X } from 'lucide-react';
import { fragmentoRespuesta, nombreRemitenteRespuesta } from '@/utils/mensajeriaRespuesta';

export default function BarraRespuestaMensaje({ mensaje, onCancelar }) {
    if (!mensaje) return null;

    const nombre = nombreRemitenteRespuesta(mensaje);
    const fragmento = fragmentoRespuesta(mensaje);

    return (
        <div className="gelia-barra-respuesta flex items-stretch gap-2 px-3 py-2 border-b theme-border theme-surface-solid shrink-0">
            <CornerDownRight className="w-4 h-4 shrink-0 mt-0.5 text-[var(--color-primario)]" aria-hidden />
            <div className="gelia-barra-respuesta__cuerpo min-w-0 flex-1 border-l-2 border-[var(--color-primario)] pl-2">
                <p className="text-[11px] font-black m-0 truncate text-[var(--color-primario)]">
                    {nombre}
                </p>
                <p className="text-xs theme-text-muted m-0 mt-0.5 truncate">{fragmento}</p>
            </div>
            <button
                type="button"
                onClick={onCancelar}
                className="p-1.5 rounded-full theme-text-muted hover:theme-text-main shrink-0 self-center"
                aria-label="Cancelar respuesta"
            >
                <X className="w-4 h-4" />
            </button>
        </div>
    );
}
