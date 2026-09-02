import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Package, AlertTriangle, PackageCheck, Truck } from 'lucide-react';
import AppLayout from '../../../Layouts/AppLayout';
import GeliaPageShell from '../../../Components/GeliaPageShell';
import { geliaCardClass } from '../../../utils/geliaTheme';
import TimelineResguardo from './Partials/TimelineResguardo';
import { badgeEstadoResguardo, formatearFechaOperativa, BTN_SECONDARY } from './Partials/resguardosStyles';
import { THEME_BTN_PRIMARY } from '../../../utils/geliaTheme';

export default function Show({ auth, resguardo, timeline = [], catalogos = {}, permisos = {} }) {
    const titulo = resguardo?.snapshot_folio || `Resguardo #${resguardo?.id}`;

    return (
        <AppLayout auth={auth}>
            <Head title={`${titulo} | Resguardos PDV`} />
            <GeliaPageShell className="max-w-[1100px] space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <Link
                        href={route('punto_venta.resguardos.index')}
                        className="inline-flex items-center gap-2 text-[10px] font-black uppercase theme-text-muted hover:theme-text-main"
                    >
                        <ArrowLeft className="w-4 h-4" /> Volver a bandejas
                    </Link>
                </div>

                <div className={`${geliaCardClass()} p-5 md:p-6 space-y-4`}>
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="space-y-2 min-w-0">
                            <div className="flex items-center gap-2">
                                <Package className="w-5 h-5 shrink-0" style={{ color: 'var(--color-primario)' }} />
                                <h1 className="text-xl font-black italic uppercase theme-text-main m-0 truncate">
                                    {titulo}
                                </h1>
                            </div>
                            <p className="text-sm font-bold theme-text-muted m-0">
                                Cliente {resguardo.referencia_cliente}
                            </p>
                            {resguardo.sucursal?.nombre && (
                                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                                    {resguardo.sucursal.nombre}
                                </p>
                            )}
                        </div>
                        <span className={`inline-flex px-3 py-1.5 rounded-xl text-[10px] font-black uppercase ${badgeEstadoResguardo(resguardo.estado)}`}>
                            {resguardo.estado_etiqueta || catalogos.estados?.[resguardo.estado] || resguardo.estado}
                        </span>
                    </div>

                    {resguardo.clasificaciones_etiquetas?.length > 0 && (
                        <div className="flex flex-wrap gap-2">
                            {resguardo.clasificaciones_etiquetas.map((etiqueta) => (
                                <span
                                    key={etiqueta}
                                    className="inline-flex px-2 py-1 rounded-lg text-[9px] font-black uppercase bg-amber-500/15 text-amber-700 dark:text-amber-300"
                                >
                                    {etiqueta}
                                </span>
                            ))}
                        </div>
                    )}

                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <DetalleCampo label="Bultos esperados" value={resguardo.cantidad_bultos_esperada} />
                        <DetalleCampo label="Salida CEDIS" value={formatearFechaOperativa(resguardo.salida_cedis_at)} />
                        <DetalleCampo label="Recepción física" value={formatearFechaOperativa(resguardo.recepcion_fisica_at)} />
                        <DetalleCampo label="Entrega completada" value={formatearFechaOperativa(resguardo.entrega_completada_at)} />
                    </div>

                    {resguardo.pedido && (
                        <p className="text-[10px] theme-text-muted font-bold m-0">
                            Pedido {resguardo.pedido.folio || resguardo.pedido.id}
                            {resguardo.pedido.folio_remision ? ` · Remisión ${resguardo.pedido.folio_remision}` : ''}
                        </p>
                    )}

                    {resguardo.entrega_bloqueada && (
                        <p className="text-[10px] font-black uppercase text-red-600 dark:text-red-300 m-0 flex items-center gap-2">
                            <AlertTriangle className="w-4 h-4" /> Entrega bloqueada
                        </p>
                    )}
                </div>

                {resguardo.bultos?.length > 0 && (
                    <div className={`${geliaCardClass()} overflow-hidden`}>
                        <div className="p-4 border-b theme-border">
                            <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Bultos</h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="w-full border-collapse min-w-[520px]">
                                <thead>
                                    <tr className="border-b theme-border">
                                        {['Folio', 'Tipo', 'Estado', 'Recepción', 'Entrega'].map((col) => (
                                            <th key={col} className="px-4 py-3 text-left text-[9px] font-black uppercase tracking-widest theme-text-muted">
                                                {col}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {resguardo.bultos.map((bulto) => (
                                        <tr key={bulto.id} className="border-b theme-border">
                                            <td className="px-4 py-3 text-sm font-semibold">{bulto.folio || `#${bulto.id}`}</td>
                                            <td className="px-4 py-3 text-sm theme-text-muted">{bulto.tipo}</td>
                                            <td className="px-4 py-3 text-sm theme-text-muted">{bulto.estado}</td>
                                            <td className="px-4 py-3 text-[10px] theme-text-muted">{formatearFechaOperativa(bulto.recepcion_at)}</td>
                                            <td className="px-4 py-3 text-[10px] theme-text-muted">{formatearFechaOperativa(bulto.entrega_at)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {resguardo.incidencias?.length > 0 && (
                    <div className={`${geliaCardClass()} p-5 md:p-6 space-y-3`}>
                        <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">Incidencias</h2>
                        {resguardo.incidencias.map((incidencia) => (
                            <div key={incidencia.id} className="rounded-2xl border theme-border p-4 space-y-1">
                                <p className="text-sm font-black theme-text-main m-0">
                                    {incidencia.tipo_etiqueta}
                                    <span className="text-[10px] font-bold theme-text-muted ml-2 uppercase">
                                        ({incidencia.estado})
                                    </span>
                                </p>
                                {incidencia.descripcion && (
                                    <p className="text-sm theme-text-muted m-0">{incidencia.descripcion}</p>
                                )}
                                <p className="text-[10px] theme-text-muted m-0">
                                    {formatearFechaOperativa(incidencia.reportado_at)}
                                </p>
                            </div>
                        ))}
                    </div>
                )}

                <TimelineResguardo eventos={timeline} />

                <div className="flex flex-wrap justify-end gap-2">
                    {permisos.entregar && resguardo.estado === 'en_custodia' && !resguardo.entrega_bloqueada && (
                        <Link
                            href={route('punto_venta.resguardos.entrega.create', resguardo.id)}
                            className={`${THEME_BTN_PRIMARY} inline-flex items-center gap-2 min-h-[44px] px-5 text-[10px] font-black uppercase tracking-widest`}
                        >
                            <Truck className="w-4 h-4" /> Entregar
                        </Link>
                    )}
                    {permisos.recibir && resguardo.estado === 'pendiente_recepcion' && (
                        <Link
                            href={route('punto_venta.resguardos.recepcion.create', resguardo.id)}
                            className={`${THEME_BTN_PRIMARY} inline-flex items-center gap-2 min-h-[44px] px-5 text-[10px] font-black uppercase tracking-widest`}
                        >
                            <PackageCheck className="w-4 h-4" /> Recibir físicamente
                        </Link>
                    )}
                    <Link href={route('punto_venta.resguardos.index')} className={`${BTN_SECONDARY} inline-flex items-center gap-2`}>
                        <ArrowLeft className="w-4 h-4" /> Regresar al listado
                    </Link>
                </div>
            </GeliaPageShell>
        </AppLayout>
    );
}

function DetalleCampo({ label, value }) {
    return (
        <div className="rounded-2xl border theme-border p-3">
            <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">{label}</p>
            <p className="text-sm font-black theme-text-main m-0 mt-1">{value ?? '—'}</p>
        </div>
    );
}
