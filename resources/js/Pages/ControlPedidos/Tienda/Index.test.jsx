// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Index from './Index';
const cargar = vi.hoisted(() => vi.fn());
vi.mock('@inertiajs/react', () => ({ Head: () => null, router: { visit: vi.fn() }, usePage: () => ({ props: { flash: {} } }) }));
vi.mock('../../../Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('../../../Components/GeliaPageShell', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('../../../Components/GeliaTituloCard', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('./Partials/FiltrosTienda', () => ({ default: () => null, TABS_TIENDA: [{ id: 'PENDIENTES', label: 'Pendientes' }] }));
vi.mock('./Partials/MetricasBandejaTienda', () => ({ default: () => null }));
vi.mock('./Partials/BandejasTienda', () => ({ default: () => null }));
vi.mock('../Partials/useListadoDiscreto', () => ({ default: ({ tareas, metricas }) => ({ tareas, metricas, cargando: false, cargar }) }));
vi.mock('./Partials/ListadoTienda', () => ({ default: ({ tareas, onAbrir }) => <button onClick={() => onAbrir(tareas.data[0], true)}>Atender prueba</button> }));
vi.mock('./Partials/ModalPreparacionTienda', () => ({ default: ({ tareaId, atender, onClose }) => <div role="dialog" data-id={tareaId} data-atender={String(atender)}><button onClick={onClose}>Cerrar prueba</button></div> }));
let root;
let container;
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement('div'); document.body.append(container); root = createRoot(container);
    cargar.mockReset();
});
afterEach(async () => { await act(() => root.unmount()); container.remove(); });
it('conecta Atender del listado con el modal y actualiza el listado al cerrar', async () => {
    await act(async () => root.render(<Index auth={{ user: { id: 10 } }} tareas={{ data: [{ id: 13 }], current_page: 1 }} />));
    await act(async () => container.querySelector('button').click());
    expect(container.querySelector('[role="dialog"]').getAttribute('data-id')).toBe('13');
    expect(container.querySelector('[role="dialog"]').getAttribute('data-atender')).toBe('true');
    await act(async () => container.querySelector('[role="dialog"] button').click());
    expect(container.querySelector('[role="dialog"]')).toBeNull();
    expect(cargar).toHaveBeenCalled();
});
