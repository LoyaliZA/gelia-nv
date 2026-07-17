import React from 'react';

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

    const cp = d.codigo_postal || codigoPostal;
    const ciudad = d.ciudad || d.municipio || '';
    const numero = [d.numero_exterior, d.numero_interior ? `Int. ${d.numero_interior}` : null]
        .filter(Boolean)
        .join(' / ');
    const textoLegacy = domicilioLegacy || d.domicilio_legacy || d.direccion_resumida;

    if (compact && !tieneEstructura) {
        return (
            <p className={`text-sm theme-text-main m-0 ${className}`}>{textoLegacy || '—'}</p>
        );
    }

    const Campo = ({ label, valor }) => (
        <div>
            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0 mb-0.5">{label}</p>
            <p className="text-sm font-bold theme-text-main m-0 break-words">{valor || '—'}</p>
        </div>
    );

    if (!tieneEstructura) {
        return (
            <div className={`rounded-xl border theme-border theme-element p-4 ${className}`}>
                <Campo label="Domicilio" valor={textoLegacy} />
                {cp && <div className="mt-3"><Campo label="C.P." valor={cp} /></div>}
            </div>
        );
    }

    return (
        <div className={`rounded-xl border theme-border theme-element p-4 space-y-3 ${className}`}>
            {codigoDireccion && (
                <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-widest border theme-border theme-text-main">
                    {codigoDireccion}
                </span>
            )}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <Campo label="Destinatario" valor={d.nombre_destinatario} />
                <Campo label="Teléfono" valor={d.telefono_destinatario} />
                <Campo label="Calle" valor={d.calle} />
                <Campo label="Número" valor={numero} />
                <Campo label="Colonia" valor={d.colonia} />
                <Campo label="Ciudad" valor={ciudad} />
                <Campo label="Estado" valor={d.estado} />
                <Campo label="C.P." valor={cp} />
            </div>
            {!compact && (d.referencias || d.indicaciones_entrega) && (
                <div className="grid grid-cols-1 gap-3 pt-1 border-t theme-border">
                    {d.referencias && <Campo label="Referencias" valor={d.referencias} />}
                    {d.indicaciones_entrega && <Campo label="Indicaciones" valor={d.indicaciones_entrega} />}
                </div>
            )}
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
    register_first_address: 'Registrar primera dirección',
    add_address: 'Añadir dirección adicional',
    update_address: 'Actualizar dirección',
};

export function labelEstadoSolicitud(estado) {
    return LABELS_ESTADO_SOLICITUD[estado] || estado || '—';
}

export function labelAccionSolicitud(accion) {
    return LABELS_ACCION_SOLICITUD[accion] || accion || '—';
}
