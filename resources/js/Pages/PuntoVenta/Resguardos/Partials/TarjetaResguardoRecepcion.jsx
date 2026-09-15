import React from 'react';
import { Link } from '@inertiajs/react';
import { Package, PackageCheck, RefreshCw, UserRound, Eye } from 'lucide-react';
import {
    formatearFechaCompacta,
    TARJETA_RECEPCION_ICONO,
    TARJETA_RECEPCION_METRICA,
    TARJETA_RECEPCION_PIE,
    tarjetaResguardoClass,
} from './resguardosStyles';
import {
    clasePieTarjetaRecepcion,
    etiquetaEstadoRecepcion,
    titularResguardo,
} from './resguardosUtils';
import { resguardoAdmiteRecepcion } from './recepcionFisicaUtils';
import { ChipEvidenciasBultosEmpaque } from './ModalEvidenciasBultosEmpaque';

export default function TarjetaResguardoRecepcion({
    resguardo,
    catalogos = {},
    puedeRecibir = false,
    puedeConfirmarCustodia = false,
    paso = 'gerente',
}) {
    const folio = resguardo.snapshot_folio || `#${resguardo.id}`;
    const numeroCliente = resguardo.cliente?.numero_cliente ?? '—';
    const estadoEtiqueta = etiquetaEstadoRecepcion(resguardo, catalogos, paso);
    const pieClase = clasePieTarjetaRecepcion(resguardo);
    const fechaReferencia = resguardo.salida_cedis_at;
    const esPasoRecepcionista = paso === 'recepcionista';
    const admiteRecepcion = !esPasoRecepcionista && puedeRecibir && resguardoAdmiteRecepcion(resguardo);
    const admiteCustodia = esPasoRecepcionista && puedeConfirmarCustodia && Boolean(resguardo?.admite_confirmacion_custodia ?? resguardo?.puede_confirmar_custodia);

    const PieContenido = () => (
        <>
            <RefreshCw className="w-4 h-4 shrink-0" aria-hidden />
            <span>{estadoEtiqueta}</span>
        </>
    );

    return (
        <article className={`${tarjetaResguardoClass(resguardo)} p-4 space-y-3`}>
            <header className="flex items-start justify-between gap-3 pb-3 border-b theme-border">
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
                            {esPasoRecepcionista
                                ? `${resguardo.cantidad_bultos_en_custodia ?? 0}/${resguardo.cantidad_bultos_esperada ?? 0}`
                                : `${resguardo.cantidad_bultos_recibida ?? 0}/${resguardo.cantidad_bultos_esperada ?? 0}`}
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

            <div className="flex flex-col gap-2 pt-1">
                {admiteRecepcion ? (
                    <Link
                        href={route('punto_venta.resguardos.recepcion.create', resguardo.id)}
                        className={`${TARJETA_RECEPCION_PIE} ${pieClase} no-underline`}
                    >
                        <PackageCheck className="w-4 h-4 shrink-0" aria-hidden />
                        <span>Recibir paquete</span>
                    </Link>
                ) : admiteCustodia ? (
                    <Link
                        href={route('punto_venta.resguardos.custodia.create', resguardo.id)}
                        className={`${TARJETA_RECEPCION_PIE} ${pieClase} no-underline`}
                    >
                        <PackageCheck className="w-4 h-4 shrink-0" aria-hidden />
                        <span>Confirmar custodia</span>
                    </Link>
                ) : (
                    <div className={`${TARJETA_RECEPCION_PIE} ${pieClase}`} aria-live="polite">
                        <PieContenido />
                    </div>
                )}
                <Link
                    href={route('punto_venta.resguardos.show', resguardo.id)}
                    className={`${TARJETA_RECEPCION_PIE} border theme-border theme-element theme-text-muted no-underline hover:border-[var(--color-primario)]/40`}
                >
                    <Eye className="w-4 h-4 shrink-0" aria-hidden />
                    <span>Ver detalle</span>
                </Link>
            </div>
        </article>
    );
}
