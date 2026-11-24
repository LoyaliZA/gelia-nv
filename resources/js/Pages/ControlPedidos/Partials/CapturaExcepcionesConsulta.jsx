import React, { useRef, useState } from 'react';
import { Camera, FileText, Package, Pencil, Plus, Trash2 } from 'lucide-react';
import SelectorProducto from '../../../Components/Almacenes/SelectorProducto';
import GaleriaEvidenciasPedido from './GaleriaEvidenciasPedido';
import { GELIA_BTN_OUTLINE, THEME_INPUT, THEME_LABEL, THEME_TEXTAREA } from '../../../utils/geliaTheme';

export function excepcionDesdeElegido(elegido = {}, extras = {}) {
    const condicion = extras.condicion || elegido.condicion || 'detalle';
    const faltante = condicion === 'faltante';
    return {
        descripcion: elegido.descripcion || '',
        sku: elegido.sku || '',
        producto_id: elegido.id || null,
        cantidad: extras.cantidad || 1,
        condicion,
        detalle: faltante ? 'Sin existencias' : (elegido.detalle || ''),
        unica_pieza: !faltante && Boolean(elegido.unica_pieza),
        mejor_ejemplar: !faltante && Boolean(elegido.mejor_ejemplar),
        archivos: faltante ? [] : (elegido.archivos || []),
        previews: faltante ? [] : (elegido.previews || []),
        ...extras,
        condicion,
    };
}

const ESTADOS_PIEZA = [['unica_pieza', 'Única pieza'], ['mejor_ejemplar', 'Mejor ejemplar'], ['faltante', 'Sin existencias']];

/** Interruptores del caso de una pieza: siempre a la mano, compartidos por Tienda, Municipal y Nacional. */
export function InterruptoresPieza({ valor = {}, onChange, disabled = false, etiqueta = 'Estado de la pieza' }) {
    const sinExistencias = valor.condicion === 'faltante';
    return <div className="gelia-interruptores-pieza" role="group" aria-label={etiqueta}>
        {ESTADOS_PIEZA.map(([campo, texto]) => {
            const activo = campo === 'faltante' ? sinExistencias : Boolean(valor[campo]);
            return <button key={campo} type="button" role="switch" aria-label={texto} aria-checked={activo} disabled={disabled || (sinExistencias && campo !== 'faltante')} onClick={() => onChange(campo === 'faltante' ? { condicion: activo ? 'detalle' : 'faltante' } : { [campo]: !activo })}>
                <span className="gelia-switch pointer-events-none shrink-0" data-active={activo ? 'true' : 'false'}><span className="gelia-switch-thumb" /></span>{texto}
            </button>;
        })}
    </div>;
}

export function aplicarCambioExcepcion(fila, cambiosPieza) {
    if (cambiosPieza.condicion === 'faltante') liberarPreviews(fila.previews);
    return {
        ...fila,
        ...cambiosPieza,
        ...(cambiosPieza.condicion ? {
            detalle: cambiosPieza.condicion === 'faltante' ? 'Sin existencias' : fila.detalle === 'Sin existencias' ? '' : fila.detalle,
            ...(cambiosPieza.condicion === 'faltante' ? { archivos: [], previews: [], unica_pieza: false, mejor_ejemplar: false } : {}),
        } : {}),
    };
}

export function mensajeValidacionExcepciones(afectadas = [], { usaPreviews = false } = {}) {
    if (!afectadas.length) return 'Agrega las piezas con detalle o faltante.';
    if (afectadas.some(p => !String(p.descripcion || '').trim())) return 'Identifica el producto de cada unidad reportada.';
    if (afectadas.some(p => p.condicion === 'detalle' && !p.unica_pieza && !p.mejor_ejemplar && !String(p.detalle || '').trim())) return 'Describe el defecto de la unidad reportada.';
    if (afectadas.some(p => p.condicion === 'detalle' && !(usaPreviews ? p.previews?.length : p.archivos?.length))) return 'Adjunta una foto de cada producto con detalle.';
    return '';
}

export function liberarPreviews(previews = []) {
    previews.forEach(p => { if (p.url?.startsWith('blob:')) URL.revokeObjectURL(p.url); });
}

export function repeticionDe(filas, fila) {
    if (!fila.sku) return null;
    const repetidas = filas.filter(f => f.sku === fila.sku);
    return repetidas.length > 1 ? `${repetidas.indexOf(fila) + 1}/${repetidas.length}` : null;
}

