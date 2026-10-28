// @vitest-environment node
import { describe, expect, it } from 'vitest';
import {
    anuncioTerminalEnNavegadorPdv,
    esEventoPersonalPdv,
    esEventoSucursalAudiblePdv,
    esLlamadoPropioEnSucursalPdv,
    resolverModoAudioPdv,
    terminalSucursalPuedeAnunciar,
} from './pdvAlertasAudiencia';
import { convieneReclamarAudioPersonalPdv } from './pdvAlertasPrefs';

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

    const datosLlamado = {
        folio: 'V-9',
        snapshot_nombre_llamado: 'Cliente',
        atencion: { user_id: 3, primer_nombre: 'Luis' },
    };

    it.each([
        'turno.asignado',
        'turno.reatencion',
        'turno.transferido',
        'atencion.prorroga',
    ])('delega %s al puesto y no lo anuncia como terminal', (tipo) => {
        const personal = {
            event_id: 'evt-personal',
            tipo,
            audiencia: 'usuario',
            datos: datosLlamado,
        };

        expect(convieneReclamarAudioPersonalPdv(personal, prefs, false, 3)).toBe(true);
        expect(anuncioTerminalEnNavegadorPdv(personal, {
            userId: 3,
            terminalActiva: true,
            prefsUsuario: prefs,
        })).toBeNull();
    });

    it('no repite el llamado de sucursal en el navegador de quien atiende', () => {
        const sucursal = {
            event_id: 'evt-mismo',
            tipo: 'turno.asignado',
            audiencia: 'sucursal',
            datos: datosLlamado,
        };
        const usuario = { ...sucursal, audiencia: 'usuario' };
        const opciones = {
            userId: 3,
            terminalActiva: true,
            prefsUsuario: prefs,
            audioYaAnunciado: false,
        };

        expect(esLlamadoPropioEnSucursalPdv(sucursal, 3)).toBe(true);
        expect(anuncioTerminalEnNavegadorPdv(usuario, opciones)).toBeNull();
        expect(anuncioTerminalEnNavegadorPdv(sucursal, opciones)).toBeNull();
        expect(anuncioTerminalEnNavegadorPdv(sucursal, opciones)).toBeNull();
        expect(convieneReclamarAudioPersonalPdv(usuario, prefs, false, 3)).toBe(true);
    });

    it('anuncia la sucursal del llamado cuando el asignado es otra persona', () => {
        const sucursal = {
            event_id: 'evt-otro',
            tipo: 'turno.transferido',
            audiencia: 'sucursal',
            datos: datosLlamado,
        };

        expect(anuncioTerminalEnNavegadorPdv(sucursal, {
            userId: 8,
            terminalActiva: true,
            prefsUsuario: prefs,
        })).toBe('terminal');
    });

    it.each(['pausa.iniciada', 'pausa.finalizada'])(
        'anuncia %s solo si esta terminal puede hablar por la sucursal',
        (tipo) => {
            const envelope = {
                event_id: 'evt-pausa',
                tipo,
                audiencia: 'sucursal',
                datos: { primer_nombre: 'Ana' },
            };
            const base = { userId: 3, prefsUsuario: prefs, audioYaAnunciado: false };

            expect(anuncioTerminalEnNavegadorPdv(envelope, { ...base, terminalActiva: false })).toBeNull();
            expect(anuncioTerminalEnNavegadorPdv(envelope, { ...base, terminalActiva: true })).toBe('terminal');
            expect(anuncioTerminalEnNavegadorPdv(envelope, {
                ...base,
                terminalActiva: true,
                audioYaAnunciado: true,
            })).toBeNull();
        },
    );

    it('la terminal general anuncia solo si no hay designación de alertas', () => {
        expect(terminalSucursalPuedeAnunciar({
            generalLocalLider: true,
            estadoAlertas: 'disponible',
        })).toBe(true);

        expect(terminalSucursalPuedeAnunciar({
            alertasLocalLider: false,
            generalLocalLider: true,
            estadoAlertas: 'otra_terminal_activa',
        })).toBe(false);

        expect(terminalSucursalPuedeAnunciar({
            alertasLocalLider: true,
            generalLocalLider: true,
            estadoAlertas: 'terminal_activa',
        })).toBe(true);

        expect(terminalSucursalPuedeAnunciar({
            alertasLocalLider: false,
            generalLocalLider: true,
            estadoAlertas: 'no_autorizada',
            designacionAlertasVigente: true,
        })).toBe(false);
    });

    it('no toma audio de sala ni cambia el camino del resguardo', () => {
        const opciones = {
            userId: 3,
            terminalActiva: true,
            prefsUsuario: prefs,
        };

        expect(anuncioTerminalEnNavegadorPdv({
            tipo: 'turno.asignado',
            audiencia: 'publico',
            datos: datosLlamado,
        }, opciones)).toBeNull();

        expect(anuncioTerminalEnNavegadorPdv({
            tipo: 'resguardo.recepcion_esperada_creada',
            audiencia: 'sucursal',
            datos: { folio: 'R-10' },
        }, opciones)).toBe('terminal');
    });

    it('no reclama el audio personal si no va a sonar', () => {
        const personal = {
            tipo: 'turno.asignado',
            audiencia: 'usuario',
            datos: datosLlamado,
        };

        expect(convieneReclamarAudioPersonalPdv(personal, prefs, true, 3)).toBe(false);
        expect(convieneReclamarAudioPersonalPdv(personal, prefs, false, 9)).toBe(false);
    });
});
