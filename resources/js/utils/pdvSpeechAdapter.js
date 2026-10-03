import { PDV_TTS_RATE, PDV_TTS_VOZ_PREFERIDA, seleccionarVozPdv } from './pdvSpeechUtils';

const ESPERA_VOCES_MS = 1500;
const PULSO_CHROME_MS = 8000;

export function crearAdaptadorVozNavegador() {
    let vozSeleccionada = null;
    let vocesConsultadas = false;
    let desuscribirVoces = null;
    let pulsoChrome = null;
    const oyentesVoz = new Set();

    const notificarVoz = () => {
        oyentesVoz.forEach((oyente) => oyente(Boolean(vozSeleccionada)));
    };

    const actualizarVoz = () => {
        if (typeof window === 'undefined' || !window.speechSynthesis) return;
        const voces = window.speechSynthesis.getVoices();
        if (voces.length === 0) return;
        vocesConsultadas = true;
        vozSeleccionada = seleccionarVozPdv(voces, PDV_TTS_VOZ_PREFERIDA);
        notificarVoz();
    };

    const preparar = () => {
        if (!soportado()) return;
        actualizarVoz();
        if (window.speechSynthesis.paused) {
            window.speechSynthesis.resume();
        }
    };

    const soportado = () => typeof window !== 'undefined' && 'speechSynthesis' in window;

    const detenerPulso = () => {
        if (pulsoChrome) {
            clearInterval(pulsoChrome);
            pulsoChrome = null;
        }
    };

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
        detenerPulso();
        oyentesVoz.clear();
        desuscribirVoces?.();
        if (soportado()) {
            window.speechSynthesis.cancel();
        }
    };

    const hablarCuandoHayaVoz = (callback) => {
        preparar();
        if (vozSeleccionada || vocesConsultadas) {
            callback(vozSeleccionada);
            return;
        }

        let cerrado = false;
        const cerrar = (voz) => {
            if (cerrado) return;
            cerrado = true;
            window.clearTimeout(timer);
            window.speechSynthesis.removeEventListener('voiceschanged', handler);
            callback(voz);
        };
        const handler = () => {
            actualizarVoz();
            if (vozSeleccionada || vocesConsultadas) {
                cerrar(vozSeleccionada);
            }
        };
        const timer = window.setTimeout(() => cerrar(vozSeleccionada), ESPERA_VOCES_MS);
        window.speechSynthesis.addEventListener('voiceschanged', handler);
    };

    return {
        soportado,
        vozDisponible: () => Boolean(vozSeleccionada),
        vocesConsultadas: () => vocesConsultadas,
        iniciarEscuchaVoces,
        observarVoz(oyente) {
            oyentesVoz.add(oyente);
            return () => oyentesVoz.delete(oyente);
        },
        destruir,
        cancelar() {
            detenerPulso();
            if (!soportado()) return;
            window.speechSynthesis.cancel();
        },
        hablar(texto, { onEnd, onError, timeoutMs = 30_000 } = {}) {
            if (!soportado() || !texto) {
                onError?.();
                return;
            }

            hablarCuandoHayaVoz((voz) => {
                if (!voz) {
                    onError?.();
                    return;
                }

                const utterance = new SpeechSynthesisUtterance(texto);
                utterance.lang = PDV_TTS_VOZ_PREFERIDA;
                utterance.rate = PDV_TTS_RATE;
                utterance.pitch = 1;
                utterance.voice = voz;

                let finalizado = false;
                const finalizar = (tipo) => {
                    if (finalizado) return;
                    finalizado = true;
                    detenerPulso();
                    if (watchdog) clearTimeout(watchdog);
                    if (tipo === 'end') onEnd?.();
                    else onError?.();
                };

                const watchdog = setTimeout(() => finalizar('error'), timeoutMs);

                utterance.onend = () => finalizar('end');
                utterance.onerror = () => finalizar('error');

                detenerPulso();
                pulsoChrome = setInterval(() => {
                    if (!finalizado && window.speechSynthesis.speaking) {
                        window.speechSynthesis.resume();
                    }
                }, PULSO_CHROME_MS);

                window.speechSynthesis.speak(utterance);
            });
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
        vozDisponible: () => true,
        vocesConsultadas: () => true,
        iniciarEscuchaVoces: () => {},
        observarVoz: () => () => {},
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
