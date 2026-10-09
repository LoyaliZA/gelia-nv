import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { Wifi, WifiOff } from 'lucide-react';
import PdvIndicadorEstadoVivo from '@/Components/PuntoVenta/PdvIndicadorEstadoVivo';
import { alConsultarDeploy, DEPLOY_REINTENTO_AUDIO_MS, DEPLOY_REINTENTO_AUDIO_TOPE_MS } from '@/utils/deployWatch';
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
    llamadoDesdeEnvelope,
    esEventoLlamadoSala,
    esEventoRefetchSala,
    fusionarEstadoSala,
    fusionarPublicidadSala,
    llamadoActualSala,
    normalizarEstadoSala,
} from '@/utils/pantallaSalaUtils';
import {
    mensajeTtsPersonalPdv,
    PDV_TTS_ESTADO,
    PDV_TTS_MENSAJE_ACTIVACION,
    seleccionarVozSalaPdv,
} from '@/utils/pdvSpeechUtils';
import ModalLlamadoTurnoPdv from '@/Components/PuntoVenta/ModalLlamadoTurnoPdv';

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

export default function PdvSalaProvider({
    children,
    sucursalId,
    estadoInicial = null,
    urlEstado = null,
}) {
    const { tonos_alertas: tonosAlertas = [] } = usePage().props;
    const tonosAlertasRef = useRef(tonosAlertas);
    tonosAlertasRef.current = tonosAlertas;
    const [estadoSala, setEstadoSala] = useState(() => normalizarEstadoSala(estadoInicial));
    const [cargandoEstado, setCargandoEstado] = useState(false);
    const idsAnunciadosRef = useRef(new Set());
    const tonosReproducidosRef = useRef(new Set());

    const [modalLlamado, setModalLlamado] = useState(null);
    const vozEnCursoRef = useRef(false);

    const {
        encolar: encolarTts,
        estadoTts,
        audioDesbloqueado,
        ttsDisponible,
        hablando,
        desbloquearAudio,
    } = useSpeechAnnouncements({
        habilitado: true,
        silenciado: false,
        seleccionarVoz: seleccionarVozSalaPdv,
        permitirVozPorDefecto: true,
        mensajeAlDesbloquear: PDV_TTS_MENSAJE_ACTIVACION,
        onAudioDesbloqueado: () => {
            reproducirTonoPdv('default', tonosAlertasRef.current);
        },
        resolverTexto: (envelope) => mensajeTtsPersonalPdv({
            ...envelope,
            audiencia: 'publico',
        }),
    });

    const anunciarEvento = useCallback((envelope) => {
        if (!esEventoLlamadoSala(envelope)) return;

        const eventId = String(envelope.event_id || '');
        if (!eventId || idsAnunciadosRef.current.has(eventId)) return;

        idsAnunciadosRef.current.add(eventId);

        if (!tonosReproducidosRef.current.has(eventId)) {
            tonosReproducidosRef.current.add(eventId);
            reproducirTonoPdv('default', tonosAlertas);
        }

        setModalLlamado(llamadoDesdeEnvelope(envelope) || envelope.datos || null);
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
                    publicidad: fusionarPublicidadSala(actual.publicidad, siguiente.publicidad),
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

    useEffect(() => {
        if (!urlEstado) return undefined;
        const timer = window.setInterval(() => {
            refrescarEstado();
        }, 60_000);
        return () => window.clearInterval(timer);
    }, [urlEstado, refrescarEstado]);

    useEffect(() => {
        if (hablando) {
            vozEnCursoRef.current = true;
            return;
        }
        if (!vozEnCursoRef.current) return;
        vozEnCursoRef.current = false;
        setModalLlamado(null);
    }, [hablando]);

    useEffect(() => {
        if (!modalLlamado) return undefined;

        const tope = window.setTimeout(() => setModalLlamado(null), 25000);
        const sinVoz = estadoTts === PDV_TTS_ESTADO.sin_voz
            || estadoTts === PDV_TTS_ESTADO.no_soportado
            || estadoTts === PDV_TTS_ESTADO.bloqueado
            || estadoTts === PDV_TTS_ESTADO.silenciado;
        const espera = (!hablando && !vozEnCursoRef.current) || sinVoz
            ? window.setTimeout(() => {
                if (!vozEnCursoRef.current) setModalLlamado(null);
            }, 8000)
            : null;

        return () => {
            window.clearTimeout(tope);
            if (espera) window.clearTimeout(espera);
        };
    }, [modalLlamado, hablando, estadoTts]);

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
        hablando,
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
        hablando,
        refrescarEstado,
    ]);

    useEffect(() => {
        const intentar = () => {
            window.speechSynthesis?.resume?.();
            desbloquearAudio();
        };
        intentar();
        const inicio = Date.now();
        const id = window.setInterval(() => {
            if (Date.now() - inicio > DEPLOY_REINTENTO_AUDIO_TOPE_MS) {
                window.clearInterval(id);
                return;
            }
            intentar();
        }, DEPLOY_REINTENTO_AUDIO_MS);
        return () => window.clearInterval(id);
    }, [desbloquearAudio]);

    useEffect(() => alConsultarDeploy(() => {
        window.speechSynthesis?.resume?.();
        desbloquearAudio();
    }), [desbloquearAudio]);

    return (
        <div data-pdv-sala-provider className="contents">
            <ModalLlamadoTurnoPdv abierto={Boolean(modalLlamado)} turno={modalLlamado} variante="sala" />
            {typeof children === 'function' ? children(valor) : children}
        </div>
    );
}
