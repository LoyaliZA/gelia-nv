import React from 'react';
import EtiquetaAnexo from './EtiquetaAnexo';
import EtiquetaMultienvio from './EtiquetaMultienvio';

export default function EncabezadoFolioPedido({ pedido, size = 'md', className = '', mostrarInterno = true, etiquetaPrincipal = null, internoCompacto = false }) {
    const folioRemision = pedido?.folio_remision || '—';
    const folioPadre = pedido?.principal?.folio;
    const folioInterno = folioPadre
        ? `${folioPadre} · ${pedido.folio}`
        : pedido?.folio;
    const sizeClass = size === 'lg' ? 'text-xl md:text-2xl' : size === 'sm' ? 'text-sm' : 'text-base';

    return (
        <div className={className}>
            <p className={`${sizeClass} font-bold theme-text-main m-0 leading-tight`}>
                {etiquetaPrincipal ? `${etiquetaPrincipal}: ${folioRemision}` : folioRemision}
            </p>
            {mostrarInterno && folioInterno && (
                <p className="text-xs theme-text-muted font-medium m-0 mt-0.5">
                    {internoCompacto ? folioInterno : `Folio interno GELIA (generado por el sistema): ${folioInterno}`}
                </p>
            )}
            {pedido?.multienvio ? (
                <div className="mt-1 flex flex-wrap gap-1">
                    <EtiquetaAnexo pedido={pedido} />
                    <EtiquetaMultienvio multienvio={pedido.multienvio} />
                </div>
            ) : <EtiquetaAnexo pedido={pedido} className="mt-1" />}
        </div>
    );
}
