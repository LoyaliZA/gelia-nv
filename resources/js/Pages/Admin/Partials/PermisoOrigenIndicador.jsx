import React from 'react';
import { Building2, UserPen, Lock, UserCheck } from 'lucide-react';
import { resolverOrigenPermiso } from '../../../utils/permisos';

const ICONOS = {
    plantilla: Building2,
    custom: UserPen,
    sistema: Lock,
    otro: UserCheck,
};

const CLASES = {
    plantilla: 'text-blue-500',
    custom: 'text-orange-500',
    sistema: 'text-teal-500',
    otro: 'text-violet-500',
};

export default function PermisoOrigenIndicador({
    meta,
    isDePlantilla = false,
    plantillaNombre = '',
    usuarioActualId = null,
    className = '',
}) {
    const { tipo, tooltip } = resolverOrigenPermiso(
        meta,
        isDePlantilla,
        plantillaNombre,
        usuarioActualId,
    );
    const Icon = ICONOS[tipo] || UserPen;

    return (
        <span
            className={`inline-flex shrink-0 ${className}`}
            title={tooltip}
            aria-label={tooltip}
        >
            <Icon className={`w-3.5 h-3.5 ${CLASES[tipo] || CLASES.custom}`} aria-hidden />
        </span>
    );
}

export function LeyendaOrigenPermisos() {
    const items = [
        { Icon: Building2, label: 'Plantilla', clase: CLASES.plantilla },
        { Icon: UserPen, label: 'Manual', clase: CLASES.custom },
        { Icon: UserCheck, label: 'Otro asignador', clase: CLASES.otro },
        { Icon: Lock, label: 'Sistema', clase: CLASES.sistema },
    ];

    return (
        <div className="flex flex-wrap gap-x-4 gap-y-1.5 text-[10px] font-bold uppercase tracking-widest theme-text-muted">
            {items.map(({ Icon, label, clase }) => (
                <span key={label} className="inline-flex items-center gap-1.5" title={label}>
                    <Icon className={`w-3.5 h-3.5 ${clase}`} aria-hidden />
                    {label}
                </span>
            ))}
        </div>
    );
}
