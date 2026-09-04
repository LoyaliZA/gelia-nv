import React, { useCallback, useEffect, useState } from 'react';
import axios from 'axios';
import { AlertTriangle, Play, Square, ArrowRightLeft } from 'lucide-react';
import { geliaCardClass, THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import { badgePrioridadTurno } from './turnosStyles';
import CronometroVisualTurno from './CronometroVisualTurno';
import ModalCerrarAtencionTurno from './ModalCerrarAtencionTurno';
import ModalMotivosEsperaTurno from './ModalMotivosEsperaTurno';
import ModalTransferirTurno from './ModalTransferirTurno';
import ModalSolicitarTransferenciaTurno from './ModalSolicitarTransferenciaTurno';
import {
    claveIdempotenciaOperacionTurno,
    esConflictoVersionTurno,
    etiquetasPrioridadDesdeTurno,
    estadoUiTurnoAsignado,
    mensajeErrorOperacionTurno,
    puedeCerrarAtencion,
    puedeIniciarAtencion,
    puedeTransferir,
    renovarClaveIdempotenciaOperacionTurno,
} from './tableroVentasUtils';

const BTN_ACCION = 'min-h-[44px] px-4 py-3 rounded-2xl text-[10px] font-black uppercase tracking-widest border transition-colors flex items-center justify-center gap-2';

export default function TarjetaTurnoVentas({
    turno,
    plazos = {},
    servidorAt,
    permisos = {},
    catalogos = {},
    personasTransferencia = [],
    onActualizado,
    onConflicto,
    onError,
}) {
    const [procesando, setProcesando] = useState(false);
    const [modalCerrar, setModalCerrar] = useState(false);
    const [modalEspera, setModalEspera] = useState(false);
    const [modalTransferir, setModalTransferir] = useState(false);
    const [modalSolicitarTransferencia, setModalSolicitarTransferencia] = useState(false);

    const etiquetas = etiquetasPrioridadDesdeTurno(turno);
    const estadoUi = estadoUiTurnoAsignado(turno);
    const atencion = turno?.atencion;

    useEffect(() => {
        if (estadoUi === 'espera_vencida' && permisos?.cerrar_atencion) {
            setModalEspera(true);
        }
    }, [estadoUi, permisos?.cerrar_atencion, turno?.id]);

    const manejarConflicto = useCallback((err) => {
        if (esConflictoVersionTurno(err)) {
            onConflicto?.(err);
            return true;
        }
        return false;
    }, [onConflicto]);

    const iniciarAtencion = useCallback(async () => {
        if (!puedeIniciarAtencion(turno, permisos) || procesando) return;
        setProcesando(true);
        try {
            const { data } = await axios.post(
                route('punto_venta.turnos.iniciar_atencion', turno.id),
                { version: turno.version },
                { headers: { Accept: 'application/json' } },
            );
            onActualizado?.(data);
        } catch (err) {
            if (!manejarConflicto(err)) {
                onError?.(mensajeErrorOperacionTurno(err, 'inicio de atención'));
            }
        } finally {
            setProcesando(false);
        }
    }, [turno, permisos, procesando, onActualizado, manejarConflicto, onError]);

    const cerrarAtencion = useCallback(async ({ motivo, motivoDetalle }) => {
        if (!permisos?.cerrar_atencion || procesando) return;
        setProcesando(true);
        const idempotencyKey = claveIdempotenciaOperacionTurno('cerrar', turno.id);
        try {
            const { data } = await axios.post(
                route('punto_venta.turnos.cerrar_atencion', turno.id),
                {
                    version: turno.version,
                    idempotency_key: idempotencyKey,
                    motivo,
                    motivo_detalle: motivoDetalle || null,
                },
                { headers: { Accept: 'application/json' } },
            );
            renovarClaveIdempotenciaOperacionTurno('cerrar', turno.id);
            setModalCerrar(false);
            setModalEspera(false);
            onActualizado?.(data);
        } catch (err) {
            if (!manejarConflicto(err)) {
                onError?.(mensajeErrorOperacionTurno(err, 'cierre de atención'));
            }
        } finally {
            setProcesando(false);
        }
    }, [turno, permisos, procesando, onActualizado, manejarConflicto, onError]);

    const transferir = useCallback(async (destinoUserId) => {
        if (!puedeTransferir(turno, permisos) || procesando) return;
        setProcesando(true);
        const idempotencyKey = claveIdempotenciaOperacionTurno('transferir', turno.id);
        try {
            const { data } = await axios.post(
                route('punto_venta.turnos.transferir', turno.id),
                {
                    version: turno.version,
                    idempotency_key: idempotencyKey,
                    destino_user_id: destinoUserId,
                },
                { headers: { Accept: 'application/json' } },
            );
            renovarClaveIdempotenciaOperacionTurno('transferir', turno.id);
            setModalTransferir(false);
            onActualizado?.(data);
        } catch (err) {
            if (!manejarConflicto(err)) {
                onError?.(mensajeErrorOperacionTurno(err, 'transferencia'));
            }
        } finally {
            setProcesando(false);
        }
    }, [turno, permisos, procesando, onActualizado, manejarConflicto, onError]);

    const abrirCierre = () => {
        if (estadoUi === 'espera_vencida') {
            setModalEspera(true);
        } else {
            setModalCerrar(true);
        }
    };

    return (
        <div className={`${geliaCardClass()} p-5 space-y-4`}>
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Turno asignado</p>
                    <p className="text-3xl font-black tracking-wider theme-text-main m-0 mt-1">{turno?.folio}</p>
                    <p className="text-sm font-bold theme-text-main m-0 mt-2">{turno?.snapshot_nombre_llamado || '—'}</p>
                </div>
                <EstadoUiBadge estadoUi={estadoUi} />
            </div>

            {etiquetas.length > 0 && (
                <div className="flex flex-wrap gap-2">
                    {etiquetas.map((etiqueta) => (
                        <span
                            key={etiqueta}
                            className={`inline-flex px-2.5 py-1 rounded-lg text-[10px] font-black uppercase ${badgePrioridadTurno(etiqueta)}`}
                        >
                            {etiqueta}
                        </span>
                    ))}
                </div>
            )}

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {!atencion?.atencion_en_curso && atencion?.espera_inicial_expira_at && (
                    <CronometroVisualTurno
                        etiqueta="Espera inicial"
                        referenciaAt={atencion.espera_inicial_expira_at}
                        servidorAt={servidorAt}
                        modo="restante"
                        alerta={atencion.espera_inicial_vencida}
                    />
                )}
                {atencion?.atencion_en_curso && atencion?.atencion_inicio_at && (
                    <CronometroVisualTurno
                        etiqueta="Tiempo de atención"
                        referenciaAt={atencion.atencion_inicio_at}
                        servidorAt={servidorAt}
                        modo="transcurrido"
                        alerta={atencion.prorroga_activa}
                    />
                )}
                {atencion?.prorroga_activa && (
                    <div className="flex items-center gap-2 rounded-xl px-3 py-2 bg-amber-500/15 sm:col-span-2">
                        <AlertTriangle className="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0" aria-hidden />
                        <p className="text-xs font-bold text-amber-700 dark:text-amber-300 m-0">
                            Atención prolongada — aviso de prórroga (visual)
                        </p>
                    </div>
                )}
            </div>

            {permisos?.cerrar_atencion && (
                <div className="flex flex-col sm:flex-row gap-3">
                    {puedeIniciarAtencion(turno, permisos) && (
                        <button
                            type="button"
                            className={`${THEME_BTN_PRIMARY} ${BTN_ACCION} flex-1`}
                            disabled={procesando}
                            onClick={iniciarAtencion}
                        >
                            <Play className="w-4 h-4" aria-hidden />
                            {atencion?.es_transferencia ? 'Continuar atención' : 'Iniciar atención'}
                        </button>
                    )}
                    {puedeCerrarAtencion(turno, permisos) && (
                        <button
                            type="button"
                            className={`${BTN_ACCION} flex-1 theme-border theme-element theme-text-main hover:bg-black/5 dark:hover:bg-white/5`}
                            disabled={procesando}
                            onClick={abrirCierre}
                        >
                            <Square className="w-4 h-4" aria-hidden />
                            Cerrar atención
                        </button>
                    )}
                    {puedeTransferir(turno, permisos) ? (
                        <button
                            type="button"
                            className={`${BTN_ACCION} flex-1 theme-border theme-element theme-text-main hover:bg-black/5 dark:hover:bg-white/5`}
                            disabled={procesando}
                            onClick={() => setModalTransferir(true)}
                        >
                            <ArrowRightLeft className="w-4 h-4" aria-hidden />
                            Transferir
                        </button>
                    ) : permisos?.cerrar_atencion && (
                        <button
                            type="button"
                            className={`${BTN_ACCION} flex-1 theme-border theme-element theme-text-muted hover:theme-text-main`}
                            disabled={procesando}
                            onClick={() => setModalSolicitarTransferencia(true)}
                        >
                            <ArrowRightLeft className="w-4 h-4" aria-hidden />
                            Solicitar transferencia
                        </button>
                    )}
                </div>
            )}

            <ModalMotivosEsperaTurno
                abierto={modalEspera}
                catalogos={catalogos}
                procesando={procesando}
                onClose={() => setModalEspera(false)}
                onConfirmar={cerrarAtencion}
            />
            <ModalCerrarAtencionTurno
                abierto={modalCerrar}
                catalogos={catalogos}
                procesando={procesando}
                onClose={() => setModalCerrar(false)}
                onConfirmar={cerrarAtencion}
            />
            <ModalTransferirTurno
                abierto={modalTransferir}
                personas={personasTransferencia}
                procesando={procesando}
                onClose={() => setModalTransferir(false)}
                onConfirmar={transferir}
            />
            <ModalSolicitarTransferenciaTurno
                abierto={modalSolicitarTransferencia}
                onClose={() => setModalSolicitarTransferencia(false)}
            />
        </div>
    );
}

function EstadoUiBadge({ estadoUi }) {
    const mapa = {
        espera: { texto: 'En espera de llegada', clase: 'bg-sky-500/15 text-sky-700 dark:text-sky-300' },
        espera_vencida: { texto: 'Espera vencida', clase: 'bg-amber-500/15 text-amber-700 dark:text-amber-300' },
        atencion: { texto: 'En atención', clase: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' },
        asignado: { texto: 'Asignado', clase: 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' },
    };
    const badge = mapa[estadoUi] || mapa.asignado;

    return (
        <span className={`inline-flex px-3 py-1.5 rounded-xl text-[10px] font-black uppercase ${badge.clase}`}>
            {badge.texto}
        </span>
    );
}
