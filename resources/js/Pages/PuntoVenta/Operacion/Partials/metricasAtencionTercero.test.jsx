// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import MetricasAtencionCompacta from './MetricasAtencionCompacta';
import TarjetaTurnoVentas from '../../Turnos/Partials/TarjetaTurnoVentas';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;

vi.mock('axios', () => ({
    default: { post: vi.fn(), get: vi.fn(), isCancel: () => false },
}));

const servidorAt = '2026-10-31T17:55:00.000Z';

function montar(elemento) {
    const contenedor = document.createElement('div');
    document.body.appendChild(contenedor);
    const root = createRoot(contenedor);
    act(() => {
        root.render(elemento);
    });
    return contenedor;
}

afterEach(() => {
    document.body.innerHTML = '';
});

describe('nombre visible de un tercero', () => {
    it('en operación muestra quien entra, la cuenta y el distintivo', () => {
        const contenedor = montar(
            <MetricasAtencionCompacta
                prominente
                servidorAt={servidorAt}
                atencion={{
                    folio: 'V-9',
                    cliente: 'Persona que entra',
                    es_tercero: true,
                    cliente_titular: 'Cliente Titular',
                    atencion_en_curso: false,
                    espera_inicial_vencida: false,
                    espera_inicial_expira_at: '2026-10-31T18:00:00.000Z',
                }}
            />,
        );

        expect(contenedor.textContent).toContain('Persona que entra');
        expect(contenedor.textContent).toContain('Tercero');
        expect(contenedor.textContent).toContain('Cuenta: Cliente Titular');
    });

    it('un cliente titular no lleva distintivo de tercero', () => {
        const contenedor = montar(
            <MetricasAtencionCompacta
                prominente
                servidorAt={servidorAt}
                atencion={{
                    folio: 'V-2',
                    cliente: 'Cliente Titular',
                    es_tercero: false,
                    cliente_titular: null,
                    atencion_en_curso: true,
                    atencion_inicio_at: '2026-10-31T17:50:00.000Z',
                    prorroga_activa: false,
                }}
            />,
        );

        expect(contenedor.textContent).toContain('Cliente Titular');
        expect(contenedor.textContent).not.toContain('Tercero');
        expect(contenedor.textContent).not.toContain('Cuenta:');
    });

    it('en la atención de ventas marca al tercero junto a su nombre', () => {
        const contenedor = montar(
            <TarjetaTurnoVentas
                servidorAt={servidorAt}
                permisos={{}}
                catalogos={{ motivos_cierre: [] }}
                turno={{
                    id: 4,
                    folio: 'V-4',
                    version: 1,
                    snapshot_nombre_llamado: 'Persona que entra',
                    es_tercero: true,
                    cliente_titular: 'Cliente Titular',
                    servicio: 'ventas',
                    atencion: {
                        id: 8,
                        atencion_en_curso: false,
                        espera_inicial_vencida: false,
                        fin_at: null,
                    },
                }}
            />,
        );

        expect(contenedor.textContent).toContain('Persona que entra');
        expect(contenedor.textContent).toContain('Tercero');
        expect(contenedor.textContent).toContain('Cuenta: Cliente Titular');
    });
});
