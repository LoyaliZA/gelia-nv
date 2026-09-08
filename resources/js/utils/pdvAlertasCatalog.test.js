// @vitest-environment node
import { describe, expect, it } from 'vitest';
import {
    mensajeTtsTerminalPdv,
    prioridadAlertaPdv,
    PDV_ALERTA_PRIORIDAD,
} from './pdvAlertasCatalog';

describe('pdvAlertasCatalog', () => {
    it('prioriza alertas críticas sobre normales', () => {
        expect(prioridadAlertaPdv({ tipo: 'atencion.espera_proximo_vencer' }))
            .toBe(PDV_ALERTA_PRIORIDAD.critica);
        expect(prioridadAlertaPdv({ tipo: 'turno.alta' }))
            .toBe(PDV_ALERTA_PRIORIDAD.normal);
        expect(
            prioridadAlertaPdv({ tipo: 'atencion.espera_proximo_vencer' })
            < prioridadAlertaPdv({ tipo: 'turno.alta' }),
        ).toBe(true);
    });

    it('anuncia VIP y Diamante en terminal usando datos del backend', () => {
        expect(mensajeTtsTerminalPdv({
            tipo: 'turno.alta',
            datos: { folio: 'V-100', prioridad_vip: true },
        })).toBe('Nuevo turno VIP, V-100');

        expect(mensajeTtsTerminalPdv({
            tipo: 'turno.alta',
            datos: { folio: 'V-200', prioridad_diamante: true },
        })).toBe('Nuevo turno Diamante, V-200');
    });

    it('usa guion de asignación con primer nombre del vendedor', () => {
        expect(mensajeTtsTerminalPdv({
            tipo: 'turno.asignado',
            datos: {
                folio: 'V-300',
                atencion: { primer_nombre: 'Ana' },
            },
        })).toBe('Turno V-300, pasar con Ana');
    });
});
