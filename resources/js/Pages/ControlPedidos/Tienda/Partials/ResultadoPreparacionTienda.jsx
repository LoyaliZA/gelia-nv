import React, { useState } from 'react';
import { CheckCircle2, Image } from 'lucide-react';
import ModalVistaPreviaDocumento from '../../Partials/ModalVistaPreviaDocumento';
import { BTN_SECONDARY, formatearFechaNegocio } from '../../Partials/pedidosBmaStyles';

export default function ResultadoPreparacionTienda({ tarea, documentos = [], tiposCaja = [] }) {
    const [vista, setVista] = useState({ abierto: false, documentos: [] });
    const productos = tarea.productos || [];
    const total = productos.reduce((n, p) => n + (Number(p.cantidad_encontrada) || 0), 0);
    const evidencias = documentos.filter((d) => ['evidencia_general', 'evidencia_producto', 'evidencia_incidencia'].includes(d.tipo_evidencia));
    const estados = { bueno: 'Buen estado', regular: 'Con detalle', malo: 'Malo', danado: 'Dañado', sin_existencia: 'Sin existencias' };
    const ver = (docs) => setVista({ abierto: true, documentos: docs });
    return <section className="gelia-tienda-op-resultado" aria-label="Respuesta registrada">
        <div className="flex flex-wrap justify-between items-start gap-3">
            <div><h3 className="text-base font-semibold m-0 inline-flex items-center gap-2"><CheckCircle2 className="w-5 h-5 theme-text-exito" aria-hidden="true" />Respuesta registrada</h3>
                <p className="text-sm theme-text-muted m-0 mt-1">{tarea.responsable?.name ? `Responsable: ${tarea.responsable.name}` : ''}{tarea.atendida_at ? ` · ${formatearFechaNegocio(tarea.atendida_at)}` : ''}</p></div>
            <strong className="text-sm tabular-nums">{total} piezas preparadas</strong>
        </div>
        <dl className="gelia-preparacion-meta">
            {tarea.apartado?.ubicacion && <div><dt>Ubicación del apartado</dt><dd>{tarea.apartado.ubicacion}</dd></div>}
            {tarea.peso_real_kg && <div><dt>Peso real</dt><dd>{tarea.peso_real_kg} kg</dd></div>}
            {tarea.peso_volumetrico_kg && <div><dt>Peso volumétrico</dt><dd>{tarea.peso_volumetrico_kg} kg</dd></div>}
            {tarea.catalogo_tipo_caja_id && <div><dt>Caja</dt><dd>{tiposCaja.find((c) => Number(c.id) === Number(tarea.catalogo_tipo_caja_id))?.nombre || 'Registrada'}</dd></div>}
        </dl>
        {tarea.observaciones_respuesta && <p className="text-sm whitespace-pre-wrap break-words">{tarea.observaciones_respuesta}</p>}
        {tarea.observaciones_fisicas && <p className="text-sm whitespace-pre-wrap break-words"><strong>Estado físico general: </strong>{tarea.observaciones_fisicas}</p>}
        <div className="gelia-tienda-op-table-wrap mt-4"><table className="gelia-tienda-op-table"><caption className="sr-only">Productos y cantidades registrados en la respuesta</caption><thead><tr><th scope="col">Producto</th><th scope="col">Preparadas</th><th scope="col">Estado y detalle</th><th scope="col">Evidencias</th></tr></thead>
            <tbody>{productos.map((p) => {
                const fotos = evidencias.filter((d) => Number(d.pedido_bma_tarea_producto_id) === Number(p.id));
                return <tr key={p.id}><td><strong>{p.descripcion_snapshot}</strong><span className="block text-xs theme-text-muted">{p.sku}</span></td><td className="tabular-nums">{p.cantidad_encontrada ?? 'Pendiente'}</td><td>{estados[p.estado_fisico] || 'Pendiente'}{(p.unica_pieza || p.mejor_ejemplar) && <span className="block text-xs mt-1">{p.unica_pieza && <span className="gelia-pedidos-atributo">Única pieza</span>}{p.mejor_ejemplar && <span className="gelia-pedidos-atributo">Mejor ejemplar</span>}</span>}{p.observacion && <span className="block text-xs theme-text-muted whitespace-pre-wrap break-words mt-1">{p.observacion}</span>}</td><td>{fotos.length ? <button type="button" className={BTN_SECONDARY} onClick={() => ver(fotos)}>Ver ({fotos.length})</button> : <span className="theme-text-muted">Sin fotos</span>}</td></tr>;
            })}</tbody></table></div>
        {evidencias.length > 0 && <button type="button" className={`${BTN_SECONDARY} mt-4`} onClick={() => ver(evidencias)}><Image className="w-4 h-4" aria-hidden="true" />Revisar todas las evidencias ({evidencias.length})</button>}
        <ModalVistaPreviaDocumento {...vista} onClose={() => setVista({ abierto: false, documentos: [] })} />
    </section>;
}
