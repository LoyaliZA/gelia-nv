import React, { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { Volume2, VolumeX, WifiOff } from 'lucide-react';
import usePdvRealtime from '@/hooks/usePdvRealtime';
import usePdvAlertQueue from '@/hooks/usePdvAlertQueue';
import useSpeechAnnouncements from '@/hooks/useSpeechAnnouncements';
import usePdvAlertasPrefs from '@/hooks/usePdvAlertasPrefs';
import PdvPreferenciasAlertas from '@/Components/PuntoVenta/PdvPreferenciasAlertas';
import WebPushService from '@/Services/WebPushService';
import NotificationBrowserService from '@/Services/NotificationBrowserService';
import {
    conexionDegradadaPdv,
    mensajeAlertaPdv,
    mensajeConexionDegradadaPdv,
    PDV_ESTADO_CONEXION,
} from '@/utils/pdvAlertQueueUtils';
import {
    debeReproducirSonidoPdv,
    mensajeFallbackWebPushPdv,
    PDV_PUSH_ESTADO,
    reproducirTonoPdv,
} from '@/utils/pdvAlertasPrefs';
import { PDV_TTS_ESTADO } from '@/utils/pdvSpeechUtils';

const PdvAlertContext = createContext(null);

export function usePdvAlertContext() {
    return useContext(PdvAlertContext);
}

/**
 * Registra recarga silenciosa ante eventos realtime del dominio indicado.
 */
export function usePdvAlertReload({ dominio = null, refrescar, filtro = null, habilitado = true }) {
    const ctx = usePdvAlertContext();

    useEffect(() => {
        if (!habilitado || !refrescar || !ctx?.registrarRecarga) {
            return undefined;
        }

        return ctx.registrarRecarga((envelope) => {
            if (dominio && envelope.dominio !== dominio) return false;
            if (typeof filtro === 'function' && !filtro(envelope)) return false;
            return true;
        }, refrescar);
    }, [ctx, dominio, refrescar, filtro, habilitado]);
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
    const { auth, tonos_alertas: tonosAlertas = [] } = usePage().props;
    const prefs = usePdvAlertasPrefs({
        temaVisual: auth?.tema_visual,
        webpush: auth?.webpush,
    });
    const [activandoPush, setActivandoPush] = useState(false);

    const { cola, encolar, descartar, reiniciar } = usePdvAlertQueue();
    const vozHabilitada = prefs.canalesEfectivos.voz;
    const {
        encolar: encolarTts,
        estadoTts,
        reiniciar: reiniciarTts,
        ttsDisponible,
    } = useSpeechAnnouncements({
        habilitado: habilitado && vozHabilitada,
        silenciado: prefs.silencioTerminal,
    });
    const recargasRef = useRef(new Set());
    const ultimoToastRef = useRef({ eventId: null, at: 0 });

    const registrarRecarga = useCallback((matcher, handler) => {
        const entrada = { matcher, handler };
        recargasRef.current.add(entrada);
        return () => recargasRef.current.delete(entrada);
    }, []);

    const manejarEvento = useCallback((envelope) => {
        const encolado = encolar(envelope);
        if (!encolado) return;

        if (debeReproducirSonidoPdv(envelope, prefs.prefsUsuario, prefs.silencioTerminal)) {
            reproducirTonoPdv(prefs.prefsUsuario.tono_id, tonosAlertas);
        }

        encolarTts(envelope);

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

        recargasRef.current.forEach(({ matcher, handler }) => {
            if (matcher(envelope)) {
                handler(envelope);
            }
        });
    }, [encolar, encolarTts, prefs.prefsUsuario, prefs.silencioTerminal, tonosAlertas]);

    const { estadoConexion } = usePdvRealtime({
        sucursalId,
        userId,
        habilitado: habilitado && Boolean(sucursalId),
        onEvent: manejarEvento,
    });

    useEffect(() => {
        reiniciar();
        reiniciarTts();
    }, [sucursalId, userId, reiniciar, reiniciarTts]);

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
        cola,
        registrarRecarga,
        descartarAlerta: descartar,
        estadoTts,
        silenciado: prefs.silencioTerminal,
        alternarSilencioTts: prefs.alternarSilencioTerminal,
        ttsDisponible,
        prefs,
        estadoPush: prefs.estadoPush,
    }), [
        estadoConexion,
        cola,
        registrarRecarga,
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
                    <PdvPreferenciasAlertas
                        prefs={prefs}
                        tonosAlertas={tonosAlertas}
                        estadoConexion={estadoConexion}
                        estadoTts={estadoTts}
                        onActivarPush={activarPush}
                        activandoPush={activandoPush}
                    />
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
