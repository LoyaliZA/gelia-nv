import React from 'react';
import { MapPin, Store, Truck, User, X } from 'lucide-react';
import EncabezadoFolioPedido from '../EncabezadoFolioPedido';
import {
    badgeEstatusPedido,
    badgeResguardoApartado,
    badgeGuiaLista,
    badgeEstadoFisico,
    badgesRetrasoSla,
    tieneGuiaLista,
    pedidoRequiereLogistica,
    respondeComoTienda,
} from '../pedidosBmaStyles';
import { META_MODALIDAD_DETALLE } from './resolverModalidadDetalle';

const ICONO_MODALIDAD = {
    tienda: Store,
    municipal: MapPin,
    nacional: Truck,
    legado: Truck,
};

export default function DetallePedidoCabecera({ pedido, modalidad, onClose }) {
    const meta = META_MODALIDAD_DETALLE[modalidad] || META_MODALIDAD_DETALLE.legado;
    const IconoModalidad = ICONO_MODALIDAD[modalidad] || Truck;
    const opcionesEstatus = {
        pedido,
        esResguardo: pedido.es_resguardo,
        requiereLogistica: pedidoRequiereLogistica(pedido),
    };
    const badge = badgeEstatusPedido(pedido.estatus, opcionesEstatus);
    const badgeGuia = tieneGuiaLista(pedido) ? badgeGuiaLista() : null;
    const badgeApartado = pedido.resguardo_apartado_at ? badgeResguardoApartado() : null;
    const badgeFisico = pedido.estado_fisico_general ? badgeEstadoFisico(pedido.estado_fisico_general) : null;
    const badgesSla = badgesRetrasoSla(pedido);
    const respondeTienda = respondeComoTienda(pedido);
    const cliente = [pedido.cliente?.nombre || 'Sin cliente', pedido.cliente?.numero_cliente ? `Cliente ${pedido.cliente.numero_cliente}` : null]
        .filter(Boolean)
        .join(' · ');

    return (
        <header className="gelia-pedidos-detalle-cabecera border-b theme-border">
            <div className="gelia-pedidos-detalle-identidad min-w-0">
                <div className="flex flex-wrap items-center gap-2 mb-1">
                    <span className="gelia-pedidos-modalidad">
                        <IconoModalidad className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                        {meta.label}
                    </span>
                    <h2 id="detalle-pedido-titulo" className="text-xs font-medium theme-text-muted m-0">Detalle del pedido</h2>
                </div>
                <EncabezadoFolioPedido pedido={pedido} size="lg" internoCompacto />
            </div>
            <div className="gelia-pedidos-detalle-cliente min-w-0">
                <p className="text-sm font-medium theme-text-main m-0 break-words">{cliente}</p>
                {pedido.vendedor?.name && (
                    <p className="text-xs theme-text-muted mt-1 m-0 flex items-center gap-1.5">
                        <User className="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
                        Capturado por: {pedido.vendedor.name}
                    </p>
                )}
            </div>
            <div className="gelia-pedidos-detalle-estados flex flex-wrap items-center gap-2 min-w-0">
                <span className={badge.className} style={badge.style}>{badge.label}</span>
                {badgeGuia && <span className={badgeGuia.className}>{badgeGuia.label}</span>}
                {badgeApartado && <span className={badgeApartado.className} style={badgeApartado.style}>{badgeApartado.label}</span>}
                {badgeFisico && <span className={badgeFisico.className} style={badgeFisico.style}>{badgeFisico.label}</span>}
                {badgesSla.map((b) => (
                    <span key={b.label} className={b.className} style={b.style}>{b.label}</span>
                ))}
                {pedido.tiene_observaciones_fisicas && (
                    <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold gelia-estado-vivo gelia-estado-vivo--aviso">
                        {respondeTienda ? 'Observaciones de Tienda' : 'Observaciones CEDIS'}
                    </span>
                )}
            </div>
            <button
                type="button"
                onClick={onClose}
                className="gelia-pedidos-detalle-cerrar p-2 rounded-full theme-text-muted hover:theme-text-main outline-none shrink-0 inline-flex items-center justify-center"
                aria-label="Cerrar detalle"
            >
                <X className="w-5 h-5" aria-hidden="true" />
            </button>
            {pedido.motivo_rechazo && (
                <p className="gelia-pedidos-detalle-rechazo text-sm theme-text-peligro font-semibold m-0">Motivo rechazo: {pedido.motivo_rechazo}</p>
            )}
        </header>
    );
}
