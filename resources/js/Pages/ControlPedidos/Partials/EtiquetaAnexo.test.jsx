// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import EtiquetaAnexo, { datosAnexo } from './EtiquetaAnexo';

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

const anexo = { complemento_nacional: { pedido_origen_id: 5, pedido_origen_folio: 'PBMA-58' } };

it('lee los datos del anexo de solicitud_preparacion o de solicitud_preparacion_json', () => {
    expect(datosAnexo({ solicitud_preparacion: anexo })).toEqual({ principalId: 5, folioPrincipal: 'PBMA-58' });
    expect(datosAnexo({ solicitud_preparacion_json: anexo })).toEqual({ principalId: 5, folioPrincipal: 'PBMA-58' });
    expect(datosAnexo({ solicitud_preparacion: { modalidad_entrega: 'nacional' } })).toBeNull();
    expect(datosAnexo({ solicitud_preparacion: { complementos_nacionales: [{ pedido_id: 9 }] } })).toBeNull();
    expect(datosAnexo(null)).toBeNull();
});

it('muestra Anexo con el folio del principal, con texto además del color', async () => {
    await act(async () => root.render(<EtiquetaAnexo pedido={{ solicitud_preparacion: anexo }} />));
    expect(host.textContent).toContain('Anexo');
    expect(host.textContent).toContain('de PBMA-58');
    expect(host.querySelector('[data-etiqueta-anexo]')).not.toBeNull();
});

it('no muestra nada en un pedido ordinario ni en el principal de un anexo', async () => {
    await act(async () => root.render(<EtiquetaAnexo pedido={{ solicitud_preparacion: { modalidad_entrega: 'nacional', complementos_nacionales: [{ pedido_id: 9 }] } }} />));
    expect(host.textContent).toBe('');
});

it('el encabezado de folio compartido por todas las bandejas lleva la etiqueta del anexo', async () => {
    const { default: EncabezadoFolioPedido } = await import('./EncabezadoFolioPedido');
    await act(async () => root.render(<EncabezadoFolioPedido pedido={{ folio: 'PBMA-58-C1', folio_remision: 'WIZ-9', solicitud_preparacion: anexo }} />));
    expect(host.textContent).toContain('WIZ-9');
    expect(host.textContent).toContain('Anexo de PBMA-58');

    await act(async () => root.render(<EncabezadoFolioPedido pedido={{ folio: 'PBMA-58', folio_remision: 'WIZ-1', solicitud_preparacion: { modalidad_entrega: 'nacional' } }} />));
    expect(host.textContent).not.toContain('Anexo');
});
