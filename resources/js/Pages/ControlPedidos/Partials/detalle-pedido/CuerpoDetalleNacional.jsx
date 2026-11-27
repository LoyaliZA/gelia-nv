import React from 'react';
import {
    Building2, Calendar, ClipboardCheck, CreditCard, Hash, MapPin, Package, Receipt, Scale, Store, Truck, UserRound, Wallet,
} from 'lucide-react';
import DireccionPedidoResumen from '../DireccionPedidoResumen';
import { codigoDireccionCliente } from '../codigoDireccionCliente';
import { esCapturaNacional } from '../flujoCapturaPedido';
import SeccionGuiaRastreo from '../SeccionGuiaRastreo';
import SeccionRevisionFisicaPedido from '../SeccionRevisionFisicaPedido';
import { cambioATiendaNacional } from '../../Nacional/superficieNacional';
import {
    BTN_SECONDARY,
    etiquetaAlmacen,
    etiquetaEnvio,
    etiquetaEstatusPedido,
    etiquetaOrigenGuia,
    etiquetaSucursal,
    formatearFechaHoraAuditoria,
    formatearFechaNegocio,
    formatearMoneda,
    LABEL_NOTA_COMPRA_CAMPO,
    pedidoRequiereLogistica,
    respondeComoTienda,
} from '../pedidosBmaStyles';
import DetallePedidoDato, { DetallePedidoDatos, DetallePedidoResumen } from './DetallePedidoDato';
import DetallePedidoSeccion from './DetallePedidoSeccion';
import SeccionMultienvioDetalle from './SeccionMultienvioDetalle';
import { etiquetaRespuestaDetalle, textoPagosPedido } from './resolverModalidadDetalle';

function kg(valor) {
    return valor != null && valor !== '' ? `${valor} kg` : null;
}

function guiasConfirmadasPorCaja(pedido, cajas) {
    if (!esCapturaNacional(pedido) || pedido.aplica_seguro || pedido.cliente_proporciona_guia) {
        return null;
    }

    const documentos = (pedido.documentos || []).filter((doc) => doc.tipo === 'guia'
        && ['guia_caja_confirmada', 'guia_caja_avance'].includes(doc.relacion_tipo));
    if (!documentos.length) return null;

    const confirmadas = new Map(documentos
        .filter((doc) => doc.relacion_tipo === 'guia_caja_confirmada' && doc.activo && doc.url)
        .map((doc) => [String(doc.pedido_bma_caja_id), doc]));

    return cajas.flatMap((caja, idx) => {
        if (['retirada', 'cancelada'].includes(caja.estado_operativo)) return [];
        const documento = confirmadas.get(String(caja.id));
        return documento ? [{
            cajaId: caja.id,
            nombreCaja: caja.tipo_caja?.nombre || caja.tipoCaja?.nombre,
            envio: idx + 1,
            documento,
        }] : [];
    });
}

