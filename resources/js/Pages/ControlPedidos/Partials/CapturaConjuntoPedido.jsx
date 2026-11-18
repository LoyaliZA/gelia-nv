import React from 'react';
import { Image } from 'lucide-react';
import { MiniaturaDocumento, esPdf } from './ModalVistaPreviaDocumento';

/** Captura vigente del pedido completo. Se monta al final del seguimiento, no en la tarjeta de respuesta. */
export default function CapturaConjuntoPedido({ pedido, tarea, onVer }) {
    const revision = pedido?.solicitud_preparacion?.revision_disponibilidad;
    const documentos = (pedido?.documentos || []).filter((d) => d.tipo === 'evidencia_condicion' && d.url && d.activo !== false && !String(d.comentario || '').startsWith('Evidencia petición'));
    const capturas = documentos.filter((d) => !d.relacion_tipo || d.relacion_tipo === 'revision_general');
    let vigentes = capturas.filter((d) => !revision?.evidencia_actual_ids || revision.evidencia_actual_ids.includes(d.id));
    let anteriores = capturas.filter((d) => revision?.evidencia_actual_ids && !revision.evidencia_actual_ids.includes(d.id));
    if (!vigentes.length) {
        const deTarea = (tarea?.respuesta?.documentos || []).filter((d) => !d.producto_id && d.url);
        vigentes = deTarea.filter((d) => !d.anterior && !esPdf(d));
        anteriores = deTarea.filter((d) => d.anterior);
    }
    if (!vigentes.length && !anteriores.length) return null;
    return (
        <section className="registrar-tienda-panel tienda-respuesta-captura" aria-label="Captura del pedido">
            <div className="flex flex-wrap items-center gap-2">
                <Image size={18} aria-hidden />
                <span>Captura del pedido</span>
                <div className="flex flex-wrap gap-2">{vigentes.map((doc, i) => <MiniaturaDocumento key={doc.id || i} documento={doc} onVer={() => onVer?.(vigentes, i)} className="tienda-respuesta-miniatura" />)}</div>
            </div>
            {anteriores.length > 0 && <details className="tienda-respuesta-notas"><summary className="cursor-pointer text-sm theme-text-muted">Capturas anteriores ({anteriores.length})</summary><div className="flex flex-wrap gap-2 mt-2">{anteriores.map((doc, i) => <MiniaturaDocumento key={doc.id || i} documento={doc} onVer={() => onVer?.(anteriores, i)} className="tienda-respuesta-miniatura" />)}</div></details>}
        </section>
    );
}
