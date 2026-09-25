import React from 'react';
import { AlertTriangle, Clock, Eye, Package, RefreshCw, UserCheck, UserRound } from 'lucide-react';
import {
    BADGE_BASE,
    BTN_ACCION_RECEPCION_TARJETA,
    BTN_SECUNDARIO_RECEPCION_TARJETA,
    ICONO_METRICA_EXITO,
    ICONO_METRICA_INFO,
    TARJETA_RECEPCION_METRICA,
    TARJETA_RECEPCION_PIE,
    TONO_AVISO,
    TONO_PELIGRO,
    badgeAntiguedad,
    badgeEstadoResguardo,
    claseTextoPlazo,
    formatearFechaCompacta,
    tarjetaResguardoClass,
} from './resguardosStyles';
import {
    clasePieTarjetaRecepcion,
    etiquetaEstadoRecepcion,
    etiquetaRetiroResguardo,
    plazosOperativosResguardo,
    titularResguardo,
} from './resguardosUtils';
import { cantidadBultosPendiente, resguardoAdmiteRecepcion } from './recepcionFisicaUtils';
import { ChipEvidenciasBultosEmpaque } from './ModalEvidenciasBultosEmpaque';
import BotonConfirmarRecepcionResguardo, { BotonPasarARecepcionResguardo } from './BotonConfirmarRecepcionResguardo';
import { AccionConfirmarCustodiaResguardo } from './ModalCustodiaResguardo';
import { AccionEntregaResguardo } from './ModalEntregaResguardo';
import AccionReponerVencidoResguardo from './AccionReponerVencidoResguardo';
import MetaRegistroManualTarjeta from './MetaRegistroManualTarjeta';
import { abrirDetalleResguardoModal } from './resguardoDetalleModalBridge';

