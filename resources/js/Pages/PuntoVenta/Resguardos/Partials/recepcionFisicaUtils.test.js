import { describe, expect, it, beforeEach, vi } from 'vitest';
import {
    armarFormDataRecepcion,
    claveIdempotenciaRecepcion,
    crearBultosVacios,
    extraerFolioEscaneado,
    limpiarClaveIdempotenciaRecepcion,
    mensajeErrorRecepcion,
    validarFormularioRecepcion,
    esConflictoVersion,
} from './recepcionFisicaUtils';

describe('recepcionFisicaUtils', () => {
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
        vi.stubGlobal('crypto', { randomUUID: () => `uuid-test-${++uuidSeq}` });
    });

    it('extrae folio desde QR o URL', () => {
        expect(extraerFolioEscaneado('REM-123')).toBe('REM-123');
        expect(extraerFolioEscaneado('https://app/folio=REM-456')).toBe('REM-456');
    });

    it('crea filas de bultos según cantidad esperada', () => {
        const bultos = crearBultosVacios(2);
        expect(bultos).toHaveLength(2);
        expect(bultos[0]).toMatchObject({ tipo: 'caja', condicion: 'bueno', piezas: 1 });
    });

    it('valida campos obligatorios y cantidad exacta de bultos', () => {
        const errores = validarFormularioRecepcion({
            almacenId: null,
            cantidadEsperada: 1,
            bultos: [{ folio: '', tipo: 'caja', condicion: '', piezas: 0 }],
        });
        expect(errores.almacen_id).toBeTruthy();
        expect(errores['bultos.0.folio']).toBeTruthy();
        expect(errores['bultos.0.condicion']).toBeTruthy();
        expect(errores['bultos.0.piezas']).toBeTruthy();
    });

    it('reutiliza clave de idempotencia por resguardo en la sesión', () => {
        const a = claveIdempotenciaRecepcion(9);
        const b = claveIdempotenciaRecepcion(9);
        expect(a).toBe(b);
        expect(a).toContain('pdv:rec:9:');
        limpiarClaveIdempotenciaRecepcion(9);
        const c = claveIdempotenciaRecepcion(9);
        expect(c).not.toBe(a);
    });

    it('arma FormData con version, bultos y evidencias', () => {
        const archivo = new File(['x'], 'foto.jpg', { type: 'image/jpeg' });
        const form = armarFormDataRecepcion({
            version: 1,
            idempotencyKey: 'pdv:rec:1:test',
            almacenId: 3,
            bultos: [{ folio: 'CJA-1', tipo: 'caja', condicion: 'bueno', piezas: 2 }],
            evidencias: [archivo],
        });
        expect(form.get('version')).toBe('1');
        expect(form.get('idempotency_key')).toBe('pdv:rec:1:test');
        expect(form.get('bultos[0][folio]')).toBe('CJA-1');
        expect(form.get('bultos[0][piezas]')).toBe('2');
        expect(form.get('evidencias[0]')).toBe(archivo);
    });

    it('traduce errores HTTP de concurrencia y validación', () => {
        expect(mensajeErrorRecepcion({ response: { status: 409, data: { message: 'Ya recibido' } } }))
            .toBe('Ya recibido');
        expect(mensajeErrorRecepcion({
            response: {
                status: 422,
                data: { errors: { version: ['Otro usuario modificó este resguardo.'] } },
            },
        })).toBe('Otro usuario modificó este resguardo.');
        expect(esConflictoVersion({
            response: { data: { errors: { version: ['x'] } } },
        })).toBe(true);
    });
});
