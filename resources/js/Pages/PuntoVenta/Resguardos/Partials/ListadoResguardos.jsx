import React from 'react';
import { Link } from '@inertiajs/react';
import { Eye, AlertTriangle, PackageCheck, Truck } from 'lucide-react';
import {
    badgeAntiguedad,
    badgeEstadoResguardo,
    formatearFechaOperativa,
    tarjetaResguardoClass,
    BTN_SECONDARY,
} from './resguardosStyles';
import { THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import {
    claseVistaTabla,
    claseVistaTarjetas,
    etiquetasClasificacionActivas,
    mensajeVacioBandeja,
    plazosOperativosResguardo,
    referenciaCliente,
} from './resguardosUtils';
import { geliaCardClass } from '../../../../utils/geliaTheme';
import AccionReponerVencidoResguardo from './AccionReponerVencidoResguardo';

function BadgesResguardo({ resguardo, catalogos }) {
    const estadoEtiqueta = catalogos.estados?.[resguardo.estado] || resguardo.estado;
    const clasificaciones = Object.entries(resguardo.clasificaciones || {})
        .filter(([, activa]) => activa)
        .map(([clave]) => ({ clave, etiqueta: catalogos.antiguedades?.[clave] || clave }));

    return (
        <div className="flex flex-wrap gap-1.5">
            <span className={`inline-flex px-2 py-1 rounded-lg text-[9px] font-black uppercase tracking-wide ${badgeEstadoResguardo(resguardo.estado)}`}>
                {estadoEtiqueta}
            </span>
            {clasificaciones.map(({ clave, etiqueta }) => (
                <span key={clave} className={`inline-flex px-2 py-1 rounded-lg text-[9px] font-black uppercase tracking-wide ${badgeAntiguedad(clave)}`}>
                    {etiqueta}
                </span>
            ))}
            {(resguardo.incidencias_abiertas_count || 0) > 0 && (
                <span className="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[9px] font-black uppercase tracking-wide bg-purple-500/15 text-purple-700 dark:text-purple-300">
                    <AlertTriangle className="w-3 h-3" />
                    {resguardo.incidencias_abiertas_count} incidencia{resguardo.incidencias_abiertas_count === 1 ? '' : 's'}
                </span>
            )}
            {resguardo.entrega_bloqueada && (
                <span className="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[9px] font-black uppercase tracking-wide bg-red-500/15 text-red-700 dark:text-red-300">
                    <AlertTriangle className="w-3 h-3" />
                    Entrega bloqueada
                </span>
            )}
        </div>
    );
}

function claseTextoPlazo(clasificacion) {
    if (clasificacion === 'vencido') return 'font-bold text-red-700 dark:text-red-300';
    if (clasificacion === 'rezagado') return 'font-bold text-orange-700 dark:text-orange-300';
    if (clasificacion === 'proximo_a_vencer') return 'font-bold text-amber-700 dark:text-amber-300';
    return 'theme-text-muted';
}

function FechasOperativasResguardo({ resguardo, bandeja }) {
    const plazos = plazosOperativosResguardo(resguardo);

    if (plazos.length > 0) {
        return (
            <div className="space-y-0.5">
                {plazos.map(({ id, etiqueta, fecha, clasificacion }) => (
                    <p
                        key={id}
                        className={`text-[10px] m-0 ${claseTextoPlazo(clasificacion)}`}
                    >
                        {etiqueta}: {formatearFechaOperativa(fecha)}
                    </p>
                ))}
            </div>
        );
    }

    if (bandeja === 'en_custodia') {
        return (
            <p className="text-[10px] theme-text-muted m-0">
                Recepción: {formatearFechaOperativa(resguardo.recepcion_fisica_at)}
            </p>
        );
    }
    if (bandeja === 'incidencias') {
        return (
            <p className="text-[10px] theme-text-muted m-0">
                Salida CEDIS: {formatearFechaOperativa(resguardo.salida_cedis_at)}
            </p>
        );
    }
    return (
        <p className="text-[10px] theme-text-muted m-0">
            Salida CEDIS: {formatearFechaOperativa(resguardo.salida_cedis_at)}
        </p>
    );
}

function TarjetaResguardo({
    resguardo,
    bandeja,
    catalogos,
    permisos = {},
    puedeRecibir,
    puedeEntregar,
    seleccionable = false,
    seleccionado = false,
    onToggleSeleccion,
    onReponerExito,
}) {
    return (
        <div className={tarjetaResguardoClass(resguardo)}>
            <div className="flex items-start justify-between gap-3">
                <div className="flex items-start gap-3 min-w-0">
                    {seleccionable && (
                        <input
                            type="checkbox"
                            className="mt-1 h-5 w-5 shrink-0"
                            checked={seleccionado}
                            onChange={() => onToggleSeleccion?.(resguardo.id)}
                            aria-label={`Seleccionar ${resguardo.snapshot_folio || `resguardo ${resguardo.id}`} para entrega conjunta`}
                        />
                    )}
                    <div className="min-w-0 space-y-1">
                    <p className="text-sm font-black theme-text-main m-0 truncate">
                        {resguardo.snapshot_folio || `Resguardo #${resguardo.id}`}
                    </p>
                    <p className="text-[10px] font-bold theme-text-muted m-0">
                        Cliente {referenciaCliente(resguardo)}
                    </p>
                    <FechasOperativasResguardo resguardo={resguardo} bandeja={bandeja} />
                    </div>
                </div>
                <p className="text-[10px] font-black theme-text-muted m-0 shrink-0">
                    {resguardo.cantidad_bultos_esperada} bulto{resguardo.cantidad_bultos_esperada === 1 ? '' : 's'}
                </p>
            </div>
            <BadgesResguardo resguardo={resguardo} catalogos={catalogos} />
            <div className="flex flex-col gap-2">
                {puedeEntregar && bandeja === 'en_custodia' && resguardo.estado === 'en_custodia' && !resguardo.entrega_bloqueada && (
                    <Link
                        href={route('punto_venta.resguardos.entrega.create', resguardo.id)}
                        className={`${THEME_BTN_PRIMARY} w-full inline-flex items-center justify-center gap-2 min-h-[44px] text-[10px] font-black uppercase tracking-widest`}
                    >
                        <Truck className="w-4 h-4" /> Entregar
                    </Link>
                )}
                {puedeRecibir && resguardo.estado === 'pendiente_recepcion' && (
                    <Link
                        href={route('punto_venta.resguardos.recepcion.create', resguardo.id)}
                        className={`${THEME_BTN_PRIMARY} w-full inline-flex items-center justify-center gap-2 min-h-[44px] text-[10px] font-black uppercase tracking-widest`}
                    >
                        <PackageCheck className="w-4 h-4" /> Recibir
                    </Link>
                )}
                <AccionReponerVencidoResguardo
                    resguardo={resguardo}
                    permisos={permisos}
                    onExito={onReponerExito}
                />
                <Link
                    href={route('punto_venta.resguardos.show', resguardo.id)}
                    className={`${BTN_SECONDARY} w-full inline-flex items-center justify-center gap-2`}
                >
                    <Eye className="w-4 h-4" /> Ver detalle
                </Link>
            </div>
        </div>
    );
}

function FilaTablaResguardo({
    resguardo,
    bandeja,
    catalogos,
    permisos = {},
    puedeRecibir,
    puedeEntregar,
    seleccionable = false,
    seleccionado = false,
    onToggleSeleccion,
    onReponerExito,
}) {
    const clasificaciones = etiquetasClasificacionActivas(resguardo, catalogos.antiguedades);

    return (
        <tr className="border-b theme-border hover:bg-black/[0.02] dark:hover:bg-white/[0.02]">
            {seleccionable && (
                <td className="px-4 py-3">
                    <input
                        type="checkbox"
                        className="h-5 w-5"
                        checked={seleccionado}
                        onChange={() => onToggleSeleccion?.(resguardo.id)}
                        aria-label={`Seleccionar ${resguardo.snapshot_folio || `resguardo ${resguardo.id}`} para entrega conjunta`}
                    />
                </td>
            )}
            <td className="px-4 py-3 text-sm font-black theme-text-main">
                {resguardo.snapshot_folio || `#${resguardo.id}`}
            </td>
            <td className="px-4 py-3 text-sm font-semibold theme-text-muted">
                {referenciaCliente(resguardo)}
            </td>
            <td className="px-4 py-3 text-sm font-semibold theme-text-muted tabular-nums">
                {resguardo.cantidad_bultos_esperada}
            </td>
            <td className="px-4 py-3">
                <span className={`inline-flex px-2 py-1 rounded-lg text-[9px] font-black uppercase ${badgeEstadoResguardo(resguardo.estado)}`}>
                    {catalogos.estados?.[resguardo.estado] || resguardo.estado}
                </span>
            </td>
            <td className="px-4 py-3 text-[10px] theme-text-muted">
                {clasificaciones.length > 0 ? clasificaciones.join(' · ') : '—'}
            </td>
            <td className="px-4 py-3 text-[10px] theme-text-muted whitespace-nowrap">
                <FechasOperativasResguardo resguardo={resguardo} bandeja={bandeja} />
            </td>
            <td className="px-4 py-3 text-right">
                <div className="flex flex-wrap justify-end gap-2">
                    {puedeEntregar && bandeja === 'en_custodia' && resguardo.estado === 'en_custodia' && !resguardo.entrega_bloqueada && (
                        <Link
                            href={route('punto_venta.resguardos.entrega.create', resguardo.id)}
                            className={`${THEME_BTN_PRIMARY} inline-flex items-center gap-2 min-h-[44px] text-[10px] font-black uppercase tracking-widest`}
                        >
                            <Truck className="w-4 h-4" /> Entregar
                        </Link>
                    )}
                    {puedeRecibir && resguardo.estado === 'pendiente_recepcion' && (
                        <Link
                            href={route('punto_venta.resguardos.recepcion.create', resguardo.id)}
                            className={`${THEME_BTN_PRIMARY} inline-flex items-center gap-2 min-h-[44px] text-[10px] font-black uppercase tracking-widest`}
                        >
                            <PackageCheck className="w-4 h-4" /> Recibir
                        </Link>
                    )}
                    <AccionReponerVencidoResguardo
                        resguardo={resguardo}
                        permisos={permisos}
                        onExito={onReponerExito}
                    />
                    <Link
                        href={route('punto_venta.resguardos.show', resguardo.id)}
                        className={`${BTN_SECONDARY} inline-flex items-center gap-2`}
                    >
                        <Eye className="w-4 h-4" /> Detalle
                    </Link>
                </div>
            </td>
        </tr>
    );
}

export default function ListadoResguardos({
    resguardos,
    bandeja,
    catalogos = {},
    permisos = {},
    hayFiltrosActivos = false,
    onLimpiarFiltros,
    puedeRecibir = false,
    puedeEntregar = false,
    idsSeleccionados = [],
    onToggleSeleccion,
    onReponerExito,
}) {
    const items = resguardos?.data || [];
    const seleccionable = puedeEntregar && bandeja === 'en_custodia' && Boolean(onToggleSeleccion);

    if (items.length === 0) {
        return (
            <div className={`${geliaCardClass()} p-10 md:p-16 text-center space-y-3`}>
                <p className="text-sm theme-text-muted font-bold uppercase tracking-widest m-0">
                    {mensajeVacioBandeja(bandeja, catalogos.bandejas, hayFiltrosActivos)}
                </p>
                {hayFiltrosActivos && onLimpiarFiltros && (
                    <button type="button" onClick={onLimpiarFiltros} className={`${BTN_SECONDARY} text-xs`}>
                        Limpiar filtros
                    </button>
                )}
            </div>
        );
    }

    return (
        <div className={`${geliaCardClass()} overflow-hidden`}>
            <div className={`${claseVistaTarjetas()} p-4 space-y-3`}>
                {items.map((resguardo) => (
                    <TarjetaResguardo
                        key={resguardo.id}
                        resguardo={resguardo}
                        bandeja={bandeja}
                        catalogos={catalogos}
                        permisos={permisos}
                        puedeRecibir={puedeRecibir}
                        puedeEntregar={puedeEntregar}
                        seleccionable={seleccionable && resguardo.estado === 'en_custodia' && !resguardo.entrega_bloqueada}
                        seleccionado={idsSeleccionados.includes(resguardo.id)}
                        onToggleSeleccion={onToggleSeleccion}
                        onReponerExito={onReponerExito}
                    />
                ))}
            </div>

            <div className={`${claseVistaTabla()} overflow-x-auto`}>
                <table className="w-full border-collapse min-w-[900px]">
                    <thead>
                        <tr className="border-b-2 border-[var(--color-primario)]/30">
                            {(seleccionable ? ['', 'Folio', 'Cliente', 'Bultos', 'Estado', 'Antigüedad', 'Fecha operativa', ''] : ['Folio', 'Cliente', 'Bultos', 'Estado', 'Antigüedad', 'Fecha operativa', '']).map((col, idx) => (
                                <th
                                    key={`${col || 'acciones'}-${idx}`}
                                    className={`px-4 py-4 text-[9px] font-black uppercase tracking-widest theme-text-muted ${col === '' ? 'text-right' : 'text-left'}`}
                                >
                                    {col}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {items.map((resguardo) => (
                        <FilaTablaResguardo
                            key={resguardo.id}
                            resguardo={resguardo}
                            bandeja={bandeja}
                            catalogos={catalogos}
                            permisos={permisos}
                            puedeRecibir={puedeRecibir}
                            puedeEntregar={puedeEntregar}
                            seleccionable={seleccionable && resguardo.estado === 'en_custodia' && !resguardo.entrega_bloqueada}
                            seleccionado={idsSeleccionados.includes(resguardo.id)}
                            onToggleSeleccion={onToggleSeleccion}
                            onReponerExito={onReponerExito}
                        />
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
