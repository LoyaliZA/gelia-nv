import React, { useMemo, useState } from 'react';
import axios from 'axios';
import { createPortal } from 'react-dom';
import { AlertTriangle, Headphones, Loader2, Pause } from 'lucide-react';
import ModalConfirmarAccion from '../../../ControlPedidos/Partials/ModalConfirmarAccion';
import { BTN_PRIMARY, BTN_SECONDARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, deferModalAction } from '../../../ControlPedidos/Partials/pedidosBmaStyles';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY } from '../../../../utils/geliaTheme';
import CronometroVisualOperacion from './CronometroVisualOperacion';
import {
    claseBadgeEstadoVendedor,
    esAccionPeligrosaEquipo,
    esConflictoVersion,
    etiquetaAccionEquipo,
    etiquetaActividad,
    etiquetaEstadoVendedor,
    inicialesNombre,
    mensajeErrorOperacion,
} from './operacionUtils';

const RUTAS_ACCION = {
    activar: 'punto_venta.operacion.equipo.activar',
    no_llego: 'punto_venta.operacion.equipo.no_llego',
    desactivar: 'punto_venta.operacion.equipo.desactivar',
    pausa_iniciar: 'punto_venta.operacion.equipo.pausa.iniciar',
    pausa_finalizar: 'punto_venta.operacion.equipo.pausa.finalizar',
    cerrar_jornada: 'punto_venta.operacion.equipo.cerrar_jornada',
    reactivar: 'punto_venta.operacion.equipo.reactivar',
    cancelar_cierre_pendiente: 'punto_venta.operacion.equipo.cancelar_cierre_pendiente',
};

