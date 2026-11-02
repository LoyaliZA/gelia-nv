import React, { useMemo, useState } from 'react';
import axios from 'axios';
import { createPortal } from 'react-dom';
import { useIconHover } from '@animateicons/react';
import { ArrowRightLeftIcon } from '@animateicons/react/lucide/arrow-right-left-icon';
import { LogOutIcon } from '@animateicons/react/lucide/log-out-icon';
import { PauseIcon } from '@animateicons/react/lucide/pause-icon';
import { PlayIcon } from '@animateicons/react/lucide/play-icon';
import { RotateCcwIcon } from '@animateicons/react/lucide/rotate-ccw-icon';
import { SquareIcon } from '@animateicons/react/lucide/square-icon';
import { UserXIcon } from '@animateicons/react/lucide/user-x-icon';
import { AlertTriangle, Loader2 } from 'lucide-react';
import ModalConfirmarAccion from '../../../ControlPedidos/Partials/ModalConfirmarAccion';
import { BTN_PRIMARY, BTN_SECONDARY, THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, deferModalAction } from '../../../ControlPedidos/Partials/pedidosBmaStyles';
import { geliaCardClass, THEME_BTN_PRIMARY } from '../../../../utils/geliaTheme';
import CronometroVisualOperacion from './CronometroVisualOperacion';
import MetricasAtencionCompacta from './MetricasAtencionCompacta';
import ModalReatencionDesdeVendedorGerencia from './ModalReatencionDesdeVendedorGerencia';
import ModalCerrarAtencionTurno from '../../Turnos/Partials/ModalCerrarAtencionTurno';
import ModalTransferirTurno from '../../Turnos/Partials/ModalTransferirTurno';
import {
    claveIdempotenciaOperacionTurno,
    renovarClaveIdempotenciaOperacionTurno,
} from '../../Turnos/Partials/tableroVentasUtils';
import {
    claseBadgeEstadoVendedor,
    esAccionPeligrosaEquipo,
    esConflictoVersion,
    etiquetaAccionEquipo,
    etiquetaEstadoVendedor,
    inicialesNombre,
    tonoEstadoVendedor,
    mensajeErrorOperacion,
} from './operacionUtils';

const ACCION_PRIMARIA_POR_ESTADO = {
    no_activado: 'activar',
    no_llego: 'activar',
    disponible: 'pausa_iniciar',
    en_espera: 'pausa_iniciar',
    en_retencion: 'pausa_finalizar',
    cierre_pendiente: 'cancelar_cierre_pendiente',
    jornada_cerrada: 'reactivar',
};

function accionPrimariaEquipo(estado, acciones, reatencionHabilitada) {
    if (reatencionHabilitada) return 'reatencion';
    const preferida = ACCION_PRIMARIA_POR_ESTADO[estado];
    if (preferida && acciones.includes(preferida)) return preferida;
    return null;
}

const FOCO_BOTON = 'focus-visible:ring-2 focus-visible:ring-[var(--color-primario)] focus-visible:ring-offset-2 focus-visible:outline-none';

const BTN_CONTORNO = `inline-flex items-center justify-center gap-2 rounded-xl px-3 min-h-[44px] text-xs font-black uppercase tracking-wide text-center leading-tight theme-surface hover:bg-black/5 dark:hover:bg-white/5 transition-colors disabled:opacity-50 disabled:cursor-not-allowed hover:!scale-100 ${FOCO_BOTON}`;

function esPeligroVisual(accion) {
    return accion === 'terminar_atencion' || accion === 'cerrar_jornada' || accion === 'no_llego';
}

const ICONO_ACCION = {
    activar: PlayIcon,
    no_llego: UserXIcon,
    desactivar: UserXIcon,
    pausa_iniciar: PauseIcon,
    pausa_finalizar: PlayIcon,
    cerrar_jornada: LogOutIcon,
    reactivar: PlayIcon,
    cancelar_cierre_pendiente: RotateCcwIcon,
    reatencion: RotateCcwIcon,
    transferir: ArrowRightLeftIcon,
    terminar_atencion: SquareIcon,
};

