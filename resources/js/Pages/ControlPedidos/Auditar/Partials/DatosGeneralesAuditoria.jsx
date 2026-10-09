import React from 'react';
import {
    etiquetaAlmacen,
    LABEL_NOTA_COMPRA_CAMPO,
    formatearFechaNegocio,
    formatearFechaHoraAuditoria,
} from '../../Partials/pedidosBmaStyles';

const Campo = ({ label, value }) => (
    <div>
        <dt className="text-xs font-medium theme-text-muted m-0">{label}</dt>
        <dd className="text-sm font-semibold theme-text-main m-0 mt-0.5">{value ?? '—'}</dd>
    </div>
);

/** Cliente + datos operativos del pedido (sección 1 del modal). */
export default function DatosGeneralesAuditoria({ pedido }) {
    return (
        <dl className="grid grid-cols-2 gap-4">
            <Campo label="N° Cliente" value={pedido.cliente?.numero_cliente} />
            <Campo label="Nombre" value={pedido.cliente?.nombre} />
            <Campo label="Número de pedido" value={pedido.folio_remision} />
            <Campo label="Número de remisión" value={pedido.numero_remision} />
            <Campo label="Folio interno" value={pedido.folio} />
            <Campo label="Fecha pedido" value={formatearFechaNegocio(pedido.fecha)} />
            <Campo label="Registrado" value={formatearFechaHoraAuditoria(pedido.created_at)} />
            <Campo label="Almacén" value={etiquetaAlmacen(pedido.almacen)} />
            <Campo label="Tipo de pedido" value={pedido.origen?.nombre} />
            <Campo label={LABEL_NOTA_COMPRA_CAMPO} value={pedido.anexar_remision ? 'Sí' : 'No'} />
            <Campo label="Capturado por" value={pedido.vendedor?.name} />
        </dl>
    );
}
