// @vitest-environment node
import { describe, expect, it } from 'vitest';
import {
    claveRecargaVistaPdv,
    debeRefrescarVistaPdv,
    PDV_VISTA_REALTIME,
} from './pdvRealtimeMatrix';

const envelope = (extra = {}) => ({
    event_id: 'evt-1',
    tipo: 'turno.alta',
    dominio: 'turnos',
    audiencia: 'sucursal',
    sucursal_id: 1,
    datos: {},
    ...extra,
});

describe('pdvRealtimeMatrix', () => {
    it('aplica la matriz mínima por vista', () => {
        expect(debeRefrescarVistaPdv(PDV_VISTA_REALTIME.gerencia, envelope())).toBe(true);
        expect(debeRefrescarVistaPdv(PDV_VISTA_REALTIME.vendedor, envelope())).toBe(false);
        expect(debeRefrescarVistaPdv(PDV_VISTA_REALTIME.recepcion, envelope())).toBe(true);

        expect(debeRefrescarVistaPdv(
            PDV_VISTA_REALTIME.gerencia,
            envelope({ tipo: 'turno.ventana_reatencion_vencida' }),
        )).toBe(true);

        expect(debeRefrescarVistaPdv(
            PDV_VISTA_REALTIME.recepcion,
            envelope({ tipo: 'turno.reatencion' }),
        )).toBe(false);
    });

    it('filtra eventos de operación al vendedor afectado', () => {
        const pausa = envelope({
            dominio: 'operacion',
            tipo: 'pausa.iniciada',
            datos: { intervalo: { user_id: 7 } },
        });

        expect(debeRefrescarVistaPdv(PDV_VISTA_REALTIME.vendedor, pausa, { userId: 7 })).toBe(true);
        expect(debeRefrescarVistaPdv(PDV_VISTA_REALTIME.vendedor, pausa, { userId: 8 })).toBe(false);
        expect(debeRefrescarVistaPdv(PDV_VISTA_REALTIME.gerencia, pausa)).toBe(true);
        expect(debeRefrescarVistaPdv(PDV_VISTA_REALTIME.recepcion, pausa)).toBe(true);
    });

    it('refresca vendedor con eventos personales de turnos', () => {
        const asignado = envelope({
            tipo: 'turno.asignado',
            audiencia: 'usuario',
        });

        expect(debeRefrescarVistaPdv(PDV_VISTA_REALTIME.vendedor, asignado, { userId: 3 })).toBe(true);
    });

    it('expone claves estables de recarga por vista', () => {
        expect(claveRecargaVistaPdv(PDV_VISTA_REALTIME.gerencia)).toBe('pdv:recarga:gerencia');
    });
});
