import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { Clock, Loader2 } from 'lucide-react';
import { geliaCardClass, THEME_BTN_SECONDARY, THEME_INPUT } from '../../../../utils/geliaTheme';
import { mensajeErrorOperacion } from './operacionUtils';

const CAMPOS = [
    {
        clave: 'espera_inicial_minutos',
        etiqueta: 'Tiempo para iniciar atención',
        ayuda: 'Minutos después de asignar el turno para comenzar la atención.',
        soloEsperaManual: true,
    },
    {
        clave: 'prorroga_minutos',
        etiqueta: 'Tiempo máximo base',
        ayuda: 'Minutos de atención antes de marcar prórroga (alerta, no cierra la atención).',
    },
    {
        clave: 'aviso_tolerancia_espera_minutos',
        etiqueta: 'Tolerancia aviso de espera',
        ayuda: 'Minutos de anticipación al vencimiento de la espera inicial.',
        soloEsperaManual: true,
    },
    {
        clave: 'aviso_tolerancia_prorroga_minutos',
        etiqueta: 'Tolerancia aviso de prórroga',
        ayuda: 'Minutos de anticipación al límite de atención base.',
    },
    {
        clave: 'ventana_reatencion_minutos',
        etiqueta: 'Ventana de reatención',
        ayuda: 'Minutos para reasignar a un cliente después de cerrar la atención.',
    },
];

function valoresDesde(plazos) {
    return {
        espera_inicial_minutos: String(plazos?.espera_inicial_minutos ?? 5),
        prorroga_minutos: String(plazos?.prorroga_minutos ?? 20),
        aviso_tolerancia_espera_minutos: String(plazos?.aviso_tolerancia_espera_minutos ?? 1),
        aviso_tolerancia_prorroga_minutos: String(plazos?.aviso_tolerancia_prorroga_minutos ?? 2),
        ventana_reatencion_minutos: String(plazos?.ventana_reatencion_minutos ?? 90),
        inicio_atencion_automatico: Boolean(plazos?.inicio_atencion_automatico),
    };
}

