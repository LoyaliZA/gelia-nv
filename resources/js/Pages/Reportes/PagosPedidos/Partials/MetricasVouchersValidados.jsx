import React from 'react';
import { Banknote, Building2, Copy, Landmark, Receipt, Wallet } from 'lucide-react';
import { cardMetricaClass, cardReportePagos, fmtContador, fmtMxn, RADIUS_METRICA, ACCENT_MARCA, SEM_TEXTO } from './pagosPedidosStyles';

const DESTACADAS = [
    { key: 'total_ingreso_bancario', label: 'Total validado para bancos', icon: Banknote, money: true },
    { key: 'vouchers_validados', label: 'Vouchers validados', icon: Receipt, money: false },
    { key: 'pedidos_relacionados', label: 'Pedidos relacionados', icon: Wallet, money: false },
    { key: 'bancos_involucrados', label: 'Bancos involucrados', icon: Building2, money: false },
];

const CONTEXTUAL = [
    { key: 'reportados_posteriormente', label: 'Reportados posteriormente', unit: 'vouchers' },
    { key: 'con_observaciones', label: 'Con observaciones', unit: 'vouchers' },
    { key: 'posibles_duplicados', label: 'Posibles duplicados', unit: 'vouchers' },
    { key: 'remisiones_con_saf', label: 'Remisiones con SAF', unit: 'pedidos' },
    { key: 'total_saf_relacionado', label: 'Total SAF relacionado', money: true },
    { key: 'pendientes_visibles', label: 'Pendientes visibles', money: true },
    { key: 'rechazados_visibles', label: 'Rechazados visibles', money: true },
];

function Tarjeta({ label, value, icon: Icon, money, cargando }) {
    return (
        <div className={cardReportePagos(cardMetricaClass, RADIUS_METRICA)}>
            <div
                className="p-3 rounded-xl theme-element border theme-border shrink-0"
                style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 10%, transparent)' }}
            >
                <Icon className="w-6 h-6" style={{ color: ACCENT_MARCA }} />
            </div>
            <div className="min-w-0 flex flex-col justify-center gap-1.5 py-0.5">
                <p className="text-[11px] font-semibold uppercase tracking-wide theme-text-muted m-0 leading-snug">{label}</p>
                <p className="text-2xl md:text-[28px] font-bold theme-text-main m-0 tabular-nums leading-tight">
                    {cargando ? '…' : (money ? fmtMxn(value) : (value ?? 0).toLocaleString('es-MX'))}
                </p>
            </div>
        </div>
    );
}

function valorItem(item, metricas, cargando) {
    if (cargando) return '…';
    const raw = metricas?.[item.key];
    if (item.money) return fmtMxn(raw);
    if (item.unit) return fmtContador(raw, item.unit);
    return raw ?? '—';
}

export default function MetricasVouchersValidados({ metricas, cargando }) {
    const porBanco = metricas?.por_banco ?? [];
    const porForma = metricas?.por_forma_pago ?? [];

    return (
        <div className="space-y-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 md:gap-6 min-w-0">
                {DESTACADAS.map(({ key, ...tarjeta }) => (
                    <Tarjeta key={key} {...tarjeta} value={metricas?.[key]} cargando={cargando} />
                ))}
            </div>

            <div className={cardReportePagos('px-4 py-3 md:px-5', '!rounded-[18px]')}>
                <h3 className="text-sm font-semibold theme-text-main m-0 mb-2">Indicadores del periodo filtrado</h3>
                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                    {CONTEXTUAL.map((item) => (
                        <div key={item.key} className="min-w-0">
                            <p className="text-[11px] font-medium theme-text-muted m-0 mb-0.5 truncate">{item.label}</p>
                            <p className={`text-base font-semibold tabular-nums m-0 ${item.key.includes('duplicado') || item.key.includes('observaciones') ? SEM_TEXTO.advertencia : 'theme-text-main'}`}>
                                {valorItem(item, metricas, cargando)}
                            </p>
                        </div>
                    ))}
                </div>
                <p className="text-[11px] theme-text-muted mt-3 m-0">
                    El SAF es contextual y no se suma al total validado para bancos.
                </p>
            </div>

            {(porBanco.length > 0 || porForma.length > 0) && !cargando && (
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    {porBanco.length > 0 && (
                        <div className={cardReportePagos('p-4', '!rounded-[18px]')}>
                            <h4 className="text-xs font-semibold uppercase tracking-wide theme-text-muted m-0 mb-2 flex items-center gap-1.5">
                                <Landmark className="w-3.5 h-3.5" /> Por banco
                            </h4>
                            <ul className="space-y-1.5 m-0 p-0 list-none">
                                {porBanco.map((b) => (
                                    <li key={b.banco_id ?? b.banco} className="flex justify-between gap-2 text-sm">
                                        <span className="theme-text-main truncate">{b.banco}</span>
                                        <span className="font-semibold tabular-nums shrink-0">{fmtMxn(b.total)}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                    {porForma.length > 0 && (
                        <div className={cardReportePagos('p-4', '!rounded-[18px]')}>
                            <h4 className="text-xs font-semibold uppercase tracking-wide theme-text-muted m-0 mb-2 flex items-center gap-1.5">
                                <Copy className="w-3.5 h-3.5" /> Por forma de pago
                            </h4>
                            <ul className="space-y-1.5 m-0 p-0 list-none">
                                {porForma.map((f) => (
                                    <li key={f.forma} className="flex justify-between gap-2 text-sm">
                                        <span className="theme-text-main truncate">{f.label}</span>
                                        <span className="font-semibold tabular-nums shrink-0">{fmtMxn(f.total)}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
