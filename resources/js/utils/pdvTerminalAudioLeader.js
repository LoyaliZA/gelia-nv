const CANAL_LIDER = 'pdv-terminal-audio-leader-v1';

export function crearCoordinadorAudioTerminalPdv({
    terminalId = null,
    habilitado = true,
} = {}) {
    if (!habilitado || typeof window === 'undefined' || typeof BroadcastChannel === 'undefined') {
        return {
            esLider: () => true,
            destruir: () => {},
        };
    }

    const canal = new BroadcastChannel(CANAL_LIDER);
    const pestañaId = `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    let esLiderLocal = false;
    let destruido = false;

    const post = (tipo, extra = {}) => {
        if (destruido) return;
        canal.postMessage({
            tipo,
            pestañaId,
            terminalId,
            at: Date.now(),
            ...extra,
        });
    };

    const reclamarLiderazgo = () => {
        post('reclamar');
    };

    canal.onmessage = (event) => {
        const data = event.data || {};
        if (data.terminalId && terminalId && data.terminalId !== terminalId) return;

        if (data.tipo === 'reclamar') {
            if (!esLiderLocal) return;
            if (data.pestañaId !== pestañaId) {
                post('lider_presente');
            }
            return;
        }

        if (data.tipo === 'lider_presente' && data.pestañaId !== pestañaId) {
            esLiderLocal = false;
            return;
        }

        if (data.tipo === 'renuncia' && data.pestañaId !== pestañaId) {
            esLiderLocal = true;
        }
    };

    esLiderLocal = true;
    reclamarLiderazgo();
    const intervalo = window.setInterval(reclamarLiderazgo, 4_000);

    return {
        esLider: () => esLiderLocal,
        destruir: () => {
            if (destruido) return;
            destruido = true;
            window.clearInterval(intervalo);
            post('renuncia');
            canal.close();
        },
    };
}
