import React from 'react';
import { AlertTriangle, CheckCircle2, ClipboardCheck, Eye, Image, Package, Layers, UserRound } from 'lucide-react';
import { GELIA_ICON_BOX, GELIA_BTN_OUTLINE } from '../../../utils/geliaTheme';
import { formatearFechaNegocio } from './pedidosBmaStyles';
import { MiniaturaDocumento } from './ModalVistaPreviaDocumento';
import { tonoDePieza } from './cambiosDisponibilidad';

const TONO_ICONO = { exito: CheckCircle2, peligro: AlertTriangle, aviso: AlertTriangle };

/** Fila de un cambio pedido (anexo, retiro o decisión): mismo molde que una pieza, con su estado en texto. */
function FilaCambio({ cambio, onVer }) {
    const Icono = TONO_ICONO[cambio.tono] || AlertTriangle;
    const docs = cambio.documentos || [];
    return <div className="tienda-respuesta-fila" role="row" data-origen="cambio" data-tono={cambio.tono}>
        <div className="tienda-respuesta-producto" role="cell" data-label="Producto">{docs.length > 0 ? <MiniaturaDocumento documento={docs[0]} onVer={() => onVer?.(docs, 0)} className="tienda-respuesta-producto-miniatura" /> : <span className="tienda-respuesta-producto-icono"><Package size={20} aria-hidden /></span>}<div className="min-w-0"><strong>{cambio.descripcion}</strong><span className="block text-xs theme-text-muted">{cambio.sku || 'Sin SKU'}</span></div></div>
        <div role="cell" data-label="Cantidad" className="tienda-respuesta-cantidad"><span>{cambio.cantidad ?? '—'}</span></div>
        <div role="cell" data-label="Resultado" className="tienda-respuesta-resultado"><span className="tienda-respuesta-estado" data-tono={cambio.tono}><Icono size={14} aria-hidden />{cambio.etiqueta}</span></div>
        <div role="cell" data-label="Foto" className="tienda-respuesta-fotos">{docs.length > 0 ? <button type="button" className={`${GELIA_BTN_OUTLINE} tienda-respuesta-ver-fotos`} onClick={() => onVer?.(docs, 0)} aria-label={`Ver captura de ${cambio.descripcion}`}><Eye size={16} aria-hidden /><span>{docs.length} {docs.length === 1 ? 'foto' : 'fotos'}</span></button> : <span className="text-xs theme-text-muted">—</span>}</div>
        <div role="cell" data-label="Acciones" className="tienda-respuesta-acciones"><span className="text-xs theme-text-muted">—</span></div>
    </div>;
}

const ESTADOS = { bueno: 'Bueno', regular: 'Con detalle', malo: 'Malo', danado: 'Dañado', sin_existencia: 'Sin existencias' };

