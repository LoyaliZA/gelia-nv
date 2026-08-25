import React from 'react';
import {
    etiquetaAlmacen,
    LABEL_NOTA_COMPRA_CAMPO,
    formatearFechaNegocio,
    formatearFechaHoraAuditoria,
} from '../../Partials/pedidosBmaStyles';

const Campo = ({ label, value }) => (
    <div>
        <p className="text-[9px] font-black uppercase theme-text-muted m-0">{label}</p>
        <p className="text-sm font-bold theme-text-main m-0 mt-0.5">{value ?? '—'}</p>
    </div>
);

/** Datos mínimos del pedido (sin pagos, SAF ni n° envíos duplicados). */
export default function DatosGeneralesAuditoria({ pedido }) {
    return (
        <div className="grid grid-cols-2 gap-4">
            <Campo label="N° Cliente" value={pedido.cliente?.numero_cliente} />
            <Campo label="Nombre" value={pedido.cliente?.nombre} />
            <Campo label="Folio WizeRP" value={pedido.folio_remision} />
            <Campo label="Folio interno" value={pedido.folio} />
            <Campo label="Fecha pedido" value={formatearFechaNegocio(pedido.fecha)} />
            <Campo label="Registrado" value={formatearFechaHoraAuditoria(pedido.created_at)} />
            <Campo label="Almacén" value={etiquetaAlmacen(pedido.almacen)} />
            <Campo label="Tipo de pedido" value={pedido.origen?.nombre} />
            <Campo label={LABEL_NOTA_COMPRA_CAMPO} value={pedido.anexar_remision ? 'Sí' : 'No'} />
            <Campo label="Capturado por" value={pedido.vendedor?.name} />
        </div>
    );
}
