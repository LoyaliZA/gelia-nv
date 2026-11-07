import React from 'react';
import { AlertTriangle, CheckCircle2, ClipboardCheck, Eye, Image, Package, Layers, UserRound } from 'lucide-react';
import { GELIA_ICON_BOX, GELIA_BTN_OUTLINE } from '../../../utils/geliaTheme';
import { formatearFechaNegocio } from './pedidosBmaStyles';
import { MiniaturaDocumento } from './ModalVistaPreviaDocumento';

const ESTADOS = { bueno: 'Bueno', regular: 'Con detalle', malo: 'Malo', danado: 'Dañado', sin_existencia: 'Sin existencias' };

/** Presentación común. Los identificadores y decisiones siguen perteneciendo a su revisión. */
export default function RespuestaDisponibilidadPedido({ titulo = 'Respuesta de disponibilidad', ariaLabel = titulo, responsable, fecha, estado, origen, filas = [], generales = [], anteriores = [], cantidad, labelCantidad = 'Piezas afectadas', columnaCantidad = 'Revisadas', aceptacionVigente = true, onVer, accionesDePieza, children }) {
    const incidencias = estado === 'excepciones_pendientes';
    const etiqueta = incidencias ? 'Con detalles o faltantes' : estado === 'sin_excepciones' ? 'Todo OK' : 'Revisión registrada';
    return <section className="registrar-tienda-panel tienda-respuesta" aria-label={ariaLabel}>
        <header className="tienda-respuesta-encabezado">
            <span className={GELIA_ICON_BOX}><ClipboardCheck size={22} aria-hidden /></span>
            <div className="min-w-0"><div className="tienda-respuesta-titulo"><h3>{titulo}</h3>{origen && <span className="gelia-pedidos-atributo">{origen}</span>}</div><p><UserRound size={14} aria-hidden /><span className="min-w-0">{responsable ? `Respondió ${responsable}` : 'Respuesta registrada'}{fecha ? ` · ${formatearFechaNegocio(fecha)}` : ''}</span></p></div>
        </header>
        {filas.length > 0 && <div className="tienda-respuesta-lista" role="table" aria-label="Productos de la respuesta">
            <div className="tienda-respuesta-columnas" role="row"><span role="columnheader"><Package size={15} aria-hidden />Producto</span><span role="columnheader"><ClipboardCheck size={15} aria-hidden />{columnaCantidad}</span><span role="columnheader"><CheckCircle2 size={15} aria-hidden />Resultado</span><span role="columnheader"><Image size={15} aria-hidden />Foto</span><span role="columnheader">Acciones</span></div>
            {filas.map((fila, indice) => {
                const fotos = fila.documentos || [];
                const estadoFila = ESTADOS[fila.estado_fisico] || 'Pendiente';
                const repetidas = fila.sku ? filas.filter(f => f.sku === fila.sku) : [];
                const observacion = fila.observacion || '';
                return <div className="tienda-respuesta-fila" role="row" key={fila.clave || fila.id || indice}>
                    <div className="tienda-respuesta-producto" role="cell" data-label="Producto"><>{fotos.length > 0 ? <MiniaturaDocumento documento={fotos[0]} onVer={() => onVer?.(fotos, 0)} className="tienda-respuesta-producto-miniatura" /> : <span className="tienda-respuesta-producto-icono"><Package size={20} aria-hidden /></span>}</><div className="min-w-0"><strong>{fila.descripcion}</strong><span className="block text-xs theme-text-muted">{fila.sku || 'Sin SKU'}{repetidas.length > 1 ? ` · ${repetidas.indexOf(fila) + 1}/${repetidas.length}` : ''}</span></div></div>
                    <div role="cell" data-label={columnaCantidad} className="tienda-respuesta-cantidad"><span>{fila.cantidad ?? 'Pendiente'}</span></div>
                    <div role="cell" data-label="Resultado" className="tienda-respuesta-resultado"><span className="tienda-respuesta-estado" data-tono={fila.estado_fisico === 'sin_existencia' ? 'peligro' : fila.estado_fisico === 'bueno' ? 'exito' : 'aviso'}>{fila.estado_fisico === 'sin_existencia' ? <AlertTriangle size={14} aria-hidden /> : <CheckCircle2 size={14} aria-hidden />}{estadoFila}</span>
                        {fila.estado_fisico !== 'sin_existencia' && <div className="gelia-pedidos-atributos">{fila.unica_pieza && <span className="gelia-pedidos-atributo">Única pieza</span>}{fila.mejor_ejemplar && <span className="gelia-pedidos-atributo">Mejor ejemplar</span>}</div>}
                        {observacion.trim() && observacion.trim().toLowerCase() !== estadoFila.toLowerCase() && <p className="tienda-respuesta-observacion">{observacion}</p>}
                    </div>
                    <div role="cell" data-label="Foto" className="tienda-respuesta-fotos">{fotos.length > 0 ? <button type="button" className={`${GELIA_BTN_OUTLINE} tienda-respuesta-ver-fotos`} onClick={() => onVer?.(fotos, 0)} aria-label={`Ver fotos de ${fila.descripcion}: ${fotos.length}`}><Eye size={16} aria-hidden /><span>{fotos.length} {fotos.length === 1 ? 'foto' : 'fotos'}</span></button> : <span className="text-xs theme-text-muted">Sin foto</span>}</div>
                    <div role="cell" data-label="Acciones" className="tienda-respuesta-acciones">{accionesDePieza?.(fila.original || fila) || <span className="text-xs theme-text-muted">—</span>}</div>
                </div>;
            })}
        </div>}
        <footer className="tienda-respuesta-resumen">
            <span className={`gelia-estado-vivo gelia-estado-vivo--compacto ${incidencias ? 'gelia-estado-vivo--aviso' : 'gelia-estado-vivo--exito'}`}>{etiqueta}</span>
            {!aceptacionVigente && <span className="gelia-pedidos-atributo">Ajuste pendiente</span>}
            {cantidad !== null && cantidad !== undefined && <span className="tienda-respuesta-conteo"><Layers size={18} aria-hidden />{labelCantidad}: <strong>{cantidad}</strong></span>}
            {generales.length > 0 && <div className="tienda-respuesta-captura"><Image size={18} aria-hidden /><span>Captura del pedido</span><div className="flex flex-wrap gap-2">{generales.map((doc, i) => <MiniaturaDocumento key={doc.id || i} documento={doc} onVer={() => onVer?.(generales, i)} className="tienda-respuesta-miniatura" />)}</div></div>}
        </footer>
        {anteriores.length > 0 && <details className="tienda-respuesta-notas"><summary className="cursor-pointer text-sm theme-text-muted">Capturas anteriores ({anteriores.length})</summary><div className="flex flex-wrap gap-2 mt-2">{anteriores.map((doc, i) => <MiniaturaDocumento key={doc.id || i} documento={doc} onVer={() => onVer?.(anteriores, i)} className="tienda-respuesta-miniatura" />)}</div></details>}
        {React.Children.toArray(children).length > 0 && <div className="tienda-respuesta-notas">{children}</div>}
    </section>;
}
