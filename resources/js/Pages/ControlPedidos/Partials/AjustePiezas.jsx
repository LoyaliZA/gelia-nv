import { adaptadorConsultaTienda } from './adaptadoresConsulta';
import React, { useEffect, useMemo, useRef, useState } from 'react';
import { CheckCircle2, FileImage, Package, PackageMinus, PackagePlus, Search, Send } from 'lucide-react';
import {
    GELIA_BTN_OUTLINE,
    THEME_BTN_PRIMARY,
    THEME_INPUT,
    THEME_LABEL,
    THEME_SELECT,
    THEME_TEXTAREA,
} from '../../../utils/geliaTheme';
import { uuidSolicitud } from '../../../utils/uuidSolicitud';
import SelectorProducto from '../../../Components/Almacenes/SelectorProducto';
import GaleriaEvidenciasPedido from './GaleriaEvidenciasPedido';
import { CUPO_ADJUNTOS_CONSULTA, bytesConsulta } from './lotesAdjuntosConsulta';

const MOTIVOS_RETIRO = [
    'El cliente ya no lo quiere',
    'Cambió de aroma',
    'El precio no convence',
    'Pieza duplicada',
    'El regalo ya no aplica',
    'Otro',
];

function mensajeDe(error, reserva) {
    if (error?.response?.status === 419) return 'La sesión cambió o venció. Recargue GELIA antes de volver a guardar; no se confirmó la operación.';
    const datos = error?.response?.data;
    const lista = datos?.errors ? Object.values(datos.errors).flat() : [];
    return lista[0] || datos?.message || reserva;
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

export function AjustePiezas({ pedidoId, pedido, solicitud, onSolicitud, onAviso, onVer, adaptador, onDirtyChange }) {
    const operaciones = adaptador || adaptadorConsultaTienda({ pedidoId, pedido });
    const puedeAnexar = operaciones.capacidades?.anexar !== false;
    const puedeRetirar = operaciones.capacidades?.retirar !== false;
    const productos = useMemo(() => {
        const retiradas = new Set((solicitud?.retiro_piezas || []).flatMap(retiro => retiro.faltantes_retirados || []).map(Number));
        // Lo ya solicitado (pendiente o aplicado) deja de ofrecerse; una cantidad parcial solo descuenta lo pedido.
        const clave = (x) => String(x?.sku || x?.descripcion || '').trim().toLowerCase();
        const pedidas = new Map();
        for (const retiro of solicitud?.retiro_piezas || []) {
            if (retiro?.estado === 'rechazada') continue;
            for (const linea of retiro?.piezas_solicitadas || retiro?.piezas || []) {
                if (linea?.revision_id) continue;
                pedidas.set(clave(linea), (pedidas.get(clave(linea)) || 0) + (Number(linea?.cantidad) || 0));
            }
        }
        return piezasRespuesta(pedido?.tarea_preparacion, pedido?.revisiones_producto || pedido?.revisionesProducto || [])
            .filter(pieza => !retiradas.has(Number(pieza.revision_id)))
            .map(pieza => ({ ...pieza, cantidad: pieza.revision_id ? pieza.cantidad : pieza.cantidad - (pedidas.get(clave(pieza)) || 0) }))
            .filter(pieza => pieza.cantidad > 0);
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
    const capturaPendiente = Boolean(
        nota.trim() || seleccionadas.length || evidenciasAnexo.length || motivoOpcion || motivoLibre.trim()
        || documentosRetiro.length || evidenciasRetiro.length || agregadas.length || Object.values(marcadas).some(Boolean),
    );
    useEffect(() => { onDirtyChange?.(capturaPendiente); }, [capturaPendiente, onDirtyChange]);
    useEffect(() => () => onDirtyChange?.(false), [onDirtyChange]);
    useEffect(() => {
        if (modo === 'anexo' && !puedeAnexar && puedeRetirar) setModo('retiro');
        if (modo === 'retiro' && !puedeRetirar && puedeAnexar) setModo('anexo');
    }, [modo, puedeAnexar, puedeRetirar]);


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
            const respuesta = await operaciones.comandos.anexar({
                archivos: evidenciasAnexo,
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
            if (respuesta.data?.solicitud_preparacion) onSolicitud(respuesta.data.solicitud_preparacion, respuesta.data.version_consulta);
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
                    cantidad: Math.max(1, Number(cantidades[pieza.id] ?? pieza.cantidad) || 1),
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
            const respuesta = await operaciones.comandos.retirar({
                archivos: cola.length ? cola : [{ size: 0, archivo: null, campo: '' }],
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
            if (respuesta.data?.solicitud_preparacion) onSolicitud(respuesta.data.solicitud_preparacion, respuesta.data.version_consulta);
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
                        {[['anexo', 'Agregar piezas', PackagePlus, puedeAnexar], ['retiro', 'Retirar piezas', PackageMinus, puedeRetirar]].filter(([, , , visible]) => visible).map(([valor, texto, Icono]) => <button key={valor} type="button" className="tienda-ajustes-opcion" aria-pressed={modo === valor} disabled={ocupado} onClick={() => setModo(valor)}><Icono size={20} aria-hidden /><span>{texto}</span>{modo === valor && <CheckCircle2 size={16} aria-hidden />}</button>)}
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
                                        almacenId={operaciones.almacenId || pedido?.tarea_preparacion?.almacen?.id || pedido?.almacen?.id || null}
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
                                        value={cantidades[pieza.id] ?? String(pieza.cantidad)}
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
                                        almacenId={operaciones.almacenId || pedido?.tarea_preparacion?.almacen?.id || pedido?.almacen?.id || null}
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
