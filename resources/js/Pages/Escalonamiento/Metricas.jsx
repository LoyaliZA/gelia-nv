import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, TrendingUp } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPageShell from '../../Components/GeliaPageShell';
import GeliaTituloCard from '../../Components/GeliaTituloCard';
import { geliaCardClass, THEME_BTN_SECONDARY, THEME_INPUT, THEME_LABEL } from '../../utils/geliaTheme';

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

function numeroTexto(valor) {
    return String(valor ?? 0);
}

export default function Metricas({ auth, periodo, periodos = [], metricas, tareas = [] }) {
    const cambiarPeriodo = (event) => {
        router.get(route('escalonamiento.metricas'), { periodo_id: event.target.value }, {
            preserveState: true,
        });
    };

    return (
        <AppLayout auth={auth}>
            <Head title="Escalonamiento · Métricas" />
            <GeliaPageShell className="space-y-6 pb-10">
                <Link href={route('escalonamiento.index', periodo ? { periodo_id: periodo.id } : {})} className={`${THEME_BTN_SECONDARY} inline-flex items-center gap-2 w-fit`}>
                    <ArrowLeft className="w-4 h-4" aria-hidden />
                    Volver
                </Link>

                <GeliaTituloCard
                    eyebrow="Finanzas"
                    title="Métricas de escalonamiento"
                    description="Indicadores trazables a documentos y al estado del período."
                    icon={TrendingUp}
                />

                {periodos.length > 0 && (
                    <section className={geliaCardClass('p-6')}>
                        <label className="block space-y-1 max-w-xs">
                            <span className={THEME_LABEL}>Período</span>
                            <select className={THEME_INPUT} value={periodo?.id || ''} onChange={cambiarPeriodo}>
                                {periodos.map((p) => (
                                    <option key={p.id} value={p.id}>{MESES[p.mes - 1]} {p.anio} ({p.estado})</option>
                                ))}
                            </select>
                        </label>
                    </section>
                )}

                {metricas && (
                    <section className={geliaCardClass('p-6')}>
                        <div className="flex flex-wrap justify-between gap-3 mb-4">
                            <h2 className="text-base font-black theme-text-main m-0">
                                {periodo ? `${MESES[periodo.mes - 1]} ${periodo.anio}` : 'Sin período'}
                            </h2>
                            {periodo && (
                                <a
                                    href={route('escalonamiento.metricas.exportar', { periodo_id: periodo.id })}
                                    className={THEME_BTN_SECONDARY}
                                >
                                    Exportar CSV
                                </a>
                            )}
                        </div>
                        <dl className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                            <Metrica etiqueta="Compras elegibles" valor={dinero(metricas.compras)} />
                            <Metrica etiqueta="Devoluciones aplicadas" valor={dinero(metricas.devoluciones_aplicadas)} />
                            <Metrica etiqueta="Venta neta" valor={dinero(metricas.venta_neta)} />
                            <Metrica etiqueta="Ascensos intrames" valor={numeroTexto(metricas.ascensos_intrames)} />
                            <Metrica etiqueta="Pendientes de vínculo" valor={numeroTexto(metricas.pendientes_vinculo)} />
                            <Metrica etiqueta="Incidencias abiertas" valor={numeroTexto(metricas.incidencias_abiertas)} />
                            <Metrica etiqueta="Documentos" valor={numeroTexto(metricas.documentos_total)} />
                            <Metrica etiqueta="Clientes con movimiento" valor={numeroTexto(metricas.clientes_con_movimiento)} />
                            {metricas.cobertura_historial_pct != null && (
                                <Metrica etiqueta="Cobertura histórica (%)" valor={`${metricas.cobertura_historial_pct}%`} />
                            )}
                            {metricas.antiguedad_corte_dias != null && (
                                <Metrica etiqueta="Días desde último corte" valor={numeroTexto(metricas.antiguedad_corte_dias)} />
                            )}
                        </dl>
                    </section>
                )}

                <section className={geliaCardClass('p-6 space-y-3')}>
                    <h2 className="text-base font-black theme-text-main m-0">Tareas operativas</h2>
                    {tareas.length === 0 ? (
                        <p className="theme-text-muted m-0 text-sm">Sin pendientes destacados.</p>
                    ) : (
                        <ul className="m-0 p-0 list-none space-y-2">
                            {tareas.map((tarea, indice) => (
                                <li key={`${tarea.codigo ?? 'tarea'}-${tarea.descripcion ?? indice}-${indice}`} className="text-sm theme-text-main">
                                    {tarea.descripcion}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </GeliaPageShell>
        </AppLayout>
    );
}

function Metrica({ etiqueta, valor }) {
    return (
        <div>
            <dt className="theme-text-muted m-0">{etiqueta}</dt>
            <dd className="font-black theme-text-main m-0 mt-1">{valor}</dd>
        </div>
    );
}
