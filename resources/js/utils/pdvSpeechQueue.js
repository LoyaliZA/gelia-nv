import { debeAnunciarTtsPdv, mensajeTtsPdv } from './pdvSpeechUtils';

export function crearColaAnunciosTts({
    adaptadorVoz,
    onEstado = () => {},
    onAnunciado = () => {},
    onFallo = () => {},
    estaSilenciado = () => false,
    audioDesbloqueado = () => true,
    timeoutMs = 30_000,
} = {}) {
    const cola = [];
    const anunciados = new Set();
    let procesando = false;
    let cancelado = false;

    const estadoActual = () => {
        if (!adaptadorVoz?.soportado?.()) return 'no_soportado';
        if (estaSilenciado()) return 'silenciado';
        if (!audioDesbloqueado()) return 'bloqueado';
        return 'listo';
    };

    const notificarEstado = () => {
        onEstado(estadoActual());
    };

    const procesarSiguiente = () => {
        if (cancelado || procesando) return;

        const estado = estadoActual();
        notificarEstado();

        if (estado !== 'listo') {
            return;
        }

        const siguiente = cola.shift();
        if (!siguiente) return;

        procesando = true;
        anunciados.add(siguiente.eventId);
        const { texto, eventId } = siguiente;

        adaptadorVoz.hablar(texto, {
            onEnd: () => {
                procesando = false;
                onAnunciado(eventId);
                procesarSiguiente();
            },
            onError: () => {
                procesando = false;
                onFallo(eventId);
                procesarSiguiente();
            },
            timeoutMs,
        });
    };

    return {
        encolar(envelope) {
            if (cancelado || !debeAnunciarTtsPdv(envelope)) return false;

            const eventId = String(envelope.event_id || '');
            if (!eventId || anunciados.has(eventId)) return false;

            const texto = mensajeTtsPdv(envelope);
            if (!texto) return false;

            const estado = estadoActual();
            if (estado === 'silenciado' || estado === 'no_soportado') {
                anunciados.add(eventId);
                notificarEstado();
                return true;
            }

            if (cola.some((item) => item.eventId === eventId)) return false;

            cola.push({ eventId, texto, envelope });
            notificarEstado();
            procesarSiguiente();
            return true;
        },

        alternarSilencio(silenciado) {
            if (silenciado) {
                adaptadorVoz?.cancelar?.();
                cola.length = 0;
                procesando = false;
            }
            notificarEstado();
            if (!silenciado) {
                procesarSiguiente();
            }
        },

        marcarAudioDesbloqueado() {
            notificarEstado();
            procesarSiguiente();
        },

        reiniciar() {
            anunciados.clear();
            cola.length = 0;
            adaptadorVoz?.cancelar?.();
            procesando = false;
            notificarEstado();
        },

        destruir() {
            cancelado = true;
            cola.length = 0;
            adaptadorVoz?.cancelar?.();
            procesando = false;
        },

        pendientes: () => cola.length,
        anunciados: () => anunciados.size,
    };
}
