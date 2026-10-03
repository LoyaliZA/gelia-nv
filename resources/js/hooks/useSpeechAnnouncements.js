import { useCallback, useEffect, useRef, useState } from 'react';
import { crearAdaptadorVozNavegador, emitirEnGestoPdv } from '@/utils/pdvSpeechAdapter';
import { crearColaAnunciosTts } from '@/utils/pdvSpeechQueue';
import {
    guardarSilencioTtsPdv,
    leerSilencioTtsPdv,
    PDV_TTS_ESTADO,
    resolverEstadoTtsPdv,
} from '@/utils/pdvSpeechUtils';

/**
 * Cola TTS serializada para eventos PDV aprobados.
 * El audio no confirma estados; solo anuncia. Fallback visual vía toasts globales en AppLayout.
 */
export default function useSpeechAnnouncements({
    habilitado = true,
    silenciado: silenciadoControlado = null,
    adaptadorVoz: adaptadorInyectado = null,
    resolverTexto = null,
    seleccionarVoz = null,
    permitirVozPorDefecto = false,
    mensajeAlDesbloquear = '',
    onAudioDesbloqueado = null,
} = {}) {
    const [silenciadoInterno, setSilenciadoInterno] = useState(() => (
        silenciadoControlado === null ? leerSilencioTtsPdv() : Boolean(silenciadoControlado)
    ));
    const silenciado = silenciadoControlado === null ? silenciadoInterno : Boolean(silenciadoControlado);
    const [estadoTts, setEstadoTts] = useState(PDV_TTS_ESTADO.no_soportado);
    const [audioDesbloqueado, setAudioDesbloqueado] = useState(false);
    const [hablando, setHablando] = useState(false);

    const adaptadorRef = useRef(null);
    const seleccionarVozRef = useRef(seleccionarVoz);
    seleccionarVozRef.current = seleccionarVoz;
    const colaRef = useRef(null);
    const silenciadoRef = useRef(silenciado);
    const audioDesbloqueadoRef = useRef(audioDesbloqueado);
    const resolverTextoRef = useRef(resolverTexto);
    const mensajeAlDesbloquearRef = useRef(mensajeAlDesbloquear);
    const onAudioDesbloqueadoRef = useRef(onAudioDesbloqueado);
    resolverTextoRef.current = resolverTexto;
    mensajeAlDesbloquearRef.current = mensajeAlDesbloquear;
    onAudioDesbloqueadoRef.current = onAudioDesbloqueado;

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

        const adaptador = adaptadorInyectado ?? crearAdaptadorVozNavegador({
            ...(seleccionarVoz ? { seleccionarVoz } : {}),
            permitirVozPorDefecto,
        });
        adaptadorRef.current = adaptador;
        adaptador.iniciarEscuchaVoces?.();
        const dejarDeObservar = adaptador.observarVoz?.((disponible) => {
            if (disponible || permitirVozPorDefecto) return;
            if (!adaptador.vocesConsultadas?.()) return;
            setEstadoTts(PDV_TTS_ESTADO.sin_voz);
        });

        const cola = crearColaAnunciosTts({
            adaptadorVoz: adaptador,
            estaSilenciado: () => silenciadoRef.current,
            audioDesbloqueado: () => audioDesbloqueadoRef.current,
            onEstado: (estado) => setEstadoTts(estado),
            onReproduccion: (activo) => setHablando(Boolean(activo)),
            resolverTexto: (envelope) => resolverTextoRef.current?.(envelope) ?? null,
        });
        colaRef.current = cola;

        setEstadoTts(resolverEstadoTtsPdv({
            soportado: adaptador.soportado(),
            silenciado: silenciadoRef.current,
            audioDesbloqueado: audioDesbloqueadoRef.current,
        }));

        return () => {
            dejarDeObservar?.();
            cola.destruir();
            adaptador.destruir?.();
            colaRef.current = null;
            adaptadorRef.current = null;
        };
    }, [habilitado, adaptadorInyectado, seleccionarVoz, permitirVozPorDefecto]);

    useEffect(() => {
        if (!habilitado || audioDesbloqueado) return undefined;

        const desbloquear = () => {
            if (audioDesbloqueadoRef.current) return;
            audioDesbloqueadoRef.current = true;
            setAudioDesbloqueado(true);
            colaRef.current?.marcarAudioDesbloqueado();
            onAudioDesbloqueadoRef.current?.();
            pronunciarActivacion();
        };

        ['click', 'touchstart', 'keydown'].forEach((evento) => {
            window.addEventListener(evento, desbloquear, { once: true, passive: true, capture: true });
        });

        return () => {
            ['click', 'touchstart', 'keydown'].forEach((evento) => {
                window.removeEventListener(evento, desbloquear, { capture: true });
            });
        };
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
            setEstadoTts(resolverEstadoTtsPdv({
                soportado: Boolean(adaptadorRef.current?.soportado?.()),
                silenciado: siguiente,
                audioDesbloqueado: audioDesbloqueadoRef.current,
            }));
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

    const pronunciarActivacion = () => {
        const mensaje = String(mensajeAlDesbloquearRef.current || '').trim();
        if (!mensaje) return;
        const fn = adaptadorRef.current?.pronunciarEnGesto;
        if (typeof fn === 'function') {
            fn(mensaje);
            return;
        }
        emitirEnGestoPdv(mensaje, seleccionarVozRef.current);
    };

    const desbloquearAudio = useCallback(() => {
        if (audioDesbloqueadoRef.current) return;
        audioDesbloqueadoRef.current = true;
        setAudioDesbloqueado(true);
        colaRef.current?.marcarAudioDesbloqueado();
        onAudioDesbloqueadoRef.current?.();
        pronunciarActivacion();
    }, []);

    return {
        encolar,
        silenciado,
        alternarSilencio,
        estadoTts,
        audioDesbloqueado,
        desbloquearAudio,
        reiniciar,
        ttsDisponible: estadoTts !== PDV_TTS_ESTADO.no_soportado,
        hablando,
    };
}
