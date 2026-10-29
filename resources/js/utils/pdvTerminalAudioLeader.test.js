// @vitest-environment node
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    crearCoordinadorAudioTerminalPdv,
    reducirLiderazgoAudioPdv,
} from './pdvTerminalAudioLeader';

function busDePestañas(cantidad) {
    const canales = Array.from({ length: cantidad }, () => ({
        onmessage: null,
        postMessage() {},
        close() {},
    }));

    canales.forEach((canal, indice) => {
        canal.postMessage = (data) => {
            canales.forEach((otro, otroIndice) => {
                if (otroIndice === indice) return;
                otro.onmessage?.({ data });
            });
        };
    });

    return canales;
}

describe('reducirLiderazgoAudioPdv', () => {
    it('deja una sola pestaña líder cuando ambas reclaman', () => {
        const menor = reducirLiderazgoAudioPdv(true, { tipo: 'reclamar', pestañaId: 'b' }, 'a');
        const mayor = reducirLiderazgoAudioPdv(true, { tipo: 'reclamar', pestañaId: 'a' }, 'b');

        expect(menor).toEqual({ esLider: false, responder: null });
        expect(mayor).toEqual({ esLider: true, responder: 'lider_presente' });

        const menorTrasAviso = reducirLiderazgoAudioPdv(
            menor.esLider,
            { tipo: 'lider_presente', pestañaId: 'b' },
            'a',
        );
        expect(menorTrasAviso.esLider).toBe(false);
        expect([menor.esLider, mayor.esLider].filter(Boolean)).toHaveLength(1);
    });

    it('al renunciar el líder, la otra pestaña reclama', () => {
        const siguiente = reducirLiderazgoAudioPdv(false, { tipo: 'renuncia', pestañaId: 'b' }, 'a');
        expect(siguiente).toEqual({ esLider: true, responder: 'reclamar' });
    });

    it('asume el liderazgo si el líder deja de responder', () => {
        const siguiente = reducirLiderazgoAudioPdv(false, { tipo: 'latido_sin_respuesta' }, 'a');
        expect(siguiente.esLider).toBe(true);
    });
});

describe('crearCoordinadorAudioTerminalPdv', () => {
    afterEach(() => {
        vi.useRealTimers();
    });

    it('elige una sola líder entre dos pestañas de la misma terminal', () => {
        const [canalA, canalB] = busDePestañas(2);
        const menor = crearCoordinadorAudioTerminalPdv({
            terminalId: 'terminal-1',
            pestañaId: 'a',
            crearCanal: () => canalA,
            intervaloMs: 60_000,
        });
        const mayor = crearCoordinadorAudioTerminalPdv({
            terminalId: 'terminal-1',
            pestañaId: 'b',
            crearCanal: () => canalB,
            intervaloMs: 60_000,
        });

        expect([menor.esLider(), mayor.esLider()].filter(Boolean)).toEqual([true]);
        expect(mayor.esLider()).toBe(true);

        mayor.destruir();
        menor.destruir();
    });

    it('otra pestaña asume al cerrar la líder', () => {
        const [canalA, canalB] = busDePestañas(2);
        const menor = crearCoordinadorAudioTerminalPdv({
            terminalId: 'terminal-1',
            pestañaId: 'a',
            crearCanal: () => canalA,
            intervaloMs: 60_000,
        });
        const mayor = crearCoordinadorAudioTerminalPdv({
            terminalId: 'terminal-1',
            pestañaId: 'b',
            crearCanal: () => canalB,
            intervaloMs: 60_000,
        });

        expect(mayor.esLider()).toBe(true);
        mayor.destruir();
        expect(menor.esLider()).toBe(true);
        expect(mayor.esLider()).toBe(true);

        menor.destruir();
    });

    it('no deja el conjunto en silencio si la líder desaparece sin renunciar', () => {
        vi.useFakeTimers();
        const [canalA, canalB] = busDePestañas(2);
        const menor = crearCoordinadorAudioTerminalPdv({
            terminalId: 'terminal-1',
            pestañaId: 'a',
            crearCanal: () => canalA,
            intervaloMs: 4_000,
            graciaMs: 1_500,
        });
        const mayor = crearCoordinadorAudioTerminalPdv({
            terminalId: 'terminal-1',
            pestañaId: 'b',
            crearCanal: () => canalB,
            intervaloMs: 4_000,
            graciaMs: 1_500,
        });

        expect(mayor.esLider()).toBe(true);
        canalB.postMessage = () => {};

        vi.advanceTimersByTime(4_000);
        expect(menor.esLider()).toBe(false);
        vi.advanceTimersByTime(1_500);
        expect(menor.esLider()).toBe(true);

        menor.destruir();
        mayor.destruir();
    });

    it('no coordina pestañas de otra terminal', () => {
        const [canalA, canalB] = busDePestañas(2);
        const alertas = crearCoordinadorAudioTerminalPdv({
            terminalId: 'alertas',
            pestañaId: 'b',
            crearCanal: () => canalA,
            intervaloMs: 60_000,
        });
        const general = crearCoordinadorAudioTerminalPdv({
            terminalId: 'general',
            pestañaId: 'a',
            crearCanal: () => canalB,
            intervaloMs: 60_000,
        });

        expect(alertas.esLider()).toBe(true);
        expect(general.esLider()).toBe(true);

        alertas.destruir();
        general.destruir();
    });
});
