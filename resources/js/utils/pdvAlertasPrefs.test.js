// @vitest-environment node
import { describe, expect, it } from 'vitest';
import {
    canalesEfectivosPdv,
    DEFAULT_PDV_ALERTAS_PREFS,
    debeAnunciarVozPdv,
    debeReproducirSonidoPdv,
    estadoWebPushPdv,
    mergePdvAlertasPrefsUsuario,
    mensajeFallbackWebPushPdv,
    PDV_PUSH_ESTADO,
} from './pdvAlertasPrefs';

const envelopeAsignado = {
    tipo: 'turno.asignado',
    datos: {
        folio: 'V-100',
        snapshot_nombre_llamado: 'Ana Pérez',
        atencion: { primer_nombre: 'Luis' },
    },
};

describe('pdvAlertasPrefs', () => {
    it('aplica defaults seguros sin configuración', () => {
        expect(mergePdvAlertasPrefsUsuario(null)).toEqual(DEFAULT_PDV_ALERTAS_PREFS);
        expect(mergePdvAlertasPrefsUsuario({ canales: { sonido: false } }).canales.sonido).toBe(false);
        expect(mergePdvAlertasPrefsUsuario({ canales: { voz: '0' } }).canales.voz).toBe(true);
    });

    it('silencio de terminal desactiva sonido y voz sin tocar push', () => {
        const efectivos = canalesEfectivosPdv(DEFAULT_PDV_ALERTAS_PREFS, true);
        expect(efectivos).toEqual({ sonido: false, voz: false, web_push: true });
    });

    it('solo reproduce sonido en eventos aprobados', () => {
        expect(debeReproducirSonidoPdv(envelopeAsignado, DEFAULT_PDV_ALERTAS_PREFS, false)).toBe(true);
        expect(debeReproducirSonidoPdv({ tipo: 'turno.alta' }, DEFAULT_PDV_ALERTAS_PREFS, false)).toBe(false);
        expect(debeReproducirSonidoPdv(envelopeAsignado, DEFAULT_PDV_ALERTAS_PREFS, true)).toBe(false);
    });

    it('respeta preferencia de voz del usuario y silencio de terminal', () => {
        const sinVoz = mergePdvAlertasPrefsUsuario({ canales: { voz: false } });
        expect(debeAnunciarVozPdv(envelopeAsignado, sinVoz, false)).toBe(false);
        expect(debeAnunciarVozPdv(envelopeAsignado, DEFAULT_PDV_ALERTAS_PREFS, true)).toBe(false);
        expect(debeAnunciarVozPdv(envelopeAsignado, DEFAULT_PDV_ALERTAS_PREFS, false)).toBe(true);
    });

    it('detecta estados de fallback de Web Push', () => {
        expect(estadoWebPushPdv(DEFAULT_PDV_ALERTAS_PREFS, { enabled: false }))
            .toBe(PDV_PUSH_ESTADO.servidor_deshabilitado);

        const deshabilitado = mergePdvAlertasPrefsUsuario({ canales: { web_push: false } });
        expect(estadoWebPushPdv(deshabilitado, { enabled: true, public_key: 'x' }))
            .toBe(PDV_PUSH_ESTADO.deshabilitado_usuario);

        expect(mensajeFallbackWebPushPdv(PDV_PUSH_ESTADO.denegado)).toContain('bloqueadas');
        expect(mensajeFallbackWebPushPdv(PDV_PUSH_ESTADO.activo)).toBeNull();
    });
});
