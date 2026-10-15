// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import Formulario from './FormularioConfirmarRecepcionResguardo';
import useRecepcionFisica from './useRecepcionFisica';

const post = vi.hoisted(() => vi.fn());
vi.mock('axios', () => ({ default: { post, put: vi.fn() } }));
vi.mock('@inertiajs/react', () => ({ router: { visit: vi.fn(), reload: vi.fn() } }));
vi.mock('./ChipEvidenciasBultosEmpaque', () => ({ default: () => null }));
vi.mock('./ModalEvidenciasBultosEmpaque', () => ({ ChipEvidenciasBultosEmpaque: () => null }));
vi.mock('../../../../hooks/useToastAlCambiar', () => ({ default: () => {} }));
let root;
let container;
beforeEach(() => {
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    globalThis.route = (name, id) => `${name}/${id}`;
    sessionStorage.clear();
    post.mockReset();
    container = document.createElement('div'); document.body.append(container);
    root = createRoot(container);
});
afterEach(async () => { await act(() => root.unmount()); container.remove(); });
const resguardo = { id: 7, snapshot_folio: 'PED-7', cantidad_bultos_esperada: 3, cantidad_bultos_recibida: 1, cantidad_bultos_pendiente: 2,
    bultos_esperados: [{ folio: 'PED-7-B1', recibido: true }, { folio: 'PED-7-B2', recibido: false }, { folio: 'PED-7-B3', recibido: false }] };
it('permite seleccionar el segundo bulto sin acreditar el primero ni el tercero', async () => {
    const enviar = vi.fn();
    await act(() => root.render(<Formulario resguardo={resguardo} onEnviar={enviar} />));
    const checks = container.querySelectorAll('fieldset input[type="checkbox"]');
    expect(checks[0].disabled).toBe(true);
    expect(checks[0].checked).toBe(true);
    expect(checks[1].checked).toBe(false);
    await act(() => checks[1].click());
    await act(() => container.querySelector('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })));
    expect(enviar.mock.calls[0][0]).toMatchObject({ folios: ['PED-7-B2'], condicion: 'bueno' });
    expect(container.textContent).toContain('Pendientes: 2');
    expect(container.querySelector('[name="almacen_id"]')).toBeNull();
});
it('envía la evidencia por POST con método PUT y folios explícitos', async () => {
    let enviar;
    function Harness() { enviar = useRecepcionFisica({ resguardoId: 7, versionInicial: 2 }).enviar; return null; }
    post.mockResolvedValue({ data: { resguardo: { version: 3, recepcion_completa: false } } });
    await act(() => root.render(<Harness />));
    const foto = new File(['foto'], 'llegada.jpg', { type: 'image/jpeg' });
    await act(() => enviar({ folios: ['PED-7-B2'], condicion: 'bueno', fotoPaquete: foto }));
    const body = post.mock.calls[0][1];
    expect(body.get('_method')).toBe('PUT');
    expect(body.get('folios[0]')).toBe('PED-7-B2');
    expect(body.get('folios[1]')).toBeNull();
    expect(body.get('foto_paquete').name).toBe('llegada.jpg');
});
