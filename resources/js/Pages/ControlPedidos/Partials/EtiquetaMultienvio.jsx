import React from 'react';
import { Layers } from 'lucide-react';

/**
 * Texto de la píldora de grupo. `n_total` cuenta solo los envíos visibles para quien consulta:
 * con `parcial` no se muestra «de N» para no sugerir un total que no es el real.
 */
export function textoMultienvio(multienvio) {
    if (!multienvio || typeof multienvio !== 'object') return null;
    const orden = Number(multienvio.orden);
    if (Number.isInteger(orden) && orden > 0) {
        return multienvio.parcial || !multienvio.n_total ? `Envío ${orden}` : `Envío ${orden} de ${multienvio.n_total}`;
    }
    if (multienvio.estado === 'configurando') return 'Multienvío en configuración';
    if (multienvio.estado === 'regularizacion_pendiente') return 'Multienvío por regularizar';
    if (multienvio.estado === 'activo') return 'Multienvío';

    return null;
}

/** Firma comparable del resumen de grupo para el refresco en vivo de los modales abiertos. */
export function firmaMultienvio(pedido) {
    const grupo = pedido?.multienvio;
    if (!grupo || typeof grupo !== 'object') return '';

    return JSON.stringify([
        grupo.id, grupo.orden, grupo.n_total, grupo.estado, Boolean(grupo.parcial),
        (grupo.hermanos || []).map((h) => [h.id, h.orden, h.folio_remision, h.estatus_fase, h.pago_validado, h.tiene_remision, h.tiene_guia]),
    ]);
}

/** Píldora de grupo de Multienvío: icono + texto, tono neutral; no cuenta contra el tope de etiquetas del listado. */
export default function EtiquetaMultienvio({ multienvio, className = '' }) {
    const texto = textoMultienvio(multienvio);
    if (!texto) return null;

    return (
        <span
            data-etiqueta-multienvio
            className={`inline-flex items-center gap-1 rounded-md border theme-border theme-element px-2 py-0.5 text-xs font-bold theme-text-main ${className}`}
            title="Multienvío: cada envío tiene su propio pedido, pago y remisión"
        >
            <Layers className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
            {texto}
        </span>
    );
}
