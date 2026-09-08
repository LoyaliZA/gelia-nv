// @vitest-environment node
import { describe, expect, it, vi } from 'vitest';
import {
    crearCoordinadorRecargaPdv,
    crearRegistroEventIdsRecarga,
    PDV_RECARGA_DEBOUNCE_MS,
} from './pdvRealtimeReload';

describe('pdvRealtimeReload', () => {
    it('deduplica event_id para recargas', () => {
        const registro = crearRegistroEventIdsRecarga(10);

        expect(registro.marcar('evt-1')).toBe(true);
        expect(registro.marcar('evt-1')).toBe(false);
        expect(registro.yaProcesado('evt-1')).toBe(true);

        registro.reiniciar();
        expect(registro.marcar('evt-1')).toBe(true);
    });

    it('agrupa recargas con debounce por clave', () => {
        vi.useFakeTimers();

        const coordinador = crearCoordinadorRecargaPdv(100);
        const handler = vi.fn();

        coordinador.programar('gerencia', handler);
        coordinador.programar('gerencia', handler);

        expect(handler).not.toHaveBeenCalled();

        vi.advanceTimersByTime(PDV_RECARGA_DEBOUNCE_MS);
        expect(handler).toHaveBeenCalledTimes(1);

        vi.useRealTimers();
    });

    it('cancela recargas pendientes al limpiar', () => {
        vi.useFakeTimers();

        const coordinador = crearCoordinadorRecargaPdv(100);
        const handler = vi.fn();

        coordinador.programar('recepcion', handler);
        coordinador.cancelarTodo();

        vi.advanceTimersByTime(200);
        expect(handler).not.toHaveBeenCalled();

        vi.useRealTimers();
    });
});
