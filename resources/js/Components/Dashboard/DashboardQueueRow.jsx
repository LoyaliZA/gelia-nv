import React from 'react';
import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { GELIA_ESTADO_VIVO_TONO } from '../../utils/geliaTheme';

/**
 * Fila densa tipo cola operativa (desktop) / tarjeta (móvil vía CSS contenedor).
 */
export default function DashboardQueueRow({
    href,
    estadoIcon: EstadoIcon,
    estadoTono = 'neutro',
    primary,
    secondary,
    trailing,
    trailingMuted,
    className = '',
    extraClass = '',
}) {
    const tonoClass = GELIA_ESTADO_VIVO_TONO[estadoTono] || GELIA_ESTADO_VIVO_TONO.neutro;
    const rowClass = [
        'dashboard-queue-row',
        'theme-element border theme-border',
        extraClass,
        className,
    ]
        .filter(Boolean)
        .join(' ');

    const inner = (
        <>
            {EstadoIcon && (
                <span
                    className={`dashboard-queue-row__estado gelia-estado-vivo gelia-estado-vivo--compacto ${tonoClass}`}
                    aria-hidden
                >
                    <EstadoIcon className="w-3.5 h-3.5" />
                </span>
            )}
            <div className="dashboard-queue-row__text min-w-0 flex-1">
                {primary && <p className="dashboard-queue-row__primary m-0 truncate">{primary}</p>}
                {secondary && <p className="dashboard-queue-row__secondary theme-text-muted m-0 truncate">{secondary}</p>}
            </div>
            <div className="dashboard-queue-row__trailing shrink-0 text-right">
                {trailing && <p className="dashboard-queue-row__metric m-0 tabular-nums">{trailing}</p>}
                {trailingMuted && <p className="dashboard-queue-row__muted m-0 truncate">{trailingMuted}</p>}
            </div>
            {href && <ChevronRight className="w-4 h-4 shrink-0 theme-text-muted dashboard-queue-row__chevron" aria-hidden />}
        </>
    );

    if (href) {
        return (
            <Link href={href} className={`${rowClass} dashboard-queue-row--link outline-none`}>
                {inner}
            </Link>
        );
    }

    return <div className={rowClass}>{inner}</div>;
}

/** Bloque métrica unificado dentro de widgets. */
export function DashboardStatBlock({ label, value, valueClassName = '' }) {
    return (
        <div className="dashboard-stat-block theme-element border theme-border">
            <p className="dashboard-stat-block__label m-0">{label}</p>
            <p className={`dashboard-stat-block__value m-0 tabular-nums ${valueClassName}`.trim()}>{value}</p>
        </div>
    );
}
