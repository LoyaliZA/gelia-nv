import { describe, expect, it, beforeEach, vi } from 'vitest';
import {
    armarFormDataRecepcion,
    cantidadBultosPendiente,
    cantidadBultosRecibida,
    claveIdempotenciaRecepcion,
    crearBultosVacios,
    esRecepcionComplementaria,
    extraerFolioEscaneado,
    foliosBultosRecibidos,
    limpiarClaveIdempotenciaRecepcion,
    mensajeConfirmacionRecepcion,
    mensajeErrorRecepcion,
    resguardoAdmiteEntregaTotal,
    resguardoAdmiteRecepcion,
    validarFormularioRecepcion,
    esConflictoVersion,
} from './recepcionFisicaUtils';

describe('recepcionFisicaUtils', () => {
    const storage = new Map();
    let uuidSeq = 0;

    const resguardoBase = {
        id: 1,
        estado: 'pendiente_recepcion',
        cantidad_bultos_esperada: 3,
        cantidad_bultos_recibida: 1,
        cantidad_bultos_pendiente: 2,
        recepcion_completa: false,
        bultos_recibidos: [{ id: 10, folio: 'CJA-001', tipo: 'caja', recepcion_at: '2026-09-02T10:00:00Z' }],
    };

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
        expect(extraerFolioEscaneado('https://app/punto-venta/resguardos/etiquetas/resolver/Ab3CdEfGhJkL')).toBe('Ab3CdEfGhJkL');
        expect(extraerFolioEscaneado('codigo=Ab3CdEfGhJkL')).toBe('Ab3CdEfGhJkL');
    });

    it('crea filas de bultos según cantidad esperada', () => {
        const bultos = crearBultosVacios(2);
        expect(bultos).toHaveLength(2);
        expect(bultos[0]).toMatchObject({ tipo: 'caja', condicion: 'bueno', piezas: 1 });
    });

    it('lee cantidades desde backend sin recalcular cuando vienen serializadas', () => {
        expect(cantidadBultosRecibida(resguardoBase)).toBe(1);
        expect(cantidadBultosPendiente(resguardoBase)).toBe(2);
        expect(esRecepcionComplementaria(resguardoBase)).toBe(true);
        expect(foliosBultosRecibidos(resguardoBase)).toEqual(['CJA-001']);
    });

    it('valida llegada parcial entre 1 y pendiente', () => {
        const erroresVacios = validarFormularioRecepcion({
            almacenId: 1,
            cantidadPendiente: 2,
            bultos: [],
        });
        expect(erroresVacios.bultos).toBeTruthy();

        const erroresExceso = validarFormularioRecepcion({
            almacenId: 1,
            cantidadPendiente: 2,
            bultos: crearBultosVacios(3),
        });
        expect(erroresExceso.bultos).toContain('Solo faltan 2 bulto(s)');

        const erroresOk = validarFormularioRecepcion({
            almacenId: 1,
            cantidadPendiente: 2,
            bultos: [{ folio: 'CJA-002', tipo: 'caja', condicion: 'bueno', piezas: 1 }],
            foliosRecibidos: ['CJA-001'],
        });
        expect(erroresOk).toEqual({});
    });

    it('rechaza folio duplicado de llegada anterior', () => {
        const errores = validarFormularioRecepcion({
            almacenId: 1,
            cantidadPendiente: 2,
            bultos: [{ folio: 'CJA-001', tipo: 'caja', condicion: 'bueno', piezas: 1 }],
            foliosRecibidos: ['CJA-001'],
        });
        expect(errores['bultos.0.folio']).toContain('llegada anterior');
    });

    it('genera mensajes de confirmación para parcial y complemento final', () => {
        expect(mensajeConfirmacionRecepcion({
            cantidadLlegada: 1,
            cantidadPendiente: 3,
            esComplementaria: false,
        }).titulo).toBe('Confirmar llegada parcial');

        expect(mensajeConfirmacionRecepcion({
            cantidadLlegada: 2,
            cantidadPendiente: 2,
            esComplementaria: true,
        }).titulo).toBe('Confirmar llegada final');
    });

    it('usa flags backend para admitir recepción y entrega total', () => {
        expect(resguardoAdmiteRecepcion(resguardoBase, true)).toBe(true);
        expect(resguardoAdmiteRecepcion({ ...resguardoBase, recepcion_completa: true }, false)).toBe(false);
        expect(resguardoAdmiteEntregaTotal({ recepcion_completa: true })).toBe(true);
        expect(resguardoAdmiteEntregaTotal(resguardoBase)).toBe(false);
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

    it('traduce errores HTTP de concurrencia, exceso y validación', () => {
        expect(mensajeErrorRecepcion({ response: { status: 409, data: { message: 'Ya completo' } } }))
            .toBe('Ya completo');
        expect(mensajeErrorRecepcion({
            response: {
                status: 422,
                data: { errors: { bultos: ['Solo faltan 1 bulto(s) por recibir.'] } },
            },
        })).toBe('Solo faltan 1 bulto(s) por recibir.');
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
