// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, expect, it, vi } from 'vitest';
import DesgloseCotizacionNacional from './DesgloseCotizacionNacional';

let host;
let root;
afterEach(async () => {
    await act(() => root.unmount());
    host.remove();
});

async function render(props) {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
    await act(async () => root.render(<DesgloseCotizacionNacional {...props} />));
}

it('lista cada concepto con su monto en pesos', async () => {
    await render({ filas: [['Mercancía', 1000], ['Seguro', 30], ['Total', 1230]] });
    const desglose = host.querySelector('[aria-label="Desglose de la cotización"]');
    expect(desglose.textContent).toContain('Mercancía$1,000.00');
    expect(desglose.textContent).toContain('Seguro$30.00');
    expect(desglose.textContent).toContain('Total$1,230.00');
});

it('marca el desglose como ocupado mientras recalcula', async () => {
    await render({ filas: [['Total', 10]], cargando: true });
    expect(host.querySelector('[aria-label="Desglose de la cotización"]').getAttribute('aria-busy')).toBe('true');
});

it('no dibuja nada sin filas', async () => {
    await render({ filas: [] });
    expect(host.querySelector('[aria-label="Desglose de la cotización"]')).toBeNull();
});
