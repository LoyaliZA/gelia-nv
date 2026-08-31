import React from 'react';
import {
    CATEGORIA_ADMIN,
    CATEGORIA_COBERTURA,
    CATEGORIA_VALIDACION,
    badgeAdminEstado,
    badgeCobertura,
    badgeCoberturaExhibicion,
    badgeValidacionEstado,
    fmtFechaHora,
    labelAdminEstado,
    labelConteoExhibicionesRevisadas,
    labelEstadoCobertura,
    labelRevisionEstado,
} from './pagosPedidosStyles';

const ETIQUETA_CATEGORIA =
    'text-[11px] md:text-xs font-semibold uppercase tracking-wide theme-text-muted leading-none';

export function EtiquetaEstadoCategorizado({ categoria, valor, badgeClass, className = '' }) {
    return (
        <div className={`flex flex-col gap-1.5 min-w-0 ${className}`.trim()}>
            <span className={ETIQUETA_CATEGORIA}>{categoria}</span>
            <span className={`${badgeClass} w-fit max-w-full`}>{valor}</span>
        </div>
    );
}

export function EtiquetaCoberturaPedido({ pedido, compacto = false, className = '' }) {
    const estado = typeof pedido === 'string' ? pedido : pedido?.estado_cobertura;
    const valor = labelEstadoCobertura(pedido);
    const badgeClass = badgeCobertura(estado);

    if (compacto) {
        return (
            <span className={`${badgeClass} w-fit max-w-full ${className}`.trim()}>
                {valor}
            </span>
        );
    }

    return (
        <EtiquetaEstadoCategorizado
            categoria={CATEGORIA_COBERTURA}
            valor={valor}
            badgeClass={badgeClass}
            className={className}
        />
    );
}

export function EtiquetaCoberturaExhibicion({ ex, exhibiciones = [], className = '' }) {
    const cobertura = badgeCoberturaExhibicion(ex, exhibiciones);

    return (
        <EtiquetaEstadoCategorizado
            categoria={CATEGORIA_COBERTURA}
            valor={cobertura.label}
            badgeClass={cobertura.badge}
            className={className}
        />
    );
}

export function EtiquetaValidacionPago({ estado, valor, className = '' }) {
    return (
        <EtiquetaEstadoCategorizado
            categoria={CATEGORIA_VALIDACION}
            valor={valor ?? labelRevisionEstado(estado)}
            badgeClass={badgeValidacionEstado(estado)}
            className={className}
        />
    );
}

export function EtiquetaRevisionAdmin({
    resumen,
    resumenLabel,
    exhibicionesRevisadas,
    exhibicionesTotal,
    revisadoPor,
    revisadoAt,
    inline = false,
    className = '',
}) {
    if (!resumen) return null;
    const valor = resumenLabel || labelAdminEstado(resumen);
    const conteo = labelConteoExhibicionesRevisadas(exhibicionesRevisadas, exhibicionesTotal);
    const revisionCerrada = resumen === 'confirmado' || resumen === 'con_error';
    const nombreRevisor = typeof revisadoPor === 'string' ? revisadoPor : revisadoPor?.name;
    const metaRevision = revisionCerrada && (nombreRevisor || revisadoAt)
        ? [
            nombreRevisor ? `Por ${nombreRevisor}` : null,
            revisadoAt ? fmtFechaHora(revisadoAt) : null,
        ].filter(Boolean).join(' · ')
        : null;

    if (inline) {
        return (
            <div className={`flex flex-col gap-1 min-w-0 ${className}`.trim()}>
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <span className="text-xs font-semibold theme-text-muted leading-snug">{CATEGORIA_ADMIN}</span>
                    <span className={badgeAdminEstado(resumen)}>{valor}</span>
                </div>
                {conteo && (
                    <span className="text-xs font-medium theme-text-muted leading-relaxed">{conteo}</span>
                )}
                {metaRevision && (
                    <span className="text-xs theme-text-muted leading-relaxed">{metaRevision}</span>
                )}
            </div>
        );
    }

    return (
        <div className={`flex flex-col gap-1.5 min-w-0 ${className}`.trim()}>
            <EtiquetaEstadoCategorizado
                categoria={CATEGORIA_ADMIN}
                valor={valor}
                badgeClass={badgeAdminEstado(resumen)}
            />
            {conteo && (
                <span className="text-xs font-medium theme-text-muted leading-relaxed">{conteo}</span>
            )}
        </div>
    );
}
