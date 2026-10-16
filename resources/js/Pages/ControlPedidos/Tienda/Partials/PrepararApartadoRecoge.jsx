import React, { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { AlertTriangle, CheckCircle2, FileText, ScanLine, Smartphone, Trash2 } from 'lucide-react';
import useEscaneoPreparacion from './useEscaneoPreparacion';
import { faltantesRespuesta } from './requisitosRespuesta';
import ModalConfirmarAccion from '../../Partials/ModalConfirmarAccion';
import DocumentoSolicitudTienda from './DocumentoSolicitudTienda';
import SelectorProducto from '../../../../Components/Almacenes/SelectorProducto';
import InputConEscanner from '../../../../Components/Escanner/InputConEscanner';
import GaleriaEvidencias from '../../Partials/GaleriaEvidenciasPedido';
import ModalVistaPreviaDocumento from '../../Partials/ModalVistaPreviaDocumento';
import ModalSesionEvidenciaTienda from './ModalSesionEvidenciaTienda';
import { THEME_INPUT, THEME_LABEL, THEME_SELECT, THEME_TEXTAREA } from '../../../../utils/geliaTheme';
import { BTN_PRIMARY, BTN_SECONDARY, formatearFechaNegocio } from '../../Partials/pedidosBmaStyles';

function formData(datos) {
    const form = new FormData();
    const agregar = (valor, clave) => {
        if (valor === null || valor === undefined) return;
        if (valor instanceof File || valor instanceof Blob) form.append(clave, valor);
        else if (Array.isArray(valor)) valor.forEach((v, i) => agregar(v, `${clave}[${i}]`));
        else if (typeof valor === 'object') Object.entries(valor).forEach(([k, v]) => agregar(v, `${clave}[${k}]`));
        else form.append(clave, String(valor));
    };
    Object.entries(datos).forEach(([k, v]) => agregar(v, k));
    return form;
}

export default function PrepararApartadoRecoge({ tarea, auth, documentos = [], documentosTarea = [], requisitos = {}, tiposCaja = [], onEnviar = null, bloqueado = false, onDirtyChange = null, onRefrescarDocumentos = null }) {
    const esRecoge = tarea.modalidad?.codigo === 'RECOGE_TIENDA' || !tarea.modalidad;
    const permisos = auth?.user?.permissions || [];
    const esAdmin = auth?.user?.roles?.includes('Super Admin');
    const autorizado = (esRecoge ? tarea.puede_preparar : tarea.puede_responder) ?? ((esAdmin || permisos.includes('control_pedidos.tienda.responder') || (esRecoge && permisos.includes('control_pedidos.tienda.apartado.separar'))) && Number(tarea.responsable?.id) === Number(auth?.user?.id));
    const editable = tarea.estado === 'EN_ATENCION' && autorizado;
    const puedeIncidencia = tarea.puede_reportar_incidencia ?? (esAdmin || permisos.includes('control_pedidos.tienda.reportar_error'));
    const [lineas, setLineas] = useState(() => (tarea.productos || []).filter((p) => p.sku).map((p) => ({ id: p.id, producto_id: p.producto_id, sku: p.sku, descripcion: p.descripcion_snapshot, cantidad: p.cantidad_encontrada ?? '', estado_fisico: p.estado_fisico || 'bueno', observacion: p.observacion || '', evidencias: [], previews: [], detalle: p.estado_fisico && p.estado_fisico !== 'bueno' })));
    const [seleccion, setSeleccion] = useState(null);
    const [codigo, setCodigo] = useState('');
    const [motivo, setMotivo] = useState('');
    const [modoIncidencia, setModoIncidencia] = useState(false);
    const [extra, setExtra] = useState({ peso_real_kg: tarea.peso_real_kg || '', peso_volumetrico_kg: tarea.peso_volumetrico_kg || '', catalogo_tipo_caja_id: tarea.catalogo_tipo_caja_id || '', observaciones_fisicas: tarea.observaciones_fisicas || '', observaciones_respuesta: tarea.observaciones_respuesta || '' });
    const [evidencias, setEvidencias] = useState({ archivos: [], previews: [] });
    const [procesando, setProcesando] = useState(false);
    const [error, setError] = useState('');
    const [galeria, setGaleria] = useState({ abierto: false, documentos: [], indice: 0 });
    const [dirty, setDirty] = useState(false);
    const [mostrarPedido, setMostrarPedido] = useState(() => !window.matchMedia?.('(max-width: 760px)').matches);
    const [validando, setValidando] = useState(false);
    const [quitarLinea, setQuitarLinea] = useState(null);
    const [modalQr, setModalQr] = useState(false);
    const ocupado = procesando || bloqueado;
    const vence = tarea.fecha_limite || tarea.apartado?.vence_at;
    const vencida = esRecoge && Boolean(vence && new Date(vence).getTime() <= Date.now());
    const formRef = useRef(null);
    const previewsRef = useRef([]);
    previewsRef.current = [...lineas.flatMap((l) => l.previews), ...evidencias.previews];
    useEffect(() => () => previewsRef.current.forEach((p) => { if (p.url?.startsWith('blob:')) URL.revokeObjectURL(p.url); }), []);
    const cambiar = (i, campo, valor) => { setDirty(true); setLineas((prev) => prev.map((l, j) => j === i ? { ...l, [campo]: valor } : l)); };
    const agregar = (producto = seleccion) => {
        if (!producto || ocupado) return;
        setDirty(true);
        setLineas((prev) => {
            const i = prev.findIndex((l) => (l.producto_id && Number(l.producto_id) === Number(producto.id)) || String(l.sku).toLowerCase() === String(producto.sku).toLowerCase());
            return i >= 0 ? prev.map((l, j) => j === i ? { ...l, cantidad: (Number(l.cantidad) || 0) + 1 } : l)
                : [...prev, { producto_id: producto.id, sku: producto.sku, descripcion: producto.descripcion, cantidad: 1, estado_fisico: 'bueno', observacion: '', evidencias: [], previews: [], detalle: false }];
        });
        setSeleccion(null);
    };
    const escaneo = useEscaneoPreparacion({ almacenId: tarea.almacen?.id, onProducto: agregar });
    useEffect(() => { onDirtyChange?.(dirty || escaneo.pendientes > 0); }, [dirty, escaneo.pendientes, onDirtyChange]);
    const guardando = ocupado || escaneo.pendientes > 0;
    const faltantes = faltantesRespuesta({ lineas, esRecoge, extra, requisitos, documentos: documentosTarea, evidencias: evidencias.archivos });
    const irACampo = (id) => {
        const campo = formRef.current?.querySelector(`[id="${id}"]`);
        campo?.scrollIntoView?.({ block: 'nearest', behavior: 'auto' });
        campo?.focus();
    };
    const atributosCampo = (id) => ({ id, 'aria-invalid': validando && faltantes.some((f) => f.campo === id), 'aria-describedby': validando && faltantes.some((f) => f.campo === id) ? `${id}-error` : undefined });
    const errorCampo = (id) => validando && faltantes.find((f) => f.campo === id) && <p id={`${id}-error`} className="gelia-preparacion-error">{faltantes.find((f) => f.campo === id).texto}</p>;
    const capturarCodigo = (valor) => {
        if (ocupado || !editable) return;
        escaneo.encolar(valor);
        setCodigo('');
    };
    const guardar = (confirmar = false, incidencia = false) => {
        if (guardando) return;
        setError('');
        if (confirmar) {
            setValidando(true);
            if (vencida) { setError('La solicitud venció. Reporte el problema a Ventas para renovarla.'); return; }
            if (faltantes.length) { setError('Complete los campos señalados antes de enviar la respuesta.'); irACampo(faltantes[0].campo); return; }
        }
        if (!lineas.length && !incidencia) { setError('Agregue los productos preparados o reporte el problema.'); return; }
        if (incidencia && !motivo.trim()) { setError('Describa qué necesita resolver Ventas.'); irACampo('tienda-motivo'); return; }
        setProcesando(true);
        const datos = { version: esRecoge ? tarea.apartado?.version : tarea.version,
            lineas: lineas.map(({ previews, detalle, ...l }) => l), motivo_incidencia: incidencia ? motivo : undefined,
            ...(!esRecoge ? extra : {}), evidencias: evidencias.archivos };
        const tieneArchivos = lineas.some((l) => l.evidencias.length) || evidencias.archivos.length;
        const payload = tieneArchivos ? formData(datos) : datos;
        const nombreRuta = esRecoge ? (confirmar ? 'control_pedidos.tienda.apartado.confirmar' : 'control_pedidos.tienda.apartado.guardar')
            : confirmar ? 'control_pedidos.tienda.responder' : 'control_pedidos.tienda.preparacion.guardar';
        const opciones = { preserveScroll: true, onFinish: () => setProcesando(false), onSuccess: () => { setDirty(false); setValidando(false); onDirtyChange?.(false); }, onError: (errores) => setError(Object.values(errores).flat().join(' ')) };
        if (onEnviar) onEnviar(route(nombreRuta, tarea.id), payload, opciones);
        else router.post(route(nombreRuta, tarea.id), payload, opciones);
    };
    const total = lineas.reduce((sum, l) => sum + (Number(l.cantidad) || 0), 0);
    const verDocs = (docs, indice = 0) => setGaleria({ abierto: true, documentos: docs, indice });
    const subirDocumentoMunicipal = async (tipo, archivo) => {
        if (!archivo || ocupado) return;
        setProcesando(true); setError('');
        try {
            const form = new FormData(); form.append('tipo', tipo); form.append('archivo', archivo);
            await axios.post(route('control_pedidos.tienda.documento_municipal.store', tarea.id), form, { headers: { Accept: 'application/json' } });
            if (onRefrescarDocumentos) await onRefrescarDocumentos();
            else router.reload({ only: ['documentos'], preserveScroll: true });
        } catch (e) { setError(e.response?.data?.message || 'No se pudo guardar el documento municipal.'); }
        finally { setProcesando(false); }
    };
    const fotosGenerales = documentosTarea.filter((d) => ['evidencia_general', 'foto_apartado'].includes(d.tipo_evidencia) && !d.pedido_bma_tarea_producto_id);
    const puedeFotosCelular = esAdmin || permisos.includes('control_pedidos.tienda.evidencias');
    const conPesaje = !esRecoge && (requisitos.peso_real_obligatorio || requisitos.peso_volumetrico_obligatorio || requisitos.caja_obligatoria);
    const actualizarExtra = (campo, valor) => { setDirty(true); setExtra((prev) => ({ ...prev, [campo]: valor })); };
    const tituloCierre = esRecoge ? 'Confirmación' : conPesaje ? 'Pesaje y respuesta' : 'Revisar y responder';
    const evidenciaGeneralObligatoria = !esRecoge && requisitos.evidencia_general_obligatoria;
    const fotosProducto = (l, i) => <GaleriaEvidencias id={`tienda-fotos-${i}`} label="Evidencia del producto" obligatorio={!esRecoge && requisitos.evidencia_por_producto}
        maxArchivos={10} archivos={l.evidencias} previews={l.previews} error={validando ? faltantes.find((f) => f.campo === `tienda-fotos-${i}`)?.texto : undefined}
        onChange={(files, previews) => { cambiar(i, 'evidencias', files); cambiar(i, 'previews', previews); }} onVer={verDocs} disabled={ocupado} />;

    const alternarPedido = () => {
        setMostrarPedido((v) => !v);
        if (!mostrarPedido && window.matchMedia?.('(max-width: 760px)').matches) {
            requestAnimationFrame(() => {
                const referencia = document.getElementById('tienda-pedido-referencia');
                referencia?.scrollIntoView({ block: 'start' });
                referencia?.focus({ preventScroll: true });
            });
        }
    };

    return <section className="gelia-preparacion" aria-label="Espacio de preparación">
        <header className="gelia-preparacion-contexto">
            <div className="min-w-0">
                <h3 className="text-sm font-semibold m-0">{esRecoge ? 'Separación y apartado en tienda' : conPesaje ? 'Preparación para pesaje' : 'Separación de mercancía'}</h3>
                <dl className="gelia-preparacion-meta">
                    <div><dt>Almacén</dt><dd>{tarea.almacen?.nombre || tarea.almacen?.codigo || 'No asignado'}</dd></div>
                    {tarea.pedido?.cliente_nombre && <div><dt>Cliente</dt><dd>{tarea.pedido.cliente_nombre}</dd></div>}
                </dl>
            </div>
            <div className="gelia-preparacion-contexto-acciones">
                <button type="button" className={BTN_SECONDARY} aria-expanded={mostrarPedido} aria-controls="tienda-pedido-referencia" onClick={alternarPedido}>
                    <FileText className="w-4 h-4" aria-hidden="true" />{mostrarPedido ? 'Ocultar pedido' : 'Ver pedido'}
                </button>
            </div>
        </header>
        {vencida && <div role="alert" className="gelia-preparacion-aviso"><AlertTriangle className="w-4 h-4 shrink-0" aria-hidden="true" /><div><strong>Solicitud vencida · {formatearFechaNegocio(vence)}</strong><p className="m-0">Puede guardar su avance. Reporte a Ventas para solicitar una nueva preparación del día.</p></div></div>}
        {error && <p role="alert" className="gelia-preparacion-aviso">{error}</p>}
        <div className="gelia-preparacion-workspace" data-pedido-visible={mostrarPedido}>
            <div className="gelia-preparacion-captura" ref={formRef}>
                <details className="gelia-preparacion-opciones">
                    <summary>Datos y opciones de preparación</summary>
                    {tarea.pedido?.vendedora_nombre && <p className="text-sm">Vendedora: {tarea.pedido.vendedora_nombre}</p>}
                    {editable && <a className={`${BTN_SECONDARY} inline-flex min-h-[44px] items-center`} href={route('traspasos.abastecimiento.index', { tarea: tarea.id })}>Abastecer preparación</a>}
                    {editable && puedeIncidencia && <button type="button" className={BTN_SECONDARY} disabled={ocupado} onClick={() => { setModoIncidencia(true); requestAnimationFrame(() => irACampo('tienda-motivo')); }}><AlertTriangle className="w-4 h-4" aria-hidden="true" />Reportar problema</button>}
                </details>
                {tarea.abastecimiento_aviso && <p role="status" className="theme-text-aviso">{tarea.abastecimiento_aviso}</p>}
                {tarea.abastecimiento_pendiente && <p role="status" className="theme-text-aviso">Hay abastecimiento pendiente de salida, llegada o resolución de incidencias. Confirme las llegadas antes de cerrar la preparación.</p>}
                {tarea.observaciones_solicitud && <aside className="gelia-preparacion-instrucciones"><h4>Indicaciones de Ventas</h4><p>{tarea.observaciones_solicitud}</p></aside>}
                {editable && <nav className="gelia-preparacion-guia" aria-label="Pasos para responder">
                    {[['tienda-productos', 'Productos'], ['tienda-evidencias', 'Evidencias'], ['tienda-cierre', 'Cierre']].map(([id, label], i) => <button key={id} type="button" onClick={() => irACampo(id)}><span>{i + 1}</span>{label}</button>)}
                </nav>}
                {tarea.estado === 'EN_ATENCION' && !autorizado && <p role="status" className="text-sm theme-text-muted">Esta tarea está asignada a {tarea.responsable?.name || 'otro auxiliar'}. Solo su responsable puede responder.</p>}
                {tarea.estado === 'PENDIENTE' && <p className="text-sm theme-text-muted">Seleccione «Atender pedido» para comenzar la captura.</p>}
                <section id="tienda-productos" tabIndex={-1} className="gelia-preparacion-seccion">
                    <div className="gelia-preparacion-seccion-titulo"><h4>1. Registrar productos</h4><span>{lineas.length} productos</span></div>
                    <p className="gelia-preparacion-ayuda">Revise el pedido de referencia. Cada lectura agrega una pieza; si repite el código, aumenta su cantidad.</p>
                    {editable && <>
                        <div className="gelia-preparacion-escaner">
                            <label htmlFor="tienda-escanear" className={THEME_LABEL}><ScanLine className="w-4 h-4" aria-hidden="true" />Escaneo continuo</label>
                            <div className="gelia-preparacion-escaner-fila">
                                <InputConEscanner label="producto" value={codigo} onChange={(e) => setCodigo(e.target.value)} mostrarBotonCamara={false}
                                    inputProps={{ ...atributosCampo('tienda-escanear'), name: 'codigo_producto', autoComplete: 'off', spellCheck: false, placeholder: 'Escanee SKU o código y pulse Enter…', className: `${THEME_INPUT} w-full`, disabled: ocupado || !tarea.almacen?.id,
                                        onKeyDown: (e) => { if (e.key === 'Enter') { e.preventDefault(); capturarCodigo(e.currentTarget.value); } } }} />
                                <button type="button" className={BTN_SECONDARY} disabled={ocupado || !codigo.trim() || !tarea.almacen?.id} onClick={() => { capturarCodigo(codigo); irACampo('tienda-escanear'); }}>Agregar código</button>
                            </div>
                            {errorCampo('tienda-escanear')}
                            <p className="gelia-preparacion-ayuda" role="status">{!tarea.almacen?.id ? 'La solicitud necesita un almacén asignado para consultar productos.' : escaneo.pendientes ? `Consultando ${escaneo.pendientes} lectura(s)… Puede seguir escaneando.` : escaneo.ultimo || 'Pistola lista. Mantenga el cursor en este campo para continuar escaneando.'}</p>
                            {escaneo.fallos.map((f, i) => <div key={`${f.codigo}-${i}`} className="gelia-preparacion-error-escaneo" role="alert"><span><strong>{f.codigo}</strong>: {f.mensaje}</span><button type="button" className={BTN_SECONDARY} disabled={ocupado} onClick={() => { escaneo.quitarFallo(i); escaneo.encolar(f.codigo); }}>Reintentar</button><button type="button" className={BTN_SECONDARY} onClick={() => escaneo.quitarFallo(i)} aria-label={`Descartar aviso de ${f.codigo}`}>Descartar aviso</button></div>)}
                        </div>
                        <div className="gelia-preparacion-busqueda">
                            <div className="min-w-0 gelia-preparacion-selector"><label htmlFor="tienda-buscar-producto" className={THEME_LABEL}>O buscar por nombre o código</label><SelectorProducto inputId="tienda-buscar-producto" almacenId={tarea.almacen?.id} value={seleccion?.id || ''} onChange={(_, p) => setSeleccion(p)} disabled={ocupado || !tarea.almacen?.id} /></div>
                            <button type="button" className={BTN_SECONDARY} onClick={() => agregar()} disabled={!seleccion || ocupado}>Agregar pieza</button>
                        </div>
                    </>}
                    {!lineas.length && <div className="gelia-preparacion-vacio"><ScanLine className="w-6 h-6" aria-hidden="true" /><strong>Aún no hay productos registrados</strong><p>Escanee un código o busque un producto y seleccione «Agregar pieza». Si no hay disponibilidad, reporte el problema a Ventas.</p></div>}
                    <div className="gelia-preparacion-productos">{lineas.map((l, i) => {
                        const registrados = documentosTarea.filter((d) => l.id && Number(d.pedido_bma_tarea_producto_id) === Number(l.id));
                        const estados = { bueno: 'Buen estado', regular: 'Con detalle', malo: 'Malo', danado: 'Dañado', sin_existencia: 'Sin existencias' };
                        return <article key={l.id || `nueva-${i}`} className="gelia-preparacion-producto">
                            <div className="gelia-preparacion-producto-fila">
                                <div className="min-w-0"><h5 className="text-sm font-semibold m-0 break-words">{l.descripcion}</h5><span className="text-xs theme-text-muted">SKU: {l.sku}</span><p className="gelia-preparacion-condicion">{estados[l.estado_fisico] || 'Pendiente'}{l.observacion ? ` · ${l.observacion}` : ''}</p></div>
                                {editable ? <label className={THEME_LABEL} htmlFor={`tienda-cantidad-${i}`}>Preparadas<input {...atributosCampo(`tienda-cantidad-${i}`)} aria-label={`Cantidad de ${l.descripcion}`} name={`cantidad-${i}`} type="number" inputMode="numeric" min="0" step="1" disabled={ocupado} className={`${THEME_INPUT} w-full mt-1`} value={l.cantidad} onChange={(e) => cambiar(i, 'cantidad', e.target.value)} /></label>
                                    : <span className="gelia-preparacion-total">{l.cantidad === '' ? 'Pendiente' : `${l.cantidad} piezas`}</span>}
                            </div>
                            {errorCampo(`tienda-cantidad-${i}`)}
                            {editable && <div className="gelia-preparacion-producto-acciones"><button type="button" className={BTN_SECONDARY} aria-expanded={l.detalle || Number(l.cantidad) === 0} aria-controls={`tienda-detalle-${i}`} disabled={ocupado} onClick={() => cambiar(i, 'detalle', !l.detalle)}>{l.detalle ? 'Ocultar detalle' : 'Reportar detalle'}</button>
                                {!l.id && <button type="button" className={BTN_SECONDARY} disabled={guardando} onClick={() => setQuitarLinea(i)}><Trash2 className="w-4 h-4" aria-hidden="true" />Quitar pieza</button>}</div>}
                            {editable && (l.detalle || Number(l.cantidad) === 0) && <div id={`tienda-detalle-${i}`} className="gelia-preparacion-detalle">
                                <label className={THEME_LABEL}>Estado de la pieza<select name={`estado-${i}`} className={`${THEME_SELECT} w-full mt-1`} value={l.estado_fisico} onChange={(e) => cambiar(i, 'estado_fisico', e.target.value)} disabled={ocupado}>
                                    {Object.entries(estados).filter(([estado]) => !requisitos.estados_fisicos_permitidos || requisitos.estados_fisicos_permitidos.includes(estado)).map(([estado, texto]) => <option key={estado} value={estado}>{texto}</option>)}
                                </select></label>
                                <label className={THEME_LABEL}>Detalle o motivo<textarea name={`observacion-${i}`} rows={2} maxLength={2000} className={`${THEME_TEXTAREA} w-full mt-1`} value={l.observacion} onChange={(e) => cambiar(i, 'observacion', e.target.value)} disabled={ocupado} placeholder="Describa el daño, faltante o detalle…" /></label>
                                {fotosProducto(l, i)}
                            </div>}
                            {editable && !esRecoge && requisitos.evidencia_por_producto && !l.detalle && Number(l.cantidad) > 0 && fotosProducto(l, i)}
                            {registrados.length > 0 && <button type="button" className={`${BTN_SECONDARY} mt-2`} onClick={() => verDocs(registrados)}>Ver evidencia registrada ({registrados.length})</button>}
                        </article>;
                    })}</div>
                </section>
                {editable && <>
                    <section id="tienda-evidencias" tabIndex={-1} className="gelia-preparacion-seccion">
                        <div className="gelia-preparacion-seccion-titulo"><h4>2. Evidencias finales</h4><span>{evidenciaGeneralObligatoria ? 'Obligatorias' : 'Opcionales'}</span></div>
                        <p className="gelia-preparacion-ayuda">Fotografíe el conjunto de productos que está preparando. Revise las imágenes antes de enviar.</p>
                        {puedeFotosCelular && <button type="button" className={`${BTN_SECONDARY} gelia-preparacion-celular`} disabled={ocupado} onClick={() => setModalQr(true)}><Smartphone className="w-4 h-4" aria-hidden="true" />Fotos con celular</button>}
                        <GaleriaEvidencias id="tienda-evidencia-general" maxArchivos={10} label={esRecoge ? 'Fotos del apartado (opcional)' : 'Evidencia del conjunto preparado'} obligatorio={evidenciaGeneralObligatoria} archivos={evidencias.archivos} previews={evidencias.previews}
                            error={validando ? faltantes.find((f) => f.campo === 'tienda-evidencia-general')?.texto : undefined} onChange={(files, previews) => { setDirty(true); setEvidencias({ archivos: files, previews }); }} onVer={verDocs} disabled={ocupado} />
                        {fotosGenerales.length > 0 && <div className="gelia-preparacion-registradas"><CheckCircle2 className="w-4 h-4" aria-hidden="true" /><span>{fotosGenerales.length} evidencias ya guardadas</span><button type="button" className={BTN_SECONDARY} onClick={() => verDocs(fotosGenerales)}>Revisar evidencias</button></div>}
                    </section>
                    <section id="tienda-cierre" tabIndex={-1} className="gelia-preparacion-seccion">
                        <div className="gelia-preparacion-seccion-titulo"><h4>3. {tituloCierre}</h4></div>
                        {esRecoge ? <p className="gelia-preparacion-ayuda">Confirme las piezas separadas para avisar a Ventas.</p>
                            : <div className="gelia-preparacion-pesaje">
                                {['peso_real_kg', 'peso_volumetrico_kg'].filter((campo) => requisitos[campo === 'peso_real_kg' ? 'peso_real_obligatorio' : 'peso_volumetrico_obligatorio']).map((campo) => <div key={campo}><label htmlFor={`tienda-${campo}`} className={THEME_LABEL}>{campo === 'peso_real_kg' ? 'Peso real (kg)' : 'Peso volumétrico (kg)'} *<input {...atributosCampo(`tienda-${campo}`)} aria-required="true" name={campo} type="number" inputMode="decimal" step="0.001" min="0.001" disabled={ocupado} className={`${THEME_INPUT} w-full mt-1`} value={extra[campo]} onChange={(e) => actualizarExtra(campo, e.target.value)} placeholder="Ej. 1.250" /></label>{errorCampo(`tienda-${campo}`)}</div>)}
                                {requisitos.caja_obligatoria && <div><label htmlFor="tienda-catalogo_tipo_caja_id" className={THEME_LABEL}>Tipo de caja *<select {...atributosCampo('tienda-catalogo_tipo_caja_id')} aria-required="true" name="catalogo_tipo_caja_id" disabled={ocupado} className={`${THEME_SELECT} w-full mt-1`} value={extra.catalogo_tipo_caja_id} onChange={(e) => actualizarExtra('catalogo_tipo_caja_id', e.target.value)}><option value="">Seleccione una caja…</option>{tiposCaja.map((c) => <option key={c.id} value={c.id}>{c.nombre}</option>)}</select></label>{errorCampo('tienda-catalogo_tipo_caja_id')}</div>}
                                {requisitos.observaciones_fisicas_obligatorias && <div className="gelia-preparacion-pesaje-completo"><label htmlFor="tienda-observaciones_fisicas" className={THEME_LABEL}>Estado físico general *<textarea {...atributosCampo('tienda-observaciones_fisicas')} aria-required="true" name="observaciones_fisicas" rows={2} maxLength={4000} disabled={ocupado} className={`${THEME_TEXTAREA} w-full mt-1`} value={extra.observaciones_fisicas} onChange={(e) => actualizarExtra('observaciones_fisicas', e.target.value)} /></label>{errorCampo('tienda-observaciones_fisicas')}</div>}
                                <label className={`${THEME_LABEL} gelia-preparacion-pesaje-completo`}>Mensaje para Ventas (opcional)<textarea name="observaciones_respuesta" rows={2} maxLength={4000} disabled={ocupado} className={`${THEME_TEXTAREA} w-full mt-1`} value={extra.observaciones_respuesta} onChange={(e) => actualizarExtra('observaciones_respuesta', e.target.value)} placeholder="Indique faltantes o información para continuar el pedido…" /></label>
                            </div>}
                        {tarea.modalidad?.codigo?.startsWith('ENVIO_MUNICIPIO') && <div className="gelia-preparacion-documentos-destino"><h5 className="text-sm font-semibold">Documentos del destinatario</h5>
                            {(esAdmin || permisos.includes('control_pedidos.tienda.cargar_identificacion')) && ['identificacion', 'remision'].map((tipo) => <div key={tipo}><label htmlFor={`tienda-${tipo}`} className={THEME_LABEL}>{tipo === 'identificacion' ? 'Identificación' : 'Remisión'}{tipo === 'identificacion' && requisitos.requiere_identificacion ? ' *' : ''}<input {...atributosCampo(`tienda-${tipo}`)} name={tipo} type="file" accept="image/jpeg,image/png,image/webp,application/pdf" disabled={ocupado} onChange={(e) => { subirDocumentoMunicipal(tipo, e.target.files?.[0]); e.target.value = ''; }} /></label>{errorCampo(`tienda-${tipo}`)}</div>)}
                            {documentosTarea.filter((d) => ['identificacion', 'remision'].includes(d.tipo_evidencia)).map((d) => <button key={d.id} type="button" className={BTN_SECONDARY} onClick={() => verDocs([d])}>{d.nombre_original}</button>)}
                        </div>}
                        <div className="gelia-preparacion-resumen" aria-label="Revisión de la respuesta"><strong>{total} piezas · {lineas.length} productos</strong><p>{esRecoge ? 'Al confirmar, Ventas recibirá las piezas y su estado.' : 'Al responder, Ventas recibirá las cantidades, el estado de la mercancía y las evidencias registradas.'}</p>
                            {faltantes.length > 0 && <div className="gelia-preparacion-pendientes"><span>Por completar antes de enviar:</span><ul>{faltantes.map((f) => <li key={f.campo}><button type="button" onClick={() => { setValidando(true); irACampo(f.campo); }}>{f.texto}</button></li>)}</ul></div>}
                        </div>
                    </section>
                    {modoIncidencia && <section className="gelia-preparacion-seccion gelia-preparacion-incidencia"><h4>Reportar problema a Ventas</h4><p className="gelia-preparacion-ayuda">Describa qué impide continuar. Puede reportarlo aunque no haya encontrado piezas.</p><label htmlFor="tienda-motivo" className={THEME_LABEL}>Qué necesita resolver Ventas *<textarea id="tienda-motivo" name="motivo_incidencia" maxLength={2000} className={`${THEME_TEXTAREA} w-full mt-1`} rows={3} value={motivo} disabled={ocupado} onChange={(e) => { setDirty(true); setMotivo(e.target.value); }} /></label><div className="flex flex-wrap gap-2 mt-3"><button type="button" className={BTN_PRIMARY} disabled={guardando} onClick={() => guardar(false, true)}>Enviar problema a Ventas</button><button type="button" className={BTN_SECONDARY} disabled={ocupado} onClick={() => setModoIncidencia(false)}>Cancelar reporte</button></div></section>}
                </>}
            </div>
            {mostrarPedido && <aside id="tienda-pedido-referencia" tabIndex={-1} className="gelia-preparacion-documento" aria-label="Pedido de referencia"><button type="button" className={`${BTN_SECONDARY} mb-3 min-h-[44px]`} onClick={() => { setMostrarPedido(false); irACampo('tienda-productos'); }}>Volver a productos</button><DocumentoSolicitudTienda tareaId={tarea.id} documentos={documentos} /></aside>}
        </div>
        <footer className="gelia-preparacion-footer">
            {editable ? <>
                <div className="gelia-preparacion-estado-guardado" role="status"><strong>{guardando ? escaneo.pendientes ? 'Procesando lecturas…' : 'Guardando…' : dirty ? 'Cambios sin guardar' : 'Sin cambios pendientes'}</strong><span>{vencida ? 'Solicitud vencida. Guarde su avance o reporte a Ventas.' : faltantes.length ? `${faltantes.length} pendiente(s) para enviar. Puede guardar su avance.` : 'Respuesta completa. Revise y confirme para avisar a Ventas.'}</span></div>
                <div className="gelia-preparacion-footer-acciones">
                    <button type="button" className={BTN_SECONDARY} disabled={guardando} onClick={() => guardar()}>Guardar y continuar después</button>
                    <button type="button" className={BTN_PRIMARY} disabled={guardando || vencida} onClick={() => guardar(true)}><CheckCircle2 className="w-4 h-4" aria-hidden="true" />{procesando ? 'Guardando…' : esRecoge ? 'Confirmar apartado' : 'Responder preparación'}</button>
                </div>
            </> : <p className="text-sm theme-text-main m-0">Total preparado: {total} piezas{tarea.apartado?.ubicacion ? ` · Ubicación: ${tarea.apartado.ubicacion}` : ''}{tarea.apartado?.estado === 'APARTADO_CONFIRMADO' ? ' · Apartado confirmado. Ventas recibió el aviso.' : ''}</p>}
        </footer>
        <ModalConfirmarAccion abierto={quitarLinea !== null} titulo="Quitar producto de la preparación" mensaje="Se quitarán su cantidad y las evidencias pendientes de guardar." etiquetaConfirmar="Quitar producto" variante="danger" onClose={() => setQuitarLinea(null)} onConfirm={() => {
            const linea = lineas[quitarLinea];
            linea?.previews.forEach((p) => { if (p.url?.startsWith('blob:')) URL.revokeObjectURL(p.url); });
            setDirty(true); setLineas((prev) => prev.filter((_, i) => i !== quitarLinea)); setQuitarLinea(null);
        }} />
        <ModalVistaPreviaDocumento {...galeria} onClose={() => setGaleria({ abierto: false, documentos: [], indice: 0 })} />
        <ModalSesionEvidenciaTienda abierto={modalQr} tareaId={tarea.id} onCerrar={() => setModalQr(false)} onImportado={onRefrescarDocumentos} />
    </section>;
}
