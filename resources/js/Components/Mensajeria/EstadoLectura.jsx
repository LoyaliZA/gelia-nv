import React from 'react';
import { Check, CheckCheck } from 'lucide-react';

export default function EstadoLectura({ estado, esPropio }) {
    if (!esPropio) return null;

    if (estado === 'leido') {
        return <CheckCheck className="w-3.5 h-3.5 inline ml-1" style={{ color: 'var(--color-primario)' }} />;
    }

    if (estado === 'entregado') {
        return <CheckCheck className="w-3.5 h-3.5 inline ml-1 opacity-50" />;
    }

    return <Check className="w-3.5 h-3.5 inline ml-1 opacity-50" />;
}
