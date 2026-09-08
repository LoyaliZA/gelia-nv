// @vitest-environment node
import { describe, expect, it } from 'vitest';
import {
    esEventoPersonalPdv,
    esEventoSucursalAudiblePdv,
    resolverModoAudioPdv,
} from './pdvAlertasAudiencia';

const prefs = {
    canales: { sonido: true, voz: true, web_push: true },
};

describe('pdvAlertasAudiencia', () => {
    it('separa evento personal del audible de sucursal', () => {
        const personal = {
            tipo: 'turno.asignado',
            audiencia: 'usuario',
            datos: { atencion: { user_id: 5 } },
        };
        const sucursal = { ...personal, audiencia: 'sucursal' };

        expect(esEventoPersonalPdv(personal, 5)).toBe(true);
        expect(esEventoSucursalAudiblePdv(sucursal, true)).toBe(true);
        expect(esEventoSucursalAudiblePdv(sucursal, false)).toBe(false);
    });

    it('deduplica audio entre canales para el mismo event_id', () => {
        const envelope = {
            event_id: 'evt-1',
            tipo: 'turno.asignado',
            audiencia: 'usuario',
            datos: {
                folio: 'V-1',
                snapshot_nombre_llamado: 'Cliente',
                atencion: { user_id: 3, primer_nombre: 'Luis' },
            },
        };

        const modo = resolverModoAudioPdv(envelope, {
            userId: 3,
            terminalActiva: true,
            prefsUsuario: prefs,
            silencioTerminal: false,
            audioYaAnunciado: false,
        });

        expect(modo).toBe('personal');

        expect(resolverModoAudioPdv(envelope, {
            userId: 3,
            terminalActiva: true,
            prefsUsuario: prefs,
            audioYaAnunciado: true,
        })).toBeNull();
    });

    it('no reproduce audio de sucursal sin terminal activa', () => {
        const envelope = {
            event_id: 'evt-2',
            tipo: 'turno.alta',
            audiencia: 'sucursal',
            datos: { folio: 'V-2' },
        };

        expect(resolverModoAudioPdv(envelope, {
            terminalActiva: false,
            prefsUsuario: prefs,
            audioYaAnunciado: false,
        })).toBeNull();

        expect(resolverModoAudioPdv(envelope, {
            terminalActiva: true,
            prefsUsuario: prefs,
            audioYaAnunciado: false,
        })).toBe('terminal');
    });
});
