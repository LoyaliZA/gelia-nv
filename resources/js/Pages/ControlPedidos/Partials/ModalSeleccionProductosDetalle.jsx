import React, { useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Camera, Images, Package, Pencil, Plus, ScanLine, Trash2, X } from 'lucide-react';
import SelectorProducto from '../../../Components/Almacenes/SelectorProducto';
import useEscaneoPreparacion from '../Tienda/Partials/useEscaneoPreparacion';
import usePedidoDialog from './usePedidoDialog';
import { InterruptoresPieza, liberarPreviews } from './CapturaExcepcionesConsulta';
import GaleriaEvidenciasPedido from './GaleriaEvidenciasPedido';
import { GELIA_BTN_OUTLINE, THEME_BTN_PRIMARY, THEME_INPUT, THEME_LABEL, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_TEXTAREA } from '../../../utils/geliaTheme';

/** Foto de la pieza: misma captura que la fila, para que llegue al pedido ya adjunta. */
function CapturaFotoPieza({ pieza, onChange }) {
    const control = useRef(null);
    const total = (pieza.previews || []).length;
    return <div className="grid gap-2">
        <div className="flex flex-wrap gap-2">
            <button type="button" className={GELIA_BTN_OUTLINE} disabled={total >= 5} onClick={() => control.current?.tomarFoto()}><Camera size={16} aria-hidden />Tomar foto{total ? ` (${total})` : ''}</button>
            <button type="button" className={GELIA_BTN_OUTLINE} disabled={total >= 5} onClick={() => control.current?.abrirGaleria()}><Images size={16} aria-hidden />Galería</button>
        </div>
        <GaleriaEvidenciasPedido compacto capturaExterna controlRef={control} soloImagenes label="Fotos del producto" maxArchivos={5} archivos={pieza.archivos || []} previews={pieza.previews || []} onChange={(archivos, previews) => onChange({ archivos, previews })} />
    </div>;
}

