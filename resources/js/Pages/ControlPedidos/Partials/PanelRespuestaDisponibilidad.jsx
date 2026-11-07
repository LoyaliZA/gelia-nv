import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { CheckCircle2, AlertTriangle, Camera, ChevronDown, ClipboardCheck, FileText, Package, Plus, Trash2, X } from 'lucide-react';
import usePedidoDialog from './usePedidoDialog';
import RutaPedidoTienda from './RutaPedidoTienda';
import AlternativasDisponibilidadCedis from './AlternativasDisponibilidadCedis';
import ResumenCambiosDisponibilidad from './ResumenCambiosDisponibilidad';
import ModalSeleccionProductosDetalle from './ModalSeleccionProductosDetalle';
import { cambiosDisponibilidad } from './cambiosDisponibilidad';
import SelectorProducto from '../../../Components/Almacenes/SelectorProducto';
import useEscaneoPreparacion from '../Tienda/Partials/useEscaneoPreparacion';
import GaleriaEvidenciasPedido from './GaleriaEvidenciasPedido';
import ModalVistaPreviaDocumento from './ModalVistaPreviaDocumento';
import { GELIA_BTN_OUTLINE, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_TEXTAREA } from '../../../utils/geliaTheme';

function formDataDe({ confirmacion, excepciones, archivos }) {
    const form = new FormData();
    form.append('confirmacion_explicita', confirmacion ? '1' : '0');
    excepciones.forEach((pieza, indice) => {
        for (const campo of ['descripcion', 'sku', 'cantidad', 'condicion', 'detalle']) {
            form.append(`excepciones[${indice}][${campo}]`, String(pieza[campo] || ''));
        }
        for (const atributo of ['unica_pieza', 'mejor_ejemplar']) form.append(`excepciones[${indice}][${atributo}]`, pieza[atributo] ? '1' : '0');
        if (pieza.condicion === 'detalle') (pieza.archivos || []).forEach(archivo => form.append(`excepciones[${indice}][evidencias][]`, archivo));
    });
    archivos.forEach(archivo => form.append('evidencias[]', archivo));
    return form;
}

