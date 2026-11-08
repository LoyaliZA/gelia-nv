// @vitest-environment happy-dom
import React, { act, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import { BancosPedidos, PrioridadesPedidos } from './PoliticasPedidos';
let container, root;
beforeEach(() => { globalThis.IS_REACT_ACT_ENVIRONMENT = true; container = document.createElement('div'); document.body.append(container); root = createRoot(container); });
afterEach(async () => { await act(() => root.unmount()); container.remove(); });
it('reordena prioridades con botones y comunica el orden accesible', async () => {
    const guardar = vi.fn();
    function Pantalla() {
        const [orden, setOrden] = useState(['md', 'municipal', 'tienda']);
        return <PrioridadesPedidos value={orden} tipos={{ md: 'Mismo día', municipal: 'Municipales', tienda: 'Recoge en tienda' }} onChange={nuevo => { setOrden(nuevo); guardar(nuevo); }} />;
    }
    await act(() => root.render(<Pantalla />));
    expect(container.querySelector('[aria-label="Subir Mismo día"]').disabled).toBe(true);
    await act(() => container.querySelector('[aria-label="Subir Municipales"]').click());
    expect(guardar).toHaveBeenLastCalledWith(['municipal', 'md', 'tienda']);
    expect(container.querySelector('[role="status"]').textContent).toContain('1. Municipales');
    expect(container.querySelector('[aria-label="Bajar Recoge en tienda"]').disabled).toBe(true);
});
it('habilita el banco y selecciona departamentos conservando errores y estado de envío', async () => {
    const cambios = vi.fn();
    function Pantalla() {
        const [bancos, setBancos] = useState([{ id: 2, habilitado: false, compartido: false, departamento_ids: [] }]);
        return <BancosPedidos bancos={bancos} catalogo={[{ id: 2, nombre: 'Cuenta de prueba', activo: true }]} departamentos={[{ id: 3, nombre: 'Bellaroma' }, { id: 4, nombre: 'Aromas' }]} onChange={nuevo => { setBancos(nuevo); cambios(nuevo); }} errors={{ 'bancos.0.departamento_ids': 'Seleccione un departamento' }} />;
    }
    await act(() => root.render(<Pantalla />));
    expect(container.textContent).not.toContain('Bellaroma');
    await act(() => container.querySelector('input').click());
    expect(container.textContent).toContain('Bellaroma');
    await act(() => [...container.querySelectorAll('label')].find(label => label.textContent.includes('Bellaroma')).querySelector('input').click());
    expect(cambios.mock.lastCall[0][0]).toMatchObject({ habilitado: true, departamento_ids: [3] });
    expect(container.querySelector('[role="alert"]').textContent).toBe('Seleccione un departamento');
    await act(() => root.render(<BancosPedidos bancos={cambios.mock.lastCall[0]} catalogo={[]} departamentos={[]} onChange={cambios} disabled />));
    expect(container.querySelector('fieldset').disabled).toBe(true);
});