export function EstadoPedidoConsulta({ name = 'estado-disponibilidad-cedis', estado, onChange, disabled }) {
    return <fieldset className="gelia-cedis-panel grid gap-2 m-0">
        <legend className="text-sm font-semibold theme-text-main mb-2">Estado del pedido</legend>
        {[['correcto', 'Todo OK'], ['detalle', 'Con detalle o faltante']].map(([valor, texto]) => <label key={valor} className="gelia-cedis-opcion flex items-center gap-3 border theme-border rounded-xl p-3 text-sm theme-text-main" data-seleccionado={estado === valor}>
            <input type="radio" name={name} className="gelia-checkbox" checked={estado === valor} disabled={disabled} onChange={() => onChange(valor)} />{texto}
        </label>)}
    </fieldset>;
}

export function EtiquetasPieza({ fila }) {
    const faltante = fila.condicion === 'faltante';
    return <span className="gelia-cedis-etiquetas">
        <span className="gelia-cedis-etiqueta" data-tipo={faltante ? 'faltante' : 'detalle'}>{faltante ? 'Sin existencias' : 'Con detalle'}</span>
        {!faltante && fila.unica_pieza && <span className="gelia-cedis-etiqueta">Única pieza</span>}
        {!faltante && fila.mejor_ejemplar && <span className="gelia-cedis-etiqueta">Mejor ejemplar</span>}
        {!faltante && fila.previews?.length > 0 && <span className="gelia-cedis-etiqueta">{fila.previews.length}/5 fotos</span>}
    </span>;
}

export function PiezaExcepcionCedis({ fila, indice, repeticion, manual, disabled, onChange, onQuitar, onVer }) {
    const [expandida, setExpandida] = useState(Boolean(fila.detalle && fila.condicion !== 'faltante') || manual && !fila.sku);
    const capturaRef = useRef(null);
    const sinExistencias = fila.condicion === 'faltante';
    const limiteFotos = fila.previews.length >= 5;
    const conComentario = !sinExistencias && Boolean(String(fila.detalle || '').trim());
    return <article className="gelia-cedis-producto">
        <button type="button" className="gelia-cedis-foto" disabled={disabled || sinExistencias || limiteFotos} aria-label={`Agregar evidencia del producto ${indice + 1}`} title={sinExistencias ? 'Sin existencias: no requiere foto' : limiteFotos ? 'Máximo de 5 fotos por producto' : 'Tomar foto del producto'} onClick={() => capturaRef.current?.tomarFoto()}>
            {fila.previews[0] ? <img src={fila.previews[0].url} alt="Foto del defecto" /> : <Camera size={22} aria-hidden />}
            <Plus size={14} className="gelia-cedis-foto-mas" aria-hidden />
        </button>
        <div className="min-w-0 grid gap-2">
            <div>
                <span className="gelia-cedis-producto-identidad">{repeticion && <span className="gelia-cedis-unidad">{repeticion}</span>}<strong title={`${fila.sku} — ${fila.descripcion}`}>{fila.sku ? `${fila.sku} — ` : ''}{fila.descripcion || 'Pieza por identificar'}</strong></span>
                <EtiquetasPieza fila={fila} />
            </div>
            <InterruptoresPieza valor={fila} disabled={disabled} etiqueta={`Estados del producto ${indice + 1}`} onChange={onChange} />
            {!sinExistencias && <details open={expandida} onToggle={e => setExpandida(e.currentTarget.open)} className="min-w-0 gelia-cedis-detalle">
                <summary hidden>Comentario y fotos</summary>
                <div className="grid gap-3">
                    {manual && !fila.sku && <label className={THEME_LABEL}>Pieza<input className={`${THEME_INPUT} w-full`} maxLength={255} value={fila.descripcion} disabled={disabled} onChange={e => onChange({ descripcion: e.target.value })} /></label>}
                    <label className={THEME_LABEL}>{fila.unica_pieza || fila.mejor_ejemplar ? 'Observaciones (opcional)' : 'Detalle del defecto'}<textarea className={`${THEME_TEXTAREA} w-full`} rows={2} maxLength={500} value={fila.detalle} disabled={disabled} onChange={e => onChange({ detalle: e.target.value })} /></label>
                    <GaleriaEvidenciasPedido compacto capturaExterna controlRef={capturaRef} soloImagenes label="Fotos del producto" maxArchivos={5} archivos={fila.archivos} previews={fila.previews} disabled={disabled} onChange={(archivos, previews) => onChange({ archivos, previews })} onVer={onVer} />
                </div>
            </details>}
        </div>
        <div className="gelia-cedis-acciones">
            <button type="button" className={`${GELIA_BTN_OUTLINE} gelia-cedis-quitar`} disabled={disabled} aria-label={`Quitar pieza ${indice + 1}`} title="Quitar de la lista" onClick={onQuitar}><Trash2 size={16} aria-hidden /></button>
            {!sinExistencias && <button type="button" className={`${GELIA_BTN_OUTLINE} gelia-cedis-quitar`} data-activo={conComentario ? 'true' : undefined} disabled={disabled} aria-expanded={expandida} aria-label={`${conComentario ? 'Editar comentario y fotos' : 'Agregar comentario o fotos'} del producto ${indice + 1}`} title="Comentario y fotos" onClick={() => setExpandida(v => !v)}><Pencil size={16} aria-hidden /></button>}
        </div>
    </article>;
}

