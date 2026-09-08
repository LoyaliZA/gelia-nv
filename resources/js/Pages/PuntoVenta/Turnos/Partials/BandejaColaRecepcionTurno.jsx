import React, { useCallback, useState } from 'react';
import axios from 'axios';
import { AlertTriangle, Loader2, RefreshCw, UserMinus } from 'lucide-react';
import { geliaCardClass, THEME_BTN_SECONDARY } from '../../../../utils/geliaTheme';
import ModalBajaColaTurno from './ModalBajaColaTurno';
import { etiquetaEstadoTurno } from './altaTurnoUtils';
import { badgeEstadoTurno } from './turnosStyles';
import {
    abreviarNombreLlamado,
    etiquetaAtencionAsignado,
    formatearEsperaTurno,
    resumenBandejaRecepcion,
    resumenPrioridadTurno,
} from './recepcionTurnoUtils';
import {
    claveIdempotenciaOperacionTurno,
    esConflictoVersionTurno,
    mensajeErrorOperacionTurno,
    puedeDarBajaCola,
    renovarClaveIdempotenciaOperacionTurno,
} from './bandejaRecepcionUtils';

export default function BandejaColaRecepcionTurno({
    bandeja = null,
    cargando = false,
    error = null,
    refrescar,
    permisos = {},
    catalogos = {},
    onTurnoDadoDeBaja,
}) {
    const puedeVer = Boolean(permisos.ver);

    const [turnoBaja, setTurnoBaja] = useState(null);
    const [procesandoBaja, setProcesandoBaja] = useState(false);
    const [mensajeAccion, setMensajeAccion] = useState(null);

    const enCola = bandeja?.en_cola ?? [];
    const asignados = bandeja?.asignados ?? [];
    const vacia = enCola.length === 0 && asignados.length === 0;
    const { enEspera, asignados: totalAsignados } = resumenBandejaRecepcion(bandeja);

    const manejarConflicto = useCallback(async () => {
        setMensajeAccion('Otro terminal modificó el turno. Actualizando bandeja…');
        await refrescar?.({ silencioso: true });
        setMensajeAccion(null);
        setTurnoBaja(null);
    }, [refrescar]);

    const confirmarBaja = useCallback(async ({ motivo, motivoDetalle }) => {
        if (!turnoBaja || procesandoBaja) return;

        setProcesandoBaja(true);
        setMensajeAccion(null);

        try {
            await axios.post(
                route('punto_venta.turnos.baja_cola', turnoBaja.id),
                {
                    version: turnoBaja.version,
                    idempotency_key: claveIdempotenciaOperacionTurno('baja-cola', turnoBaja.id),
                    motivo,
                    motivo_detalle: motivoDetalle,
                },
                { headers: { Accept: 'application/json' } },
            );

            renovarClaveIdempotenciaOperacionTurno('baja-cola', turnoBaja.id);
            setTurnoBaja(null);
            setMensajeAccion('Turno dado de baja correctamente.');
            await refrescar?.({ silencioso: true });
            onTurnoDadoDeBaja?.();
        } catch (err) {
            if (esConflictoVersionTurno(err)) {
                await manejarConflicto();
            } else {
                setMensajeAccion(mensajeErrorOperacionTurno(err, 'baja de cola'));
            }
        } finally {
            setProcesandoBaja(false);
        }
    }, [turnoBaja, procesandoBaja, refrescar, manejarConflicto, onTurnoDadoDeBaja]);

    if (!puedeVer) {
        return null;
    }

    return (
        <section className="space-y-3 min-w-0" aria-labelledby="bandeja-cola-titulo" data-bandeja-cola-root>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 id="bandeja-cola-titulo" className="text-xs font-black uppercase tracking-widest theme-text-muted m-0">
                    Detalle de fila
                </h2>
                <button
                    type="button"
                    className={`${THEME_BTN_SECONDARY} min-h-[44px] px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest inline-flex items-center gap-1.5`}
                    disabled={cargando}
                    onClick={() => refrescar?.()}
                >
                    {cargando ? <Loader2 className="w-3.5 h-3.5 animate-spin" aria-hidden /> : <RefreshCw className="w-3.5 h-3.5" aria-hidden />}
                    Actualizar
                </button>
            </div>

            {error && (
                <div className={`${geliaCardClass()} p-3 flex items-start gap-2`}>
                    <AlertTriangle className="w-4 h-4 text-red-500 shrink-0" aria-hidden />
                    <p className="text-xs font-semibold text-red-600 dark:text-red-400 m-0">{error}</p>
                </div>
            )}

            {mensajeAccion && (
                <div className={`${geliaCardClass()} p-3`}>
                    <p className="text-xs font-semibold theme-text-muted m-0">{mensajeAccion}</p>
                </div>
            )}

            {cargando && vacia && !error && (
                <div className={`${geliaCardClass()} p-4 text-center`}>
                    <Loader2 className="w-6 h-6 mx-auto animate-spin theme-text-muted" aria-hidden />
                    <p className="text-xs font-semibold theme-text-muted mt-2 m-0">Cargando bandeja…</p>
                </div>
            )}

            {!cargando && vacia && !error && (
                <div className={`${geliaCardClass()} p-4 text-center`}>
                    <p className="text-sm font-bold theme-text-main m-0">Sin turnos en espera ni asignados</p>
                    <p className="text-[11px] font-semibold theme-text-muted m-0 mt-1">
                        Los nuevos registros aparecerán aquí automáticamente.
                    </p>
                </div>
            )}

            {enCola.length > 0 && (
                <ListaTurnos
                    titulo={`En espera (${enEspera})`}
                    turnos={enCola}
                    catalogos={catalogos}
                    permisos={permisos}
                    servicio={catalogos.servicio}
                    onBaja={setTurnoBaja}
                />
            )}

            {asignados.length > 0 && (
                <ListaTurnos
                    titulo={`Asignados (${totalAsignados})`}
                    turnos={asignados}
                    catalogos={catalogos}
                    permisos={permisos}
                    servicio={catalogos.servicio}
                    mostrarAtencion
                />
            )}

            <ModalBajaColaTurno
                abierto={Boolean(turnoBaja)}
                turno={turnoBaja}
                catalogos={catalogos}
                procesando={procesandoBaja}
                onClose={() => !procesandoBaja && setTurnoBaja(null)}
                onConfirmar={confirmarBaja}
            />
        </section>
    );
}

