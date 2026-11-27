// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import EtiquetaMultienvio, { textoMultienvio } from './EtiquetaMultienvio';
import EncabezadoFolioPedido from './EncabezadoFolioPedido';

let host;
let root;
beforeEach(() => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    host = document.createElement('div');
    document.body.append(host);
    root = createRoot(host);
});
afterEach(async () => {
    await act(() => root.unmount());
    host.remove();
});

const grupo = (extra = {}) => ({ id: 4, orden: 2, n_total: 3, estado: 'activo', parcial: false, hermanos: [], ...extra });

it('nombra la posición del envío y omite el total cuando la vista es parcial', () => {
    expect(textoMultienvio(grupo())).toBe('Envío 2 de 3');
    expect(textoMultienvio(grupo({ parcial: true }))).toBe('Envío 2');
    expect(textoMultienvio(grupo({ orden: null, estado: 'configurando' }))).toBe('Multienvío en configuración');
    expect(textoMultienvio(grupo({ orden: null, estado: 'regularizacion_pendiente' }))).toBe('Multienvío por regularizar');
    expect(textoMultienvio(null)).toBeNull();
    expect(textoMultienvio(undefined)).toBeNull();
});

it('muestra la píldora con icono y texto', async () => {
    await act(async () => root.render(<EtiquetaMultienvio multienvio={grupo()} />));
    const etiqueta = host.querySelector('[data-etiqueta-multienvio]');
    expect(etiqueta.textContent).toBe('Envío 2 de 3');
    expect(etiqueta.querySelector('svg')).not.toBeNull();
});

it('no muestra nada sin grupo', async () => {
    await act(async () => root.render(<EtiquetaMultienvio multienvio={null} />));
    expect(host.textContent).toBe('');
});

it('el encabezado de folio de todas las bandejas lleva la etiqueta del grupo', async () => {
    await act(async () => root.render(<EncabezadoFolioPedido pedido={{ folio: 'PBMA-10', folio_remision: 'WZ-2', multienvio: grupo({ parcial: true }) }} />));
    expect(host.textContent).toContain('WZ-2');
    expect(host.querySelector('[data-etiqueta-multienvio]').textContent).toBe('Envío 2');

    await act(async () => root.render(<EncabezadoFolioPedido pedido={{ folio: 'PBMA-11', folio_remision: 'WZ-3' }} />));
    expect(host.querySelector('[data-etiqueta-multienvio]')).toBeNull();
    expect(host.textContent).toContain('WZ-3');
});

it('el detalle nacional lista los envíos hermanos con folio Wizerp y estado con icono', async () => {
    const { default: SeccionMultienvioDetalle } = await import('./detalle-pedido/SeccionMultienvioDetalle');
    const hermanos = [
        { id: 11, orden: 3, folio: 'PBMA-11', folio_remision: 'WZ-3', estatus_nombre: 'Enviado', estatus_fase: 'ENVIADO', pago_validado: true, tiene_remision: true, tiene_guia: true },
        { id: 10, orden: 2, folio: 'PBMA-10', folio_remision: 'WZ-2', estatus_nombre: 'Pendiente de pago', estatus_fase: 'PENDIENTE', pago_validado: false, tiene_remision: false, tiene_guia: false },
    ];
    await act(async () => root.render(<SeccionMultienvioDetalle pedido={{ id: 10, multienvio: grupo({ hermanos }) }} />));
    const filas = [...host.querySelectorAll('li')];
    expect(filas.map((fila) => fila.textContent.match(/Envío \d/)[0])).toEqual(['Envío 2', 'Envío 3']);
    expect(filas[1].textContent).toContain('Folio Wizerp: WZ-3');
    expect(filas[1].textContent).toContain('Enviado');
    expect(filas[1].querySelector('svg')).not.toBeNull();
    expect(filas[0].getAttribute('aria-current')).toBe('true');
    expect(host.textContent).toContain('Envío 2 de 3');

    await act(async () => root.render(<SeccionMultienvioDetalle pedido={{ id: 10 }} />));
    expect(host.textContent).toBe('');
});

it('la firma de grupo cambia con el estado o el avance de un hermano y es vacía sin grupo', async () => {
    const { firmaMultienvio } = await import('./EtiquetaMultienvio');
    const hermano = { id: 10, orden: 2, folio_remision: 'WZ-2', estatus_fase: 'PENDIENTE', pago_validado: false, tiene_remision: false, tiene_guia: false };
    const base = { multienvio: grupo({ hermanos: [hermano] }) };
    expect(firmaMultienvio(base)).toBe(firmaMultienvio({ multienvio: grupo({ hermanos: [{ ...hermano }] }) }));
    expect(firmaMultienvio(base)).not.toBe(firmaMultienvio({ multienvio: grupo({ estado: 'regularizacion_pendiente', hermanos: [hermano] }) }));
    expect(firmaMultienvio(base)).not.toBe(firmaMultienvio({ multienvio: grupo({ hermanos: [{ ...hermano, pago_validado: true }] }) }));
    expect(firmaMultienvio({})).toBe('');
    expect(firmaMultienvio({ multienvio: null })).toBe('');
});
