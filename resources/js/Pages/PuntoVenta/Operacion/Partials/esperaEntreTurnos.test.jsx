// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';
import CronometroVisualOperacion from './CronometroVisualOperacion';
import TarjetaVendedorGerencia from './TarjetaVendedorGerencia';
import useEstadoOperacion from './useEstadoOperacion';

globalThis.IS_REACT_ACT_ENVIRONMENT = true;
vi.mock('axios', () => ({ default: { get: vi.fn() } }));

const servidorAt = '2026-10-16T16:00:00.000Z';
const referenciaAt = '2026-10-16T16:00:10.000Z';
const persona = {
    id: 4,
    nombre: 'Vendedor',
    estado_vendedor: 'en_espera',
    recibe_turnos: false,
    cronometro: {
        etiqueta: 'Espera para el siguiente turno',
        modo: 'restante',
        referencia_at: referenciaAt,
    },
};
let root;
let contenedor;

function montar(elemento) {
    act(() => root.render(elemento));
}

function Operacion({ estado }) {
    const operacion = useEstadoOperacion({ estado, refrescoMs: 30000 });
    return <TarjetaVendedorGerencia persona={operacion.estado.equipo[0]} servidorAt={operacion.ahoraServidor()} />;
}

beforeEach(() => {
    vi.useFakeTimers();
    // La computadora puede tener una hora distinta de la del servidor.
    vi.setSystemTime(new Date('2026-10-16T19:00:00.000Z'));
    vi.stubGlobal('route', (nombre) => nombre);
    contenedor = document.createElement('div');
    document.body.appendChild(contenedor);
    root = createRoot(contenedor);
});

afterEach(() => {
    act(() => root.unmount());
    contenedor.remove();
    vi.useRealTimers();
    vi.clearAllMocks();
    vi.unstubAllGlobals();
});

describe('cuenta regresiva entre turnos', () => {
    it('avanza cada segundo sin nuevas propiedades y no muestra cero antes del vencimiento', () => {
        montar(<CronometroVisualOperacion etiqueta="Espera" modo="restante" servidorAt={servidorAt} referenciaAt="2026-10-16T16:00:10.500Z" />);
        expect(contenedor.textContent).toContain('00:11');
        act(() => vi.advanceTimersByTime(1000));
        expect(contenedor.textContent).toContain('00:10');
        act(() => vi.advanceTimersByTime(9000));
        expect(contenedor.textContent).toContain('00:01');
        act(() => vi.advanceTimersByTime(1000));
        expect(contenedor.textContent).toContain('00:00');
    });

    it('sincroniza una nueva hora del servidor y conserva los cronómetros transcurridos', () => {
        montar(<CronometroVisualOperacion etiqueta="Disponible" servidorAt={servidorAt} referenciaAt="2026-10-16T15:59:00Z" />);
        act(() => vi.advanceTimersByTime(2000));
        expect(contenedor.textContent).toContain('01:02');
        expect(contenedor.textContent).not.toContain('00:01:02');
        montar(<CronometroVisualOperacion etiqueta="Espera" modo="restante" servidorAt="2026-10-16T16:00:05Z" referenciaAt={referenciaAt} />);
        expect(contenedor.textContent).toContain('00:05');
    });

    it('muestra horas solo cuando el tiempo llega a una hora', () => {
        montar(<CronometroVisualOperacion etiqueta="Atención" servidorAt={servidorAt} referenciaAt="2026-10-16T14:55:47Z" />);
        expect(contenedor.textContent).toContain('01:04:13');
        montar(<CronometroVisualOperacion etiqueta="Atención" servidorAt={servidorAt} referenciaAt="2026-10-16T15:00:01Z" />);
        expect(contenedor.textContent).toContain('59:59');
        expect(contenedor.textContent).not.toContain('00:59:59');
    });

    it('muestra la espera y consulta al vencer para reflejar disponibilidad sin esperar los 30 segundos', async () => {
        axios.get.mockResolvedValue({ data: {
            servidor_at: referenciaAt,
            equipo: [{ ...persona, estado_vendedor: 'disponible', recibe_turnos: true, cronometro: null }],
        } });
        montar(<Operacion estado={{ servidor_at: servidorAt, equipo: [persona] }} />);
        expect(contenedor.textContent).toContain('00:10');
        expect(contenedor.textContent).toContain('Volverá a la cola');
        expect(contenedor.textContent).not.toContain('Recibe turnos');
        expect(contenedor.textContent).not.toContain('Actividad:');
        await act(async () => vi.advanceTimersByTime(9000));
        expect(contenedor.textContent).toContain('00:01');
        expect(axios.get).not.toHaveBeenCalled();
        await act(async () => vi.advanceTimersByTime(1000));
        expect(axios.get).toHaveBeenCalledTimes(1);
        expect(contenedor.textContent).toContain('Disponible');
        expect(contenedor.textContent).not.toContain('Recibe turnos');
        expect(contenedor.textContent).not.toContain('Volverá a la cola');
    });

    it('agrupa esperas simultáneas y evita consultar cada segundo si falla la actualización', async () => {
        axios.get.mockRejectedValue(new Error('Sin conexión'));
        montar(<Operacion estado={{ servidor_at: servidorAt, equipo: [persona, { ...persona, id: 5 }] }} />);
        await act(async () => vi.advanceTimersByTime(10000));
        await act(async () => vi.advanceTimersByTime(5000));
        expect(axios.get).toHaveBeenCalledTimes(1);
    });
});