function ListaTurnos({
    titulo,
    turnos,
    catalogos,
    permisos,
    servicio = 'Ventas',
    mostrarAtencion = false,
    onBaja,
}) {
    return (
        <div className="space-y-1.5">
            <h3 className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">{titulo}</h3>
            <ul className="space-y-1.5 m-0 p-0 list-none">
                {turnos.map((turno) => (
                    <li key={turno.id} className={`${geliaCardClass()} p-3`}>
                        <div className="grid grid-cols-[auto_1fr_auto] gap-x-3 gap-y-1 items-start">
                            <p className="text-sm font-black theme-text-main m-0 tabular-nums">{turno.folio}</p>
                            <p className="text-xs font-semibold theme-text-main m-0 truncate" title={turno.snapshot_nombre_llamado || undefined}>
                                {abreviarNombreLlamado(turno.snapshot_nombre_llamado)}
                            </p>
                            <span className={`inline-flex px-2 py-0.5 rounded-lg text-[9px] font-black uppercase shrink-0 ${badgeEstadoTurno(turno.estado)}`}>
                                {etiquetaEstadoTurno(turno.estado, catalogos)}
                            </span>

                            <p className="text-[10px] font-semibold theme-text-muted m-0 col-span-3 sm:col-span-1 sm:col-start-2">
                                {servicio}
                                <span className="mx-1.5" aria-hidden>·</span>
                                {resumenPrioridadTurno(turno)}
                                <span className="mx-1.5" aria-hidden>·</span>
                                {formatearEsperaTurno(turno)}
                            </p>

                            {mostrarAtencion && (
                                <p className="text-[10px] font-semibold theme-text-muted m-0 col-span-3">
                                    {turno.atencion?.primer_nombre ? (
                                        <>
                                            <span className="font-bold theme-text-main">{turno.atencion.primer_nombre}</span>
                                            <span className="mx-1.5" aria-hidden>·</span>
                                            {etiquetaAtencionAsignado(turno)}
                                        </>
                                    ) : (
                                        etiquetaAtencionAsignado(turno)
                                    )}
                                </p>
                            )}
                        </div>

                        {puedeDarBajaCola(turno, permisos) && (
                            <button
                                type="button"
                                className="mt-2 w-full min-h-[44px] rounded-xl text-[10px] font-black uppercase tracking-widest border theme-border theme-element theme-text-main inline-flex items-center justify-center gap-1.5"
                                onClick={() => onBaja?.(turno)}
                            >
                                <UserMinus className="w-3.5 h-3.5" aria-hidden />
                                Dar de baja
                            </button>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}
