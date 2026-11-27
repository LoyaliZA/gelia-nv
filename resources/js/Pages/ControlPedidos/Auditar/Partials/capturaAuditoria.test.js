import { describe, expect, it } from 'vitest';
import { hayCapturaPendienteAuditoria } from './capturaAuditoria';

describe('captura pendiente al cambiar de envío', () => {
    it('está limpia con la ficha igual al servidor y sin diálogos', () => {
        expect(hayCapturaPendienteAuditoria({
            numeroRemision: 'RM-1', numeroGuardado: 'RM-1',
            fechaEmision: '2026-11-27', fechaGuardada: '2026-11-27',
        })).toBe(false);
    });

    it('detecta número de remisión o fecha sin guardar', () => {
        expect(hayCapturaPendienteAuditoria({ numeroRemision: 'RM-2', numeroGuardado: 'RM-1' })).toBe(true);
        expect(hayCapturaPendienteAuditoria({ fechaEmision: '2026-11-28', fechaGuardada: '2026-11-27' })).toBe(true);
    });

    it('detecta un diálogo abierto salvo el de cambiar envío', () => {
        expect(hayCapturaPendienteAuditoria({ rechazoAbierto: true })).toBe(true);
        expect(hayCapturaPendienteAuditoria({ errorDatosAbierto: true })).toBe(true);
        expect(hayCapturaPendienteAuditoria({ confirmacion: { accion: 'aprobar' } })).toBe(true);
        expect(hayCapturaPendienteAuditoria({ confirmacion: { accion: 'cambiar_envio', destinoId: 2 } })).toBe(false);
    });
});
