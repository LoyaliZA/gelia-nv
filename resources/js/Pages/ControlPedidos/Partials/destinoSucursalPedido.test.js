import { describe, expect, it } from 'vitest';
import {
    destinoSucursalParaPayload,
    esTrasladoInternoPdv,
    destinoSucursalVisible,
    modalidadesRequierenDestino,
    requiereCapturaDestinoSucursal,
    resolverCodigoModalidadEfectiva,
} from './destinoSucursalPedido';

const MODALIDADES = ['RECOGE_TIENDA', 'RECOGE_TIENDA_TRANSFERENCIA'];

const catalogos = {
    destino_sucursal_config: {
        modalidades_requieren_destino: MODALIDADES,
    },
};

describe('requiereCapturaDestinoSucursal', () => {
    it('exige destino para modalidades a sucursal', () => {
        expect(requiereCapturaDestinoSucursal({
            requiereLogistica: true,
            codigoModalidad: 'RECOGE_TIENDA',
            modalidadesRequierenDestino: MODALIDADES,
        })).toBe(true);
    });

    it('oculta destino para modalidades de envío', () => {
        expect(requiereCapturaDestinoSucursal({
            requiereLogistica: true,
            codigoModalidad: 'ENVIO_BODEGA_NORMAL',
            modalidadesRequierenDestino: MODALIDADES,
        })).toBe(false);
        expect(requiereCapturaDestinoSucursal({
            requiereLogistica: true,
            codigoModalidad: 'ENVIO_MUNICIPIO',
            modalidadesRequierenDestino: MODALIDADES,
        })).toBe(false);
    });

    it('exige destino en tienda/mostrador sin modalidad', () => {
        expect(requiereCapturaDestinoSucursal({
            requiereLogistica: false,
            codigoModalidad: '',
            modalidadesRequierenDestino: MODALIDADES,
        })).toBe(true);
    });

    it('no exige destino en envío sin modalidad de sucursal', () => {
        expect(requiereCapturaDestinoSucursal({
            requiereLogistica: true,
            codigoModalidad: '',
            modalidadesRequierenDestino: MODALIDADES,
        })).toBe(false);
    });
});

describe('destinoSucursalVisible', () => {
    it('ofrece sucursal opcional en salida CEDIS con logística', () => {
        expect(destinoSucursalVisible({
            requiereLogistica: true,
            tieneAlmacenSalida: true,
            salidaEsPdv: false,
            modalidadesRequierenDestino: MODALIDADES,
        })).toEqual({ muestra: true, obligatorio: false });
    });

    it('ofrece traslado a otro PDV desde salida PDV', () => {
        expect(destinoSucursalVisible({
            requiereLogistica: true,
            tieneAlmacenSalida: true,
            salidaEsPdv: true,
            modalidadesRequierenDestino: MODALIDADES,
        })).toEqual({ muestra: true, obligatorio: false });
    });
});

describe('destinoSucursalParaPayload', () => {
    it('conserva opción válida al guardar', () => {
        expect(destinoSucursalParaPayload({
            muestra: true,
            sucursalDestinoId: '7',
            esAutoguardado: false,
        })).toEqual({ sucursal_destino_id: 7 });
    });

    it('omite el campo en autoguardado cuando no aplica', () => {
        expect(destinoSucursalParaPayload({
            muestra: false,
            sucursalDestinoId: '7',
            esAutoguardado: true,
        })).toEqual({});
    });

    it('permite destino nulo al guardar cuando no aplica', () => {
        expect(destinoSucursalParaPayload({
            muestra: false,
            sucursalDestinoId: '7',
            esAutoguardado: false,
        })).toEqual({ sucursal_destino_id: null });
    });
});

describe('modalidadesRequierenDestino', () => {
    it('lee la lista expuesta por el backend', () => {
        expect(modalidadesRequierenDestino(catalogos)).toEqual(MODALIDADES);
    });
});

describe('resolverCodigoModalidadEfectiva', () => {
    it('prioriza la modalidad de la tarea vigente', () => {
        expect(resolverCodigoModalidadEfectiva({
            codigoModalidadPreparacion: 'RECOGE_TIENDA',
            tareaPreparacion: { modalidad: { codigo: 'ENVIO_BODEGA_NORMAL' } },
        })).toBe('ENVIO_BODEGA_NORMAL');
    });
});

describe('Q6 recorrido físico durante captura', () => {
    const cedis = { id: 1, codigo: 'CEDIS', sucursal_id: 1 };
    const pdv = { id: 2, codigo: 'PDV-1', tipo_almacen: { nombre: 'PDV' }, sucursal_id: 1 };
    it.each([cedis, pdv])('reconoce traslado sin ID desde $codigo', (almacen) => {
        expect(esTrasladoInternoPdv({ almacen_id: almacen.id, sucursal_destino_id: 2 }, almacen)).toBe(true);
    });
    it('mantiene como externo un envío municipal con sucursal asociada', () => {
        expect(esTrasladoInternoPdv({ almacen_id: 1, sucursal_destino_id: 2,
            solicitud_preparacion: { codigo_modalidad: 'ENVIO_MUNICIPIO' } }, cedis)).toBe(false);
    });
    it('la sucursal del almacén y la comercial no son un destino implícito', () => {
        expect(esTrasladoInternoPdv({ almacen_id: 2, sucursal_id: 2 }, pdv)).toBe(false);
        expect(esTrasladoInternoPdv({ almacen_id: 2, sucursal_destino_id: 1 }, pdv)).toBe(false);
    });
});
