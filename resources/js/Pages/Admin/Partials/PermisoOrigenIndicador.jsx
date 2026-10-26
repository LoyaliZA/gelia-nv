import React from 'react';
import { Lock } from 'lucide-react';
import { resolverOrigenPermiso } from '../../../utils/permisos';

const ETIQUETAS = {
    plantilla: 'Plantilla',
    custom: 'Directo',
    sistema: 'Sistema',
    otro: 'Otra persona',
};

const CLASES = {
    plantilla: 'theme-text-primario',
    custom: 'theme-text-main',
    sistema: 'theme-text-muted',
    otro: 'theme-text-aviso',
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
    const etiqueta = ETIQUETAS[tipo] || ETIQUETAS.custom;

    return (
        <span
            className={`usuarios-permiso-badge ${CLASES[tipo] || CLASES.custom} ${className}`}
            title={tooltip}
            aria-label={tooltip}
        >
            {etiqueta}
        </span>
    );
}

export function IndicadorPermisoProtegido({ motivo = 'Protegido contra modificaciones' }) {
    return (
        <span className="usuarios-permiso-bloqueo" title={motivo}>
            <Lock className="w-3.5 h-3.5" aria-hidden />
            <span className="sr-only">{motivo}</span>
        </span>
    );
}

export function LeyendaOrigenPermisos() {
    const items = [
        { tipo: 'plantilla', label: 'Plantilla' },
        { tipo: 'custom', label: 'Directo' },
        { tipo: 'otro', label: 'Otra persona' },
        { tipo: 'sistema', label: 'Sistema' },
    ];

    return (
        <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-sm theme-text-muted">
            {items.map(({ tipo, label }) => (
                <span key={tipo} className={`usuarios-permiso-badge ${CLASES[tipo]}`}>{label}</span>
            ))}
            <span className="inline-flex items-center gap-1">
                <Lock className="w-3.5 h-3.5 theme-text-aviso" aria-hidden />
                Protegido
            </span>
        </div>
    );
}
