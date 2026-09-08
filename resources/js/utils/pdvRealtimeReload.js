import { PDV_COLA_MAX_IDS_VISTOS } from './pdvAlertQueueUtils';

export const PDV_RECARGA_DEBOUNCE_MS = 150;

export function crearRegistroEventIdsRecarga(maxIds = PDV_COLA_MAX_IDS_VISTOS) {
    const ids = new Set();

    return {
        yaProcesado(eventId) {
            return ids.has(String(eventId || ''));
        },
        marcar(eventId) {
            const id = String(eventId || '').trim();
            if (!id) return false;
            if (ids.has(id)) return false;
            ids.add(id);
            if (ids.size > maxIds) {
                const recortados = [...ids].slice(-maxIds);
                ids.clear();
                recortados.forEach((valor) => ids.add(valor));
            }
            return true;
        },
        reiniciar() {
            ids.clear();
        },
    };
}

export function crearCoordinadorRecargaPdv(debounceMs = PDV_RECARGA_DEBOUNCE_MS) {
    const pendientes = new Map();

    return {
        programar(clave, handler) {
            const key = String(clave || 'default');
            const existente = pendientes.get(key);
            if (existente) clearTimeout(existente);

            const timeoutId = setTimeout(() => {
                pendientes.delete(key);
                handler?.();
            }, debounceMs);

            pendientes.set(key, timeoutId);
        },
        cancelarTodo() {
            pendientes.forEach((timeoutId) => clearTimeout(timeoutId));
            pendientes.clear();
        },
        pendiente(clave) {
            return pendientes.has(String(clave || 'default'));
        },
    };
}
