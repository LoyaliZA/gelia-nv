// @vitest-environment happy-dom
import React, { act, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import SelectorFecha from './SelectorFecha';
import { fechaCalendario } from '../../utils/fechaCalendario';
import { fechaInicialRemision } from '../../Pages/ControlPedidos/Auditar/Partials/remisionVigente';

let root, container;
const click = async selector => act(() => container.querySelector(selector).click());
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    container = document.createElement('div'); document.body.append(container); root = createRoot(container);
});
afterEach(async () => { await act(() => root.unmount()); container.remove(); vi.useRealTimers(); });
function Campo({ inicial = '2028-02-29', ...props }) {
    const [value, onChange] = useState(inicial);
    return <SelectorFecha value={value} onChange={onChange} aria-label="Emisión" {...props} />;
}
it('precarga el día del pedido en México y conserva una emisión registrada', () => {
    expect(fechaInicialRemision({ fecha: '2026-11-03T00:00:00.000000Z', created_at: '2026-11-06T03:30:00Z' })).toBe('2026-11-03');
    expect(fechaInicialRemision({ created_at: '2026-11-06T03:30:00Z' })).toBe('2026-11-05');
    expect(fechaInicialRemision({ created_at: '2026-11-06T15:00:00Z', solicitud_preparacion: { remision_tienda: { emitida_at: '2026-11-04T00:00:00-06:00' } } })).toBe('2026-11-04');
    expect(fechaInicialRemision({})).toBe('');
    expect(fechaCalendario('2026-11-06')).toBe('2026-11-06');
});
it('navega febrero bisiesto, selecciona con teclado y devuelve el foco', async () => {
    await act(() => root.render(<Campo />));
    await click('[aria-label="Emisión"]');
    expect(container.querySelectorAll('.gelia-calendario-dia')).toHaveLength(29);
    await act(() => document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight', bubbles: true })));
    expect(document.activeElement.getAttribute('aria-label')).toBe('1 de marzo de 2028');
    await act(() => document.activeElement.click());
    expect(container.querySelector('.gelia-calendario')).toBeNull();
    expect(container.querySelector('[aria-label="Emisión"]').textContent).toContain('1 de marzo de 2028');
    expect(document.activeElement).toBe(container.querySelector('[aria-label="Emisión"]'));
});
it('limita el día al cambiar de mes y cruza año sin perder la selección', async () => {
    await act(() => root.render(<Campo inicial="2026-01-31" />));
    await click('[aria-label="Emisión"]');
    await click('[aria-label="Mes siguiente"]');
    expect(container.querySelector('[role="grid"] [tabindex="0"]').textContent).toBe('28');
    expect(container.querySelector('[aria-label="Emisión"]').textContent).toContain('31 de enero');
    await act(() => document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'PageUp', shiftKey: true, bubbles: true })));
    expect(container.querySelector('[role="grid"]').getAttribute('aria-label')).toBe('febrero 2025');
});
it('Hoy usa el día de México incluso cuando UTC ya es el siguiente', async () => {
    vi.useFakeTimers(); vi.setSystemTime(new Date('2026-11-07T02:00:00Z'));
    await act(() => root.render(<Campo />));
    await click('.gelia-selector-fecha-hoy');
    expect(container.querySelector('[aria-label="Emisión"]').textContent).toContain('6 de noviembre de 2026');
});
it('Escape cierra únicamente el calendario y el estado bloqueado impide cambiar fecha', async () => {
    const modal = vi.fn(); document.addEventListener('keydown', modal, true);
    try {
        await act(() => root.render(<Campo />));
        await click('[aria-label="Emisión"]');
        await act(() => document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true })));
        expect(modal).not.toHaveBeenCalled();
        expect(container.querySelector('.gelia-calendario')).toBeNull();
        await click('[aria-label="Emisión"]');
        await act(() => root.render(<Campo disabled />));
        expect(container.querySelector('.gelia-calendario')).toBeNull();
        expect([...container.querySelectorAll('button')].every(b => b.disabled)).toBe(true);
    } finally { document.removeEventListener('keydown', modal, true); }
});
