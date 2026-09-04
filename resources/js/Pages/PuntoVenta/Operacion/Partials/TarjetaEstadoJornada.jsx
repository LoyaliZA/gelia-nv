import React, { useState } from 'react';
import axios from 'axios';
import { DoorClosed, DoorOpen, Loader2, Pause, Play } from 'lucide-react';
import ModalConfirmarAccion from '../../../ControlPedidos/Partials/ModalConfirmarAccion';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY } from '../../../../utils/geliaTheme';
import CronometroVisualOperacion from './CronometroVisualOperacion';
import {
    claseBadgeActividad,
    claseBadgeJornada,
    esConflictoVersion,
    etiquetaActividad,
    etiquetaJornada,
    mensajeErrorOperacion,
    puedeAbrirJornada,
    puedeCerrarJornada,
    puedeFinalizarPausa,
    puedeIniciarPausa,
    referenciaCronometro,
} from './operacionUtils';

export default function TarjetaEstadoJornada({
    estado,
    permisos,
    servidorAt,
    onActualizado,
    onConflicto,
    onError,
}) {
    const [accion, setAccion] = useState(null);
    const [cargando, setCargando] = useState(false);
    const [modalCerrar, setModalCerrar] = useState(false);

    const cronometro = referenciaCronometro(estado);
    const tieneAtencionAbierta = estado?.actividad === 'en_atencion';

    const ejecutar = async (ruta, payload = {}, etiquetaAccion = 'acción') => {
        setCargando(true);
        onError?.(null);

        try {
            const { data } = await axios.post(route(ruta), payload);
            await onActualizado?.(data);
            return data;
        } catch (err) {
            if (esConflictoVersion(err)) {
                onConflicto?.();
            } else {
                onError?.(mensajeErrorOperacion(err, etiquetaAccion));
            }
            return null;
        } finally {
            setCargando(false);
            setAccion(null);
            setModalCerrar(false);
        }
    };

    const abrirJornada = () => ejecutar('punto_venta.operacion.jornada.abrir', {}, 'apertura de jornada');

    const cerrarJornada = () => ejecutar(
        'punto_venta.operacion.jornada.cerrar',
        { version: estado?.jornada?.version },
        'cierre de jornada',
    );

    const iniciarPausa = () => ejecutar('punto_venta.operacion.pausa.iniciar', {}, 'inicio de pausa');
    const finalizarPausa = () => ejecutar('punto_venta.operacion.pausa.finalizar', {}, 'fin de pausa');

    return (
        <section className={`${geliaCardClass()} p-5 space-y-4`} aria-labelledby="jornada-propia-titulo">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="jornada-propia-titulo" className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                        Mi jornada PDV
                    </h2>
                    <p className="text-xs font-semibold theme-text-muted m-0 mt-1">
                        Independiente de la presencia en Mensajería.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <span className={`inline-flex px-2.5 py-1 rounded-full text-[10px] font-black uppercase ${claseBadgeJornada(estado?.jornada?.estado)}`}>
                        {etiquetaJornada(estado?.jornada?.estado || 'CERRADA')}
                    </span>
                    {estado?.actividad && (
                        <span className={`inline-flex px-2.5 py-1 rounded-full text-[10px] font-black uppercase ${claseBadgeActividad(estado.actividad)}`}>
                            {etiquetaActividad(estado.actividad)}
                        </span>
                    )}
                </div>
            </div>

            {cronometro && (
                <CronometroVisualOperacion
                    etiqueta={cronometro.etiqueta}
                    referenciaAt={cronometro.referenciaAt}
                    servidorAt={servidorAt}
                    modo={cronometro.modo}
                />
            )}

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {puedeAbrirJornada(estado, permisos) && (
                    <button
                        type="button"
                        className={`${THEME_BTN_PRIMARY} min-h-[44px] px-4 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest inline-flex items-center justify-center gap-2`}
                        disabled={cargando}
                        onClick={() => {
                            setAccion('abrir');
                            abrirJornada();
                        }}
                    >
                        {cargando && accion === 'abrir' ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden /> : <DoorOpen className="w-4 h-4" aria-hidden />}
                        Abrir jornada
                    </button>
                )}

                {puedeCerrarJornada(estado, permisos) && (
                    <button
                        type="button"
                        className={`${THEME_BTN_SECONDARY} min-h-[44px] px-4 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest inline-flex items-center justify-center gap-2`}
                        disabled={cargando}
                        onClick={() => setModalCerrar(true)}
                    >
                        <DoorClosed className="w-4 h-4" aria-hidden />
                        Cerrar jornada
                    </button>
                )}

                {puedeIniciarPausa(estado, permisos) && (
                    <button
                        type="button"
                        className={`${THEME_BTN_SECONDARY} min-h-[44px] px-4 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest inline-flex items-center justify-center gap-2`}
                        disabled={cargando}
                        onClick={() => {
                            setAccion('pausa');
                            iniciarPausa();
                        }}
                    >
                        {cargando && accion === 'pausa' ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden /> : <Pause className="w-4 h-4" aria-hidden />}
                        Iniciar pausa
                    </button>
                )}

                {puedeFinalizarPausa(estado, permisos) && (
                    <button
                        type="button"
                        className={`${THEME_BTN_PRIMARY} min-h-[44px] px-4 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest inline-flex items-center justify-center gap-2`}
                        disabled={cargando}
                        onClick={() => {
                            setAccion('fin-pausa');
                            finalizarPausa();
                        }}
                    >
                        {cargando && accion === 'fin-pausa' ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden /> : <Play className="w-4 h-4" aria-hidden />}
                        Finalizar pausa
                    </button>
                )}
            </div>

            {tieneAtencionAbierta && permisos?.pausa && (
                <p className="text-xs font-semibold text-amber-700 dark:text-amber-300 m-0">
                    No puedes pausar mientras tengas una atención abierta.
                </p>
            )}

            <ModalConfirmarAccion
                abierto={modalCerrar}
                titulo="Cerrar jornada"
                mensaje={
                    tieneAtencionAbierta
                        ? 'Tienes una atención en curso. La jornada pasará a «cerrada con atención»: no recibirás turnos nuevos, pero la atención actual continúa hasta que la cierres.'
                        : 'Al cerrar la jornada dejarás de recibir turnos nuevos. La cola de la sucursal no se vacía.'
                }
                etiquetaConfirmar="Cerrar jornada"
                variante="danger"
                onClose={() => setModalCerrar(false)}
                onConfirm={cerrarJornada}
            />
        </section>
    );
}
