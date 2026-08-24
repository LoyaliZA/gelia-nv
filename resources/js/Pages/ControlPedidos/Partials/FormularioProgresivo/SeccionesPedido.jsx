import React from 'react';

/** Slot de sección: título orientado a acción + descripción + hijos. */
export function MarcoSeccion({ titulo, descripcion = null, children, errores = null, soloHijos = false }) {
    if (soloHijos) {
        return children;
    }
    return (
        <section className="space-y-4">
            <div>
                <h3 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">{titulo}</h3>
                {descripcion && <p className="text-xs font-bold theme-text-muted m-0 mt-1">{descripcion}</p>}
            </div>
            {errores && (
                <p className="text-xs font-bold m-0" style={{ color: 'var(--color-peligro)' }} role="alert">{errores}</p>
            )}
            {children}
        </section>
    );
}

export function SeccionClientePedido({ children, soloHijos = false, ...rest }) {
    return (
        <MarcoSeccion titulo="Cliente" descripcion="Busque por número o nombre." soloHijos={soloHijos} {...rest}>
            {children}
        </MarcoSeccion>
    );
}

export function SeccionSolicitudInicialPedido({ children, soloHijos = false, ...rest }) {
    return (
        <MarcoSeccion
            titulo="Solicitud inicial"
            descripcion="Tipo de pedido, folio WizeRP, almacén, archivo y destino cuando ya se conozca."
            soloHijos={soloHijos}
            {...rest}
        >
            {children}
        </MarcoSeccion>
    );
}

export function SeccionConsultaPreparacionPedido({ children, esConsultaMercancia = false, soloHijos = false, ...rest }) {
    return (
        <MarcoSeccion
            titulo={esConsultaMercancia ? 'Solicitar revisión a CEDIS' : 'Solicitar pesaje a CEDIS'}
            descripcion="Envíe la consulta. CEDIS responderá con disponibilidad y, si aplica, pesos."
            soloHijos={soloHijos}
            {...rest}
        >
            {children}
        </MarcoSeccion>
    );
}

export function SeccionRespuestaPreparacionPedido({ children, soloHijos = false, ...rest }) {
    return (
        <MarcoSeccion
            titulo="Respuesta de CEDIS"
            descripcion="Revise disponibilidad, pesos, evidencia e incidencias."
            soloHijos={soloHijos}
            {...rest}
        >
            {children}
        </MarcoSeccion>
    );
}

export function SeccionConfirmacionClientePedido({ children, soloHijos = false, ...rest }) {
    return (
        <MarcoSeccion
            titulo="Confirmar con el cliente"
            descripcion="Cierre la consulta cuando el cliente acepte las condiciones."
            soloHijos={soloHijos}
            {...rest}
        >
            {children}
        </MarcoSeccion>
    );
}

export function SeccionCotizacionPedido({ children, soloHijos = false, ...rest }) {
    return (
        <MarcoSeccion
            titulo="Cotización"
            descripcion="Montos, costos de envío y dirección. No aparece antes de cerrar la consulta."
            soloHijos={soloHijos}
            {...rest}
        >
            {children}
        </MarcoSeccion>
    );
}

export function SeccionPagoPedido({ children, soloHijos = false, ...rest }) {
    return (
        <MarcoSeccion
            titulo="Registrar comprobante"
            descripcion="Exhibiciones de pago y cobertura calculada por el servidor."
            soloHijos={soloHijos}
            {...rest}
        >
            {children}
        </MarcoSeccion>
    );
}
