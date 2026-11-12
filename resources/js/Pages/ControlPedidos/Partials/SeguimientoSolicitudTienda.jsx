import { uuidSolicitud } from '../../../utils/uuidSolicitud';
import AccionPrincipalTienda from './AccionPrincipalTienda';
import React, { useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';
import { AlertTriangle, RotateCcw, ChevronDown, CheckCircle2, ClipboardCheck, Clock, PackageMinus, PackagePlus, FileImage, Search, Send, Package } from 'lucide-react';
import {
    GELIA_BTN_OUTLINE,
    GELIA_ICON_BOX,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_INPUT,
    THEME_LABEL,
    THEME_SELECT,
    THEME_TEXTAREA,
} from '../../../utils/geliaTheme';
import SelectorProducto from '../../../Components/Almacenes/SelectorProducto';
import GaleriaEvidenciasPedido from './GaleriaEvidenciasPedido';
import { CUPO_ADJUNTOS_CONSULTA, bytesConsulta, enviarEnLotes } from './lotesAdjuntosConsulta';
import AvisoOperativoPedido from './AvisoOperativoPedido';
import ModalVistaPreviaDocumento from './ModalVistaPreviaDocumento';
import { formatearFechaNegocio } from './pedidosBmaStyles';
import AccionesRapidasDisponibilidad from './AccionesRapidasDisponibilidad';
import ResumenCambiosDisponibilidad from './ResumenCambiosDisponibilidad';
import RespuestaPreparacionTienda from './RespuestaPreparacionTienda';
import RespuestaDisponibilidadPedido from './RespuestaDisponibilidadPedido';

const ESTADOS_REVISION = ['sin_excepciones', 'excepciones_pendientes', 'decision_registrada'];
const MOTIVOS_RETIRO = [
    'El cliente ya no lo quiere',
    'Cambió de aroma',
    'El precio no convence',
    'Pieza duplicada',
    'El regalo ya no aplica',
    'Otro',
];

function cabeceras() {
    return {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
}

function mensajeDe(error, reserva) {
    if (error?.response?.status === 419) return 'La sesión cambió o venció. Recargue GELIA antes de volver a guardar; no se confirmó la operación.';
    const datos = error?.response?.data;
    const lista = datos?.errors ? Object.values(datos.errors).flat() : [];
    return lista[0] || datos?.message || reserva;
}

function preparacionVisible(tarea) {
    const respuesta = tarea?.respuesta;
    const fotos = (respuesta?.documentos || []).some((documento) => documento?.url);
    return Boolean(respuesta) && (respuesta.respondida || tarea?.estado === 'CON_INCIDENCIA' || fotos);
}

export function respuestaDeTiendaEmitida(pedido) {
    const estado = pedido?.solicitud_preparacion?.revision_disponibilidad?.estado;
    if (ESTADOS_REVISION.includes(estado)) return true;
    const tarea = pedido?.tarea_preparacion;
    if (preparacionVisible(tarea) || tarea?.estado === 'RESPONDIDA' || tarea?.estado === 'CON_INCIDENCIA') return true;
    return Boolean(pedido?.pesaje_respondido_at);
}

function piezasRespuesta(tarea, revisiones = []) {
    const respuesta = tarea?.respuesta?.productos || [];
    const productos = respuesta.length ? respuesta : (tarea?.productos || []);
    const faltantes = revisiones.filter(p => p.estado_fisico === 'sin_existencia');
    const coincide = (pieza, revision) => pieza.sku && revision.sku
        ? pieza.sku === revision.sku
        : (pieza.descripcion || pieza.descripcion_snapshot) === (revision.descripcion_producto || revision.descripcion);
    const conocidas = productos
        .filter(p => !faltantes.some(r => coincide(p, r)))
        .map(p => ({ id: `pieza-${p.id}`, sku: p.sku || '', descripcion: p.descripcion || p.descripcion_snapshot || '', cantidad: Math.max(1, Number(p.cantidad || p.cantidad_encontrada || p.cantidad_solicitada) || 1), estado: p.estado_fisico || '' }));
    const reportadas = revisiones.filter(p => !p.resolucion && (p.estado_fisico === 'sin_existencia' || !productos.length))
        .map(p => ({ id: `revision-${p.id}`, revision_id: p.estado_fisico === 'sin_existencia' ? p.id : undefined, sku: p.sku || '', descripcion: p.descripcion_producto || p.descripcion, cantidad: Math.max(1, Number(p.cantidad_solicitada) || 1), estado: p.estado_fisico }));
    return [...reportadas, ...conocidas];
}


function PanelEspera({ label, icon: Icono, children }) {
    return (
        <div
            className="p-4 rounded-xl border-2 grid gap-2"
            role="status"
            style={{
                borderColor: 'color-mix(in srgb, var(--color-aviso) 55%, var(--theme-border))',
                backgroundColor: 'color-mix(in srgb, var(--color-aviso) 14%, var(--theme-surface-solid))',
            }}
        >
            <p className="text-xs font-bold m-0" style={{ color: 'var(--theme-text-main)' }}>{label}</p>
            <div className="flex items-start gap-2 text-sm theme-text-main">
                {Icono && <Icono className="w-4 h-4 shrink-0 mt-0.5" style={{ color: 'var(--color-aviso)' }} aria-hidden="true" />}
                <div className="min-w-0">{children}</div>
            </div>
        </div>
    );
}

function EsperaRevision({ area, nombreAlmacen }) {
    const tienda = area !== 'control_pedidos';
    const lugar = tienda && nombreAlmacen ? ` a ${nombreAlmacen}` : '';
    return (
        <div className="mt-4">
            <PanelEspera
                label={tienda ? 'Esperando respuesta de Tienda' : 'Esperando respuesta de CEDIS'}
                icon={Clock}
            >
                {tienda
                    ? `La solicitud ya se envió${lugar}. Aquí aparecerá el resultado de las piezas cuando Tienda responda. No hay pago ni entrega.`
                    : 'La consulta ya se envió. Aquí aparecerá el resultado de las piezas cuando CEDIS responda. No hay traslado, pago ni entrega.'}
            </PanelEspera>
        </div>
    );
}

function ResultadoDisponibilidad({ area, pedido, revisiones, onVer, accionesDePieza }) {
    const revision = pedido?.solicitud_preparacion?.revision_disponibilidad;
    if (!revision?.estado || preparacionVisible(pedido?.tarea_preparacion)) return null;
    const documentos = (pedido.documentos || []).filter(d => d.tipo === 'evidencia_condicion' && d.url && d.activo !== false && !String(d.comentario || '').startsWith('Evidencia petición'));
    const capturas = documentos.filter(d => !d.relacion_tipo || d.relacion_tipo === 'revision_general');
    const generales = capturas.filter(d => !revision.evidencia_actual_ids || revision.evidencia_actual_ids.includes(d.id));
    const anteriores = capturas.filter(d => revision.evidencia_actual_ids && !revision.evidencia_actual_ids.includes(d.id));
    const piezas = (revisiones || []).filter(p => ['regular', 'malo', 'danado', 'sin_existencia'].includes(p.estado_fisico));
    const filas = piezas.map(p => ({
        id: p.id, descripcion: p.descripcion_producto || p.descripcion, sku: p.sku,
        cantidad: p.cantidad_encontrada ?? (p.estado_fisico === 'sin_existencia' ? 0 : p.cantidad_solicitada),
        estado_fisico: p.estado_fisico, unica_pieza: p.unica_pieza, mejor_ejemplar: p.mejor_ejemplar,
        observacion: p.comentario, original: p,
        documentos: documentos.filter(d => d.relacion_tipo === 'revision_producto' && Number(d.relacion_id) === Number(p.id)),
    }));
    return <RespuestaDisponibilidadPedido
        titulo="Respuesta de disponibilidad" origen={area === 'preparacion_tienda' ? 'Tienda' : 'CEDIS'} ariaLabel={area === 'preparacion_tienda' ? 'Resultado de la revisión en tienda' : 'Resultado de la consulta en CEDIS'}
        responsable={pedido.pesaje_respondido_por?.name || pedido.pesajeRespondidoPor?.name || revision.respondida_por_nombre || (pedido.historial || []).find(h => Number(h.usuario?.id) === Number(revision.respondida_por_id))?.usuario?.name}
        fecha={revision.respondida_at} estado={revision.estado} aceptacionVigente={revision.aceptacion_vigente !== false}
        filas={filas} generales={generales} anteriores={anteriores} cantidad={piezas.length ? piezas.reduce((s,p) => s + Number(p.cantidad_solicitada || 0), 0) : null}
        onVer={onVer} accionesDePieza={accionesDePieza}
    />;
}

function ConfirmacionSolicitud({ pedidoId, visible, cerrada, onCerrada, faltante, onReabrir, soloLectura, destinoAccion, area }) {
    const [ocupado, setOcupado] = useState(false);
    const [error, setError] = useState('');
    if (!visible && !cerrada) return null;

    const cerrar = async () => {
        if (ocupado || !pedidoId) return;
        setOcupado(true);
        setError('');
        try {
            const { data } = await axios.post(`/control-pedidos/${pedidoId}/cerrar-consulta`, {}, { headers: cabeceras() });
            onCerrada(data);
        } catch (errorCierre) {
            setError(mensajeDe(errorCierre, 'No se pudo confirmar la mercancía.'));
        } finally {
            setOcupado(false);
        }
    };

    if (cerrada && faltante) return <div className="tienda-alerta-disponibilidad rounded-xl border p-4 grid gap-3" role="alert"><div className="flex gap-2 items-start"><AlertTriangle size={20} className="shrink-0" /><div><strong>Consulta cerrada con piezas sin existencias</strong><p className="text-sm m-0 mt-1">Reabre la consulta y retira o sustituye los faltantes antes de continuar.</p></div></div>{!soloLectura && <button type="button" className={`${GELIA_BTN_OUTLINE} justify-self-start`} onClick={onReabrir}><RotateCcw size={16} />Reabrir consulta</button>}</div>;
    if (cerrada) {
        return (
            <AvisoOperativoPedido label="Consulta cerrada" tono="success" icon={CheckCircle2}>
                La mercancía quedó confirmada con el cliente.
            </AvisoOperativoPedido>
        );
    }

    return (
        <PanelEspera label="Siguiente paso" icon={CheckCircle2}>
            <p className="m-0">Confirme la mercancía con el cliente. Al cerrar, la solicitud queda aceptada.</p>
            {error && (
                <p className="m-0 mt-2" role="alert">
                    {error}
                </p>
            )}
            <AccionPrincipalTienda destino={destinoAccion}><button
                type="button"
                className={`${THEME_BTN_PRIMARY} ${destinoAccion === undefined ? 'mt-3' : ''} min-h-[44px] w-full sm:w-auto`}
                disabled={ocupado}
                onClick={cerrar}
            >
                <CheckCircle2 className="w-4 h-4" aria-hidden="true" />
                {ocupado ? 'Confirmando…' : destinoAccion === undefined ? 'Cerrar consulta' : area === 'preparacion_tienda' ? 'Confirmar separación de pedido en Tienda' : 'Confirmar mercancía y cerrar consulta'}
            </button></AccionPrincipalTienda>
        </PanelEspera>
    );
}

export function AjustePiezas({ pedidoId, pedido, solicitud, onSolicitud, onAviso, onVer }) {
    const productos = useMemo(() => {
        const retiradas = new Set((solicitud?.retiro_piezas || []).flatMap(retiro => retiro.faltantes_retirados || []).map(Number));
        return piezasRespuesta(pedido?.tarea_preparacion, pedido?.revisiones_producto || pedido?.revisionesProducto || [])
            .filter(pieza => !retiradas.has(Number(pieza.revision_id)));
    }, [pedido, solicitud]);
    const claveAnexo = useRef(uuidSolicitud());
    const claveRetiro = useRef(uuidSolicitud());
    const [modo, setModo] = useState('anexo');
    const [entrada, setEntrada] = useState('documento');
    const [producto, setProducto] = useState(null);
    const [seleccionadas, setSeleccionadas] = useState([]);
    const [cantidad, setCantidad] = useState('1');
    const [nota, setNota] = useState('');
    const [evidenciasAnexo, setEvidenciasAnexo] = useState([]);
    const [marcadas, setMarcadas] = useState({});
    const [cantidades, setCantidades] = useState({});
    const [motivoOpcion, setMotivoOpcion] = useState('');
    const [motivoLibre, setMotivoLibre] = useState('');
    const [entradaRetiro, setEntradaRetiro] = useState(productos.length ? 'catalogo' : 'documento');
    const [documentosRetiro, setDocumentosRetiro] = useState([]);
    const [previewsDocumentos, setPreviewsDocumentos] = useState([]);
    const [evidenciasRetiro, setEvidenciasRetiro] = useState([]);
    const [previewsRetiro, setPreviewsRetiro] = useState([]);
    const [piezaBuscada, setPiezaBuscada] = useState(null);
    const [cantidadBuscada, setCantidadBuscada] = useState('1');
    const [agregadas, setAgregadas] = useState([]);
    const [manual, setManual] = useState(false);
    const [ocupado, setOcupado] = useState(false);
    const [error, setError] = useState('');
    const [previewAnexo, setPreviewAnexo] = useState([]);
    const bytesGuardados = bytesConsulta(solicitud);
    const mostrarBusqueda = productos.length === 0 || manual;
    const campo = `${THEME_INPUT} w-full min-h-[44px] text-base`;

    const alternar = (pieza) => {
        setMarcadas((actual) => ({ ...actual, [pieza.id]: !actual[pieza.id] }));
        setCantidades((actual) => ({ ...actual, [pieza.id]: actual[pieza.id] || String(pieza.cantidad) }));
    };

    const agregarProducto = () => {
        if (!producto?.sku || !producto?.descripcion || Number(cantidad) < 1) {
            setError('Seleccione el producto y la cantidad.');
            return;
        }
        setSeleccionadas((actual) => {
            const indice = actual.findIndex((pieza) => String(pieza.sku) === String(producto.sku));
            const pieza = {
                producto_id: producto.id,
                sku: producto.sku,
                descripcion: producto.descripcion,
                cantidad: Number(cantidad),
            };
            if (indice < 0) return [...actual, pieza];
            return actual.map((fila, i) => (i === indice ? { ...fila, cantidad: fila.cantidad + pieza.cantidad } : fila));
        });
        setProducto(null);
        setCantidad('1');
        setError('');
    };

    const solicitarAnexo = async () => {
        if (ocupado) return;
        if (evidenciasAnexo.length === 0) {
            setError(entrada === 'documento' ? 'Adjunte el PDF o la captura del anexo.' : 'Adjunte el soporte del anexo.');
            return;
        }
        if (entrada === 'catalogo' && seleccionadas.length === 0) {
            setError('Seleccione al menos un producto y su cantidad.');
            return;
        }
        if (bytesGuardados + evidenciasAnexo.reduce((suma, archivo) => suma + Number(archivo.size || 0), 0) > CUPO_ADJUNTOS_CONSULTA) {
            setError('Los archivos de esta consulta superan 50 MB en total. Quite alguno antes de enviar. Lo ya guardado se conserva.');
            return;
        }
        setOcupado(true);
        setError('');
        try {
            const respuesta = await enviarEnLotes({
                url: `/control-pedidos/${pedidoId}/anexo-tienda`,
                archivos: evidenciasAnexo,
                headers: cabeceras(),
                post: (url, form, opciones) => axios.post(url, form, opciones),
                armar: (lote) => {
                    const form = new FormData();
                    form.append('solicitud_id', claveAnexo.current);
                    form.append('entrada', entrada);
                    lote.forEach((archivo) => form.append('evidencias[]', archivo));
                    if (lote[0]) form.append('evidencia', lote[0]);
                    if (nota.trim()) form.append('condicion', nota.trim());
                    if (entrada === 'catalogo') {
                        seleccionadas.forEach((pieza, indice) => {
                            if (pieza.revision_id) form.append(`piezas[${indice}][revision_id]`, String(pieza.revision_id));
                            form.append(`piezas[${indice}][sku]`, pieza.sku);
                            form.append(`piezas[${indice}][descripcion]`, pieza.descripcion);
                            form.append(`piezas[${indice}][cantidad]`, String(pieza.cantidad));
                            if (pieza.producto_id) form.append(`piezas[${indice}][producto_id]`, String(pieza.producto_id));
                            form.append(`piezas[${indice}][condicion]`, nota.trim() || 'Pendiente de revisión');
                        });
                    }
                    return form;
                },
            });
            const lineas = respuesta.data?.expediente?.anexo?.sin_remision;
            if (respuesta.data?.solicitud_preparacion) onSolicitud(respuesta.data.solicitud_preparacion);
            else if (Array.isArray(lineas)) onSolicitud({ ...solicitud, anexo_sin_remision: lineas });
            claveAnexo.current = uuidSolicitud();
            setSeleccionadas([]);
            setProducto(null);
            setCantidad('1');
            setNota('');
            setEvidenciasAnexo([]);
            setPreviewAnexo([]);
            onAviso(
                respuesta.data?.folio && respuesta.data?.pedido_id && respuesta.data.pedido_id !== pedidoId
                    ? `Anexo registrado en ${respuesta.data.folio}. Falta su cobertura y la integración física.`
                    : entrada === 'documento'
                        ? 'Anexo documental enviado. Quien prepara identifica las piezas. No confirma disponibilidad.'
                        : 'Anexo con productos enviado. Quien prepara lo revisa. No confirma disponibilidad.',
            );
        } catch (errorAnexo) {
            setError(mensajeDe(errorAnexo, 'No se registró el anexo.'));
        } finally {
            setOcupado(false);
        }
    };

    const agregarPiezaBuscada = () => {
        if (!piezaBuscada?.sku || !piezaBuscada?.descripcion || Number(cantidadBuscada) < 1) {
            setError('Seleccione el producto y la cantidad.');
            return;
        }
        setAgregadas((actual) => {
            const indice = actual.findIndex((pieza) => String(pieza.sku) === String(piezaBuscada.sku));
            const pieza = {
                sku: piezaBuscada.sku,
                descripcion: piezaBuscada.descripcion,
                cantidad: Number(cantidadBuscada),
            };
            if (indice < 0) return [...actual, pieza];
            return actual.map((fila, i) => (i === indice ? { ...fila, cantidad: fila.cantidad + pieza.cantidad } : fila));
        });
        setPiezaBuscada(null);
        setCantidadBuscada('1');
        setError('');
    };

    const solicitarRetiro = async () => {
        if (ocupado) return;
        const motivo = motivoOpcion === 'Otro' ? motivoLibre.trim() : motivoOpcion;
        const desdeLista = productos
                .filter((pieza) => marcadas[pieza.id])
                .map((pieza) => ({
                    sku: pieza.sku,
                    revision_id: pieza.revision_id,
                    descripcion: pieza.descripcion,
                    cantidad: Math.max(1, Number(cantidades[pieza.id] || pieza.cantidad) || 1),
                }))
                .filter((pieza) => (pieza.sku || pieza.revision_id) && pieza.descripcion);
        const elegidas = mostrarBusqueda ? [...desdeLista] : desdeLista;
        if (mostrarBusqueda) {
            agregadas.forEach((pieza) => {
                const indice = elegidas.findIndex((fila) => String(fila.sku) === String(pieza.sku));
                if (indice < 0) elegidas.push(pieza);
                else elegidas[indice] = { ...elegidas[indice], cantidad: elegidas[indice].cantidad + pieza.cantidad };
            });
        }
        if (!motivo) {
            setError(motivoOpcion === 'Otro' ? 'Escriba el motivo del retiro.' : 'Elija el motivo del retiro.');
            return;
        }
        if (entradaRetiro === 'documento' && documentosRetiro.length === 0) {
            setError('Adjunte el PDF o las capturas de las piezas a retirar.');
            return;
        }
        if (entradaRetiro === 'catalogo' && elegidas.length === 0) {
            setError('Elija las piezas y el motivo del retiro.');
            return;
        }
        const seleccionRetiro = [...documentosRetiro, ...evidenciasRetiro];
        if (bytesGuardados + seleccionRetiro.reduce((suma, archivo) => suma + Number(archivo.size || 0), 0) > CUPO_ADJUNTOS_CONSULTA) {
            setError('Los archivos de esta consulta superan 50 MB en total. Quite alguno antes de enviar. Lo ya guardado se conserva.');
            return;
        }
        setOcupado(true);
        setError('');
        const cola = [
            ...documentosRetiro.map((archivo) => ({ archivo, campo: 'documentos[]', size: archivo.size })),
            ...evidenciasRetiro.map((archivo) => ({ archivo, campo: 'evidencias[]', size: archivo.size })),
        ];
        try {
            const respuesta = await enviarEnLotes({
                url: `/control-pedidos/${pedidoId}/retiro-piezas-tienda`,
                archivos: cola.length ? cola : [{ size: 0, archivo: null, campo: '' }],
                headers: cabeceras(),
                post: (url, form, opciones) => axios.post(url, form, opciones),
                armar: (lote) => {
                    const form = new FormData();
                    form.append('solicitud_id', claveRetiro.current);
                    form.append('entrada', entradaRetiro);
                    form.append('motivo', motivo);
                    form.append('motivo_opcion', motivoOpcion);
                    if (entradaRetiro === 'catalogo') {
                        elegidas.forEach((pieza, indice) => {
                            if (pieza.revision_id) form.append(`piezas[${indice}][revision_id]`, String(pieza.revision_id));
                            form.append(`piezas[${indice}][sku]`, pieza.sku);
                            form.append(`piezas[${indice}][descripcion]`, pieza.descripcion);
                            form.append(`piezas[${indice}][cantidad]`, String(pieza.cantidad));
                        });
                    }
                    lote.forEach((item) => { if (item?.archivo && item.campo) form.append(item.campo, item.archivo); });
                    return form;
                },
            });
            if (respuesta.data?.solicitud_preparacion) onSolicitud(respuesta.data.solicitud_preparacion);
            claveRetiro.current = uuidSolicitud();
            [...previewsRetiro, ...previewsDocumentos].forEach((preview) => {
                if (preview.url?.startsWith('blob:')) URL.revokeObjectURL(preview.url);
            });
            setMarcadas({});
            setMotivoOpcion('');
            setMotivoLibre('');
            setDocumentosRetiro([]);
            setPreviewsDocumentos([]);
            setEvidenciasRetiro([]);
            setPreviewsRetiro([]);
            setAgregadas([]);
            setPiezaBuscada(null);
            setCantidadBuscada('1');
            onAviso(respuesta.data?.message || 'Retiro solicitado.');
        } catch (errorRetiro) {
            setError(mensajeDe(errorRetiro, 'No se solicitó el retiro.'));
        } finally {
            setOcupado(false);
        }
    };

    return (
        <section className="registrar-tienda-panel tienda-ajustes" aria-label="Ajuste de piezas">
            <div className="registrar-tienda-seccion-titulo">
                <PackageMinus size={18} aria-hidden="true" />
                <h3>Ajuste de piezas</h3>
            </div>
            <p className="text-sm theme-text-muted m-0">Solicita un cambio a quien revisó el pedido.</p>
            <div className="tienda-ajustes-layout">
                <aside className="tienda-ajustes-opciones">
                    <fieldset><legend><Package size={16} aria-hidden />1. Tipo de ajuste</legend>
                        {[['anexo', 'Agregar piezas', PackagePlus], ['retiro', 'Retirar piezas', PackageMinus]].map(([valor, texto, Icono]) => <button key={valor} type="button" className="tienda-ajustes-opcion" aria-pressed={modo === valor} disabled={ocupado} onClick={() => setModo(valor)}><Icono size={20} aria-hidden /><span>{texto}</span>{modo === valor && <CheckCircle2 size={16} aria-hidden />}</button>)}
                    </fieldset>
                    <fieldset><legend><FileImage size={16} aria-hidden />2. Identificación de piezas</legend>
                        {[['documento', 'PDF / imágenes', FileImage], ['catalogo', 'SKU / nombre', Search]].map(([valor, texto, Icono]) => <button key={valor} type="button" className="tienda-ajustes-opcion" aria-pressed={(modo === 'anexo' ? entrada : entradaRetiro) === valor} disabled={ocupado} onClick={() => modo === 'anexo' ? setEntrada(valor) : setEntradaRetiro(valor)}><Icono size={20} aria-hidden /><span>{texto}</span>{(modo === 'anexo' ? entrada : entradaRetiro) === valor && <CheckCircle2 size={16} aria-hidden />}</button>)}
                    </fieldset>
                </aside>
                <div className="tienda-ajustes-captura">
                    <div className="tienda-ajustes-captura-titulo">{modo === 'anexo' ? <PackagePlus size={20} aria-hidden /> : <PackageMinus size={20} aria-hidden />}<h4>{modo === 'anexo' ? 'Agregar piezas' : 'Retirar piezas'} · {(modo === 'anexo' ? entrada : entradaRetiro) === 'documento' ? 'PDF / imágenes' : 'SKU / nombre'}</h4></div>
            {modo === 'anexo' ? (
                <div className="grid gap-3">
                    {entrada === 'documento' ? (
                        <p className="text-sm theme-text-muted m-0">
                            Adjunta las piezas a agregar.
                        </p>
                    ) : (
                        <div className="grid gap-3">
                            <p className="text-sm theme-text-muted m-0">Busca en el catálogo e indica la cantidad.</p>
                            <div className="tienda-ajustes-producto-campos">
                                <div className="min-w-0">
                                    <label className={THEME_LABEL} htmlFor="ajuste-buscar-producto">Producto</label>
                                    <SelectorProducto
                                        inputId="ajuste-buscar-producto"
                                        almacenId={pedido?.tarea_preparacion?.almacen?.id || pedido?.almacen?.id || null}
                                        incluirSinInventario
                                        value={producto?.id || ''}
                                        onChange={(_, elegido) => setProducto(elegido)}
                                        disabled={ocupado}
                                    />
                                </div>
                                <div>
                                    <label className={THEME_LABEL} htmlFor="ajuste-cantidad">Cantidad</label>
                                    <input id="ajuste-cantidad" className={campo} inputMode="numeric" min="1" value={cantidad} onChange={(evento) => setCantidad(evento.target.value)} />
                                </div>
                                <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px]`} disabled={ocupado || !producto} onClick={agregarProducto}>Agregar</button>
                            </div>
                            {seleccionadas.length > 0 && (
                                <ul className="m-0 pl-0 list-none grid gap-2">
                                    {seleccionadas.map((pieza) => (
                                        <li key={pieza.sku} className="flex items-center justify-between gap-3 rounded-lg border theme-border px-3 py-2 text-sm theme-text-main">
                                            <span className="min-w-0">{pieza.descripcion}<span className="block text-xs theme-text-muted">{pieza.sku}</span></span>
                                            <span className="flex items-center gap-3"><span className="font-semibold tabular-nums">{pieza.cantidad}</span>
                                                <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px]`} disabled={ocupado} onClick={() => setSeleccionadas(actual => actual.filter(fila => fila.sku !== pieza.sku))}>Quitar</button></span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}
                    <div>
                        <label className={THEME_LABEL} htmlFor="ajuste-nota">Nota (opcional)</label>
                        <input id="ajuste-nota" className={campo} value={nota} onChange={(evento) => setNota(evento.target.value)} />
                    </div>
                    <GaleriaEvidenciasPedido compacto etiquetaAdjuntar="Adjuntar archivos" id="ajuste-evidencia" label={entrada === 'documento' ? 'Piezas a agregar' : 'Soporte del agregado'}
                        obligatorio maxBytesTotal={CUPO_ADJUNTOS_CONSULTA} bytesUsados={bytesGuardados} archivos={evidenciasAnexo}
                        previews={previewAnexo} disabled={ocupado}
                        onChange={(archivos, vistas) => { setEvidenciasAnexo(archivos); setPreviewAnexo(vistas); }} onVer={onVer} />
                    <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px] justify-self-start`} disabled={ocupado} onClick={solicitarAnexo}>
                        <Send size={16} aria-hidden />{ocupado ? 'Enviando…' : 'Solicitar agregado'}
                    </button>
                </div>
            ) : (
                <div className="grid gap-3">
                    <p className="text-sm theme-text-muted m-0">
                        {entradaRetiro === 'documento'
                            ? 'Adjunta las piezas a retirar.'
                            : 'Selecciona las piezas a retirar o búscalas en el catálogo.'}
                    </p>
                    {entradaRetiro === 'documento' && (
                        <GaleriaEvidenciasPedido compacto etiquetaAdjuntar="Adjuntar archivos"
                            id="retiro-piezas-documento"
                            label="Piezas a retirar"
                            obligatorio
                            maxBytesTotal={CUPO_ADJUNTOS_CONSULTA}
                            bytesUsados={bytesGuardados + evidenciasRetiro.reduce((suma, archivo) => suma + Number(archivo.size || 0), 0)}
                            archivos={documentosRetiro}
                            previews={previewsDocumentos}
                            onChange={(archivos, previews) => {
                                setDocumentosRetiro(archivos);
                                setPreviewsDocumentos(previews);
                            }}
                            onVer={onVer}
                            disabled={ocupado}
                        />
                    )}
                    {entradaRetiro === 'catalogo' && productos.length > 0 && (
                        <ul className="m-0 pl-0 list-none grid gap-2">
                            {productos.map((pieza) => (
                                <li key={pieza.id} className="grid gap-2 rounded-lg border theme-border px-3 py-2 sm:grid-cols-[minmax(0,1fr)_6rem] sm:items-center">
                                    <label className="flex items-start gap-2 text-sm theme-text-main">
                                        <input
                                            type="checkbox"
                                            className="gelia-checkbox mt-1"
                                            checked={Boolean(marcadas[pieza.id])}
                                            onChange={() => alternar(pieza)}
                                        />
                                        <span>
                                            {pieza.descripcion}
                                            <span className="block text-xs theme-text-muted">{pieza.sku}</span>
                                        </span>
                                    </label>
                                    <input
                                        aria-label={`Cantidad a retirar de ${pieza.descripcion}`}
                                        className={campo}
                                        inputMode="numeric"
                                        min="1"
                                        disabled={!marcadas[pieza.id]}
                                        value={cantidades[pieza.id] || String(pieza.cantidad)}
                                        onChange={(evento) => setCantidades((actual) => ({ ...actual, [pieza.id]: evento.target.value }))}
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                    {entradaRetiro === 'catalogo' && mostrarBusqueda && (
                        <div className="grid gap-3">
                            <div className="tienda-ajustes-producto-campos">
                                <div className="min-w-0">
                                    <label className={THEME_LABEL} htmlFor="retiro-buscar-producto">Producto</label>
                                    <SelectorProducto
                                        inputId="retiro-buscar-producto"
                                        almacenId={pedido?.tarea_preparacion?.almacen?.id || pedido?.almacen?.id || null}
                                        incluirSinInventario
                                        value={piezaBuscada?.id || ''}
                                        onChange={(_, elegido) => setPiezaBuscada(elegido)}
                                        disabled={ocupado}
                                    />
                                </div>
                                <div>
                                    <label className={THEME_LABEL} htmlFor="retiro-cantidad">Cantidad</label>
                                    <input id="retiro-cantidad" className={campo} inputMode="numeric" min="1" value={cantidadBuscada} onChange={(evento) => setCantidadBuscada(evento.target.value)} />
                                </div>
                                <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px]`} disabled={ocupado || !piezaBuscada} onClick={agregarPiezaBuscada}>Agregar</button>
                            </div>
                            {agregadas.length > 0 && (
                                <ul className="m-0 pl-0 list-none grid gap-2">
                                    {agregadas.map((pieza) => (
                                        <li key={pieza.sku} className="flex items-center justify-between gap-3 rounded-lg border theme-border px-3 py-2 text-sm theme-text-main">
                                            <span className="min-w-0">{pieza.descripcion}<span className="block text-xs theme-text-muted">{pieza.sku}</span></span>
                                            <span className="flex items-center gap-3">
                                                <span className="font-semibold tabular-nums">{pieza.cantidad}</span>
                                                <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px]`} onClick={() => setAgregadas((actual) => actual.filter((fila) => fila.sku !== pieza.sku))}>Quitar</button>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}
                    {entradaRetiro === 'catalogo' && productos.length > 0 && (
                        <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px] justify-self-start`} onClick={() => setManual((actual) => !actual)}>
                            {manual ? 'Ocultar búsqueda' : 'La pieza no está en la respuesta'}
                        </button>
                    )}
                    <div>
                        <label className={THEME_LABEL} htmlFor="retiro-motivo">Motivo del retiro</label>
                        <select
                            id="retiro-motivo"
                            className={`${THEME_SELECT} w-full min-h-[44px] text-base`}
                            value={motivoOpcion}
                            onChange={(evento) => setMotivoOpcion(evento.target.value)}
                        >
                            <option value="">Elegir</option>
                            {MOTIVOS_RETIRO.map((opcion) => (
                                <option key={opcion} value={opcion}>{opcion}</option>
                            ))}
                        </select>
                    </div>
                    {motivoOpcion === 'Otro' && (
                        <div>
                            <label className={THEME_LABEL} htmlFor="retiro-motivo-libre">Motivo</label>
                            <textarea id="retiro-motivo-libre" className={`${THEME_TEXTAREA} w-full text-base`} rows={2} value={motivoLibre} onChange={(evento) => setMotivoLibre(evento.target.value)} />
                        </div>
                    )}
                    <GaleriaEvidenciasPedido compacto etiquetaAdjuntar="Adjuntar archivos"
                        id="retiro-evidencia"
                        label="Evidencia petición cliente"
                        maxBytesTotal={CUPO_ADJUNTOS_CONSULTA}
                        bytesUsados={bytesGuardados + documentosRetiro.reduce((suma, archivo) => suma + Number(archivo.size || 0), 0)}
                        archivos={evidenciasRetiro}
                        previews={previewsRetiro}
                        onChange={(archivos, previews) => {
                            setEvidenciasRetiro(archivos);
                            setPreviewsRetiro(previews);
                        }}
                        onVer={onVer}
                        disabled={ocupado}
                    />
                    <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px] justify-self-start`} disabled={ocupado} onClick={solicitarRetiro}>
                        <PackageMinus size={16} aria-hidden="true" />{ocupado ? 'Enviando…' : 'Solicitar retiro'}
                    </button>
                </div>
            )}
            {error && (
                <p className="text-sm theme-text-main m-0" role="alert">
                    {error}
                </p>
            )}
            </div>
            </div>
        </section>
    );
}

export default function SeguimientoSolicitudTienda({
    pedidoId,
    pedido,
    area,
    nombreAlmacen = '',
    revisiones = [],
    consultaCerrada = false,
    onConsultaCerrada,
    onSolicitud,
    onPedidoActualizado,
    onConsultaReabierta,
    soloLectura = false,
    destinoAccion,
}) {
    const [solicitud, setSolicitud] = useState(pedido?.solicitud_preparacion || {});
    const [lectura, setLectura] = useState(null);
    const [modificar, setModificar] = useState(false);
    const [reabriendo, setReabriendo] = useState(false);
    const [cerradaLocal, setCerradaLocal] = useState(null);
    const visto = lectura || pedido;
    const cerrada = cerradaLocal ?? consultaCerrada;
    const filas = lectura?.revisiones_producto || revisiones;
    const aplicar = datos => { setLectura(datos); setSolicitud(datos.solicitud_preparacion || {}); setCerradaLocal(Boolean(datos.consulta_cerrada || datos.consulta_cerrada_at)); onSolicitud?.(datos.solicitud_preparacion || {}); onPedidoActualizado?.(datos); };
    const reabrir = async () => {
        if (reabriendo) return; setReabriendo(true);
        try { await axios.post(`/control-pedidos/${pedidoId}/reabrir-consulta`, {}, { headers: cabeceras() }); const { data } = await axios.get(`/control-pedidos/${pedidoId}/disponibilidad-tienda`); aplicar(data); setCerradaLocal(false); onConsultaReabierta?.(); setAviso('Consulta reabierta. Se notificó a quien revisó el pedido.'); }
        catch (e) { setAviso(mensajeDe(e, 'No se pudo reabrir la consulta.')); } finally { setReabriendo(false); }
    };
    const accionesDePieza = pieza => {
        const idRevision = pieza.revision_id || (pieza.descripcion_producto ? pieza.id : null);
        const coincidencias = filas.filter(r => pieza.sku ? r.sku === pieza.sku : r.descripcion_producto === (pieza.descripcion || pieza.descripcion_producto));
        const revision = idRevision ? filas.find(r => Number(r.id) === Number(idRevision)) : coincidencias.length === 1 ? coincidencias[0] : null;
        return revision ? <AccionesRapidasDisponibilidad key={revision.id} compacta pedidoId={pedidoId} pieza={revision} almacenId={visto?.almacen_id || visto?.tarea_preparacion?.almacen?.id} soloLectura={soloLectura || cerrada} onActualizar={aplicar} onVer={(documentos, indice) => setVista({ documentos, indice })} /> : null;
    };
    const faltante = filas.some(p => p.estado_fisico === 'sin_existencia' && !p.resolucion);

    const [aviso, setAviso] = useState('');
    const [vista, setVista] = useState(null);
    const marcaRespuesta = pedido?.solicitud_preparacion?.revision_disponibilidad?.estado
        || pedido?.tarea_preparacion?.estado
        || pedido?.pesaje_respondido_at
        || '';
    useEffect(() => {
        if (!lectura || (pedido?.updated_at && lectura?.updated_at && new Date(pedido.updated_at) > new Date(lectura.updated_at))) {
            setSolicitud(pedido?.solicitud_preparacion || {}); setLectura(null); setCerradaLocal(null);
        }
    }, [pedido, marcaRespuesta]);
    const emitida = respuestaDeTiendaEmitida({ ...pedido, solicitud_preparacion: solicitud });
    const estado = solicitud?.revision_disponibilidad?.estado;
    const ajustePendiente = solicitud?.revision_disponibilidad?.aceptacion_vigente === false || (solicitud?.retiro_piezas || []).some(r => (r.estado || 'pendiente') === 'pendiente');
    const puedeConfirmar = !ajustePendiente && !soloLectura && emitida && !cerrada && Boolean(visto?.puede_cerrar_consulta) && !faltante;
    const causaCierre = visto?.motivo_bloqueo_cierre
        || (solicitud?.revision_disponibilidad?.aceptacion_vigente === false ? 'Hay un ajuste pendiente. La aceptación de esta versión no sigue vigente.' : '')
        || ((solicitud?.retiro_piezas || []).some((retiro) => (retiro?.estado || 'pendiente') === 'pendiente') ? 'Hay un retiro pendiente de atención por el auxiliar.' : '');

    if (!pedidoId) return null;
    if (!emitida) {
        return <EsperaRevision area={area} nombreAlmacen={nombreAlmacen} />;
    }

    return (
        <div className="tienda-seguimiento mt-4 grid gap-4 theme-text-main">
            {aviso && (
                <p className="text-sm theme-text-main m-0 break-words" role="status">
                    {aviso}
                </p>
            )}
            {cerrada && <ConfirmacionSolicitud pedidoId={pedidoId} cerrada faltante={faltante} soloLectura={soloLectura} onReabrir={reabrir} />}
            <RespuestaPreparacionTienda
                tarea={visto?.tarea_preparacion}
                accionesDePieza={accionesDePieza}
                onVerDocumentos={(documentos, indice) => setVista({ documentos, indice })}
            />
            <ResultadoDisponibilidad
                area={area}
                pedido={{ ...visto, solicitud_preparacion: solicitud }}
                revisiones={filas}
                accionesDePieza={accionesDePieza}
                onVer={(documentos, indice) => setVista({ documentos, indice })}
            />
            <ResumenCambiosDisponibilidad solicitud={solicitud} documentos={visto?.documentos || []} onVer={(documentos, indice) => setVista({ documentos, indice })} />
            {!soloLectura && !cerrada && <button type="button" className="tienda-ajustes-desplegar" aria-expanded={modificar} onClick={() => setModificar(!modificar)}><span className={GELIA_ICON_BOX}><PackagePlus size={20} aria-hidden /></span><span>Modificar pedido</span><ChevronDown size={18} aria-hidden /></button>}
            {!soloLectura && !cerrada && modificar && (
                <AjustePiezas
                    pedidoId={pedidoId}
                    pedido={visto}
                    solicitud={solicitud}
                    onSolicitud={(siguiente) => { setSolicitud(siguiente); onSolicitud?.(siguiente); }}
                    onAviso={setAviso}
                    onVer={(documentos, indice) => setVista({ documentos, indice })}
                />
            )}
            {!cerrada && !puedeConfirmar && causaCierre && (
                <PanelEspera label="La consulta no puede cerrarse" icon={Clock}>
                    {causaCierre}
                </PanelEspera>
            )}
            {!cerrada && <ConfirmacionSolicitud
                pedidoId={pedidoId}
                destinoAccion={destinoAccion}
                area={area}
                visible={puedeConfirmar}
                cerrada={cerrada}
                faltante={faltante}
                soloLectura={soloLectura}
                onReabrir={reabrir}
                onCerrada={(lecturaCierre) => {
                    setCerradaLocal(true); onConsultaCerrada?.(lecturaCierre);
                    setAviso('Consulta cerrada. La mercancía quedó confirmada con el cliente.');
                }}
            />}
            <ModalVistaPreviaDocumento
                abierto={Boolean(vista)}
                documentos={vista?.documentos || []}
                indice={vista?.indice || 0}
                onClose={() => setVista(null)}
            />
        </div>
    );
}
