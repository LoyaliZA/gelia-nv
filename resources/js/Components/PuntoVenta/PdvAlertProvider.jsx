import React, { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Volume2, VolumeX, WifiOff } from 'lucide-react';
import usePdvRealtime from '@/hooks/usePdvRealtime';
import usePdvAlertQueue from '@/hooks/usePdvAlertQueue';
import useSpeechAnnouncements from '@/hooks/useSpeechAnnouncements';
import usePdvAlertasPrefs from '@/hooks/usePdvAlertasPrefs';
import usePdvTerminalAlertas from '@/hooks/usePdvTerminalAlertas';
import PdvPreferenciasAlertas from '@/Components/PuntoVenta/PdvPreferenciasAlertas';
import PdvTerminalAlertasSucursal from '@/Components/PuntoVenta/PdvTerminalAlertasSucursal';
import WebPushService from '@/Services/WebPushService';
import NotificationBrowserService from '@/Services/NotificationBrowserService';
import {
    conexionDegradadaPdv,
    mensajeAlertaPdv,
    mensajeConexionDegradadaPdv,
    PDV_ESTADO_CONEXION,
} from '@/utils/pdvAlertQueueUtils';
import { debeRefrescarVistaPdv, claveRecargaVistaPdv } from '@/utils/pdvRealtimeMatrix';
import {
    crearCoordinadorRecargaPdv,
    crearRegistroEventIdsRecarga,
} from '@/utils/pdvRealtimeReload';
import {
    debeAnunciarVozPdv,
    debeReproducirSonidoPdv,
    debeReproducirTonoEventoPdv,
    mensajeFallbackWebPushPdv,
    PDV_PUSH_ESTADO,
    reproducirTonoPdv,
} from '@/utils/pdvAlertasPrefs';
import { mensajeTtsTerminalPdv } from '@/utils/pdvAlertasCatalog';
import { mensajeTtsPersonalPdv, PDV_TTS_ESTADO } from '@/utils/pdvSpeechUtils';
import { resolverModoAudioPdv } from '@/utils/pdvAlertasAudiencia';

const PdvAlertContext = createContext(null);

export function usePdvAlertContext() {
    return useContext(PdvAlertContext);
}

/**
 * Registra recarga silenciosa ante eventos realtime.
 * Preferir `vista` (matriz formal); `dominio`/`filtro` se conservan para consumidores legados.
 */
export function usePdvAlertReload({
    vista = null,
    userId = null,
    dominio = null,
    refrescar,
    filtro = null,
    habilitado = true,
    resincronizar = true,
}) {
    const ctx = usePdvAlertContext();

    useEffect(() => {
        if (!habilitado || !refrescar || !ctx?.registrarRecarga) {
            return undefined;
        }

        const ejecutarRecarga = async () => refrescar({ silencioso: true });

        const matcher = (envelope) => {
            if (vista) {
                return debeRefrescarVistaPdv(vista, envelope, { userId });
            }
            if (dominio && envelope.dominio !== dominio) return false;
            if (typeof filtro === 'function' && !filtro(envelope)) return false;
            return true;
        };

        const clave = vista ? claveRecargaVistaPdv(vista) : (dominio || 'legacy');

        const liberadores = [
            ctx.registrarRecarga(matcher, ejecutarRecarga, clave),
        ];

        if (resincronizar && ctx.registrarResincronizacion) {
            liberadores.push(ctx.registrarResincronizacion(ejecutarRecarga));
        }

        return () => {
            liberadores.forEach((liberar) => liberar?.());
        };
    }, [ctx, vista, userId, dominio, refrescar, filtro, habilitado, resincronizar]);
}

