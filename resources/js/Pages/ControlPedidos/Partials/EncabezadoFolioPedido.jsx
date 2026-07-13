import React from 'react';

export default function EncabezadoFolioPedido({ pedido, size = 'md', className = '' }) {
    const folioRemision = pedido?.folio_remision || '—';
    const folioInterno = pedido?.folio;
    const sizeClass = size === 'lg' ? 'text-xl md:text-2xl' : size === 'sm' ? 'text-sm' : 'text-base';

    return (
        <div className={className}>
            <p className={`${sizeClass} font-black theme-text-main uppercase italic m-0 leading-tight`}>
                {folioRemision}
            </p>
            {folioInterno && (
                <p className="text-[10px] theme-text-muted font-bold m-0 mt-0.5 opacity-60">
                    {folioInterno}
                </p>
            )}
        </div>
    );
}
