import { areaResponsableMunicipal, nombreTerminalMunicipal } from './terminalMunicipal';
import React, { useCallback, useEffect, useRef, useState } from 'react';
import { CheckCircle2, ChevronDown, Clock, MapPin, PackagePlus } from 'lucide-react';
import EncabezadoFolioPedido from '../Partials/EncabezadoFolioPedido';
import AvisoOperativoPedido from '../Partials/AvisoOperativoPedido';
import RespuestaDisponibilidadPedido from '../Partials/RespuestaDisponibilidadPedido';
import AccionesRapidasDisponibilidad from '../Partials/AccionesRapidasDisponibilidad';
import CapturaConjuntoPedido from '../Partials/CapturaConjuntoPedido';
import { AjustePiezas } from '../Partials/AjustePiezas';
import { adaptadorConsultaMunicipal } from '../Partials/adaptadoresConsulta';
import ModalVistaPreviaDocumento from '../Partials/ModalVistaPreviaDocumento';
import { formatearFechaNegocio } from '../Partials/pedidosBmaStyles';
import { GELIA_BTN_OUTLINE, GELIA_ICON_BOX, THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';
import { anexosPendientesRevision, filasDeCambios, revisionesDelCicloEnCurso } from '../Partials/cambiosDisponibilidad';

function etiquetaConsulta(consulta, revision, solicitud) {
    if (consulta?.estado === 'cerrada') return { texto: 'Consulta cerrada', tono: 'gelia-estado-vivo--exito' };
    if (anexosPendientesRevision(solicitud) || revision?.actualizacion_pendiente || revision?.vigente === false || revision?.sustituida_at) {
        return { texto: 'Excepciones pendientes', tono: 'gelia-estado-vivo--aviso' };
    }
    if (revision?.estado === 'excepciones_pendientes' || consulta?.estado === 'excepciones_pendientes') {
        return { texto: 'Excepciones pendientes', tono: 'gelia-estado-vivo--aviso' };
    }
    if (consulta?.estado === 'respondida' || revision?.estado === 'sin_excepciones') {
        return { texto: 'Consulta respondida', tono: 'gelia-estado-vivo--exito' };
    }
    return {
        texto: consulta?.area === 'tienda' ? 'Consulta pendiente: Tienda' : 'Consulta pendiente: CEDIS',
        tono: 'gelia-estado-vivo--aviso',
    };
}

export default function SeguimientoSolicitudMunicipal({ pedido, onCerrar, onReabrir, cerrando, onPedidoActualizado, onOcupado }) {
    const capturasOcupadas = useRef(new Set());
    const marcarOcupado = useCallback((valor, id) => {
        if (valor) capturasOcupadas.current.add(id);
        else capturasOcupadas.current.delete(id);
        onOcupado?.(capturasOcupadas.current.size > 0);
    }, [onOcupado]);
    useEffect(() => () => onOcupado?.(false), [onOcupado]);
    const [docPreview, setDocPreview] = useState(null);
    const [solicitud, setSolicitud] = useState(pedido?.solicitud_preparacion || {});
    const [modificar, setModificar] = useState(false);
    const [aviso, setAviso] = useState('');
    useEffect(() => { setSolicitud(pedido?.solicitud_preparacion || {}); }, [pedido]);
    const consulta = solicitud.consulta_municipal;
    const revision = solicitud.revision_disponibilidad;
    if (!consulta && !revision) return null;
    const etiqueta = etiquetaConsulta(consulta, revision, solicitud);
    const cerrada = consulta?.estado === 'cerrada' || Boolean(pedido?.consulta_cerrada);
    const pendiente = anexosPendientesRevision(solicitud) || Boolean(revision?.actualizacion_pendiente) || revision?.vigente === false || Boolean(revision?.sustituida_at);
    const excepciones = pendiente;
    const respondida = consulta?.estado === 'respondida' || ['sin_excepciones', 'excepciones_pendientes', 'decision_registrada'].includes(revision?.estado);
    const origen = consulta?.area === 'tienda' ? 'Tienda' : 'CEDIS';
    const documentos = (pedido.documentos || []).filter(d => d.tipo === 'evidencia_condicion' && d.url && d.activo !== false && !String(d.comentario || '').startsWith('Evidencia petición'));
    const capturas = documentos.filter(d => !d.relacion_tipo || d.relacion_tipo === 'revision_general');
    const vigentes = capturas.filter(d => !revision?.evidencia_actual_ids || revision.evidencia_actual_ids.includes(d.id));
    const anteriores = capturas.filter(d => revision?.evidencia_actual_ids && !revision.evidencia_actual_ids.includes(d.id));
    const enCurso = revision?.actualizacion_pendiente ? revisionesDelCicloEnCurso(solicitud) : new Set();
    const piezas = (pedido.revisiones_producto || []).filter(p => !enCurso.has(Number(p.id)) && !p.retirada_at && ['regular', 'malo', 'danado', 'sin_existencia'].includes(p.estado_fisico));
    const filas = piezas.map(p => ({
        id: p.id, descripcion: p.descripcion_producto || p.descripcion, sku: p.sku,
        cantidad: p.cantidad_encontrada ?? (p.estado_fisico === 'sin_existencia' ? 0 : p.cantidad_solicitada),
        estado_fisico: p.estado_fisico, unica_pieza: p.unica_pieza, mejor_ejemplar: p.mejor_ejemplar,
        observacion: p.comentario, original: p,
        documentos: documentos.filter(d => d.relacion_tipo === 'revision_producto' && Number(d.relacion_id) === Number(p.id)),
    }));
    const cambios = filasDeCambios(solicitud, pedido.documentos || [], { revisionIdsEnTabla: new Set(filas.map(f => Number(f.id))) });
    const avisoReapertura = solicitud.reapertura_disponibilidad ? `Consulta reabierta por ${solicitud.reapertura_disponibilidad.por}.` : '';
    const bloqueo = pedido?.motivo_bloqueo_cierre;
    const puedeCerrar = respondida && !cerrada && !pendiente && pedido?.puede_cerrar_consulta !== false && !bloqueo;
    const adaptador = adaptadorConsultaMunicipal({ pedidoId: pedido.id, pedido: { ...pedido, solicitud_preparacion: solicitud } });
    const puedeAjustar = !cerrada && (adaptador.capacidades.anexar || adaptador.capacidades.retirar);

    return (
        <section className="registrar-tienda-panel space-y-4" aria-label="Seguimiento de envío municipal">
            <div className="registrar-tienda-seccion-titulo"><MapPin size={18} aria-hidden /><h3>Seguimiento · Envío municipal</h3></div>
            <EncabezadoFolioPedido pedido={pedido} size="lg" etiquetaPrincipal="Folio" internoCompacto />
            <dl className="grid sm:grid-cols-2 gap-3 text-sm m-0">
                <div><dt className="text-xs font-semibold theme-text-muted m-0">Cliente</dt><dd className="m-0 theme-text-main">{pedido.cliente?.nombre || pedido.cliente?.nombre_comercial || '—'}</dd></div>
                <div><dt className="text-xs font-semibold theme-text-muted m-0">Modalidad</dt><dd className="m-0 theme-text-main">Envío municipal</dd></div>
                <div><dt className="text-xs font-semibold theme-text-muted m-0">Terminal destino</dt><dd className="m-0 theme-text-main">{nombreTerminalMunicipal(pedido)}</dd></div>
                <div><dt className="text-xs font-semibold theme-text-muted m-0">Área responsable</dt><dd className="m-0 theme-text-main">{areaResponsableMunicipal(pedido)}</dd></div>
                {consulta?.enviada_at && <div><dt className="text-xs font-semibold theme-text-muted m-0">Consulta enviada</dt><dd className="m-0 theme-text-main">{formatearFechaNegocio(consulta.enviada_at)}</dd></div>}
                {revision?.respondida_at && <div><dt className="text-xs font-semibold theme-text-muted m-0">Última respuesta</dt><dd className="m-0 theme-text-main">{formatearFechaNegocio(revision.respondida_at)}</dd></div>}
            </dl>
            <span className={`gelia-estado-vivo gelia-estado-vivo--compacto ${etiqueta.tono}`}>{etiqueta.texto}</span>
            {!respondida && !cerrada && (
                <AvisoOperativoPedido label={`Esperando respuesta de ${origen}`} tono="warning" icon={Clock}>
                    La consulta ya se envió. Aquí aparecerá el resultado de las piezas cuando {origen} responda. CEDIS o Tienda confirman mercancía; Ventas registra el cierre con el cliente.
                </AvisoOperativoPedido>
            )}
            {respondida && revision?.estado && (
                <RespuestaDisponibilidadPedido
                    titulo={revision?.actualizacion_pendiente ? "Respuesta anterior de disponibilidad" : "Respuesta de disponibilidad"}
                    origen={origen}
                    ariaLabel={`Resultado de la consulta en ${origen}`}
                    responsable={pedido.pesaje_respondido_por?.name || pedido.pesajeRespondidoPor?.name || revision.respondida_por_nombre}
                    fecha={revision.respondida_at}
                    estado={revision.estado}
                    aceptacionVigente={revision.aceptacion_vigente !== false && !pendiente}
                    mostrarCaptura={false}
                    cambios={cambios} avisoReapertura={avisoReapertura}
                    filas={filas}
                    generales={vigentes}
                    anteriores={anteriores}
                    cantidad={piezas.length ? piezas.reduce((s, p) => s + Number(p.cantidad_solicitada || 0), 0) : null}
                    onVer={(docs, indice) => setDocPreview({ documentos: docs, indice })}
                    accionesDePieza={pieza => !adaptador.capacidades.sustituir || pieza?.resolucion || !['regular', 'malo', 'danado', 'sin_existencia'].includes(pieza?.estado_fisico) ? null : (
                        <AccionesRapidasDisponibilidad compacta pieza={pieza} pedidoId={pedido.id} almacenId={adaptador.almacenId} onActualizar={onPedidoActualizado} onOcupado={marcarOcupado} onVer={(docs, indice) => setDocPreview({ documentos: docs, indice })} />
                    )}
                />
            )}
            <AvisoOperativoPedido tono="info">
                CEDIS o Tienda confirman mercancía. Ventas registra el cierre con el cliente. Una respuesta positiva no cierra ni autoriza el pago.
            </AvisoOperativoPedido>
            {!(respondida && revision?.estado) && (cambios.actuales.length > 0 || cambios.anteriores.length > 0) && (
                <RespuestaDisponibilidadPedido titulo="Cambios solicitados" ariaLabel="Cambios solicitados" estado={null} cantidad={null} mostrarCaptura={false} cambios={cambios} avisoReapertura={avisoReapertura} onVer={(docs, indice) => setDocPreview({ documentos: docs, indice })} />
            )}
            <CapturaConjuntoPedido pedido={{ ...pedido, solicitud_preparacion: solicitud }} onVer={(docs, indice) => setDocPreview({ documentos: docs, indice })} />
            {aviso && <p className="text-sm theme-text-main m-0" role="status">{aviso}</p>}
            {puedeAjustar && <button type="button" className="tienda-ajustes-desplegar" aria-expanded={modificar} onClick={() => setModificar((actual) => !actual)}><span className={GELIA_ICON_BOX}><PackagePlus size={20} aria-hidden /></span><span>Modificar pedido</span><ChevronDown size={18} aria-hidden /></button>}
            {puedeAjustar && (
                <div hidden={!modificar}>
                    <AjustePiezas
                        pedidoId={pedido.id}
                        pedido={{ ...pedido, solicitud_preparacion: solicitud }}
                        solicitud={solicitud}
                        adaptador={adaptador}
                        onDirtyChange={(valor) => marcarOcupado(valor, 'ajuste')}
                        onSolicitud={(siguiente, versionConsulta) => {
                            setSolicitud(siguiente);
                            onPedidoActualizado?.({
                                ...pedido,
                                solicitud_preparacion: siguiente,
                                consulta_cerrada: false,
                                consulta_cerrada_at: null,
                                ...(versionConsulta != null && Number(versionConsulta) > Number(pedido?.version_consulta ?? 0) ? { version_consulta: Number(versionConsulta) } : {}),
                            });
                        }}
                        onAviso={setAviso}
                        onVer={(docs, indice) => setDocPreview({ documentos: docs, indice })}
                    />
                </div>
            )}
            {excepciones && !cerrada && <p className="text-sm theme-text-aviso m-0" role="status">Hay excepciones pendientes. La consulta sigue detenida.</p>}
            {bloqueo && !cerrada && <p className="text-sm theme-text-aviso m-0" role="status">{bloqueo}</p>}
            {cerrada && onReabrir && !pedido.pago_validado_at && !pedido.numero_remision && (
                <button type="button" className={GELIA_BTN_OUTLINE} disabled={cerrando} onClick={onReabrir}>Solicitar nueva revisión</button>
            )}
            {cerrada && (
                <AvisoOperativoPedido label="Consulta cerrada" tono="success" icon={CheckCircle2}>
                    Ventas registró el cierre con el cliente.
                </AvisoOperativoPedido>
            )}
            {puedeCerrar && (
                <button
                    type="button"
                    className={THEME_BTN_PRIMARY}
                    disabled={cerrando}
                    onClick={() => onCerrar?.({
                        modalidad_esperada: pedido?.solicitud_preparacion?.modalidad_entrega || 'municipal',
                        version_consulta: pedido?.version_consulta ?? null,
                    })}
                >
                    {cerrando ? 'Cerrando…' : 'Cerrar consulta'}
                </button>
            )}
            <ModalVistaPreviaDocumento abierto={Boolean(docPreview)} documentos={docPreview?.documentos} indice={docPreview?.indice || 0} documento={docPreview?.documento} onClose={() => setDocPreview(null)} />
        </section>
    );
}