function PdvConexionDegradadaBanner({ estadoConexion }) {
    if (!conexionDegradadaPdv(estadoConexion)) return null;

    const esReconectando = estadoConexion === PDV_ESTADO_CONEXION.conectando;

    return (
        <div
            className="rounded-2xl border px-4 py-3 flex items-start gap-3 text-sm"
            style={{
                borderColor: 'color-mix(in srgb, var(--color-aviso) 35%, transparent)',
                backgroundColor: 'color-mix(in srgb, var(--color-aviso) 12%, transparent)',
                color: 'var(--color-texto)',
            }}
            role="status"
            aria-live="polite"
            data-pdv-conexion-degradada
        >
            <WifiOff className={`w-4 h-4 mt-0.5 shrink-0 ${esReconectando ? 'animate-pulse' : ''}`} aria-hidden />
            <p className="leading-snug m-0">{mensajeConexionDegradadaPdv(estadoConexion)}</p>
        </div>
    );
}

function PdvTtsEstadoBanner({ estadoTts, silenciado, vozHabilitada }) {
    if (!vozHabilitada || silenciado) return null;

    if (estadoTts === PDV_TTS_ESTADO.bloqueado) {
        return (
            <div
                className="rounded-2xl border px-4 py-2 text-xs"
                style={{
                    borderColor: 'color-mix(in srgb, var(--color-aviso) 30%, transparent)',
                    backgroundColor: 'color-mix(in srgb, var(--color-aviso) 8%, transparent)',
                    color: 'var(--color-texto)',
                }}
                role="status"
                aria-live="polite"
                data-pdv-tts-bloqueado
            >
                Audio no disponible. Las alertas visuales continúan activas.
            </div>
        );
    }

    if (estadoTts === PDV_TTS_ESTADO.no_soportado) {
        return (
            <div
                className="rounded-2xl border px-4 py-2 text-xs opacity-80"
                style={{
                    borderColor: 'color-mix(in srgb, var(--color-texto) 12%, transparent)',
                    color: 'var(--color-texto)',
                }}
                role="status"
                data-pdv-tts-no-soportado
            >
                Este navegador no reproduce anuncios por voz. Las alertas visuales continúan activas.
            </div>
        );
    }

    return null;
}

function PdvPushFallbackBanner({ estadoPush, mensaje, onActivar, activando }) {
    if (!mensaje) return null;

    return (
        <div
            className="rounded-2xl border px-4 py-3 text-xs flex items-start gap-3"
            style={{
                borderColor: 'color-mix(in srgb, var(--color-aviso) 30%, transparent)',
                backgroundColor: 'color-mix(in srgb, var(--color-aviso) 8%, transparent)',
                color: 'var(--color-texto)',
            }}
            role="status"
            aria-live="polite"
            data-pdv-push-fallback-banner
        >
            <p className="leading-snug m-0 flex-1">{mensaje}</p>
            {estadoPush === PDV_PUSH_ESTADO.pendiente && typeof onActivar === 'function' && (
                <button
                    type="button"
                    className="shrink-0 text-[10px] font-black uppercase tracking-widest underline min-h-[44px]"
                    disabled={activando}
                    onClick={onActivar}
                >
                    {activando ? '…' : 'Activar'}
                </button>
            )}
        </div>
    );
}

function PdvTtsSilencioControl({ silenciado, alternarSilencio, ttsDisponible }) {
    if (!ttsDisponible) return null;

    return (
        <button
            type="button"
            className="fixed bottom-4 left-4 z-40 rounded-full border shadow-lg p-3 theme-surface pointer-events-auto min-h-[44px] min-w-[44px]"
            style={{ borderColor: 'color-mix(in srgb, var(--color-primario) 25%, transparent)' }}
            onClick={() => alternarSilencio()}
            aria-pressed={silenciado}
            aria-label={silenciado ? 'Activar anuncios por voz y timbre en este terminal' : 'Silenciar este terminal'}
            title={silenciado ? 'Activar sonido' : 'Silenciar terminal'}
            data-pdv-tts-silencio
        >
            {silenciado ? (
                <VolumeX className="w-5 h-5" aria-hidden />
            ) : (
                <Volume2 className="w-5 h-5" aria-hidden />
            )}
        </button>
    );
}

