import React from 'react';
import { formatearFechaNegocio } from './pedidosBmaStyles';
import { MiniaturaDocumento } from './ModalVistaPreviaDocumento';

export default function RespuestaPreparacionTienda({ tarea, onVerDocumentos }) {
    const respuesta = tarea?.respuesta;
    const documentos = respuesta?.documentos || [];
    const hayFotos = documentos.some((d) => d?.url);
    const visible = Boolean(respuesta) && (respuesta.respondida || tarea.estado === 'CON_INCIDENCIA' || hayFotos);
    if (!visible) return null;
    const estados = { bueno: 'Bueno', regular: 'Con detalle', malo: 'Malo', danado: 'Dañado', sin_existencia: 'Sin existencias' };
    const fotosDe = (productoId) => documentos.filter((d) => Number(d.producto_id) === Number(productoId) && d.url);
    const fotosGenerales = documentos.filter((d) => !d.producto_id && d.url);
    const ver = (docs, i) => onVerDocumentos?.(docs, i);
    return <section className="space-y-3 border theme-border rounded-xl p-4" aria-label="Respuesta de preparación">
        <h3 className="text-base font-bold m-0">{tarea.modalidad?.codigo === 'RECOGE_TIENDA' ? 'Resultado del apartado en tienda' : 'Resultado de preparación'}</h3>
        <p className="text-sm theme-text-muted m-0">{respuesta.responsable && `Respondió ${respuesta.responsable}`}{respuesta.respondida_at && ` · ${formatearFechaNegocio(respuesta.respondida_at)}`}{!respuesta.respondida && hayFotos && ' · Evidencias registradas'}</p>
        <div className="overflow-x-auto"><table className="w-full text-sm text-left"><thead><tr className="border-b theme-border"><th className="p-2">Producto</th><th className="p-2">Preparadas</th><th className="p-2">Resultado</th><th className="p-2">Foto</th></tr></thead>
            <tbody>{respuesta.productos.map((p) => {
                const fotos = fotosDe(p.id);
                return <tr key={p.id} className="border-b theme-border"><td className="p-2">{p.descripcion}<span className="block text-xs theme-text-muted">{p.sku}</span></td><td className="p-2">{p.cantidad ?? 'Pendiente'}</td><td className="p-2">{estados[p.estado_fisico] || 'Pendiente'}{(p.unica_pieza || p.mejor_ejemplar) && <span className="block text-xs">{p.unica_pieza && <span className="gelia-pedidos-atributo">Única pieza</span>}{p.mejor_ejemplar && <span className="gelia-pedidos-atributo">Mejor ejemplar</span>}</span>}{p.observacion && <span className="block">{p.observacion}</span>}</td><td className="p-2">{fotos.length ? <div className="flex flex-wrap gap-2">{fotos.map((d, i) => <MiniaturaDocumento key={d.id} documento={d} onVer={() => ver(fotos, i)} />)}</div> : <span className="theme-text-muted text-xs">Sin foto</span>}</td></tr>;
            })}</tbody>
        </table></div>
        <p className="text-sm font-bold m-0">Total: {respuesta.cantidad} piezas{respuesta.ubicacion ? ` · Ubicación: ${respuesta.ubicacion}` : ''}</p>
        {respuesta.observaciones && <p className="text-sm m-0">{respuesta.observaciones}</p>}
        {tarea.incidencia?.motivo && <p role="alert" className="text-sm m-0">{tarea.incidencia.motivo}</p>}
        {fotosGenerales.length > 0 && (
            <div className="space-y-2">
                <p className="text-sm font-semibold m-0">Fotos del apartado</p>
                <div className="flex flex-wrap gap-2">{fotosGenerales.map((d, i) => <MiniaturaDocumento key={d.id} documento={d} onVer={() => ver(fotosGenerales, i)} />)}</div>
            </div>
        )}
    </section>;
}
