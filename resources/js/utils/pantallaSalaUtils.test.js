// @vitest-environment node
import { describe, expect, it } from 'vitest';
import {
    aplicarEventoSala,
    fusionarEstadoSala,
    llamadoActualSala,
    payloadSalaSinDatosSensibles,
    PDV_SALA_CAMPOS_PROHIBIDOS,
} from './pantallaSalaUtils';

const envelopeAsignado = (extra = {}) => ({
    event_id: 'turnos:turno.asignado:1',
    tipo: 'turno.asignado',
    dominio: 'turnos',
    audiencia: 'publico',
    datos: {
        turno_id: 10,
        folio: 'V-0100',
        servicio: 'ventas',
        estado: 'ASIGNADO',
        prioridad_diamante: true,
        prioridad_vip: true,
        snapshot_nombre_llamado: 'María López',
        atencion_primer_nombre: 'Ana',
        cliente_id: 99,
        telefono: '5551234567',
    },
    ocurrido_at: '2026-09-04T12:00:00Z',
    ...extra,
});

describe('pantallaSalaUtils', () => {
    it('aplica llamado y cierra atención sin datos VIP en el estado', () => {
        const conLlamado = aplicarEventoSala([], envelopeAsignado());
        expect(conLlamado).toHaveLength(1);
        expect(conLlamado[0].folio).toBe('V-0100');
        expect(conLlamado[0].snapshot_nombre_llamado).toBe('María López');
        expect(conLlamado[0].prioridad_diamante).toBe(true);
        expect(conLlamado[0].prioridad_vip).toBeUndefined();

        const sinLlamado = aplicarEventoSala(conLlamado, {
            event_id: 'turnos:atencion.cerrada:2',
            tipo: 'atencion.cerrada',
            datos: { turno_id: 10 },
        });

        expect(sinLlamado).toHaveLength(0);
    });

    it('deduplica actualizaciones del mismo turno', () => {
        const primero = aplicarEventoSala([], envelopeAsignado());
        const segundo = aplicarEventoSala(primero, envelopeAsignado({
            event_id: 'turnos:turno.transferido:3',
            tipo: 'turno.transferido',
            datos: {
                ...envelopeAsignado().datos,
                atencion_primer_nombre: 'Luis',
            },
        }));

        expect(segundo).toHaveLength(1);
        expect(segundo[0].atencion_primer_nombre).toBe('Luis');
    });

    it('elige el llamado más reciente como actual', () => {
        const lista = aplicarEventoSala([], envelopeAsignado({
            ocurrido_at: '2026-09-04T11:00:00Z',
            datos: { ...envelopeAsignado().datos, turno_id: 1, folio: 'V-0001' },
        }));
        const conDos = aplicarEventoSala(lista, envelopeAsignado({
            event_id: 'turnos:turno.asignado:2',
            ocurrido_at: '2026-09-04T12:30:00Z',
            datos: {
                ...envelopeAsignado().datos,
                turno_id: 2,
                folio: 'V-0002',
                snapshot_nombre_llamado: 'Pedro Ruiz',
            },
        }));

        expect(llamadoActualSala(conDos)?.folio).toBe('V-0002');
    });

    it('fusiona snapshot de API con eventos locales al reconectar', () => {
        const desdeEventos = aplicarEventoSala([], envelopeAsignado({
            ocurrido_at: '2026-09-04T12:00:00Z',
            datos: { ...envelopeAsignado().datos, turno_id: 10, folio: 'V-0100' },
        }));

        const fusionado = fusionarEstadoSala(
            [{
                turno_id: 10,
                folio: 'V-0100',
                servicio: 'ventas',
                snapshot_nombre_llamado: 'María López',
                atencion_primer_nombre: 'Ana',
                llamado_at: '2026-09-04T12:00:00Z',
            }, {
                turno_id: 11,
                folio: 'V-0101',
                servicio: 'ventas',
                snapshot_nombre_llamado: 'Pedro Ruiz',
                atencion_primer_nombre: 'Luis',
                llamado_at: '2026-09-04T12:30:00Z',
            }],
            desdeEventos,
        );

        expect(fusionado).toHaveLength(2);
        expect(llamadoActualSala(fusionado)?.folio).toBe('V-0101');
        expect(fusionado.find((item) => item.turno_id === 10)?.atencion_primer_nombre).toBe('Ana');
    });

    it('elimina campos sensibles del payload renderizable', () => {
        const limpio = payloadSalaSinDatosSensibles({
            folio: 'V-1',
            snapshot_nombre_llamado: 'Cliente',
            prioridad_vip: true,
            nested: { telefono: '123', rfc: 'X' },
        });

        PDV_SALA_CAMPOS_PROHIBIDOS.forEach((campo) => {
            expect(limpio[campo]).toBeUndefined();
        });
        expect(limpio.snapshot_nombre_llamado).toBe('Cliente');
        expect(limpio.nested.telefono).toBeUndefined();
    });
});
