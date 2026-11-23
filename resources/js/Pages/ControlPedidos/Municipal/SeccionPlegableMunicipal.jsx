import React, { useId, useState } from 'react';
import { ChevronDown } from 'lucide-react';

/** Sección que nace contraída y se abre a voluntad; `abiertaInicial` la abre cuando ya hay algo que mostrar. */
export default function SeccionPlegableMunicipal({ titulo, icono: Icono, etiqueta, abiertaInicial = false, resumen = null, children }) {
    const [abierta, setAbierta] = useState(Boolean(abiertaInicial));
    const idCuerpo = useId();
    return (
        <section className="registrar-tienda-panel" aria-label={etiqueta || titulo}>
            <button
                type="button"
                className="w-full flex items-center gap-2 text-left bg-transparent border-0 p-0 min-h-[44px] cursor-pointer theme-text-main"
                aria-expanded={abierta}
                aria-controls={idCuerpo}
                onClick={() => setAbierta((valor) => !valor)}
            >
                {Icono && <Icono size={18} aria-hidden="true" />}
                <h3 className="m-0 flex-1">{titulo}</h3>
                {resumen && !abierta && <span className="text-xs theme-text-muted">{resumen}</span>}
                <ChevronDown size={18} aria-hidden="true" className={`transition-transform duration-150 ${abierta ? 'rotate-180' : ''}`} />
            </button>
            <div id={idCuerpo} hidden={!abierta} className={abierta ? 'mt-3' : undefined}>{children}</div>
        </section>
    );
}
