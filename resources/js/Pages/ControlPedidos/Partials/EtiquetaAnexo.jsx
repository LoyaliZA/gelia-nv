import React from 'react';
import { PackagePlus } from 'lucide-react';

/** Un anexo extraordinario guarda su relación con el pedido principal en `complemento_nacional`. */
export function datosAnexo(pedido) {
    const solicitud = pedido?.solicitud_preparacion || pedido?.solicitud_preparacion_json;
    const anexo = solicitud?.complemento_nacional;
    if (!anexo || typeof anexo !== 'object' || !anexo.pedido_origen_id) return null;

    return { principalId: anexo.pedido_origen_id, folioPrincipal: anexo.pedido_origen_folio || null };
}

/**
 * Distingue un anexo de la consulta original en cualquier bandeja: icono, texto y borde propios.
 * No depende solo del color.
 */
export default function EtiquetaAnexo({ pedido, className = '' }) {
    const anexo = datosAnexo(pedido);
    if (!anexo) return null;

    return (
        <span
            data-etiqueta-anexo
            className={`inline-flex items-center gap-1 rounded-md border-2 border-dashed border-amber-500 bg-amber-500/10 px-2 py-0.5 text-xs font-bold theme-text-main ${className}`}
            title="Anexo extraordinario: minipedido relacionado con un pedido ya pagado"
        >
            <PackagePlus className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
            Anexo{anexo.folioPrincipal ? ` de ${anexo.folioPrincipal}` : ''}
        </span>
    );
}
