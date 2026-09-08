import { describe, expect, it } from 'vitest';
import {
    abreviarNombreLlamado,
    etiquetaAtencionAsignado,
    formatearEsperaTurno,
    resumenBandejaRecepcion,
    resumenPrioridadTurno,
} from './recepcionTurnoUtils';

describe('recepcionTurnoUtils', () => {
    it('abrevia nombres largos para la fila', () => {
        expect(abreviarNombreLlamado('María Fernanda López García')).toBe('María G.');
        expect(abreviarNombreLlamado('Visitante')).toBe('Visitante');
    });

    it('lee resumen del payload cuando existe', () => {
        expect(resumenBandejaRecepcion({
            resumen: {
                en_espera: 3,
                asignados: 2,
                mayor_espera_segundos: 125,
                vendedores_disponibles: 4,
            },
            en_cola: [],
            asignados: [],
        })).toEqual({
            enEspera: 3,
            asignados: 2,
            mayorEsperaSegundos: 125,
            vendedoresDisponibles: 4,
        });
    });

    it('calcula conteos desde listas como respaldo', () => {
        expect(resumenBandejaRecepcion({
            en_cola: [{ espera_segundos: 30 }, { espera_segundos: 90 }],
            asignados: [{}],
        })).toMatchObject({
            enEspera: 2,
            asignados: 1,
            mayorEsperaSegundos: 90,
        });
    });

    it('formatea espera y prioridad de turno', () => {
        expect(formatearEsperaTurno({ espera_segundos: 125 })).toBe('2m 5s');
        expect(resumenPrioridadTurno({ prioridad_vip: true })).toBe('VIP');
        expect(resumenPrioridadTurno({})).toBe('Normal');
    });

    it('etiqueta estado de atención en asignados', () => {
        expect(etiquetaAtencionAsignado({
            atencion: { atencion_en_curso: true, espera_inicial_vencida: false },
        })).toBe('En atención');
        expect(etiquetaAtencionAsignado({
            atencion: { atencion_en_curso: false, espera_inicial_vencida: true },
        })).toBe('Espera vencida');
    });
});
