import React from 'react';
import { DollarSign, Receipt, Wallet, ShieldCheck } from 'lucide-react';
import { cardMetricaClass, cardReportePagos, fmtContador, fmtMxn, RADIUS_DESGLOSE, RADIUS_METRICA, ACCENT_MARCA, SEM_TEXTO } from './pagosPedidosStyles';

const DESTACADAS = [
    { key: 'pedidos_validados', label: 'Pedidos incluidos', icon: Receipt, money: false },
    { key: 'total_remisiones', label: 'Total de remisiones', icon: DollarSign, money: true },
    { key: 'pagos_validos', label: 'Total cubierto con pagos', icon: Wallet, money: true },
    { key: 'saf_aplicado', label: 'SAF aplicado', icon: ShieldCheck, money: true },
];

/** @type {Array<{key: string, label: string, money?: boolean, unit?: string}>} */
const DESGLOSE = [
    { key: 'monto_venta', label: 'Mercancía', money: true },
    { key: 'monto_envio', label: 'Envío', money: true },
    { key: 'monto_seguro', label: 'Seguro', money: true },
    { key: 'saf_aplicado', label: 'SAF aplicado', money: true },
    { key: 'pedidos_cubiertos', label: 'Pedidos cubiertos', unit: 'pedidos' },
    { key: 'pedidos_cubiertos_con_saf', label: 'Cubiertos con SAF', unit: 'pedidos' },
    { key: 'pedidos_parciales', label: 'Parcialmente cubiertos', unit: 'pedidos' },
    { key: 'pedidos_con_excedente', label: 'Con excedente', unit: 'pedidos' },
    { key: 'pedidos_observaciones', label: 'Con observaciones', unit: 'pedidos' },
    { key: 'pendientes_admin', label: 'Pendientes revisión admin', unit: 'pedidos' },
    { key: 'cantidad_vouchers', label: 'Vouchers', unit: 'vouchers' },
];

function Tarjeta({ label, value, icon: Icon, money, cargando, accent }) {
    return (
        <div className={cardReportePagos(cardMetricaClass, RADIUS_METRICA)}>
            <div
                className="p-3 rounded-xl theme-element border theme-border shrink-0"
                style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 10%, transparent)' }}
            >
                <Icon className="w-6 h-6" style={{ color: accent || ACCENT_MARCA }} />
            </div>
            <div className="min-w-0 flex flex-col justify-center gap-1.5 py-0.5">
                <p className="text-[11px] font-semibold uppercase tracking-wide theme-text-muted m-0 leading-snug">
                    {label}
                </p>
                <p className="text-2xl md:text-[28px] font-bold theme-text-main m-0 tabular-nums leading-tight">
                    {cargando ? '…' : (money ? fmtMxn(value) : (value ?? 0).toLocaleString('es-MX'))}
                </p>
            </div>
        </div>
    );
}

function valorDesglose(item, metricas, cargando) {
    if (cargando) return '…';
    const raw = metricas?.[item.key];
    if (item.money) return fmtMxn(raw);
    if (item.unit) return fmtContador(raw, item.unit);
    return raw ?? '—';
}

function DesglosePeriodo({ metricas, cargando }) {
    return (
        <div className={cardReportePagos('px-4 py-2.5 md:px-5', RADIUS_DESGLOSE)}>
            <h3 className="text-sm font-semibold theme-text-main m-0 mb-1.5 leading-tight">
                Desglose del periodo
            </h3>
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-0 items-stretch">
                {DESGLOSE.map((item, index) => (
                    <div
                        key={item.key}
                        className={[
                            'flex flex-col justify-center py-1.5 px-3 min-w-0',
                            index % 2 === 1 ? 'border-l border-[color-mix(in_srgb,var(--theme-border)_65%,transparent)]' : '',
                            index >= 2 ? 'max-sm:border-t max-sm:border-[color-mix(in_srgb,var(--theme-border)_65%,transparent)]' : '',
                            index > 0 ? 'sm:border-l sm:border-[color-mix(in_srgb,var(--theme-border)_65%,transparent)]' : '',
                        ].join(' ')}
                    >
                        <p className="text-[11px] md:text-xs font-medium theme-text-muted m-0 leading-snug truncate mb-1">
                            {item.label}
                        </p>
                        <p
                            className={[
                                'text-base md:text-lg font-semibold m-0 tabular-nums leading-tight truncate',
                                ['pedidos_observaciones', 'pedidos_parciales', 'pedidos_con_excedente'].includes(item.key)
                                    && !cargando && Number(metricas?.[item.key] ?? 0) > 0
                                    ? SEM_TEXTO.advertencia
                                    : 'theme-text-main',
                            ].join(' ')}
                        >
                            {valorDesglose(item, metricas, cargando)}
                        </p>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function MetricasPagosPedidos({ metricas, cargando }) {
    return (
        <div className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 md:gap-6 min-w-0">
                {DESTACADAS.map(({ key, ...tarjeta }) => (
                    <Tarjeta key={key} {...tarjeta} value={metricas?.[key]} cargando={cargando} />
                ))}
            </div>
            <DesglosePeriodo metricas={metricas} cargando={cargando} />
        </div>
    );
}
