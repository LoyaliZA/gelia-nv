import AccionPrincipalTienda from './AccionPrincipalTienda';
import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { AlertTriangle, RotateCcw, ChevronDown, CheckCircle2, Clock, PackagePlus } from 'lucide-react';
import {
    GELIA_BTN_OUTLINE,
    GELIA_ICON_BOX,
    THEME_BTN_PRIMARY,
} from '../../../utils/geliaTheme';
import { AjustePiezas } from './AjustePiezas';
import { adaptadorConsultaTienda } from './adaptadoresConsulta';

export { AjustePiezas };
import { lecturaConsultaVigente } from './vigenciaConsultaPedido';
import AvisoOperativoPedido from './AvisoOperativoPedido';
import ModalVistaPreviaDocumento from './ModalVistaPreviaDocumento';
import { formatearFechaNegocio } from './pedidosBmaStyles';
import AccionesRapidasDisponibilidad from './AccionesRapidasDisponibilidad';
import ResumenCambiosDisponibilidad from './ResumenCambiosDisponibilidad';
import RespuestaPreparacionTienda from './RespuestaPreparacionTienda';
import RespuestaDisponibilidadPedido from './RespuestaDisponibilidadPedido';
import CapturaConjuntoPedido from './CapturaConjuntoPedido';

const ESTADOS_REVISION = ['sin_excepciones', 'excepciones_pendientes', 'decision_registrada'];

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
        titulo="Respuesta de disponibilidad" origen={area === 'preparacion_tienda' ? 'Tienda' : 'CEDIS'} ariaLabel={area === 'preparacion_tienda' ? 'Resultado de la revisión en tienda' : 'Resultado de la consulta en CEDIS'} mostrarCaptura={false}
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
    const adaptador = adaptadorConsultaTienda({ pedidoId, pedido: visto });
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
        if (!adaptador.capacidades.sustituir) return null;
        const revision = idRevision ? filas.find(r => Number(r.id) === Number(idRevision)) : coincidencias.length === 1 ? coincidencias[0] : null;
        return revision ? <AccionesRapidasDisponibilidad key={revision.id} compacta pedidoId={pedidoId} pieza={revision} almacenId={adaptador.almacenId || visto?.almacen_id || visto?.tarea_preparacion?.almacen?.id} soloLectura={soloLectura || cerrada} onActualizar={aplicar} onVer={(documentos, indice) => setVista({ documentos, indice })} /> : null;
    };
    const faltante = filas.some(p => p.estado_fisico === 'sin_existencia' && !p.resolucion);

    const [aviso, setAviso] = useState('');
    const [vista, setVista] = useState(null);
    const marcaRespuesta = pedido?.solicitud_preparacion?.revision_disponibilidad?.estado
        || pedido?.tarea_preparacion?.estado
        || pedido?.pesaje_respondido_at
        || '';
    useEffect(() => {
        if (!lectura || lecturaConsultaVigente(lectura, pedido)) {
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
                mostrarCaptura={false}
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
            <CapturaConjuntoPedido pedido={{ ...visto, solicitud_preparacion: solicitud }} tarea={visto?.tarea_preparacion} onVer={(documentos, indice) => setVista({ documentos, indice })} />
            {!soloLectura && !cerrada && <button type="button" className="tienda-ajustes-desplegar" aria-expanded={modificar} onClick={() => setModificar(!modificar)}><span className={GELIA_ICON_BOX}><PackagePlus size={20} aria-hidden /></span><span>Modificar pedido</span><ChevronDown size={18} aria-hidden /></button>}
            {!soloLectura && !cerrada && (
                <div hidden={!modificar}>
                <AjustePiezas
                    pedidoId={pedidoId}
                    pedido={visto}
                    solicitud={solicitud}
                    adaptador={adaptador}
                    onSolicitud={(siguiente) => { setSolicitud(siguiente); onSolicitud?.(siguiente); }}
                    onAviso={setAviso}
                    onVer={(documentos, indice) => setVista({ documentos, indice })}
                />
                </div>
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
