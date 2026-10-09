// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import usePedidoDialog from './usePedidoDialog';

let root;
let container;
let originalRects;
function Dialog({ name, onClose, bloqueado = false, layer = 1 }) {
    const props = usePedidoDialog({ abierto: true, onClose, bloqueado });
    return React.createElement('div', { className: 'gelia-modal-overlay', style: { zIndex: layer } },
        React.createElement('div', { ...props, 'aria-label': name },
            React.createElement('button', {}, `${name} first`),
            React.createElement('button', {}, `${name} last`)));
}
const key = (value, shiftKey = false) => document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: value, shiftKey, bubbles: true, cancelable: true }));

beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    originalRects = HTMLElement.prototype.getClientRects;
    HTMLElement.prototype.getClientRects = () => [{ width: 100, height: 44 }];
    document.body.style.overflow = 'auto';
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
});
afterEach(async () => {
    await act(() => root.unmount());
    container.remove();
    HTMLElement.prototype.getClientRects = originalRects;
    document.body.innerHTML = '';
    document.body.style.overflow = '';
});

describe('order dialog interaction', () => {
    it('locks scroll, contains keyboard focus, and returns to the trigger', async () => {
        const trigger = document.createElement('button');
        document.body.prepend(trigger);
        trigger.focus();
        await act(() => root.render(React.createElement(Dialog, { name: 'order' })));
        const dialog = document.querySelector('[role="dialog"]');
        const [first, last] = dialog.querySelectorAll('button');
        expect(document.activeElement).toBe(dialog);
        expect(document.body.style.overflow).toBe('hidden');
        key('Tab');
        expect(document.activeElement).toBe(first);
        key('Tab', true);
        expect(document.activeElement).toBe(last);
        key('Tab');
        expect(document.activeElement).toBe(first);
        await act(() => root.render(null));
        expect(document.activeElement).toBe(trigger);
        expect(document.body.style.overflow).toBe('auto');
    });

    it('closes only the top dialog and retains the parent scroll lock', async () => {
        const parentClose = vi.fn();
        const childClose = vi.fn();
        const tree = (child) => React.createElement(React.Fragment, {},
            React.createElement(Dialog, { name: 'parent', onClose: parentClose, key: 'parent' }),
            child && React.createElement(Dialog, { name: 'child', onClose: childClose, layer: 2, key: 'child' }));
        await act(() => root.render(tree(false)));
        const parentButton = document.querySelector('button');
        parentButton.focus();
        await act(() => root.render(tree(true)));
        key('Escape');
        expect(childClose).toHaveBeenCalledTimes(1);
        expect(parentClose).not.toHaveBeenCalled();
        await act(() => root.render(tree(false)));
        expect(document.body.style.overflow).toBe('hidden');
        expect(document.activeElement).toBe(parentButton);
        key('Escape');
        expect(parentClose).toHaveBeenCalledTimes(1);
    });

    it('blocks Escape while a request is running and uses the latest callback', async () => {
        const close = vi.fn();
        await act(() => root.render(React.createElement(Dialog, { name: 'order', onClose: close, bloqueado: true })));
        key('Escape');
        expect(close).not.toHaveBeenCalled();
        const updatedClose = vi.fn();
        await act(() => root.render(React.createElement(Dialog, { name: 'order', onClose: updatedClose })));
        key('Escape');
        expect(updatedClose).toHaveBeenCalledTimes(1);
    });
});
