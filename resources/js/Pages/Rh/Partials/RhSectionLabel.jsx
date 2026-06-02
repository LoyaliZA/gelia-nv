import React from 'react';

/**
 * Etiqueta de sección RH — legible sobre fondos de marca (fucsia) y en claro/oscuro.
 * Usar en sub-navegación y subcabeceras fuera de tarjetas con superficie sólida.
 */
export default function RhSectionLabel({ children, className = '', as: Tag = 'span' }) {
    return (
        <Tag className={`gelia-rh-section-label ${className}`.trim()}>{children}</Tag>
    );
}
