import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { CalendarClock, Clock, Loader2, ShieldAlert } from 'lucide-react';
import ModalConfirmarAccion from '../../../ControlPedidos/Partials/ModalConfirmarAccion';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY } from '../../../../utils/geliaTheme';
import {
    esConflictoVersion,
    isoDesdeDatetimeLocal,
    mensajeErrorOperacion,
    puedeAmpliarHorario,
    puedeCerrarSucursal,
    puedeConfigurarHorarioCierre,
    valorDatetimeLocalDesdeIso,
} from './operacionUtils';

export default function TarjetaGerenciaOperacion({
    estado,
    permisos,
    onActualizado,
    onConflicto,
    onError,
}) {
    const [cargando, setCargando] = useState(false);
    const [modalCierre, setModalCierre] = useState(false);
    const [ampliacionLocal, setAmpliacionLocal] = useState('');
    const [horaCierre, setHoraCierre] = useState(estado?.horario_cierre?.hora_cierre || '19:00');
    const [zonaHoraria, setZonaHoraria] = useState(estado?.horario_cierre?.zona_horaria || '');

    useEffect(() => {
        setHoraCierre(estado?.horario_cierre?.hora_cierre || '19:00');
        setZonaHoraria(estado?.horario_cierre?.zona_horaria || '');
    }, [estado?.horario_cierre?.hora_cierre, estado?.horario_cierre?.zona_horaria]);

    const ejecutar = async (peticion, etiquetaAccion) => {
        setCargando(true);
        onError?.(null);

        try {
            const { data } = await peticion();
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
            setModalCierre(false);
        }
    };

    const cerrarSucursal = () => ejecutar(
        () => axios.post(route('punto_venta.operacion.jornada.cerrar_sucursal'), {
            version: estado?.sucursal_dia?.version,
        }),
        'cierre manual de sucursal',
    );

    const ampliarHorario = () => {
        const ampliacionIso = isoDesdeDatetimeLocal(ampliacionLocal);
        if (!ampliacionIso) {
            onError?.('Indica una fecha y hora válidas para la ampliación.');
            return null;
        }

        return ejecutar(
            () => axios.post(route('punto_venta.operacion.jornada.ampliar'), {
                version: estado?.sucursal_dia?.version,
                ampliacion_hasta_at: ampliacionIso,
            }),
            'ampliación de horario',
        );
    };

    const guardarHorarioCierre = () => ejecutar(
        () => axios.put(route('punto_venta.operacion.configuracion.horario_cierre'), {
            hora_cierre: horaCierre,
            zona_horaria: zonaHoraria || null,
        }),
        'configuración de horario de cierre',
    );

    const mostrarGerencia = puedeCerrarSucursal(estado, permisos)
        || puedeAmpliarHorario(estado, permisos)
        || puedeConfigurarHorarioCierre(permisos);

    if (!mostrarGerencia) return null;

    return (
        <section className={`${geliaCardClass()} p-5 space-y-4`} aria-labelledby="gerencia-operacion-titulo">
            <div className="flex items-start gap-3">
                <ShieldAlert className="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" aria-hidden />
                <div>
                    <h2 id="gerencia-operacion-titulo" className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                        Gerencia del día
                    </h2>
                    <p className="text-xs font-semibold theme-text-muted m-0 mt-1">
                        Cierre manual y ampliación tienen prioridad sobre el cierre automático de hoy.
                    </p>
                </div>
            </div>

            {puedeConfigurarHorarioCierre(permisos) && (
                <div className="space-y-3 rounded-2xl border theme-border p-4">
                    <div className="flex items-center gap-2">
                        <Clock className="w-4 h-4 theme-text-muted" aria-hidden />
                        <p className="text-xs font-black uppercase tracking-widest theme-text-main m-0">
                            Horario de cierre (sucursal activa)
                        </p>
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label className="space-y-1">
                            <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Hora</span>
                            <input
                                type="time"
                                className="w-full min-h-[44px] rounded-xl border theme-border theme-element px-3 text-sm font-semibold"
                                value={horaCierre}
                                onChange={(event) => setHoraCierre(event.target.value)}
                            />
                        </label>
                        <label className="space-y-1">
                            <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Zona horaria</span>
                            <input
                                type="text"
                                className="w-full min-h-[44px] rounded-xl border theme-border theme-element px-3 text-sm font-semibold"
                                value={zonaHoraria}
                                placeholder="America/Mexico_City"
                                onChange={(event) => setZonaHoraria(event.target.value)}
                            />
                        </label>
                    </div>
                    {!estado?.horario_cierre?.configurado && (
                        <p className="text-xs font-semibold text-amber-700 dark:text-amber-300 m-0">
                            Sin horario persistido; al guardar se usará el valor provisional de planeación.
                        </p>
                    )}
                    <button
                        type="button"
                        className={`${THEME_BTN_SECONDARY} min-h-[44px] px-4 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest inline-flex items-center justify-center gap-2`}
                        disabled={cargando}
                        onClick={guardarHorarioCierre}
                    >
                        {cargando ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden /> : <CalendarClock className="w-4 h-4" aria-hidden />}
                        Guardar horario
                    </button>
                </div>
            )}

            {puedeAmpliarHorario(estado, permisos) && (
                <div className="space-y-3 rounded-2xl border theme-border p-4">
                    <p className="text-xs font-black uppercase tracking-widest theme-text-main m-0">Ampliar horario de hoy</p>
                    <input
                        type="datetime-local"
                        className="w-full min-h-[44px] rounded-xl border theme-border theme-element px-3 text-sm font-semibold"
                        value={ampliacionLocal || valorDatetimeLocalDesdeIso(estado?.sucursal_dia?.ampliacion_hasta_at)}
                        onChange={(event) => setAmpliacionLocal(event.target.value)}
                    />
                    <button
                        type="button"
                        className={`${THEME_BTN_PRIMARY} min-h-[44px] px-4 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest inline-flex items-center justify-center gap-2`}
                        disabled={cargando}
                        onClick={ampliarHorario}
                    >
                        {cargando ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden /> : <CalendarClock className="w-4 h-4" aria-hidden />}
                        Ampliar hasta
                    </button>
                </div>
            )}

            {puedeCerrarSucursal(estado, permisos) && (
                <button
                    type="button"
                    className="theme-btn-danger w-full min-h-[44px] px-4 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest"
                    disabled={cargando}
                    onClick={() => setModalCierre(true)}
                >
                    Cerrar sucursal (sin altas nuevas)
                </button>
            )}

            <ModalConfirmarAccion
                abierto={modalCierre}
                titulo="Cierre manual del día"
                mensaje="La sucursal dejará de aceptar altas nuevas. Este cierre manual invalida el automático de hoy. No elimina la cola ni corta atenciones en curso."
                etiquetaConfirmar="Confirmar cierre"
                variante="danger"
                onClose={() => setModalCierre(false)}
                onConfirm={cerrarSucursal}
            />
        </section>
    );
}
