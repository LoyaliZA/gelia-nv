import React from 'react';
import RespuestaDisponibilidadPedido from './RespuestaDisponibilidadPedido';
import { ClipboardCheck } from 'lucide-react';
import { formatearFechaNegocio } from './pedidosBmaStyles';
import { esPdf, MiniaturaDocumento } from './ModalVistaPreviaDocumento';

export default function RespuestaPreparacionTienda({ tarea, onVerDocumentos, accionesDePieza }) {
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
    const notasAutomaticas = [
        'La respuesta incluye piezas con excepción. El pedido se cierra con Cerrar consulta. Para cambiar piezas, registre un anexo o un retiro.',
        'Revisión sin excepciones. Falta el apartado físico de lo aceptado. No hay pago ni entrega.',
        'Consulta de disponibilidad sin excepciones. No hay traslado, pago ni entrega.',
        'La revisión quedó registrada.',
    ];
    const ver = (docs, i) => onVerDocumentos?.(docs, i);
    const titulo = respuesta.apartado_confirmado
        ? 'Resultado del apartado en tienda'
        : (tarea.modalidad?.codigo === 'RECOGE_TIENDA' || respuesta.revision?.estado
            ? 'Respuesta de disponibilidad'
            : (!respuesta.respondida ? 'Resultado de la revisión en tienda' : 'Resultado de preparación'));
    const excepciones = Array.isArray(respuesta.excepciones) ? respuesta.excepciones : [];
    const skusEnTabla = new Set((respuesta.productos || []).map((producto) => String(producto.sku || '')));
    const excepcionesSueltas = excepciones.filter((pieza) => !skusEnTabla.has(String(pieza.sku || '')) || !pieza.sku);
    if (respuesta.revision?.estado) {
        const filas = [
            ...(respuesta.productos || []).map(p => ({ ...p, clave: `producto-${p.id}`, documentos: fotosDe(p.id), original: p })),
            ...excepcionesSueltas.map(p => ({ ...p, clave: `revision-${p.id}`, cantidad: p.estado_fisico === 'sin_existencia' ? 0 : p.cantidad, observacion: p.detalle, documentos: [], original: { ...p, revision_id: p.id } })),
        ];
        return <RespuestaDisponibilidadPedido titulo={titulo} origen="Tienda" ariaLabel="Respuesta de preparación" responsable={respuesta.responsable} fecha={respuesta.respondida_at} estado={respuesta.revision.estado} aceptacionVigente={respuesta.revision.aceptacion_vigente !== false}
            filas={filas} generales={generales} cantidad={respuesta.productos?.length || respuesta.piezas_afectadas ? respuesta.cantidad : null}
            labelCantidad={respuesta.piezas_afectadas ? 'Piezas afectadas' : 'Total preparado'} columnaCantidad={respuesta.apartado_confirmado ? 'Preparadas' : 'Revisadas'} onVer={ver} accionesDePieza={accionesDePieza}>
            {respuesta.documental && respuesta.apartado_confirmado && <p>Pedido completo separado conforme a la consulta aprobada.</p>}
            {respuesta.ubicacion && <p>Ubicación: {respuesta.ubicacion}</p>}
            {respuesta.apartado_confirmado && <p>Apartado confirmado. {respuesta.pago_presencial === 'confirmado' ? 'Pago presencial confirmado.' : 'Pago presencial pendiente.'} {respuesta.entrega === 'registrada' ? 'Entrega registrada.' : 'Entrega pendiente.'}</p>}
            {respuesta.observaciones && !notasAutomaticas.includes(respuesta.observaciones.trim()) && <p>{respuesta.observaciones}</p>}
            {tarea.incidencia?.motivo && <p role="alert">{tarea.incidencia.motivo}</p>}
        </RespuestaDisponibilidadPedido>;
    }
    return <section className="registrar-tienda-panel space-y-3 theme-text-main" aria-label="Respuesta de preparación">
        <div className="registrar-tienda-seccion-titulo">
            <ClipboardCheck size={18} aria-hidden="true" />
            <h3>{titulo}</h3>
        </div>
        <p className="text-sm theme-text-muted m-0">{respuesta.responsable && `Respondió ${respuesta.responsable}`}{respuesta.respondida_at && ` · ${formatearFechaNegocio(respuesta.respondida_at)}`}{!respuesta.respondida && hayFotos && ' · Evidencias registradas'}</p>
        {(respuesta.productos || []).length > 0 && <div className="overflow-x-auto"><table className="w-full text-sm text-left tienda-respuesta-tabla"><thead><tr className="border-b theme-border"><th className="p-2">Producto</th><th className="p-2">{respuesta.apartado_confirmado ? 'Preparadas' : 'Revisadas'}</th><th className="p-2">Resultado</th><th className="p-2">Foto</th></tr></thead>
            <tbody>{respuesta.productos.map((p) => {
                const fotos = fotosDe(p.id);
                return <tr key={p.id} className="border-b theme-border"><td className="p-2">{p.descripcion}<span className="block text-xs theme-text-muted">{p.sku}</span>{accionesDePieza?.(p)}</td><td className="p-2" data-label={respuesta.apartado_confirmado ? 'Preparadas' : 'Revisadas'}>{p.cantidad ?? 'Pendiente'}</td><td className="p-2" data-label="Resultado">{estados[p.estado_fisico] || 'Pendiente'}{(p.estado_fisico !== 'sin_existencia' && (p.unica_pieza || p.mejor_ejemplar)) && <span className="block text-xs">{p.unica_pieza && <span className="gelia-pedidos-atributo">Única pieza</span>}{p.mejor_ejemplar && <span className="gelia-pedidos-atributo">Mejor ejemplar</span>}</span>}{p.observacion && p.observacion.trim().toLowerCase() !== (estados[p.estado_fisico] || '').toLowerCase() && <span className="block">{p.observacion}</span>}</td><td className="p-2" data-label="Foto">{fotos.length ? <div className="flex flex-wrap gap-2">{fotos.map((d, i) => <MiniaturaDocumento key={d.id} documento={d} onVer={() => ver(fotos, i)} />)}</div> : <span className="theme-text-muted text-xs">Sin foto</span>}</td></tr>;
            })}</tbody>
        </table></div>}
        {respuesta.revision?.estado && <div className="flex flex-wrap gap-2 items-center text-xs">
            <span className={`gelia-estado-vivo gelia-estado-vivo--compacto ${respuesta.revision.estado === 'excepciones_pendientes' ? 'gelia-estado-vivo--aviso' : 'gelia-estado-vivo--exito'}`}>{respuesta.revision.estado === 'excepciones_pendientes' ? 'Con incidencias' : respuesta.revision.estado === 'sin_excepciones' ? 'Sin incidencias' : 'Revisión registrada'}</span>
            {respuesta.revision.aceptacion_vigente === false && <span className="gelia-pedidos-atributo">Ajuste pendiente</span>}
        </div>}
        {excepcionesSueltas.length > 0 && <ul className="m-0 pl-4 grid gap-1">{excepcionesSueltas.map((pieza) => <li key={pieza.id || pieza.descripcion} className="text-sm theme-text-main">{pieza.descripcion} · piezas afectadas: {pieza.cantidad}{pieza.detalle ? ` · ${pieza.detalle}` : ''}</li>)}</ul>}
        {respuesta.documental && respuesta.apartado_confirmado && <p className="text-sm font-bold m-0">Pedido completo separado conforme a la consulta aprobada.</p>}
        {!(respuesta.documental && respuesta.apartado_confirmado) && (respuesta.productos?.length > 0 || respuesta.piezas_afectadas) && <p className="text-sm font-bold m-0">{respuesta.piezas_afectadas ? `Piezas afectadas: ${respuesta.cantidad}` : `Total preparado: ${respuesta.cantidad} piezas`}{respuesta.ubicacion ? ` · Ubicación: ${respuesta.ubicacion}` : ''}</p>}
        {respuesta.apartado_confirmado && <p className="text-sm theme-text-main m-0">Apartado confirmado. {respuesta.pago_presencial === 'confirmado' ? 'Pago presencial confirmado.' : 'Pago presencial pendiente.'} {respuesta.entrega === 'registrada' ? 'Entrega registrada.' : 'Entrega pendiente.'}</p>}
        {respuesta.observaciones && !notasAutomaticas.includes(respuesta.observaciones.trim()) && <p className="text-sm m-0">{respuesta.observaciones}</p>}
        {tarea.incidencia?.motivo && <p role="alert" className="text-sm m-0">{tarea.incidencia.motivo}</p>}
        {fotosGenerales.length > 0 && (
            <div className="space-y-2">
                <p className="text-sm font-semibold m-0">Foto del conjunto</p>
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
