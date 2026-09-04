import React, { useCallback, useEffect, useState } from 'react';
import axios from 'axios';
import { AlertTriangle, Loader2, RefreshCw, UserMinus, Users } from 'lucide-react';
import { geliaCardClass, THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import ModalBajaColaTurno from './ModalBajaColaTurno';
import { etiquetaEstadoTurno, etiquetasPrioridadTurno } from './altaTurnoUtils';
import { badgeEstadoTurno, badgePrioridadTurno } from './turnosStyles';
import {
    claveIdempotenciaOperacionTurno,
    esConflictoVersionTurno,
    mensajeErrorOperacionTurno,
    puedeDarBajaCola,
    renovarClaveIdempotenciaOperacionTurno,
} from './bandejaRecepcionUtils';
import useBandejaRecepcionTurno from './useBandejaRecepcionTurno';

export default function BandejaColaRecepcionTurno({
    bandeja: bandejaInicial,
    permisos = {},
    catalogos = {},
    onTurnoDadoDeBaja,
    senalRefresco = 0,
}) {
    const puedeVer = Boolean(permisos.ver);
    const {
        bandeja,
        cargando,
        error,
        refrescar,
    } = useBandejaRecepcionTurno({
        bandeja: bandejaInicial,
        habilitado: puedeVer,
    });

    const [turnoBaja, setTurnoBaja] = useState(null);
    const [procesandoBaja, setProcesandoBaja] = useState(false);
    const [mensajeAccion, setMensajeAccion] = useState(null);

    const enCola = bandeja?.en_cola ?? [];
    const asignados = bandeja?.asignados ?? [];
    const vacia = enCola.length === 0 && asignados.length === 0;

    const manejarConflicto = useCallback(async () => {
        setMensajeAccion('Otro terminal modificó el turno. Actualizando bandeja…');
        await refrescar({ silencioso: true });
        setMensajeAccion(null);
        setTurnoBaja(null);
    }, [refrescar]);

    useEffect(() => {
        if (senalRefresco > 0) {
            refrescar({ silencioso: true });
        }
    }, [senalRefresco, refrescar]);

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
            await refrescar({ silencioso: true });
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
        return (
            <div className={`${geliaCardClass()} p-6 text-center space-y-3`}>
                <Users className="w-10 h-10 mx-auto theme-text-muted" aria-hidden />
                <p className="text-sm font-bold theme-text-main m-0">Sin permiso para ver la bandeja</p>
                <p className="text-xs font-semibold theme-text-muted m-0">
                    Solicita el permiso de consulta de turnos a quien administre accesos.
                </p>
            </div>
        );
    }

    return (
        <section className="space-y-4" aria-labelledby="bandeja-cola-titulo" data-bandeja-cola-root>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                    <Users className="w-4 h-4 theme-text-muted" aria-hidden />
                    <h2 id="bandeja-cola-titulo" className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                        Cola y asignados
                    </h2>
                </div>
                <button
                    type="button"
                    className={`${THEME_BTN_PRIMARY} min-h-[44px] px-4 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest inline-flex items-center gap-2`}
                    disabled={cargando}
                    onClick={() => refrescar()}
                >
                    {cargando ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden /> : <RefreshCw className="w-4 h-4" aria-hidden />}
                    Actualizar
                </button>
            </div>

            {error && (
                <div className={`${geliaCardClass()} p-4 flex items-start gap-3`}>
                    <AlertTriangle className="w-5 h-5 text-red-500 shrink-0" aria-hidden />
                    <p className="text-sm font-semibold text-red-600 dark:text-red-400 m-0">{error}</p>
                </div>
            )}

            {mensajeAccion && (
                <div className={`${geliaCardClass()} p-4`}>
                    <p className="text-sm font-semibold theme-text-muted m-0">{mensajeAccion}</p>
                </div>
            )}

            {cargando && vacia && !error && (
                <div className={`${geliaCardClass()} p-8 text-center`}>
                    <Loader2 className="w-8 h-8 mx-auto animate-spin theme-text-muted" aria-hidden />
                    <p className="text-sm font-semibold theme-text-muted mt-3 m-0">Cargando bandeja…</p>
                </div>
            )}

            {!cargando && vacia && !error && (
                <div className={`${geliaCardClass()} p-8 text-center space-y-3`}>
                    <Users className="w-10 h-10 mx-auto theme-text-muted" aria-hidden />
                    <p className="text-sm font-bold theme-text-main m-0">Sin turnos en cola ni asignados</p>
                    <p className="text-xs font-semibold theme-text-muted m-0">
                        El refresco es solo lectura y no modifica turnos.
                    </p>
                </div>
            )}

            {enCola.length > 0 && (
                <ListaTurnos
                    titulo="En cola"
                    turnos={enCola}
                    catalogos={catalogos}
                    permisos={permisos}
                    onBaja={setTurnoBaja}
                />
            )}

            {asignados.length > 0 && (
                <ListaTurnos
                    titulo="Asignados"
                    turnos={asignados}
                    catalogos={catalogos}
                    permisos={permisos}
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
    mostrarAtencion = false,
    onBaja,
}) {
    return (
        <div className="space-y-2">
            <h3 className="text-xs font-black uppercase tracking-widest theme-text-muted m-0">{titulo}</h3>
            <ul className="space-y-2 m-0 p-0 list-none">
                {turnos.map((turno) => (
                    <li key={turno.id} className={`${geliaCardClass()} p-4 space-y-3`}>
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div className="min-w-0 flex-1">
                                <p className="text-lg font-black theme-text-main m-0">{turno.folio}</p>
                                <p className="text-sm font-semibold theme-text-muted m-0 mt-1 truncate">
                                    {turno.snapshot_nombre_llamado || '—'}
                                </p>
                            </div>
                            <span className={`inline-flex px-3 py-1.5 rounded-xl text-[10px] font-black uppercase shrink-0 ${badgeEstadoTurno(turno.estado)}`}>
                                {etiquetaEstadoTurno(turno.estado, catalogos)}
                            </span>
                        </div>

                        {etiquetasPrioridadTurno(turno).length > 0 && (
                            <div className="flex flex-wrap gap-2">
                                {etiquetasPrioridadTurno(turno).map((etiqueta) => (
                                    <span
                                        key={etiqueta}
                                        className={`inline-flex px-2 py-1 rounded-lg text-[10px] font-black uppercase ${badgePrioridadTurno(etiqueta)}`}
                                    >
                                        {etiqueta}
                                    </span>
                                ))}
                            </div>
                        )}

                        {mostrarAtencion && turno.atencion?.primer_nombre && (
                            <p className="text-xs font-semibold theme-text-muted m-0">
                                Atiende: <span className="font-bold theme-text-main">{turno.atencion.primer_nombre}</span>
                            </p>
                        )}

                        {puedeDarBajaCola(turno, permisos) && (
                            <button
                                type="button"
                                className="w-full min-h-[44px] rounded-2xl text-[10px] font-black uppercase tracking-widest border theme-border theme-element theme-text-main inline-flex items-center justify-center gap-2"
                                onClick={() => onBaja?.(turno)}
                            >
                                <UserMinus className="w-4 h-4" aria-hidden />
                                Dar de baja
                            </button>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}
