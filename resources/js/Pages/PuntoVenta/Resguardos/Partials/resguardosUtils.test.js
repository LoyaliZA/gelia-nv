import { describe, expect, it } from 'vitest';
import {
    claseVistaTabla,
    claseVistaTarjetas,
    mensajeVacioBandeja,
    paramsListadoResguardos,
    referenciaCliente,
} from './resguardosUtils';

describe('resguardosUtils', () => {
    it('arma parámetros de listado sin valores vacíos', () => {
        expect(paramsListadoResguardos({
            bandeja: 'por_recibir',
            q: '',
            estado: '',
            antiguedad: 'rezagado',
            page: 2,
        })).toEqual({
            bandeja: 'por_recibir',
            antiguedad: 'rezagado',
            page: 2,
        });
    });

    it('oculta nombre completo y usa número de cliente', () => {
        expect(referenciaCliente({
            cliente: { numero_cliente: '12345' },
            snapshot_folio: 'REM-001',
        })).toBe('#12345');
    });

    it('usa folio cuando no hay número de cliente', () => {
        expect(referenciaCliente({ snapshot_folio: 'REM-777' })).toBe('REM-777');
    });

    it('define vistas responsivas para tarjetas y tabla', () => {
        expect(claseVistaTarjetas()).toContain('lg:hidden');
        expect(claseVistaTabla()).toContain('hidden');
        expect(claseVistaTabla()).toContain('lg:block');
    });

    it('genera mensaje vacío según bandeja', () => {
        expect(mensajeVacioBandeja('por_recibir', { por_recibir: 'Por recibir' }))
            .toBe('No hay resguardos en por recibir');
    });
});
