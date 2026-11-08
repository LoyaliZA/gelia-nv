import React, { useEffect, useRef, useState } from 'react';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { AlertTriangle, CheckCircle2, ClipboardCheck, FileText, ImagePlus, PackageMinus, ScanLine, Smartphone, Trash2 } from 'lucide-react';
import ConsultaPreparacionTienda from './ConsultaPreparacionTienda';
import useEscaneoPreparacion from './useEscaneoPreparacion';
import AjustesSolicitadosTienda from './AjustesSolicitadosTienda';
import VigenciaApartadoTienda from '../../Partials/VigenciaApartadoTienda';
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

const ESTADOS_EXCEPCION = ['regular', 'malo', 'danado', 'sin_existencia'];

function esExcepcionFisica(linea) {
    return ESTADOS_EXCEPCION.includes(linea?.estado_fisico);
}

function esImagenGuardada(documento) {
    const mime = String(documento?.mime_type || '');
    const nombre = String(documento?.nombre_original || '');
    if (mime.includes('pdf') || /\.pdf$/i.test(nombre)) return false;
    return mime.startsWith('image/') || /\.(jpe?g|png|webp)$/i.test(nombre);
}

function formData(datos) {
    const form = new FormData();
    const agregar = (valor, clave) => {
        if (valor === null || valor === undefined) return;
        if (valor instanceof File || valor instanceof Blob) form.append(clave, valor);
        else if (Array.isArray(valor)) valor.forEach((v, i) => agregar(v, `${clave}[${i}]`));
        else if (typeof valor === 'boolean') form.append(clave, valor ? '1' : '0');
        else if (typeof valor === 'object') Object.entries(valor).forEach(([k, v]) => agregar(v, `${clave}[${k}]`));
        else form.append(clave, String(valor));
    };
    Object.entries(datos).forEach(([k, v]) => agregar(v, k));
    return form;
}


