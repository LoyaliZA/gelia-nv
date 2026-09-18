import { describe, expect, it } from 'vitest';
import {
    antiguedadValidaEnBandeja,
    antiguedadesVisiblesPorBandeja,
    claseGridTarjetasResguardo,
    clasePieTarjetaRecepcion,
    claseVistaTabla,
    claseVistaTarjetas,
    etiquetaRetiroResguardo,
    etiquetaEstadoRecepcion,
    guardarVistaPorRecibir,
    leerVistaPorRecibir,
    mensajeVacioBandeja,
    metricasAntiguedadClaves,
    paramsListadoResguardos,
    plazosOperativosResguardo,
    referenciaCliente,
    titularResguardo,
    VISTA_RESGUARDOS_POR_RECIBIR,
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
        expect(claseVistaTarjetas('en_custodia')).toBe('');
        expect(claseVistaTabla('en_custodia')).toBe('hidden');
        expect(claseGridTarjetasResguardo('en_custodia')).toContain('grid');
        expect(claseVistaTarjetas('incidencias')).toContain('lg:hidden');
        expect(claseVistaTabla('incidencias')).toContain('lg:block');
    });

    it('permite alternar vista en por_recibir sin depender del breakpoint', () => {
        expect(claseVistaTarjetas('por_recibir', VISTA_RESGUARDOS_POR_RECIBIR.CARD)).toBe('');
        expect(claseVistaTabla('por_recibir', VISTA_RESGUARDOS_POR_RECIBIR.CARD)).toBe('hidden');
        expect(claseVistaTarjetas('por_recibir', VISTA_RESGUARDOS_POR_RECIBIR.LISTA)).toBe('hidden');
        expect(claseVistaTabla('por_recibir', VISTA_RESGUARDOS_POR_RECIBIR.LISTA)).toBe('');
    });

    it('persiste preferencia de vista por recibir', () => {
        const storage = {};
        globalThis.localStorage = {
            getItem: (key) => storage[key] ?? null,
            setItem: (key, value) => { storage[key] = value; },
        };

        expect(leerVistaPorRecibir()).toBe(VISTA_RESGUARDOS_POR_RECIBIR.CARD);
        guardarVistaPorRecibir(VISTA_RESGUARDOS_POR_RECIBIR.LISTA);
        expect(leerVistaPorRecibir()).toBe(VISTA_RESGUARDOS_POR_RECIBIR.LISTA);
    });

    it('arma titular, retiro y etiqueta de estado para tarjetas de recepción', () => {
        expect(titularResguardo({
            snapshot_cliente_nombre: 'Cliente Alfa',
            cliente: { numero_cliente: '9' },
        })).toBe('Cliente Alfa');

        expect(etiquetaRetiroResguardo({ etiqueta_retiro: 'Retira titular' })).toBe('Retira titular');
        expect(etiquetaRetiroResguardo({ envia_a_otra_persona: true, envia_otra_persona: 'Ana' }))
            .toBe('Recoge tercero autorizado: Ana');

        expect(etiquetaEstadoRecepcion({
            clasificaciones: { rezagado: true },
        }, { antiguedades: { rezagado: 'Rezagado' } })).toBe('Rezagado');

        expect(clasePieTarjetaRecepcion({ clasificaciones: { rezagado: true } }))
            .toContain('orange');
    });

    it('genera mensaje vacío según bandeja', () => {
        expect(mensajeVacioBandeja('por_recibir', { por_recibir: 'Por recibir' }))
            .toBe('No hay resguardos pendientes de recepción en esta sucursal.');
        expect(mensajeVacioBandeja('en_custodia', {}, true))
            .toBe('No hay resguardos que coincidan con los filtros aplicados.');
    });

    it('restringe antigüedad y métricas por bandeja', () => {
        const catalogo = {
            rezagado: 'Rezagado',
            proximo_a_vencer: 'Próximo a vencer',
            vencido: 'Vencido',
        };

        expect(metricasAntiguedadClaves('por_recibir')).toEqual(['rezagado']);
        expect(metricasAntiguedadClaves('en_custodia', false)).toEqual(['proximo_a_vencer']);
        expect(metricasAntiguedadClaves('en_custodia', true)).toEqual(['proximo_a_vencer', 'vencido']);

        expect(antiguedadValidaEnBandeja('por_recibir', 'rezagado')).toBe(true);
        expect(antiguedadValidaEnBandeja('por_recibir', 'vencido')).toBe(false);
        expect(antiguedadValidaEnBandeja('en_custodia', 'proximo_a_vencer')).toBe(true);

        expect(antiguedadesVisiblesPorBandeja('por_recibir', catalogo, true)).toEqual([['rezagado', 'Rezagado']]);
        expect(antiguedadesVisiblesPorBandeja('en_custodia', catalogo, false)).toEqual([
            ['proximo_a_vencer', 'Próximo a vencer'],
        ]);
    });

    it('presenta plazos del backend sin recalcular fechas', () => {
        const resguardo = {
            estado: 'en_custodia',
            fecha_limite_custodia: '2026-09-10T23:59:59-06:00',
            clasificaciones: { proximo_a_vencer: true, vencido: false, rezagado: false },
        };

        expect(plazosOperativosResguardo(resguardo)).toEqual([{
            id: 'custodia',
            etiqueta: 'Límite custodia',
            fecha: '2026-09-10T23:59:59-06:00',
            clasificacion: 'proximo_a_vencer',
        }]);

        expect(plazosOperativosResguardo({
            estado: 'pendiente_recepcion',
            fecha_limite_rezago: '2026-08-20T23:59:59-06:00',
            clasificaciones: { rezagado: true },
        })).toEqual([{
            id: 'rezago',
            etiqueta: 'Límite recepción',
            fecha: '2026-08-20T23:59:59-06:00',
            clasificacion: 'rezagado',
        }]);
    });
});
