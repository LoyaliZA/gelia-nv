import React from 'react';
import { ChevronDown, ChevronUp, ChevronsUpDown } from 'lucide-react';

export default function EncabezadoOrdenable({
    columna,
    etiqueta,
    sortActual,
    dirActual,
    onOrdenar,
    alineacion = 'left',
}) {
    const activo = sortActual === columna;
    const dir = activo ? dirActual : null;

    const alineacionClass = alineacion === 'right' ? 'text-right justify-end' : 'text-left justify-start';

    return (
        <th className={`px-4 py-4 ${alineacion === 'right' ? 'text-right' : ''}`}>
            <button
                type="button"
                onClick={() => onOrdenar(columna)}
                className={`inline-flex items-center gap-1 uppercase tracking-widest hover:theme-text-main transition-colors w-full ${alineacionClass}`}
            >
                <span>{etiqueta}</span>
                {activo && dir === 'asc' ? (
                    <ChevronUp className="w-3 h-3 shrink-0" style={{ color: 'var(--color-primario)' }} />
                ) : activo && dir === 'desc' ? (
                    <ChevronDown className="w-3 h-3 shrink-0" style={{ color: 'var(--color-primario)' }} />
                ) : (
                    <ChevronsUpDown className="w-3 h-3 shrink-0 opacity-40" />
                )}
            </button>
        </th>
    );
}
