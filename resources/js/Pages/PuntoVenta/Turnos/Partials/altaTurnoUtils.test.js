// @vitest-environment happy-dom
import { afterEach, describe, expect, it } from 'vitest';
import {
    armarPayloadAltaTurno,
    claveIdempotenciaAltaTurno,
    esListaDiamanteCliente,
    etiquetasPrioridadTurno,
    renovarClaveIdempotenciaAltaTurno,
    validarFormularioAltaTurno,
} from './altaTurnoUtils';

describe('altaTurnoUtils', () => {
    afterEach(() => {
        sessionStorage.clear();
    });

    it('reutiliza clave de idempotencia por sesión', () => {
        const primera = claveIdempotenciaAltaTurno('test');
        const segunda = claveIdempotenciaAltaTurno('test');
        expect(segunda).toBe(primera);
    });

    it('renueva clave tras alta exitosa', () => {
        const original = claveIdempotenciaAltaTurno('test');
        const renovada = renovarClaveIdempotenciaAltaTurno('test');
        expect(renovada).not.toBe(original);
        expect(claveIdempotenciaAltaTurno('test')).toBe(renovada);
    });

    it('arma payload de cliente y visitante', () => {
        expect(armarPayloadAltaTurno({
            idempotencyKey: 'pdv:turno:1',
            clienteId: 9,
            prioridadAdultoMayor: true,
        })).toEqual({
            idempotency_key: 'pdv:turno:1',
            cliente_id: 9,
            prioridad_adulto_mayor: true,
            prioridad_discapacidad: false,
        });

        expect(armarPayloadAltaTurno({
            idempotencyKey: 'pdv:turno:2',
            nombreLlamado: 'Ana',
        })).toEqual({
            idempotency_key: 'pdv:turno:2',
            nombre_llamado: 'Ana',
            prioridad_adulto_mayor: false,
            prioridad_discapacidad: false,
        });
    });

    it('valida cliente, visitante y prioridades visibles', () => {
        expect(validarFormularioAltaTurno({ modo: 'cliente', cliente: null })).toHaveProperty('cliente');
        expect(validarFormularioAltaTurno({ modo: 'visitante', nombreLlamado: 'A' })).toHaveProperty('nombre_llamado');
        expect(validarFormularioAltaTurno({
            modo: 'cliente',
            cliente: { id: 1, nombre: 'Cliente' },
        })).toEqual({});

        const turno = {
            prioridad_diamante: true,
            prioridad_vip: false,
            prioridad_adulto_mayor: true,
            prioridad_discapacidad: false,
        };
        expect(etiquetasPrioridadTurno(turno)).toEqual(['Diamante', 'Adulto mayor']);
        expect(esListaDiamanteCliente({ lista_actual: 'MAYOREO DIAMANTE' })).toBe(true);
    });
});
