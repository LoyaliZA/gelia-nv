import React from 'react';
import { formatearFechaNegocio } from './pedidosBmaStyles';

export default function RespuestaPreparacionTienda({ tarea, onVerDocumentos }) {
    const respuesta = tarea?.respuesta;
    if (!respuesta || (!respuesta.respondida && tarea.estado !== 'CON_INCIDENCIA')) return null;
    const estados = { bueno: 'Bueno', regular: 'Con detalle', malo: 'Malo', danado: 'Dañado', sin_existencia: 'Sin existencias' };
    return <section className="space-y-3 border theme-border rounded-xl p-4" aria-label="Respuesta de preparación">
        <h3 className="text-base font-bold m-0">{tarea.modalidad?.codigo === 'RECOGE_TIENDA' ? 'Resultado del apartado en tienda' : 'Resultado de preparación'}</h3>
        <p className="text-sm theme-text-muted m-0">{respuesta.responsable && `Respondió ${respuesta.responsable}`}{respuesta.respondida_at && ` · ${formatearFechaNegocio(respuesta.respondida_at)}`}</p>
        <div className="overflow-x-auto"><table className="w-full text-sm text-left"><thead><tr className="border-b theme-border"><th className="p-2">Producto</th><th className="p-2">Preparadas</th><th className="p-2">Resultado</th></tr></thead>
            <tbody>{respuesta.productos.map((p) => <tr key={p.id} className="border-b theme-border"><td className="p-2">{p.descripcion}<span className="block text-xs theme-text-muted">{p.sku}</span></td><td className="p-2">{p.cantidad ?? 'Pendiente'}</td><td className="p-2">{estados[p.estado_fisico] || 'Pendiente'}{p.observacion && <span className="block">{p.observacion}</span>}</td></tr>)}</tbody>
        </table></div>
        <p className="text-sm font-bold m-0">Total: {respuesta.cantidad} piezas{respuesta.ubicacion ? ` · Ubicación: ${respuesta.ubicacion}` : ''}</p>
        {respuesta.observaciones && <p className="text-sm m-0">{respuesta.observaciones}</p>}
        {tarea.incidencia?.motivo && <p role="alert" className="text-sm m-0">{tarea.incidencia.motivo}</p>}
        <div className="flex flex-wrap gap-2">{respuesta.documentos.map((d, i) => <button key={d.id} type="button" className="theme-btn-secondary text-sm" onClick={() => onVerDocumentos?.(respuesta.documentos, i)}>{d.tipo === 'caratula_recoge_tienda' ? 'Ver carátula' : d.nombre_original || 'Ver evidencia'}</button>)}</div>
    </section>;
}
