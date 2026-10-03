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

    it('usa el mismo guion de llamado sin decir reatención', () => {
        expect(mensajeTtsTerminalPdv({
            tipo: 'turno.asignado',
            datos: {
                folio: 'V-300',
                snapshot_nombre_llamado: 'María López',
                prioridad_diamante: true,
                atencion: { primer_nombre: 'Ana' },
            },
        })).toBe('Turno V-300. María López. Tiene prioridad. Pase con Ana.');

        expect(mensajeTtsTerminalPdv({
            tipo: 'turno.reatencion',
            datos: {
                folio: 'V-301',
                snapshot_nombre_llamado: 'María López',
                atencion: { primer_nombre: 'Ana' },
            },
        })).toBe('Turno V-301. María López. Pase con Ana.');
    });

    it('anuncia prórroga, pausa y resguardo nuevo solo con la frase breve', () => {
        expect(mensajeTtsTerminalPdv({
            tipo: 'atencion.prorroga',
            datos: { folio: 'V-400' },
        })).toBe('Prórroga iniciada. Turno V-400.');

        expect(mensajeTtsTerminalPdv({
            tipo: 'pausa.iniciada',
            datos: { primer_nombre: 'Ana' },
        })).toBe('Pausa activa. Ana.');

        expect(mensajeTtsTerminalPdv({
            tipo: 'resguardo.recepcion_esperada_creada',
            datos: { folio: 'R-10', snapshot_cliente_nombre: 'Cliente Uno' },
        })).toBe('Nuevo resguardo pendiente de aprobación. R-10.');
    });
});
