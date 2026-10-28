const CANAL_LIDER = 'pdv-terminal-audio-leader-v1';
const INTERVALO_RECLAMO_MS = 4_000;
const GRACIA_SIN_LIDER_MS = 1_500;

function compararPestaña(a, b) {
    return String(a).localeCompare(String(b));
}

/**
 * Elección entre pestañas de la misma terminal.
 * Gana el pestañaId mayor. El menor cede y no responde, para no dejar a ambas sin liderazgo.
 * `latido_sin_respuesta` asume el liderazgo si el líder dejó de contestar.
 */
export function reducirLiderazgoAudioPdv(esLider, mensaje, propioId) {
    if (mensaje?.tipo === 'latido_sin_respuesta') {
        return { esLider: true, responder: null };
    }

    const otroId = mensaje?.pestañaId;
    if (!otroId || otroId === propioId) {
        return { esLider: Boolean(esLider), responder: null };
    }

    const propioGana = compararPestaña(propioId, otroId) > 0;

    if (mensaje.tipo === 'reclamar') {
        if (propioGana) {
            return { esLider: true, responder: 'lider_presente' };
        }
        return { esLider: false, responder: null };
    }

    if (mensaje.tipo === 'lider_presente') {
        if (propioGana) {
            return { esLider: true, responder: null };
        }
        return { esLider: false, responder: null };
    }

    if (mensaje.tipo === 'renuncia') {
        return { esLider: true, responder: 'reclamar' };
    }

    return { esLider: Boolean(esLider), responder: null };
}

export function crearCoordinadorAudioTerminalPdv({
    terminalId = null,
    habilitado = true,
    pestañaId = null,
    crearCanal = null,
    intervaloMs = INTERVALO_RECLAMO_MS,
    graciaMs = GRACIA_SIN_LIDER_MS,
} = {}) {
    const canalDisponible = typeof crearCanal === 'function'
        || (typeof window !== 'undefined' && typeof BroadcastChannel !== 'undefined');

    if (!habilitado || !canalDisponible) {
        return {
            esLider: () => true,
            destruir: () => {},
        };
    }

    const canal = typeof crearCanal === 'function'
        ? crearCanal()
        : new BroadcastChannel(CANAL_LIDER);
    const idLocal = pestañaId || `${Date.now()}-${Math.random().toString(36).slice(2)}`;
    let esLiderLocal = true;
    let destruido = false;
    let cerrando = false;
    let graciaTimer = null;
    let oyoParEnEsteReclamo = false;

    const post = (tipo) => {
        if (destruido) return;
        canal.postMessage({
            tipo,
            pestañaId: idLocal,
            terminalId,
            at: Date.now(),
        });
    };

    const limpiarGracia = () => {
        if (graciaTimer == null) return;
        clearTimeout(graciaTimer);
        graciaTimer = null;
    };

    const programarGracia = () => {
        if (esLiderLocal || destruido) return;
        limpiarGracia();
        graciaTimer = setTimeout(() => {
            graciaTimer = null;
            if (destruido || esLiderLocal) return;
            esLiderLocal = reducirLiderazgoAudioPdv(
                false,
                { tipo: 'latido_sin_respuesta' },
                idLocal,
            ).esLider;
        }, graciaMs);
    };

    const aplicar = (mensaje) => {
        const resultado = reducirLiderazgoAudioPdv(esLiderLocal, mensaje, idLocal);
        esLiderLocal = resultado.esLider;
        if (resultado.responder === 'lider_presente') post('lider_presente');
        if (resultado.responder === 'reclamar') post('reclamar');
        if (esLiderLocal) {
            limpiarGracia();
        }
    };

    canal.onmessage = (event) => {
        if (destruido || cerrando) return;
        const data = event?.data || {};
        if (data.terminalId && terminalId && data.terminalId !== terminalId) return;
        if (data.pestañaId === idLocal) return;
        oyoParEnEsteReclamo = true;
        aplicar(data);
        if (!esLiderLocal && (data.tipo === 'reclamar' || data.tipo === 'lider_presente')) {
            limpiarGracia();
        }
    };

    const reclamarLiderazgo = () => {
        oyoParEnEsteReclamo = false;
        post('reclamar');
        if (oyoParEnEsteReclamo) return;
        programarGracia();
    };

    reclamarLiderazgo();
    const intervalo = setInterval(reclamarLiderazgo, intervaloMs);

    return {
        esLider: () => esLiderLocal,
        destruir: () => {
            if (destruido) return;
            cerrando = true;
            clearInterval(intervalo);
            limpiarGracia();
            post('renuncia');
            destruido = true;
            canal.close?.();
        },
    };
}