export default function ConfiguracionPlazosTurnosPdv({
    plazos = null,
    cargarAlMontar = false,
    variante = 'pagina',
    onActualizado,
    onError,
}) {
    const [valores, setValores] = useState(() => valoresDesde(plazos));
    const [cargando, setCargando] = useState(false);
    const [cargandoInicial, setCargandoInicial] = useState(cargarAlMontar && !plazos);
    const inicioAutomatico = valores.inicio_atencion_automatico;

    useEffect(() => {
        if (!cargarAlMontar || plazos) return undefined;

        let cancelado = false;

        const cargar = async () => {
            setCargandoInicial(true);
            onError?.(null);

            try {
                const { data } = await axios.get(route('punto_venta.operacion.configuracion.plazos_turnos.consultar'));
                if (!cancelado) {
                    setValores(valoresDesde(data?.plazos_turnos));
                }
            } catch (err) {
                if (!cancelado) {
                    onError?.(mensajeErrorOperacion(err, 'cargar los tiempos de atención'));
                }
            } finally {
                if (!cancelado) {
                    setCargandoInicial(false);
                }
            }
        };

        cargar();

        return () => {
            cancelado = true;
        };
    }, [cargarAlMontar, onError, plazos]);

    useEffect(() => {
        if (!plazos) return;
        setValores(valoresDesde(plazos));
    }, [
        plazos?.espera_inicial_minutos,
        plazos?.prorroga_minutos,
        plazos?.aviso_tolerancia_espera_minutos,
        plazos?.aviso_tolerancia_prorroga_minutos,
        plazos?.ventana_reatencion_minutos,
        plazos?.inicio_atencion_automatico,
    ]);

    const guardar = async () => {
        setCargando(true);
        onError?.(null);

        try {
            const { data } = await axios.put(route('punto_venta.operacion.configuracion.plazos_turnos'), {
                espera_inicial_minutos: Number(valores.espera_inicial_minutos),
                prorroga_minutos: Number(valores.prorroga_minutos),
                aviso_tolerancia_espera_minutos: Number(valores.aviso_tolerancia_espera_minutos),
                aviso_tolerancia_prorroga_minutos: Number(valores.aviso_tolerancia_prorroga_minutos),
                ventana_reatencion_minutos: Number(valores.ventana_reatencion_minutos),
                inicio_atencion_automatico: valores.inicio_atencion_automatico,
            });
            setValores(valoresDesde(data?.plazos_turnos));
            await onActualizado?.(data);
        } catch (err) {
            onError?.(mensajeErrorOperacion(err, 'guardar los tiempos de atención'));
        } finally {
            setCargando(false);
        }
    };

    const contenedorClass = variante === 'modal'
        ? 'space-y-3'
        : `${geliaCardClass()} p-4 space-y-3`;

    if (cargandoInicial) {
        return (
            <section className={contenedorClass} aria-labelledby="plazos-turnos-titulo" data-pdv-plazos-turnos>
                <div className="flex items-center justify-center gap-2 py-6">
                    <Loader2 className="w-5 h-5 animate-spin theme-text-muted" aria-hidden />
                    <span className="text-sm font-semibold theme-text-muted">Cargando tiempos de atención…</span>
                </div>
            </section>
        );
    }

    return (
        <section className={contenedorClass} aria-labelledby="plazos-turnos-titulo" data-pdv-plazos-turnos>
            <div className="flex items-center gap-2">
                <Clock className="w-4 h-4 theme-text-muted shrink-0" aria-hidden />
                <h2 id="plazos-turnos-titulo" className="text-sm font-black uppercase tracking-widest theme-text-main m-0">
                    Tiempos de atención
                </h2>
            </div>
            <p className="text-xs font-semibold theme-text-muted m-0">
                La prórroga es una alerta visual al superar el tiempo máximo base; no agrega una fase extra de atención.
            </p>

            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 py-2 border-b theme-border">
                <div className="min-w-0">
                    <p className="text-sm font-semibold theme-text-main m-0">Inicio automático de atención</p>
                    <p className="text-xs font-semibold theme-text-muted m-0 mt-0.5">
                        {inicioAutomatico
                            ? 'La atención comienza al asignar el turno; el vendedor no necesita pulsar iniciar.'
                            : 'El vendedor debe iniciar la atención tras el tiempo de espera configurado.'}
                    </p>
                </div>
                <button
                    type="button"
                    className="gelia-switch shrink-0 scale-110 shadow-sm self-end sm:self-center"
                    data-active={inicioAutomatico}
                    onClick={() => setValores((actual) => ({
                        ...actual,
                        inicio_atencion_automatico: !actual.inicio_atencion_automatico,
                    }))}
                    aria-label={inicioAutomatico ? 'Desactivar inicio automático de atención' : 'Activar inicio automático de atención'}
                    aria-pressed={inicioAutomatico}
                >
                    <div className="gelia-switch-thumb shadow-md" />
                </button>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3">
                {CAMPOS.map((campo) => {
                    const deshabilitado = inicioAutomatico && campo.soloEsperaManual;

                    return (
                        <label key={campo.clave} className={`space-y-1 min-w-0 ${deshabilitado ? 'opacity-50' : ''}`}>
                            <span className="text-[10px] font-black uppercase tracking-widest theme-text-muted">
                                {campo.etiqueta}
                            </span>
                            <input
                                type="number"
                                min="1"
                                className={`${THEME_INPUT} w-full min-h-[44px]`}
                                value={valores[campo.clave]}
                                disabled={deshabilitado}
                                onChange={(event) => setValores((actual) => ({
                                    ...actual,
                                    [campo.clave]: event.target.value,
                                }))}
                                aria-describedby={`${campo.clave}-ayuda`}
                            />
                            <span id={`${campo.clave}-ayuda`} className="block text-[10px] font-semibold theme-text-muted leading-snug">
                                {deshabilitado
                                    ? 'No aplica con inicio automático activo.'
                                    : campo.ayuda}
                            </span>
                        </label>
                    );
                })}
            </div>

            <button
                type="button"
                className={`${THEME_BTN_SECONDARY} min-h-[44px] px-4 py-2.5 rounded-2xl text-[10px] font-black uppercase tracking-widest inline-flex items-center justify-center gap-2`}
                disabled={cargando}
                onClick={guardar}
            >
                {cargando ? <Loader2 className="w-4 h-4 animate-spin" aria-hidden /> : <Clock className="w-4 h-4" aria-hidden />}
                Guardar tiempos
            </button>
        </section>
    );
}
