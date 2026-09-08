import React, { useEffect, useMemo, useState } from 'react';
import axios from 'axios';
import { createPortal } from 'react-dom';
import { Headphones, Loader2, RotateCcw, UserRound } from 'lucide-react';
import { BTN_PRIMARY, BTN_SECONDARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, deferModalAction } from '../../../ControlPedidos/Partials/pedidosBmaStyles';
import { geliaCardClass, THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import CronometroVisualOperacion from './CronometroVisualOperacion';
import { esConflictoVersion, mensajeErrorOperacion } from './operacionUtils';
import { formatearCronometro } from '../../Turnos/Partials/tableroVentasUtils';

function calcularRestanteMs(reatencionExpiraAt, servidorAt) {
    if (!reatencionExpiraAt || !servidorAt) return 0;
    const expira = new Date(reatencionExpiraAt).getTime();
    const ahora = new Date(servidorAt).getTime();
    if (!Number.isFinite(expira) || !Number.isFinite(ahora)) return 0;
    return Math.max(0, expira - ahora);
}

export default function PanelReatencionGerencia({
    reatenciones = [],
    servidorAt,
    puedeAsignar = false,
    onActualizado,
    onConflicto,
    onError,
}) {
    const [turnoSeleccionado, setTurnoSeleccionado] = useState(null);
    const [destinoId, setDestinoId] = useState('');
    const [cargando, setCargando] = useState(false);

    const cantidad = reatenciones.length;

    useEffect(() => {
        if (turnoSeleccionado !== null && !reatenciones.some((item) => item.id === turnoSeleccionado.id)) {
            setTurnoSeleccionado(null);
            setDestinoId('');
        }
    }, [reatenciones, turnoSeleccionado]);

    const abrirModal = (turno) => {
        setTurnoSeleccionado(turno);
        setDestinoId(turno.candidatos?.[0]?.id ? String(turno.candidatos[0].id) : '');
    };

    const cerrarModal = () => {
        setTurnoSeleccionado(null);
        setDestinoId('');
    };

    const ejecutarAsignacion = async () => {
        if (!turnoSeleccionado || !destinoId) return;

        setCargando(true);
        onError?.(null);

        try {
            const { data } = await axios.post(
                route('punto_venta.turnos.asignar_reatencion', turnoSeleccionado.id),
                {
                    version: turnoSeleccionado.version,
                    destino_user_id: Number(destinoId),
                    idempotency_key: `pdv:reatencion:${turnoSeleccionado.id}:${destinoId}`,
                },
            );
            await onActualizado?.(data);
            cerrarModal();
        } catch (err) {
            if (esConflictoVersion(err)) {
                onConflicto?.();
                cerrarModal();
            } else {
                onError?.(mensajeErrorOperacion(err, 'asignar la re-atención'));
            }
        } finally {
            setCargando(false);
        }
    };

    const candidatoSeleccionado = useMemo(
        () => turnoSeleccionado?.candidatos?.find((c) => String(c.id) === String(destinoId)) ?? null,
        [turnoSeleccionado, destinoId],
    );

    if (!puedeAsignar) {
        return null;
    }

    return (
        <>
            <aside
                className={`${geliaCardClass()} p-4 space-y-4 lg:sticky lg:top-4 lg:self-start`}
                data-panel-reatencion-gerencia
                aria-label={`Re-atención (${cantidad})`}
            >
                <div className="flex items-center gap-2">
                    <RotateCcw className="w-5 h-5 shrink-0 theme-text-muted" aria-hidden />
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                        Re-atención ({cantidad})
                    </h2>
                </div>

                {cantidad === 0 ? (
                    <p className="text-sm font-semibold theme-text-muted m-0">
                        No hay clientes en ventana de re-atención.
                    </p>
                ) : (
                    <ul className="space-y-3 list-none m-0 p-0">
                        {reatenciones.map((turno) => {
                            const restanteMs = calcularRestanteMs(turno.reatencion_expira_at, servidorAt);
                            const sinCandidatos = !turno.candidatos?.length;

                            return (
                                <li key={turno.id}>
                                    <div className="rounded-2xl border border-black/5 dark:border-white/10 p-3 space-y-2">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <p className="text-xs font-black uppercase tracking-widest theme-text-muted m-0">
                                                    Folio {turno.folio}
                                                </p>
                                                <p className="text-sm font-bold theme-text-main m-0 truncate">
                                                    {turno.cliente_nombre}
                                                </p>
                                            </div>
                                            <span className="text-[10px] font-black tabular-nums theme-text-muted shrink-0">
                                                {formatearCronometro(restanteMs)}
                                            </span>
                                        </div>

                                        {turno.vendedor_anterior && (
                                            <p className="text-xs font-semibold theme-text-muted m-0">
                                                Anterior: {turno.vendedor_anterior.nombre}
                                            </p>
                                        )}

                                        {turno.atencion_previa?.cierre_at && (
                                            <p className="text-xs font-semibold theme-text-muted m-0">
                                                Cierre: {new Date(turno.atencion_previa.cierre_at).toLocaleTimeString('es-MX', {
                                                    hour: '2-digit',
                                                    minute: '2-digit',
                                                })}
                                            </p>
                                        )}

                                        <button
                                            type="button"
                                            className={`${THEME_BTN_PRIMARY} w-full min-h-[40px] px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest inline-flex items-center justify-center gap-2 disabled:opacity-50`}
                                            disabled={sinCandidatos || restanteMs <= 0}
                                            onClick={() => abrirModal(turno)}
                                        >
                                            <Headphones className="w-4 h-4" aria-hidden />
                                            {sinCandidatos ? 'Sin vendedores' : 'Asignar'}
                                        </button>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </aside>

            {turnoSeleccionado && createPortal(
                <div className={THEME_MODAL_OVERLAY} role="dialog" aria-modal="true" aria-labelledby="modal-reatencion-titulo">
                    <div className={`${THEME_MODAL_SHELL} max-w-md w-full space-y-4`}>
                        <div>
                            <h3 id="modal-reatencion-titulo" className="text-lg font-black theme-text-main m-0">
                                Asignar re-atención
                            </h3>
                            <p className="text-sm font-semibold theme-text-muted mt-1 m-0">
                                Folio {turnoSeleccionado.folio} · {turnoSeleccionado.cliente_nombre}
                            </p>
                        </div>

                        {turnoSeleccionado.vendedor_anterior && (
                            <p className="text-xs font-semibold theme-text-muted m-0">
                                Vendedor anterior: {turnoSeleccionado.vendedor_anterior.nombre}
                            </p>
                        )}

                        {turnoSeleccionado.atencion_previa?.cierre_at && (
                            <p className="text-xs font-semibold theme-text-muted m-0">
                                Cierre de atención previa: {new Date(turnoSeleccionado.atencion_previa.cierre_at).toLocaleString('es-MX', {
                                    dateStyle: 'short',
                                    timeStyle: 'short',
                                })}
                            </p>
                        )}

                        <CronometroVisualOperacion
                            etiqueta="Tiempo restante"
                            referenciaAt={turnoSeleccionado.reatencion_expira_at}
                            servidorAt={servidorAt}
                            modo="restante"
                        />

                        <div className="space-y-2">
                            <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted" htmlFor="destino-reatencion">
                                Vendedor destino
                            </label>
                            <select
                                id="destino-reatencion"
                                className="w-full rounded-xl border px-3 py-2.5 text-sm font-semibold theme-text-main bg-transparent"
                                value={destinoId}
                                onChange={(e) => setDestinoId(e.target.value)}
                                disabled={cargando}
                            >
                                <option value="">Seleccionar…</option>
                                {(turnoSeleccionado.candidatos ?? []).map((candidato) => (
                                    <option key={candidato.id} value={candidato.id}>
                                        {candidato.nombre}
                                    </option>
                                ))}
                            </select>
                        </div>

                        {candidatoSeleccionado && (
                            <div className="flex items-center gap-2 text-sm font-semibold theme-text-muted">
                                <UserRound className="w-4 h-4 shrink-0" aria-hidden />
                                <span>Se asignará a {candidatoSeleccionado.nombre}</span>
                            </div>
                        )}

                        <div className="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end">
                            <button
                                type="button"
                                className={`${BTN_SECONDARY} min-h-[44px] px-4 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest`}
                                disabled={cargando}
                                onClick={() => deferModalAction(cerrarModal)}
                            >
                                Cancelar
                            </button>
                            <button
                                type="button"
                                className={`${BTN_PRIMARY} min-h-[44px] px-4 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest inline-flex items-center justify-center gap-2 disabled:opacity-50`}
                                disabled={cargando || !destinoId}
                                onClick={ejecutarAsignacion}
                            >
                                {cargando ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden /> : null}
                                Confirmar asignación
                            </button>
                        </div>
                    </div>
                </div>,
                document.body,
            )}
        </>
    );
}
