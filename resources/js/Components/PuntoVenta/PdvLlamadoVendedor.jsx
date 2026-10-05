import React, { useCallback, useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { usePage } from '@inertiajs/react';
import usePdvRealtime from '@/hooks/usePdvRealtime';
import useSpeechAnnouncements from '@/hooks/useSpeechAnnouncements';
import usePdvAlertasPrefs from '@/hooks/usePdvAlertasPrefs';
import ModalLlamadoTurnoPdv from '@/Components/PuntoVenta/ModalLlamadoTurnoPdv';
import { mensajeTtsPersonalPdv, PDV_TTS_ESTADO } from '@/utils/pdvSpeechUtils';
import { debeReproducirSonidoPersonalPdv } from '@/utils/pdvAlertasAudiencia';
import {
    debeReproducirTonoEventoPdv,
    mensajeFallbackWebPushPdv,
    PDV_PUSH_ESTADO,
    reclamarAudioEventoPdv,
    reproducirTonoPdv,
} from '@/utils/pdvAlertasPrefs';

const ESTADOS_SIN_MODAL = new Set(['no_activado', 'no_llego', 'jornada_cerrada']);
const TIPOS_MODAL = new Set(['turno.asignado', 'turno.reatencion', 'turno.transferido']);

function urlTablero() {
    try {
        if (typeof route === 'function' && route().has?.('punto_venta.turnos.ventas.datos')) {
            return route('punto_venta.turnos.ventas.datos');
        }
    } catch {
        /* ziggy */
    }
    return '/punto-venta/turnos/ventas/datos';
}

export default function PdvLlamadoVendedor({ userId }) {
    const { auth, tonos_alertas: tonosAlertas = [] } = usePage().props;
    const prefs = usePdvAlertasPrefs({
        temaVisual: auth?.tema_visual,
        webpush: auth?.webpush,
    });
    const [turnoModal, setTurnoModal] = useState(null);
    const vozEnCursoRef = useRef(false);
    const avisoPushRef = useRef(false);

    const resolverTexto = useCallback((envelope) => {
        if (envelope?.audiencia !== 'usuario') return null;
        if (Number(envelope?.datos?.atencion?.user_id) !== Number(userId)) return null;
        return mensajeTtsPersonalPdv(envelope);
    }, [userId]);

    const { encolar, hablando, estadoTts } = useSpeechAnnouncements({
        habilitado: prefs.canalesEfectivos.voz,
        silenciado: prefs.silencioTerminal,
        resolverTexto,
    });

    const mostrarSiActivo = useCallback(async (envelope) => {
        if (!TIPOS_MODAL.has(String(envelope?.tipo || ''))) return;
        if (Number(envelope?.datos?.atencion?.user_id) !== Number(userId)) return;

        try {
            const { data } = await axios.get(urlTablero(), { headers: { Accept: 'application/json' } });
            const estado = String(data?.estado_vendedor || '');
            if (!estado || ESTADOS_SIN_MODAL.has(estado)) return;
            setTurnoModal(envelope.datos);
        } catch {
            // ponytail: sin estado operativo no se muestra el modal
        }
    }, [userId]);

    const onEvent = useCallback((envelope) => {
        if (envelope?.audiencia !== 'usuario') return;

        const tomaAudio = reclamarAudioEventoPdv(envelope?.event_id, 'usuario');
        if (tomaAudio) {
            if (
                debeReproducirSonidoPersonalPdv(envelope, prefs.prefsUsuario, prefs.silencioTerminal, userId)
                && debeReproducirTonoEventoPdv(envelope, 'personal')
            ) {
                reproducirTonoPdv(prefs.prefsUsuario.tono_id, tonosAlertas);
            }
            encolar(envelope);
        }

        if (!avisoPushRef.current && prefs.estadoPush === PDV_PUSH_ESTADO.pendiente) {
            const mensaje = mensajeFallbackWebPushPdv(prefs.estadoPush);
            if (mensaje) {
                avisoPushRef.current = true;
                window.dispatchEvent(new CustomEvent('gelia-toast', {
                    detail: { mensaje, tipo: 'info' },
                }));
            }
        }

        mostrarSiActivo(envelope);
    }, [
        encolar,
        mostrarSiActivo,
        prefs.estadoPush,
        prefs.prefsUsuario,
        prefs.silencioTerminal,
        tonosAlertas,
        userId,
    ]);

    usePdvRealtime({
        userId,
        habilitado: Boolean(userId),
        onEvent,
    });

    useEffect(() => {
        if (hablando) {
            vozEnCursoRef.current = true;
            return;
        }
        if (!vozEnCursoRef.current) return;
        vozEnCursoRef.current = false;
        setTurnoModal(null);
    }, [hablando]);

    useEffect(() => {
        if (!turnoModal) return undefined;

        const tope = window.setTimeout(() => setTurnoModal(null), 25000);
        const sinVoz = estadoTts === PDV_TTS_ESTADO.sin_voz
            || estadoTts === PDV_TTS_ESTADO.no_soportado
            || estadoTts === PDV_TTS_ESTADO.bloqueado
            || estadoTts === PDV_TTS_ESTADO.silenciado;
        const espera = (!hablando && !vozEnCursoRef.current) || sinVoz
            ? window.setTimeout(() => {
                if (!vozEnCursoRef.current) setTurnoModal(null);
            }, 8000)
            : null;

        return () => {
            window.clearTimeout(tope);
            if (espera) window.clearTimeout(espera);
        };
    }, [turnoModal, hablando, estadoTts]);

    return (
        <ModalLlamadoTurnoPdv
            abierto={Boolean(turnoModal)}
            turno={turnoModal}
            variante="vendedor"
            onCerrar={() => setTurnoModal(null)}
        />
    );
}