function claseBotonTarjeta(accion, esPrimaria) {
    if (accion === 'terminar_atencion') {
        return `theme-btn-danger w-full min-h-[44px] !text-xs text-center leading-tight hover:!scale-100 ${FOCO_BOTON}`;
    }
    if (esPrimaria) {
        return `${THEME_BTN_PRIMARY} theme-btn-primary--compact w-full min-h-[44px] !text-xs text-center leading-tight hover:!scale-100 ${FOCO_BOTON}`;
    }

    if (esPeligroVisual(accion)) {
        return `${BTN_CONTORNO} w-full py-2 border theme-text-peligro !border-[var(--color-peligro)]`;
    }

    return `${BTN_CONTORNO} w-full py-2 border theme-border theme-text-main`;
}

function pesoAccionSecundaria(accion) {
    if (accion === 'cerrar_jornada' || accion === 'no_llego') return 2;
    return 0;
}

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
    puedeTransferirTurno = false,
    destinosTransferencia = [],
    puedeAsignarReatencion = false,
    reatenciones = [],
    motivosPausa = [],
    catalogos = {},
    onActualizado,
    onConflicto,
    onError,
}) {
    const [accionPendiente, setAccionPendiente] = useState(null);
    const [modalPausaAbierto, setModalPausaAbierto] = useState(false);
    const [modalReatencionAbierto, setModalReatencionAbierto] = useState(false);
    const [modalCerrarAtencion, setModalCerrarAtencion] = useState(false);
    const [modalTransferir, setModalTransferir] = useState(false);
    const [motivoPausaId, setMotivoPausaId] = useState('');
    const [motivoDetalle, setMotivoDetalle] = useState('');
    const [cargando, setCargando] = useState(false);
    const [fotoFallida, setFotoFallida] = useState(false);

    const motivoSeleccionado = useMemo(
        () => motivosPausa.find((motivo) => String(motivo.id) === String(motivoPausaId)),
        [motivosPausa, motivoPausaId],
    );

    const clientesReatencion = useMemo(
        () => (reatenciones ?? []).filter((turno) => (
            (turno.candidatos ?? []).some((candidato) => Number(candidato.id) === Number(persona.id))
        )),
        [reatenciones, persona.id],
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

    const cerrarAtencion = async ({ motivo, motivoDetalle }) => {
        const turnoId = persona.atencion_actual?.turno_id;
        const version = persona.atencion_actual?.turno_version;
        if (cargando) return;
        if (!turnoId || !Number.isInteger(version) || version < 1) {
            onError?.('No se pudo obtener el estado de la atención. Actualiza la operación e intenta de nuevo.');
            return;
        }

        setCargando(true);
        onError?.(null);
        const idempotencyKey = claveIdempotenciaOperacionTurno('cger', turnoId);
        try {
            const { data } = await axios.post(route('punto_venta.turnos.cerrar_atencion', turnoId), {
                version,
                idempotency_key: idempotencyKey,
                motivo,
                motivo_detalle: motivoDetalle || null,
            });
            renovarClaveIdempotenciaOperacionTurno('cger', turnoId);
            setModalCerrarAtencion(false);
            await onActualizado?.(data);
        } catch (err) {
            if (esConflictoVersion(err)) {
                onConflicto?.();
            } else {
                onError?.(mensajeErrorOperacion(err, 'cierre de atención'));
            }
        } finally {
            setCargando(false);
        }
    };

    const transferirAtencion = async (destinoUserId) => {
        const turnoId = persona.atencion_actual?.turno_id;
        const version = persona.atencion_actual?.turno_version;
        if (cargando) return;
        if (!turnoId || !Number.isInteger(version) || version < 1) {
            onError?.('No se pudo obtener el estado de la atención. Actualiza la operación e intenta de nuevo.');
            return;
        }

        setCargando(true);
        onError?.(null);
        const idempotencyKey = claveIdempotenciaOperacionTurno('transferir', turnoId);
        try {
            const { data } = await axios.post(route('punto_venta.turnos.transferir', turnoId), {
                version,
                idempotency_key: idempotencyKey,
                destino_user_id: destinoUserId,
            });
            renovarClaveIdempotenciaOperacionTurno('transferir', turnoId);
            setModalTransferir(false);
            await onActualizado?.(data);
        } catch (err) {
            if (esConflictoVersion(err)) {
                onConflicto?.();
            } else {
                onError?.(mensajeErrorOperacion(err, 'transferencia'));
            }
        } finally {
            setCargando(false);
        }
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
    const mostrarAtencion = Boolean(persona.atencion_actual);
    const mostrarCierrePendiente = persona.estado_vendedor === 'cierre_pendiente';
    const enPausa = persona.estado_vendedor === 'en_retencion';
    const ocupado = persona.estado_vendedor === 'atendiendo';
    const reatencionHabilitada = puedeAsignarReatencion
        && !ocupado
        && clientesReatencion.length > 0;
    const primaria = accionPrimariaEquipo(persona.estado_vendedor, acciones, reatencionHabilitada);
    const accionesSecundarias = acciones
        .filter((accion) => accion !== primaria)
        .sort((a, b) => pesoAccionSecundaria(a) - pesoAccionSecundaria(b));
    const mostrarTerminarAtencion = Boolean(puedeGestionar && persona.atencion_actual?.turno_id);
    const mostrarTransferir = Boolean(puedeTransferirTurno && persona.atencion_actual?.turno_id);
    const mostrarAcciones = puedeAsignarReatencion || acciones.length > 0 || mostrarTerminarAtencion || mostrarTransferir;
    const tieneFoto = Boolean(persona.foto_perfil) && !fotoFallida;
    const prorrogaActiva = Boolean(persona.atencion_actual?.prorroga_activa);
    const tonoTarjeta = tonoEstadoVendedor(persona.estado_vendedor, prorrogaActiva);

    return (
        <>
            <article className={`${geliaCardClass()} gelia-tarjeta-tono gelia-tarjeta-tono--${tonoTarjeta} p-4 h-full flex flex-col gap-3`}>
                <div className="flex items-start gap-3">
                    <div
                        className={`w-10 h-10 shrink-0 overflow-hidden flex items-center justify-center text-xs font-bold uppercase theme-element theme-text-main ${tieneFoto ? 'rounded-full' : 'rounded-xl'}`}
                        aria-hidden
                    >
                        {tieneFoto ? (
                            <img
                                src={`/storage/${persona.foto_perfil}`}
                                alt=""
                                width={44}
                                height={44}
                                className="w-full h-full object-cover"
                                onError={() => setFotoFallida(true)}
                            />
                        ) : (
                            inicialesNombre(persona.nombre)
                        )}
                    </div>

                    <div className="min-w-0 flex-1">
                        <div className="flex flex-col items-start gap-1.5">
                            <p className="text-sm font-bold theme-text-main m-0 min-w-0 max-w-full truncate">{persona.nombre}</p>
                            <span
                                className={`inline-flex items-center gap-1.5 shrink-0 text-[10px] font-bold uppercase tracking-wide ${claseBadgeEstadoVendedor(persona.estado_vendedor, prorrogaActiva)}`}
                                aria-label={`Estado: ${etiquetaEstadoVendedor(persona.estado_vendedor)}`}
                            >
                                <span className="h-1.5 w-1.5 rounded-full bg-current" aria-hidden />
                                {etiquetaEstadoVendedor(persona.estado_vendedor)}
                            </span>
                        </div>
                    </div>
                </div>

                {enPausa && (
                    <div
                        className="flex items-start gap-2 rounded-xl border px-3 py-2 gelia-estado-equipo--pausa"
                        role="status"
                        aria-label="Pausa activa"
                    >
                        <PauseIcon size={18} className="shrink-0 mt-0.5" />
                        <div className="min-w-0">
                            <p className="text-sm font-semibold m-0">
                                Pausa activa
                            </p>
                            {persona.pausa_motivo && (
                                <p className="text-xs font-medium m-0 mt-1">
                                    Motivo: {persona.pausa_motivo}
                                </p>
                            )}
                        </div>
                    </div>
                )}

                {cronometro && !mostrarAtencion && (
                    <CronometroVisualOperacion
                        etiqueta={cronometro.etiqueta}
                        referenciaAt={cronometro.referenciaAt}
                        servidorAt={servidorAt}
                        modo={cronometro.modo}
                        prominente
                        alerta={enPausa || persona.estado_vendedor === 'en_espera'}
                        superficieAlerta={enPausa ? 'pausa' : null}
                    />
                )}

                {persona.estado_vendedor === 'en_espera' && (
                    <p className="text-sm font-semibold theme-text-muted m-0">
                        Volverá a la cola al terminar la cuenta regresiva.
                    </p>
                )}

                {mostrarCierrePendiente && (
                    <div className="flex items-start gap-2 rounded-xl border px-3 py-2 gelia-estado-vivo--aviso">
                        <AlertTriangle className="w-5 h-5 shrink-0 mt-0.5" aria-hidden />
                        <p className="text-sm font-medium m-0">Cierre pendiente al terminar la atención actual.</p>
                    </div>
                )}

                {mostrarAtencion && (
                    <MetricasAtencionCompacta
                        atencion={persona.atencion_actual}
                        servidorAt={servidorAt}
                        prominente
                    />
                )}

                {mostrarAcciones && (
                    <AccionesTarjeta
                        primaria={primaria}
                        accionesSecundarias={accionesSecundarias}
                        mostrarTerminarAtencion={mostrarTerminarAtencion}
                        mostrarTransferir={mostrarTransferir}
                        puedeAsignarReatencion={puedeAsignarReatencion}
                        reatencionHabilitada={reatencionHabilitada}
                        ocupado={ocupado}
                        cantidadClientes={clientesReatencion.length}
                        cargando={cargando}
                        onReatencion={() => setModalReatencionAbierto(true)}
                        onTerminar={() => setModalCerrarAtencion(true)}
                        onTransferir={() => setModalTransferir(true)}
                        onAccion={solicitarAccion}
                    />
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

            <ModalTransferirTurno
                abierto={modalTransferir}
                personas={destinosTransferencia}
                servidorAt={servidorAt}
                procesando={cargando}
                onClose={() => setModalTransferir(false)}
                onConfirmar={transferirAtencion}
            />

            <ModalCerrarAtencionTurno
                abierto={modalCerrarAtencion}
                catalogos={catalogos}
                procesando={cargando}
                onClose={() => setModalCerrarAtencion(false)}
                onConfirmar={cerrarAtencion}
            />

            <ModalReatencionDesdeVendedorGerencia
                abierto={modalReatencionAbierto}
                persona={persona}
                turnos={clientesReatencion}
                servidorAt={servidorAt}
                onClose={() => setModalReatencionAbierto(false)}
                onActualizado={onActualizado}
                onConflicto={onConflicto}
                onError={onError}
            />
        </>
    );
}

function AccionesTarjeta({
    primaria,
    accionesSecundarias,
    mostrarTerminarAtencion,
    mostrarTransferir,
    puedeAsignarReatencion,
    reatencionHabilitada,
    ocupado,
    cantidadClientes,
    cargando,
    onReatencion,
    onTerminar,
    onTransferir,
    onAccion,
}) {
    const secundarias = [];

    if (puedeAsignarReatencion && primaria !== 'reatencion') {
        secundarias.push({
            key: 'reatencion',
            accion: 'reatencion',
            etiqueta: 'Reatención',
            disabled: !reatencionHabilitada || cargando,
            title: tituloReatencion(reatencionHabilitada, ocupado, cantidadClientes),
            onClick: onReatencion,
        });
    }

    if (mostrarTransferir) {
        secundarias.push({
            key: 'transferir',
            accion: 'transferir',
            etiqueta: 'Transferir',
            disabled: cargando,
            onClick: onTransferir,
        });
    }

    accionesSecundarias.forEach((accion) => {
        secundarias.push({
            key: accion,
            accion,
            etiqueta: etiquetaAccionEquipo(accion),
            disabled: cargando,
            onClick: () => onAccion(accion),
        });
    });

    return (
        <div className="flex flex-col gap-2 mt-auto pt-1">
            {primaria === 'reatencion' && (
                <BotonAccion
                    accion="reatencion"
                    className={claseBotonTarjeta('reatencion', true)}
                    disabled={!reatencionHabilitada || cargando}
                    title={tituloReatencion(reatencionHabilitada, ocupado, cantidadClientes)}
                    cargando={cargando}
                    onClick={onReatencion}
                >
                    Reatención
                </BotonAccion>
            )}
            {primaria && primaria !== 'reatencion' && (
                <BotonAccion
                    accion={primaria}
                    className={claseBotonTarjeta(primaria, true)}
                    disabled={cargando}
                    cargando={cargando}
                    onClick={() => onAccion(primaria)}
                >
                    {etiquetaAccionEquipo(primaria)}
                </BotonAccion>
            )}
            {mostrarTerminarAtencion && (
                <BotonAccion
                    accion="terminar_atencion"
                    className={claseBotonTarjeta('terminar_atencion', false)}
                    disabled={cargando}
                    cargando={cargando}
                    onClick={onTerminar}
                >
                    Terminar atención
                </BotonAccion>
            )}
            {secundarias.length > 0 && (
                <div className="grid grid-cols-2 gap-2">
                    {secundarias.map((item, index) => {
                        const sola = secundarias.length % 2 === 1 && index === secundarias.length - 1;
                        return (
                            <div key={item.key} className={sola ? 'col-span-2' : undefined}>
                                <BotonAccion
                                    accion={item.accion}
                                    className={claseBotonTarjeta(item.accion, false)}
                                    disabled={item.disabled}
                                    title={item.title}
                                    cargando={cargando}
                                    onClick={item.onClick}
                                >
                                    {item.etiqueta}
                                </BotonAccion>
                            </div>
                        );
                    })}
                </div>
            )}
        </div>
    );
}

function BotonAccion({
    accion,
    className,
    disabled,
    title,
    cargando,
    onClick,
    children,
}) {
    const { ref, triggerProps } = useIconHover();
    const Icono = ICONO_ACCION[accion];

    return (
        <button
            type="button"
            className={className}
            disabled={disabled}
            title={title}
            {...triggerProps}
            onClick={onClick}
        >
            {cargando
                ? <Loader2 className="w-4 h-4 shrink-0 motion-safe:animate-spin" aria-hidden />
                : (Icono ? <Icono ref={ref} size={16} /> : null)}
            {children}
        </button>
    );
}

function tituloReatencion(habilitada, ocupado, cantidadClientes) {
    if (habilitada) {
        return 'Asignar un cliente en re-atención';
    }
    if (ocupado) {
        return 'No disponible: hay una atención en curso';
    }
    if (cantidadClientes === 0) {
        return 'No hay clientes en ventana de re-atención';
    }
    return 'Solo se puede reatender con personal disponible';
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
                    <PauseIcon size={32} className="theme-text-aviso shrink-0" />
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
                            className="mt-1 w-full min-h-[44px] rounded-xl border theme-border theme-element px-3 text-sm font-semibold theme-text-main"
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
                                className="mt-1 w-full min-h-[88px] rounded-xl border theme-border theme-element px-3 py-2 text-sm font-semibold theme-text-main resize-y"
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
