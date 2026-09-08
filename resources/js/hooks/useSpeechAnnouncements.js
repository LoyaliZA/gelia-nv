import { useCallback, useEffect, useRef, useState } from 'react';
import { crearAdaptadorVozNavegador } from '@/utils/pdvSpeechAdapter';
import { crearColaAnunciosTts } from '@/utils/pdvSpeechQueue';
import {
    guardarSilencioTtsPdv,
    leerSilencioTtsPdv,
    PDV_TTS_ESTADO,
} from '@/utils/pdvSpeechUtils';

/**
 * Cola TTS serializada para eventos PDV aprobados.
 * El audio no confirma estados; solo anuncia. Fallback visual permanece en PdvAlertProvider.
 */
export default function useSpeechAnnouncements({
    habilitado = true,
    silenciado: silenciadoControlado = null,
    adaptadorVoz: adaptadorInyectado = null,
    resolverTexto = null,
} = {}) {
    const [silenciadoInterno, setSilenciadoInterno] = useState(() => (
        silenciadoControlado === null ? leerSilencioTtsPdv() : Boolean(silenciadoControlado)
    ));
    const silenciado = silenciadoControlado === null ? silenciadoInterno : Boolean(silenciadoControlado);
    const [estadoTts, setEstadoTts] = useState(PDV_TTS_ESTADO.no_soportado);
    const [audioDesbloqueado, setAudioDesbloqueado] = useState(false);

    const adaptadorRef = useRef(null);
    const colaRef = useRef(null);
    const silenciadoRef = useRef(silenciado);
    const audioDesbloqueadoRef = useRef(audioDesbloqueado);

    useEffect(() => {
        silenciadoRef.current = silenciado;
        colaRef.current?.alternarSilencio(silenciado);
    }, [silenciado]);

    useEffect(() => {
        audioDesbloqueadoRef.current = audioDesbloqueado;
    }, [audioDesbloqueado]);

    useEffect(() => {
        if (!habilitado) {
            colaRef.current?.destruir();
            colaRef.current = null;
            adaptadorRef.current?.destruir?.();
            adaptadorRef.current = null;
            setEstadoTts(PDV_TTS_ESTADO.no_soportado);
            return undefined;
        }

        const adaptador = adaptadorInyectado ?? crearAdaptadorVozNavegador();
        adaptadorRef.current = adaptador;
        adaptador.iniciarEscuchaVoces?.();

        const cola = crearColaAnunciosTts({
            adaptadorVoz: adaptador,
            estaSilenciado: () => silenciadoRef.current,
            audioDesbloqueado: () => audioDesbloqueadoRef.current,
            onEstado: (estado) => setEstadoTts(estado),
            resolverTexto,
        });
        colaRef.current = cola;

        if (!adaptador.soportado()) {
            setEstadoTts(PDV_TTS_ESTADO.no_soportado);
        } else if (silenciadoRef.current) {
            setEstadoTts(PDV_TTS_ESTADO.silenciado);
        }

        return () => {
            cola.destruir();
            adaptador.destruir?.();
            colaRef.current = null;
            adaptadorRef.current = null;
        };
    }, [habilitado, adaptadorInyectado, resolverTexto]);

    useEffect(() => {
        if (!habilitado || audioDesbloqueado) return undefined;

        const desbloquear = () => {
            setAudioDesbloqueado(true);
            colaRef.current?.marcarAudioDesbloqueado();
        };

        ['click', 'touchstart', 'keydown'].forEach((evento) => {
            window.addEventListener(evento, desbloquear, { once: true, passive: true, capture: true });
        });

        return undefined;
    }, [habilitado, audioDesbloqueado]);

    const encolar = useCallback((envelope) => {
        if (!habilitado) return false;
        return colaRef.current?.encolar(envelope) ?? false;
    }, [habilitado]);

    const alternarSilencio = useCallback((valor = null) => {
        const aplicar = (actual) => {
            const siguiente = typeof valor === 'boolean' ? valor : !actual;
            if (silenciadoControlado === null) {
                guardarSilencioTtsPdv(siguiente);
            }
            colaRef.current?.alternarSilencio(siguiente);
            if (siguiente) {
                setEstadoTts(PDV_TTS_ESTADO.silenciado);
            } else if (!adaptadorRef.current?.soportado?.()) {
                setEstadoTts(PDV_TTS_ESTADO.no_soportado);
            } else if (!audioDesbloqueadoRef.current) {
                setEstadoTts(PDV_TTS_ESTADO.bloqueado);
            } else {
                setEstadoTts(PDV_TTS_ESTADO.listo);
            }
            return siguiente;
        };

        if (silenciadoControlado === null) {
            setSilenciadoInterno(aplicar);
            return;
        }

        aplicar(silenciadoControlado);
    }, [silenciadoControlado]);

    const reiniciar = useCallback(() => {
        colaRef.current?.reiniciar();
    }, []);

    return {
        encolar,
        silenciado,
        alternarSilencio,
        estadoTts,
        audioDesbloqueado,
        reiniciar,
        ttsDisponible: estadoTts !== PDV_TTS_ESTADO.no_soportado,
    };
}