export default function PanelRespuestaDisponibilidad({ modo = 'seccion', abierto = true, onClose, pedido = {}, documentos = [], revision = null, piezas = [], puedeResponder = false, tomada = false, origen = 'CEDIS', mostrarCambios = true, onDirtyChange, onEnviar }) {
    const solicitud = pedido.solicitud_preparacion || pedido.solicitud_preparacion_json || {};
    const actualizacion = Boolean(revision?.actualizacion_pendiente || (solicitud.anexo_sin_remision || []).some(a => !a.respondida_at));
    const cambios = cambiosDisponibilidad(solicitud, pedido.documentos || documentos);
    const [respuestasCambios, setRespuestasCambios] = useState({});
    const [origenIncidencia, setOrigenIncidencia] = useState(null);
    const [capturaRapida, setCapturaRapida] = useState(null);
    const [estado, setEstado] = useState('');
    const [excepciones, setExcepciones] = useState([]);
    const [manual, setManual] = useState(false);
    const [archivos, setArchivos] = useState([]);
    const [previews, setPreviews] = useState(() => actualizacion ? [] : (pedido.documentos || []).filter(d => d.tipo === 'evidencia_condicion' && d.url && d.activo !== false && (!d.relacion_tipo || d.relacion_tipo === 'revision_general')).map(d => ({ url: d.url, name: d.nombre_original, mime: d.mime_type, remoto: true })));
    const [vista, setVista] = useState(null);
    const secuencia = useRef(0);
    const incidenciasRef = useRef(null);
    useEffect(() => { if (origenIncidencia) incidenciasRef.current?.scrollIntoView?.({ block: 'nearest', behavior: 'smooth' }); }, [origenIncidencia]);
    const excepcionesRef = useRef([]);
    excepcionesRef.current = excepciones;
    useEffect(() => () => excepcionesRef.current.forEach(fila => liberarPreviews(fila.previews)), []);
    const [error, setError] = useState('');
    const [ocupado, setOcupado] = useState(false);
    const [enviada, setEnviada] = useState(false);
    const previewsRef = useRef([]);
    previewsRef.current = previews;
    useEffect(() => () => previewsRef.current.forEach(p => { if (p.url?.startsWith('blob:')) URL.revokeObjectURL(p.url); }), []);
    const almacenId = pedido.almacen?.id || pedido.almacen_id;
    const agregar = elegido => {
        if (!elegido) return;
        const fila = { descripcion: elegido.descripcion || '', sku: elegido.sku || '', clave: ++secuencia.current, cantidad: 1, origen_cambio: origenIncidencia?.clave, condicion: 'detalle', detalle: '', unica_pieza: false, mejor_ejemplar: false, archivos: [], previews: [] };
        setExcepciones(actual => [...actual, fila]);
        if (origenIncidencia) setRespuestasCambios(actual => ({ ...actual, [origenIncidencia.clave]: 'incidencia' }));
    };
    const escaneo = useEscaneoPreparacion({ almacenId, permitirSinAlmacen: true, onProducto: agregar });
    const guardando = ocupado || escaneo.pendientes > 0;
    const respondida = enviada || Boolean(revision?.estado && !actualizacion);
    useEffect(() => { onDirtyChange?.(!enviada && Boolean(estado || excepciones.length || archivos.length || Object.keys(respuestasCambios).length || capturaRapida)); }, [estado, excepciones.length, archivos.length, respuestasCambios, capturaRapida, enviada, onDirtyChange]);
    const editable = tomada && puedeResponder && !respondida;
    const dialogo = usePedidoDialog({ abierto: modo === 'dialogo' && abierto, onClose, bloqueado: guardando });
    const cambiar = (indice, cambios) => setExcepciones(actual => actual.map((fila, i) => {
        if (i !== indice) return fila;
        if (cambios.condicion === 'faltante') liberarPreviews(fila.previews);
        return { ...fila, ...cambios, ...(cambios.condicion ? {
            detalle: cambios.condicion === 'faltante' ? 'Sin existencias' : fila.detalle === 'Sin existencias' ? '' : fila.detalle,
            ...(cambios.condicion === 'faltante' ? { archivos: [], previews: [], unica_pieza: false, mejor_ejemplar: false } : {}),
        } : {}) };
    }));
    const quitar = indice => {
        liberarPreviews(excepciones[indice]?.previews);
        setExcepciones(actual => actual.filter((_, i) => i !== indice));
    };
    const responderCambio = (cambio, respuesta) => {
        setRespuestasCambios(actual => ({ ...actual, [cambio.clave]: respuesta }));
        setExcepciones(actual => {
            actual.filter(f => f.origen_cambio === cambio.clave).forEach(f => liberarPreviews(f.previews));
            return actual.filter(f => f.origen_cambio !== cambio.clave);
        });
        if (origenIncidencia?.clave === cambio.clave) setOrigenIncidencia(null);
    };
    const reportarCambio = (cambio, condicion) => {
        if (cambio.esDocumento) { setCapturaRapida({ origen: cambio }); return; }
        setOrigenIncidencia(cambio);
        setRespuestasCambios(actual => ({ ...actual, [cambio.clave]: 'incidencia' }));
        setExcepciones(actual => {
            if (actual.some(f => f.origen_cambio === cambio.clave)) return actual;
            return [...actual, { descripcion: cambio.descripcion || '', sku: cambio.sku || '', cantidad: condicion === 'faltante' ? Number(cambio.cantidad) || 1 : 1,
                clave: ++secuencia.current, origen_cambio: cambio.clave, condicion, detalle: condicion === 'faltante' ? 'Sin existencias' : '',
                unica_pieza: false, mejor_ejemplar: false, archivos: [], previews: [] }];
        });
    };
    const agregarSeleccionDetalle = (seleccionadas, origen) => {
        setExcepciones(actual => [...actual, ...seleccionadas.map(pieza => ({
            descripcion: pieza.descripcion, sku: pieza.sku || '', cantidad: 1,
            clave: ++secuencia.current, origen_cambio: origen?.clave, condicion: 'detalle', detalle: '',
            unica_pieza: false, mejor_ejemplar: false, archivos: [], previews: [],
            abrir_detalle: seleccionadas.length === 1,
        }))]);
        if (seleccionadas.some(pieza => !pieza.sku)) setManual(true);
        if (origen) {
            setOrigenIncidencia(origen);
            setRespuestasCambios(actual => ({ ...actual, [origen.clave]: 'incidencia' }));
        }
        setCapturaRapida(null);
        requestAnimationFrame(() => incidenciasRef.current?.scrollIntoView?.({ block: 'nearest' }));
    };
    const capturarCodigo = valor => escaneo.encolar(valor);
    const destino = pedido.sucursal_destino?.nombre || pedido.sucursalDestino?.nombre || pedido.sucursal_destino_nombre || pedido.expediente_registrar?.sucursal || 'Por confirmar';
    const referencias = documentos.filter(d => d.url);

    const enviar = async () => {
        if (guardando || !editable) return;
        setError('');
        const afectadas = actualizacion || estado === 'detalle' ? excepciones : [];
        if (!actualizacion && !estado) return setError('Indica el estado del pedido.');
        if (!actualizacion && estado === 'detalle' && !afectadas.length) return setError('Agrega las piezas con detalle o faltante.');
        const pendientes = cambios.filter(c => c.pendiente && c.esDocumento && !respuestasCambios[c.clave]);
        if (actualizacion && pendientes.length) return setError('Revise cada captura: marque Todo OK o registre las piezas con detalle.');
        if (actualizacion && cambios.some(c => c.pendiente && respuestasCambios[c.clave] === 'incidencia' && !afectadas.some(f => f.origen_cambio === c.clave))) return setError('Registre la pieza con detalle o marque Todo OK en su captura.');
        if (afectadas.some(p => !p.descripcion.trim())) return setError('Identifica el producto de cada unidad reportada.');
        if (afectadas.some(p => p.condicion === 'detalle' && !p.unica_pieza && !p.mejor_ejemplar && !p.detalle.trim())) return setError('Describe el defecto de la unidad reportada.');
        if (afectadas.some(p => p.condicion === 'detalle' && !p.archivos?.length)) return setError('Adjunta una foto de cada producto con detalle.');
        if (!archivos.length) return setError('Adjunta evidencia en Captura del pedido.');
        setOcupado(true);
        try {
            const form = formDataDe({ confirmacion: actualizacion ? afectadas.length === 0 : estado === 'correcto', excepciones: afectadas, archivos });
            if (actualizacion) cambios.filter(c => c.pendiente && c.indice !== undefined).forEach((c, i) => {
                form.append(`cambios_revisados[${i}][tipo]`, c.tipo);
                form.append(`cambios_revisados[${i}][indice]`, String(c.indice));
                form.append(`cambios_revisados[${i}][resultado]`, respuestasCambios[c.clave] || 'correcto');
            });
            if (revision?.actualizacion_id) form.append('actualizacion_id', revision.actualizacion_id);
            await onEnviar?.(form);
            setEnviada(true);
        } catch (e) {
            const errores = e?.response?.data?.errors;
            setError(errores ? Object.values(errores).flat().join(' ') : e?.response?.data?.message || 'No se pudo registrar la revisión.');
        } finally { setOcupado(false); }
    };
    if (modo === 'dialogo' && !abierto) return null;
    const resultado = piezas.length && !actualizacion ? piezas : actualizacion || estado === 'detalle' ? excepciones : [];
    const contenido = <section className="gelia-preparacion gelia-cedis-disponibilidad" aria-label={`Consulta de disponibilidad en ${origen}`}>
        <header className="gelia-preparacion-contexto">
            <div className="min-w-0">
                <div className="registrar-tienda-seccion-titulo"><ClipboardCheck size={18} aria-hidden /><h3>{actualizacion ? 'Actualización de consulta' : 'Disponibilidad'} · {origen}</h3></div>
                <dl className="gelia-preparacion-meta">
                    {pedido.cliente?.nombre && <div><dt>Cliente</dt><dd>{pedido.cliente.nombre}</dd></div>}

                </dl>
            </div>
            {origen === 'CEDIS' && <RutaPedidoTienda origen={pedido.almacen?.nombre || origen} destino={destino} />}
            <button className={GELIA_BTN_OUTLINE} type="button" disabled={!referencias.length} onClick={() => setVista({ documentos: referencias, indice: 0 })}><FileText size={16} aria-hidden />Ver pedido{referencias.length > 1 ? ` (${referencias.length})` : ''}</button>
        </header>
        <div className="gelia-preparacion-workspace" data-pedido-visible="false">
            <div className="gelia-preparacion-captura space-y-4">
                {actualizacion ? <ResumenCambiosDisponibilidad solicitud={solicitud} documentos={pedido.documentos || documentos} interactivo={editable}
                    respuestas={respuestasCambios} onResponder={responderCambio} onIncidencia={reportarCambio} disabled={guardando}
                    onVer={(documentos, indice) => setVista({ documentos, indice })} />
                    : mostrarCambios && <AlternativasDisponibilidadCedis pedido={pedido} onVer={(documentos, indice) => setVista({ documentos, indice })} />}

                {actualizacion && !respondida && <p className="text-sm theme-text-main m-0" role="status">Revise las capturas y use las acciones de cada solicitud. Al terminar, tome una nueva captura del pedido completo.</p>}
                {respondida ? <div className="grid gap-3" role="status">
                    <span className="gelia-estado-vivo gelia-estado-vivo--compacto gelia-estado-vivo--exito justify-self-start">Consulta respondida</span>
                    <p className="text-sm theme-text-muted m-0">Ventas confirma las piezas con el cliente.</p>
                    {resultado.length > 0 && <ul className="list-none p-0 m-0 space-y-2">{resultado.map((p, i) => {
                        const fotos = p.previews || (pedido.documentos || []).filter(d => d.activo !== false && d.relacion_tipo === 'revision_producto' && Number(d.relacion_id) === Number(p.id)).map(d => ({ url: d.url, name: d.nombre_original, mime: d.mime_type, remoto: true }));
                        return <li key={p.id || i} className="gelia-cedis-panel"><details open={resultado.length <= 3 ? true : undefined}><summary className="gelia-cedis-producto-resumen"><span><strong>{p.descripcion}</strong><span className="block text-xs theme-text-muted">{p.sku}{Number(p.cantidad) > 1 ? ` · ${p.cantidad} pza(s)` : ''}</span><EtiquetasPieza fila={p} /></span><ChevronDown size={18} aria-hidden /></summary><p className="text-sm">{p.detalle}</p>{fotos.length > 0 && <GaleriaEvidenciasPedido compacto label="Fotos del defecto" previews={fotos} disabled onVer={(docs, indice) => setVista({ documentos: docs, indice })} />}</details></li>;
                    })}</ul>}
                </div> : !editable ? <p className="text-sm theme-text-muted">Toma la revisión para responder.</p> : <>
                    {!actualizacion && <fieldset className="gelia-cedis-panel grid gap-2 m-0">
                        <legend className="text-sm font-semibold theme-text-main mb-2">Estado del pedido</legend>
                        {[['correcto', 'Todo OK'], ['detalle', 'Con detalle o faltante']].map(([valor, texto]) => <label key={valor} className="gelia-cedis-opcion flex items-center gap-3 border theme-border rounded-xl p-3 text-sm theme-text-main" data-seleccionado={estado === valor}>
                            <input type="radio" name="estado-disponibilidad-cedis" className="gelia-checkbox" checked={estado === valor} disabled={guardando} onChange={() => setEstado(valor)} />{texto}
                        </label>)}
                    </fieldset>}
                    {(estado === 'detalle' || actualizacion && excepciones.length > 0) && <section ref={incidenciasRef} className="gelia-cedis-panel grid gap-3" aria-label="Productos con detalle o faltantes">
                        <div className="registrar-tienda-seccion-titulo"><Package size={18} aria-hidden /><h3>{actualizacion ? 'Productos con detalle' : 'Productos con detalle o faltantes'}</h3><span className="text-xs theme-text-muted">{excepciones.length}</span></div>
                        {origenIncidencia && <p className="consulta-incidencia-origen"><FileText size={16} aria-hidden />{origenIncidencia.esDocumento ? 'Piezas de la captura seleccionada' : origenIncidencia.descripcion}</p>}
                        <p className="text-xs theme-text-muted m-0">Registra solo las piezas afectadas.</p>
                        {actualizacion ? <button type="button" className={`${GELIA_BTN_OUTLINE} justify-self-start`} disabled={guardando} onClick={() => setCapturaRapida({ origen: origenIncidencia })}><Plus size={16} aria-hidden />Agregar productos con detalle</button> : <>
                        <div>
                            <label className={THEME_LABEL} htmlFor="cedis-buscar-producto">Buscar o escanear producto</label>
                            <SelectorProducto inputId="cedis-buscar-producto" almacenId={almacenId} incluirSinInventario value="" onChange={(_, elegido) => { if (elegido) agregar(elegido); }} onEscanear={capturarCodigo} disabled={ocupado} />
                            <p className="text-xs theme-text-muted mt-2 mb-0">Cada lectura o selección registra una unidad.</p>
                        </div>
                        {escaneo.ultimo && <p className="text-xs theme-text-muted m-0" role="status">{escaneo.ultimo}</p>}
                        {escaneo.fallos.map((fallo, i) => <p key={i} role="alert" className="text-sm theme-text-peligro">{fallo.codigo}: {fallo.mensaje}</p>)}
                        <button type="button" className={`${GELIA_BTN_OUTLINE} justify-self-start`} disabled={guardando} onClick={() => { setManual(true); agregar({ descripcion: '', sku: '' }); }}><Plus size={16} aria-hidden />Capturar sin catálogo</button>
                        </>}
                        <p className="text-xs theme-text-muted m-0">Abre una unidad para registrar sus detalles.</p>
                        {excepciones.map((fila, i) => <PiezaExcepcionCedis key={fila.clave} fila={fila} indice={i} repeticion={repeticionDe(excepciones, fila)} manual={manual} disabled={guardando} onChange={cambios => cambiar(i, cambios)} onQuitar={() => quitar(i)} onVer={(docs, indice) => setVista({ documentos: docs, indice })} />)}
                    </section>}

                </>}
                {(actualizacion || estado || respondida) && <section className="gelia-cedis-panel" aria-label="Captura del pedido"><GaleriaEvidenciasPedido compacto accionesCamara id="cedis-evidencias-disponibilidad" label="Captura del pedido" obligatorio={!respondida} maxArchivos={8} archivos={archivos} previews={previews} disabled={guardando || respondida} onChange={(files, vistas) => { setArchivos(files); setPreviews(vistas); }} onVer={(docs, indice) => setVista({ documentos: docs, indice })} /></section>}
            </div>

        </div>
        <footer className="gelia-preparacion-footer">
            <div className="gelia-cedis-alerta min-w-0 text-sm" data-error={Boolean(error)} role={error ? 'alert' : 'status'}><AlertTriangle size={18} className="shrink-0" aria-hidden /><span>{error || (respondida ? 'Respuesta enviada a Ventas.' : 'Consulta de disponibilidad · sin confirmar apartado.')}</span></div>
            {editable && <button type="button" className={`${THEME_BTN_PRIMARY} shrink-0`} disabled={guardando} onClick={enviar}>{guardando ? 'Procesando…' : 'Enviar respuesta'}</button>}
        </footer>
        {capturaRapida && <ModalSeleccionProductosDetalle almacenId={almacenId} origen={capturaRapida.origen} onClose={() => setCapturaRapida(null)} onAgregar={agregarSeleccionDetalle} />}
        <ModalVistaPreviaDocumento abierto={Boolean(vista)} {...vista} onClose={() => setVista(null)} accionesDocumento={actualizacion && editable ? documento => {
            const cambio = cambios.find(c => c.pendiente && c.documentos.some(d => d.id === documento.id));
            if (!cambio) return null;
            return <div className="consulta-visor-acciones">
                <button type="button" className={GELIA_BTN_OUTLINE} data-respuesta="correcto" onClick={() => { responderCambio(cambio, 'correcto'); setVista(null); }}><CheckCircle2 size={16} aria-hidden />{cambio.tipo === 'retiro' ? 'Retiro revisado' : 'Todo OK'}</button>
                {cambio.tipo !== 'retiro' && <button type="button" className={GELIA_BTN_OUTLINE} onClick={() => { reportarCambio(cambio, 'detalle'); setVista(null); }}><AlertTriangle size={16} aria-hidden />Registrar detalle</button>}
            </div>;
        } : null} />
    </section>;
    if (modo !== 'dialogo') return contenido;
    return createPortal(<div className={`${THEME_MODAL_OVERLAY} p-0 md:p-4`}>
        <div {...dialogo} aria-label="Consulta de disponibilidad" className={`${THEME_MODAL_SHELL} gelia-preparacion-modal w-full max-w-7xl max-h-[100dvh] md:max-h-[94dvh] flex flex-col overflow-hidden`}>
            <header className="flex items-center justify-between gap-3 px-4 py-3 border-b theme-border shrink-0"><div className="min-w-0"><h2 className="text-base font-semibold theme-text-main m-0">Consulta de disponibilidad</h2>{(pedido.folio_remision || pedido.folio) && <p className="text-xs theme-text-muted m-0">Folio No. : {pedido.folio_remision || pedido.folio}</p>}</div><button type="button" className={THEME_BTN_SECONDARY} disabled={guardando} onClick={onClose} aria-label="Cerrar consulta"><X size={20} /></button></header>
            <div className="gelia-modal-body min-h-0 overflow-y-auto">{contenido}</div>
        </div>
    </div>, document.body);
}

