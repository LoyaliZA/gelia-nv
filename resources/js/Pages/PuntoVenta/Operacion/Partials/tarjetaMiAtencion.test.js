// @vitest-environment happy-dom
globalThis.IS_REACT_ACT_ENVIRONMENT = true;

import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { createElement } from 'react';
import { createRoot } from 'react-dom/client';
import { act } from 'react';
import TarjetaMiAtencion from './TarjetaMiAtencion';
import {
    assertSinOverflowHorizontal,
    buscarControlPorTexto,
    configurarViewportMovil,
    MOBILE_VIEWPORT_WIDTH,
} from '../../Resguardos/Partials/recepcionMovilViewport';

const SERVIDOR_AT = '2026-09-04T12:05:00Z';

const ESTADOS = [
    'no_activado',
    'disponible',
    'en_retencion',
    'atendiendo',
    'cierre_pendiente',
    'jornada_cerrada',
];

function montar(props = {}) {
    const contenedor = document.createElement('div');
    contenedor.setAttribute('data-mi-atencion-root', 'true');
    document.body.appendChild(contenedor);
    const root = createRoot(contenedor);
    act(() => {
        root.render(createElement(TarjetaMiAtencion, {
            nombre: 'Ana Vendedor',
            sucursal: 'Sucursal Centro',
            servidorAt: SERVIDOR_AT,
            ...props,
        }));
    });
    return contenedor;
}

describe('TarjetaMiAtencion', () => {
    beforeEach(() => {
        configurarViewportMovil();
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it.each(ESTADOS)('presenta el estado %s sin acciones gerenciales', (estadoVendedor) => {
        const contenedor = montar({
            estado: {
                estado_vendedor: estadoVendedor,
                cronometro: estadoVendedor === 'en_retencion'
                    ? { etiqueta: 'Tiempo en pausa', referencia_at: '2026-09-04T12:00:00Z', modo: 'transcurrido' }
                    : null,
            },
            turnoAsignado: estadoVendedor === 'atendiendo'
                ? { id: 1, folio: 'A-001', servicio: 'Ventas' }
                : null,
        });

        expect(contenedor.textContent).toContain('Ana Vendedor');
        expect(contenedor.textContent).toContain('Sucursal Centro');
        expect(contenedor.textContent).toContain('Última actualización');

        const accionesProhibidas = [
            'Abrir jornada',
            'Cerrar jornada',
            'Iniciar pausa',
            'Finalizar pausa',
        ];

        accionesProhibidas.forEach((texto) => {
            expect(buscarControlPorTexto(contenedor, texto)).toBeFalsy();
        });
    });

    it('usa pausa en lugar de retención', () => {
        const contenedor = montar({
            estado: {
                estado_vendedor: 'en_retencion',
                cronometro: {
                    etiqueta: 'Tiempo en pausa',
                    referencia_at: '2026-09-04T12:00:00Z',
                    modo: 'transcurrido',
                },
            },
        });

        expect(contenedor.textContent).toContain('En pausa');
        expect(contenedor.textContent).toContain('Pausa activa');
        expect(contenedor.textContent).not.toMatch(/retenci[oó]n/i);
        expect(contenedor.textContent).toContain('Tiempo en pausa');
    });

    it('muestra motivo de pausa cuando existe', () => {
        const contenedor = montar({
            estado: {
                estado_vendedor: 'en_retencion',
                pausa_motivo: 'Comida',
            },
        });

        expect(contenedor.textContent).toContain('Motivo: Comida');
    });

    it('estado desconocido cae en presentación segura sin botones', () => {
        const contenedor = montar({
            estado: { estado_vendedor: 'estado_fantasma' },
        });

        expect(contenedor.textContent).toContain('Estado desconocido');
        expect(contenedor.textContent).toContain('Estado no disponible');
        expect(contenedor.querySelector('button')).toBeNull();
    });

    it('mantiene layout responsive sin overflow horizontal', () => {
        const contenedor = montar({
            estado: { estado_vendedor: 'disponible' },
        });

        assertSinOverflowHorizontal(contenedor, MOBILE_VIEWPORT_WIDTH);
        expect(contenedor.querySelector('button')).toBeNull();
    });
});
