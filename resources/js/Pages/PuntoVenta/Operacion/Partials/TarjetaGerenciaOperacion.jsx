import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { CalendarClock, ChevronDown, Clock, Loader2, ShieldAlert } from 'lucide-react';
import ModalConfirmarAccion from '../../../ControlPedidos/Partials/ModalConfirmarAccion';
import { geliaCardClass, THEME_BTN_PRIMARY, THEME_BTN_SECONDARY } from '../../../../utils/geliaTheme';
import {
    esConflictoVersion,
    isoDesdeDatetimeLocal,
    mensajeErrorOperacion,
    puedeAmpliarHorario,
    puedeAbrirSucursalManualmente,
    puedeCerrarSucursal,
    puedeConfigurarHorarioCierre,
    puedeReabrirSucursal,
    resumenHorarioOperativo,
    valorDatetimeLocalDesdeIso,
} from './operacionUtils';

const STORAGE_HORARIO = 'pdv:operacion:horario_expandido';
const STORAGE_GERENCIA = 'pdv:operacion:gerencia_expandida';

export default function TarjetaGerenciaOperacion({
    estado,
    permisos,
    onActualizado,
    onConflicto,
    onError,
}) {
    const [cargando, setCargando] = useState(false);
    const [modalCierre, setModalCierre] = useState(false);
    const [modalReabrir, setModalReabrir] = useState(false);
    const [modalApertura, setModalApertura] = useState(false);
    const [ampliacionLocal, setAmpliacionLocal] = useState('');
    const [horaApertura, setHoraApertura] = useState(estado?.horario_cierre?.hora_apertura || '');
    const [horaCierre, setHoraCierre] = useState(estado?.horario_cierre?.hora_cierre || '19:00');
    const [zonaHoraria, setZonaHoraria] = useState(estado?.horario_cierre?.zona_horaria || '');
    const [horarioAbierto, setHorarioAbierto] = useState(() => {
        if (typeof window === 'undefined') return false;
        return window.sessionStorage.getItem(STORAGE_HORARIO) === '1';
    });
    const [gerenciaAbierta, setGerenciaAbierta] = useState(() => {
        if (typeof window === 'undefined') return false;
        return window.sessionStorage.getItem(STORAGE_GERENCIA) === '1';
    });

    const alternarGerencia = () => {
        setGerenciaAbierta((actual) => {
            const siguiente = !actual;
            window.sessionStorage.setItem(STORAGE_GERENCIA, siguiente ? '1' : '0');
            return siguiente;
        });
    };

    useEffect(() => {
        setHoraApertura(estado?.horario_cierre?.hora_apertura || '');
        setHoraCierre(estado?.horario_cierre?.hora_cierre || '19:00');
        setZonaHoraria(estado?.horario_cierre?.zona_horaria || '');
    }, [estado?.horario_cierre?.hora_apertura, estado?.horario_cierre?.hora_cierre, estado?.horario_cierre?.zona_horaria]);

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
            setModalReabrir(false);
            setModalApertura(false);
        }
    };

    const abrirSucursal = () => ejecutar(
        () => axios.post(route('punto_venta.operacion.jornada.abrir_sucursal'), {
            version: estado?.sucursal_dia?.version,
        }),
        'inicio manual de jornada',
    );

    const cerrarSucursal = () => ejecutar(
        () => axios.post(route('punto_venta.operacion.jornada.cerrar_sucursal'), {
            version: estado?.sucursal_dia?.version,
        }),
        'cierre manual de sucursal',
    );

    const reabrirSucursal = () => ejecutar(
        () => axios.post(route('punto_venta.operacion.jornada.reabrir_sucursal'), {
            version: estado?.sucursal_dia?.version,
        }),
        'reapertura de sucursal',
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
            hora_apertura: horaApertura || null,
            hora_cierre: horaCierre,
            zona_horaria: zonaHoraria || null,
        }),
        'configuración de horario operativo',
    );

    const mostrarGerencia = puedeCerrarSucursal(estado, permisos)
        || puedeAbrirSucursalManualmente(estado, permisos)
        || puedeReabrirSucursal(estado, permisos)
        || puedeAmpliarHorario(estado, permisos)
        || puedeConfigurarHorarioCierre(permisos);

    if (!mostrarGerencia) return null;

    return (
        <section className={`${geliaCardClass()} p-4`} aria-labelledby="gerencia-operacion-titulo">
            <button
                type="button"
                className="flex w-full items-start gap-3 text-left"
                aria-expanded={gerenciaAbierta}
                aria-controls="gerencia-operacion-detalle"
                onClick={alternarGerencia}
            >
                <ShieldAlert className="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" aria-hidden />
                <span className="min-w-0 flex-1">
                    <span id="gerencia-operacion-titulo" className="block text-sm font-black uppercase tracking-widest theme-text-main">
                        Gerencia del día
                    </span>
                    <span className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1">
                        <EstadoJornadaSucursal estado={estado} compacto />
                        <span className="text-xs font-semibold theme-text-muted">
                            {resumenHorarioOperativo(estado)}
                        </span>
                    </span>
                </span>
                <ChevronDown className={`w-4 h-4 theme-text-muted shrink-0 mt-1 transition-transform ${gerenciaAbierta ? 'rotate-180' : ''}`} aria-hidden />
            </button>

            <div className={`grid transition-[grid-template-rows] duration-200 ${gerenciaAbierta ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'}`}>
                <div id="gerencia-operacion-detalle" className="overflow-hidden min-h-0">
            <div className="space-y-4 pt-4">
            <p className="text-xs font-semibold theme-text-muted m-0">
                El inicio o cierre manual tiene prioridad sobre el horario automático de hoy.
            </p>

            {puedeConfigurarHorarioCierre(permisos) && (
                <div className="rounded-2xl border theme-border">
                    <button
                        type="button"
                        className="flex w-full items-center gap-2 p-4 text-left"
                        aria-expanded={horarioAbierto}
                        onClick={() => {
                            setHorarioAbierto((actual) => {
                                const siguiente = !actual;
                                window.sessionStorage.setItem(STORAGE_HORARIO, siguiente ? '1' : '0');
                                return siguiente;
                            });
                        }}
                    >
                        <Clock className="w-4 h-4 theme-text-muted shrink-0" aria-hidden />
                        <span className="min-w-0 flex-1">
                            <span className="block text-xs font-black uppercase tracking-widest theme-text-main">
                                Horario operativo
                            </span>
                            <span className="block text-xs font-semibold theme-text-muted mt-1">
                                {resumenHorarioOperativo(estado)}
                            </span>
                        </span>
                        <ChevronDown className={`w-4 h-4 theme-text-muted shrink-0 transition-transform ${horarioAbierto ? 'rotate-180' : ''}`} aria-hidden />
                    </button>
                    <div className={`grid transition-[grid-template-rows] duration-200 ${horarioAbierto ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'}`}>
                        <div className="overflow-hidden min-h-0">
                    <div className="space-y-3 px-4 pb-4">
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label className="space-y-1">
                            <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Apertura</span>
                            <input
                                type="time"
                                className="w-full min-h-[44px] rounded-xl border theme-border theme-element px-3 text-sm font-semibold"
                                value={horaApertura}
                                onChange={(event) => setHoraApertura(event.target.value)}
                            />
                        </label>
                        <label className="space-y-1">
                            <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">Cierre</span>
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
                            Sin horario persistido; al guardar se usará el valor provisional de planeación. Deja apertura vacía para no restringir altas.
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
                        </div>
                    </div>
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

            {puedeAbrirSucursalManualmente(estado, permisos) && (
                <button
                    type="button"
                    className={`${THEME_BTN_PRIMARY} w-full min-h-[44px] px-4 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest`}
                    disabled={cargando}
                    onClick={() => setModalApertura(true)}
                >
                    Iniciar jornada
                </button>
            )}

            {puedeReabrirSucursal(estado, permisos) && (
                <button
                    type="button"
                    className={`${THEME_BTN_PRIMARY} w-full min-h-[44px] px-4 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest`}
                    disabled={cargando}
                    onClick={() => setModalReabrir(true)}
                >
                    Reabrir sucursal
                </button>
            )}

            {puedeCerrarSucursal(estado, permisos) && (
                <button
                    type="button"
                    className="theme-btn-danger w-full min-h-[44px] px-4 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest"
                    disabled={cargando}
                    onClick={() => setModalCierre(true)}
                >
                    Cerrar sucursal
                </button>
            )}
            </div>
                </div>
            </div>

            <ModalConfirmarAccion
                abierto={modalApertura}
                titulo="Iniciar jornada"
                mensaje="La sucursal comenzará a aceptar altas nuevas de turnos. Si el horario automático aún no llega, ya no se ejecutará el inicio automático de hoy."
                etiquetaConfirmar="Iniciar jornada"
                onClose={() => setModalApertura(false)}
                onConfirm={abrirSucursal}
            />

            <ModalConfirmarAccion
                abierto={modalReabrir}
                titulo="Reabrir sucursal"
                mensaje="La sucursal volverá a aceptar altas nuevas de turnos. No activa vendedores automáticamente."
                etiquetaConfirmar="Reabrir sucursal"
                onClose={() => setModalReabrir(false)}
                onConfirm={reabrirSucursal}
            />

            <ModalConfirmarAccion
                abierto={modalCierre}
                titulo="Cierre manual del día"
                mensaje="La sucursal dejará de aceptar altas. Quien no está atendiendo sale de la jornada. Quien atiende termina su turno y queda fuera. La cola no se cancela."
                etiquetaConfirmar="Confirmar cierre"
                variante="danger"
                onClose={() => setModalCierre(false)}
                onConfirm={cerrarSucursal}
            />
        </section>
    );
}

function EstadoJornadaSucursal({ estado, compacto = false }) {
    const dia = estado?.sucursal_dia;
    if (!dia) return null;

    const abierta = dia.acepta_altas !== false;
    let etiqueta = 'Cerrada';
    let clase = 'bg-slate-500/15 theme-text-muted';

    if (abierta) {
        if (dia.origen_apertura === 'manual') {
            etiqueta = 'Abierta (manual)';
            clase = 'bg-sky-500/15 text-sky-700 dark:text-sky-300';
        } else if (dia.origen_apertura === 'automatica') {
            etiqueta = 'Abierta (automática)';
            clase = 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300';
        } else {
            etiqueta = 'Abierta';
            clase = 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300';
        }
    }

    const badge = (
        <span className={`inline-flex px-2 py-1 rounded-lg text-[10px] font-black uppercase tracking-widest ${clase}`}>
            {etiqueta}
        </span>
    );

    if (compacto) return badge;

    return (
        <p className="text-xs font-semibold theme-text-muted m-0">
            Sucursal:
            {' '}
            {badge}
        </p>
    );
}
