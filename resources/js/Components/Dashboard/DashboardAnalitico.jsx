import React from 'react';
import { Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    CalendarDays,
    ClipboardList,
    CreditCard,
    FileSignature,
    Minus,
    TrendingUp,
} from 'lucide-react';
import { geliaCardClass, GELIA_BTN_OUTLINE, GELIA_ESTADO_VIVO_TONO } from '../../utils/geliaTheme';
import { formatoMoneda } from '../../utils/formatoMoneda';
import { dashboardTonoClass, DASHBOARD_WIDGET_TONO } from './dashboardModulesCatalog';
import DashboardPeriodFilter from './DashboardPeriodFilter';
import GraficaDashboard from './GraficaDashboard';
import DashboardQueueRow from './DashboardQueueRow';
import DashboardWidgetStatus from './DashboardWidgetStatus';
import { estiloEstadoSolicitud } from './dashboardEstadoSolicitud';

const FASES_PEDIDO = [
    { key: 'pendiente_auxiliar', label: 'Auxiliar', tono: 'aviso' },
    { key: 'en_cedis', label: 'CEDIS', tono: 'info' },
    { key: 'borradores', label: 'Borradores', tono: 'neutro' },
    { key: 'enviados', label: 'Enviados', tono: 'exito' },
    { key: 'rechazadas', label: 'Rechazadas', tono: 'error' },
];

const RANGOS_CARTERA = [
    { key: 'rango_1_30', label: '1–30 días' },
    { key: 'rango_31_60', label: '31–60 días' },
    { key: 'rango_61_90', label: '61–90 días' },
    { key: 'rango_91_120', label: '91–120 días' },
    { key: 'rango_120_mas', label: 'Más de 120' },
];

function Delta({ valor }) {
    if (valor == null) {
        return <span className="dashboard-kpi-delta theme-text-muted">Sin periodo comparable</span>;
    }
    if (valor === 0) {
        return (
            <span className="dashboard-kpi-delta theme-text-muted inline-flex items-center gap-1">
                <Minus className="w-3.5 h-3.5" aria-hidden />
                Igual al periodo anterior
            </span>
        );
    }
    const sube = valor > 0;
    const Icono = sube ? ArrowUpRight : ArrowDownRight;
    return (
        <span className={`dashboard-kpi-delta inline-flex items-center gap-1 ${sube ? 'theme-text-exito' : 'theme-text-muted'}`}>
            <Icono className="w-3.5 h-3.5" aria-hidden />
            <span className="sr-only">{sube ? 'Sube' : 'Baja'} </span>
            {Math.abs(valor).toLocaleString('es-MX')}% vs periodo anterior
        </span>
    );
}

function KpiCard({ tono, icon: Icon, label, hint, value, extra }) {
    return (
        <div className={`${geliaCardClass('dashboard-kpi-card p-4 md:p-5 min-h-[4.5rem] flex flex-col justify-center')} ${dashboardTonoClass(tono)}`}>
            <span className="dashboard-module-card__bar" aria-hidden />
            <p className="dashboard-kpi-card__label m-0">
                <span className="dashboard-kpi-card__icon" aria-hidden>
                    {Icon && <Icon className="w-4 h-4" />}
                </span>
                <span className="text-xs font-medium theme-text-muted">{label}</span>
            </p>
            <p className="text-2xl md:text-[1.75rem] font-semibold theme-text-main m-0 leading-none tabular-nums mt-2 break-words">
                {value}
            </p>
            {hint && <p className="text-[11px] theme-text-muted m-0 mt-2">{hint}</p>}
            {extra}
        </div>
    );
}

function ChartCard({ tono, title, hint, icon: Icon, children, footer, className = '' }) {
    return (
        <section className={`${geliaCardClass(`dashboard-chart-card p-4 md:p-5 min-w-0 ${className}`)} ${dashboardTonoClass(tono)}`}>
            <span className="dashboard-module-card__bar" aria-hidden />
            <header className="flex flex-wrap items-start justify-between gap-2 mb-3">
                <h2 className="dashboard-chart-card__title theme-text-main m-0 inline-flex items-center gap-2 min-w-0">
                    <span className="dashboard-kpi-card__icon" aria-hidden>
                        {Icon && <Icon className="w-4 h-4" />}
                    </span>
                    {title}
                </h2>
                {hint && <p className="text-[11px] theme-text-muted m-0">{hint}</p>}
            </header>
            {children}
            {footer}
        </section>
    );
}

