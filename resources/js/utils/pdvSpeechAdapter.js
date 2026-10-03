import { PDV_TTS_RATE, PDV_TTS_VOZ_PREFERIDA, seleccionarVozPdv } from './pdvSpeechUtils';

const ESPERA_VOCES_MS = 1500;
const PULSO_CHROME_MS = 8000;

export function emitirEnGestoPdv(texto, seleccionarVoz = null) {
    if (typeof window === 'undefined' || !window.speechSynthesis || !texto) return false;
    const voces = window.speechSynthesis.getVoices();
    const voz = typeof seleccionarVoz === 'function' ? seleccionarVoz(voces) : null;
    const utterance = new SpeechSynthesisUtterance(texto);
    utterance.lang = PDV_TTS_VOZ_PREFERIDA;
    utterance.rate = PDV_TTS_RATE;
    utterance.pitch = 1;
    if (voz) utterance.voice = voz;
    if (window.speechSynthesis.paused) window.speechSynthesis.resume();
    window.speechSynthesis.speak(utterance);
    return true;
}

export function crearAdaptadorVozNavegador({
    seleccionarVoz = seleccionarVozPdv,
    permitirVozPorDefecto = false,
} = {}) {
    let vozSeleccionada = null;
    let vocesConsultadas = false;
    let desuscribirVoces = null;
    let pulsoChrome = null;
    let utteranceActiva = null;
    const oyentesVoz = new Set();

    const notificarVoz = () => {
        oyentesVoz.forEach((oyente) => oyente(Boolean(vozSeleccionada)));
    };

    const actualizarVoz = () => {
        if (typeof window === 'undefined' || !window.speechSynthesis) return;
        const voces = window.speechSynthesis.getVoices();
        if (voces.length === 0) return;
        vocesConsultadas = true;
        vozSeleccionada = seleccionarVoz(voces);
        if (!vozSeleccionada && permitirVozPorDefecto) {
            vozSeleccionada = voces.find((voz) => String(voz?.lang || '').toLowerCase().startsWith('es'))
                ?? voces[0]
                ?? null;
        }
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

    const emitirFrase = (texto, voz) => {
        const utterance = new SpeechSynthesisUtterance(texto);
        utterance.lang = PDV_TTS_VOZ_PREFERIDA;
        utterance.rate = PDV_TTS_RATE;
        utterance.pitch = 1;
        if (voz) utterance.voice = voz;
        if (window.speechSynthesis.paused) {
            window.speechSynthesis.resume();
        }
        window.speechSynthesis.speak(utterance);
        return utterance;
    };

    return {
        soportado,
        vozDisponible: () => permitirVozPorDefecto || Boolean(vozSeleccionada),
        vocesConsultadas: () => vocesConsultadas,
        iniciarEscuchaVoces,
        observarVoz(oyente) {
            oyentesVoz.add(oyente);
            return () => oyentesVoz.delete(oyente);
        },
        destruir,
        cancelar() {
            detenerPulso();
            utteranceActiva = null;
            if (!soportado()) return;
            window.speechSynthesis.cancel();
        },
        hablar(texto, { onEnd, onError, timeoutMs = 30_000 } = {}) {
            if (!soportado() || !texto) {
                onError?.();
                return;
            }

            hablarCuandoHayaVoz((voz) => {
                if (!voz && !permitirVozPorDefecto) {
                    onError?.();
                    return;
                }

                let finalizado = false;
                let vioHabla = false;
                let reintentos = 0;
                let vigilarFin = null;
                let utteranceActivaLocal = null;
                const finalizar = (tipo) => {
                    if (finalizado) return;
                    finalizado = true;
                    detenerPulso();
                    if (vigilarFin) clearInterval(vigilarFin);
                    if (watchdog) clearTimeout(watchdog);
                    if (utteranceActiva === utteranceActivaLocal) utteranceActiva = null;
                    if (tipo === 'end') onEnd?.();
                    else onError?.();
                };

                const watchdog = setTimeout(() => finalizar('error'), timeoutMs);

                const lanzar = () => {
                    const utterance = emitirFrase(texto, voz);
                    utteranceActivaLocal = utterance;
                    utteranceActiva = utterance;
                    utterance.onend = () => finalizar('end');
                    utterance.onerror = (event) => {
                        if (event?.error === 'interrupted' && !vioHabla && reintentos < 1) {
                            reintentos += 1;
                            window.setTimeout(() => {
                                if (!finalizado) lanzar();
                            }, 50);
                            return;
                        }
                        finalizar('error');
                    };
                };

                detenerPulso();
                pulsoChrome = setInterval(() => {
                    if (finalizado || !window.speechSynthesis.speaking) return;
                    window.speechSynthesis.resume();
                }, PULSO_CHROME_MS);

                vigilarFin = setInterval(() => {
                    if (finalizado) return;
                    const synth = window.speechSynthesis;
                    if (synth.speaking || synth.pending) {
                        vioHabla = true;
                        return;
                    }
                    if (vioHabla) finalizar('end');
                }, 250);

                if (window.speechSynthesis.speaking || window.speechSynthesis.pending) {
                    window.speechSynthesis.cancel();
                    window.setTimeout(lanzar, 50);
                } else {
                    lanzar();
                }
            });
        },
        pronuncirEnGesto(texto) {
            if (!soportado() || !texto) return false;
            preparar();
            emitirFrase(texto, vozSeleccionada);
            return true;
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
