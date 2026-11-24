import React from 'react';
import { Building2, ClipboardCheck, CreditCard, Receipt, Store, Wallet } from 'lucide-react';
import ConsultaSolicitudTienda from '../ConsultaSolicitudTienda';
import { formatearMoneda, respondeComoTienda } from '../pedidosBmaStyles';
import DetallePedidoDato, { DetallePedidoDatos, DetallePedidoResumen } from './DetallePedidoDato';
import DetallePedidoSeccion from './DetallePedidoSeccion';
import { etiquetaRespuestaDetalle } from './resolverModalidadDetalle';

const PAGO = { apartado: 'Al recoger', transferencia: 'Transferencia', credito: 'Crédito' };

export default function CuerpoDetalleTienda({ pedido }) {
    const respondeTienda = respondeComoTienda(pedido);
    const consultaCerrada = Boolean(pedido.consulta_cerrada || pedido.consulta_cerrada_at);
    const apartado = pedido.solicitud_preparacion?.cobertura_tienda?.tipo === 'apartado';

    return (
        <>
            <section id="detalle-respuesta" tabIndex={-1} className="space-y-3">
                <DetallePedidoResumen hechos={[
                    { icon: ClipboardCheck, label: respondeTienda ? 'Respuesta de Tienda' : 'Respuesta CEDIS', value: etiquetaRespuestaDetalle(pedido) },
                    { icon: Store, label: 'Consulta', value: consultaCerrada ? 'Cerrada' : 'Abierta' },
                    consultaCerrada && (apartado
                        ? { icon: Wallet, label: 'Pago al recoger', value: 'Se paga en tienda' }
                        : { icon: Wallet, label: 'Importe', value: formatearMoneda(pedido.total_a_cobrar) }),
                ]} />
                <ConsultaSolicitudTienda pedido={pedido} />
            </section>
            <DetallePedidoSeccion id="detalle-datos" icon={Store} titulo="Recolección en tienda">
                <DetallePedidoDatos>
                    <DetallePedidoDato icon={ClipboardCheck} label="Consulta" value={respondeTienda ? 'Tienda' : 'CEDIS'} obligatorio />
                    <DetallePedidoDato icon={Store} label="Sucursal de destino" value={pedido.sucursal_destino?.nombre || pedido.sucursal_destino_nombre || 'Por confirmar'} obligatorio />
                    <DetallePedidoDato icon={Building2} label="Procedencia" value={pedido.almacen?.nombre} />
                    <DetallePedidoDato icon={CreditCard} label="Pago" value={PAGO[pedido.solicitud_preparacion?.cobertura_tienda?.tipo] || 'Por definir'} obligatorio />
                    <DetallePedidoDato icon={Receipt} label="Remisión" value={pedido.numero_remision} />
                    {consultaCerrada && <DetallePedidoDato icon={Wallet} label="Mercancía" value={formatearMoneda(pedido.total_mercancia)} obligatorio />}
                </DetallePedidoDatos>
            </DetallePedidoSeccion>
        </>
    );
}
