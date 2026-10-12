import React, { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
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
    const [ubicacion, setUbicacion] = useState(tarea.apartado?.ubicacion || '');
    const [motivo, setMotivo] = useState('');
    const [modoIncidencia, setModoIncidencia] = useState(false);
    const [extra, setExtra] = useState({ peso_real_kg: tarea.peso_real_kg || '', peso_volumetrico_kg: tarea.peso_volumetrico_kg || '', catalogo_tipo_caja_id: tarea.catalogo_tipo_caja_id || '', observaciones_fisicas: tarea.observaciones_fisicas || '', observaciones_respuesta: tarea.observaciones_respuesta || '' });
    const [evidencias, setEvidencias] = useState({ archivos: [], previews: [] });
    const [procesando, setProcesando] = useState(false);
    const [error, setError] = useState('');
    const [galeria, setGaleria] = useState({ abierto: false, documentos: [], indice: 0 });
    const [dirty, setDirty] = useState(false);
    const [modalQr, setModalQr] = useState(false);
    const ocupado = procesando || bloqueado;
    const vence = tarea.fecha_limite || tarea.apartado?.vence_at;
    const vencida = esRecoge && Boolean(vence && new Date(vence).getTime() <= Date.now());
    const formRef = useRef(null);
    const previewsRef = useRef([]);
    previewsRef.current = [...lineas.flatMap((l) => l.previews), ...evidencias.previews];
    useEffect(() => () => previewsRef.current.forEach((p) => { if (p.url?.startsWith('blob:')) URL.revokeObjectURL(p.url); }), []);
    useEffect(() => { onDirtyChange?.(dirty); }, [dirty, onDirtyChange]);
    const cambiar = (i, campo, valor) => { setDirty(true); setLineas((prev) => prev.map((l, j) => j === i ? { ...l, [campo]: valor } : l)); };
    const agregar = (producto = seleccion) => {
        if (!producto || ocupado) return;
        setDirty(true);
        setLineas((prev) => {
            const i = prev.findIndex((l) => Number(l.producto_id) === Number(producto.id));
            return i >= 0 ? prev.map((l, j) => j === i ? { ...l, cantidad: (Number(l.cantidad) || 0) + 1 } : l)
                : [...prev, { producto_id: producto.id, sku: producto.sku, descripcion: producto.descripcion, cantidad: 1, estado_fisico: 'bueno', observacion: '', evidencias: [], previews: [], detalle: false }];
        });
        setSeleccion(null);
    };
    const escanear = async (valor = codigo) => {
        if (!String(valor).trim() || !tarea.almacen?.id || ocupado) return;
        setError('');
        try {
            const { data } = await axios.get(route('gestion_interna.productos.buscar'), { params: { q: String(valor).trim(), almacen_id: tarea.almacen.id, per_page: 25 } });
            const encontrados = data.data || [];
            const producto = encontrados.find((p) => [p.sku, p.codigo_barras, p.folio].some((v) => String(v || '').toLowerCase() === String(valor).trim().toLowerCase())) || (encontrados.length === 1 ? encontrados[0] : null);
            if (producto) { agregar(producto); setCodigo(''); }
            else setError(encontrados.length ? 'Hay varios resultados. Seleccione el producto en el buscador.' : 'No se encontró el producto en este almacén. Puede reportarlo a Ventas.');
        } catch { setError('No se pudo consultar el catálogo del almacén. Intente de nuevo.'); }
    };
    const guardar = (confirmar = false, incidencia = false) => {
        if (ocupado) return;
        setError('');
        if (!lineas.length && !incidencia) { setError('Agregue los productos preparados o reporte el problema.'); return; }
        if (incidencia && !motivo.trim()) { setError('Describa qué necesita resolver Ventas.'); return; }
        if (confirmar && esRecoge && !ubicacion.trim()) { setError('Indique dónde queda el apartado.'); formRef.current?.querySelector('[name="ubicacion"]')?.focus(); return; }
        setProcesando(true);
        const datos = { version: esRecoge ? tarea.apartado?.version : tarea.version, ubicacion,
            lineas: lineas.map(({ previews, detalle, ...l }) => l), motivo_incidencia: incidencia ? motivo : undefined,
            ...(!esRecoge ? extra : {}), evidencias: evidencias.archivos };
        const tieneArchivos = lineas.some((l) => l.evidencias.length) || evidencias.archivos.length;
        const payload = tieneArchivos ? formData(datos) : datos;
        const nombreRuta = esRecoge ? (confirmar ? 'control_pedidos.tienda.apartado.confirmar' : 'control_pedidos.tienda.apartado.guardar')
            : confirmar ? 'control_pedidos.tienda.responder' : 'control_pedidos.tienda.preparacion.guardar';
        const opciones = { preserveScroll: true, onFinish: () => setProcesando(false), onSuccess: () => { setDirty(false); onDirtyChange?.(false); }, onError: (errores) => setError(Object.values(errores).flat().join(' ')) };
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
    return <section className="gelia-preparacion" aria-label="Espacio de preparación">
        <header className="gelia-preparacion-contexto"><div><h3 className="text-base font-bold m-0">{esRecoge ? 'Separación y apartado en tienda' : tarea.pedido?.requiere_logistica ? 'Preparación para pesaje' : 'Separación de mercancía'}</h3>
            <p className="text-sm theme-text-muted m-0">Almacén: <strong>{tarea.almacen?.nombre || tarea.almacen?.codigo || 'No asignado'}</strong>{tarea.pedido?.cliente_nombre ? ` · Cliente: ${tarea.pedido.cliente_nombre}` : ''}{tarea.pedido?.vendedora_nombre ? ` · Vendedora: ${tarea.pedido.vendedora_nombre}` : ''}</p></div>
            <span className="text-sm font-bold theme-text-main tabular-nums">{total} piezas preparadas</span>
        </header>
        {vencida && <p role="alert" className="gelia-preparacion-aviso">La solicitud venció el {formatearFechaNegocio(vence)}. Reporte a Ventas para solicitar una nueva preparación del día.</p>}
        {error && <p role="alert" className="gelia-preparacion-aviso">{error}</p>}
        <div className="gelia-preparacion-workspace">
            <div className="gelia-preparacion-captura" ref={formRef}>
                {tarea.observaciones_solicitud && <p className="text-sm theme-text-main m-0">{tarea.observaciones_solicitud}</p>}
                {editable && <div className="gelia-preparacion-busqueda">
                    <div className="min-w-0"><label className={`block ${THEME_LABEL}`}>Buscar producto del almacén</label><SelectorProducto almacenId={tarea.almacen?.id} value={seleccion?.id || ''} onChange={(_, p) => setSeleccion(p)} disabled={ocupado} /></div>
                    <button type="button" className={BTN_SECONDARY} onClick={() => agregar()} disabled={!seleccion || ocupado}>Agregar pieza</button>
                    <div className="min-w-0"><InputConEscanner label="Escanear código" value={codigo} onChange={(v, origen) => { setCodigo(v); if (origen?.desdeEscanner) escanear(v); }} mostrarBotonCamara={false} inputProps={{ 'aria-label': 'Escanear SKU o código de barras', placeholder: 'Escanee y pulse Enter…', className: `${THEME_INPUT} w-full`, disabled: ocupado, onKeyDown: (e) => { if (e.key === 'Enter') { e.preventDefault(); escanear(); } } }} /></div>
                </div>}
                {tarea.estado === 'EN_ATENCION' && !autorizado && <p role="status" className="text-sm theme-text-muted">Esta tarea está asignada a {tarea.responsable?.name || 'otro auxiliar'}. Solo su responsable puede responder.</p>}
                {!lineas.length && <p className="text-sm theme-text-muted">Consulte el pedido y agregue las piezas encontradas. Si no hay disponibilidad, puede reportarlo sin registrar piezas.</p>}
                <div className="gelia-preparacion-productos">{lineas.map((l, i) => <article key={l.id || `nueva-${i}`} className="gelia-preparacion-producto">
                    <div className="gelia-preparacion-producto-fila"><div className="min-w-0"><p className="text-sm font-bold m-0 break-words">{l.descripcion}</p><span className="text-xs theme-text-muted">{l.sku}</span></div>
                        {editable ? <><label className={THEME_LABEL}>Preparadas<input aria-label={`Cantidad de ${l.descripcion}`} type="number" min="0" step="1" disabled={ocupado} className={`${THEME_INPUT} w-full mt-1`} value={l.cantidad} onChange={(e) => cambiar(i, 'cantidad', e.target.value)} /></label>
                            <button type="button" className={BTN_SECONDARY} disabled={ocupado} onClick={() => cambiar(i, 'detalle', !l.detalle)}>{l.detalle ? 'Ocultar detalle' : 'Reportar detalle'}</button>
                            {!l.id && <button type="button" className={BTN_SECONDARY} disabled={ocupado} onClick={() => { setDirty(true); setLineas((prev) => prev.filter((_, j) => j !== i)); }}>Quitar pieza</button>}</>
                            : <span className="text-sm theme-text-main">{l.cantidad} · {l.estado_fisico}{l.observacion ? ` · ${l.observacion}` : ''}</span>}
                    </div>
                    {editable && (l.detalle || Number(l.cantidad) === 0) && <div className="space-y-2 mt-3"><label className={`block ${THEME_LABEL}`}>Estado de la pieza<select className={`${THEME_SELECT} w-full mt-1`} value={l.estado_fisico} onChange={(e) => cambiar(i, 'estado_fisico', e.target.value)} disabled={ocupado}><option value="bueno">Bueno / retiro solicitado</option><option value="regular">Con detalle</option><option value="malo">Malo</option><option value="danado">Dañado</option><option value="sin_existencia">Sin existencias</option></select></label>
                        <label className={`block ${THEME_LABEL}`}>Detalle o motivo<input className={`${THEME_INPUT} w-full mt-1`} value={l.observacion} onChange={(e) => cambiar(i, 'observacion', e.target.value)} disabled={ocupado} /></label>
                        <GaleriaEvidencias archivos={l.evidencias} previews={l.previews} label={requisitos.evidencia_por_producto ? 'Fotos del producto' : 'Fotos del detalle (opcional)'} obligatorio={requisitos.evidencia_por_producto} onChange={(files, previews) => { cambiar(i, 'evidencias', files); cambiar(i, 'previews', previews); }} onVer={verDocs} disabled={ocupado} />
                    </div>}
                    {editable && requisitos.evidencia_por_producto && !l.detalle && Number(l.cantidad) > 0 && <GaleriaEvidencias label="Fotos del producto" obligatorio archivos={l.evidencias} previews={l.previews} onChange={(files, previews) => { cambiar(i, 'evidencias', files); cambiar(i, 'previews', previews); }} onVer={verDocs} disabled={ocupado} />}
                    {documentosTarea.filter((d) => Number(d.pedido_bma_tarea_producto_id) === Number(l.id) && l.id).map((d) => <button key={d.id} type="button" className="text-xs font-semibold theme-text-primario underline" onClick={() => verDocs([d])}>Ver evidencia registrada</button>)}
                </article>)}</div>
                {!esRecoge && editable && <div className="space-y-2 mt-3">
                    {requisitos.peso_real_obligatorio && <label className={`block ${THEME_LABEL}`}>Peso real (kg)<input type="number" step="0.001" min="0" className={`${THEME_INPUT} w-full mt-1`} value={extra.peso_real_kg} onChange={(e) => { setDirty(true); setExtra({ ...extra, peso_real_kg: e.target.value }); }} /></label>}
                    {requisitos.peso_volumetrico_obligatorio && <label className={`block ${THEME_LABEL}`}>Peso volumétrico (kg)<input type="number" step="0.001" min="0" className={`${THEME_INPUT} w-full mt-1`} value={extra.peso_volumetrico_kg} onChange={(e) => { setDirty(true); setExtra({ ...extra, peso_volumetrico_kg: e.target.value }); }} /></label>}
                    {requisitos.caja_obligatoria && <label className={`block ${THEME_LABEL}`}>Tipo de caja<select className={`${THEME_SELECT} w-full mt-1`} value={extra.catalogo_tipo_caja_id} onChange={(e) => { setDirty(true); setExtra({ ...extra, catalogo_tipo_caja_id: e.target.value }); }}><option value="">Seleccione…</option>{tiposCaja.map((c) => <option key={c.id} value={c.id}>{c.nombre}</option>)}</select></label>}
                    <label className={`block ${THEME_LABEL}`}>Observaciones de preparación<textarea className={`${THEME_TEXTAREA} w-full mt-1`} value={extra.observaciones_respuesta} onChange={(e) => { setDirty(true); setExtra({ ...extra, observaciones_respuesta: e.target.value }); }} /></label>
                    {requisitos.observaciones_fisicas_obligatorias && <label className={`block ${THEME_LABEL}`}>Estado físico general<textarea className={`${THEME_TEXTAREA} w-full mt-1`} value={extra.observaciones_fisicas} onChange={(e) => { setDirty(true); setExtra({ ...extra, observaciones_fisicas: e.target.value }); }} /></label>}
                    <GaleriaEvidencias label="Evidencia del conjunto preparado" obligatorio={requisitos.evidencia_general_obligatoria} archivos={evidencias.archivos} previews={evidencias.previews} onChange={(files, previews) => { setDirty(true); setEvidencias({ archivos: files, previews }); }} onVer={verDocs} disabled={ocupado} />
                </div>}
                {esRecoge && editable && <details className="mt-3 text-sm theme-text-main"><summary className="cursor-pointer theme-text-muted">Fotos del apartado (opcional)</summary><GaleriaEvidencias archivos={evidencias.archivos} previews={evidencias.previews} onChange={(files, previews) => { setDirty(true); setEvidencias({ archivos: files, previews }); }} onVer={verDocs} disabled={ocupado} /></details>}
                {editable && tarea.modalidad?.codigo?.startsWith('ENVIO_MUNICIPIO') && <details className="mt-3 text-sm theme-text-main"><summary className="cursor-pointer theme-text-muted">Documentos del destinatario</summary>
                    {(esAdmin || permisos.includes('control_pedidos.tienda.cargar_identificacion')) && <div className="space-y-2 mt-2">{['identificacion', 'remision'].map((tipo) => <label key={tipo} className={`block ${THEME_LABEL}`}>{tipo === 'identificacion' ? 'Identificación' : 'Remisión'}<input type="file" accept="image/*,application/pdf" disabled={ocupado} onChange={(e) => subirDocumentoMunicipal(tipo, e.target.files?.[0])} /></label>)}</div>}
                    {documentosTarea.filter((d) => ['identificacion', 'remision'].includes(d.tipo_evidencia)).map((d) => <button key={d.id} type="button" className="block theme-text-primario underline text-left" onClick={() => verDocs([d])}>{d.nombre_original}</button>)}
                </details>}
            </div>
            <div className="gelia-preparacion-documento"><DocumentoSolicitudTienda tareaId={tarea.id} documentos={documentos} /></div>
        </div>
        <footer className="gelia-preparacion-footer">
            {editable ? <>
                {esRecoge && <label className={`${THEME_LABEL} flex-1 min-w-0`}>Ubicación del apartado<input name="ubicacion" className={`${THEME_INPUT} w-full mt-1`} value={ubicacion} disabled={ocupado} onChange={(e) => { setDirty(true); setUbicacion(e.target.value); }} placeholder="Anaquel o zona de apartados" /></label>}
                {modoIncidencia && <label className={`${THEME_LABEL} flex-1 min-w-0`}>Qué necesita resolver Ventas<textarea className={`${THEME_TEXTAREA} w-full mt-1`} value={motivo} disabled={ocupado} onChange={(e) => { setDirty(true); setMotivo(e.target.value); }} /></label>}
                <div className="flex flex-wrap gap-2"><button type="button" className={BTN_PRIMARY} disabled={ocupado || vencida} onClick={() => guardar(true)}>{procesando ? 'Guardando…' : esRecoge ? 'Confirmar apartado' : 'Responder preparación'}</button>
                    <button type="button" className={BTN_SECONDARY} disabled={ocupado} onClick={() => guardar()}>Guardar y continuar después</button>
                    {(esAdmin || permisos.includes('control_pedidos.tienda.evidencias')) && <button type="button" className={BTN_SECONDARY} disabled={ocupado} onClick={() => setModalQr(true)}>Fotos con celular</button>}
                    {puedeIncidencia && <button type="button" className={BTN_SECONDARY} disabled={ocupado} onClick={() => modoIncidencia ? guardar(false, true) : setModoIncidencia(true)}>{modoIncidencia ? 'Enviar problema a Ventas' : 'Reportar problema'}</button>}
                </div>
            </> : <p className="text-sm theme-text-main m-0">Total preparado: {total} piezas{tarea.apartado?.ubicacion ? ` · Ubicación: ${tarea.apartado.ubicacion}` : ''}{tarea.apartado?.estado === 'APARTADO_CONFIRMADO' ? ' · Apartado confirmado. Ventas recibió el aviso.' : ''}</p>}
        </footer>
        <ModalVistaPreviaDocumento {...galeria} onClose={() => setGaleria({ abierto: false, documentos: [], indice: 0 })} />
        <ModalSesionEvidenciaTienda abierto={modalQr} tareaId={tarea.id} onCerrar={() => setModalQr(false)} onImportado={onRefrescarDocumentos} />
    </section>;
}
