import { describe, expect, it, beforeEach, vi } from 'vitest';
import { compressImageToWebp } from '../../../../utils/compressImage';
import {
    armarFormDataEntrega,
    claveIdempotenciaEntrega,
    comprimirEvidenciasEntrega,
    dataUrlAFichero,
    etiquetaProgresoEntrega,
    EVIDENCIA_ENTREGA_MAX_BYTES,
    EVIDENCIA_ENTREGA_MAX_DIMENSION,
    limpiarClaveIdempotenciaEntrega,
    MENSAJE_FOTO_ENTREGA_GRANDE,
    mensajeErrorEntrega,
    validarFormularioEntrega,
    validarPasoBultos,
    validarPasoReceptor,
    validarPasoEvidencia,
    esConflictoVersion,
    puedeAvanzarPaso,
    PASOS_ENTREGA,
    FIRMA_ENTREGA_ANCHO_NORMALIZADO,
    FIRMA_ENTREGA_ALTO_NORMALIZADO,
} from './entregaResguardoUtils';

vi.mock('../../../../utils/compressImage', () => ({
    compressImageToWebp: vi.fn(),
}));

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

    it('define dimensiones normalizadas de firma para el panel de entrega', () => {
        expect(FIRMA_ENTREGA_ANCHO_NORMALIZADO).toBe(600);
        expect(FIRMA_ENTREGA_ALTO_NORMALIZADO).toBe(200);
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

    it('nombra la espera de fotos y el guardado sin inventar un porcentaje', () => {
        expect(etiquetaProgresoEntrega({ preparando: true, progreso: 0 })).toBe('Preparando fotos…');
        expect(etiquetaProgresoEntrega({ progreso: 40 })).toBe('Registrando entrega… 40%');
        expect(etiquetaProgresoEntrega({ progreso: 100 })).toBe('Guardando entrega…');
    });

    it('sustituye la foto grande por la versión comprimida antes del FormData', async () => {
        const grande = new File([new Uint8Array(8)], 'camara.jpg', { type: 'image/jpeg' });
        Object.defineProperty(grande, 'size', { value: 2_000_000 });
        const comprimida = new File(['webp'], 'camara.webp', { type: 'image/webp' });
        compressImageToWebp.mockResolvedValueOnce(comprimida);

        const listas = await comprimirEvidenciasEntrega([grande]);

        expect(listas).toEqual([comprimida]);
        expect(compressImageToWebp).toHaveBeenCalledWith(grande, {
            maxDimension: EVIDENCIA_ENTREGA_MAX_DIMENSION,
            maxBytes: EVIDENCIA_ENTREGA_MAX_BYTES,
        });

        const form = armarFormDataEntrega({
            version: 1,
            idempotencyKey: 'pdv:ent:1:foto',
            relacion: 'titular',
            nombreQuienRetira: 'Persona titular',
            metodoValidacion: 'firma',
            firma: new File(['firma'], 'firma.png', { type: 'image/png' }),
            evidencias: listas,
            bultoIds: [1],
        });
        expect(form.get('evidencias[0]')).toBe(comprimida);
        expect(form.get('evidencias[0]').size).toBeLessThan(grande.size);
    });

    it('conserva el original pequeño si el navegador no puede comprimir', async () => {
        compressImageToWebp.mockRejectedValueOnce(new Error('CANVAS_BLOB_FAILED'));
        const chica = new File(['abc'], 'ok.jpg', { type: 'image/jpeg' });

        await expect(comprimirEvidenciasEntrega([chica])).resolves.toEqual([chica]);
    });

    it('no envía la foto si sigue grande después de fallar la compresión', async () => {
        compressImageToWebp.mockRejectedValueOnce(new Error('COMPRESS_TOO_LARGE'));
        const grande = new File([new Uint8Array(8)], 'camara.jpg', { type: 'image/jpeg' });
        Object.defineProperty(grande, 'size', { value: 2_000_000 });

        await expect(comprimirEvidenciasEntrega([grande])).rejects.toThrow(MENSAJE_FOTO_ENTREGA_GRANDE);
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
