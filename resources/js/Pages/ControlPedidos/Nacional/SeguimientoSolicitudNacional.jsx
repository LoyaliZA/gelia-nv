import React, { useState } from 'react';
import axios from 'axios';
import { CheckCircle2, ChevronDown, Clock, Eye, Layers, MapPin, PackagePlus, Scale, Boxes, Weight } from 'lucide-react';
import { AjustePiezas } from '../Partials/AjustePiezas';
import AvisoOperativoPedido from '../Partials/AvisoOperativoPedido';
import RespuestaDisponibilidadPedido from '../Partials/RespuestaDisponibilidadPedido';
import { adaptadorConsultaNacional } from '../Partials/adaptadoresConsulta';
import { GELIA_BTN_OUTLINE, GELIA_ICON_BOX, THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';
import { vinculoFisicoNacional } from './superficieNacional';

const ESTADOS_CON_DETALLE = ['regular', 'malo', 'danado', 'sin_existencia'];
const numero = (valor) => Number(valor);

function etiquetaConsulta({ respondida, cerrada, actualizando }) {
    if (cerrada) return { texto: 'Consulta cerrada', tono: 'gelia-estado-vivo--exito' };
    if (actualizando) return { texto: 'Actualización pendiente', tono: 'gelia-estado-vivo--aviso' };
    if (respondida) return { texto: 'Consulta respondida', tono: 'gelia-estado-vivo--exito' };

    return { texto: 'Consulta pendiente: CEDIS', tono: 'gelia-estado-vivo--aviso' };
}

/** Datos del pesaje que CEDIS respondió, cada uno en su propia celda con icono. */
function ResumenPesaje({ pedido }) {
    const datos = [
        pedido.numero_cajas ? { clave: 'cajas', icono: Boxes, titulo: pedido.numero_cajas > 1 ? 'Cajas' : 'Cajas', valor: String(numero(pedido.numero_cajas)) } : null,
        pedido.peso_real_kg != null ? { clave: 'real', icono: Scale, titulo: 'Peso real', valor: `${numero(pedido.peso_real_kg)} kg` } : null,
        pedido.peso_cobrado_guia_kg != null ? { clave: 'cobrar', icono: Weight, titulo: 'Peso a cobrar', valor: `${numero(pedido.peso_cobrado_guia_kg)} kg` } : null,
    ].filter(Boolean);
    if (!datos.length) return null;

    return (
        <dl className="nacional-pesaje-resumen" aria-label="Resumen del pesaje">
            {datos.map(({ clave, icono: Icono, titulo, valor }) => (
                <div className="nacional-pesaje-dato" key={clave}>
                    <span className={GELIA_ICON_BOX}><Icono size={18} aria-hidden="true" /></span>
                    <div className="min-w-0">
                        <dt>{titulo}</dt>
                        <dd>{valor}</dd>
                    </div>
                </div>
            ))}
        </dl>
    );
}

function mensajeDe(error, reserva) {
    const datos = error?.response?.data;
    const lista = datos?.errors ? Object.values(datos.errors).flat() : [];

    return lista[0] || datos?.message || reserva;
}

export default function SeguimientoSolicitudNacional({ pedido, onPedidoActualizado, onVer, referenciaMercancia }) {
    const [aviso, setAviso] = useState('');
    const [error, setError] = useState('');
    const [ocupado, setOcupado] = useState(false);
    const [modificar, setModificar] = useState(false);
    const actualizando = Boolean(pedido?.consulta_actualizacion_pendiente);
    const respondida = Boolean(pedido?.pesaje_respondido_at) && !actualizando;
    const cerrada = Boolean(pedido?.consulta_cerrada || pedido?.consulta_cerrada_at);
    const adaptador = adaptadorConsultaNacional({ pedidoId: pedido?.id, pedido });
    const puedeAjustar = !cerrada && (adaptador.capacidades.anexar || adaptador.capacidades.retirar);
    const puedeCerrar = !cerrada && Boolean(pedido?.puede_cerrar_consulta);
    const documentosRespuesta = (pedido?.documentos || []).filter((doc) => doc.tipo === 'evidencia_condicion'
        && doc.activo !== false && doc.url && ['revision_producto', 'envio_caja', 'revision_general'].includes(doc.relacion_tipo));
    const vinculo = vinculoFisicoNacional(pedido);
    const puedeRegistrarSalida = Boolean(vinculo && vinculo.vinculo.estado === 'vinculado');
    const etiqueta = etiquetaConsulta({ respondida, cerrada, actualizando });
    const revisiones = (pedido?.revisiones_producto || []).filter((pieza) => !pieza.retirada_at);
    const conDetalle = revisiones.some((pieza) => ESTADOS_CON_DETALLE.includes(pieza.estado_fisico));
    const filas = revisiones.map((pieza) => ({
        id: pieza.id,
        sku: pieza.sku,
        descripcion: pieza.descripcion_producto || pieza.descripcion,
        cantidad: pieza.cantidad_encontrada ?? (pieza.estado_fisico === 'sin_existencia' ? 0 : pieza.cantidad_solicitada),
        estado_fisico: pieza.estado_fisico || 'bueno',
        unica_pieza: pieza.unica_pieza,
        mejor_ejemplar: pieza.mejor_ejemplar,
        observacion: pieza.comentario,
        original: pieza,
        documentos: (pedido?.documentos || []).filter((doc) => doc.tipo === 'evidencia_condicion' && doc.activo !== false && doc.url
            && doc.relacion_tipo === 'revision_producto' && Number(doc.relacion_id) === Number(pieza.id)),
    }));
    const conPago = Boolean(pedido?.numero_remision || pedido?.pago_validado_at);
    const hayMercancia = respondida;

    const refrescar = async () => {
        const { data } = await axios.get(`/control-pedidos/${pedido.id}/consulta-nacional`, {
            headers: { Accept: 'application/json' },
        });
        onPedidoActualizado?.(data);

        return data;
    };

    const ejecutar = async (accion) => {
        if (ocupado) return;
        setOcupado(true);
        setError('');
        try {
            await accion();
        } catch (fallo) {
            setError(mensajeDe(fallo, 'No se completó la operación. Intente de nuevo.'));
        } finally {
            setOcupado(false);
        }
    };

    return (
        <>
        <section className="registrar-tienda-panel space-y-4" aria-label="Consulta a CEDIS">
            <div className="registrar-tienda-seccion-titulo"><MapPin size={18} aria-hidden="true" /><h3>Consulta a CEDIS</h3></div>
            <span className={`gelia-estado-vivo gelia-estado-vivo--compacto ${etiqueta.tono}`}>{etiqueta.texto}</span>
            {!respondida && !cerrada && (
                <AvisoOperativoPedido label="Esperando respuesta de CEDIS" tono="warning" icon={Clock}>
                    {actualizando
                        ? 'CEDIS tiene pendiente actualizar la disponibilidad y el peso.'
                        : 'Consulta enviada. CEDIS tiene pendiente confirmar disponibilidad y peso.'}
                </AvisoOperativoPedido>
            )}
            {respondida && (
                <RespuestaDisponibilidadPedido
                    titulo="Respuesta de CEDIS"
                    origen="CEDIS"
                    ariaLabel="Resultado de la consulta en CEDIS"
                    responsable={pedido.pesaje_respondido_por?.name || pedido.pesajeRespondidoPor?.name}
                    fecha={pedido.pesaje_respondido_at}
                    estado={filas.length ? (conDetalle ? 'excepciones_pendientes' : 'sin_excepciones') : null}
                    aceptacionVigente={!actualizando}
                    mostrarCaptura={false}
                    filas={filas}
                    cantidad={filas.length ? filas.reduce((total, fila) => total + numero(fila.cantidad || 0), 0) : null}
                    labelCantidad="Unidades encontradas"
                    onVer={onVer}
                >
                    <ResumenPesaje pedido={pedido} />
                    {documentosRespuesta.length > 0 && onVer && (
                        <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px] mt-3 inline-flex items-center gap-2`} onClick={() => onVer(documentosRespuesta, 0)}>
                            <Eye size={16} aria-hidden="true" /> Ver evidencias de CEDIS
                        </button>
                    )}
                </RespuestaDisponibilidadPedido>
            )}
            {cerrada && (
                <AvisoOperativoPedido label="Consulta cerrada" tono="success" icon={CheckCircle2}>
                    Ventas registró el cierre de la consulta con el cliente.
                </AvisoOperativoPedido>
            )}
            {aviso && <p className="text-sm theme-text-main m-0" role="status">{aviso}</p>}
            {error && <p className="text-sm theme-text-aviso m-0" role="alert">{error}</p>}

            <div className="flex flex-col sm:flex-row gap-2">
                <button type="button" className={`${GELIA_BTN_OUTLINE} min-h-[44px]`} onClick={() => ejecutar(refrescar)}>
                    {ocupado ? 'Actualizando…' : 'Actualizar consulta'}
                </button>
                {puedeCerrar && (
                    <button
                        type="button"
                        className={`${THEME_BTN_PRIMARY} min-h-[44px]`}
                        onClick={() => ejecutar(async () => {
                            const version = Number(pedido?.version_consulta);
                            await axios.post(`/control-pedidos/${pedido.id}/cerrar-consulta`, {
                                modalidad_esperada: 'nacional',
                                ...(Number.isInteger(version) && version > 0 ? { version_consulta: version } : {}),
                            }, { headers: { Accept: 'application/json' } });
                            await refrescar();
                        })}
                    >
                        {ocupado ? 'Cerrando…' : 'Cerrar consulta'}
                    </button>
                )}
                {puedeRegistrarSalida && (
                    <button
                        type="button"
                        className={`${THEME_BTN_PRIMARY} min-h-[44px]`}
                        onClick={() => ejecutar(async () => {
                            await axios.post(`/control-pedidos/${vinculo.pedidoId}/custodia-nacional`, {
                                accion: 'salida',
                            }, { headers: { Accept: 'application/json' } });
                            await refrescar();
                        })}
                    >
                        {ocupado ? 'Registrando…' : 'Registrar salida'}
                    </button>
                )}
            </div>
        </section>
        {hayMercancia && (
            <section className="registrar-tienda-panel space-y-3" aria-labelledby="mercancia-nacional-titulo">
                <div className="registrar-tienda-seccion-titulo">
                    <Layers size={18} aria-hidden="true" />
                    <h3 id="mercancia-nacional-titulo">Mercancía</h3>
                </div>
                {filas.length === 0 && referenciaMercancia}
                {puedeAjustar && (
                    <>
                        <button type="button" className="tienda-ajustes-desplegar" aria-expanded={modificar} onClick={() => setModificar((actual) => !actual)}>
                            <span className={GELIA_ICON_BOX}><PackagePlus size={20} aria-hidden="true" /></span>
                            <span>Modificar pedido</span>
                            <ChevronDown size={18} aria-hidden="true" />
                        </button>
                        <div hidden={!modificar}>
                            <AjustePiezas
                                pedidoId={pedido.id}
                                pedido={pedido}
                                solicitud={pedido.solicitud_preparacion || {}}
                                adaptador={adaptador}
                                avisoAnexo="Anexo registrado. CEDIS debe responder el nuevo peso antes de cerrar."
                                avisoRetiro="Retiro registrado. CEDIS debe responder el nuevo peso antes de cerrar."
                                onVer={onVer}
                                onSolicitud={(_, version, lectura) => lectura ? onPedidoActualizado?.(lectura) : ejecutar(refrescar)}
                                onAviso={setAviso}
                            />
                        </div>
                    </>
                )}
                {!puedeAjustar && (
                    <AvisoOperativoPedido tono="info" icon={PackagePlus}>
                        {conPago
                            ? 'Para agregar piezas a un pedido con pago o remisión use «Anexo extraordinario» en el menú del pedido.'
                            : 'La consulta está cerrada: la mercancía ya no se modifica desde aquí.'}
                    </AvisoOperativoPedido>
                )}
            </section>
        )}
        </>
    );
}
