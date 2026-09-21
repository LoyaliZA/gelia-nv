import React, { useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import axios from 'axios';
import { Headphones, Loader2 } from 'lucide-react';
import {
    BTN_PRIMARY,
    BTN_SECONDARY,
    THEME_MODAL_OVERLAY,
    THEME_MODAL_SHELL,
    deferModalAction,
} from '../../../ControlPedidos/Partials/pedidosBmaStyles';
import CronometroVisualOperacion from './CronometroVisualOperacion';
import { esConflictoVersion, mensajeErrorOperacion } from './operacionUtils';

export default function ModalReatencionDesdeVendedorGerencia({
    abierto = false,
    persona = null,
    turnos = [],
    servidorAt,
    onClose,
    onActualizado,
    onConflicto,
    onError,
}) {
    const [turnoId, setTurnoId] = useState('');
    const [cargando, setCargando] = useState(false);

    useEffect(() => {
        if (!abierto) {
            setTurnoId('');
            setCargando(false);
            return;
        }

        setTurnoId(turnos[0]?.id ? String(turnos[0].id) : '');
    }, [abierto, turnos]);

    useEffect(() => {
        if (!abierto) return undefined;

        const overflowAnterior = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        return () => {
            document.body.style.overflow = overflowAnterior;
        };
    }, [abierto]);

    const turnoSeleccionado = useMemo(
        () => turnos.find((turno) => String(turno.id) === String(turnoId)) ?? null,
        [turnos, turnoId],
    );

    const cerrar = (event) => {
        event?.stopPropagation?.();
        deferModalAction(onClose);
    };

    const ejecutarAsignacion = async () => {
        if (!persona || !turnoSeleccionado) return;

        setCargando(true);
        onError?.(null);

        try {
            const { data } = await axios.post(
                route('punto_venta.turnos.asignar_reatencion', turnoSeleccionado.id),
                {
                    version: turnoSeleccionado.version,
                    destino_user_id: Number(persona.id),
                    idempotency_key: `pdv:reatencion:${turnoSeleccionado.id}:${persona.id}`,
                },
            );
            await onActualizado?.(data);
            deferModalAction(onClose);
        } catch (err) {
            if (esConflictoVersion(err)) {
                onConflicto?.();
                deferModalAction(onClose);
            } else {
                onError?.(mensajeErrorOperacion(err, 'asignar la re-atención'));
            }
        } finally {
            setCargando(false);
        }
    };

    if (!abierto || !persona) {
        return null;
    }

    return createPortal(
        <div
            className={`${THEME_MODAL_OVERLAY} items-center py-4`}
            style={{ zIndex: 'calc(var(--gelia-z-modal) + 20)' }}
            onClick={cerrar}
        >
            <div
                className={`${THEME_MODAL_SHELL} max-w-md w-full p-6 md:p-8 space-y-6`}
                onClick={(event) => event.stopPropagation()}
                role="dialog"
                aria-modal="true"
                aria-labelledby="modal-reatencion-vendedor-titulo"
            >
                <div className="flex items-start gap-4">
                    <Headphones className="w-8 h-8 shrink-0 theme-text-primario" aria-hidden />
                    <div className="min-w-0">
                        <h3 id="modal-reatencion-vendedor-titulo" className="text-base font-black uppercase theme-text-main m-0">
                            Reatención
                        </h3>
                        <p className="text-sm theme-text-muted mt-2 m-0 leading-relaxed">
                            Asignar a {persona.nombre} un cliente que aún puede reatenderse.
                        </p>
                    </div>
                </div>

                {turnos.length === 0 ? (
                    <p className="text-sm font-semibold theme-text-muted m-0">
                        No hay clientes en ventana de re-atención para esta persona.
                    </p>
                ) : (
                    <div className="space-y-2">
                        <label className="text-[10px] font-black uppercase tracking-widest theme-text-muted" htmlFor="cliente-reatencion">
                            Cliente
                        </label>
                        <select
                            id="cliente-reatencion"
                            className="w-full min-h-[44px] rounded-xl border border-black/10 dark:border-white/10 px-3 py-2.5 text-sm font-semibold theme-text-main bg-transparent"
                            value={turnoId}
                            onChange={(event) => setTurnoId(event.target.value)}
                            disabled={cargando}
                        >
                            <option value="">Seleccionar…</option>
                            {turnos.map((turno) => (
                                <option key={turno.id} value={turno.id}>
                                    Folio {turno.folio} · {turno.cliente_nombre}
                                </option>
                            ))}
                        </select>
                    </div>
                )}

                {turnoSeleccionado?.vendedor_anterior && (
                    <p className="text-xs font-semibold theme-text-muted m-0">
                        Atención previa: {turnoSeleccionado.vendedor_anterior.nombre}
                    </p>
                )}

                {turnoSeleccionado?.reatencion_expira_at && (
                    <CronometroVisualOperacion
                        etiqueta="Ventana restante"
                        referenciaAt={turnoSeleccionado.reatencion_expira_at}
                        servidorAt={servidorAt}
                        modo="restante"
                        compacto
                    />
                )}

                <div className="flex flex-col gap-3">
                    <button
                        type="button"
                        className={`${BTN_PRIMARY} min-h-[44px] px-4 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest inline-flex items-center justify-center gap-2 disabled:opacity-50`}
                        disabled={cargando || !turnoSeleccionado}
                        onClick={ejecutarAsignacion}
                    >
                        {cargando ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden /> : null}
                        Confirmar reatención
                    </button>
                    <button
                        type="button"
                        className={`${BTN_SECONDARY} min-h-[44px] px-4 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest border theme-border theme-element`}
                        disabled={cargando}
                        onClick={cerrar}
                    >
                        Cancelar
                    </button>
                </div>
            </div>
        </div>,
        document.body,
    );
}