function liberarPreviews(previews = []) {
    previews.forEach(p => { if (p.url?.startsWith('blob:')) URL.revokeObjectURL(p.url); });
}

function repeticionDe(filas, fila) {
    if (!fila.sku) return null;
    const repetidas = filas.filter(f => f.sku === fila.sku);
    return repetidas.length > 1 ? `${repetidas.indexOf(fila) + 1}/${repetidas.length}` : null;
}

function EtiquetasPieza({ fila }) {
    const faltante = fila.condicion === 'faltante';
    return <span className="gelia-cedis-etiquetas">
        <span className="gelia-cedis-etiqueta" data-tipo={faltante ? 'faltante' : 'detalle'}>{faltante ? 'Sin existencias' : 'Con detalle'}</span>
        {!faltante && fila.unica_pieza && <span className="gelia-cedis-etiqueta">Única pieza</span>}
        {!faltante && fila.mejor_ejemplar && <span className="gelia-cedis-etiqueta">Mejor ejemplar</span>}
        {!faltante && fila.previews?.length > 0 && <span className="gelia-cedis-etiqueta">{fila.previews.length}/5 fotos</span>}
    </span>;
}

function PiezaExcepcionCedis({ fila, indice, repeticion, manual, disabled, onChange, onQuitar, onVer }) {
    const [expandida, setExpandida] = useState(Boolean(fila.abrir_detalle) || manual && !fila.sku);
    const capturaRef = useRef(null);
    const sinExistencias = fila.condicion === 'faltante';
    const limiteFotos = fila.previews.length >= 5;
    return <article className="gelia-cedis-producto">
        <button type="button" className="gelia-cedis-foto" disabled={disabled || sinExistencias || limiteFotos} aria-label={`Agregar evidencia del producto ${indice + 1}`} title={sinExistencias ? 'Sin existencias: no requiere foto' : limiteFotos ? 'Máximo de 5 fotos por producto' : 'Tomar foto del producto'} onClick={() => capturaRef.current?.tomarFoto()}>
            {fila.previews[0] ? <img src={fila.previews[0].url} alt="Foto del defecto" /> : <Camera size={22} aria-hidden />}
            <Plus size={14} className="gelia-cedis-foto-mas" aria-hidden />
        </button>
        <details open={expandida} onToggle={e => setExpandida(e.currentTarget.open)} className="min-w-0">
            <summary className="gelia-cedis-producto-resumen">
                <ChevronDown size={16} className="gelia-cedis-desplegar" aria-hidden />
                <span className="min-w-0">
                    <span className="gelia-cedis-producto-identidad">{repeticion && <span className="gelia-cedis-unidad">{repeticion}</span>}<strong title={`${fila.sku} — ${fila.descripcion}`}>{fila.sku ? `${fila.sku} — ` : ''}{fila.descripcion || 'Pieza por identificar'}</strong></span>
                    <EtiquetasPieza fila={fila} />
                </span>
            </summary>
            <div className="grid gap-3 pt-3">
                {manual && !fila.sku && <label className={THEME_LABEL}>Pieza<input className={`${THEME_INPUT} w-full`} maxLength={255} value={fila.descripcion} disabled={disabled} onChange={e => onChange({ descripcion: e.target.value })} /></label>}
                <div className="gelia-cedis-interruptores" aria-label={`Estados del producto ${indice + 1}`}>
                    {[['unica_pieza', 'Única pieza'], ['mejor_ejemplar', 'Mejor ejemplar'], ['faltante', 'Sin existencias']].map(([campo, etiqueta]) => {
                        const activo = campo === 'faltante' ? sinExistencias : Boolean(fila[campo]);
                        return <button key={campo} type="button" role="switch" aria-checked={activo} aria-label={etiqueta} disabled={disabled || (sinExistencias && campo !== 'faltante')} onClick={() => onChange(campo === 'faltante' ? { condicion: activo ? 'detalle' : 'faltante' } : { [campo]: !activo })}>
                            <span className="gelia-switch pointer-events-none shrink-0" data-active={activo ? 'true' : 'false'}><span className="gelia-switch-thumb" /></span><span>{etiqueta}</span>
                        </button>;
                    })}
                </div>
                {!sinExistencias && <>
                    <label className={THEME_LABEL}>{fila.unica_pieza || fila.mejor_ejemplar ? 'Observaciones (opcional)' : 'Detalle del defecto'}<textarea className={`${THEME_TEXTAREA} w-full`} rows={2} maxLength={500} value={fila.detalle} disabled={disabled} onChange={e => onChange({ detalle: e.target.value })} /></label>
                    <GaleriaEvidenciasPedido compacto capturaExterna controlRef={capturaRef} soloImagenes label="Fotos del producto" maxArchivos={5} archivos={fila.archivos} previews={fila.previews} disabled={disabled} onChange={(archivos, previews) => onChange({ archivos, previews })} onVer={onVer} />
                </>}
            </div>
        </details>
        <button type="button" className={`${GELIA_BTN_OUTLINE} gelia-cedis-quitar`} disabled={disabled} aria-label={`Quitar pieza ${indice + 1}`} title="Quitar de la lista" onClick={onQuitar}><Trash2 size={16} aria-hidden /></button>
    </article>;
}
