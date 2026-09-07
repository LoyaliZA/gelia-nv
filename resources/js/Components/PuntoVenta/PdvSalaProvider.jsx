import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { Volume2, Wifi, WifiOff } from 'lucide-react';
import usePdvRealtimePublico from '@/hooks/usePdvRealtimePublico';
import useSpeechAnnouncements from '@/hooks/useSpeechAnnouncements';
import {
    conexionDegradadaPdv,
    mensajeConexionDegradadaPdv,
    PDV_ESTADO_CONEXION,
} from '@/utils/pdvAlertQueueUtils';
import { reproducirTonoPdv } from '@/utils/pdvAlertasPrefs';
import {
    aplicarEventoSala,
    esEventoLlamadoSala,
    fusionarEstadoSala,
    llamadoActualSala,
} from '@/utils/pantallaSalaUtils';
import { PDV_TTS_ESTADO } from '@/utils/pdvSpeechUtils';

function PdvSalaConexionBanner({ estadoConexion }) {
    const conectado = estadoConexion === PDV_ESTADO_CONEXION.conectado;

    return (
        <div
            className="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-wider"
            style={{
                borderColor: conectado
                    ? 'color-mix(in srgb, var(--color-exito) 35%, transparent)'
                    : 'color-mix(in srgb, var(--color-aviso) 35%, transparent)',
                color: 'var(--color-texto)',
            }}
            role="status"
            aria-live="polite"
            data-pdv-sala-conexion={estadoConexion}
        >
            {conectado ? (
                <Wifi className="w-3.5 h-3.5" aria-hidden />
            ) : (
                <WifiOff className="w-3.5 h-3.5" aria-hidden />
            )}
            {conectado ? 'En línea' : mensajeConexionDegradadaPdv(estadoConexion)}
        </div>
    );
}

function PdvSalaAudioDesbloqueo({ visible }) {
    if (!visible) return null;

    return (
        <button
            type="button"
            className="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-6"
            data-pdv-sala-audio-desbloqueo
        >
            <span
                className="flex max-w-md flex-col items-center gap-4 rounded-3xl border px-8 py-10 text-center shadow-2xl theme-surface"
                style={{ borderColor: 'color-mix(in srgb, var(--color-primario) 30%, transparent)' }}
            >
                <Volume2 className="w-12 h-12" aria-hidden />
                <span className="text-lg font-bold">Toca para activar anuncios</span>
                <span className="text-sm opacity-80">
                    El navegador requiere una interacción inicial para reproducir voz y timbre.
                </span>
            </span>
        </button>
    );
}

export default function PdvSalaProvider({
    children,
    sucursalId,
    estadoInicial = null,
    urlEstado = null,
}) {
    const { tonos_alertas: tonosAlertas = [] } = usePage().props;
    const [llamados, setLlamados] = useState(() => estadoInicial?.llamados ?? []);
    const [cargandoEstado, setCargandoEstado] = useState(false);
    const idsAnunciadosRef = useRef(new Set());
    const tonosReproducidosRef = useRef(new Set());

    const {
        encolar: encolarTts,
        estadoTts,
        audioDesbloqueado,
        ttsDisponible,
    } = useSpeechAnnouncements({ habilitado: true, silenciado: false });

    const anunciarEvento = useCallback((envelope) => {
        if (!esEventoLlamadoSala(envelope)) return;

        const eventId = String(envelope.event_id || '');
        if (!eventId || idsAnunciadosRef.current.has(eventId)) return;

        idsAnunciadosRef.current.add(eventId);

        if (!tonosReproducidosRef.current.has(eventId)) {
            tonosReproducidosRef.current.add(eventId);
            reproducirTonoPdv('default', tonosAlertas);
        }

        encolarTts(envelope);
    }, [encolarTts, tonosAlertas]);

    const manejarEvento = useCallback((envelope) => {
        setLlamados((actual) => aplicarEventoSala(actual, envelope));
        anunciarEvento(envelope);
    }, [anunciarEvento]);

    const { estadoConexion } = usePdvRealtimePublico({
        sucursalId,
        habilitado: Boolean(sucursalId),
        onEvent: manejarEvento,
    });

    const refrescarEstado = useCallback(async () => {
        if (!urlEstado) return;

        setCargandoEstado(true);
        try {
            const { data } = await axios.get(urlEstado);
            setLlamados((actual) => fusionarEstadoSala(data?.llamados ?? [], actual));
        } finally {
            setCargandoEstado(false);
        }
    }, [urlEstado]);

    useEffect(() => {
        if (estadoInicial?.llamados) {
            setLlamados(fusionarEstadoSala(estadoInicial.llamados, []));
        }
    }, [estadoInicial]);

    const conexionPrevRef = useRef(estadoConexion);

    useEffect(() => {
        const previo = conexionPrevRef.current;
        conexionPrevRef.current = estadoConexion;

        if (
            previo !== PDV_ESTADO_CONEXION.conectado
            && estadoConexion === PDV_ESTADO_CONEXION.conectado
            && conexionDegradadaPdv(previo)
        ) {
            refrescarEstado();
        }
    }, [estadoConexion, refrescarEstado]);

    const valor = useMemo(() => ({
        llamados,
        llamadoActual: llamadoActualSala(llamados),
        estadoConexion,
        cargandoEstado,
        estadoTts,
        audioDesbloqueado,
        ttsDisponible,
        refrescarEstado,
    }), [
        llamados,
        estadoConexion,
        cargandoEstado,
        estadoTts,
        audioDesbloqueado,
        ttsDisponible,
        refrescarEstado,
    ]);

    const mostrarDesbloqueo = ttsDisponible
        && !audioDesbloqueado
        && estadoTts === PDV_TTS_ESTADO.bloqueado;

    return (
        <div data-pdv-sala-provider>
            <PdvSalaConexionBanner estadoConexion={estadoConexion} />
            <PdvSalaAudioDesbloqueo visible={mostrarDesbloqueo} />
            {typeof children === 'function' ? children(valor) : children}
        </div>
    );
}
