import React from 'react';
import { Package, RefreshCw, UserRound, Eye, UserCheck } from 'lucide-react';
import {
    formatearFechaCompacta,
    TARJETA_RECEPCION_ICONO,
    TARJETA_RECEPCION_METRICA,
    BTN_ACCION_RECEPCION_TARJETA,
    BTN_SECUNDARIO_RECEPCION_TARJETA,
    TARJETA_RECEPCION_PIE,
    tarjetaResguardoClass,
} from './resguardosStyles';
import {
    clasePieTarjetaRecepcion,
    etiquetaEstadoRecepcion,
    etiquetaRetiroResguardo,
    titularResguardo,
} from './resguardosUtils';
import { resguardoAdmiteRecepcion } from './recepcionFisicaUtils';
import { ChipEvidenciasBultosEmpaque } from './ModalEvidenciasBultosEmpaque';
import BotonConfirmarRecepcionResguardo, { BotonPasarARecepcionResguardo } from './BotonConfirmarRecepcionResguardo';
import { AccionConfirmarCustodiaResguardo } from './ModalCustodiaResguardo';
import MetaRegistroManualTarjeta from './MetaRegistroManualTarjeta';
import { abrirDetalleResguardoModal } from './resguardoDetalleModalBridge';

export default function TarjetaResguardoRecepcion({
    resguardo,
    catalogos = {},
    puedeRecibir = false,
    puedeConfirmarCustodia = false,
    paso = 'gerente',
    seleccionable = false,
    seleccionado = false,
    onToggleSeleccion,
    onRecepcionExito,
}) {
    const folio = resguardo.snapshot_folio || `#${resguardo.id}`;
    const numeroCliente = resguardo.cliente?.numero_cliente ?? '—';
    const estadoEtiqueta = etiquetaEstadoRecepcion(resguardo, catalogos, paso);
    const pieClase = clasePieTarjetaRecepcion(resguardo);
    const esPasoRecepcionista = paso === 'recepcionista';
    const fechaReferencia = esPasoRecepcionista
        ? (resguardo.recepcion_fisica_at || resguardo.salida_cedis_at)
        : resguardo.salida_cedis_at;
    const etiquetaRetiro = etiquetaRetiroResguardo(resguardo);
    const admiteRecepcion = !esPasoRecepcionista && puedeRecibir && resguardoAdmiteRecepcion(resguardo);
    const admitePasarARecepcion = !esPasoRecepcionista && puedeRecibir && Boolean(resguardo?.admite_pasar_a_recepcion ?? resguardo?.puede_pasar_a_recepcion);
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
                <MetaRegistroManualTarjeta resguardo={resguardo} />
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

            {esPasoRecepcionista && (
                <div className="rounded-xl border theme-border theme-element p-3 space-y-1">
                    <div className="flex items-center gap-2">
                        <UserCheck className="w-4 h-4 shrink-0 text-[var(--color-primario)]" aria-hidden />
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">
                            Quién retira
                        </p>
                    </div>
                    <p className="text-sm font-bold theme-text-main m-0 break-words">
                        {etiquetaRetiro}
                    </p>
                </div>
            )}

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
                    <BotonConfirmarRecepcionResguardo
                        resguardo={resguardo}
                        variant="pie"
                        etiqueta="Confirmar recepción"
                        onExito={onRecepcionExito}
                    />
                ) : admitePasarARecepcion ? (
                    <BotonPasarARecepcionResguardo
                        resguardo={resguardo}
                        variant="pie"
                        onExito={onRecepcionExito}
                    />
                ) : admiteCustodia ? (
                    <AccionConfirmarCustodiaResguardo
                        resguardo={resguardo}
                        className={BTN_ACCION_RECEPCION_TARJETA}
                        onExito={onRecepcionExito}
                    />
                ) : (
                    <div className={`${TARJETA_RECEPCION_PIE} ${pieClase}`} aria-live="polite">
                        <PieContenido />
                    </div>
                )}
                <button
                    type="button"
                    onClick={() => abrirDetalleResguardoModal(resguardo.id, resguardo)}
                    className={BTN_SECUNDARIO_RECEPCION_TARJETA}
                >
                    <Eye className="w-4 h-4 shrink-0" aria-hidden />
                    <span>Ver detalle</span>
                </button>
            </div>
        </article>
    );
}
