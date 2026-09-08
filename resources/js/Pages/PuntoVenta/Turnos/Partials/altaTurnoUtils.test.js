// @vitest-environment happy-dom
import { afterEach, describe, expect, it } from 'vitest';
import {
    armarPayloadAltaTurno,
    buscarTurnoActivoClienteEnBandeja,
    claveIdempotenciaAltaTurno,
    debeRefrescarBandejaRecepcionPorEvento,
    esListaDiamanteCliente,
    etiquetasPrioridadTurno,
    formularioListoParaEnviar,
    mensajeClienteYaEnCola,
    mensajeErrorAltaTurno,
    mensajeSucursalSinAltas,
    renovarClaveIdempotenciaAltaTurno,
    sucursalAceptaAltasTurno,
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

    it('detecta cliente ya presente en la bandeja visible', () => {
        const bandeja = {
            en_cola: [{ id: 10, cliente_id: 5, folio: 'V-0001', estado: 'EN_COLA' }],
            asignados: [{ id: 11, cliente_id: 8, folio: 'V-0002', estado: 'ASIGNADO' }],
        };

        expect(buscarTurnoActivoClienteEnBandeja(bandeja, 5)).toMatchObject({ folio: 'V-0001' });
        expect(buscarTurnoActivoClienteEnBandeja(bandeja, 8)).toMatchObject({ folio: 'V-0002' });
        expect(buscarTurnoActivoClienteEnBandeja(bandeja, 99)).toBeNull();

        expect(validarFormularioAltaTurno({
            modo: 'cliente',
            cliente: { id: 5, nombre: 'Cliente' },
            bandeja,
        })).toEqual({
            cliente: mensajeClienteYaEnCola(bandeja.en_cola[0]),
        });

        expect(mensajeClienteYaEnCola(bandeja.asignados[0])).toContain('asignado');
        expect(mensajeClienteYaEnCola(bandeja.en_cola[0])).toContain('en cola');
    });

    it('oculta mensajes SQL crudos del servidor', () => {
        const mensaje = mensajeErrorAltaTurno({
            response: {
                status: 500,
                data: {
                    message: "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1-V-0001'",
                },
            },
        });

        expect(mensaje).not.toContain('SQLSTATE');
        expect(mensaje).toContain('Actualiza la bandeja');
    });

    it('bloquea envío sin datos, con sucursal cerrada y refresca con evento turno.alta', () => {
        expect(formularioListoParaEnviar({
            modo: 'visitante',
            nombreLlamado: 'Ana',
        })).toBe(true);

        expect(formularioListoParaEnviar({
            modo: 'visitante',
            nombreLlamado: 'A',
        })).toBe(false);

        expect(formularioListoParaEnviar({
            modo: 'cliente',
            cliente: { id: 1, nombre: 'Cliente' },
            sucursalDia: { acepta_altas: false },
        })).toBe(false);

        expect(sucursalAceptaAltasTurno({ acepta_altas: true })).toBe(true);
        expect(sucursalAceptaAltasTurno({ acepta_altas: false })).toBe(false);
        expect(mensajeSucursalSinAltas({ acepta_altas: false, cierre_manual_at: '2026-09-07T19:00:00Z' }))
            .toContain('cierre manual');

        expect(debeRefrescarBandejaRecepcionPorEvento({
            dominio: 'turnos',
            tipo: 'turno.alta',
        })).toBe(true);
        expect(debeRefrescarBandejaRecepcionPorEvento({
            dominio: 'turnos',
            tipo: 'turno.asignado',
        })).toBe(true);
        expect(debeRefrescarBandejaRecepcionPorEvento({
            dominio: 'turnos',
            tipo: 'turno.reatencion',
        })).toBe(false);
        expect(debeRefrescarBandejaRecepcionPorEvento({
            dominio: 'operacion',
            tipo: 'pausa.iniciada',
        })).toBe(true);
    });
});
