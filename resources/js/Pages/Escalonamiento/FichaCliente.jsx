import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, TrendingUp } from 'lucide-react';
import AppLayout from '../../Layouts/AppLayout';
import GeliaPageShell from '../../Components/GeliaPageShell';
import GeliaTituloCard from '../../Components/GeliaTituloCard';
import BadgeListaDescuento from '../../Components/BadgeListaDescuento';
import { geliaCardClass, THEME_BTN_SECONDARY } from '../../utils/geliaTheme';

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

export default function FichaCliente({ auth, cliente, periodo, ficha, historialMeses = [] }) {
    const cab = ficha?.cabecera || {};

    return (
        <AppLayout auth={auth}>
            <Head title={`Escalonamiento · ${cliente?.numero_cliente || cliente?.nombre || 'Cliente'}`} />
            <GeliaPageShell className="space-y-6 pb-10">
                <div className="flex flex-wrap items-center gap-3">
                    <Link href={route('escalonamiento.index')} className={`${THEME_BTN_SECONDARY} inline-flex items-center gap-2`}>
                        <ArrowLeft className="w-4 h-4" aria-hidden />
                        Volver
                    </Link>
                </div>

                <GeliaTituloCard
                    eyebrow="Finanzas"
                    title={[cliente?.numero_cliente, cliente?.nombre].filter(Boolean).join(' · ') || 'Cliente'}
                    description="Ficha mensual del módulo de escalonamiento. La lista operativa del cliente puede diferir de la lista vigente proyectada aquí."
                    icon={TrendingUp}
                />

                {periodo && (
                    <section className={geliaCardClass('p-6 space-y-2')}>
                        <p className="text-sm theme-text-muted m-0">
                            Período: {MESES[periodo.mes - 1]} {periodo.anio} · {periodo.estado}
                            {periodo.corte ? ` · Corte ${periodo.corte}` : ''}
                        </p>
                        <p className="text-2xl font-black theme-text-main m-0">
                            Acumulado: {dinero(cab.acumulado)}
                        </p>
                    </section>
                )}

                {historialMeses.length > 0 && (
                    <section className={geliaCardClass('p-6 space-y-3')}>
                        <h2 className="text-base font-black theme-text-main m-0">Historial por mes</h2>
                        <ul className="m-0 p-0 list-none space-y-2 text-sm">
                            {historialMeses.map((mes) => (
                                <li key={mes.periodo_id} className="flex flex-wrap gap-2 items-baseline border-t theme-border pt-2">
                                    <Link
                                        href={route('escalonamiento.clientes.ficha', { cliente: cliente.id, periodo_id: mes.periodo_id })}
                                        className="font-semibold theme-text-main underline"
                                    >
                                        {mes.etiqueta}
                                    </Link>
                                    <span className="theme-text-muted">({mes.estado})</span>
                                    <span className="theme-text-main">{dinero(mes.acumulado)}</span>
                                    <span className="inline-flex flex-wrap items-center gap-2">
                                        <BadgeListaDescuento nombre={mes.clasificacion_mes} />
                                        <span className="theme-text-muted text-xs">vigente</span>
                                        <BadgeListaDescuento nombre={mes.lista_vigente} />
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                <section className={geliaCardClass('p-6')}>
                    <h2 className="text-base font-black theme-text-main m-0 mb-4">Listas</h2>
                    <dl className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                        <TarjetaLista etiqueta="Lista base" valor={cab.lista_base} esLista />
                        <TarjetaLista etiqueta="Lista vigente (módulo)" valor={cab.lista_vigente} destacar={cab.diverge_lista_operativa} esLista />
                        <TarjetaLista etiqueta="Clasificación del mes" valor={cab.clasificacion_mes} esLista />
                        <TarjetaLista etiqueta="Máximo del mes" valor={cab.clasificacion_mes_max} esLista />
                        <TarjetaLista etiqueta="Lista operativa" valor={cab.lista_operativa} esLista />
                        <TarjetaLista etiqueta="Participa" valor={cab.participa ? 'Sí' : 'No'} />
                    </dl>
                    {cab.diverge_lista_operativa && (
                        <p className="gelia-callout-aviso p-3 text-sm mt-4 m-0 font-semibold">
                            La lista operativa no coincide con la lista vigente del módulo (operación en paralelo).
                        </p>
                    )}
                </section>

                <section className={geliaCardClass('p-6 space-y-3')}>
                    <h2 className="text-base font-black theme-text-main m-0">Renovación propuesta</h2>
                    <dl className="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                        <TarjetaLista etiqueta="Lista siguiente (propuesta)" valor={cab.lista_siguiente_propuesta} esLista />
                        <TarjetaLista
                            etiqueta="Cumple mantenimiento"
                            valor={cab.cumple_mantenimiento == null ? '—' : cab.cumple_mantenimiento ? 'Sí' : 'No'}
                        />
                        <TarjetaLista
                            etiqueta="Faltante mantenimiento"
                            valor={cab.faltante_mantenimiento != null ? dinero(cab.faltante_mantenimiento) : '—'}
                        />
                    </dl>
                </section>

                <section id="movimientos" className={geliaCardClass('p-6 space-y-3 scroll-mt-24')}>
                    <h2 className="text-base font-black theme-text-main m-0">Movimientos</h2>
                    {(ficha?.movimientos || []).length === 0 ? (
                        <p className="theme-text-muted m-0 text-sm">Sin movimientos en el período.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="text-left theme-text-muted">
                                        <th className="py-2 pr-2">Documento</th>
                                        <th className="py-2 pr-2">Fecha</th>
                                        <th className="py-2 pr-2">Operación</th>
                                        <th className="py-2">Efecto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {ficha.movimientos.map((mov) => (
                                        <tr key={mov.id} className="border-t theme-border">
                                            <td className="py-2 pr-2 theme-text-main">
                                                {mov.documento
                                                    ? `${mov.documento.tipo} ${mov.documento.folio}`
                                                    : '—'}
                                            </td>
                                            <td className="py-2 pr-2 theme-text-muted">{mov.documento?.fecha_emision || '—'}</td>
                                            <td className="py-2 pr-2 theme-text-muted">{mov.operacion}</td>
                                            <td className="py-2 theme-text-main">{dinero(mov.efecto)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className={geliaCardClass('p-6 space-y-3')}>
                    <h2 className="text-base font-black theme-text-main m-0">Documentos y devoluciones</h2>
                    {(ficha?.documentos || []).length === 0 ? (
                        <p className="theme-text-muted m-0 text-sm">Sin documentos.</p>
                    ) : (
                        <ul className="m-0 p-0 list-none space-y-2 text-sm">
                            {ficha.documentos.map((doc) => (
                                <li key={doc.id} className="border-t theme-border pt-2 theme-text-main">
                                    <span className="font-semibold">{doc.tipo} {doc.folio}</span>
                                    <span className="theme-text-muted"> · {doc.estado} · {dinero(doc.total)}</span>
                                    {doc.remision_original ? (
                                        <span className="block theme-text-muted text-xs">Venta original: {doc.remision_original}</span>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    )}
                    {(ficha?.aplicaciones || []).length > 0 && (
                        <div className="mt-4 space-y-2">
                            <h3 className="text-sm font-black theme-text-main m-0">Vínculos de devolución</h3>
                            {ficha.aplicaciones.map((app) => (
                                <p key={app.id} className="text-sm theme-text-muted m-0">
                                    {app.folio_devolucion} → {app.folio_remision} ({dinero(app.importe)}) · {app.estado}
                                </p>
                            ))}
                        </div>
                    )}
                </section>

                {(ficha?.solicitudes_conciliacion || []).length > 0 && (
                    <section className={geliaCardClass('p-6 space-y-3')}>
                        <h2 className="text-base font-black theme-text-main m-0">Solicitudes pagadas (cotejo)</h2>
                        <ul className="m-0 p-0 list-none space-y-2 text-sm">
                            {ficha.solicitudes_conciliacion.map((sol) => (
                                <li key={sol.solicitud_id} className="border-t theme-border pt-2 theme-text-main">
                                    #{sol.solicitud_id} · objetivo {dinero(sol.importe_objetivo)} · {sol.estado_cobertura}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}

                {(ficha?.incidencias || []).length > 0 && (
                    <section className={geliaCardClass('p-6 space-y-3')}>
                        <h2 className="text-base font-black theme-text-main m-0">Incidencias</h2>
                        <ul className="m-0 p-0 list-none space-y-2 text-sm">
                            {ficha.incidencias.map((inc) => (
                                <li key={inc.id} className="border-t theme-border pt-2">
                                    <span className="font-semibold theme-text-main">{inc.codigo || 'incidencia'}</span>
                                    <span className="theme-text-muted"> · {inc.estado}</span>
                                    <p className="theme-text-muted m-0">{inc.motivo}</p>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </GeliaPageShell>
        </AppLayout>
    );
}

function TarjetaLista({ etiqueta, valor, destacar = false, esLista = false }) {
    const vacio = valor === null || valor === undefined || valor === '';

    return (
        <div className={destacar ? 'gelia-callout-aviso p-3' : ''}>
            <dt className="text-[10px] font-black uppercase tracking-widest theme-text-muted">{etiqueta}</dt>
            <dd className="theme-text-main m-0 font-semibold">
                {esLista && !vacio ? <BadgeListaDescuento nombre={valor} /> : (vacio ? '—' : valor)}
            </dd>
        </div>
    );
}