function PdvAlertQueueVisual({ cola, onDescartar }) {
    if (!cola.length) return null;

    return (
        <div
            className="fixed bottom-4 right-4 z-40 flex flex-col gap-2 max-w-sm w-[min(100vw-2rem,20rem)] pointer-events-none"
            aria-live="polite"
            aria-label="Actualizaciones recientes de punto de venta"
            data-pdv-alerta-cola
        >
            {cola.map((alerta) => (
                <div
                    key={alerta.event_id}
                    className="pointer-events-auto rounded-2xl border shadow-lg px-4 py-3 text-sm flex items-start gap-3 theme-surface"
                    style={{ borderColor: 'color-mix(in srgb, var(--color-primario) 25%, transparent)' }}
                >
                    <div className="min-w-0 flex-1">
                        <p className="font-semibold text-[11px] uppercase tracking-wide opacity-70">
                            Tiempo real
                        </p>
                        <p className="mt-0.5 leading-snug">{alerta.mensaje}</p>
                    </div>
                    <button
                        type="button"
                        className="shrink-0 text-[10px] font-bold uppercase tracking-wider opacity-60 hover:opacity-100 min-h-[44px] px-2"
                        onClick={() => onDescartar(alerta.event_id)}
                        aria-label="Descartar alerta"
                    >
                        Cerrar
                    </button>
                </div>
            ))}
        </div>
    );
}

