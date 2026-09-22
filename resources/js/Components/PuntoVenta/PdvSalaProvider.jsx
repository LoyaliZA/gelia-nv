import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { Volume2, Wifi, WifiOff } from 'lucide-react';
import PdvIndicadorEstadoVivo from '@/Components/PuntoVenta/PdvIndicadorEstadoVivo';
import { THEME_MODAL_OVERLAY, THEME_MODAL_SHELL, GELIA_ICON_BOX } from '@/utils/geliaTheme';
import usePdvRealtimePublico from '@/hooks/usePdvRealtimePublico';
import useSpeechAnnouncements from '@/hooks/useSpeechAnnouncements';
import {
    conexionDegradadaPdv,
    etiquetaConexionTiempoRealPdv,
    mensajeConexionDegradadaPdv,
    PDV_ESTADO_CONEXION,
} from '@/utils/pdvAlertQueueUtils';
import { reproducirTonoPdv } from '@/utils/pdvAlertasPrefs';
import {
    aplicarEventoSala,
    esEventoLlamadoSala,
    esEventoRefetchSala,
    fusionarEstadoSala,
    llamadoActualSala,
    normalizarEstadoSala,
} from '@/utils/pantallaSalaUtils';
import { PDV_TTS_ESTADO } from '@/utils/pdvSpeechUtils';

export function PdvSalaIndicadorConexion({ estadoConexion }) {
    const conectado = estadoConexion === PDV_ESTADO_CONEXION.conectado;
    const degradada = conexionDegradadaPdv(estadoConexion);
    const reconectando = estadoConexion === PDV_ESTADO_CONEXION.conectando;

    return (
        <PdvIndicadorEstadoVivo
            icono={conectado ? Wifi : WifiOff}
            etiqueta={conectado ? 'En línea' : mensajeConexionDegradadaPdv(estadoConexion)}
            titulo={conectado ? 'En línea' : etiquetaConexionTiempoRealPdv(estadoConexion)}
            tono={conectado ? 'exito' : (reconectando ? 'aviso' : 'error')}
            pulsando={degradada}
            dataAtributo={`sala-conexion-${estadoConexion}`}
        />
    );
}

function PdvSalaAudioDesbloqueo({ visible }) {
    if (!visible) return null;

    return (
        <button
            type="button"
            className={`${THEME_MODAL_OVERLAY} z-50 p-6`}
            data-pdv-sala-audio-desbloqueo
        >
            <span
                className={`${THEME_MODAL_SHELL} flex max-w-md flex-col items-center gap-4 px-8 py-10 text-center modal-pop`}
            >
                <span className={GELIA_ICON_BOX} aria-hidden>
                    <Volume2 className="w-8 h-8 theme-text-primario" />
                </span>
                <span className="text-lg font-bold theme-text-main">Toca para activar anuncios</span>
                <span className="text-sm theme-text-muted">
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
    const [estadoSala, setEstadoSala] = useState(() => normalizarEstadoSala(estadoInicial));
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

    const refrescarEstado = useCallback(async () => {
        if (!urlEstado) return;

        setCargandoEstado(true);
        try {
            const { data } = await axios.get(urlEstado);
            setEstadoSala((actual) => {
                const siguiente = normalizarEstadoSala(data);
                const llamados = fusionarEstadoSala(siguiente.llamados, actual.llamados);
                return {
                    ...siguiente,
                    llamados,
                    turno_actual: siguiente.turno_actual ?? llamadoActualSala(llamados),
                };
            });
        } catch {
            // ponytail: conservar último estado conocido si el refetch de sala falla
        } finally {
            setCargandoEstado(false);
        }
    }, [urlEstado]);

    const manejarEvento = useCallback((envelope) => {
        if (String(envelope?.tipo || '') === 'publicidad.actualizada') {
            refrescarEstado();
            return;
        }
        setEstadoSala((actual) => {
            const llamados = aplicarEventoSala(actual.llamados, envelope);
            return {
                ...actual,
                llamados,
                turno_actual: llamadoActualSala(llamados),
            };
        });
        anunciarEvento(envelope);
        if (esEventoRefetchSala(envelope)) {
            refrescarEstado();
        }
    }, [anunciarEvento, refrescarEstado]);

    const { estadoConexion } = usePdvRealtimePublico({
        sucursalId,
        habilitado: Boolean(sucursalId),
        onEvent: manejarEvento,
    });

    useEffect(() => {
        setEstadoSala(normalizarEstadoSala(estadoInicial));
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

    const llamados = estadoSala.llamados;
    const llamadoActual = llamadoActualSala(llamados);

    const valor = useMemo(() => ({
        ...estadoSala,
        llamados,
        llamadoActual,
        turnoActual: llamadoActual,
        proximos: estadoSala.proximos,
        anteriores: estadoSala.anteriores,
        publicidad: estadoSala.publicidad,
        sucursal: estadoSala.sucursal,
        tema: estadoSala.tema,
        estadoConexion,
        cargandoEstado,
        estadoTts,
        audioDesbloqueado,
        ttsDisponible,
        refrescarEstado,
    }), [
        estadoSala,
        llamados,
        llamadoActual,
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
        <div data-pdv-sala-provider className="contents">
            <PdvSalaAudioDesbloqueo visible={mostrarDesbloqueo} />
            {typeof children === 'function' ? children(valor) : children}
        </div>
    );
}