export default function TarjetaVendedorGerencia({
    persona,
    servidorAt,
    puedeGestionar = false,
    motivosPausa = [],
    onActualizado,
    onConflicto,
    onError,
}) {
    const [accionPendiente, setAccionPendiente] = useState(null);
    const [modalPausaAbierto, setModalPausaAbierto] = useState(false);
    const [motivoPausaId, setMotivoPausaId] = useState('');
    const [motivoDetalle, setMotivoDetalle] = useState('');
    const [cargando, setCargando] = useState(false);
    const [fotoFallida, setFotoFallida] = useState(false);

    const motivoSeleccionado = useMemo(
        () => motivosPausa.find((motivo) => String(motivo.id) === String(motivoPausaId)),
        [motivosPausa, motivoPausaId],
    );

    const ejecutarAccion = async (accion, payloadExtra = {}) => {
        const ruta = RUTAS_ACCION[accion];
        if (!ruta) return;

        setCargando(true);
        onError?.(null);

        const payload = (accion === 'desactivar' || accion === 'cerrar_jornada' || accion === 'cancelar_cierre_pendiente')
            ? { version: persona.jornada?.version, ...payloadExtra }
            : { ...payloadExtra };

        try {
            const { data } = await axios.post(route(ruta, persona.id), payload);
            await onActualizado?.(data);
        } catch (err) {
            if (esConflictoVersion(err)) {
                onConflicto?.();
            } else {
                onError?.(mensajeErrorOperacion(err, etiquetaAccionEquipo(accion).toLowerCase()));
            }
        } finally {
            setCargando(false);
            setAccionPendiente(null);
            setModalPausaAbierto(false);
            setMotivoPausaId('');
            setMotivoDetalle('');
        }
    };

    const solicitarAccion = (accion) => {
        if (accion === 'pausa_iniciar') {
            setModalPausaAbierto(true);
            return;
        }

        if (esAccionPeligrosaEquipo(accion)) {
            setAccionPendiente(accion);
            return;
        }
        ejecutarAccion(accion);
    };

    const confirmarPausa = () => {
        if (!motivoPausaId) {
            onError?.('Selecciona un motivo de pausa.');
            return;
        }

        if (motivoSeleccionado?.requiere_detalle && !motivoDetalle.trim()) {
            onError?.('Indica el detalle del motivo seleccionado.');
            return;
        }

        ejecutarAccion('pausa_iniciar', {
            motivo_pausa_id: Number(motivoPausaId),
            motivo_detalle: motivoDetalle.trim() || null,
        });
    };

    const cronometro = persona.cronometro
        ? {
            etiqueta: persona.cronometro.etiqueta,
            referenciaAt: persona.cronometro.referencia_at,
            modo: persona.cronometro.modo,
        }
        : null;

    const acciones = puedeGestionar ? (persona.acciones ?? []) : [];
    const mostrarAtencion = persona.estado_vendedor === 'atendiendo' && persona.atencion_actual;
    const mostrarCierrePendiente = persona.estado_vendedor === 'cierre_pendiente';
    const enPausa = persona.estado_vendedor === 'en_retencion';

    return (
        <>
            <article className={`${geliaCardClass()} p-4 h-full flex flex-col gap-3`}>
                <div className="flex items-start gap-3">
                    <div
                        className="w-11 h-11 rounded-2xl shrink-0 overflow-hidden flex items-center justify-center text-xs font-black uppercase"
                        style={{
                            backgroundColor: 'color-mix(in srgb, var(--color-primario) 14%, transparent)',
                            color: 'var(--color-primario)',
                        }}
                        aria-hidden
                    >
                        {persona.foto_perfil && !fotoFallida ? (
                            <img
                                src={`/storage/${persona.foto_perfil}`}
                                alt=""
                                className="w-full h-full object-cover"
                                onError={() => setFotoFallida(true)}
                            />
                        ) : (
                            inicialesNombre(persona.nombre)
                        )}
                    </div>

                    <div className="min-w-0 flex-1">
                        <p className="text-sm font-black theme-text-main m-0 truncate">{persona.nombre}</p>
                        <span
                            className={`inline-flex mt-2 px-2.5 py-1 rounded-full text-[10px] font-black uppercase ${claseBadgeEstadoVendedor(persona.estado_vendedor)}`}
                            aria-label={`Estado: ${etiquetaEstadoVendedor(persona.estado_vendedor)}`}
                        >
                            {etiquetaEstadoVendedor(persona.estado_vendedor)}
                        </span>
                    </div>
                </div>

                {persona.actividad && (
                    <p className="text-xs font-semibold theme-text-muted m-0">
                        Actividad: <span className="theme-text-main">{etiquetaActividad(persona.actividad)}</span>
                    </p>
                )}

                {enPausa && (
                    <div
                        className="flex items-start gap-2 rounded-xl px-3 py-2 bg-amber-500/10 border border-amber-500/20"
                        role="status"
                        aria-label="Pausa activa"
                    >
                        <Pause className="w-4 h-4 shrink-0 text-amber-700 dark:text-amber-300 mt-0.5" aria-hidden />
                        <div className="min-w-0">
                            <p className="text-[10px] font-black uppercase tracking-widest text-amber-800 dark:text-amber-200 m-0">
                                Pausa activa
                            </p>
                            {persona.pausa_motivo && (
                                <p className="text-xs font-semibold text-amber-800/90 dark:text-amber-200/90 m-0 mt-1">
                                    Motivo: {persona.pausa_motivo}
                                </p>
                            )}
                        </div>
                    </div>
                )}

                {cronometro && (
                    <CronometroVisualOperacion
                        etiqueta={cronometro.etiqueta}
                        referenciaAt={cronometro.referenciaAt}
                        servidorAt={servidorAt}
                        modo={cronometro.modo}
                    />
                )}

                {mostrarCierrePendiente && (
                    <div className="flex items-start gap-2 rounded-xl px-3 py-2 bg-amber-500/10 text-amber-800 dark:text-amber-200">
                        <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" aria-hidden />
                        <p className="text-xs font-semibold m-0">Cierre pendiente al terminar la atención actual.</p>
                    </div>
                )}

                {mostrarAtencion && (
                    <div className="flex items-start gap-2 rounded-xl px-3 py-2 bg-black/5 dark:bg-white/5">
                        <Headphones className="w-4 h-4 shrink-0 theme-text-muted mt-0.5" aria-hidden />
                        <div className="min-w-0">
                            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Turno actual</p>
                            <p className="text-sm font-black theme-text-main m-0 mt-1">{persona.atencion_actual.folio}</p>
                            {persona.atencion_actual.cliente && (
                                <p className="text-xs font-semibold theme-text-muted m-0 mt-1 truncate">
                                    {persona.atencion_actual.cliente}
                                </p>
                            )}
                        </div>
                    </div>
                )}

                {persona.recibe_turnos && (
                    <p className="text-[10px] font-black uppercase tracking-widest text-emerald-700 dark:text-emerald-300 m-0">
                        Recibe turnos
                    </p>
                )}

                {acciones.length > 0 && (
                    <div className="flex flex-wrap gap-2 mt-auto pt-1">
                        {acciones.map((accion) => (
                            <button
                                key={accion}
                                type="button"
                                className={`${esAccionPeligrosaEquipo(accion) ? THEME_BTN_SECONDARY : THEME_BTN_PRIMARY} min-h-[40px] px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest inline-flex items-center gap-1.5`}
                                disabled={cargando}
                                onClick={() => solicitarAccion(accion)}
                            >
                                {cargando && <Loader2 className="w-3.5 h-3.5 animate-spin" aria-hidden />}
                                {etiquetaAccionEquipo(accion)}
                            </button>
                        ))}
                    </div>
                )}
            </article>

            <ModalConfirmarAccion
                abierto={Boolean(accionPendiente)}
                titulo={accionPendiente ? etiquetaAccionEquipo(accionPendiente) : ''}
                mensaje={mensajeConfirmacion(persona, accionPendiente)}
                etiquetaConfirmar={accionPendiente ? etiquetaAccionEquipo(accionPendiente) : 'Confirmar'}
                variante={accionPendiente === 'activar' ? 'primary' : 'danger'}
                onClose={() => setAccionPendiente(null)}
                onConfirm={() => {
                    if (accionPendiente) {
                        ejecutarAccion(accionPendiente);
                    }
                }}
            />

            <ModalPausaGerencia
                abierto={modalPausaAbierto}
                persona={persona}
                motivosPausa={motivosPausa}
                motivoPausaId={motivoPausaId}
                motivoDetalle={motivoDetalle}
                motivoSeleccionado={motivoSeleccionado}
                cargando={cargando}
                onMotivoPausaIdChange={setMotivoPausaId}
                onMotivoDetalleChange={setMotivoDetalle}
                onClose={() => {
                    setModalPausaAbierto(false);
                    setMotivoPausaId('');
                    setMotivoDetalle('');
                }}
                onConfirm={confirmarPausa}
            />
        </>
    );
}

