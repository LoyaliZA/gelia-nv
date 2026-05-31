import React from 'react';
import { ChevronDown } from 'lucide-react';

export default function NuevosMensajesPill({ count, onClick }) {
    if (!count || count <= 0) return null;

    const label = count === 1 ? '1 mensaje nuevo' : `${count} mensajes nuevos`;

    return (
        <button
            type="button"
            onClick={onClick}
            className="gelia-mensajeria-nuevos-pill"
            aria-label={`Ir a ${label}`}
        >
            <ChevronDown className="w-4 h-4 shrink-0" aria-hidden="true" />
            <span>{label}</span>
        </button>
    );
}
