import React, { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Volume2, VolumeX } from 'lucide-react';
import usePdvRealtime from '@/hooks/usePdvRealtime';
import usePdvAlertQueue from '@/hooks/usePdvAlertQueue';
import useSpeechAnnouncements from '@/hooks/useSpeechAnnouncements';
import usePdvAlertasPrefs from '@/hooks/usePdvAlertasPrefs';
import usePdvTerminalAlertas from '@/hooks/usePdvTerminalAlertas';
import WebPushService from '@/Services/WebPushService';
import NotificationBrowserService from '@/Services/NotificationBrowserService';
import {
    conexionDegradadaPdv,
    mensajeAlertaPdv,
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
    reproducirTonoPdv,
} from '@/utils/pdvAlertasPrefs';
import { mensajeTtsTerminalPdv } from '@/utils/pdvAlertasCatalog';
import { mensajeTtsPersonalPdv } from '@/utils/pdvSpeechUtils';
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

export default function PdvAlertProvider({
    children,
    sucursalId = null,
    userId = null,
    habilitado = true,
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

    const { encolar, reiniciar } = usePdvAlertQueue();
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
        desbloquearAudio,
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
        recargasRef.current.forEach(({ matcher, handler, clave }) => {
            if (!matcher(envelope)) return;

            const idDedupe = `${envelope.event_id}:${clave || 'legacy'}`;
            if (!eventIdsRecargaRef.current.marcar(idDedupe)) return;

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

    const probarVozTerminal = useCallback(() => {
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
    }, [encolarTts]);

    const valor = useMemo(() => ({
        estadoConexion,
        ultimaActualizacionConfirmada,
        registrarRecarga,
        registrarResincronizacion,
        resincronizar: resincronizarTodos,
        estadoTts,
        silenciado: prefs.silencioTerminal,
        alternarSilencioTts: prefs.alternarSilencioTerminal,
        desbloquearAudio,
        ttsDisponible,
        prefs,
        estadoPush: prefs.estadoPush,
        activarPush,
        activandoPush,
        terminal,
        capacidades,
        tonosAlertas,
        probarVozTerminal,
    }), [
        estadoConexion,
        ultimaActualizacionConfirmada,
        registrarRecarga,
        registrarResincronizacion,
        resincronizarTodos,
        estadoTts,
        prefs,
        ttsDisponible,
        desbloquearAudio,
        activarPush,
        activandoPush,
        terminal,
        capacidades,
        tonosAlertas,
        probarVozTerminal,
    ]);

    return (
        <PdvAlertContext.Provider value={valor}>
            <div className="space-y-4" data-pdv-alert-provider data-mostrar-preferencias={mostrarPreferencias}>
                {children}
            </div>
            <PdvTtsSilencioControl
                silenciado={prefs.silencioTerminal}
                alternarSilencio={prefs.alternarSilencioTerminal}
                ttsDisponible={prefs.prefsUsuario.canales.sonido || prefs.prefsUsuario.canales.voz}
            />
        </PdvAlertContext.Provider>
    );
}
