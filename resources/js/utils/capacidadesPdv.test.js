import { describe, it, expect } from 'vitest';
import { calcularCapacidadesPdv } from './capacidadesPdv';

const SUCURSALES = [
    { id: 1, nombre: 'Centro' },
    { id: 2, nombre: 'Norte' },
];

describe('calcularCapacidadesPdv', () => {
    it('marca capacidades según permisos y sucursales', () => {
        const capacidades = calcularCapacidadesPdv({
            permisos: [
                'pdv.turnos.alta',
                'pdv.turnos.atender',
                'pdv.turnos.transferir',
                'pdv.turnos.ver',
            ],
            sucursalesCatalogo: SUCURSALES,
            sucursalIds: [1, 2],
            sucursalPrincipalId: 2,
        });

        expect(capacidades.sucursalesAsignadas).toEqual(['Centro', 'Norte']);
        expect(capacidades.sucursalPrincipal).toBe('Norte');
        expect(capacidades.puedeRegistrarTurnos).toBe(true);
        expect(capacidades.puedeAtenderTurnos).toBe(true);
        expect(capacidades.apareceEnGestionVendedores).toBe(true);
        expect(capacidades.notaAtenderSinSucursal).toBe(false);
        expect(capacidades.puedeGestionarVendedores).toBe(true);
        expect(capacidades.puedeConsultarReportes).toBe(true);
        expect(capacidades.puedeAdministrarExcepciones).toBe(false);
    });

    it('indica nota cuando puede atender pero no tiene sucursal', () => {
        const capacidades = calcularCapacidadesPdv({
            permisos: ['pdv.turnos.atender'],
            sucursalesCatalogo: SUCURSALES,
            sucursalIds: [],
        });

        expect(capacidades.puedeAtenderTurnos).toBe(true);
        expect(capacidades.apareceEnGestionVendedores).toBe(false);
        expect(capacidades.notaAtenderSinSucursal).toBe(true);
    });

    it('detecta reportes por resguardos con alcance global', () => {
        const capacidades = calcularCapacidadesPdv({
            permisos: ['pdv.resguardos.ver', 'pdv.alcance.global'],
            sucursalesCatalogo: SUCURSALES,
            sucursalIds: [1],
        });

        expect(capacidades.puedeConsultarReportes).toBe(true);
        expect(capacidades.puedeAdministrarExcepciones).toBe(true);
    });

    it('gestionar vendedores requiere transferir o baja de cola', () => {
        expect(calcularCapacidadesPdv({
            permisos: ['pdv.turnos.baja_cola'],
        }).puedeGestionarVendedores).toBe(true);

        expect(calcularCapacidadesPdv({
            permisos: ['pdv.turnos.ver'],
        }).puedeGestionarVendedores).toBe(false);
    });
});
