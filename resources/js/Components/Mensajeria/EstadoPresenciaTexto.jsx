import React from 'react';
import { textoPresencia, clasePresencia } from '@/utils/presenciaUsuario';

export default function EstadoPresenciaTexto({ presencia, className = '', compact = false }) {
    const texto = textoPresencia(presencia);
    if (!texto) return null;

    return (
        <p
            className={`gelia-presencia-texto m-0 truncate ${clasePresencia(presencia)} ${compact ? 'text-[10px]' : 'text-xs'} ${className}`}
            title={texto}
        >
            {presencia?.emoji && (
                <span className="gelia-presencia-emoji mr-1" aria-hidden>
                    {presencia.emoji}
                </span>
            )}
            <span>{texto}</span>
        </p>
    );
}