function PrepararApartadoLegado({ tarea, auth, documentos = [], documentosTarea = [], requisitos = {}, tiposCaja = [], onEnviar = null, bloqueado = false, onDirtyChange = null, onRefrescarDocumentos = null }) {
    const esRecoge = tarea.modalidad?.codigo === 'RECOGE_TIENDA' || !tarea.modalidad;
    const capturaEstadoPieza = esRecoge || tarea.modalidad?.codigo === 'RECOGE_TIENDA_TRANSFERENCIA';
    const permisos = auth?.user?.permissions || [];
    const esAdmin = auth?.user?.roles?.includes('Super Admin');
    const autorizado = (esRecoge ? tarea.puede_preparar : tarea.puede_responder) ?? ((esAdmin || permisos.includes('control_pedidos.tienda.responder') || (esRecoge && permisos.includes('control_pedidos.tienda.apartado.separar'))) && Number(tarea.responsable?.id) === Number(auth?.user?.id));
    const consultaTienda = esRecoge && tarea.pedido?.area_revision === 'preparacion_tienda';
    const revision = tarea.pedido?.revision_disponibilidad || null;
    const revisionRegistrada = Boolean(revision?.estado);
    const consultaCerrada = Boolean(tarea.pedido?.consulta_cerrada);
    const hayAjustes = (tarea.pedido?.retiros || []).some((retiro) => (retiro.estado || 'pendiente') === 'pendiente')
        || (tarea.pedido?.anexos_pendientes || []).length > 0;
    const faltaConfirmacionPedido = Boolean(tarea.pedido?.cobertura_tienda?.requiere_confirmacion_pedido && !tarea.pedido?.cobertura_tienda?.confirmacion_enviada_at);
    const esperandoVentas = consultaTienda && revisionRegistrada && (!consultaCerrada || faltaConfirmacionPedido) && tarea.apartado?.estado !== 'APARTADO_CONFIRMADO';
    const editable = tarea.estado === 'EN_ATENCION' && autorizado && !hayAjustes && !esperandoVentas;
    const puedeIncidencia = tarea.puede_reportar_incidencia ?? (esAdmin || permisos.includes('control_pedidos.tienda.reportar_error'));
    const [lineas, setLineas] = useState(() => (tarea.productos || []).filter((p) => p.sku).map((p) => ({ id: p.id, producto_id: p.producto_id, sku: p.sku, descripcion: p.descripcion_snapshot, cantidad: p.cantidad_encontrada ?? '', estado_fisico: p.estado_fisico || 'bueno', observacion: p.observacion || '', unica_pieza: Boolean(p.unica_pieza), mejor_ejemplar: Boolean(p.mejor_ejemplar), evidencias: [], previews: [], detalle: false })));
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
    const [ajustesDirty, setAjustesDirty] = useState(false);
    const [mostrarPedido, setMostrarPedido] = useState(() => !window.matchMedia?.('(max-width: 760px)').matches);
    const [validando, setValidando] = useState(false);
    const [quitarLinea, setQuitarLinea] = useState(null);
    const [modalQr, setModalQr] = useState(false);
    const [confirmacionConjunto, setConfirmacionConjunto] = useState(false);
    const [estadoPedido, setEstadoPedido] = useState(null);
    const [sugerencias, setSugerencias] = useState([]);
    const buscarSugerenciasRef = useRef(null);
    const ocupado = procesando || bloqueado;
    const vence = tarea.fecha_limite || tarea.apartado?.vence_at;
    const horariosActivos = tarea.pedido?.horarios_activos !== false;
    const vencida = horariosActivos && esRecoge && Boolean(vence && new Date(vence).getTime() <= Date.now());
    const formRef = useRef(null);
    const previewsRef = useRef([]);
    previewsRef.current = [...lineas.flatMap((l) => l.previews), ...evidencias.previews];
    useEffect(() => () => previewsRef.current.forEach((p) => { if (p.url?.startsWith('blob:')) URL.revokeObjectURL(p.url); }), []);
    const cambiar = (i, campo, valor) => { setDirty(true); setLineas((prev) => prev.map((l, j) => j === i ? { ...l, [campo]: valor } : l)); };
    const permitirSinExistencia = !requisitos.estados_fisicos_permitidos || requisitos.estados_fisicos_permitidos.includes('sin_existencia');
    const MOTIVO_SIN_EXISTENCIA = 'Sin existencias';
    const alternarEtiqueta = (i, clave) => {
        setDirty(true);
        setLineas((prev) => prev.map((l, j) => {
            if (j !== i) return l;
            if (l.estado_fisico === 'sin_existencia' && clave !== 'sin_existencia') return l;
            if (clave === 'unica_pieza') return { ...l, unica_pieza: !l.unica_pieza };
            if (clave === 'mejor_ejemplar') return { ...l, mejor_ejemplar: !l.mejor_ejemplar };
            if (clave !== 'sin_existencia') return l;
            if (l.estado_fisico === 'sin_existencia') {
                return {
                    ...l,
                    estado_fisico: momentoRevision ? 'regular' : 'bueno',
                    cantidad: Number(l.cantidad_consulta) > 0 ? l.cantidad_consulta : (Number(l.cantidad) === 0 ? 1 : l.cantidad),
                    observacion: l.observacion === MOTIVO_SIN_EXISTENCIA ? '' : l.observacion,
                    detalle: false,
                };
            }
            return {
                ...l,
                estado_fisico: 'sin_existencia', unica_pieza: false, mejor_ejemplar: false,
                cantidad_consulta: Number(l.cantidad) > 0 ? Number(l.cantidad) : (l.cantidad_consulta || 1),
                cantidad: 0,
                observacion: String(l.observacion || '').trim() || MOTIVO_SIN_EXISTENCIA,
                detalle: false,
            };
        }));
    };
    const agregar = (producto = seleccion) => {
        if (!producto || ocupado) return;
        const excepcion = momentoRevision && estadoPedido === 'detalle';
        setDirty(true);
        setLineas((prev) => {
            const i = prev.findIndex((l) => (l.producto_id && Number(l.producto_id) === Number(producto.id)) || String(l.sku).toLowerCase() === String(producto.sku).toLowerCase());
            return i >= 0 ? prev.map((l, j) => j === i ? { ...l, cantidad: (Number(l.cantidad) || 0) + 1 } : l)
                : [...prev, { producto_id: producto.id, sku: producto.sku, descripcion: producto.descripcion, cantidad: 1, estado_fisico: excepcion ? 'regular' : 'bueno', observacion: '', unica_pieza: false, mejor_ejemplar: false, evidencias: [], previews: [], detalle: false }];
        });
        setSeleccion(null);
        setCodigo('');
        setSugerencias([]);
    };
    const elegirEstadoPedido = (valor) => {
        setEstadoPedido(valor);
        setDirty(true);
        setError('');
        if (valor === 'correcto') {
            setConfirmacionConjunto(true);
            setLineas((prev) => {
                prev.forEach((linea) => linea.previews?.forEach((p) => { if (p.url?.startsWith('blob:')) URL.revokeObjectURL(p.url); }));
                return [];
            });
            setSeleccion(null);
            setCodigo('');
            setSugerencias([]);
            return;
        }
        setConfirmacionConjunto(false);
        setLineas((prev) => prev.map((linea) => ({
            ...linea,
            detalle: false,
            estado_fisico: linea.estado_fisico === 'bueno' ? 'regular' : linea.estado_fisico,
        })));
    };
    const programarSugerencias = (termino) => {
        clearTimeout(buscarSugerenciasRef.current);
        const texto = String(termino || '').trim();
        if (texto.length < 2 || !tarea.almacen?.id) {
            setSugerencias([]);
            return;
        }
        buscarSugerenciasRef.current = setTimeout(async () => {
            try {
                const { data } = await axios.get(route('gestion_interna.productos.buscar'), {
                    params: { q: texto, almacen_id: tarea.almacen.id, per_page: 8, incluir_sin_inventario: 1 },
                });
                setSugerencias(data.data || []);
            } catch {
                setSugerencias([]);
            }
        }, 250);
    };
    const escaneo = useEscaneoPreparacion({ almacenId: tarea.almacen?.id, onProducto: agregar });
    useEffect(() => { onDirtyChange?.(dirty || ajustesDirty || escaneo.pendientes > 0); }, [dirty, ajustesDirty, escaneo.pendientes, onDirtyChange]);
    const guardando = ocupado || escaneo.pendientes > 0;
    const estadoRevision = revision?.estado || '';
    const aceptacionVigente = revision?.aceptacion_vigente !== false && revision?.vigente !== false && !revision?.sustituida_at;
    const apartadoConfirmado = tarea.apartado?.estado === 'APARTADO_CONFIRMADO';
    const seleccionAceptada = aceptacionVigente && (estadoRevision === 'sin_excepciones' || estadoRevision === 'excepciones_pendientes' || estadoRevision === 'decision_registrada');
    const momentoRevision = consultaTienda && editable && !estadoRevision;
    const momentoSeparacion = consultaTienda && editable && seleccionAceptada && !apartadoConfirmado;
    const momentoEspera = consultaTienda && editable && !momentoRevision && !momentoSeparacion && !apartadoConfirmado;
    const faltantes = (momentoRevision || momentoEspera || momentoSeparacion) ? [] : faltantesRespuesta({ lineas, esRecoge, extra, requisitos, documentos: documentosTarea, evidencias: evidencias.archivos });
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
        if (guardando || !editable) return;
        setError('');
        if (confirmar) {
            setValidando(true);
            if (vencida) { setError('La solicitud venció. Reporte el problema a Ventas para renovarla.'); return; }
            if (faltantes.length) { setError('Complete los campos señalados antes de enviar la respuesta.'); irACampo(faltantes[0].campo); return; }
        }
        if (!momentoSeparacion && !lineas.length && !incidencia) {
            if (confirmar) {
                setError('Identifique los productos y cantidades físicos antes de confirmar la separación.');
                return;
            }
            if (!evidencias.archivos.length) {
                setError('Agregue los productos preparados, una foto del conjunto o reporte el problema.');
                return;
            }
        }
        if (incidencia && !motivo.trim()) { setError('Describa qué necesita resolver Ventas.'); irACampo('tienda-motivo'); return; }
        setProcesando(true);
        const datos = { version: esRecoge ? tarea.apartado?.version : tarea.version,
            lineas: momentoSeparacion ? [] : lineas.map(({ previews, detalle, ...l }) => l), motivo_incidencia: incidencia ? motivo : undefined,
            ...(!esRecoge ? extra : {}), evidencias: evidencias.archivos };
        const tieneArchivos = lineas.some((l) => l.evidencias.length) || evidencias.archivos.length;
        const payload = tieneArchivos ? formData(datos) : datos;
        const nombreRuta = esRecoge ? (confirmar ? 'control_pedidos.tienda.apartado.confirmar' : 'control_pedidos.tienda.apartado.guardar')
            : confirmar ? 'control_pedidos.tienda.responder' : 'control_pedidos.tienda.preparacion.guardar';
        const opciones = { preserveScroll: true, onFinish: () => setProcesando(false), onSuccess: () => { setDirty(false); setValidando(false); onDirtyChange?.(false); }, onError: (errores) => setError(Object.values(errores).flat().join(' ')) };
        if (onEnviar) onEnviar(route(nombreRuta, tarea.id), payload, opciones);
        else router.post(route(nombreRuta, tarea.id), payload, opciones);
    };
    const puedeRegistrarRevision = editable && esRecoge && tarea.pedido?.area_revision === 'preparacion_tienda' && !revisionRegistrada;
    const registrarRevision = () => {
        if (guardando || !puedeRegistrarRevision) return;
        setError('');
        if (estadoPedido !== 'correcto' && estadoPedido !== 'detalle') {
            setError('Indique el estado del pedido.');
            irACampo('tienda-estado-pedido');
            return;
        }
        const afectadas = estadoPedido === 'detalle' ? lineas.filter(esExcepcionFisica) : [];
        if (estadoPedido === 'detalle' && afectadas.length === 0) {
            setError('Registre las piezas con detalles.');
            irACampo('tienda-productos');
            return;
        }
        const sinDetalle = afectadas.find((linea) => linea.estado_fisico !== 'sin_existencia' && !String(linea.observacion || '').trim());
        if (sinDetalle) {
            setError('Describa el detalle de la pieza afectada. Única pieza y Mejor ejemplar no indican un defecto.');
            return;
        }
        const imagenesNuevas = evidencias.archivos.filter((archivo) => String(archivo.type || '').startsWith('image/'));
        const guardadas = fotosGenerales.filter(esImagenGuardada);
        if (imagenesNuevas.length === 0 && guardadas.length === 0) {
            setError('Adjunte una foto del conjunto físico. El PDF del pedido no la sustituye.');
            irACampo('tienda-evidencia-general');
            return;
        }
        setProcesando(true);
        const datos = {
            confirmacion_explicita: afectadas.length === 0,
            version: tarea.apartado?.version,
            excepciones: afectadas.map((linea) => ({
                descripcion: linea.descripcion,
                sku: linea.sku || '',
                cantidad: Math.max(1, Number(linea.cantidad_consulta || linea.cantidad) || 1),
                condicion: linea.estado_fisico === 'sin_existencia' ? 'faltante' : 'detalle',
                detalle: String(linea.observacion || '').trim() || 'Sin existencias',
            })),
            evidencias: imagenesNuevas,
            evidencias_guardadas: guardadas.map((documento) => documento.id),
            ...(estadoPedido === 'detalle' && lineas.length ? { lineas: lineas.map(({ previews, detalle, cantidad_consulta, ...linea }) => linea) } : {}),
        };
        const opciones = {
            onFinish: () => setProcesando(false),
            onSuccess: () => { setDirty(false); onDirtyChange?.(false); },
            onError: (errores) => setError(Object.values(errores).flat().join(' ')),
        };
        const payload = formData(datos);
        if (onEnviar) onEnviar(route('control_pedidos.tienda.disponibilidad', tarea.id), payload, opciones);
        else router.post(route('control_pedidos.tienda.disponibilidad', tarea.id), payload, opciones);
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
    const fotoRevisionObligatoria = momentoRevision || puedeRegistrarRevision;
    const capturaExcepcion = momentoRevision && estadoPedido === 'detalle';
    const mostrarProductos = !momentoSeparacion && (!momentoRevision || capturaExcepcion) && (!consultaTienda || editable || lineas.length > 0);
    const mostrarEvidencias = !momentoSeparacion && editable && (!momentoRevision || estadoPedido === 'correcto' || (capturaExcepcion && lineas.length > 0));
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
        {horariosActivos && tarea.pedido?.es_t01_documental && !apartadoConfirmado && tarea.pedido?.cobertura_tienda?.tipo === 'apartado' && <div className="px-4 py-2"><VigenciaApartadoTienda venceAt={vence} finalizado={['ENTREGADA', 'DEVUELTA_ANAQUEL'].includes(tarea.apartado?.estado)} /></div>}
        {vencida && <div role="alert" className="gelia-preparacion-aviso"><AlertTriangle className="w-4 h-4 shrink-0" aria-hidden="true" /><div><strong>Solicitud vencida · {formatearFechaNegocio(vence)}</strong><p className="m-0">Puede guardar su avance. Reporte a Ventas para solicitar una nueva preparación del día.</p></div></div>}
        {error && <p role="alert" className="gelia-preparacion-aviso">{error}</p>}
        <div className="gelia-preparacion-workspace" data-pedido-visible={mostrarPedido}>
            <div className="gelia-preparacion-captura" ref={formRef}>
                <details className="gelia-preparacion-opciones">
                    <summary>Datos y opciones de preparación</summary>
                    {tarea.pedido?.vendedora_nombre && <p className="text-sm">Vendedora: {tarea.pedido.vendedora_nombre}</p>}
                    {editable && !esRecoge && <a className={`${BTN_SECONDARY} inline-flex min-h-[44px] items-center`} href={route('traspasos.abastecimiento.index', { tarea: tarea.id })}>Abastecer preparación</a>}
                    {editable && puedeIncidencia && <button type="button" className={BTN_SECONDARY} disabled={ocupado} onClick={() => { setModoIncidencia(true); requestAnimationFrame(() => irACampo('tienda-motivo')); }}><AlertTriangle className="w-4 h-4" aria-hidden="true" />Reportar error</button>}
                </details>
                {tarea.abastecimiento_aviso && <p role="status" className="theme-text-aviso">{tarea.abastecimiento_aviso}</p>}
                {tarea.abastecimiento_pendiente && <p role="status" className="theme-text-aviso">Hay abastecimiento pendiente de salida, llegada o resolución de errores reportados. Confirme las llegadas antes de cerrar la preparación.</p>}
                {esperandoVentas && !hayAjustes && <aside className="gelia-preparacion-instrucciones" role="status"><h4>Respuesta enviada</h4><p>{faltaConfirmacionPedido ? 'Espera la confirmación del pedido de Ventas.' : 'Ventas está confirmando las piezas con el cliente.'}</p></aside>}
                {tarea.observaciones_solicitud && <aside className="gelia-preparacion-instrucciones"><h4>Indicaciones de Ventas</h4><p>{tarea.observaciones_solicitud}</p></aside>}
                {tarea.estado === 'EN_ATENCION' && !autorizado && <p role="status" className="text-sm theme-text-muted">Esta tarea está asignada a {tarea.responsable?.name || 'otro auxiliar'}. Solo su responsable puede responder.</p>}
                {tarea.estado === 'PENDIENTE' && <p className="text-sm theme-text-muted">Seleccione «Atender pedido» para comenzar la captura.</p>}
                <AjustesSolicitadosTienda tarea={tarea} auth={auth} ocupado={ocupado} onEnviar={onEnviar} onDirtyChange={setAjustesDirty} />
                {momentoRevision && <section id="tienda-estado-pedido" tabIndex={-1} className="gelia-preparacion-seccion registrar-tienda-panel">
                    <div className="registrar-tienda-seccion-titulo"><ClipboardCheck size={18} aria-hidden="true" /><h3>Indique el estado del pedido</h3></div>
                    <p className="gelia-preparacion-ayuda">Elija una opción. Las piezas con detalles se registran después. Si todo está correcto, no se guardan productos.</p>
                    <fieldset className="grid gap-2 m-0 border-0 p-0">
                        <label className="gelia-pedidos-atributo gelia-pedidos-atributo-control"><input type="radio" name="estado-pedido" checked={estadoPedido === 'correcto'} disabled={ocupado} onChange={() => elegirEstadoPedido('correcto')} />Todas las piezas en buen estado</label>
                        <label className="gelia-pedidos-atributo gelia-pedidos-atributo-control"><input type="radio" name="estado-pedido" checked={estadoPedido === 'detalle'} disabled={ocupado} onChange={() => elegirEstadoPedido('detalle')} />Piezas con detalles</label>
                    </fieldset>
                </section>}
                {mostrarProductos && <section id="tienda-productos" tabIndex={-1} className="gelia-preparacion-seccion registrar-tienda-panel">
                    <div className="registrar-tienda-seccion-titulo"><ScanLine size={18} aria-hidden="true" /><h3>Registrar productos</h3><span className="text-xs theme-text-muted">{lineas.length} productos</span></div>
                    <p className="gelia-preparacion-ayuda">Revise el pedido de referencia. Cada lectura agrega una pieza; si repite el código, aumenta su cantidad.</p>
                    {editable && (capturaExcepcion ? <div className="gelia-preparacion-escaner">
                        <label htmlFor="tienda-escanear" className={THEME_LABEL}><ScanLine className="w-4 h-4" aria-hidden="true" />Buscar o escanear</label>
                        <InputConEscanner label="producto" value={codigo} mostrarBotonCamara alEscanear={(leido) => { setSugerencias([]); capturarCodigo(leido); }}
                            onChange={(e) => { setCodigo(e.target.value); programarSugerencias(e.target.value); }}
                            inputProps={{ ...atributosCampo('tienda-escanear'), name: 'codigo_producto', autoComplete: 'off', spellCheck: false, placeholder: 'Nombre, SKU o código…', className: `${THEME_INPUT} w-full`, disabled: ocupado || !tarea.almacen?.id,
                                onKeyDown: (e) => { if (e.key === 'Enter') { e.preventDefault(); setSugerencias([]); capturarCodigo(e.currentTarget.value); } } }} />
                        {sugerencias.length > 0 && <ul className="gelia-preparacion-sugerencias m-0 p-0 list-none">{sugerencias.map((producto) => <li key={producto.id}><button type="button" className={`${BTN_SECONDARY} w-full justify-start`} onClick={() => agregar(producto)}>{producto.descripcion}<span className="theme-text-muted">SKU: {producto.sku}</span></button></li>)}</ul>}
                        {errorCampo('tienda-escanear')}
                        <p className="gelia-preparacion-ayuda" role="status">{!tarea.almacen?.id ? 'La solicitud necesita un almacén asignado para consultar productos.' : escaneo.pendientes ? `Consultando ${escaneo.pendientes} lectura(s)…` : escaneo.ultimo || 'Escriba para buscar, escanee con la pistola o use la cámara. La pieza se agrega al elegirla.'}</p>
                        {escaneo.fallos.map((f, i) => <div key={`${f.codigo}-${i}`} className="gelia-preparacion-error-escaneo" role="alert"><span><strong>{f.codigo}</strong>: {f.mensaje}</span><button type="button" className={BTN_SECONDARY} disabled={ocupado} onClick={() => { escaneo.quitarFallo(i); escaneo.encolar(f.codigo); }}>Reintentar</button><button type="button" className={BTN_SECONDARY} onClick={() => escaneo.quitarFallo(i)} aria-label={`Descartar aviso de ${f.codigo}`}>Descartar aviso</button></div>)}
                    </div> : <>
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
                            <div className="min-w-0 gelia-preparacion-selector"><label htmlFor="tienda-buscar-producto" className={THEME_LABEL}>O buscar por nombre o código</label><SelectorProducto inputId="tienda-buscar-producto" almacenId={tarea.almacen?.id} incluirSinInventario value={seleccion?.id || ''} onChange={(_, p) => setSeleccion(p)} disabled={ocupado || !tarea.almacen?.id} /></div>
                            <button type="button" className={BTN_SECONDARY} onClick={() => agregar()} disabled={!seleccion || ocupado}>Agregar pieza</button>
                        </div>
                    </>)}
                    {!lineas.length && <div className="gelia-preparacion-vacio"><ScanLine className="w-6 h-6" aria-hidden="true" /><strong>Aún no hay productos registrados</strong><p>{capturaExcepcion ? 'Busque o escanee la pieza con detalle.' : 'Escanee un código o busque un producto y seleccione «Agregar pieza». Si no hay disponibilidad, reporte el problema a Ventas.'}</p></div>}
                    <div className="gelia-preparacion-productos">{lineas.map((l, i) => {
                        const registrados = documentosTarea.filter((d) => l.id && Number(d.pedido_bma_tarea_producto_id) === Number(l.id));
                        const estados = { bueno: 'Buen estado', regular: 'Con detalle', malo: 'Malo', danado: 'Dañado', sin_existencia: 'Sin existencias' };
                        if (capturaExcepcion) {
                            const sinExistencia = l.estado_fisico === 'sin_existencia';
                            const marcarSinExistencia = () => {
                                setDirty(true);
                                setLineas((prev) => prev.map((fila, j) => j !== i ? fila : {
                                    ...fila,
                                    estado_fisico: 'sin_existencia', unica_pieza: false, mejor_ejemplar: false,
                                    cantidad_consulta: Number(fila.cantidad) > 0 ? Number(fila.cantidad) : (fila.cantidad_consulta || 1),
                                    cantidad: 0,
                                    observacion: String(fila.observacion || '').trim() || MOTIVO_SIN_EXISTENCIA,
                                    detalle: false,
                                }));
                            };
                            return <article key={l.id || `nueva-${i}`} className="gelia-preparacion-producto">
                                <div className="gelia-preparacion-producto-fila">
                                    <button type="button" className="min-w-0 text-left" aria-expanded={!sinExistencia && Boolean(l.detalle)} aria-controls={`tienda-detalle-${i}`} disabled={ocupado || sinExistencia} onClick={() => cambiar(i, 'detalle', !l.detalle)}>
                                        <h5 className="text-sm font-semibold m-0 break-words">{l.descripcion}</h5>
                                        <span className="text-xs theme-text-muted">SKU: {l.sku}</span>
                                        <p className="gelia-preparacion-condicion m-0">{estados[l.estado_fisico] || 'Con detalle'}{l.observacion ? ` · ${l.observacion}` : ''}</p>
                                    </button>
                                    <button type="button" className={BTN_SECONDARY} aria-label="Quitar pieza" disabled={guardando} onClick={() => setQuitarLinea(i)}><Trash2 className="w-4 h-4" aria-hidden="true" /></button>
                                </div>
                                {sinExistencia && <label className="gelia-pedidos-atributo gelia-pedidos-atributo-control"><input type="checkbox" checked onChange={() => alternarEtiqueta(i, 'sin_existencia')} disabled={ocupado} />Sin existencias</label>}
                                {!sinExistencia && l.detalle && <div id={`tienda-detalle-${i}`} className="gelia-preparacion-detalle">
                                    <label className={THEME_LABEL} htmlFor={`tienda-cantidad-${i}`}>Preparadas<input {...atributosCampo(`tienda-cantidad-${i}`)} aria-label={`Cantidad de ${l.descripcion}`} name={`cantidad-${i}`} type="number" inputMode="numeric" min="0" step="1" disabled={ocupado} className={`${THEME_INPUT} w-full mt-1`} value={l.cantidad} onChange={(e) => cambiar(i, 'cantidad', e.target.value)} /></label>
                                    <div className="gelia-pedidos-atributos" aria-label="Marcas rápidas de la pieza">
                                        <label className="gelia-pedidos-atributo gelia-pedidos-atributo-control"><input type="checkbox" checked={l.unica_pieza} onChange={() => alternarEtiqueta(i, 'unica_pieza')} disabled={ocupado || l.estado_fisico === 'sin_existencia'} />Única pieza</label>
                                        <label className="gelia-pedidos-atributo gelia-pedidos-atributo-control"><input type="checkbox" checked={l.mejor_ejemplar} onChange={() => alternarEtiqueta(i, 'mejor_ejemplar')} disabled={ocupado || l.estado_fisico === 'sin_existencia'} />Mejor ejemplar</label>
                                        {permitirSinExistencia && <label className="gelia-pedidos-atributo gelia-pedidos-atributo-control"><input type="checkbox" checked={false} onChange={marcarSinExistencia} disabled={ocupado} />Sin existencias</label>}
                                    </div>
                                    <label className={THEME_LABEL}>Estado de la pieza<select name={`estado-${i}`} className={`${THEME_SELECT} w-full mt-1`} value={l.estado_fisico === 'bueno' ? 'regular' : l.estado_fisico} onChange={(e) => { if (e.target.value === 'sin_existencia') marcarSinExistencia(); else cambiar(i, 'estado_fisico', e.target.value); }} disabled={ocupado}>
                                        {Object.entries(estados).filter(([estado]) => estado !== 'bueno' && (!requisitos.estados_fisicos_permitidos || requisitos.estados_fisicos_permitidos.includes(estado))).map(([estado, texto]) => <option key={estado} value={estado}>{texto}</option>)}
                                    </select></label>
                                    <label className={THEME_LABEL}>Detalle o motivo<textarea name={`observacion-${i}`} rows={2} maxLength={2000} className={`${THEME_TEXTAREA} w-full mt-1`} value={l.observacion} onChange={(e) => cambiar(i, 'observacion', e.target.value)} disabled={ocupado} placeholder="Describa el daño, faltante o detalle…" /></label>
                                    {fotosProducto(l, i)}
                                </div>}
                                {registrados.length > 0 && <button type="button" className={`${BTN_SECONDARY} mt-2`} onClick={() => verDocs(registrados)}>Ver evidencia registrada ({registrados.length})</button>}
                            </article>;
                        }
                        return <article key={l.id || `nueva-${i}`} className="gelia-preparacion-producto">
                            <div className="gelia-preparacion-producto-fila">
                                <div className="min-w-0"><h5 className="text-sm font-semibold m-0 break-words">{l.descripcion}</h5><span className="text-xs theme-text-muted">SKU: {l.sku}</span><p className="gelia-preparacion-condicion">{estados[l.estado_fisico] || 'Pendiente'}{l.observacion ? ` · ${l.observacion}` : ''}</p></div>
                                {editable ? <label className={THEME_LABEL} htmlFor={`tienda-cantidad-${i}`}>Preparadas<input {...atributosCampo(`tienda-cantidad-${i}`)} aria-label={`Cantidad de ${l.descripcion}`} name={`cantidad-${i}`} type="number" inputMode="numeric" min="0" step="1" disabled={ocupado} className={`${THEME_INPUT} w-full mt-1`} value={l.cantidad} onChange={(e) => cambiar(i, 'cantidad', e.target.value)} /></label>
                                    : <span className="gelia-preparacion-total">{l.cantidad === '' ? 'Pendiente' : `${l.cantidad} piezas`}</span>}
                            </div>
                            {errorCampo(`tienda-cantidad-${i}`)}
                            {editable && (!capturaEstadoPieza || !l.id) && <div className="gelia-preparacion-producto-acciones">{!capturaEstadoPieza && <button type="button" className={BTN_SECONDARY} aria-expanded={l.detalle || Number(l.cantidad) === 0} aria-controls={`tienda-detalle-${i}`} disabled={ocupado} onClick={() => cambiar(i, 'detalle', !l.detalle)}>{l.detalle ? 'Ocultar detalle' : 'Reportar detalle'}</button>}
                                {!l.id && <button type="button" className={BTN_SECONDARY} disabled={guardando} onClick={() => setQuitarLinea(i)}><Trash2 className="w-4 h-4" aria-hidden="true" />Quitar pieza</button>}</div>}
                            {editable && <div className="gelia-pedidos-atributos" aria-label="Marcas rápidas de la pieza">
                                <label className="gelia-pedidos-atributo gelia-pedidos-atributo-control"><input type="checkbox" checked={l.unica_pieza} onChange={() => alternarEtiqueta(i, 'unica_pieza')} disabled={ocupado || l.estado_fisico === 'sin_existencia'} />Única pieza</label>
                                <label className="gelia-pedidos-atributo gelia-pedidos-atributo-control"><input type="checkbox" checked={l.mejor_ejemplar} onChange={() => alternarEtiqueta(i, 'mejor_ejemplar')} disabled={ocupado || l.estado_fisico === 'sin_existencia'} />Mejor ejemplar</label>
                                {permitirSinExistencia && <label className="gelia-pedidos-atributo gelia-pedidos-atributo-control"><input type="checkbox" checked={l.estado_fisico === 'sin_existencia'} onChange={() => alternarEtiqueta(i, 'sin_existencia')} disabled={ocupado} />Sin existencias</label>}
                            </div>}
                            {editable && (capturaEstadoPieza || l.detalle || Number(l.cantidad) === 0) && <div id={`tienda-detalle-${i}`} className="gelia-preparacion-detalle">
                                <label className={THEME_LABEL}>Estado de la pieza<select name={`estado-${i}`} className={`${THEME_SELECT} w-full mt-1`} value={l.estado_fisico} onChange={(e) => {
                                    const estado = e.target.value;
                                    if (estado !== 'sin_existencia') { cambiar(i, 'estado_fisico', estado); return; }
                                    setDirty(true);
                                    setLineas((prev) => prev.map((fila, j) => j !== i ? fila : {
                                        ...fila,
                                        estado_fisico: 'sin_existencia', unica_pieza: false, mejor_ejemplar: false,
                                        cantidad_consulta: Number(fila.cantidad) > 0 ? Number(fila.cantidad) : (fila.cantidad_consulta || 1),
                                        cantidad: 0,
                                        observacion: String(fila.observacion || '').trim() || MOTIVO_SIN_EXISTENCIA,
                                        detalle: true,
                                    }));
                                }} disabled={ocupado}>
                                    {Object.entries(estados).filter(([estado]) => !requisitos.estados_fisicos_permitidos || requisitos.estados_fisicos_permitidos.includes(estado)).map(([estado, texto]) => <option key={estado} value={estado}>{texto}</option>)}
                                </select></label>
                                <label className={THEME_LABEL}>Detalle o motivo<textarea name={`observacion-${i}`} rows={2} maxLength={2000} className={`${THEME_TEXTAREA} w-full mt-1`} value={l.observacion} onChange={(e) => cambiar(i, 'observacion', e.target.value)} disabled={ocupado} placeholder={l.estado_fisico === 'sin_existencia' ? 'Opcional si ya marcó Sin existencias' : 'Describa el daño, faltante o detalle…'} /></label>
                                {l.estado_fisico === 'sin_existencia' && <p className="text-xs font-bold theme-text-info m-0">Sin existencias: Ventas verá este aviso. No hace falta redactar un motivo si ya marcó la etiqueta.</p>}
                                {fotosProducto(l, i)}
                            </div>}
                            {editable && !capturaEstadoPieza && !esRecoge && requisitos.evidencia_por_producto && !l.detalle && Number(l.cantidad) > 0 && fotosProducto(l, i)}
                            {registrados.length > 0 && <button type="button" className={`${BTN_SECONDARY} mt-2`} onClick={() => verDocs(registrados)}>Ver evidencia registrada ({registrados.length})</button>}
                        </article>;
                    })}</div>
                </section>}
                {editable && mostrarEvidencias && <section id="tienda-evidencias" tabIndex={-1} className="gelia-preparacion-seccion registrar-tienda-panel">
                        <div className="registrar-tienda-seccion-titulo"><ImagePlus size={18} aria-hidden="true" /><h3>Evidencias</h3><span className="text-xs theme-text-muted">{fotoRevisionObligatoria || evidenciaGeneralObligatoria ? 'Obligatorias' : 'Opcionales'}</span></div>
                        <p className="gelia-preparacion-ayuda">{fotoRevisionObligatoria ? 'La foto del conjunto físico es obligatoria. El PDF del pedido no la sustituye.' : 'Fotografíe el conjunto de productos que está preparando. Revise las imágenes antes de enviar.'}</p>
                        {puedeFotosCelular && <button type="button" className={`${BTN_SECONDARY} gelia-preparacion-celular`} disabled={ocupado} onClick={() => setModalQr(true)}><Smartphone className="w-4 h-4" aria-hidden="true" />Fotos con celular</button>}
                        <GaleriaEvidencias id="tienda-evidencia-general" maxArchivos={10} label={fotoRevisionObligatoria ? 'Foto del conjunto físico' : (esRecoge ? 'Fotos del apartado (opcional)' : 'Evidencia del conjunto preparado')} obligatorio={fotoRevisionObligatoria || evidenciaGeneralObligatoria} archivos={evidencias.archivos} previews={evidencias.previews}
                            error={validando ? faltantes.find((f) => f.campo === 'tienda-evidencia-general')?.texto : undefined} onChange={(files, previews) => { setDirty(true); setEvidencias({ archivos: files, previews }); }} onVer={verDocs} disabled={ocupado} />
                        {fotosGenerales.length > 0 && <div className="gelia-preparacion-registradas"><CheckCircle2 className="w-4 h-4" aria-hidden="true" /><span>{fotosGenerales.length} evidencias ya guardadas</span><button type="button" className={BTN_SECONDARY} onClick={() => verDocs(fotosGenerales)}>Revisar evidencias</button></div>}
                        {momentoRevision && <div className="gelia-preparacion-resumen" aria-label="Revisión de la respuesta">{estadoPedido === 'correcto' ? <strong className="gelia-preparacion-correctas">Todas las piezas correctas</strong> : <strong>{`${lineas.filter(esExcepcionFisica).length} piezas afectadas`}</strong>}<p>Al enviar, Ventas recibe la respuesta de disponibilidad. Todavía no hay apartado.</p></div>}
                    </section>}
                    {editable && !momentoRevision && <section id="tienda-cierre" tabIndex={-1} className="gelia-preparacion-seccion registrar-tienda-panel">
                        <div className="registrar-tienda-seccion-titulo"><ClipboardCheck size={18} aria-hidden="true" /><h3>{tituloCierre}</h3></div>
                        {esRecoge ? <p className="gelia-preparacion-ayuda">{momentoRevision ? 'Confirme el conjunto o marque solo las piezas afectadas. La foto física es obligatoria y el PDF del pedido no la sustituye.' : momentoSeparacion ? 'La vendedora aprobó la separación al cerrar la consulta. Separe el pedido completo de referencia y confirme; no necesita volver a registrar las piezas.' : 'Confirme las piezas separadas para avisar a Ventas.'}</p>
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
                        {puedeRegistrarRevision && <div className="grid gap-3 mb-3">
                            <p className="gelia-preparacion-ayuda m-0">La revisión usa las piezas y la foto ya capturadas. No hace falta transcribir las piezas correctas. Única pieza y Mejor ejemplar no son faltante ni daño. El apartado físico se confirma aparte.</p>
                            {lineas.some(esExcepcionFisica) ? <ul className="m-0 pl-4 grid gap-1">{lineas.filter(esExcepcionFisica).map((linea) => <li key={linea.id || linea.sku} className="text-sm theme-text-main">{linea.descripcion} · {Math.max(1, Number(linea.cantidad_consulta || linea.cantidad) || 1)}</li>)}</ul>
                                : <label className="flex items-start gap-2 text-sm theme-text-main"><input type="checkbox" className="mt-1" checked={confirmacionConjunto} disabled={ocupado} onChange={(evento) => setConfirmacionConjunto(evento.target.checked)} />Confirmo el conjunto sin detalles ni faltantes.</label>}
                        </div>}
                        {revisionRegistrada && esRecoge && <p className="gelia-preparacion-ayuda" role="status">La revisión ya quedó registrada. Confirmar el apartado es un paso distinto y no repite la captura.</p>}
                        <div className="gelia-preparacion-resumen" aria-label="Revisión de la respuesta"><strong>{momentoRevision && lineas.some(esExcepcionFisica) ? `${lineas.filter(esExcepcionFisica).length} piezas afectadas` : momentoSeparacion ? 'Pedido completo aprobado' : `${total} piezas · ${lineas.length} productos`}</strong><p>{momentoRevision ? 'Al enviar, Ventas recibe la respuesta de disponibilidad. Todavía no hay apartado.' : esRecoge ? 'Al confirmar la separación, Ventas recibe el apartado físico.' : 'Al responder, Ventas recibirá las cantidades, el estado de la mercancía y las evidencias registradas.'}</p>
                            {faltantes.length > 0 && <div className="gelia-preparacion-pendientes"><span>Por completar antes de enviar:</span><ul>{faltantes.map((f) => <li key={f.campo}><button type="button" onClick={() => { setValidando(true); irACampo(f.campo); }}>{f.texto}</button></li>)}</ul></div>}
                        </div>
                    </section>}
                    {editable && modoIncidencia && <section className="gelia-preparacion-seccion gelia-preparacion-incidencia"><h4>Reportar error a Ventas</h4><p className="gelia-preparacion-ayuda">Describa qué impide continuar. Puede reportarlo aunque no haya encontrado piezas.</p><label htmlFor="tienda-motivo" className={THEME_LABEL}>Qué necesita resolver Ventas *<textarea id="tienda-motivo" name="motivo_incidencia" maxLength={2000} className={`${THEME_TEXTAREA} w-full mt-1`} rows={3} value={motivo} disabled={ocupado} onChange={(e) => { setDirty(true); setMotivo(e.target.value); }} /></label><div className="flex flex-wrap gap-2 mt-3"><button type="button" className={BTN_PRIMARY} disabled={guardando} onClick={() => guardar(false, true)}>Reportar error a Ventas</button><button type="button" className={BTN_SECONDARY} disabled={ocupado} onClick={() => setModoIncidencia(false)}>Cancelar reporte</button></div></section>}
            </div>
            {mostrarPedido && <aside id="tienda-pedido-referencia" tabIndex={-1} className="gelia-preparacion-documento" aria-label="Pedido de referencia"><button type="button" className={`${BTN_SECONDARY} mb-3 min-h-[44px]`} onClick={() => { setMostrarPedido(false); irACampo('tienda-productos'); }}>{momentoSeparacion ? 'Volver a la confirmación' : 'Volver a productos'}</button><DocumentoSolicitudTienda tareaId={tarea.id} documentos={documentos} /></aside>}
        </div>
        <footer className="gelia-preparacion-footer">
            {editable ? <>
                <div className="gelia-preparacion-estado-guardado" role="status"><strong>{guardando ? escaneo.pendientes ? 'Procesando lecturas…' : 'Guardando…' : momentoSeparacion ? 'Separación aprobada' : dirty ? 'Cambios sin guardar' : 'Sin cambios pendientes'}</strong><span>{vencida ? 'Solicitud vencida. Guarde su avance o reporte a Ventas.' : momentoRevision ? 'Envíe la respuesta de disponibilidad. La separación se confirma cuando la consulta esté cerrada.' : momentoEspera ? 'Hay un ajuste pendiente. No confirme la separación.' : momentoSeparacion ? 'La consulta está cerrada. Separe el pedido aprobado y confirme el apartado.' : faltantes.length ? `${faltantes.length} pendiente(s) para enviar. Puede guardar su avance.` : 'Respuesta completa. Revise y confirme para avisar a Ventas.'}</span></div>
                <div className="gelia-preparacion-footer-acciones">
                    {!momentoEspera && !momentoSeparacion && <button type="button" className={BTN_SECONDARY} disabled={guardando} onClick={() => guardar()}>Guardar y continuar después</button>}
                    {momentoRevision && <button type="button" className={BTN_PRIMARY} disabled={guardando || vencida} onClick={registrarRevision}><CheckCircle2 className="w-4 h-4" aria-hidden="true" />{procesando ? 'Guardando…' : 'Enviar respuesta'}</button>}
                    {momentoSeparacion && <button type="button" className={BTN_PRIMARY} disabled={guardando || vencida} onClick={() => guardar(true)}><CheckCircle2 className="w-4 h-4" aria-hidden="true" />{procesando ? 'Guardando…' : 'Confirmar pedido separado'}</button>}
                    {momentoEspera && <p className="text-sm theme-text-main m-0">Hay un ajuste pendiente. No se confirma la separación hasta recibir la respuesta vigente.</p>}
                    {!consultaTienda && <button type="button" className={BTN_PRIMARY} disabled={guardando || vencida} onClick={() => guardar(true)}><CheckCircle2 className="w-4 h-4" aria-hidden="true" />{procesando ? 'Guardando…' : esRecoge ? 'Confirmar apartado' : 'Responder preparación'}</button>}
                </div>
            </> : <p className="text-sm theme-text-main m-0">{tarea.pedido?.es_t01_documental ? 'Pedido completo conforme a la consulta aprobada' : `Total preparado: ${total} piezas`}{tarea.apartado?.ubicacion ? ` · Ubicación: ${tarea.apartado.ubicacion}` : ''}{apartadoConfirmado ? ` · Apartado confirmado. ${tarea.apartado?.pago_confirmado_at ? 'Pago presencial confirmado.' : 'Pago presencial pendiente.'} ${tarea.apartado?.entregada_at ? 'Entrega registrada.' : 'Entrega pendiente.'}` : ''}</p>}
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

export default function PrepararApartadoRecoge(props) {
    if (props.tarea.pedido?.area_revision === 'preparacion_tienda'
        && (!props.tarea.modalidad || props.tarea.modalidad.codigo === 'RECOGE_TIENDA')) {
        return <ConsultaPreparacionTienda {...props} />;
    }
    return <PrepararApartadoLegado {...props} />;
}