export default function CuerpoDetalleNacional({
    pedido,
    vacios = false,
    puedeAtender,
    puedeCambiarDireccion = false,
    onVerDoc,
    onVerGaleria,
    onCambiarDireccion,
}) {
    const respondeTienda = respondeComoTienda(pedido);
    const opcionesEstatus = {
        pedido,
        esResguardo: pedido.es_resguardo,
        requiereLogistica: pedidoRequiereLogistica(pedido),
    };
    const sucursal = cambioATiendaNacional(pedido) ? (pedido.sucursal_destino || (pedido.sucursal_destino_id ? {
        id: pedido.sucursal_destino_id,
        nombre: pedido.sucursal_destino_nombre,
        codigo: pedido.sucursal_destino_codigo,
    } : null)) : null;
    const snap = pedido.direccion_vigente || pedido.direccionVigente;
    const sinRevision = !pedido.estado_fisico_general
        && !(pedido.revisiones_producto || pedido.revisionesProducto || []).length
        && !pedido.pesaje_respondido_at;
    const pesoOBultos = pedidoRequiereLogistica(pedido)
        ? (pedido.peso_cobrado_guia_kg != null ? `${pedido.peso_cobrado_guia_kg} kg` : (vacios ? '— kg' : null))
        : (pedido.numero_cajas != null ? String(pedido.numero_cajas) : (vacios ? '—' : null));
    const cajas = [...(pedido.cajas || [])].sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0));
    const guiasPorCaja = guiasConfirmadasPorCaja(pedido, cajas);
    const direccionBloqueada = pedido.solicitud_preparacion?.modalidad_entrega === 'nacional'
        && ['ENVIADO', 'ENTREGADO'].includes(pedido.estatus?.fase_ciclo);

    return (
        <>
            <section id="detalle-respuesta" tabIndex={-1} className="space-y-3">
                <DetallePedidoResumen hechos={[
                    { icon: ClipboardCheck, label: respondeTienda ? 'Respuesta de Tienda' : 'Respuesta CEDIS', value: etiquetaRespuestaDetalle(pedido) },
                    pesoOBultos && {
                        icon: Scale,
                        label: pedidoRequiereLogistica(pedido) ? 'Peso cobrado' : 'Bultos aproximados',
                        value: pesoOBultos,
                    },
                    { icon: Wallet, label: 'Importe', value: formatearMoneda(pedido.total_a_cobrar) },
                ]} />
                <SeccionRevisionFisicaPedido
                    pedido={pedido}
                    titulo={respondeTienda ? 'Revisión física de Tienda' : undefined}
                    onVerDoc={onVerDoc}
                    onVerGaleria={onVerGaleria}
                    puedeAtender={puedeAtender}
                    puedeCancelar={Boolean(pedido?.puede_cancelar)}
                />
                {sinRevision && (
                    <p className="text-sm theme-text-muted m-0 px-1">
                        La revisión y sus evidencias aparecerán aquí cuando {respondeTienda ? 'Tienda registre su respuesta' : 'CEDIS registre su respuesta'}.
                    </p>
                )}
            </section>
            <DetallePedidoSeccion id="detalle-datos" icon={ClipboardCheck} titulo="Datos del pedido">
                <DetallePedidoDatos>
                    <DetallePedidoDato icon={Hash} label="Número de pedido" value={pedido.folio_remision} obligatorio />
                    <DetallePedidoDato icon={Receipt} label="Número de remisión" value={pedido.numero_remision} obligatorio={vacios} />
                    <DetallePedidoDato icon={UserRound} label="Cliente" value={[pedido.cliente?.numero_cliente, pedido.cliente?.nombre].filter(Boolean).join(' — ')} obligatorio />
                    <DetallePedidoDato icon={Calendar} label="Fecha pedido" value={formatearFechaNegocio(pedido.fecha)} obligatorio />
                    <DetallePedidoDato icon={Calendar} label="Registrado" value={formatearFechaHoraAuditoria(pedido.created_at)} obligatorio={vacios} />
                    <DetallePedidoDato icon={ClipboardCheck} label="Estado" value={etiquetaEstatusPedido(pedido.estatus, opcionesEstatus)} obligatorio />
                    <DetallePedidoDato icon={Truck} label="Tipo de pedido" value={pedido.origen?.nombre} obligatorio={vacios} />
                    <DetallePedidoDato icon={Building2} label="Almacén" value={pedido.almacen ? etiquetaAlmacen(pedido.almacen) : null} obligatorio={vacios} />
                    <DetallePedidoDato icon={Store} label="Sucursal de recolección" value={sucursal ? etiquetaSucursal(sucursal) : null} />
                    <DetallePedidoDato icon={CreditCard} label="Pagos / bancos" value={textoPagosPedido(pedido)} obligatorio={vacios} />
                    <DetallePedidoDato icon={Package} label="Tipo caja" value={pedido.tipo_caja?.nombre} obligatorio={vacios} />
                    <DetallePedidoDato icon={Scale} label="Peso vol." value={kg(pedido.peso_volumetrico_kg)} obligatorio={vacios} />
                    <DetallePedidoDato icon={Scale} label="Peso real" value={kg(pedido.peso_real_kg)} obligatorio={vacios} />
                    <DetallePedidoDato icon={Scale} label="Peso cobrado guía" value={kg(pedido.peso_cobrado_guia_kg)} obligatorio={vacios} />
                    <DetallePedidoDato icon={Truck} label="Paquetería" value={pedido.paqueteria?.nombre} obligatorio={vacios} />
                    <DetallePedidoDato icon={Receipt} label="Origen de la guía" value={etiquetaOrigenGuia(pedido)} obligatorio={vacios} />
                    <DetallePedidoDato icon={Receipt} label="Tipo guía" value={pedido.tipo_guia?.nombre} obligatorio={vacios} />
                    <DetallePedidoDato icon={MapPin} label="Reexpedición" value={pedido.zona?.nombre} obligatorio={vacios} />
                    <DetallePedidoDato icon={ClipboardCheck} label="Resguardo" value={vacios || pedido.es_resguardo ? (pedido.es_resguardo ? 'Sí' : 'No') : null} obligatorio={vacios} />
                    <DetallePedidoDato
                        icon={Calendar}
                        label="Apartado CEDIS"
                        value={pedido.resguardo_apartado_at
                            ? formatearFechaHoraAuditoria(pedido.resguardo_apartado_at)
                            : (pedido.es_resguardo || vacios ? (pedido.es_resguardo ? 'Pendiente' : '—') : null)}
                        obligatorio={vacios}
                    />
                    <DetallePedidoDato icon={Receipt} label={LABEL_NOTA_COMPRA_CAMPO} value={vacios || pedido.anexar_remision ? (pedido.anexar_remision ? 'Sí' : 'No') : null} obligatorio={vacios} />
                    <DetallePedidoDato icon={MapPin} label="C.P." value={pedido.codigo_postal} obligatorio={vacios} />
                    <DetallePedidoDato icon={Wallet} label="Total a cobrar" value={formatearMoneda(pedido.total_a_cobrar)} obligatorio />
                </DetallePedidoDatos>
            </DetallePedidoSeccion>
            <SeccionMultienvioDetalle pedido={pedido} />
            {cajas.length > 0 && (
                <DetallePedidoSeccion icon={Package} titulo="Envíos y pesaje">
                    {cajas.map((c, idx) => (
                        <div key={c.id || idx} className="space-y-1">
                            <p className="text-sm font-semibold theme-text-main m-0">
                                {etiquetaEnvio(idx, c)}
                                <span className="ml-2 font-medium theme-text-muted">
                                    {(c.estatus_recoleccion || 'pendiente') === 'recolectada' ? 'Recolectada' : 'Pendiente'}
                                </span>
                            </p>
                            <div className="grid grid-cols-2 gap-1 text-sm">
                                <p className="m-0 theme-text-muted">Largo: <span className="theme-text-main">{c.largo != null ? `${c.largo} cm` : '—'}</span></p>
                                <p className="m-0 theme-text-muted">Ancho: <span className="theme-text-main">{c.ancho != null ? `${c.ancho} cm` : '—'}</span></p>
                                <p className="m-0 theme-text-muted">Alto: <span className="theme-text-main">{c.alto != null ? `${c.alto} cm` : '—'}</span></p>
                                <p className="m-0 theme-text-muted">Vol.: <span className="theme-text-main">{c.peso_volumetrico_kg != null ? `${c.peso_volumetrico_kg} kg` : '—'}</span></p>
                                <p className="m-0 theme-text-muted">Real: <span className="theme-text-main">{c.peso_real_kg != null ? `${c.peso_real_kg} kg` : '—'}</span></p>
                                <p className="m-0 theme-text-muted">Cobrado: <span className="theme-text-main">{c.peso_cobrado_kg != null ? `${c.peso_cobrado_kg} kg` : '—'}</span></p>
                                {(c.numero_rastreo || pedido.numero_rastreo) && (
                                    <p className="m-0 theme-text-muted col-span-2">
                                        Guía: <span className="theme-text-main">{c.numero_rastreo || pedido.numero_rastreo}</span>
                                    </p>
                                )}
                            </div>
                        </div>
                    ))}
                </DetallePedidoSeccion>
            )}
            <DetallePedidoSeccion
                id="detalle-envio"
                icon={MapPin}
                titulo="Entrega y dirección"
                extra={puedeCambiarDireccion && !direccionBloqueada ? (
                    <button
                        type="button"
                        className={`${BTN_SECONDARY} text-xs py-1.5 px-2 inline-flex items-center gap-1`}
                        onClick={onCambiarDireccion}
                    >
                        <MapPin className="w-3.5 h-3.5" aria-hidden="true" /> Cambiar
                    </button>
                ) : null}
            >
                <SeccionGuiaRastreo pedido={pedido} onVerPdf={onVerDoc} guiasPorCaja={guiasPorCaja} />
                <DireccionPedidoResumen
                    direccion={snap}
                    domicilioLegacy={pedido.domicilio_entrega}
                    codigoPostal={pedido.codigo_postal}
                    codigoDireccion={codigoDireccionCliente(pedido.cliente?.numero_cliente, snap?.numero_direccion)}
                    compact={!vacios}
                    etiquetaDestinatario="Nombre de quien recibe"
                />
                <DetallePedidoDatos>
                    <DetallePedidoDato icon={UserRound} label="Destinatario alterno" value={pedido.envia_a_otra_persona ? pedido.envia_otra_persona : null} obligatorio={vacios && pedido.envia_a_otra_persona} />
                    <DetallePedidoDato icon={ClipboardCheck} label="Comentarios" value={pedido.comentarios_drive} obligatorio={vacios} />
                </DetallePedidoDatos>
            </DetallePedidoSeccion>
        </>
    );
}