/** Selección local: el caso de cada pieza se elige aquí; las fotos se completan en el pedido, antes de enviar. */
export default function ModalSeleccionProductosDetalle({ almacenId, origen, onClose, onAgregar }) {
    const [piezas, setPiezas] = useState([]);
    const [manual, setManual] = useState(false);
    const [descripcion, setDescripcion] = useState('');
    const [error, setError] = useState('');
    const [conComentario, setConComentario] = useState({});
    const confirmada = useRef(false);
    const piezasRef = useRef([]);
    const cambiarPieza = (indice, cambios) => setPiezas(actual => actual.map((pieza, i) => {
        if (i !== indice) return pieza;
        if (cambios.condicion === 'faltante') liberarPreviews(pieza.previews);
        return {
            ...pieza, ...cambios,
            ...(cambios.condicion === 'faltante' ? { unica_pieza: false, mejor_ejemplar: false, detalle: '', archivos: [], previews: [] } : {}),
        };
    }));
    const agregar = producto => {
        if (!producto) return;
        setPiezas(actual => [...actual, { descripcion: producto.descripcion, sku: producto.sku || '', id: producto.id || null, condicion: 'detalle', unica_pieza: false, mejor_ejemplar: false, detalle: '', archivos: [], previews: [] }]);
        setError('');
    };
    const escaneo = useEscaneoPreparacion({ almacenId, permitirSinAlmacen: true, onProducto: agregar });
    const pendiente = escaneo.pendientes > 0;
    piezasRef.current = piezas;
    const cerrar = () => {
        if (pendiente) return;
        if (!confirmada.current) piezasRef.current.forEach(pieza => liberarPreviews(pieza.previews));
        onClose();
    };
    const dialogo = usePedidoDialog({ abierto: true, onClose: cerrar, bloqueado: pendiente });
    const agregarManual = () => {
        if (!descripcion.trim()) return setError('Escriba el nombre de la pieza.');
        agregar({ descripcion: descripcion.trim() });
        setDescripcion('');
    };
    const terminar = () => {
        if (pendiente) return;
        if (descripcion.trim()) return setError('Agregue la descripción escrita a la selección antes de continuar.');
        if (!piezas.length) return setError('Seleccione o describa las piezas con detalle.');
        confirmada.current = true;
        onAgregar(piezas, origen);
    };
    return createPortal(<div className={`${THEME_MODAL_OVERLAY} p-3 items-center`} style={{ zIndex: 'calc(var(--gelia-z-toast) + 3)' }} onClick={cerrar}>
        <div {...dialogo} aria-label="Registrar detalle de productos" className={`${THEME_MODAL_SHELL} consulta-seleccion-detalle w-full max-w-xl flex flex-col overflow-hidden`} onClick={e => e.stopPropagation()}>
            <header className="consulta-seleccion-detalle-cabecera">
                <span className="consulta-seleccion-detalle-icono"><Package size={22} aria-hidden /></span>
                <div className="min-w-0"><h3>Registrar detalle</h3><p>{origen?.esDocumento ? 'Elija las piezas de la captura.' : 'Elija las piezas que necesita revisar.'}</p></div>
                <button type="button" className={GELIA_BTN_OUTLINE} disabled={pendiente} onClick={cerrar} aria-label="Cerrar selección de productos"><X size={18} /></button>
            </header>
            <div className="consulta-seleccion-detalle-cuerpo">
                <div className="gelia-preparacion-selector"><label className={THEME_LABEL} htmlFor="detalle-rapido-producto"><ScanLine size={16} aria-hidden />Buscar o escanear producto</label>
                    <SelectorProducto zIndexEscaner="calc(var(--gelia-z-toast) + 4)" inputId="detalle-rapido-producto" almacenId={almacenId} incluirSinInventario value="" onChange={(_, producto) => agregar(producto)} onEscanear={escaneo.encolar} />
                </div>
                <p className="text-xs theme-text-muted m-0" role="status">{pendiente ? `Consultando ${escaneo.pendientes} lectura(s)…` : escaneo.ultimo || 'Cada selección o lectura agrega una pieza.'}</p>
                {escaneo.fallos.map((f, i) => <p key={i} className="text-xs theme-text-peligro m-0" role="alert">{f.codigo}: {f.mensaje}</p>)}
                <button type="button" className={`${GELIA_BTN_OUTLINE} justify-self-start`} onClick={() => setManual(v => !v)} aria-expanded={manual}><Plus size={16} aria-hidden />Sin catálogo</button>
                {manual && <div className="consulta-seleccion-detalle-manual"><label className={THEME_LABEL} htmlFor="detalle-rapido-descripcion">Nombre de la pieza<input id="detalle-rapido-descripcion" className={`${THEME_INPUT} w-full`} maxLength={255} value={descripcion} onChange={e => setDescripcion(e.target.value)} onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); agregarManual(); } }} /></label><button type="button" className={GELIA_BTN_OUTLINE} onClick={agregarManual}><Plus size={16} aria-hidden />Agregar pieza</button></div>}
                {piezas.length > 0 && <ul className="consulta-seleccion-detalle-lista" aria-label="Piezas seleccionadas">{piezas.map((p, i) => <li key={i}>
                    <Package size={17} aria-hidden /><div><strong>{p.descripcion}</strong><span>{p.sku || 'Sin SKU'} · 1 pieza</span></div>
                    <div className="gelia-cedis-acciones">
                        <button type="button" className={`${GELIA_BTN_OUTLINE} gelia-cedis-quitar`} aria-label={`Quitar selección ${i + 1}`} onClick={() => { liberarPreviews(p.previews); setPiezas(actual => actual.filter((_, indice) => indice !== i)); setConComentario({}); }}><Trash2 size={16} aria-hidden /></button>
                        {p.condicion !== 'faltante' && <button type="button" className={`${GELIA_BTN_OUTLINE} gelia-cedis-quitar`} data-activo={p.detalle ? 'true' : undefined} aria-expanded={Boolean(conComentario[i] || p.detalle)} aria-label={`Agregar comentario a ${p.descripcion}`} title="Comentario" onClick={() => setConComentario(actual => ({ ...actual, [i]: !actual[i] }))}><Pencil size={16} aria-hidden /></button>}
                    </div>
                    <div className="consulta-seleccion-detalle-pieza-extra">
                        <InterruptoresPieza valor={p} etiqueta={`Estado de ${p.descripcion}`} onChange={cambios => cambiarPieza(i, cambios)} />
                        {p.condicion !== 'faltante' && (conComentario[i] || p.detalle) && <label className={THEME_LABEL}>Comentario<textarea className={`${THEME_TEXTAREA} w-full`} rows={2} maxLength={500} value={p.detalle} onChange={e => cambiarPieza(i, { detalle: e.target.value })} /></label>}
                        {p.condicion !== 'faltante' && <CapturaFotoPieza pieza={p} onChange={cambios => cambiarPieza(i, cambios)} />}
                    </div>
                </li>)}</ul>}
                {error && <p role="alert" className="text-sm theme-text-peligro m-0">{error}</p>}
                <p className="text-xs theme-text-muted m-0">Elige el caso de cada pieza y, si hace falta, su foto y comentario.</p>
            </div>
            <footer className="consulta-seleccion-detalle-pie">
                <button type="button" className={GELIA_BTN_OUTLINE} disabled={pendiente} onClick={cerrar}>Cancelar</button>
                <button type="button" className={THEME_BTN_PRIMARY} disabled={pendiente || !piezas.length} onClick={terminar}><Plus size={16} aria-hidden />Agregar al pedido{piezas.length ? ` (${piezas.length})` : ''}</button>
            </footer>
        </div>
    </div>, document.body);
}
