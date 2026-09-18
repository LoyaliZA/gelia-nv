import React from 'react';
import { Link } from '@inertiajs/react';
import { AlertTriangle, Clock, Eye, Package, UserRound } from 'lucide-react';
import {
    badgeAntiguedad,
    badgeEstadoResguardo,
    BTN_SECUNDARIO_RECEPCION_TARJETA,
    formatearFechaCompacta,
    TARJETA_RECEPCION_ICONO,
    TARJETA_RECEPCION_METRICA,
    tarjetaResguardoClass,
} from './resguardosStyles';
import {
    plazosOperativosResguardo,
    titularResguardo,
} from './resguardosUtils';
import { ChipEvidenciasBultosEmpaque } from './ModalEvidenciasBultosEmpaque';
import { AccionEntregaResguardo } from './ModalEntregaResguardo';
import AccionReponerVencidoResguardo from './AccionReponerVencidoResguardo';

function claseTextoPlazo(clasificacion) {
    if (clasificacion === 'vencido') return 'text-red-700 dark:text-red-300';
    if (clasificacion === 'proximo_a_vencer') return 'text-amber-700 dark:text-amber-300';
    return 'theme-text-muted';
}

export default function TarjetaResguardoEnCustodia({
    resguardo,
    catalogos = {},
    permisos = {},
    puedeEntregar = false,
    seleccionable = false,
    seleccionado = false,
    onToggleSeleccion,
    onReponerExito,
    onEntregaExito,
}) {
    const folio = resguardo.snapshot_folio || `#${resguardo.id}`;
    const numeroCliente = resguardo.cliente?.numero_cliente ?? '—';
    const fechaReferencia = resguardo.recepcion_fisica_at || resguardo.custodia_confirmada_at;
    const plazos = plazosOperativosResguardo(resguardo);
    const clasificaciones = Object.entries(resguardo.clasificaciones || {})
        .filter(([, activa]) => activa)
        .map(([clave]) => ({ clave, etiqueta: catalogos.antiguedades?.[clave] || clave }));
    const admiteEntrega = puedeEntregar
        && resguardo.estado === 'en_custodia'
        && !resguardo.entrega_bloqueada;

    return (
        <article className={`${tarjetaResguardoClass(resguardo)} p-4 space-y-3`}>
            <header className="flex items-start justify-between gap-3 pb-3 border-b theme-border">
                <div className="flex items-start gap-3 min-w-0">
                    {seleccionable && (
                        <input
                            type="checkbox"
                            className="mt-1 h-5 w-5 shrink-0"
                            checked={seleccionado}
                            onChange={() => onToggleSeleccion?.(resguardo.id)}
                            aria-label={`Seleccionar ${folio}`}
                        />
                    )}
                    <div className="min-w-0">
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">
                            Folio del paquete
                        </p>
                        <p
                            className="text-2xl sm:text-3xl font-black m-0 mt-0.5 truncate tabular-nums"
                            style={{ color: 'var(--color-primario)' }}
                            title={folio}
                        >
                            {folio}
                        </p>
                    </div>
                </div>
                {resguardo.sucursal?.nombre && (
                    <span className="shrink-0 inline-flex px-2 py-1 rounded-lg text-[9px] font-black uppercase tracking-wide bg-purple-500/15 text-purple-700 dark:text-purple-300 max-w-[40%] truncate">
                        {resguardo.sucursal.nombre}
                    </span>
                )}
            </header>

            <div className="space-y-1">
                <div className="flex items-center justify-between gap-2">
                    <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">
                        Titular del paquete
                    </p>
                    <p className="text-[9px] font-bold theme-text-muted m-0 shrink-0">
                        {formatearFechaCompacta(fechaReferencia)}
                    </p>
                </div>
                <p className="text-sm sm:text-base font-black theme-text-main uppercase m-0 leading-snug break-words">
                    {titularResguardo(resguardo)}
                </p>
            </div>

            <div className="grid grid-cols-2 gap-2">
                <div className={TARJETA_RECEPCION_METRICA}>
                    <span className={`${TARJETA_RECEPCION_ICONO} bg-sky-500/15 text-sky-700 dark:text-sky-300`}>
                        <Package className="w-4 h-4" aria-hidden />
                    </span>
                    <div className="min-w-0">
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Bultos</p>
                        <p className="text-lg font-black theme-text-main m-0 tabular-nums">
                            {resguardo.cantidad_bultos_en_custodia ?? resguardo.cantidad_bultos_esperada ?? 0}
                            /{resguardo.cantidad_bultos_esperada ?? 0}
                        </p>
                    </div>
                </div>
                <div className={TARJETA_RECEPCION_METRICA}>
                    <span className={`${TARJETA_RECEPCION_ICONO} bg-emerald-500/15 text-emerald-700 dark:text-emerald-300`}>
                        <UserRound className="w-4 h-4" aria-hidden />
                    </span>
                    <div className="min-w-0">
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">No. cliente</p>
                        <p className="text-lg font-black theme-text-main m-0 tabular-nums truncate" title={String(numeroCliente)}>
                            {numeroCliente}
                        </p>
                    </div>
                </div>
            </div>

            {(resguardo.bultos_empaque_cedis?.length ?? 0) > 0 && (
                <ChipEvidenciasBultosEmpaque
                    bultos={resguardo.bultos_empaque_cedis}
                    folio={folio}
                />
            )}

            <div className="flex flex-wrap gap-1.5">
                <span className={`inline-flex px-2 py-1 rounded-lg text-[9px] font-black uppercase tracking-wide ${badgeEstadoResguardo(resguardo.estado)}`}>
                    {catalogos.estados?.[resguardo.estado] || resguardo.estado}
                </span>
                {clasificaciones.map(({ clave, etiqueta }) => (
                    <span
                        key={clave}
                        className={`inline-flex px-2 py-1 rounded-lg text-[9px] font-black uppercase tracking-wide ${badgeAntiguedad(clave)}`}
                    >
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

            {plazos.length > 0 && (
                <div className="rounded-xl border theme-border theme-element p-3 space-y-1">
                    {plazos.map(({ id, etiqueta, fecha, clasificacion }) => (
                        <p key={id} className={`text-[10px] m-0 flex items-center gap-1.5 ${claseTextoPlazo(clasificacion)}`}>
                            <Clock className="w-3.5 h-3.5 shrink-0" aria-hidden />
                            <span>{etiqueta}: {formatearFechaCompacta(fecha)}</span>
                        </p>
                    ))}
                </div>
            )}

            <div className="flex flex-col gap-2 pt-1">
                {admiteEntrega && (
                    <AccionEntregaResguardo
                        resguardo={resguardo}
                        variant="pie"
                        onExito={onEntregaExito}
                    />
                )}
                <AccionReponerVencidoResguardo
                    resguardo={resguardo}
                    permisos={permisos}
                    onExito={onReponerExito}
                />
                <Link
                    href={route('punto_venta.resguardos.show', resguardo.id)}
                    className={BTN_SECUNDARIO_RECEPCION_TARJETA}
                >
                    <Eye className="w-4 h-4 shrink-0" aria-hidden />
                    <span>Ver detalle</span>
                </Link>
            </div>
        </article>
    );
}