export default function DashboardAnalitico({
    filtroPeriodo,
    estadisticas = {},
    metricasCredibox = {},
    metricasPedidos = {},
    metricasSolicitudes = {},
    ultimasSolicitudes = [],
    ultimasOperativas = [],
    alertasActivos = [],
    onPeriodoChange,
    refreshing = false,
}) {
    const cotizado = estadisticas.cotizado;
    const actividad = estadisticas.actividad;
    const serie = cotizado?.serie || actividad?.serie || [];
    const labels = serie.map((punto) => punto.etiqueta);
    const hayCotizado = Boolean(cotizado);
    const hayCredibox = Object.prototype.hasOwnProperty.call(metricasCredibox, 'saldo_vencido');
    const hayPedidos = Object.prototype.hasOwnProperty.call(metricasPedidos, 'todas');
    const cartera = metricasCredibox.cartera || {};
    const topVencidos = metricasCredibox.top_vencidos || [];
    const desglose = cotizado?.desglose?.items || [];
    const dimensionDesglose = cotizado?.desglose?.dimension === 'vendedor' ? 'Por responsable' : 'Por tipo de solicitud';
    const maxDesglose = Math.max(1, ...desglose.map((item) => Number(item.monto) || 0));
    const colaPedidos = (metricasPedidos.pendiente_auxiliar ?? 0) + (metricasPedidos.en_cedis ?? 0);

    const kpis = [];
    if (Object.prototype.hasOwnProperty.call(estadisticas, 'mis_activas')) {
        kpis.push({
            key: 'mis_activas',
            tono: 'amber',
            icon: FileSignature,
            label: 'Mis solicitudes abiertas',
            hint: 'Cartera propia',
            value: Number(estadisticas.mis_activas || 0).toLocaleString('es-MX'),
        });
    }
    if (hayCotizado) {
        kpis.push({
            key: 'creadas',
            tono: 'blue',
            icon: CalendarDays,
            label: 'Solicitudes creadas',
            hint: filtroPeriodo?.etiqueta,
            value: Number(actividad?.creadas || 0).toLocaleString('es-MX'),
        });
        kpis.push({
            key: 'cotizado',
            tono: DASHBOARD_WIDGET_TONO.solicitudes,
            icon: TrendingUp,
            label: 'Monto cotizado',
            hint: filtroPeriodo?.etiqueta,
            value: formatoMoneda(cotizado.total),
            extra: <p className="m-0 mt-2 text-[11px]"><Delta valor={cotizado.delta_pct} /></p>,
        });
    }
    if (hayCredibox) {
        const vencido = Number(metricasCredibox.saldo_vencido || 0);
        kpis.push({
            key: 'vencido',
            tono: DASHBOARD_WIDGET_TONO.credibox,
            icon: CreditCard,
            label: 'Saldo vencido',
            hint: 'Cartera viva · Credibox',
            value: formatoMoneda(vencido),
            extra: vencido > 0 ? (
                <p className="m-0 mt-2">
                    <DashboardWidgetStatus tono="error" icon={AlertTriangle}>Requiere seguimiento</DashboardWidgetStatus>
                </p>
            ) : (
                <p className="m-0 mt-2 text-[11px] theme-text-muted">Al día</p>
            ),
        });
    }
    if (hayPedidos) {
        kpis.push({
            key: 'pedidos',
            tono: DASHBOARD_WIDGET_TONO.pedidos,
            icon: ClipboardList,
            label: 'Pedidos en cola',
            hint: 'Auxiliar + CEDIS',
            value: Number(colaPedidos).toLocaleString('es-MX'),
        });
    }

    return (
        <div
            className="dashboard-analitico space-y-4 md:space-y-5 dashboard-page-reveal"
            aria-busy={refreshing}
        >
            <div className="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-3">
                <DashboardPeriodFilter
                    filtro={filtroPeriodo}
                    onChange={onPeriodoChange}
                    disabled={refreshing}
                />
                {filtroPeriodo?.etiqueta && (
                    <p className="text-xs theme-text-muted m-0">
                        Periodo: <span className="theme-text-main capitalize">{filtroPeriodo.etiqueta}</span>
                        {refreshing ? ' · Actualizando…' : ''}
                    </p>
                )}
            </div>

            {kpis.length > 0 && (
                <section
                    aria-label="Indicadores"
                    className="dashboard-kpi-grid"
                >
                    {kpis.map((kpi) => (
                        <KpiCard key={kpi.key} {...kpi} />
                    ))}
                </section>
            )}

            {(hayCotizado || hayPedidos) && (
                <div className={`dashboard-chart-row ${hayCotizado ? '' : 'dashboard-chart-row--solo'}`}>
                    {hayCotizado && (
                        <ChartCard
                            tono={DASHBOARD_WIDGET_TONO.solicitudes}
                            title="Montos cotizados"
                            hint={cotizado.periodo_anterior ? `Comparado con ${cotizado.periodo_anterior}` : filtroPeriodo?.etiqueta}
                            icon={TrendingUp}
                        >
                            <GraficaDashboard
                                type="bar"
                                tono={DASHBOARD_WIDGET_TONO.solicitudes}
                                labels={labels}
                                ariaLabel="Monto cotizado por tramo"
                                emptyLabel="Sin montos registrados en este periodo."
                                datasets={[
                                    { label: 'Periodo actual', data: serie.map((p) => p.monto), rol: 'principal' },
                                    { label: 'Periodo anterior', data: serie.map((p) => p.monto_anterior), rol: 'comparacion' },
                                ]}
                            />
                            {desglose.length > 0 && (
                                <ul className="dashboard-desglose m-0 mt-4 p-0 list-none" aria-label={dimensionDesglose}>
                                    <li className="text-[11px] theme-text-muted mb-2">{dimensionDesglose}</li>
                                    {desglose.map((item) => (
                                        <li key={`${item.id}-${item.nombre}`} className="dashboard-desglose__row">
                                            <div className="flex items-center justify-between gap-2 text-xs">
                                                <span className="theme-text-main truncate">{item.nombre}</span>
                                                <span className="tabular-nums theme-text-main">{formatoMoneda(item.monto)}</span>
                                            </div>
                                            <span
                                                className="dashboard-desglose__bar"
                                                style={{ width: `${Math.max(4, (Number(item.monto) / maxDesglose) * 100)}%` }}
                                                aria-hidden
                                            />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </ChartCard>
                    )}

                    <div className="dashboard-chart-side">
                        {hayCotizado && (
                            <ChartCard
                                tono="blue"
                                title="Actividad"
                                hint="Creadas y cerradas"
                                icon={CalendarDays}
                            >
                                <GraficaDashboard
                                    type="bar"
                                    tono="blue"
                                    labels={labels}
                                    heightClass="h-40"
                                    ariaLabel="Solicitudes creadas y cerradas"
                                    emptyLabel="Sin movimientos en este periodo."
                                    datasets={[
                                        { label: 'Creadas', data: serie.map((p) => p.creadas), rol: 'principal' },
                                        { label: 'Cerradas', data: serie.map((p) => p.cerradas), rol: 'comparacion' },
                                    ]}
                                />
                                <p className="text-[11px] theme-text-muted m-0 mt-2">
                                    {actividad?.creadas ?? 0} creadas · {actividad?.cerradas ?? 0} cerradas
                                    {metricasSolicitudes.pendientes != null && ` · ${metricasSolicitudes.pendientes} abiertas ahora`}
                                </p>
                            </ChartCard>
                        )}

                        {hayPedidos && (
                            <ChartCard
                                tono={DASHBOARD_WIDGET_TONO.pedidos}
                                title="Pedidos por etapa"
                                hint="Conteo actual"
                                icon={ClipboardList}
                                footer={(
                                    <Link href={route('control_pedidos.index')} className={`${GELIA_BTN_OUTLINE} mt-3 min-h-[44px] w-full`}>
                                        Ver pedidos
                                    </Link>
                                )}
                            >
                                <div className="dashboard-phase-table" role="table" aria-label="Pedidos por fase">
                                    {FASES_PEDIDO.map(({ key, label, tono }) => (
                                        <div key={key} className="dashboard-phase-table__row" role="row">
                                            <span
                                                className={`gelia-estado-vivo gelia-estado-vivo--compacto text-[10px] font-semibold ${GELIA_ESTADO_VIVO_TONO[tono]}`}
                                                role="cell"
                                            >
                                                {label}
                                            </span>
                                            <span className="dashboard-phase-table__value tabular-nums theme-text-main" role="cell">
                                                {metricasPedidos[key] ?? 0}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </ChartCard>
                        )}
                    </div>
                </div>
            )}

            {hayCredibox && (
                <div className="dashboard-fluid-row">
                    <ChartCard
                        tono={DASHBOARD_WIDGET_TONO.credibox}
                        title="Antigüedad de cartera"
                        hint="Saldo vencido hoy"
                        icon={CreditCard}
                    >
                        <GraficaDashboard
                            type="bar"
                            tono={DASHBOARD_WIDGET_TONO.credibox}
                            labels={RANGOS_CARTERA.map((rango) => rango.label)}
                            heightClass="h-40"
                            ariaLabel="Saldo vencido por antigüedad"
                            emptyLabel="Sin saldo vencido."
                            datasets={[
                                {
                                    label: 'Monto vencido',
                                    data: RANGOS_CARTERA.map((rango) => cartera[rango.key]?.total ?? 0),
                                    rol: 'principal',
                                },
                            ]}
                        />
                    </ChartCard>

                    <ChartCard
                        tono={DASHBOARD_WIDGET_TONO.credibox}
                        title="Clientes con mayor saldo vencido"
                        hint="Top 5 · cartera viva"
                        icon={CreditCard}
                        footer={(
                            <Link href={route('auto-cobranza.index')} className={`${GELIA_BTN_OUTLINE} mt-3 min-h-[44px] w-full`}>
                                Abrir Credibox
                            </Link>
                        )}
                    >
                        {topVencidos.length > 0 ? (
                            <div className="dashboard-tabla-wrap">
                                <table className="dashboard-tabla">
                                    <caption className="sr-only">Clientes con mayor saldo vencido</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">Cliente</th>
                                            <th scope="col">Saldo</th>
                                            <th scope="col">Días</th>
                                            <th scope="col">Folios</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {topVencidos.map((fila) => (
                                            <tr key={fila.cliente_id}>
                                                <th scope="row">
                                                    <Link
                                                        href={route('auto-cobranza.index', fila.numero_cliente ? { q: fila.numero_cliente } : {})}
                                                        className="theme-text-main underline-offset-2 hover:underline"
                                                    >
                                                        {fila.nombre}
                                                    </Link>
                                                    {fila.numero_cliente && (
                                                        <span className="block text-[11px] theme-text-muted font-normal">{fila.numero_cliente}</span>
                                                    )}
                                                </th>
                                                <td className="tabular-nums">{formatoMoneda(fila.saldo_vencido)}</td>
                                                <td className="tabular-nums">{fila.dias_atraso}</td>
                                                <td className="tabular-nums">{fila.folios}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="dashboard-widget-empty m-0">Sin clientes con saldo vencido.</p>
                        )}
                    </ChartCard>
                </div>
            )}

            {(ultimasSolicitudes.length > 0 || ultimasOperativas.length > 0 || alertasActivos.length > 0) && (
                <div className="dashboard-fluid-row">
                    {ultimasSolicitudes.length > 0 && (
                        <ChartCard tono={DASHBOARD_WIDGET_TONO.solicitudes} title="Últimas solicitudes" icon={FileSignature}>
                            <div className="dashboard-queue-list" role="list">
                                {ultimasSolicitudes.slice(0, 4).map((sol) => {
                                    const { icon: StatusIcon, tono } = estiloEstadoSolicitud(sol.estado?.nombre);
                                    return (
                                        <DashboardQueueRow
                                            key={sol.id}
                                            estadoIcon={StatusIcon}
                                            estadoTono={tono}
                                            primary={`FOL-${sol.id}`}
                                            secondary={sol.cliente?.nombre || 'Nuevo prospecto'}
                                            trailing={formatoMoneda(sol.monto_cotizado || 0)}
                                        />
                                    );
                                })}
                            </div>
                            <Link href={route('solicitudes.index')} className={`${GELIA_BTN_OUTLINE} mt-3 min-h-[44px] w-full`}>
                                Ver listado de solicitudes
                            </Link>
                        </ChartCard>
                    )}
                    {ultimasOperativas.length > 0 && (
                        <ChartCard tono={DASHBOARD_WIDGET_TONO.cancelaciones} title="Últimos movimientos operativos" icon={FileSignature}>
                            <div className="dashboard-queue-list" role="list">
                                {ultimasOperativas.slice(0, 4).map((sol) => {
                                    const { icon: StatusIcon, tono } = estiloEstadoSolicitud(sol.estado?.nombre);
                                    return (
                                        <DashboardQueueRow
                                            key={sol.id}
                                            estadoIcon={StatusIcon}
                                            estadoTono={tono}
                                            primary={sol.proceso?.nombre || 'Operativa'}
                                            secondary={`FOL-${sol.id} · ${sol.cliente?.nombre || 'Sin cliente'}`}
                                            trailingMuted={sol.estado?.nombre}
                                        />
                                    );
                                })}
                            </div>
                            <Link href={route('cancelaciones_cotizaciones.index')} className={`${GELIA_BTN_OUTLINE} mt-3 min-h-[44px] w-full`}>
                                Ver solicitudes operativas
                            </Link>
                        </ChartCard>
                    )}
                    {alertasActivos.length > 0 && (
                        <ChartCard tono={DASHBOARD_WIDGET_TONO.activos} title="Alertas de activos" icon={AlertTriangle}>
                            <div className="dashboard-queue-list" role="list">
                                {alertasActivos.slice(0, 4).map((item) => (
                                    <DashboardQueueRow
                                        key={`${item.id}-${item.alerta_tipo}`}
                                        href={route('activos.show', item.id)}
                                        estadoIcon={AlertTriangle}
                                        estadoTono={item.alerta_tipo === 'vencimiento' ? 'error' : 'aviso'}
                                        primary={item.folio}
                                        secondary={item.nombre}
                                        trailingMuted={item.fecha || undefined}
                                    />
                                ))}
                            </div>
                            <Link href={route('activos.index')} className={`${GELIA_BTN_OUTLINE} mt-3 min-h-[44px] w-full`}>
                                Explorar activos
                            </Link>
                        </ChartCard>
                    )}
                </div>
            )}
        </div>
    );
}
