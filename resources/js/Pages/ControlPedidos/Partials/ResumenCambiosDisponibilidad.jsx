import React from 'react';
import { CheckCircle2, ArrowRightLeft, PackageMinus, ClipboardList, Image } from 'lucide-react';
import { GELIA_BTN_OUTLINE } from '../../../utils/geliaTheme';

export default function ResumenCambiosDisponibilidad({ solicitud = {}, documentos = [], onVer }) {
    const decisiones = Object.values(solicitud.acciones_disponibilidad || {});
    const retiros = solicitud.retiro_piezas || [];
    const anexos = solicitud.anexo_sin_remision || [];
    if (!decisiones.length && !retiros.length && !anexos.length && !solicitud.reapertura_disponibilidad) return null;
    const iconos = { confirmar: CheckCircle2, retirar: PackageMinus, sustituir: ArrowRightLeft };
    return <section className="registrar-tienda-panel grid gap-3" aria-label="Cambios guardados del pedido">
        <div className="registrar-tienda-seccion-titulo"><ClipboardList size={18} /><h3>Cambios guardados del pedido</h3></div>
        {solicitud.reapertura_disponibilidad && <p className="text-sm theme-text-main m-0">Consulta reabierta por {solicitud.reapertura_disponibilidad.por}. Se conserva la respuesta original para revisar los cambios.</p>}
        {decisiones.map(d => { const Icono = iconos[d.accion] || ClipboardList; return <div key={d.revision_id} className="flex items-start gap-3 border theme-border rounded-xl p-3 theme-element"><Icono size={20} className="shrink-0" /><div className="min-w-0 text-sm theme-text-main"><strong>{d.accion === 'confirmar' ? 'Estado confirmado' : d.accion === 'retirar' ? 'Retirar' : 'Sustituir'}</strong><p className="m-0 break-words">{d.descripcion}{d.sku ? ` · ${d.sku}` : ''}</p>{d.alternativa && <p className="m-0 font-semibold">Nueva pieza: {d.alternativa.descripcion}{d.alternativa.sku ? ` · ${d.alternativa.sku}` : ''} · {anexos.find(a => Number(a.sustituye_revision_id) === Number(d.revision_id))?.respondida_at ? 'Respuesta registrada' : 'Pendiente de respuesta'}</p>}<span className="text-xs theme-text-muted">{d.por}</span></div></div>; })}
        {retiros.map((r,i) => { const ids = [...(r.documentos_piezas || []), ...(r.documentos_peticion || [])].map(d => Number(d.id)); const docs = documentos.filter(d => ids.includes(Number(d.id))); return <div key={`retiro-${i}`} className="grid gap-2"><p className="text-sm theme-text-main m-0"><PackageMinus className="inline mr-2" size={16} />Retiro {r.estado === 'aplicada' ? 'aplicado' : 'pendiente del auxiliar'}: {(r.piezas_solicitadas || r.piezas || []).map(p => p.descripcion).join(', ') || 'Piezas en documento'}</p><details className="text-xs theme-text-muted"><summary className="cursor-pointer">Ver solicitud enviada</summary><p>{r.motivo} · {r.solicitado_por}{r.resultado ? ` · ${r.resultado}` : ''}</p>{docs.length > 0 && onVer && <button type="button" className={GELIA_BTN_OUTLINE} onClick={() => onVer(docs,0)}><Image size={16} />Ver evidencias enviadas ({docs.length})</button>}</details></div>; })}
        {anexos.map((a,i) => { const doc = documentos.find(d => Number(d.id) === Number(a.documento_id)); return <div key={`anexo-${i}`} className="grid gap-2 text-sm theme-text-main"><p className="m-0"><ArrowRightLeft className="inline mr-2" size={16} />{a.sustituye_revision_id ? 'Alternativa' : 'Anexo'}: {a.descripcion || 'Piezas en documento'} · {a.respondida_at ? (a.resultado || 'Respuesta registrada') : 'Pendiente de revisión'}</p>{doc && onVer && <button type="button" className={`${GELIA_BTN_OUTLINE} justify-self-start`} onClick={() => onVer([doc],0)}><Image size={16} />Ver captura enviada</button>}</div>; })}
    </section>;
}
