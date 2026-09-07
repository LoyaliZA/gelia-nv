import { PDV_TTS_VOZ_PREFERIDA, seleccionarVozPdv } from './pdvSpeechUtils';

export function crearAdaptadorVozNavegador() {
    let vozSeleccionada = null;
    let desuscribirVoces = null;

    const actualizarVoz = () => {
        if (typeof window === 'undefined' || !window.speechSynthesis) return;
        const voces = window.speechSynthesis.getVoices();
        if (voces.length === 0) return;
        vozSeleccionada = seleccionarVozPdv(voces, PDV_TTS_VOZ_PREFERIDA);
    };

    const preparar = () => {
        if (!soportado()) return;
        actualizarVoz();
        if (window.speechSynthesis.paused) {
            window.speechSynthesis.resume();
        }
    };

    const soportado = () => typeof window !== 'undefined' && 'speechSynthesis' in window;

    const iniciarEscuchaVoces = () => {
        if (!soportado() || desuscribirVoces) return;
        const handler = () => actualizarVoz();
        window.speechSynthesis.addEventListener('voiceschanged', handler);
        desuscribirVoces = () => {
            window.speechSynthesis.removeEventListener('voiceschanged', handler);
            desuscribirVoces = null;
        };
        actualizarVoz();
    };

    const destruir = () => {
        desuscribirVoces?.();
        if (soportado()) {
            window.speechSynthesis.cancel();
        }
    };

    return {
        soportado,
        iniciarEscuchaVoces,
        destruir,
        cancelar() {
            if (!soportado()) return;
            window.speechSynthesis.cancel();
        },
        hablar(texto, { onEnd, onError, timeoutMs = 30_000 } = {}) {
            if (!soportado() || !texto) {
                onError?.();
                return;
            }

            preparar();

            const utterance = new SpeechSynthesisUtterance(texto);
            utterance.lang = PDV_TTS_VOZ_PREFERIDA;
            utterance.rate = 1;
            utterance.pitch = 1;
            if (vozSeleccionada) {
                utterance.voice = vozSeleccionada;
            }

            let finalizado = false;
            const finalizar = (tipo) => {
                if (finalizado) return;
                finalizado = true;
                if (watchdog) clearTimeout(watchdog);
                if (tipo === 'end') onEnd?.();
                else onError?.();
            };

            let watchdog = setTimeout(() => finalizar('error'), timeoutMs);

            utterance.onend = () => finalizar('end');
            utterance.onerror = () => finalizar('error');

            window.speechSynthesis.speak(utterance);
        },
    };
}

export function crearAdaptadorVozSimulado() {
    const historial = [];
    let reproduciendo = false;
    let cancelado = false;

    return {
        historial,
        soportado: () => true,
        iniciarEscuchaVoces: () => {},
        destruir: () => {
            cancelado = true;
            reproduciendo = false;
        },
        cancelar() {
            reproduciendo = false;
        },
        hablar(texto, { onEnd, onError } = {}) {
            if (cancelado) {
                onError?.();
                return;
            }
            reproduciendo = true;
            historial.push(texto);
            if (texto === '__ERROR__') {
                reproduciendo = false;
                onError?.();
                return;
            }
            reproduciendo = false;
            onEnd?.();
        },
        estaReproduciendo: () => reproduciendo,
    };
}
