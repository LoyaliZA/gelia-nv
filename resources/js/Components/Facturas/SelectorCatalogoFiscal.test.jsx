// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import SelectorCatalogoFiscal from './SelectorCatalogoFiscal';
import SolicitudDialog from '@/Components/Solicitudes/SolicitudDialog';

let container, root;
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    vi.spyOn(HTMLElement.prototype, 'getClientRects').mockReturnValue([{ width: 400, height: 300 }]);
    container = document.createElement('div'); document.body.append(container); root = createRoot(container);
});
afterEach(async () => { await act(() => root.unmount()); container.remove(); vi.restoreAllMocks(); });
const opciones = [{ codigo: '601', nombre: 'General de Ley Personas Morales' }, { codigo: '605', nombre: 'Sueldos y Salarios e Ingresos Asimilados' }, { codigo: '626', nombre: 'Régimen Simplificado de Confianza' }];
const escribir = (input, value) => act(() => {
    Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value').set.call(input, value);
    input.dispatchEvent(new Event('input', { bubbles: true }));
});

it('mantiene el foco de búsqueda dentro del modal y filtra por texto sin acentos', async () => {
    const close = vi.fn(); const change = vi.fn();
    await act(() => root.render(<SolicitudDialog onClose={close}><div className="gelia-modal-shell"><SelectorCatalogoFiscal opciones={opciones} onChange={change} label="Régimen fiscal" /></div></SolicitudDialog>));
    await act(() => document.querySelector('[aria-haspopup="listbox"]').click());
    const input = document.querySelector('[role="combobox"]');
    expect(input.closest('[role="dialog"]')).not.toBeNull();
    expect(document.activeElement).toBe(input);
    await escribir(input, 'regimen confianza');
    expect(document.querySelectorAll('[role="option"]')).toHaveLength(1);
    expect(document.querySelector('[role="option"]').textContent).toContain('626');
    expect(document.activeElement).toBe(input);
    await act(() => input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true })));
    expect(close).not.toHaveBeenCalled();
    expect(document.querySelector('[role="listbox"]')).toBeNull();
    expect(document.activeElement).toBe(document.querySelector('[aria-haspopup="listbox"]'));
});

it('selecciona una opción por teclado y devuelve el foco al control', async () => {
    const change = vi.fn();
    await act(() => root.render(<SelectorCatalogoFiscal opciones={opciones} onChange={change} label="Régimen fiscal" />));
    await act(() => container.querySelector('button').click());
    const input = document.querySelector('[role="combobox"]');
    await escribir(input, '605');
    await act(() => input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true })));
    expect(change).toHaveBeenCalledWith('605');
    expect(document.querySelector('[role="listbox"]')).toBeNull();
    expect(document.activeElement).toBe(container.querySelector('button'));
});
