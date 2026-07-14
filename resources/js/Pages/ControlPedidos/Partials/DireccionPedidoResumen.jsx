import React from 'react';
import { MapPin, User, Phone } from 'lucide-react';

/**
 * Resumen estructurado de dirección (snapshot, listado verificado o excepción manual).
 * Acepta snapshot snake_case de Laravel o item del API de direcciones.
 */
export default function DireccionPedidoResumen({
    direccion = null,
    domicilioLegacy = null,
    codigoPostal = null,
    codigoDireccion = null,
    className = '',
    compact = false,
}) {
    const d = direccion || {};
    const tieneEstructura = Boolean(
        d.nombre_destinatario || d.calle || d.colonia || d.direccion_resumida
    );

    if (!tieneEstructura && !domicilioLegacy) {
        return (
            <p className={`text-sm theme-text-muted m-0 ${className}`}>Sin domicilio capturado</p>
        );
    }

    const lineaCalle = [
        d.calle,
        d.numero_exterior ? `Ext. ${d.numero_exterior}` : null,
        d.numero_interior ? `Int. ${d.numero_interior}` : null,
    ].filter(Boolean).join(' ');

    const cp = d.codigo_postal || codigoPostal;
    const lugar = [d.colonia, d.municipio || d.ciudad, d.estado, d.pais].filter(Boolean).join(', ');
    const textoLegacy = domicilioLegacy || d.domicilio_legacy || d.direccion_resumida;

    return (
        <div className={`rounded-xl border theme-border theme-element p-3 space-y-2 ${className}`}>
            {codigoDireccion && (
                <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-widest border theme-border theme-text-main">
                    {codigoDireccion}
                </span>
            )}
            {(d.nombre_destinatario || d.telefono_destinatario) && (
                <div className="flex flex-wrap gap-3 text-sm">
                    {d.nombre_destinatario && (
                        <span className="inline-flex items-center gap-1.5 font-bold theme-text-main">
                            <User className="w-3.5 h-3.5 theme-text-muted" />
                            {d.nombre_destinatario}
                            {d.etiqueta ? ` · ${d.etiqueta}` : ''}
                            {d.es_principal ? ' ★' : ''}
                        </span>
                    )}
                    {d.telefono_destinatario && (
                        <span className="inline-flex items-center gap-1.5 theme-text-muted">
                            <Phone className="w-3.5 h-3.5" />
                            {d.telefono_destinatario}
                        </span>
                    )}
                </div>
            )}
            <div className="flex items-start gap-2 text-sm theme-text-main">
                <MapPin className="w-3.5 h-3.5 theme-text-muted shrink-0 mt-0.5" />
                <div className="min-w-0 space-y-0.5">
                    {lineaCalle && <p className="m-0 font-semibold">{lineaCalle}</p>}
                    {lugar && <p className="m-0 theme-text-muted">{lugar}</p>}
                    {cp && <p className="m-0 theme-text-muted">C.P. {cp}</p>}
                    {!lineaCalle && textoLegacy && (
                        <p className="m-0 font-semibold">{textoLegacy}</p>
                    )}
                    {!compact && d.referencias && (
                        <p className="m-0 text-xs theme-text-muted">Ref: {d.referencias}</p>
                    )}
                    {!compact && d.indicaciones_entrega && (
                        <p className="m-0 text-xs theme-text-muted">Indicaciones: {d.indicaciones_entrega}</p>
                    )}
                </div>
            </div>
        </div>
    );
}

export const LABELS_ESTADO_SOLICITUD = {
    pending: 'Pendiente',
    verified: 'Identidad verificada',
    rejected: 'Rechazada',
    requires_correction: 'Requiere corrección',
    possible_duplicate: 'Posible duplicado',
    identity_review_required: 'Revisión de identidad',
    approved: 'Aprobada',
};

export const LABELS_ACCION_SOLICITUD = {
    register_first_address: 'Primera dirección',
    add_address: 'Dirección adicional',
    update_address: 'Actualización',
};

export function labelEstadoSolicitud(estado) {
    return LABELS_ESTADO_SOLICITUD[estado] || estado || '—';
}

export function labelAccionSolicitud(accion) {
    return LABELS_ACCION_SOLICITUD[accion] || accion || '—';
}
