import React from 'react';

export default function ProgresoOperacionTienda({ pasos = [], titulo = 'Progreso' }) {
    if (!pasos.length) return null;

    return (
        <section className="space-y-2">
            <p className="text-xs font-semibold theme-text-muted m-0">{titulo}</p>
            <div className="gelia-tienda-op-stepper" role="list">
                {pasos.map((paso) => (
                    <span
                        key={paso.clave}
                        className="gelia-tienda-op-step"
                        data-hecho={paso.hecho ? 'true' : 'false'}
                        role="listitem"
                    >
                        <span className="gelia-tienda-op-step__dot" aria-hidden />
                        {paso.label}
                    </span>
                ))}
            </div>
        </section>
    );
}
