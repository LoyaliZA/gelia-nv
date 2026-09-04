import { describe, expect, it, beforeEach, vi } from 'vitest';
import {
    armarFormDataEntrega,
    claveIdempotenciaEntrega,
    dataUrlAFichero,
    limpiarClaveIdempotenciaEntrega,
    mensajeErrorEntrega,
    validarFormularioEntrega,
    validarPasoBultos,
    validarPasoReceptor,
    validarPasoEvidencia,
    esConflictoVersion,
    puedeAvanzarPaso,
    PASOS_ENTREGA,
} from './entregaResguardoUtils';

describe('entregaResguardoUtils', () => {
    const storage = new Map();
    let uuidSeq = 0;

    beforeEach(() => {
        storage.clear();
        uuidSeq = 0;
        vi.stubGlobal('sessionStorage', {
            getItem: (key) => storage.get(key) ?? null,
            setItem: (key, value) => storage.set(key, value),
            removeItem: (key) => storage.delete(key),
            clear: () => storage.clear(),
        });
        vi.stubGlobal('crypto', { randomUUID: () => `uuid-ent-${++uuidSeq}` });
    });

    it('define cinco pasos del flujo de entrega', () => {
        expect(PASOS_ENTREGA).toHaveLength(5);
        expect(PASOS_ENTREGA.map((p) => p.id)).toEqual([
            'localizar', 'revisar', 'receptor', 'evidencia', 'confirmar',
        ]);
    });

    it('valida receptor titular y tercero', () => {
        expect(validarPasoReceptor({ relacion: 'titular', nombreQuienRetira: 'Ana López' })).toEqual({});
        expect(validarPasoReceptor({ relacion: 'tercero', nombreQuienRetira: 'Persona autorizada' })).toEqual({});
        expect(validarPasoReceptor({ relacion: '', nombreQuienRetira: '' })).toMatchObject({
            relacion: expect.any(String),
            nombre_quien_retira: expect.any(String),
        });
    });

    it('exige firma en paso de evidencia', () => {
        expect(validarPasoEvidencia({ tieneFirma: false }).firma).toBeTruthy();
        expect(validarPasoEvidencia({ tieneFirma: true })).toEqual({});
    });

    it('valida formulario completo titular con firma', () => {
        const errores = validarFormularioEntrega({
            relacion: 'titular',
            nombreQuienRetira: 'Titular',
            tieneFirma: true,
            bultoIds: [1],
        });
        expect(errores).toEqual({});
        expect(validarPasoBultos({ bultoIds: [] }).bulto_ids).toBeTruthy();
    });

    it('bloquea avance de receptor sin nombre', () => {
        expect(puedeAvanzarPaso('receptor', { relacion: 'titular', nombreQuienRetira: '' })).toBe(false);
        expect(puedeAvanzarPaso('receptor', { relacion: 'tercero', nombreQuienRetira: 'Tercero' })).toBe(true);
    });

    it('reutiliza clave de idempotencia por resguardo en la sesión', () => {
        const a = claveIdempotenciaEntrega(12);
        const b = claveIdempotenciaEntrega(12);
        expect(a).toBe(b);
        expect(a).toContain('pdv:ent:12:');
        limpiarClaveIdempotenciaEntrega(12);
        const c = claveIdempotenciaEntrega(12);
        expect(c).not.toBe(a);
    });

    it('convierte dataUrl a fichero y arma FormData', () => {
        const dataUrl = 'data:image/png;base64,aGVsbG8=';
        const fichero = dataUrlAFichero(dataUrl);
        expect(fichero).toBeInstanceOf(File);
        expect(fichero.type).toBe('image/png');

        const form = armarFormDataEntrega({
            version: 2,
            idempotencyKey: 'pdv:ent:1:test',
            relacion: 'titular',
            nombreQuienRetira: 'Persona titular',
            metodoValidacion: 'firma',
            observaciones: 'Sin novedad',
            firma: fichero,
            evidencias: [],
            bultoIds: [11, 12],
        });

        expect(form.get('version')).toBe('2');
        expect(form.get('idempotency_key')).toBe('pdv:ent:1:test');
        expect(form.get('relacion')).toBe('titular');
        expect(form.get('nombre_quien_retira')).toBe('Persona titular');
        expect(form.get('metodo_validacion')).toBe('firma');
        expect(form.get('observaciones')).toBe('Sin novedad');
        expect(form.get('firma')).toBe(fichero);
        expect(form.get('bulto_ids[0]')).toBe('11');
        expect(form.get('bulto_ids[1]')).toBe('12');
    });

    it('traduce errores HTTP de concurrencia, conflicto y validación', () => {
        expect(mensajeErrorEntrega({ response: { status: 409, data: { message: 'Ya entregado' } } }))
            .toBe('Ya entregado');
        expect(mensajeErrorEntrega({
            response: {
                status: 422,
                data: { errors: { version: ['Otro usuario modificó este resguardo.'] } },
            },
        })).toBe('Otro usuario modificó este resguardo.');
        expect(mensajeErrorEntrega({ response: { status: 403 } }))
            .toContain('permiso');
        expect(esConflictoVersion({
            response: { data: { errors: { version: ['x'] } } },
        })).toBe(true);
    });
});
