import { describe, expect, it } from 'vitest';
import {
    TIPO_INCIDENCIA_DANO,
    TIPO_INCIDENCIA_FALTANTE,
    TIPO_INCIDENCIA_FOLIO,
    admiteRegistroIncidencia,
    admiteResolucionIncidencia,
    armarFormDataIncidencia,
    armarPayloadResolucion,
    esConflictoVersionIncidencia,
    exigeBultoIncidencia,
    exigeEvidenciaIncidencia,
    incidenciaEstaResuelta,
    incidenciasOrdenadasCronologicamente,
    mensajeErrorIncidencia,
    mensajeErrorResolucion,
    puedeRegistrarAlgunaIncidencia,
    puedeRegistrarTipo,
    puedeResolverIncidencia,
    validarFormularioIncidencia,
    validarFormularioResolucion,
} from './incidenciasResguardoUtils';

describe('incidenciasResguardoUtils', () => {
    const permisosCompletos = {
        incidencia_folio: true,
        incidencia_dano: true,
        incidencia_faltante: true,
        autorizar_incidencia: true,
    };

    it('exige evidencia solo para daño y faltante', () => {
        expect(exigeEvidenciaIncidencia(TIPO_INCIDENCIA_FOLIO)).toBe(false);
        expect(exigeEvidenciaIncidencia(TIPO_INCIDENCIA_DANO)).toBe(true);
        expect(exigeEvidenciaIncidencia(TIPO_INCIDENCIA_FALTANTE)).toBe(true);
    });

    it('exige bulto solo para daño', () => {
        expect(exigeBultoIncidencia(TIPO_INCIDENCIA_DANO)).toBe(true);
        expect(exigeBultoIncidencia(TIPO_INCIDENCIA_FALTANTE)).toBe(false);
    });

    it('valida formulario incompleto de incidencia', () => {
        const errores = validarFormularioIncidencia({
            tipo: TIPO_INCIDENCIA_FALTANTE,
            descripcion: '',
            evidencias: [],
        });

        expect(errores.descripcion).toBeTruthy();
        expect(errores.evidencias).toBeTruthy();
    });

    it('valida formulario válido de folio no encontrado', () => {
        const errores = validarFormularioIncidencia({
            tipo: TIPO_INCIDENCIA_FOLIO,
            descripcion: 'Folio escaneado no aparece',
            evidencias: [],
        });

        expect(errores).toEqual({});
    });

    it('valida formulario de daño con bulto y almacén', () => {
        const errores = validarFormularioIncidencia({
            tipo: TIPO_INCIDENCIA_DANO,
            descripcion: 'Caja golpeada',
            evidencias: [{}],
            bulto: { folio: 'CJA-1', tipo: 'caja', condicion: 'danado' },
            almacenId: 3,
        });

        expect(errores).toEqual({});
    });

    it('controla permisos de registro por tipo', () => {
        expect(puedeRegistrarTipo(permisosCompletos, TIPO_INCIDENCIA_DANO)).toBe(true);
        expect(puedeRegistrarTipo({ incidencia_dano: false }, TIPO_INCIDENCIA_DANO)).toBe(false);
        expect(puedeRegistrarAlgunaIncidencia(permisosCompletos, { estado: 'en_custodia' })).toBe(true);
        expect(puedeRegistrarAlgunaIncidencia(permisosCompletos, { estado: 'entregado' })).toBe(false);
    });

    it('controla permisos de resolución', () => {
        const incidenciaDano = { tipo: TIPO_INCIDENCIA_DANO, estado: 'abierta' };
        const incidenciaFolio = { tipo: TIPO_INCIDENCIA_FOLIO, estado: 'abierta' };

        expect(puedeResolverIncidencia(permisosCompletos, incidenciaDano)).toBe(true);
        expect(puedeResolverIncidencia({ autorizar_incidencia: false }, incidenciaDano)).toBe(false);
        expect(puedeResolverIncidencia({ incidencia_folio: true }, incidenciaFolio)).toBe(true);
        expect(puedeResolverIncidencia({ incidencia_folio: false }, incidenciaFolio)).toBe(false);
        expect(puedeResolverIncidencia(permisosCompletos, { ...incidenciaDano, estado: 'autorizada' })).toBe(false);
    });

    it('valida resolución incompleta y completa', () => {
        expect(validarFormularioResolucion({ motivoResolucion: '' }).motivo_resolucion).toBeTruthy();
        expect(validarFormularioResolucion({ motivoResolucion: 'Autorizado por gerencia' })).toEqual({});
    });

    it('arma FormData de incidencia con evidencias', () => {
        const archivo = new File(['x'], 'foto.jpg', { type: 'image/jpeg' });
        const form = armarFormDataIncidencia({
            version: 2,
            idempotencyKey: 'pdv:inc:1:test',
            tipo: TIPO_INCIDENCIA_FALTANTE,
            descripcion: 'Falta un bulto',
            evidencias: [archivo],
        });

        expect(form.get('version')).toBe('2');
        expect(form.get('tipo')).toBe(TIPO_INCIDENCIA_FALTANTE);
        expect(form.get('evidencias[0]')).toBe(archivo);
    });

    it('arma payload de resolución', () => {
        expect(armarPayloadResolucion({
            version: 3,
            incidenciaVersion: 2,
            idempotencyKey: 'pdv:inc-res:9:test',
            motivoResolucion: '  Autorizado  ',
        })).toEqual({
            version: 3,
            incidencia_version: 2,
            idempotency_key: 'pdv:inc-res:9:test',
            motivo_resolucion: 'Autorizado',
        });
    });

    it('detecta conflicto de versión', () => {
        expect(esConflictoVersionIncidencia({
            response: { data: { errors: { version: ['conflicto'] } } },
        })).toBe(true);
        expect(esConflictoVersionIncidencia({
            response: { data: { errors: { incidencia_version: ['conflicto'] } } },
        })).toBe(true);
        expect(esConflictoVersionIncidencia({
            response: { status: 500 },
        })).toBe(false);
    });

    it('ordena incidencias cronológicamente', () => {
        const ordenadas = incidenciasOrdenadasCronologicamente([
            { id: 1, reportado_at: '2026-01-01T10:00:00Z' },
            { id: 2, reportado_at: '2026-01-02T10:00:00Z' },
        ]);

        expect(ordenadas[0].id).toBe(2);
        expect(incidenciaEstaResuelta({ estado: 'autorizada' })).toBe(true);
        expect(admiteResolucionIncidencia({ estado: 'abierta' })).toBe(true);
        expect(admiteRegistroIncidencia({ estado: 'pendiente_recepcion' })).toBe(true);
    });

    it('genera mensajes de error legibles', () => {
        expect(mensajeErrorIncidencia({ response: { status: 403 } }))
            .toContain('permiso');
        expect(mensajeErrorResolucion({ response: { status: 403 } }))
            .toContain('permiso');
        expect(mensajeErrorIncidencia({
            response: {
                status: 422,
                data: { errors: { descripcion: ['Campo requerido'] } },
            },
        })).toBe('Campo requerido');
    });
});
