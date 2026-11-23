// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import FormularioRegistrarTienda from '../Partials/FormularioRegistrarTienda';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
vi.mock('@inertiajs/react', () => ({ usePage: () => ({ props: { auth: { user: { id: 1, departamento_id: 1, roles: [], permissions: [] } } } }) }));
vi.mock('../Partials/useActualizacionesPedidos', () => ({ default: () => {} }));
vi.mock('../Partials/FormularioProgresivo/EncabezadoPedido', () => ({
    BotonBorrador: ({ onClick }) => <button type="button" onClick={onClick}>Guardar borrador</button>,
}));

let root, host;
const documento = { id: 1, tipo: 'pdf_pedido', url: '/pedido.png', nombre_original: 'Pedido.png', mime_type: 'image/png', activo: true };
const pedido = { id: 9, folio: 'QA-9', folio_remision: 'WIZ-9', cliente_id: 3, numero_cliente: '3',
    departamento_operacion_id: 1, almacen_id: 47, catalogo_paqueteria_id: 8, total_mercancia: 100,
    revision_registro: 1, base_captura: 'a'.repeat(64), documentos: [documento],
    estatus: { fase_ciclo: 'BORRADOR' }, solicitud_preparacion: { modalidad_entrega: 'nacional', captura_nacional: 'compartida' } };
const catalogos = { departamentos: [{ id: 1, nombre: 'Ventas' }],
    paqueterias: [{ id: 8, nombre: 'DHL', categoria: 'comercial', activo: true }],
    almacenes: [47, 73].map(id => ({ id, nombre: `CEDIS de prueba ${id}`, tipo_almacen: { nombre: 'CEDIS' }, permite_salida_pedidos: true })) };

beforeEach(() => {
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    vi.stubGlobal('route', (nombre, id) => `${nombre}/${id || ''}`);
    axios.get.mockReset(); axios.post.mockReset();
    axios.get.mockResolvedValue({ data: pedido });
    axios.post.mockResolvedValue({ data: { ...pedido, revision_registro: 2, base_captura: 'b'.repeat(64) } });
    host = document.createElement('div'); document.body.append(host); root = createRoot(host);
});
afterEach(async () => { await act(() => root.unmount()); host.remove(); vi.unstubAllGlobals(); });

it('envía el almacén elegido mediante el autoguardado existente del formulario completo', async () => {
    await act(async () => root.render(<FormularioRegistrarTienda abierto pedido={pedido} catalogos={catalogos} onClose={() => {}} />));
    await act(async () => {
        const salida = document.getElementById('almacen-salida-nacional');
        salida.value = '73'; salida.dispatchEvent(new Event('change', { bubbles: true }));
    });
    await act(async () => [...document.querySelectorAll('button')].find(b => b.textContent.trim() === 'Guardar borrador').click());
    expect(axios.post).toHaveBeenCalled();
    const [url, datos] = axios.post.mock.calls.find(([url]) => url === '/control-pedidos/autoguardar');
    expect(url).toBe('/control-pedidos/autoguardar');
    expect(datos.almacen_id).toBe(73);
    expect(datos.pedido_id).toBe(9);
    expect(datos.catalogo_paqueteria_id).toBe(8);
    expect(datos.total_mercancia).toBe(100);
    expect(axios.post.mock.calls.some(([url]) => url.includes('solicitar-pesaje'))).toBe(false);
    expect(document.getElementById('almacen-salida-nacional').value).toBe('73');
});