export default function TarjetaResguardoOperativa({
    resguardo,
    bandeja = 'por_recibir',
    catalogos = {},
    permisos = {},
    puedeRecibir = false,
    puedeConfirmarCustodia = false,
    puedeEntregar = false,
    paso = 'gerente',
    seleccionable = false,
    seleccionado = false,
    onToggleSeleccion,
    onRecepcionExito,
    onEntregaExito,
    onReponerExito,
}) {
    const folio = resguardo.snapshot_folio || `#${resguardo.id}`;
    const numeroCliente = resguardo.cliente?.numero_cliente ?? '—';
    const esPorRecibir = bandeja === 'por_recibir';
    const esPasoRecepcionista = esPorRecibir && paso === 'recepcionista';
    const fechaReferencia = esPasoRecepcionista
        ? (resguardo.recepcion_fisica_at || resguardo.salida_cedis_at)
        : bandeja === 'en_custodia'
            ? (resguardo.recepcion_fisica_at || resguardo.custodia_confirmada_at)
            : resguardo.salida_cedis_at;
    const bultosActuales = esPasoRecepcionista
        ? (resguardo.cantidad_bultos_en_custodia ?? 0)
        : bandeja === 'en_custodia'
            ? (resguardo.cantidad_bultos_en_custodia ?? resguardo.cantidad_bultos_esperada ?? 0)
            : (resguardo.cantidad_bultos_recibida ?? 0);
    const admiteRecepcion = esPorRecibir && !esPasoRecepcionista && puedeRecibir && resguardoAdmiteRecepcion(resguardo);
    const admitePasarARecepcion = esPorRecibir && !esPasoRecepcionista && puedeRecibir && Boolean(resguardo?.admite_pasar_a_recepcion ?? resguardo?.puede_pasar_a_recepcion);
    const admiteCustodia = esPasoRecepcionista && puedeConfirmarCustodia && Boolean(resguardo?.admite_confirmacion_custodia ?? resguardo?.puede_confirmar_custodia);
    const admiteEntrega = puedeEntregar && resguardo.estado === 'en_custodia' && !resguardo.entrega_bloqueada;
    const plazos = plazosOperativosResguardo(resguardo);
    const clasificaciones = Object.entries(resguardo.clasificaciones || {})
        .filter(([, activa]) => activa)
        .map(([clave]) => ({ clave, etiqueta: catalogos.antiguedades?.[clave] || clave }));
    const pendientes = cantidadBultosPendiente(resguardo);

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
                            className="text-2xl sm:text-3xl font-black m-0 mt-0.5 truncate tabular-nums text-[var(--color-primario)]"
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
                        {etiquetaRetiroResguardo(resguardo)}
                    </p>
                </div>
            )}

            <div className="grid grid-cols-2 gap-2">
                <div className={TARJETA_RECEPCION_METRICA}>
                    <span className={ICONO_METRICA_INFO}>
                        <Package className="w-4 h-4" aria-hidden />
                    </span>
                    <div className="min-w-0">
                        <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted m-0">Bultos</p>
                        <p className="text-lg font-black theme-text-main m-0 tabular-nums">
                            {bultosActuales}/{resguardo.cantidad_bultos_esperada ?? 0}
                        </p>
                    </div>
                </div>
                <div className={TARJETA_RECEPCION_METRICA}>
                    <span className={ICONO_METRICA_EXITO}>
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
                <ChipEvidenciasBultosEmpaque bultos={resguardo.bultos_empaque_cedis} folio={folio} />
            )}

            <div className="flex flex-wrap gap-1.5">
                <span className={`${BADGE_BASE} ${badgeEstadoResguardo(resguardo.estado)}`}>
                    {catalogos.estados?.[resguardo.estado] || resguardo.estado}
                </span>
                {clasificaciones.map(({ clave, etiqueta }) => (
                    <span key={clave} className={`${BADGE_BASE} ${badgeAntiguedad(clave)}`}>
                        {etiqueta}
                    </span>
                ))}
                {(resguardo.incidencias_abiertas_count || 0) > 0 && (
                    <span className={`${BADGE_BASE} ${TONO_AVISO}`}>
                        <AlertTriangle className="w-3 h-3" />
                        {resguardo.incidencias_abiertas_count} incidencia{resguardo.incidencias_abiertas_count === 1 ? '' : 's'}
                    </span>
                )}
                {resguardo.entrega_bloqueada && (
                    <span className={`${BADGE_BASE} ${TONO_PELIGRO}`}>
                        <AlertTriangle className="w-3 h-3" />
                        Entrega bloqueada
                    </span>
                )}
            </div>

            {esPorRecibir && !esPasoRecepcionista && pendientes > 0 && (
                <p className="text-[10px] font-bold text-[var(--color-aviso)] m-0">
                    {pendientes} bulto{pendientes === 1 ? '' : 's'} pendiente{pendientes === 1 ? '' : 's'}
                </p>
            )}

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
                {admiteRecepcion ? (
                    <BotonConfirmarRecepcionResguardo
                        resguardo={resguardo}
                        variant="pie"
                        etiqueta="Confirmar recepción"
                        onExito={onRecepcionExito}
                    />
                ) : admitePasarARecepcion ? (
                    <BotonPasarARecepcionResguardo resguardo={resguardo} variant="pie" onExito={onRecepcionExito} />
                ) : admiteCustodia ? (
                    <AccionConfirmarCustodiaResguardo
                        resguardo={resguardo}
                        className={BTN_ACCION_RECEPCION_TARJETA}
                        onExito={onRecepcionExito}
                    />
                ) : admiteEntrega ? (
                    <AccionEntregaResguardo resguardo={resguardo} variant="pie" onExito={onEntregaExito} />
                ) : esPorRecibir ? (
                    <div className={`${TARJETA_RECEPCION_PIE} ${clasePieTarjetaRecepcion(resguardo)}`} aria-live="polite">
                        <RefreshCw className="w-4 h-4 shrink-0" aria-hidden />
                        <span>{etiquetaEstadoRecepcion(resguardo, catalogos, paso)}</span>
                    </div>
                ) : null}
                <AccionReponerVencidoResguardo resguardo={resguardo} permisos={permisos} onExito={onReponerExito} />
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
