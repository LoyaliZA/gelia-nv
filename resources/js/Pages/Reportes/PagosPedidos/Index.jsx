import React, { useCallback, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import GeliaPaginacion from '../../../Components/GeliaPaginacion';
import { geliaCardClass } from '../../../utils/geliaTheme';
import {
    filtrosAlCambiarTipoReporte,
    FILTROS_LIMPIOS_VOUCHERS,
    subtituloAgrupacionVouchers,
    TIPO_REPORTE_PEDIDO,
    TIPO_REPORTE_VOUCHERS,
} from '../../../utils/reportesPagosTipoReporte';
import BarraConsultaPagosPedidos from './Partials/BarraConsultaPagosPedidos';
import BarraConsultaVouchersValidados from './Partials/BarraConsultaVouchersValidados';
import MetricasPagosPedidos from './Partials/MetricasPagosPedidos';
import MetricasVouchersValidados from './Partials/MetricasVouchersValidados';
import GrupoDiaPagos from './Partials/GrupoDiaPagos';
import GrupoVouchersValidados from './Partials/GrupoVouchersValidados';
import SelectorAgrupacionVouchers from './Partials/SelectorAgrupacionVouchers';
import MenuExportarPagos from './Partials/MenuExportarPagos';
import MisReportesPagosPedidos from './Partials/MisReportesPagosPedidos';
import PagosPedidosReporteFloatingTracker from './Partials/PagosPedidosReporteFloatingTracker';
import SelectorTipoReportePagos from './Partials/SelectorTipoReportePagos';
import { puedePermiso } from '../../../utils/permisos';

const cardHeader = geliaCardClass('p-6 md:p-10 flex flex-col lg:flex-row justify-between items-stretch lg:items-center gap-6');

const FILTROS_LIMPIOS_PEDIDO = {
    tipo_reporte: TIPO_REPORTE_PEDIDO,
    busqueda: null,
    fecha_pedido_desde: null,
    fecha_pedido_hasta: null,
    fecha_validacion_desde: null,
    fecha_validacion_hasta: null,
    estado_cierre: 'vigente',
    estado_cobertura: null,
    forma_pago: null,
    con_remision: null,
    con_evidencia: null,
    fecha_incompleta: null,
    estado_admin: 'pendiente',
};

export default function Index({
    auth,
    grupos = [],
    paginacion = {},
    metricas = {},
    metricas_vouchers = {},
    grupos_vouchers = [],
    agrupar_por_vouchers = 'movimiento',
    filtros = {},
    tipo_reporte = TIPO_REPORTE_PEDIDO,
    vouchers_disponible = false,
    formas_pago = [],
    bancos = [],
    departamentos = [],
    vendedores = [],
    almacenes = [],
    origenes_pedido = [],
    capturadores = [],
    validadores_vouchers = [],
    avisos = {},
    mis_exportaciones = [],
}) {
    const [cacheDetalle, setCacheDetalle] = useState({});
    const [cargando, setCargando] = useState(false);
    const [avisoSegundoPlano, setAvisoSegundoPlano] = useState(false);
    const [jobSeguimiento, setJobSeguimiento] = useState(null);

    const esPedido = tipo_reporte === TIPO_REPORTE_PEDIDO;
    const esVouchers = tipo_reporte === TIPO_REPORTE_VOUCHERS && vouchers_disponible;

    const navegar = useCallback((params) => {
        setCargando(true);
        router.get(route('reportes.pagos_pedidos.index'), { ...filtros, ...params, page: 1 }, {
            preserveState: true,
            replace: true,
            onFinish: () => setCargando(false),
        });
    }, [filtros]);

    const cambiarTipoReporte = useCallback((nuevoTipo) => {
        setCargando(true);
        const params = filtrosAlCambiarTipoReporte(filtros, nuevoTipo);
        router.get(route('reportes.pagos_pedidos.index'), params, {
            preserveState: true,
            replace: true,
            onFinish: () => setCargando(false),
        });
    }, [filtros]);

    const cambiarPagina = (page) => {
        setCargando(true);
        router.get(route('reportes.pagos_pedidos.index'), { ...filtros, page }, {
            preserveState: true,
            onFinish: () => setCargando(false),
        });
    };

    const limpiarPedido = () => navegar(FILTROS_LIMPIOS_PEDIDO);
    const limpiarVouchers = () => navegar(FILTROS_LIMPIOS_VOUCHERS);

    const recargarListaAdmin = useCallback(() => {
        if ((filtros.estado_admin || 'pendiente') !== 'pendiente') return;
        setCargando(true);
        router.reload({
            only: esPedido
                ? ['grupos', 'metricas', 'paginacion', 'filtros']
                : ['grupos_vouchers', 'metricas_vouchers', 'paginacion', 'filtros'],
            preserveScroll: true,
            onFinish: () => setCargando(false),
        });
    }, [filtros.estado_admin, esPedido]);

    const puedeCsv = puedePermiso(auth, 'reportes.pagos_pedidos.exportar_csv');
    const puedePdf = puedePermiso(auth, 'reportes.pagos_pedidos.exportar_pdf');
    const puedeExportar = (esPedido || esVouchers) && (puedeCsv || puedePdf);

    const subtitulo = esPedido
        ? `Pagos por pedido · Agrupado por fecha del pedido${(puedeCsv || puedePdf) ? ' · Exportación CSV y PDF' : ''}`
        : esVouchers
            ? `Vouchers validados · ${subtituloAgrupacionVouchers(agrupar_por_vouchers)}${(puedeCsv || puedePdf) ? ' · Exportación CSV y PDF' : ''}`
            : 'Vouchers validados · Próximamente';

    const headTitle = esPedido
        ? 'Reportes administrativos de pagos | GELIA'
        : 'Vouchers validados | GELIA';

    return (
        <AppLayout auth={auth}>
            <Head title={headTitle} />
            <GeliaPageShell className="space-y-6 md:space-y-8">
                <header className={cardHeader}>
                    <div className="min-w-0">
                        <div className="flex items-center gap-3 mb-2">
                            <span className="h-1.5 w-12 rounded-full shrink-0" style={{ backgroundColor: 'var(--color-primario)' }} />
                            <p className="text-[10px] font-black uppercase tracking-[0.3em] m-0" style={{ color: 'var(--color-primario)' }}>
                                Reportes
                            </p>
                        </div>
                        <h1 className="text-2xl sm:text-3xl md:text-5xl font-black italic uppercase tracking-tighter theme-text-main m-0 leading-none">
                            Reportes <span style={{ color: 'var(--color-primario)' }}>administrativos de pagos</span>
                        </h1>
                        <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-3 m-0">
                            {subtitulo}
                        </p>
                    </div>
                </header>

                <SelectorTipoReportePagos
                    tipoActivo={tipo_reporte}
                    onCambiar={cambiarTipoReporte}
                />

                {esPedido && (
                    <MetricasPagosPedidos metricas={metricas} cargando={cargando} />
                )}

                {esVouchers && (
                    <MetricasVouchersValidados metricas={metricas_vouchers} cargando={cargando} />
                )}

                {esPedido && avisos.requiere_backfill && (
                    <div className={geliaCardClass('p-4 border-l-4')} style={{ borderLeftColor: 'var(--color-primario)' }}>
                        <p className="text-xs font-bold theme-text-main m-0">
                            Hay pedidos validados sin cierre histórico. Ejecute{' '}
                            <code className="text-[10px] px-1.5 py-0.5 rounded theme-element border theme-border">php artisan reportes:backfill-cierres-pago</code>
                            {' '}para cargarlos en el reporte.
                        </p>
                    </div>
                )}

                {esPedido && (
                    <div className={geliaCardClass('p-4 md:p-6 space-y-4')}>
                        <BarraConsultaPagosPedidos
                            filtros={filtros}
                            formasPago={formas_pago}
                            onAplicar={navegar}
                            onLimpiarTodo={limpiarPedido}
                        />
                        <MenuExportarPagos
                            filtrosQuery={filtros}
                            puedeCsv={puedeCsv}
                            puedePdf={puedePdf}
                            bancos={bancos}
                            formasPago={formas_pago}
                            departamentos={departamentos}
                            vendedores={vendedores}
                            almacenes={almacenes}
                            origenesPedido={origenes_pedido}
                            onAvisoSegundoPlano={() => setAvisoSegundoPlano(true)}
                            jobSeguimientoExterno={jobSeguimiento}
                            onLimpiarSeguimiento={() => setJobSeguimiento(null)}
                        />
                        {avisoSegundoPlano && (
                            <div className="rounded-xl border theme-border px-4 py-3 text-sm theme-text-main" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 8%, transparent)' }}>
                                El reporte continuará generándose. Te avisaremos cuando esté listo.
                            </div>
                        )}
                    </div>
                )}

                {esVouchers && (
                    <div className={geliaCardClass('p-4 md:p-6 space-y-4')}>
                        <BarraConsultaVouchersValidados
                            filtros={filtros}
                            formasPago={formas_pago}
                            bancos={bancos}
                            capturadores={capturadores}
                            validadores={validadores_vouchers}
                            onAplicar={navegar}
                            onLimpiarTodo={limpiarVouchers}
                        />
                        <SelectorAgrupacionVouchers
                            valor={agrupar_por_vouchers}
                            onCambiar={(agrupar_por) => navegar({ agrupar_por })}
                        />
                        <MenuExportarPagos
                            filtrosQuery={filtros}
                            puedeCsv={puedeCsv}
                            puedePdf={puedePdf}
                            bancos={bancos}
                            formasPago={formas_pago}
                            departamentos={departamentos}
                            vendedores={vendedores}
                            almacenes={almacenes}
                            origenesPedido={origenes_pedido}
                            onAvisoSegundoPlano={() => setAvisoSegundoPlano(true)}
                            jobSeguimientoExterno={jobSeguimiento}
                            onLimpiarSeguimiento={() => setJobSeguimiento(null)}
                        />
                        {avisoSegundoPlano && (
                            <div className="rounded-xl border theme-border px-4 py-3 text-sm theme-text-main" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 8%, transparent)' }}>
                                El reporte continuará generándose. Te avisaremos cuando esté listo.
                            </div>
                        )}
                    </div>
                )}

                {(esPedido || esVouchers) && (
                    <MisReportesPagosPedidos
                        exportacionesIniciales={mis_exportaciones}
                        puedeExportar={puedeExportar}
                        onVerProgreso={(id) => {
                            setAvisoSegundoPlano(false);
                            setJobSeguimiento(id);
                        }}
                    />
                )}

                {esPedido && grupos.length === 0 && !cargando && (
                    <div className={geliaCardClass('p-10 md:p-14 text-center')}>
                        <div className="inline-flex p-4 rounded-2xl theme-element border theme-border mb-4">
                            <CalendarDays className="w-8 h-8" style={{ color: 'var(--color-primario)' }} />
                        </div>
                        <p className="text-sm font-semibold theme-text-main m-0">Sin resultados</p>
                        <p className="text-xs theme-text-muted mt-2 m-0 max-w-md mx-auto leading-relaxed">
                            Ajuste el periodo o los filtros. Solo aparecen pedidos con cierre financiero registrado.
                        </p>
                    </div>
                )}

                {esVouchers && grupos_vouchers.length === 0 && !cargando && (
                    <div className={geliaCardClass('p-10 md:p-14 text-center')}>
                        <div className="inline-flex p-4 rounded-2xl theme-element border theme-border mb-4">
                            <CalendarDays className="w-8 h-8" style={{ color: 'var(--color-primario)' }} />
                        </div>
                        <p className="text-sm font-semibold theme-text-main m-0">Sin resultados</p>
                        <p className="text-xs theme-text-muted mt-2 m-0 max-w-md mx-auto leading-relaxed">
                            Ajuste el periodo o los filtros. Por defecto se muestran vouchers de ingreso bancario validado.
                        </p>
                    </div>
                )}

                {esPedido && (
                    <div className="space-y-4 md:space-y-6">
                        {grupos.map((grupo, i) => (
                            <GrupoDiaPagos
                                key={grupo.fecha}
                                grupo={grupo}
                                abiertoDefault={i === 0}
                                auth={auth}
                                cacheDetalle={cacheDetalle}
                                onCacheDetalle={(id, data) => setCacheDetalle((c) => ({ ...c, [id]: data }))}
                                onRecargarLista={recargarListaAdmin}
                            />
                        ))}
                    </div>
                )}

                {esVouchers && (
                    <div className="space-y-4 md:space-y-6">
                        {grupos_vouchers.map((grupo, i) => (
                            <GrupoVouchersValidados
                                key={grupo.clave}
                                grupo={grupo}
                                abiertoDefault={i === 0}
                                auth={auth}
                                onRecargarLista={recargarListaAdmin}
                            />
                        ))}
                    </div>
                )}

                {paginacion.last_page > 1 && (esPedido || esVouchers) && (
                    <GeliaPaginacion
                        paginator={{
                            current_page: paginacion.current_page,
                            last_page: paginacion.last_page,
                            from: ((paginacion.current_page - 1) * paginacion.per_page) + 1,
                            to: Math.min(paginacion.current_page * paginacion.per_page, paginacion.total),
                            total: paginacion.total,
                        }}
                        onIrAPagina={cambiarPagina}
                    />
                )}
            </GeliaPageShell>
            <PagosPedidosReporteFloatingTracker canView={puedeExportar} />
        </AppLayout>
    );
}
