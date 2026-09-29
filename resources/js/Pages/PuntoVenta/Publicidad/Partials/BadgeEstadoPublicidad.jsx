import React from 'react';
import { etiquetaEstadoPublicidad } from '@/utils/estadoPublicidadPdv';

const ESTILOS = {
    activa: 'bg-emerald-600/15 text-emerald-800',
    programada: 'bg-violet-600/15 text-violet-800',
    expirada: 'bg-amber-600/15 text-amber-900',
    deshabilitada: 'bg-zinc-500/15 text-zinc-700',
};

export default function BadgeEstadoPublicidad({ estado }) {
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold ${ESTILOS[estado] || ESTILOS.deshabilitada}`}>
            {etiquetaEstadoPublicidad(estado)}
        </span>
    );
}

export function BadgeAlcancePublicidad({ alcance }) {
    const global = alcance === 'global';
    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold ${global ? 'bg-sky-600/15 text-sky-900' : 'bg-stone-500/15 text-stone-800'}`}>
            {global ? 'Todas las sucursales' : 'Sucursal actual'}
        </span>
    );
}
