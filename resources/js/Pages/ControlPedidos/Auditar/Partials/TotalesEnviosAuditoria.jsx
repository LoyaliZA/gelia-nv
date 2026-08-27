import React from 'react';
import {
    etiquetaCostoEnvio,
    formatearMoneda,
} from '../../Partials/pedidosBmaStyles';

const Campo = ({ label, value }) => (
    <div>
        <p className="text-[9px] font-black uppercase theme-text-muted m-0">{label}</p>
        <p className="text-sm font-bold theme-text-main m-0 mt-0.5">{value ?? '—'}</p>
    </div>
);

/** Consolidado del pedido (no repite cada tarjeta de envío). */
export default function TotalesEnviosAuditoria({ pedido, cajasActivas = [] }) {
    const n = pedido?.numero_cajas ?? cajasActivas.length ?? 0;
    const titulo = n === 1 ? 'Totales del pedido (1 envío)' : `Totales de envíos (pedido completo)`;
    const ayuda = n <= 1
        ? 'Resumen consolidado del envío. No repite la tarjeta anterior.'
        : `Resumen consolidado de ${n} envíos. No repite cada tarjeta; es la información total del pedido.`;

    return (
        <div className="mt-4 pt-4 border-t theme-border space-y-3">
            <div>
                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">{titulo}</p>
                <p className="text-[10px] font-bold theme-text-muted m-0 mt-1">{ayuda}</p>
            </div>
            <div className="grid grid-cols-2 gap-4">
                <Campo label="Núm. envíos" value={n || null} />
                <Campo label="Peso real total (kg)" value={pedido?.peso_real_kg != null ? `${pedido.peso_real_kg}` : null} />
                <Campo label="Peso volumétrico total (kg)" value={pedido?.peso_volumetrico_kg != null ? `${pedido.peso_volumetrico_kg}` : null} />
                <Campo label="Peso cobrado guía total (kg)" value={pedido?.peso_cobrado_guia_kg != null ? `${pedido.peso_cobrado_guia_kg}` : null} />
                <Campo label={etiquetaCostoEnvio(pedido?.paqueteria)} value={formatearMoneda(pedido?.costo_envio)} />
                <Campo
                    label="Costo del seguro"
                    value={pedido?.aplica_seguro ? formatearMoneda(pedido.costo_seguro) : formatearMoneda(0)}
                />
                <Campo label="Total mercancía" value={formatearMoneda(pedido?.total_mercancia)} />
            </div>
        </div>
    );
}
