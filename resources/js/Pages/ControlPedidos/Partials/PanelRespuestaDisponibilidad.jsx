import React, { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { ClipboardCheck, FileText, ScanLine, Trash2, X } from 'lucide-react';
import usePedidoDialog from './usePedidoDialog';
import SelectorProducto from '../../../Components/Almacenes/SelectorProducto';
import DocumentoSolicitudTienda from '../Tienda/Partials/DocumentoSolicitudTienda';
import useEscaneoPreparacion from '../Tienda/Partials/useEscaneoPreparacion';
import GaleriaEvidenciasPedido from './GaleriaEvidenciasPedido';
import ModalVistaPreviaDocumento from './ModalVistaPreviaDocumento';
import { THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, THEME_SELECT, THEME_TEXTAREA } from '../../../utils/geliaTheme';

function formDataDe({ confirmacion, excepciones, archivos }) {
    const form = new FormData();
    form.append('confirmacion_explicita', confirmacion ? '1' : '0');
    excepciones.forEach((pieza, indice) => {
        for (const campo of ['descripcion', 'sku', 'cantidad', 'condicion', 'detalle']) {
            form.append(`excepciones[${indice}][${campo}]`, String(pieza[campo] || ''));
        }
    });
    archivos.forEach(archivo => form.append('evidencias[]', archivo));
    return form;
}

export default function PanelRespuestaDisponibilidad({ modo = 'seccion', abierto = true, onClose, pedido = {}, documentos = [], revision = null, piezas = [], puedeResponder = false, tomada = false, onEnviar }) {
    const [estado, setEstado] = useState('');
    const [excepciones, setExcepciones] = useState([]);
    const [producto, setProducto] = useState(null);
    const [codigo, setCodigo] = useState('');
    const [manual, setManual] = useState(false);
    const [archivos, setArchivos] = useState([]);
    const [previews, setPreviews] = useState(() => (pedido.documentos || []).filter(d => d.tipo === 'evidencia_condicion' && d.url && d.activo !== false && (!d.relacion_tipo || d.relacion_tipo === 'revision_general')).map(d => ({ url: d.url, name: d.nombre_original, mime: d.mime_type, remoto: true })));
    const documentoRef = useRef(null);
    const [vista, setVista] = useState(null);
    const [mostrarPedido, setMostrarPedido] = useState(() => !window.matchMedia?.('(max-width: 760px)').matches);
    const [error, setError] = useState('');
    const [ocupado, setOcupado] = useState(false);
    const [enviada, setEnviada] = useState(false);
    const previewsRef = useRef([]);
    previewsRef.current = previews;
    useEffect(() => () => previewsRef.current.forEach(p => { if (p.url?.startsWith('blob:')) URL.revokeObjectURL(p.url); }), []);
    const almacenId = pedido.almacen?.id || pedido.almacen_id;
    const agregar = (elegido = producto) => {
        if (!elegido) return;
        setExcepciones(actual => {
            const indice = actual.findIndex(fila => elegido.sku && fila.sku === elegido.sku);
            if (indice >= 0) return actual.map((fila, i) => i === indice ? { ...fila, cantidad: Number(fila.cantidad) + 1 } : fila);
            return [...actual, { descripcion: elegido.descripcion || '', sku: elegido.sku || '', cantidad: 1, condicion: 'faltante', detalle: 'Sin existencias' }];
        });
        setProducto(null);
    };
    const escaneo = useEscaneoPreparacion({ almacenId, onProducto: agregar });
    const guardando = ocupado || escaneo.pendientes > 0;
    const respondida = Boolean(revision?.estado || enviada);
    const editable = tomada && puedeResponder && !respondida;
    const dialogo = usePedidoDialog({ abierto: modo === 'dialogo' && abierto, onClose, bloqueado: guardando });
    const cambiar = (indice, campo, valor) => setExcepciones(actual => actual.map((fila, i) => i !== indice ? fila : {
        ...fila, [campo]: valor,
        ...(campo === 'condicion' ? { detalle: valor === 'faltante' ? 'Sin existencias' : fila.detalle === 'Sin existencias' ? '' : fila.detalle } : {}),
    }));
    const enviar = async () => {
        if (guardando || !editable) return;
        setError('');
        const afectadas = estado === 'detalle' ? excepciones : [];
        if (!estado) return setError('Indica el estado del pedido.');
        if (estado === 'detalle' && !afectadas.length) return setError('Agrega las piezas con detalle o faltante.');
        if (afectadas.some(p => !p.descripcion.trim() || !Number.isInteger(Number(p.cantidad)) || Number(p.cantidad) < 1 || !p.detalle.trim())) return setError('Revisa la pieza, la cantidad y el detalle.');
        if (!archivos.length) return setError('Adjunta evidencia de la revisión.');
        setOcupado(true);
        try {
            await onEnviar?.(formDataDe({ confirmacion: estado === 'correcto', excepciones: afectadas, archivos }));
            setEnviada(true);
        } catch (e) {
            const errores = e?.response?.data?.errors;
            setError(errores ? Object.values(errores).flat().join(' ') : e?.response?.data?.message || 'No se pudo registrar la revisión.');
        } finally { setOcupado(false); }
    };
    if (modo === 'dialogo' && !abierto) return null;
    const resultado = piezas.length ? piezas : estado === 'detalle' ? excepciones : [];
    const contenido = <section className="gelia-preparacion" aria-label="Consulta de disponibilidad en CEDIS">
        <header className="gelia-preparacion-contexto">
            <div className="min-w-0">
                <div className="registrar-tienda-seccion-titulo"><ClipboardCheck size={18} aria-hidden /><h3>Disponibilidad · CEDIS</h3></div>
                <dl className="gelia-preparacion-meta">
                    {pedido.cliente?.nombre && <div><dt>Cliente</dt><dd>{pedido.cliente.nombre}</dd></div>}
                    <div><dt>Origen</dt><dd>{pedido.almacen?.nombre || 'CEDIS'}</dd></div>
                </dl>
            </div>
            <button className={THEME_BTN_SECONDARY} type="button" aria-expanded={mostrarPedido} aria-controls="cedis-documento-referencia" onClick={() => { setMostrarPedido(!mostrarPedido); if (!mostrarPedido && window.matchMedia?.('(max-width: 760px)').matches) requestAnimationFrame(() => documentoRef.current?.scrollIntoView({ block: 'start' })); }}><FileText size={16} aria-hidden />{mostrarPedido ? 'Ocultar pedido' : 'Ver pedido'}</button>
        </header>
        <div className="gelia-preparacion-workspace" data-pedido-visible={mostrarPedido}>
            <div className="gelia-preparacion-captura space-y-4">
                {respondida ? <div className="grid gap-3" role="status">
                    <span className="gelia-estado-vivo gelia-estado-vivo--compacto gelia-estado-vivo--exito justify-self-start">Consulta respondida</span>
                    <p className="text-sm theme-text-muted m-0">Ventas confirma las piezas con el cliente.</p>
                    {resultado.length > 0 && <ul className="list-none p-0 m-0 space-y-2">{resultado.map((p, i) => <li key={p.id || i} className="gelia-preparacion-producto"><strong>{p.descripcion}</strong><span className="block text-xs theme-text-muted">{p.sku}</span><span className="text-sm">{p.cantidad} · {p.detalle}</span></li>)}</ul>}
                </div> : !editable ? <p className="text-sm theme-text-muted">Toma la revisión para responder.</p> : <>
                    <fieldset className="grid gap-2 m-0 border-0 p-0">
                        <legend className="text-sm font-semibold theme-text-main mb-2">Estado del pedido</legend>
                        {[['correcto', 'Sin incidencias'], ['detalle', 'Con detalle o faltante']].map(([valor, texto]) => <label key={valor} className="flex items-center gap-3 border theme-border rounded-xl p-3 text-sm theme-text-main">
                            <input type="radio" name="estado-disponibilidad-cedis" className="gelia-checkbox" checked={estado === valor} disabled={guardando} onChange={() => setEstado(valor)} />{texto}
                        </label>)}
                    </fieldset>
                    {estado === 'detalle' && <div className="grid gap-3">
                        <p className="text-xs theme-text-muted m-0">Registra solo las piezas afectadas.</p>
                        {almacenId && <form className="flex gap-2" onSubmit={e => { e.preventDefault(); escaneo.encolar(codigo); setCodigo(''); }}>
                            <label className="theme-field-with-icon flex-1 min-w-0"><ScanLine className="theme-field-icon" aria-hidden /><input aria-label="Escanear código" placeholder="Escanea un código" className={`${THEME_INPUT} w-full`} value={codigo} onChange={e => setCodigo(e.target.value)} disabled={ocupado} /></label>
                            <button className={THEME_BTN_SECONDARY} disabled={ocupado || !codigo.trim()}>Buscar</button>
                        </form>}
                        {escaneo.ultimo && <p className="text-xs theme-text-muted m-0" role="status">{escaneo.ultimo}</p>}
                        {escaneo.fallos.map((fallo, i) => <p key={i} role="alert" className="text-sm theme-text-peligro">{fallo.codigo}: {fallo.mensaje}</p>)}
                        <div className="flex gap-2 items-end">
                            <div className="min-w-0 flex-1"><label className={THEME_LABEL} htmlFor="cedis-buscar-producto">SKU / nombre</label><SelectorProducto inputId="cedis-buscar-producto" almacenId={almacenId} incluirSinInventario value={producto?.id || ''} onChange={(_, elegido) => setProducto(elegido)} disabled={guardando} /></div>
                            <button type="button" className={THEME_BTN_SECONDARY} disabled={guardando || !producto} onClick={() => agregar()}>Agregar</button>
                        </div>
                        <button type="button" className={`${THEME_BTN_SECONDARY} justify-self-start`} disabled={guardando} onClick={() => { setManual(true); agregar({ descripcion: '', sku: '' }); }}>Capturar sin catálogo</button>
                        {excepciones.map((fila, i) => <article key={i} className="gelia-preparacion-producto space-y-3">
                            <div className="flex justify-between gap-3 items-start"><div className="min-w-0"><strong className="text-sm break-words">{fila.descripcion || 'Pieza por identificar'}</strong>{fila.sku && <span className="block text-xs theme-text-muted">{fila.sku}</span>}</div><button type="button" className={THEME_BTN_SECONDARY} disabled={guardando} aria-label={`Quitar pieza ${i + 1}`} onClick={() => setExcepciones(actual => actual.filter((_, j) => j !== i))}><Trash2 size={16} aria-hidden /></button></div>
                            {manual && !fila.sku && <label className={`block ${THEME_LABEL}`}>Pieza<input className={`${THEME_INPUT} w-full`} maxLength={255} value={fila.descripcion} disabled={guardando} onChange={e => cambiar(i, 'descripcion', e.target.value)} /></label>}
                            <div className="grid grid-cols-2 gap-3">
                                <label className={THEME_LABEL}>Cantidad afectada<input className={`${THEME_INPUT} w-full`} type="number" min="1" step="1" value={fila.cantidad} disabled={guardando} onChange={e => cambiar(i, 'cantidad', e.target.value)} /></label>
                                <label className={THEME_LABEL}>Resultado<select className={`${THEME_SELECT} w-full`} value={fila.condicion} disabled={guardando} onChange={e => cambiar(i, 'condicion', e.target.value)}><option value="faltante">Sin existencias</option><option value="detalle">Con detalle</option></select></label>
                            </div>
                            {fila.condicion === 'detalle' && <label className={`block ${THEME_LABEL}`}>Detalle<textarea className={`${THEME_TEXTAREA} w-full`} rows={2} maxLength={500} value={fila.detalle} disabled={guardando} onChange={e => cambiar(i, 'detalle', e.target.value)} /></label>}
                        </article>)}
                    </div>}
                </>}
                {(estado || respondida) && <GaleriaEvidenciasPedido compacto id="cedis-evidencias-disponibilidad" label="Evidencia de revisión" obligatorio={!respondida} maxArchivos={8} archivos={archivos} previews={previews} disabled={guardando || respondida} onChange={(files, vistas) => { setArchivos(files); setPreviews(vistas); }} onVer={(docs, indice) => setVista({ documentos: docs, indice })} />}
            </div>
            {mostrarPedido && <aside ref={documentoRef} id="cedis-documento-referencia" className="gelia-preparacion-documento" aria-label="Pedido de referencia"><div className="md:hidden"><button type="button" className={`${THEME_BTN_SECONDARY} mb-3`} onClick={() => setMostrarPedido(false)}>Volver a la revisión</button></div><DocumentoSolicitudTienda documentos={documentos} /></aside>}
        </div>
        <footer className="gelia-preparacion-footer">
            <div className="min-w-0 text-sm" role={error ? 'alert' : 'status'}>{error || (respondida ? 'Respuesta enviada a Ventas.' : 'Consulta de disponibilidad · sin confirmar apartado.')}</div>
            {editable && <button type="button" className={`${THEME_BTN_PRIMARY} shrink-0`} disabled={guardando} onClick={enviar}>{guardando ? 'Procesando…' : 'Enviar respuesta'}</button>}
        </footer>
        <ModalVistaPreviaDocumento abierto={Boolean(vista)} {...vista} onClose={() => setVista(null)} />
    </section>;
    if (modo !== 'dialogo') return contenido;
    return createPortal(<div className={`${THEME_MODAL_OVERLAY} p-0 md:p-4`}>
        <div {...dialogo} aria-label="Consulta de disponibilidad" className={`${THEME_MODAL_SHELL} gelia-preparacion-modal w-full max-w-7xl max-h-[100dvh] md:max-h-[94dvh] flex flex-col overflow-hidden`}>
            <header className="flex items-center justify-between gap-3 px-4 py-3 border-b theme-border shrink-0"><div className="min-w-0"><h2 className="text-base font-semibold theme-text-main m-0">Consulta de disponibilidad</h2>{(pedido.folio_remision || pedido.folio) && <p className="text-xs theme-text-muted m-0">{pedido.folio_remision || pedido.folio}</p>}</div><button type="button" className={THEME_BTN_SECONDARY} disabled={guardando} onClick={onClose} aria-label="Cerrar consulta"><X size={20} /></button></header>
            <div className="gelia-modal-body min-h-0 overflow-y-auto">{contenido}</div>
        </div>
    </div>, document.body);
}
