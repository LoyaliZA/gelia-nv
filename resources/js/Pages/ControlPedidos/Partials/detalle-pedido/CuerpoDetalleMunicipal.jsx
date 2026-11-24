import React from 'react';
import {
    Building2, Calendar, ClipboardCheck, CreditCard, Hash, MapPin, Phone, Receipt, Store, UserRound, Wallet,
} from 'lucide-react';
import { nombreTerminalMunicipal } from '../../Municipal/terminalMunicipal';
import SeccionRevisionFisicaPedido from '../SeccionRevisionFisicaPedido';
import {
    etiquetaAlmacen,
    etiquetaEstatusPedido,
    etiquetaSucursal,
    formatearFechaHoraAuditoria,
    formatearFechaNegocio,
    formatearMoneda,
    pedidoRequiereLogistica,
    respondeComoTienda,
} from '../pedidosBmaStyles';
import DetallePedidoDato, { DetallePedidoDatos, DetallePedidoResumen } from './DetallePedidoDato';
import DetallePedidoSeccion from './DetallePedidoSeccion';
import { etiquetaRespuestaDetalle, textoPagosPedido } from './resolverModalidadDetalle';

const LEYENDA = { PAGADO: 'Pagado', POR_COBRAR: 'Por cobrar' };
const COSTO = { conocido: 'Conocido', diferido: 'Diferido' };

export default function CuerpoDetalleMunicipal({ pedido, puedeAtender, onVerDoc, onVerGaleria }) {
    const respondeTienda = respondeComoTienda(pedido);
    const expediente = pedido.solicitud_preparacion?.expediente || {};
    const finanzas = pedido.solicitud_preparacion?.finanzas_municipales || {};
    const resguardo = pedido.solicitud_preparacion?.resguardo_envio || {};
    const terminal = nombreTerminalMunicipal(pedido);
    const opcionesEstatus = {
        pedido,
        esResguardo: pedido.es_resguardo,
        requiereLogistica: pedidoRequiereLogistica(pedido),
    };
    const sucursal = pedido.sucursal_destino || (pedido.sucursal_destino_id ? {
        id: pedido.sucursal_destino_id,
        nombre: pedido.sucursal_destino_nombre,
        codigo: pedido.sucursal_destino_codigo,
    } : null);
    const sinRevision = !pedido.estado_fisico_general
        && !(pedido.revisiones_producto || pedido.revisionesProducto || []).length
        && !pedido.pesaje_respondido_at;

    return (
        <>
            <section id="detalle-respuesta" tabIndex={-1} className="space-y-3">
                <DetallePedidoResumen hechos={[
                    { icon: ClipboardCheck, label: respondeTienda ? 'Respuesta de Tienda' : 'Respuesta CEDIS', value: etiquetaRespuestaDetalle(pedido) },
                    { icon: MapPin, label: 'Terminal', value: terminal },
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
            <DetallePedidoSeccion id="detalle-datos" icon={ClipboardCheck} titulo="Datos del envío municipal">
                <DetallePedidoDatos>
                    <DetallePedidoDato icon={Hash} label="Número de pedido" value={pedido.folio_remision} obligatorio />
                    <DetallePedidoDato icon={Receipt} label="Número de remisión" value={pedido.numero_remision} />
                    <DetallePedidoDato icon={UserRound} label="Cliente" value={[pedido.cliente?.numero_cliente, pedido.cliente?.nombre].filter(Boolean).join(' — ')} obligatorio />
                    <DetallePedidoDato icon={Calendar} label="Fecha pedido" value={formatearFechaNegocio(pedido.fecha)} obligatorio />
                    <DetallePedidoDato icon={Calendar} label="Registrado" value={formatearFechaHoraAuditoria(pedido.created_at)} />
                    <DetallePedidoDato icon={ClipboardCheck} label="Estado" value={etiquetaEstatusPedido(pedido.estatus, opcionesEstatus)} obligatorio />
                </DetallePedidoDatos>
            </DetallePedidoSeccion>
            <DetallePedidoSeccion icon={MapPin} titulo="Destino">
                <DetallePedidoDatos>
                    <DetallePedidoDato icon={MapPin} label="Terminal destino" value={terminal} obligatorio />
                    <DetallePedidoDato icon={UserRound} label="Consignatario" value={expediente.nombre} />
                    <DetallePedidoDato icon={Phone} label="Teléfono" value={expediente.telefono} />
                    <DetallePedidoDato icon={Building2} label="Almacén" value={pedido.almacen ? etiquetaAlmacen(pedido.almacen) : null} />
                    <DetallePedidoDato icon={Store} label="Sucursal destino" value={sucursal ? etiquetaSucursal(sucursal) : null} />
                </DetallePedidoDatos>
            </DetallePedidoSeccion>
            <DetallePedidoSeccion icon={Wallet} titulo="Transporte y cobro">
                <DetallePedidoDatos>
                    <DetallePedidoDato icon={CreditCard} label="Pagos / bancos" value={textoPagosPedido(pedido)} />
                    <DetallePedidoDato icon={Wallet} label="Costo de transporte" value={COSTO[finanzas.costo_transporte]} />
                    <DetallePedidoDato icon={Wallet} label="Costo de envío" value={pedido.costo_envio != null && pedido.costo_envio !== '' ? formatearMoneda(pedido.costo_envio) : null} />
                    <DetallePedidoDato icon={Receipt} label="Condición de terminal" value={LEYENDA[finanzas.leyenda_terminal]} />
                    <DetallePedidoDato icon={ClipboardCheck} label="Resguardo de envío" value={resguardo.activo ? (resguardo.motivo || 'Activo') : null} />
                    <DetallePedidoDato icon={Wallet} label="Total a cobrar" value={formatearMoneda(pedido.total_a_cobrar)} obligatorio />
                </DetallePedidoDatos>
            </DetallePedidoSeccion>
        </>
    );
}