function mensajeConfirmacion(persona, accion) {
    if (!accion) return '';

    if (accion === 'no_llego') {
        return `¿Confirmar que ${persona.nombre} no llegó hoy? No recibirá turnos.`;
    }

    if (accion === 'desactivar' || accion === 'cerrar_jornada') {
        if (persona.estado_vendedor === 'atendiendo') {
            return `${persona.nombre} tiene una atención en curso. La jornada pasará a cierre pendiente: no recibirá turnos nuevos hasta terminar la atención actual.`;
        }
        return `¿Cerrar la jornada de ${persona.nombre}? Dejará de recibir turnos nuevos.`;
    }

    return `¿Confirmar acción para ${persona.nombre}?`;
}

function ModalPausaGerencia({
    abierto,
    persona,
    motivosPausa,
    motivoPausaId,
    motivoDetalle,
    motivoSeleccionado,
    cargando,
    onMotivoPausaIdChange,
    onMotivoDetalleChange,
    onClose,
    onConfirm,
}) {
    if (!abierto) return null;

    const cerrar = (event) => {
        event?.stopPropagation?.();
        deferModalAction(onClose);
    };

    return createPortal(
        <div
            className={`${THEME_MODAL_OVERLAY} items-center py-4`}
            style={{ zIndex: 'calc(var(--gelia-z-modal) + 20)' }}
            onClick={cerrar}
        >
            <div
                className={`${THEME_MODAL_SHELL} max-w-md w-full p-6 md:p-8 space-y-6`}
                onClick={(event) => event.stopPropagation()}
            >
                <div className="flex items-start gap-4">
                    <Pause className="w-8 h-8 text-amber-600 shrink-0" aria-hidden />
                    <div>
                        <h3 className="text-base font-black uppercase theme-text-main m-0">
                            Pausar a {persona.nombre}
                        </h3>
                        <p className="text-sm theme-text-muted mt-2 m-0 leading-relaxed">
                            Selecciona el motivo de la pausa. El vendedor dejará de recibir turnos hasta que finalices la pausa.
                        </p>
                    </div>
                </div>

                <div className="space-y-3">
                    <label className="block">
                        <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Motivo</span>
                        <select
                            className="mt-1 w-full min-h-[44px] rounded-xl border border-black/10 dark:border-white/10 px-3 text-sm font-semibold theme-text-main bg-transparent"
                            value={motivoPausaId}
                            onChange={(event) => onMotivoPausaIdChange(event.target.value)}
                            disabled={cargando}
                        >
                            <option value="">Seleccionar motivo…</option>
                            {motivosPausa.map((motivo) => (
                                <option key={motivo.id} value={motivo.id}>
                                    {motivo.nombre}
                                </option>
                            ))}
                        </select>
                    </label>

                    {motivoSeleccionado?.requiere_detalle && (
                        <label className="block">
                            <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Detalle</span>
                            <textarea
                                className="mt-1 w-full min-h-[88px] rounded-xl border border-black/10 dark:border-white/10 px-3 py-2 text-sm font-semibold theme-text-main bg-transparent resize-y"
                                value={motivoDetalle}
                                onChange={(event) => onMotivoDetalleChange(event.target.value)}
                                disabled={cargando}
                                maxLength={500}
                                placeholder="Describe el motivo de la pausa"
                            />
                        </label>
                    )}
                </div>

                <div className="flex flex-col gap-3">
                    <button
                        type="button"
                        onClick={(event) => {
                            event.stopPropagation();
                            deferModalAction(onConfirm);
                        }}
                        className={`${BTN_PRIMARY} flex-1 py-3`}
                        disabled={cargando}
                    >
                        {cargando ? 'Iniciando pausa…' : 'Iniciar pausa'}
                    </button>
                    <button
                        type="button"
                        onClick={cerrar}
                        className={`${BTN_SECONDARY} flex-1 py-3 rounded-xl border theme-border theme-element`}
                        disabled={cargando}
                    >
                        Cancelar
                    </button>
                </div>
            </div>
        </div>,
        document.body,
    );
}