export default function CapturaExcepcionesConsulta({
    actualizacion = false,
    excepciones = [],
    archivadas = new Set(),
    origenIncidencia = null,
    incidenciasRef,
    manual = false,
    almacenId,
    inputId = 'cedis-buscar-producto',
    disabled = false,
    ocupado = false,
    escaneo = { ultimo: '', fallos: [] },
    onAgregar,
    onCapturarManual,
    onCapturarCodigo,
    onAgregarDetalle,
    onChange,
    onQuitar,
    onVer,
}) {
    return <section ref={incidenciasRef} className="gelia-cedis-panel grid gap-3" aria-label="Productos con detalle o faltantes">
        <div className="registrar-tienda-seccion-titulo"><Package size={18} aria-hidden /><h3>{actualizacion ? 'Productos con detalle' : 'Productos con detalle o faltantes'}</h3><span className="text-xs theme-text-muted">{excepciones.length - archivadas.size}</span></div>
        {origenIncidencia && <p className="consulta-incidencia-origen"><FileText size={16} aria-hidden />{origenIncidencia.esDocumento ? 'Piezas de la captura seleccionada' : origenIncidencia.descripcion}</p>}
        <p className="text-xs theme-text-muted m-0">Registra solo las piezas afectadas.</p>
        {actualizacion ? <button type="button" className={`${GELIA_BTN_OUTLINE} justify-self-start`} disabled={disabled} onClick={onAgregarDetalle}><Plus size={16} aria-hidden />Agregar productos con detalle</button> : <>
        <div>
            <label className={THEME_LABEL} htmlFor={inputId}>Buscar o escanear producto</label>
            <SelectorProducto inputId={inputId} almacenId={almacenId} incluirSinInventario value="" onChange={(_, elegido) => { if (elegido) onAgregar?.(elegido); }} onEscanear={onCapturarCodigo} disabled={ocupado} />
            <p className="text-xs theme-text-muted mt-2 mb-0">Cada lectura o selección registra una unidad.</p>
        </div>
        {escaneo.ultimo && <p className="text-xs theme-text-muted m-0" role="status">{escaneo.ultimo}</p>}
        {escaneo.fallos.map((fallo, i) => <p key={i} role="alert" className="text-sm theme-text-peligro">{fallo.codigo}: {fallo.mensaje}</p>)}
        <button type="button" className={`${GELIA_BTN_OUTLINE} justify-self-start`} disabled={disabled} onClick={onCapturarManual}><Plus size={16} aria-hidden />Capturar sin catálogo</button>
        </>}
        <p className="text-xs theme-text-muted m-0">Abre una unidad para registrar sus detalles.</p>
        {excepciones.map((fila, i) => archivadas.has(fila.clave) ? null : <PiezaExcepcionCedis key={fila.clave} fila={fila} indice={i} repeticion={repeticionDe(excepciones, fila)} manual={manual} disabled={disabled} onChange={cambios => onChange?.(i, cambios)} onQuitar={() => onQuitar?.(i)} onVer={onVer} />)}
        {archivadas.size > 0 && <details className="gelia-cedis-archivo">
            <summary className="text-xs theme-text-muted cursor-pointer">Consulta anterior · {archivadas.size} {archivadas.size === 1 ? 'pieza archivada' : 'piezas archivadas'}</summary>
            <ul className="grid gap-1 m-0 mt-2 pl-0 list-none">
                {excepciones.filter(fila => archivadas.has(fila.clave)).map(fila => <li key={fila.clave} className="text-xs theme-text-muted"><strong>{fila.sku ? `${fila.sku} — ` : ''}{fila.descripcion || 'Pieza'}</strong> · {fila.condicion === 'faltante' ? 'Sin existencias' : 'Con detalle'}{fila.previews?.length > 0 && ` · ${fila.previews.length} foto(s)`}</li>)}
            </ul>
        </details>}
    </section>;
}