export default function PdvAlertProvider({
    children,
    sucursalId = null,
    userId = null,
    habilitado = true,
    mostrarCola = true,
    mostrarPreferencias = true,
}) {
    const { auth, tonos_alertas: tonosAlertas = [], capacidades = {} } = usePage().props;
    const prefs = usePdvAlertasPrefs({
        temaVisual: auth?.tema_visual,
        webpush: auth?.webpush,
    });
    const terminal = usePdvTerminalAlertas({
        sucursalId,
        autorizado: Boolean(capacidades?.alertas_sucursal),
        habilitado: habilitado && Boolean(sucursalId),
    });
    const [activandoPush, setActivandoPush] = useState(false);

    const { cola, encolar, descartar, reiniciar } = usePdvAlertQueue();
    const vozHabilitada = prefs.canalesEfectivos.voz;
    const audioAnunciadosRef = useRef(new Set());

    const resolverTextoTts = useCallback((envelope) => {
        const modo = resolverModoAudioPdv(envelope, {
            userId,
            terminalActiva: terminal.terminalActiva && terminal.esLiderAudio(),
            prefsUsuario: prefs.prefsUsuario,
            silencioTerminal: prefs.silencioTerminal,
            audioYaAnunciado: audioAnunciadosRef.current.has(envelope?.event_id),
        });

        if (modo === 'terminal') {
            return mensajeTtsTerminalPdv(envelope);
        }
        if (modo === 'personal') {
            return mensajeTtsPersonalPdv(envelope);
        }
        return null;
    }, [userId, terminal, prefs.prefsUsuario, prefs.silencioTerminal]);

    const {
        encolar: encolarTts,
        estadoTts,
        reiniciar: reiniciarTts,
        ttsDisponible,
    } = useSpeechAnnouncements({
        habilitado: habilitado && vozHabilitada,
        silenciado: prefs.silencioTerminal,
        resolverTexto: resolverTextoTts,
    });
    const recargasRef = useRef(new Set());
    const resincronizacionesRef = useRef(new Set());
    const eventIdsRecargaRef = useRef(crearRegistroEventIdsRecarga());
    const coordinadorRecargaRef = useRef(crearCoordinadorRecargaPdv());
    const conexionPrevRef = useRef(PDV_ESTADO_CONEXION.desconectado);
    const contextoPrevRef = useRef({ sucursalId, userId });
    const ultimoToastRef = useRef({ eventId: null, at: 0 });
    const [ultimaActualizacionConfirmada, setUltimaActualizacionConfirmada] = useState(null);

    const marcarActualizacionConfirmada = useCallback((resultado) => {
        const marca = resultado?.servidor_at
            ?? resultado?.tablero?.servidor_at
            ?? new Date().toISOString();
        setUltimaActualizacionConfirmada(marca);
    }, []);

    const registrarRecarga = useCallback((matcher, handler, clave = null) => {
        const entrada = { matcher, handler, clave: clave || handler };
        recargasRef.current.add(entrada);
        return () => recargasRef.current.delete(entrada);
    }, []);

    const registrarResincronizacion = useCallback((handler) => {
        resincronizacionesRef.current.add(handler);
        return () => resincronizacionesRef.current.delete(handler);
    }, []);

    const resincronizarTodos = useCallback(async () => {
        const handlers = [...resincronizacionesRef.current];
        if (!handlers.length) return;

        const resultados = await Promise.all(handlers.map(async (handler) => {
            try {
                return await handler({ motivo: 'resincronizacion' });
            } catch {
                return null;
            }
        }));

        const confirmado = resultados.find(Boolean);
        if (confirmado) {
            marcarActualizacionConfirmada(confirmado);
        }
    }, [marcarActualizacionConfirmada]);

    const programarRecargasPorEvento = useCallback((envelope) => {
        if (!eventIdsRecargaRef.current.marcar(envelope.event_id)) {
            return;
        }

        recargasRef.current.forEach(({ matcher, handler, clave }) => {
            if (!matcher(envelope)) return;

            coordinadorRecargaRef.current.programar(clave, async () => {
                try {
                    const resultado = await handler(envelope);
                    if (resultado) {
                        marcarActualizacionConfirmada(resultado);
                    }
                } catch {
                    // ponytail: conservar último estado conocido ante fallo de recarga
                }
            });
        });
    }, [marcarActualizacionConfirmada]);

    const manejarEvento = useCallback((envelope) => {
        const encolado = encolar(envelope);

        const modoAudio = resolverModoAudioPdv(envelope, {
            userId,
            terminalActiva: terminal.terminalActiva && terminal.esLiderAudio(),
            prefsUsuario: prefs.prefsUsuario,
            silencioTerminal: prefs.silencioTerminal,
            audioYaAnunciado: audioAnunciadosRef.current.has(envelope?.event_id),
        });

        if (modoAudio && !audioAnunciadosRef.current.has(envelope.event_id)) {
            audioAnunciadosRef.current.add(envelope.event_id);

            if (
                debeReproducirSonidoPdv(envelope, prefs.prefsUsuario, prefs.silencioTerminal, {
                    userId,
                    terminalActiva: terminal.terminalActiva,
                    modo: modoAudio,
                })
                && debeReproducirTonoEventoPdv(envelope, modoAudio)
            ) {
                reproducirTonoPdv(prefs.prefsUsuario.tono_id, tonosAlertas);
            }

            if (
                debeAnunciarVozPdv(envelope, prefs.prefsUsuario, prefs.silencioTerminal, {
                    userId,
                    terminalActiva: terminal.terminalActiva,
                    modo: modoAudio,
                })
            ) {
                encolarTts(envelope);
            }
        }

        if (encolado) {
            const ahora = Date.now();
            if (
                ultimoToastRef.current.eventId !== envelope.event_id
                || ahora - ultimoToastRef.current.at > 1_500
            ) {
                ultimoToastRef.current = { eventId: envelope.event_id, at: ahora };
                window.dispatchEvent(new CustomEvent('gelia-toast', {
                    detail: {
                        mensaje: mensajeAlertaPdv(envelope),
                        tipo: 'info',
                    },
                }));
            }
        }

        programarRecargasPorEvento(envelope);
    }, [
        encolar,
        encolarTts,
        prefs.prefsUsuario,
        prefs.silencioTerminal,
        tonosAlertas,
        programarRecargasPorEvento,
        userId,
        terminal,
    ]);

    const { estadoConexion } = usePdvRealtime({
        sucursalId,
        userId,
        habilitado: habilitado && Boolean(sucursalId),
        onEvent: manejarEvento,
    });

    useEffect(() => {
        const previo = contextoPrevRef.current;
        const cambioContexto = previo.sucursalId !== sucursalId || previo.userId !== userId;
        contextoPrevRef.current = { sucursalId, userId };

        reiniciar();
        reiniciarTts();
        audioAnunciadosRef.current.clear();
        eventIdsRecargaRef.current.reiniciar();
        coordinadorRecargaRef.current.cancelarTodo();

        if (cambioContexto && (previo.sucursalId != null || previo.userId != null)) {
            resincronizarTodos();
        }
    }, [sucursalId, userId, reiniciar, reiniciarTts, resincronizarTodos]);

    useEffect(() => {
        const previo = conexionPrevRef.current;
        conexionPrevRef.current = estadoConexion;

        if (
            previo !== PDV_ESTADO_CONEXION.conectado
            && estadoConexion === PDV_ESTADO_CONEXION.conectado
            && conexionDegradadaPdv(previo)
        ) {
            eventIdsRecargaRef.current.reiniciar();
            resincronizarTodos();
        }
    }, [estadoConexion, resincronizarTodos]);

    useEffect(() => () => {
        coordinadorRecargaRef.current.cancelarTodo();
    }, []);

    const activarPush = useCallback(async () => {
        if (!WebPushService.isSupported()) return;
        setActivandoPush(true);
        try {
            await NotificationBrowserService.requestDesktopPermissions();
            const result = await WebPushService.ensureSubscribed();
            NotificationBrowserService.setServiceWorkerPushActive(result?.ok === true);
        } finally {
            setActivandoPush(false);
        }
    }, []);

    const mensajePush = mensajeFallbackWebPushPdv(prefs.estadoPush);

    const valor = useMemo(() => ({
        estadoConexion,
        ultimaActualizacionConfirmada,
        cola,
        registrarRecarga,
        registrarResincronizacion,
        descartarAlerta: descartar,
        estadoTts,
        silenciado: prefs.silencioTerminal,
        alternarSilencioTts: prefs.alternarSilencioTerminal,
        ttsDisponible,
        prefs,
        estadoPush: prefs.estadoPush,
    }), [
        estadoConexion,
        ultimaActualizacionConfirmada,
        cola,
        registrarRecarga,
        registrarResincronizacion,
        descartar,
        estadoTts,
        prefs,
        ttsDisponible,
    ]);

    return (
        <PdvAlertContext.Provider value={valor}>
            <div className="space-y-4">
                <PdvConexionDegradadaBanner estadoConexion={estadoConexion} />
                <PdvPushFallbackBanner
                    estadoPush={prefs.estadoPush}
                    mensaje={mensajePush}
                    onActivar={activarPush}
                    activando={activandoPush}
                />
                <PdvTtsEstadoBanner
                    estadoTts={estadoTts}
                    silenciado={prefs.silencioTerminal}
                    vozHabilitada={prefs.prefsUsuario.canales.voz}
                />
                {mostrarPreferencias && (
                    <>
                        {capacidades?.alertas_sucursal && (
                            <PdvTerminalAlertasSucursal
                            terminal={terminal}
                            tonosAlertas={tonosAlertas}
                            prefsUsuario={prefs.prefsUsuario}
                            silencioTerminal={prefs.silencioTerminal}
                            estadoTts={estadoTts}
                            onProbarVoz={() => {
                                const demo = mensajeTtsTerminalPdv({
                                    tipo: 'turno.alta',
                                    datos: { folio: 'V-0001' },
                                });
                                if (demo) {
                                    encolarTts({
                                        event_id: `demo-terminal-${Date.now()}`,
                                        tipo: 'turno.alta',
                                        datos: { folio: 'V-0001' },
                                    });
                                }
                            }}
                            />
                        )}
                        <PdvPreferenciasAlertas
                        prefs={prefs}
                        tonosAlertas={tonosAlertas}
                        estadoConexion={estadoConexion}
                        estadoTts={estadoTts}
                        onActivarPush={activarPush}
                        activandoPush={activandoPush}
                        />
                    </>
                )}
                {children}
            </div>
            <PdvTtsSilencioControl
                silenciado={prefs.silencioTerminal}
                alternarSilencio={prefs.alternarSilencioTerminal}
                ttsDisponible={prefs.prefsUsuario.canales.sonido || prefs.prefsUsuario.canales.voz}
            />
            {mostrarCola && (
                <PdvAlertQueueVisual cola={cola} onDescartar={descartar} />
            )}
        </PdvAlertContext.Provider>
    );
}