/** Presentación común. Los identificadores y decisiones siguen perteneciendo a su revisión. */
export default function RespuestaDisponibilidadPedido({ titulo = 'Respuesta de disponibilidad', ariaLabel = titulo, responsable, fecha, estado, origen, filas = [], generales = [], anteriores = [], cantidad, labelCantidad = 'Piezas afectadas', columnaCantidad = 'Revisadas', aceptacionVigente = true, mostrarCaptura = true, onVer, accionesDePieza, cambios = null, avisoReapertura = '', children }) {
    const cambiosActuales = cambios?.actuales || [];
    const cambiosAnteriores = cambios?.anteriores || [];
    const incidencias = estado === 'excepciones_pendientes';
    const etiqueta = incidencias ? 'Con detalles o faltantes' : estado === 'sin_excepciones' ? 'Todo OK' : 'Revisión registrada';
    return <section className="registrar-tienda-panel tienda-respuesta" aria-label={ariaLabel}>
        <header className="tienda-respuesta-encabezado">
            <span className={GELIA_ICON_BOX}><ClipboardCheck size={22} aria-hidden /></span>
            <div className="min-w-0"><div className="tienda-respuesta-titulo"><h3>{titulo}</h3>{origen && <span className="gelia-pedidos-atributo">{origen}</span>}</div><p><UserRound size={14} aria-hidden /><span className="min-w-0">{responsable ? `Respondió ${responsable}` : estado ? 'Respuesta registrada' : 'Pendiente de respuesta'}{fecha ? ` · ${formatearFechaNegocio(fecha)}` : ''}</span></p></div>
        </header>
        {(filas.length > 0 || cambiosActuales.length > 0) && <div className="tienda-respuesta-lista" role="table" aria-label="Productos de la respuesta">
            <div className="tienda-respuesta-columnas" role="row"><span role="columnheader"><Package size={15} aria-hidden />Producto</span><span role="columnheader"><ClipboardCheck size={15} aria-hidden />{columnaCantidad}</span><span role="columnheader"><CheckCircle2 size={15} aria-hidden />Resultado</span><span role="columnheader"><Image size={15} aria-hidden />Foto</span><span role="columnheader">Acciones</span></div>
            {filas.map((fila, indice) => {
                const fotos = fila.documentos || [];
                const estadoFila = ESTADOS[fila.estado_fisico] || 'Pendiente';
                const repetidas = fila.sku ? filas.filter(f => f.sku === fila.sku) : [];
                const observacion = fila.observacion || '';
                return <div className="tienda-respuesta-fila" role="row" data-tono={tonoDePieza({ ...fila, resolucion: fila.resolucion || fila.original?.resolucion })} key={fila.clave || fila.id || indice}>
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
            {cambiosActuales.map(cambio => <FilaCambio key={cambio.clave} cambio={cambio} onVer={onVer} />)}
        </div>}
        {avisoReapertura && <p className="text-xs theme-text-muted m-0">{avisoReapertura}</p>}
        {cambiosAnteriores.length > 0 && <details className="tienda-respuesta-movimientos"><summary>Movimientos ({cambiosAnteriores.length})</summary><div className="tienda-respuesta-lista" role="table" aria-label="Movimientos anteriores">{cambiosAnteriores.map(cambio => <FilaCambio key={cambio.clave} cambio={cambio} onVer={onVer} />)}</div></details>}
        <footer className="tienda-respuesta-resumen">
            {estado && <span className={`gelia-estado-vivo gelia-estado-vivo--compacto ${incidencias ? 'gelia-estado-vivo--aviso' : 'gelia-estado-vivo--exito'}`}>{etiqueta}</span>}
            {!aceptacionVigente && <span className="gelia-pedidos-atributo">Ajuste pendiente</span>}
            {cantidad !== null && cantidad !== undefined && <span className="tienda-respuesta-conteo"><Layers size={18} aria-hidden />{labelCantidad}: <strong>{cantidad}</strong></span>}
            {mostrarCaptura && generales.length > 0 && <div className="tienda-respuesta-captura"><Image size={18} aria-hidden /><span>Captura del pedido</span><div className="flex flex-wrap gap-2">{generales.map((doc, i) => <MiniaturaDocumento key={doc.id || i} documento={doc} onVer={() => onVer?.(generales, i)} className="tienda-respuesta-miniatura" />)}</div></div>}
        </footer>
        {mostrarCaptura && anteriores.length > 0 && <details className="tienda-respuesta-notas"><summary className="cursor-pointer text-sm theme-text-muted">Capturas anteriores ({anteriores.length})</summary><div className="flex flex-wrap gap-2 mt-2">{anteriores.map((doc, i) => <MiniaturaDocumento key={doc.id || i} documento={doc} onVer={() => onVer?.(anteriores, i)} className="tienda-respuesta-miniatura" />)}</div></details>}
        {React.Children.toArray(children).length > 0 && <div className="tienda-respuesta-notas">{children}</div>}
    </section>;
}
