import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Download, Lock } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPageShell from '../../Components/GeliaPageShell';
import GeliaTituloCard from '../../Components/GeliaTituloCard';
import BadgeListaDescuento from '../../Components/BadgeListaDescuento';
import { THEME_BTN_PRIMARY, THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL, geliaCardClass } from '../../utils/geliaTheme';

const MESES = [
    'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
    'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
];

function dinero(valor) {
    const numero = Number(valor ?? 0);
    return Number.isFinite(numero)
        ? numero.toLocaleString('es-MX', { style: 'currency', currency: 'MXN' })
        : '—';
}

export default function Cierre({
    auth,
    periodo,
    periodos = [],
    cierre,
    puedeOperar = false,
    puedeAutorizar = false,
    autoridad = { autoridad_activa: false, periodo_oficial_id: null },
}) {
    const { flash } = usePage().props;
    const formExterna = useForm({ evidencia: '' });
    const formAutoridad = useForm({
        autoridad_activa: Boolean(autoridad?.autoridad_activa),
        periodo_oficial_id: autoridad?.periodo_oficial_id ?? periodo?.id ?? '',
    });

    const guardarAutoridad = (event) => {
        event.preventDefault();
        formAutoridad.post(route('escalonamiento.autoridad.actualizar'));
    };

    const cambiarPeriodo = (event) => {
        router.get(route('escalonamiento.cierre'), { periodo_id: event.target.value });
    };

    const simular = () => {
        router.post(route('escalonamiento.cierre.simular'), { periodo_id: periodo?.id });
    };

    const cancelar = () => {
        router.post(route('escalonamiento.cierre.cancelar'), { periodo_id: periodo?.id });
    };

    const autorizar = () => {
        router.post(route('escalonamiento.cierre.autorizar'), { periodo_id: periodo?.id });
    };

    const aplicar = () => {
        router.post(route('escalonamiento.cierre.aplicar'), { periodo_id: periodo?.id });
    };

    const registrarExterna = (event) => {
        event.preventDefault();
        if (!cierre?.id) return;
        formExterna.post(route('escalonamiento.cierre.aplicacion_externa', cierre.id));
    };

    const puedeSimular = (periodo?.estado === 'abierto' || periodo?.estado === 'historial') && puedeOperar;
    const esReconstruccion = periodo?.estado === 'historial' || Boolean(cierre?.reconstruccion);
    const puedeCancelar = cierre && cierre.estado !== 'aplicado' && puedeOperar;
    const puedeAutorizarCierre = cierre?.estado === 'borrador' && periodo?.estado === 'en_revision' && puedeAutorizar;
    const puedeAplicar = cierre?.estado === 'autorizado' && periodo?.estado === 'autorizado' && puedeAutorizar;

    return (
        <AppLayout auth={auth}>
            <Head title="Cierre de escalonamiento" />
            <GeliaPageShell className="space-y-6 pb-10">
                <GeliaTituloCard
                    eyebrow="Finanzas"
                    title="Cierre de escalonamiento"
                    description="Simulación congelada, autorización y aplicación interna. El reporte CSV sirve para ajustes manuales en el ERP."
                    icon={Lock}
                />

                <Link href={route('escalonamiento.index')} className={`${THEME_BTN_SECONDARY} inline-flex items-center gap-2 w-fit`}>
                    <ArrowLeft className="w-4 h-4" aria-hidden />
                    Volver al resumen
                </Link>

                {flash?.success && (
                    <p className={`${geliaCardClass('p-4')} text-sm theme-text-exito m-0`} role="status">{flash.success}</p>
                )}
                {flash?.error && (
                    <p className={`${geliaCardClass('p-4')} text-sm theme-text-peligro m-0`} role="alert">{flash.error}</p>
                )}

                {puedeAutorizar && (
                    <section className={geliaCardClass('p-6 space-y-4')}>
                        <div>
                            <h2 className="text-base font-semibold theme-text-main m-0">Autoridad del módulo</h2>
                            <p className="text-sm theme-text-muted mt-1 mb-0">
                                Cuando está activa, el acumulado y la lista vigente del período oficial se publican en cada cliente participante.
                                Activar solo tras el corte del runbook (mes nuevo abierto).
                            </p>
                        </div>
                        <form onSubmit={guardarAutoridad} className="flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                            <label className="flex items-center gap-2 text-sm theme-text-main cursor-pointer">
                                <input
                                    type="checkbox"
                                    className="h-4 w-4 rounded border theme-border accent-[var(--color-primario)]"
                                    checked={formAutoridad.data.autoridad_activa}
                                    onChange={(e) => formAutoridad.setData('autoridad_activa', e.target.checked)}
                                />
                                Módulo como única autoridad de monto y lista
                            </label>
                            {periodo?.id && (
                                <label className="text-sm theme-text-main flex flex-col gap-1">
                                    <span className={THEME_LABEL}>Período oficial (opcional)</span>
                                    <select
                                        className={`${THEME_INPUT} min-w-[12rem]`}
                                        value={formAutoridad.data.periodo_oficial_id ?? ''}
                                        onChange={(e) => formAutoridad.setData(
                                            'periodo_oficial_id',
                                            e.target.value ? Number(e.target.value) : '',
                                        )}
                                    >
                                        <option value="">Último período abierto</option>
                                        <option value={periodo.id}>
                                            {MESES[periodo.mes - 1]} {periodo.anio} (ID {periodo.id})
                                        </option>
                                    </select>
                                </label>
                            )}
                            <button
                                type="submit"
                                className={THEME_BTN_SECONDARY}
                                disabled={formAutoridad.processing}
                            >
                                Guardar autoridad
                            </button>
                        </form>
                        {(formAutoridad.errors.autoridad_activa || formAutoridad.errors.periodo_oficial_id) && (
                            <div className="space-y-1" role="alert">
                                {formAutoridad.errors.autoridad_activa && <p className="text-xs theme-text-peligro m-0">{formAutoridad.errors.autoridad_activa}</p>}
                                {formAutoridad.errors.periodo_oficial_id && <p className="text-xs theme-text-peligro m-0">{formAutoridad.errors.periodo_oficial_id}</p>}
                            </div>
                        )}
                        {autoridad?.autoridad_activa && (
                            <p className="text-sm font-medium theme-text-main m-0">Estado: autoridad activa.</p>
                        )}
                    </section>
                )}

                {periodos.length > 0 && (
                    <section className={geliaCardClass('p-6')}>
                        <label className="block space-y-1 max-w-xs">
                            <span className={THEME_LABEL}>Período</span>
                            <select
                                className={THEME_INPUT}
                                value={periodo?.id || ''}
                                onChange={cambiarPeriodo}
                            >
                                {periodos.map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {MESES[p.mes - 1]} {p.anio} ({p.estado})
                                    </option>
                                ))}
                            </select>
                        </label>
                    </section>
                )}

                {!periodo ? (
                    <p className={geliaCardClass('p-6 theme-text-muted m-0')}>No hay período en curso para cerrar.</p>
                ) : (
                    <section className={geliaCardClass('p-6 space-y-4')}>
                        {esReconstruccion && (
                            <p className="text-sm theme-text-muted m-0">
                                Este cierre reconstruye un mes histórico. El período operativo y la lista publicada en clientes se mantienen.
                            </p>
                        )}
                        <div className="grid gap-4 sm:grid-cols-3">
                            <div>
                                <p className="text-xs theme-text-muted m-0">Período</p>
                                <p className="theme-text-main font-semibold m-0">{MESES[periodo.mes - 1]} {periodo.anio}</p>
                            </div>
                            <div>
                                <p className="text-xs theme-text-muted m-0">Estado del período</p>
                                <p className="theme-text-main font-semibold m-0">{periodo.estado}</p>
                            </div>
                            <div>
                                <p className="text-xs theme-text-muted m-0">Cierre simulado</p>
                                <p className="theme-text-main font-semibold m-0">
                                    {cierre ? `v${cierre.version} · ${cierre.estado}` : '—'}
                                </p>
                            </div>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {puedeSimular && (
                                <button type="button" className={THEME_BTN_PRIMARY} onClick={simular}>
                                    Simular cierre
                                </button>
                            )}
                            {puedeCancelar && (
                                <button type="button" className={THEME_BTN_SECONDARY} onClick={cancelar}>
                                    Cancelar simulación
                                </button>
                            )}
                            {puedeAutorizarCierre && (
                                <button type="button" className={THEME_BTN_PRIMARY} onClick={autorizar}>
                                    Autorizar cierre
                                </button>
                            )}
                            {puedeAplicar && (
                                <button type="button" className={THEME_BTN_PRIMARY} onClick={aplicar}>
                                    Aplicar cierre interno
                                </button>
                            )}
                            {cierre?.reporte_disponible && (
                                <a
                                    href={route('escalonamiento.cierre.reporte', cierre.id)}
                                    className={`${THEME_BTN_SECONDARY} inline-flex items-center gap-2`}
                                >
                                    <Download className="w-4 h-4" aria-hidden />
                                    Reporte ERP
                                </a>
                            )}
                        </div>
                    </section>
                )}

                {cierre?.estado === 'aplicado' && puedeOperar && (
                    <section className={geliaCardClass('p-6 space-y-3')}>
                        <h2 className="text-sm font-bold theme-text-main m-0">Aplicación manual en ERP</h2>
                        <form onSubmit={registrarExterna} className="space-y-3 max-w-xl">
                            <label className="block space-y-1">
                                <span className={THEME_LABEL}>Evidencia</span>
                                <textarea
                                    className={`${THEME_INPUT} theme-textarea`}
                                    rows={3}
                                    value={formExterna.data.evidencia}
                                    onChange={(e) => formExterna.setData('evidencia', e.target.value)}
                                    required
                                />
                            </label>
                            {formExterna.errors.evidencia && (
                                <p className="text-xs theme-text-peligro m-0">{formExterna.errors.evidencia}</p>
                            )}
                            <button type="submit" className={THEME_BTN_SECONDARY} disabled={formExterna.processing}>
                                Registrar aplicación externa
                            </button>
                        </form>
                        {cierre.aplicacion_externa_declarada && (
                            <p className="text-sm theme-text-muted m-0">Aplicación externa ya declarada.</p>
                        )}
                    </section>
                )}

                {cierre?.detalles?.length > 0 && (
                    <section className={geliaCardClass('p-6')}>
                        <h2 className="text-sm font-bold theme-text-main mb-4">Detalle por cliente</h2>
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left theme-text-muted">
                                        <th className="py-2 pr-3">Cliente</th>
                                        <th className="py-2 pr-3">Neto</th>
                                        <th className="py-2 pr-3">Lista siguiente</th>
                                        <th className="py-2 pr-3">Motivo</th>
                                        <th className="py-2">Meses s/compra</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {cierre.detalles.map((fila) => (
                                        <tr key={fila.cliente_id} className="border-t theme-border">
                                            <td className="py-2 pr-3 theme-text-main">
                                                <span className="font-semibold">{fila.numero_cliente}</span>
                                                <span className="block theme-text-muted">{fila.nombre}</span>
                                            </td>
                                            <td className="py-2 pr-3">{dinero(fila.neto)}</td>
                                            <td className="py-2 pr-3">
                                                <BadgeListaDescuento nombre={fila.lista_siguiente} />
                                            </td>
                                            <td className="py-2 pr-3">{fila.motivo}</td>
                                            <td className="py-2">{fila.meses_sin_compra}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                )}
            </GeliaPageShell>
        </AppLayout>
    );
}
