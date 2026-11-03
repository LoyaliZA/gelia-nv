import React from 'react';
import { ClipboardCheck } from 'lucide-react';
import { formatearFechaNegocio } from './pedidosBmaStyles';
import { esPdf, MiniaturaDocumento } from './ModalVistaPreviaDocumento';

export default function RespuestaPreparacionTienda({ tarea, onVerDocumentos }) {
    const respuesta = tarea?.respuesta;
    const documentos = respuesta?.documentos || [];
    const hayFotos = documentos.some((d) => d?.url);
    const visible = Boolean(respuesta) && (respuesta.respondida || tarea.estado === 'CON_INCIDENCIA' || hayFotos);
    if (!visible) return null;
    const estados = { bueno: 'Bueno', regular: 'Con detalle', malo: 'Malo', danado: 'Dañado', sin_existencia: 'Sin existencias' };
    const fotosDe = (productoId) => documentos.filter((d) => Number(d.producto_id) === Number(productoId) && d.url);
    const generales = documentos.filter((d) => !d.producto_id && d.url);
    const fotosGenerales = generales.filter((d) => !esPdf(d));
    const documentosGenerales = generales.filter((d) => esPdf(d));
    const ver = (docs, i) => onVerDocumentos?.(docs, i);
    const titulo = respuesta.apartado_confirmado
        ? 'Resultado del apartado en tienda'
        : (tarea.modalidad?.codigo === 'RECOGE_TIENDA' || respuesta.revision?.estado
            ? 'Respuesta de disponibilidad'
            : (!respuesta.respondida ? 'Resultado de la revisión en tienda' : 'Resultado de preparación'));
    const excepciones = Array.isArray(respuesta.excepciones) ? respuesta.excepciones : [];
    const skusEnTabla = new Set((respuesta.productos || []).map((producto) => String(producto.sku || '')));
    const excepcionesSueltas = excepciones.filter((pieza) => !skusEnTabla.has(String(pieza.sku || '')) || !pieza.sku);
    return <section className="registrar-tienda-panel space-y-3" aria-label="Respuesta de preparación">
        <div className="registrar-tienda-seccion-titulo">
            <ClipboardCheck size={18} aria-hidden="true" />
            <h3>{titulo}</h3>
        </div>
        <p className="text-sm theme-text-muted m-0">{respuesta.responsable && `Respondió ${respuesta.responsable}`}{respuesta.respondida_at && ` · ${formatearFechaNegocio(respuesta.respondida_at)}`}{!respuesta.respondida && hayFotos && ' · Evidencias registradas'}</p>
        <div className="overflow-x-auto"><table className="w-full text-sm text-left"><thead><tr className="border-b theme-border"><th className="p-2">Producto</th><th className="p-2">Preparadas</th><th className="p-2">Resultado</th><th className="p-2">Foto</th></tr></thead>
            <tbody>{respuesta.productos.map((p) => {
                const fotos = fotosDe(p.id);
                return <tr key={p.id} className="border-b theme-border"><td className="p-2">{p.descripcion}<span className="block text-xs theme-text-muted">{p.sku}</span></td><td className="p-2">{p.cantidad ?? 'Pendiente'}</td><td className="p-2">{estados[p.estado_fisico] || 'Pendiente'}{(p.unica_pieza || p.mejor_ejemplar) && <span className="block text-xs">{p.unica_pieza && <span className="gelia-pedidos-atributo">Única pieza</span>}{p.mejor_ejemplar && <span className="gelia-pedidos-atributo">Mejor ejemplar</span>}</span>}{p.observacion && <span className="block">{p.observacion}</span>}</td><td className="p-2">{fotos.length ? <div className="flex flex-wrap gap-2">{fotos.map((d, i) => <MiniaturaDocumento key={d.id} documento={d} onVer={() => ver(fotos, i)} />)}</div> : <span className="theme-text-muted text-xs">Sin foto</span>}</td></tr>;
            })}</tbody>
        </table></div>
        {respuesta.revision?.estado && <p className="text-sm theme-text-main m-0">{respuesta.revision.estado === 'excepciones_pendientes' ? 'Hay excepciones pendientes de decisión. Esas piezas no continúan como aceptadas.' : respuesta.revision.estado === 'sin_excepciones' ? 'Conjunto sin excepciones. El apartado físico es un paso distinto.' : 'La decisión del cliente quedó registrada.'}{respuesta.revision.aceptacion_vigente === false ? ' La aceptación de esta versión no sigue vigente.' : ''}</p>}
        {excepcionesSueltas.length > 0 && <ul className="m-0 pl-4 grid gap-1">{excepcionesSueltas.map((pieza) => <li key={pieza.id || pieza.descripcion} className="text-sm theme-text-main">{pieza.descripcion} · piezas afectadas: {pieza.cantidad}{pieza.detalle ? ` · ${pieza.detalle}` : ''}</li>)}</ul>}
        {(respuesta.productos?.length > 0 || respuesta.piezas_afectadas) && <p className="text-sm font-bold m-0">{respuesta.piezas_afectadas ? `Piezas afectadas: ${respuesta.cantidad}` : `Total preparado: ${respuesta.cantidad} piezas`}{respuesta.ubicacion ? ` · Ubicación: ${respuesta.ubicacion}` : ''}</p>}
        {respuesta.apartado_confirmado && <p className="text-sm theme-text-main m-0">Apartado confirmado. {respuesta.pago_presencial === 'confirmado' ? 'Pago presencial confirmado.' : 'Pago presencial pendiente.'} {respuesta.entrega === 'registrada' ? 'Entrega registrada.' : 'Entrega pendiente.'}</p>}
        {respuesta.observaciones && <p className="text-sm m-0">{respuesta.observaciones}</p>}
        {tarea.incidencia?.motivo && <p role="alert" className="text-sm m-0">{tarea.incidencia.motivo}</p>}
        {fotosGenerales.length > 0 && (
            <div className="space-y-2">
                <p className="text-sm font-semibold m-0">Evidencia del conjunto</p>
                <div className="flex flex-wrap gap-2">{fotosGenerales.map((d, i) => <MiniaturaDocumento key={d.id} documento={d} onVer={() => ver(fotosGenerales, i)} />)}</div>
            </div>
        )}
        {documentosGenerales.length > 0 && (
            <div className="space-y-2">
                <p className="text-sm font-semibold m-0">Documentos</p>
                <div className="flex flex-wrap gap-2">{documentosGenerales.map((d, i) => <MiniaturaDocumento key={d.id} documento={d} onVer={() => ver(documentosGenerales, i)} />)}</div>
            </div>
        )}
    </section>;
}
