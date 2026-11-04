import React, { useEffect, useMemo, useRef, useState } from 'react';
import axios from 'axios';
import { CheckCircle2, ClipboardCheck, Clock, PackageMinus } from 'lucide-react';
import {
    GELIA_BTN_OUTLINE,
    GELIA_SEGMENT_TABS_TRACK_COMPACT,
    THEME_BTN_PRIMARY,
    THEME_BTN_SECONDARY,
    THEME_INPUT,
    THEME_LABEL,
    THEME_SELECT,
    THEME_TEXTAREA,
} from '../../../utils/geliaTheme';
import SelectorProducto from '../../../Components/Almacenes/SelectorProducto';
import GaleriaEvidenciasPedido from './GaleriaEvidenciasPedido';
import AvisoOperativoPedido from './AvisoOperativoPedido';
import { archivosImagenDesdeClipboard } from './archivosDesdeClipboard';
import ModalVistaPreviaDocumento, { MiniaturaDocumento } from './ModalVistaPreviaDocumento';
import { formatearFechaNegocio } from './pedidosBmaStyles';
import RespuestaPreparacionTienda from './RespuestaPreparacionTienda';

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
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
        'X-Requested-With': 'XMLHttpRequest',
    };
}

function mensajeDe(error, reserva) {
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

function piezasRespuesta(tarea) {
    const desdeRespuesta = tarea?.respuesta?.productos || [];
    if (desdeRespuesta.length > 0) {
        return desdeRespuesta
            .filter((pieza) => pieza?.descripcion || pieza?.sku)
            .map((pieza) => ({
                id: pieza.id,
                sku: pieza.sku || '',
                descripcion: pieza.descripcion || '',
                cantidad: Math.max(1, Number(pieza.cantidad) || 1),
                estado: pieza.estado_fisico || '',
            }));
    }
    return (tarea?.productos || [])
        .filter((pieza) => pieza?.descripcion_snapshot || pieza?.sku)
        .map((pieza) => ({
            id: pieza.id,
            sku: pieza.sku || '',
            descripcion: pieza.descripcion_snapshot || '',
            cantidad: Math.max(1, Number(pieza.cantidad_encontrada || pieza.cantidad_solicitada) || 1),
            estado: pieza.estado_fisico || '',
        }));
}

function respuestaSoloConDetalle(productos) {
    return productos.length > 0 && productos.every((pieza) => pieza.estado && pieza.estado !== 'bueno');
}

function detalleRevision(area, estado) {
    if (estado === 'excepciones_pendientes') {
        return 'La respuesta incluye piezas con excepción. El pedido se cierra con Cerrar consulta. Para cambiar piezas, registre un anexo o un retiro.';
    }
    if (estado === 'sin_excepciones' && area === 'preparacion_tienda') {
        return 'Revisión sin excepciones. Falta el apartado físico de lo aceptado. No hay pago ni entrega.';
    }
    if (estado === 'sin_excepciones') {
        return 'Consulta de disponibilidad sin excepciones. No hay traslado, pago ni entrega.';
    }
    if (estado === 'decision_registrada') {
        return 'La revisión quedó registrada. No hay pago ni entrega.';
    }
    return '';
}

function pegarArchivo(evento, asignar) {
    const archivos = archivosImagenDesdeClipboard(evento.clipboardData);
    if (!archivos.length) return;
    evento.preventDefault();
    asignar(archivos[0]);
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
            <p className="text-xs font-bold m-0" style={{ color: 'var(--color-aviso)' }}>{label}</p>
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

function ResultadoDisponibilidad({ area, pedido, revisiones, onVer }) {
    const revision = pedido?.solicitud_preparacion?.revision_disponibilidad;
    if (!revision?.estado || preparacionVisible(pedido?.tarea_preparacion)) return null;
    const preparacion = preparacionVisible(pedido?.tarea_preparacion);
    const evidencias = (pedido?.documentos || []).filter((documento) => {
        if (documento?.tipo !== 'evidencia_condicion' || !documento?.url || documento?.activo === false) return false;
        if (documento.relacion_tipo === 'retiro_piezas' || documento.relacion_tipo === 'retiro_peticion_cliente') return false;
        if (String(documento.comentario || '').startsWith('Evidencia petición')) return false;
        return !documento.relacion_tipo || documento.relacion_tipo === 'revision_general';
    });
    const responsable = pedido?.pesaje_respondido_por?.name || pedido?.pesajeRespondidoPor?.name || '';
    const titulo = area === 'preparacion_tienda' ? 'Resultado de la revisión en tienda' : 'Resultado de la consulta en CEDIS';
    const piezas = (revisiones || []).filter((pieza) =>
        ['regular', 'malo', 'danado', 'sin_existencia'].includes(pieza?.estado_fisico),
    );

    return (
        <section className="registrar-tienda-panel space-y-3" aria-label={titulo}>
            <div className="registrar-tienda-seccion-titulo">
                <ClipboardCheck size={18} aria-hidden="true" />
                <h3>{titulo}</h3>
            </div>
            <p className="text-sm theme-text-muted m-0">
                {responsable ? `Respondió ${responsable}` : 'Respuesta registrada'}
                {revision.respondida_at ? ` · ${formatearFechaNegocio(revision.respondida_at)}` : ''}
            </p>
            <p className="text-sm theme-text-main m-0">{detalleRevision(area, revision.estado)}</p>
            {!preparacion && piezas.length > 0 && (
                <ul className="m-0 pl-4 grid gap-1">
                    {piezas.map((pieza) => (
                        <li key={pieza.id || `${pieza.descripcion_producto}-${pieza.sku}`} className="text-sm theme-text-main">
                            {pieza.descripcion_producto || pieza.descripcion}
                            {pieza.cantidad_solicitada ? ` · ${pieza.cantidad_solicitada}` : ''}
                            {pieza.comentario ? ` · ${pieza.comentario}` : ''}
                        </li>
                    ))}
                </ul>
            )}
            {!preparacion && evidencias.length > 0 && (
                <div className="grid gap-2">
                    <p className="text-sm font-semibold theme-text-main m-0">Evidencia de la revisión</p>
                    <div className="flex flex-wrap gap-2">
                        {evidencias.map((documento, indice) => (
                            <MiniaturaDocumento
                                key={documento.id || indice}
                                documento={documento}
                                onVer={() => onVer(evidencias, indice)}
                            />
                        ))}
                    </div>
                </div>
            )}
        </section>
    );
}

function ConfirmacionSolicitud({ pedidoId, visible, cerrada, onCerrada }) {
    const [ocupado, setOcupado] = useState(false);
    const [error, setError] = useState('');
    if (!visible && !cerrada) return null;

    const cerrar = async () => {
        if (ocupado || !pedidoId) return;
        setOcupado(true);
        setError('');
        try {
            await axios.post(`/control-pedidos/${pedidoId}/cerrar-consulta`, {}, { headers: cabeceras() });
            onCerrada();
        } catch (errorCierre) {
            setError(mensajeDe(errorCierre, 'No se pudo confirmar la mercancía.'));
        } finally {
            setOcupado(false);
        }
    };

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
            <button
                type="button"
                className={`${THEME_BTN_PRIMARY} mt-3 min-h-[44px] w-full sm:w-auto`}
                disabled={ocupado}
                onClick={cerrar}
            >
                <CheckCircle2 className="w-4 h-4" aria-hidden="true" />
                {ocupado ? 'Confirmando…' : 'Cerrar consulta / Confirmar mercancía con el cliente'}
            </button>
        </PanelEspera>
    );
}

function AjustePiezas({ pedidoId, pedido, solicitud, onSolicitud, onAviso, onVer }) {
    const productos = useMemo(() => piezasRespuesta(pedido?.tarea_preparacion), [pedido]);
    const soloDetalle = respuestaSoloConDetalle(productos);
    const [modo, setModo] = useState('anexo');
    const [entrada, setEntrada] = useState('documento');
    const [producto, setProducto] = useState(null);
    const [seleccionadas, setSeleccionadas] = useState([]);
    const [cantidad, setCantidad] = useState('1');
    const [nota, setNota] = useState('');
    const [evidenciaAnexo, setEvidenciaAnexo] = useState(null);
    const [marcadas, setMarcadas] = useState({});
    const [cantidades, setCantidades] = useState({});
    const [motivoOpcion, setMotivoOpcion] = useState('');
    const [motivoLibre, setMotivoLibre] = useState('');
    const [entradaRetiro, setEntradaRetiro] = useState('documento');
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
    const evidenciaAnexoRef = useRef(null);
    const mostrarBusqueda = soloDetalle || productos.length === 0 || manual;
    const anexos = Array.isArray(solicitud?.anexo_sin_remision) ? solicitud.anexo_sin_remision : [];
    const retiros = Array.isArray(solicitud?.retiro_piezas) ? solicitud.retiro_piezas : [];
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
        if (!evidenciaAnexo) {
            setError(entrada === 'documento' ? 'Adjunte el PDF o la captura del anexo.' : 'Adjunte el soporte del anexo.');
            return;
        }
        if (entrada === 'catalogo' && seleccionadas.length === 0) {
            setError('Seleccione al menos un producto y su cantidad.');
            return;
        }
        setOcupado(true);
        setError('');
        const form = new FormData();
        form.append('entrada', entrada);
        form.append('evidencia', evidenciaAnexo);
        if (nota.trim()) form.append('condicion', nota.trim());
        if (entrada === 'catalogo') {
            seleccionadas.forEach((pieza, indice) => {
                form.append(`piezas[${indice}][sku]`, pieza.sku);
                form.append(`piezas[${indice}][descripcion]`, pieza.descripcion);
                form.append(`piezas[${indice}][cantidad]`, String(pieza.cantidad));
                if (pieza.producto_id) form.append(`piezas[${indice}][producto_id]`, String(pieza.producto_id));
                form.append(`piezas[${indice}][condicion]`, nota.trim() || 'Pendiente de revisión');
            });
        }
        try {
            const respuesta = await axios.post(`/control-pedidos/${pedidoId}/anexo-tienda`, form, { headers: cabeceras() });
            const lineas = respuesta.data?.expediente?.anexo?.sin_remision;
            if (Array.isArray(lineas)) {
                onSolicitud((actual) => ({ ...actual, anexo_sin_remision: lineas }));
            }
            setSeleccionadas([]);
            setProducto(null);
            setCantidad('1');
            setNota('');
            setEvidenciaAnexo(null);
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
        const desdeLista = soloDetalle
            ? []
            : productos
                .filter((pieza) => marcadas[pieza.id])
                .map((pieza) => ({
                    sku: pieza.sku,
                    descripcion: pieza.descripcion,
                    cantidad: Math.max(1, Number(cantidades[pieza.id] || pieza.cantidad) || 1),
                }))
                .filter((pieza) => pieza.sku && pieza.descripcion);
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
        setOcupado(true);
        setError('');
        const form = new FormData();
        form.append('entrada', entradaRetiro);
        form.append('motivo', motivo);
        form.append('motivo_opcion', motivoOpcion);
        if (entradaRetiro === 'catalogo') {
            elegidas.forEach((pieza, indice) => {
                form.append(`piezas[${indice}][sku]`, pieza.sku);
                form.append(`piezas[${indice}][descripcion]`, pieza.descripcion);
                form.append(`piezas[${indice}][cantidad]`, String(pieza.cantidad));
            });
        }
        documentosRetiro.forEach((archivo) => form.append('documentos[]', archivo));
        evidenciasRetiro.forEach((archivo) => form.append('evidencias[]', archivo));
        try {
            const respuesta = await axios.post(`/control-pedidos/${pedidoId}/retiro-piezas-tienda`, form, {
                headers: cabeceras(),
            });
            if (respuesta.data?.solicitud_preparacion) onSolicitud(respuesta.data.solicitud_preparacion);
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
        <section className="registrar-tienda-panel" aria-label="Ajuste de piezas">
            <div className="registrar-tienda-seccion-titulo">
                <PackageMinus size={18} aria-hidden="true" />
                <h3>Ajuste de piezas</h3>
            </div>
            <div className="grid gap-3">
            <p className="text-sm theme-text-muted m-0">
                Después de la respuesta se puede anexar una pieza o pedir el retiro. Quien revisó incorpora el cambio. No confirma pago ni entrega.
            </p>
            <div className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK_COMPACT}`} role="tablist" aria-label="Tipo de ajuste">
                <button type="button" role="tab" className="gelia-segment-btn min-h-[44px]" aria-selected={modo === 'anexo'} data-active={modo === 'anexo'} onClick={() => setModo('anexo')}>
                    Anexar pieza
                </button>
                <button type="button" role="tab" className="gelia-segment-btn min-h-[44px]" aria-selected={modo === 'retiro'} data-active={modo === 'retiro'} onClick={() => setModo('retiro')}>
                    Retirar piezas
                </button>
            </div>
            {modo === 'anexo' ? (
                <div className="grid gap-3" onPaste={(evento) => pegarArchivo(evento, setEvidenciaAnexo)}>
                    <div className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK_COMPACT}`} role="tablist" aria-label="Forma del anexo">
                        <button type="button" role="tab" className="gelia-segment-btn min-h-[44px]" aria-selected={entrada === 'documento'} data-active={entrada === 'documento'} onClick={() => setEntrada('documento')}>
                            PDF o captura del anexo
                        </button>
                        <button type="button" role="tab" className="gelia-segment-btn min-h-[44px]" aria-selected={entrada === 'catalogo'} data-active={entrada === 'catalogo'} onClick={() => setEntrada('catalogo')}>
                            Seleccionar productos
                        </button>
                    </div>
                    {entrada === 'documento' ? (
                        <p className="text-sm theme-text-muted m-0">
                            Adjunte el documento del anexo. No hace falta transcribir SKU ni cantidades. Quien prepara identifica las piezas. Adjuntar el archivo no confirma disponibilidad.
                        </p>
                    ) : (
                        <div className="grid gap-3">
                            <p className="text-sm theme-text-muted m-0">Busque por nombre o SKU y indique la cantidad. La selección no declara que la pieza ya esté apartada.</p>
                            <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_6rem_auto] sm:items-end">
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
                                <button type="button" className={`${THEME_BTN_SECONDARY} min-h-[44px]`} disabled={ocupado || !producto} onClick={agregarProducto}>Agregar</button>
                            </div>
                            {seleccionadas.length > 0 && (
                                <ul className="m-0 pl-0 list-none grid gap-2">
                                    {seleccionadas.map((pieza) => (
                                        <li key={pieza.sku} className="flex items-center justify-between gap-3 rounded-lg border theme-border px-3 py-2 text-sm theme-text-main">
                                            <span className="min-w-0">{pieza.descripcion}<span className="block text-xs theme-text-muted">{pieza.sku}</span></span>
                                            <span className="font-semibold tabular-nums">{pieza.cantidad}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}
                    <div>
                        <label className={THEME_LABEL} htmlFor="ajuste-nota">{entrada === 'documento' ? 'Nota, si hace falta' : 'Nota para quien prepara'}</label>
                        <input id="ajuste-nota" className={campo} value={nota} onChange={(evento) => setNota(evento.target.value)} />
                    </div>
                    <div>
                        <p className={THEME_LABEL} id="ajuste-evidencia-titulo">{entrada === 'documento' ? 'PDF o captura del anexo' : 'Soporte del anexo'}</p>
                        <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px]`} onClick={() => evidenciaAnexoRef.current?.click()}>
                            {evidenciaAnexo ? 'Reemplazar archivo' : 'Adjuntar archivo'}
                        </button>
                        <input
                            id="ajuste-evidencia"
                            ref={evidenciaAnexoRef}
                            className="hidden"
                            type="file"
                            accept="application/pdf,image/jpeg,image/png,image/webp"
                            aria-labelledby="ajuste-evidencia-titulo"
                            onChange={(evento) => setEvidenciaAnexo(evento.target.files?.[0] || null)}
                        />
                        <p className="text-xs theme-text-muted m-0 mt-1">
                            {evidenciaAnexo ? evidenciaAnexo.name : 'Puede pegar una captura en este apartado.'}
                        </p>
                    </div>
                    <button type="button" className={`${THEME_BTN_PRIMARY} min-h-[44px] justify-self-start`} disabled={ocupado} onClick={solicitarAnexo}>
                        {ocupado ? 'Enviando…' : 'Solicitar anexo'}
                    </button>
                </div>
            ) : (
                <div className="grid gap-3">
                    <div className={`gelia-segment ${GELIA_SEGMENT_TABS_TRACK_COMPACT}`} role="tablist" aria-label="Forma del retiro">
                        <button type="button" role="tab" className="gelia-segment-btn min-h-[44px]" aria-selected={entradaRetiro === 'documento'} data-active={entradaRetiro === 'documento'} onClick={() => setEntradaRetiro('documento')}>
                            PDF o capturas
                        </button>
                        <button type="button" role="tab" className="gelia-segment-btn min-h-[44px]" aria-selected={entradaRetiro === 'catalogo'} data-active={entradaRetiro === 'catalogo'} onClick={() => setEntradaRetiro('catalogo')}>
                            Seleccionar productos
                        </button>
                    </div>
                    <p className="text-sm theme-text-muted m-0">
                        {entradaRetiro === 'documento'
                            ? 'Adjunte el PDF o las capturas de las piezas a retirar. No hace falta transcribir SKU. El auxiliar identifica productos y cantidades. Solicitar no retira ni libera stock.'
                            : 'Elija piezas conocidas o búsquelas por nombre o SKU, aunque no tengan existencia libre. Un producto del catálogo no prueba que esté en el pedido.'}
                    </p>
                    {entradaRetiro === 'documento' && (
                        <GaleriaEvidenciasPedido
                            id="retiro-piezas-documento"
                            label="Piezas a retirar"
                            obligatorio
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
                    {entradaRetiro === 'catalogo' && !soloDetalle && productos.length > 0 && (
                        <ul className="m-0 pl-0 list-none grid gap-2">
                            {productos.map((pieza) => (
                                <li key={pieza.id} className="grid gap-2 rounded-lg border theme-border px-3 py-2 sm:grid-cols-[minmax(0,1fr)_6rem] sm:items-center">
                                    <label className="flex items-start gap-2 text-sm theme-text-main">
                                        <input
                                            type="checkbox"
                                            className="mt-1"
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
                            <div className="grid gap-3 sm:grid-cols-[minmax(0,1fr)_6rem_auto] sm:items-end">
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
                                <button type="button" className={`${THEME_BTN_SECONDARY} min-h-[44px]`} disabled={ocupado || !piezaBuscada} onClick={agregarPiezaBuscada}>Agregar</button>
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
                    {entradaRetiro === 'catalogo' && !soloDetalle && productos.length > 0 && (
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
                    <GaleriaEvidenciasPedido
                        id="retiro-evidencia"
                        label="Evidencia petición cliente"
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
                        {ocupado ? 'Enviando…' : 'Solicitar retiro'}
                    </button>
                </div>
            )}
            {error && (
                <p className="text-sm theme-text-main m-0" role="alert">
                    {error}
                </p>
            )}
            {anexos.length > 0 && (
                <div>
                    <p className="text-sm font-semibold theme-text-main m-0">Anexos documentados</p>
                    <ul className="m-0 mt-1 pl-4">
                        {anexos.map((linea, indice) => (
                            <li key={`${linea.sku || 'documento'}-${indice}`} className="text-sm theme-text-main">
                                {linea.respondida_at
                                    ? `${linea.separada_at ? 'Separación confirmada' : linea.estado === 'disponible' ? 'Disponible · separación pendiente' : 'Con detalle'} · ${linea.resultado || ''}${linea.respondida_por ? ` · Respondió ${linea.respondida_por}` : ''}${linea.piezas_respuesta?.length ? ` · ${linea.piezas_respuesta.map((pieza) => `${pieza.descripcion} (${pieza.cantidad})`).join(', ')}` : ''}`
                                    : linea.entrada === 'documento' || linea.pendiente_identificacion
                                    ? 'Documento del anexo · pendiente de identificación'
                                    : `${linea.descripcion} · ${linea.cantidad}${linea.condicion ? ` · ${linea.condicion}` : ''}`}
                            </li>
                        ))}
                    </ul>
                </div>
            )}
            {retiros.length > 0 && (
                <div>
                    <p className="text-sm font-semibold theme-text-main m-0">Retiros solicitados</p>
                    <ul className="m-0 mt-1 pl-4">
                        {retiros.map((lote, indice) => (
                            <li key={`${lote.version || lote.solicitado_at || indice}`} className="text-sm theme-text-main">
                                {lote.motivo}
                                {` · ${lote.estado || 'pendiente'}`}
                                {lote.solicitado_por ? ` · ${lote.solicitado_por}` : ''}
                                {Array.isArray(lote.piezas) && lote.piezas.length > 0
                                    ? ` · ${lote.piezas.map((pieza) => `${pieza.descripcion} (${pieza.cantidad})`).join(', ')}`
                                    : ' · Pendiente de identificación por el auxiliar'}
                                {lote.resultado ? ` · ${lote.resultado}` : ''}
                                {Array.isArray(lote.documentos_piezas) && lote.documentos_piezas.length > 0
                                    ? ` · ${lote.documentos_piezas.length} captura(s) de piezas`
                                    : ''}
                            </li>
                        ))}
                    </ul>
                </div>
            )}
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
}) {
    const [solicitud, setSolicitud] = useState(pedido?.solicitud_preparacion || {});
    const [aviso, setAviso] = useState('');
    const [vista, setVista] = useState(null);
    const marcaRespuesta = pedido?.solicitud_preparacion?.revision_disponibilidad?.estado
        || pedido?.tarea_preparacion?.estado
        || pedido?.pesaje_respondido_at
        || '';
    useEffect(() => {
        setSolicitud(pedido?.solicitud_preparacion || {});
    }, [pedido, marcaRespuesta]);
    const emitida = respuestaDeTiendaEmitida({ ...pedido, solicitud_preparacion: solicitud });
    const estado = solicitud?.revision_disponibilidad?.estado;
    const puedeConfirmar = emitida && !consultaCerrada && Boolean(pedido?.puede_cerrar_consulta);
    const causaCierre = pedido?.motivo_bloqueo_cierre
        || (solicitud?.revision_disponibilidad?.aceptacion_vigente === false ? 'Hay un ajuste pendiente. La aceptación de esta versión no sigue vigente.' : '')
        || ((solicitud?.retiro_piezas || []).some((retiro) => (retiro?.estado || 'pendiente') === 'pendiente') ? 'Hay un retiro pendiente de atención por el auxiliar.' : '');

    if (!pedidoId) return null;
    if (!emitida) {
        return <EsperaRevision area={area} nombreAlmacen={nombreAlmacen} />;
    }

    return (
        <div className="mt-4 grid gap-4">
            {aviso && (
                <p className="text-sm theme-text-main m-0 break-words" role="status">
                    {aviso}
                </p>
            )}
            <RespuestaPreparacionTienda
                tarea={pedido?.tarea_preparacion}
                onVerDocumentos={(documentos, indice) => setVista({ documentos, indice })}
            />
            <ResultadoDisponibilidad
                area={area}
                pedido={{ ...pedido, solicitud_preparacion: solicitud }}
                revisiones={revisiones}
                onVer={(documentos, indice) => setVista({ documentos, indice })}
            />
            {!consultaCerrada && (
                <AjustePiezas
                    pedidoId={pedidoId}
                    pedido={pedido}
                    solicitud={solicitud}
                    onSolicitud={(siguiente) => { setSolicitud(siguiente); onSolicitud?.(siguiente); }}
                    onAviso={setAviso}
                    onVer={(documentos, indice) => setVista({ documentos, indice })}
                />
            )}
            {!consultaCerrada && !puedeConfirmar && causaCierre && (
                <PanelEspera label="La consulta no puede cerrarse" icon={Clock}>
                    {causaCierre}
                </PanelEspera>
            )}
            <ConfirmacionSolicitud
                pedidoId={pedidoId}
                visible={puedeConfirmar}
                cerrada={consultaCerrada}
                onCerrada={() => {
                    onConsultaCerrada?.();
                    setAviso('Consulta cerrada. La mercancía quedó confirmada con el cliente.');
                }}
            />
            <ModalVistaPreviaDocumento
                abierto={Boolean(vista)}
                documentos={vista?.documentos || []}
                indice={vista?.indice || 0}
                onClose={() => setVista(null)}
            />
        </div>
    );
}
