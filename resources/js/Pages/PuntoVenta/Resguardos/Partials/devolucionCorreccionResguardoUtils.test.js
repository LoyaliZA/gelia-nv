import { describe, expect, it } from 'vitest';
import {
    TIPO_CORRECCION_ANOTACION,
    TIPO_CORRECCION_SNAPSHOT,
    admiteDevolucionResguardo,
    armarFormDataCorreccion,
    armarFormDataDevolucion,
    esConflictoVersionExcepcion,
    eventosReferenciaDisponibles,
    mensajeErrorCorreccion,
    mensajeErrorDevolucion,
    puedeAlgunaExcepcion,
    puedeConfirmarDevolucion,
    puedeCorregirResguardo,
    resumenImpactoCorreccion,
    resumenImpactoDevolucion,
    validarFormularioCorreccion,
    validarFormularioDevolucion,
} from './devolucionCorreccionResguardoUtils';

describe('devolucionCorreccionResguardoUtils', () => {
    const resguardoEnCustodia = {
        id: 1,
        estado: 'en_custodia',
        snapshot_folio: 'FOLIO-ACTUAL',
        snapshot_cliente_nombre: 'Cliente Actual',
        referencia_cliente: 'Cliente Actual',
        bultos: [
            { id: 10, folio: 'B-1', estado: 'recibido' },
            { id: 11, folio: 'B-2', estado: 'pendiente' },
        ],
    };

    const permisosCompletos = {
        confirmar_devolucion: true,
        corregir: true,
    };

    it('controla visibilidad por permiso y estado', () => {
        expect(puedeConfirmarDevolucion(permisosCompletos, resguardoEnCustodia)).toBe(true);
        expect(puedeConfirmarDevolucion({ confirmar_devolucion: false }, resguardoEnCustodia)).toBe(false);
        expect(puedeConfirmarDevolucion(permisosCompletos, { ...resguardoEnCustodia, estado: 'entregado' })).toBe(false);
        expect(puedeCorregirResguardo(permisosCompletos)).toBe(true);
        expect(puedeCorregirResguardo({ corregir: false })).toBe(false);
        expect(puedeAlgunaExcepcion(permisosCompletos, resguardoEnCustodia)).toBe(true);
        expect(puedeAlgunaExcepcion({ corregir: true }, { ...resguardoEnCustodia, bultos: [] })).toBe(true);
    });

    it('admite devolución solo con bultos recibidos en custodia', () => {
        expect(admiteDevolucionResguardo(resguardoEnCustodia)).toBe(true);
        expect(admiteDevolucionResguardo({ ...resguardoEnCustodia, bultos: [] })).toBe(false);
        expect(admiteDevolucionResguardo({
            ...resguardoEnCustodia,
            bultos: [{ id: 11, estado: 'pendiente' }],
        })).toBe(false);
    });

    it('valida motivo obligatorio en devolución', () => {
        expect(validarFormularioDevolucion({ motivo: '' })).toHaveProperty('motivo');
        expect(validarFormularioDevolucion({ motivo: 'Cliente no recogió' })).toEqual({});
    });

    it('valida corrección snapshot con al menos un cambio', () => {
        const erroresVacios = validarFormularioCorreccion({
            tipoCorreccion: TIPO_CORRECCION_SNAPSHOT,
            motivo: 'Error de captura',
            snapshotFolio: 'FOLIO-ACTUAL',
            snapshotClienteNombre: '',
            resguardo: resguardoEnCustodia,
        });
        expect(erroresVacios.correccion).toBeTruthy();

        const erroresValidos = validarFormularioCorreccion({
            tipoCorreccion: TIPO_CORRECCION_SNAPSHOT,
            motivo: 'Error de captura',
            snapshotFolio: 'FOLIO-NUEVO',
            snapshotClienteNombre: '',
            resguardo: resguardoEnCustodia,
        });
        expect(erroresValidos).toEqual({});
    });

    it('valida anotación con evento de referencia', () => {
        const errores = validarFormularioCorreccion({
            tipoCorreccion: TIPO_CORRECCION_ANOTACION,
            motivo: 'Aclaración',
            eventoReferenciaId: null,
            resguardo: resguardoEnCustodia,
        });
        expect(errores.evento_referencia_id).toBeTruthy();

        const validos = validarFormularioCorreccion({
            tipoCorreccion: TIPO_CORRECCION_ANOTACION,
            motivo: 'Aclaración',
            eventoReferenciaId: 5,
            resguardo: resguardoEnCustodia,
        });
        expect(validos).toEqual({});
    });

    it('resume impacto de devolución y corrección', () => {
        const devolucion = resumenImpactoDevolucion(resguardoEnCustodia);
        expect(devolucion.cantidadBultos).toBe(1);
        expect(devolucion.estadoNuevo).toBe('devuelto');

        const correccion = resumenImpactoCorreccion({
            tipoCorreccion: TIPO_CORRECCION_SNAPSHOT,
            resguardo: resguardoEnCustodia,
            snapshotFolio: 'FOLIO-NUEVO',
            snapshotClienteNombre: '',
        });
        expect(correccion.cambios).toHaveLength(1);
        expect(correccion.cambios[0].nuevo).toBe('FOLIO-NUEVO');
    });

    it('arma FormData con campos requeridos', () => {
        const devolucion = armarFormDataDevolucion({
            version: 3,
            idempotencyKey: 'key-dev',
            motivo: 'Salida física',
            evidencias: [],
        });
        expect(devolucion.get('version')).toBe('3');
        expect(devolucion.get('motivo')).toBe('Salida física');

        const correccion = armarFormDataCorreccion({
            version: 4,
            idempotencyKey: 'key-corr',
            tipoCorreccion: TIPO_CORRECCION_SNAPSHOT,
            motivo: 'Ajuste folio',
            snapshotFolio: 'FOLIO-NUEVO',
            snapshotClienteNombre: '',
            resguardo: resguardoEnCustodia,
            evidencias: [],
        });
        expect(correccion.get('tipo_correccion')).toBe(TIPO_CORRECCION_SNAPSHOT);
        expect(correccion.get('snapshot_folio')).toBe('FOLIO-NUEVO');
    });

    it('mapea eventos de referencia desde timeline', () => {
        const eventos = eventosReferenciaDisponibles([
            { id: 1, tipo_etiqueta: 'Recepción completa', ocurrido_at: '2026-01-01T10:00:00Z' },
        ]);
        expect(eventos).toHaveLength(1);
        expect(eventos[0].etiqueta).toBe('Recepción completa');
    });

    it('interpreta errores 403, 409 y conflicto de versión', () => {
        expect(mensajeErrorDevolucion({ response: { status: 403 } })).toMatch(/permiso/i);
        expect(mensajeErrorDevolucion({ response: { status: 409, data: { message: 'Ya devuelto' } } })).toBe('Ya devuelto');
        expect(mensajeErrorCorreccion({ response: { status: 422, data: { errors: { motivo: ['Requerido'] } } } })).toBe('Requerido');
        expect(esConflictoVersionExcepcion({ response: { data: { errors: { version: ['Obsoleto'] } } } })).toBe(true);
        expect(esConflictoVersionExcepcion({ response: { status: 500 } })).toBe(false);
    });
});
