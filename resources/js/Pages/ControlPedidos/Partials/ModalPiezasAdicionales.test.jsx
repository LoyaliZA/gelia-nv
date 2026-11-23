// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import { accionesPedidoLista } from './pedidosListadoUi';
import { complementosDe } from './pedidosBmaStyles';
import ModalPiezasAdicionales from './ModalPiezasAdicionales';
import PiezasAdicionalesArea from './PiezasAdicionalesArea';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
vi.mock('@inertiajs/react', () => ({ router: { post: vi.fn(), reload: vi.fn() } }));

let container;
let root;
const boton = (texto) => [...document.body.querySelectorAll('button')].find((b) => b.textContent.trim() === texto);
const complemento = (extra = {}) => ({
    id: 7, folio: 'PED-1-C1', folio_pedido: 'WIZ-9', estado: 'abierto', etiqueta: 'Pendientes de pago', total_mercancia: 250,
    piezas: [{ descripcion: 'Aroma extra', cantidad: 2 }], pago_registrado: false, pagado: false, remisionado: false,
    puede_registrar_pago: false, puede_enviar: false, puede_integrar: false, ...extra,
});
const pedido = (resumen) => ({ id: 1, folio: 'PED-1', cliente: { nombre: 'Cliente ficticio' }, piezas_adicionales: resumen });

beforeEach(() => {
    vi.clearAllMocks();
    globalThis.IS_REACT_ACT_ENVIRONMENT = true;
    globalThis.route = (nombre, params) => `${nombre}:${JSON.stringify(params ?? null)}`;
    container = document.createElement('div');
    document.body.append(container);
    root = createRoot(container);
});
afterEach(async () => {
    await act(() => root.unmount());
    container.remove();
});

const propsLista = { can: () => true, onVer: vi.fn(), onBitacora: vi.fn(), puedeEditar: () => false };

it('la acción de Ventas depende de lo que informa el servidor', () => {
    const onAgregarPiezas = vi.fn();
    const base = { solicitud_preparacion: { modalidad_entrega: 'municipal' }, estatus: { fase_ciclo: 'EN_CEDIS' } };
    const con = (resumen) => accionesPedidoLista({ ...propsLista, onAgregarPiezas, pedido: { ...base, piezas_adicionales: resumen } });

    expect(con({ puede_agregar: true, items: [] }).find((a) => a.key === 'piezas-adicionales').label).toBe('Agregar piezas');
    expect(con({ puede_agregar: false, items: [complemento()] }).find((a) => a.key === 'piezas-adicionales').label).toBe('Piezas adicionales');
    expect(con({ puede_agregar: false, items: [] }).some((a) => a.key === 'piezas-adicionales')).toBe(false);
    expect(con(null).some((a) => a.key === 'piezas-adicionales')).toBe(false);
    expect(con({ puede_agregar: true, items: [] }).find((a) => a.key === 'piezas-adicionales').grupo).toBe('Operaciones');
});

it('los complementos municipales no se cuentan como complementos del grupo de empaque', () => {
    const lista = { id: 1, complementos: [{ id: 7 }, { id: 8 }], piezas_adicionales: { items: [{ id: 7 }] } };
    expect(complementosDe(lista).map((c) => c.id)).toEqual([8]);
});

it('Ventas agrega piezas con folio, importe, piezas y documento', async () => {
    axios.post.mockResolvedValue({ data: { ok: true } });
    const onActualizado = vi.fn();
    await act(() => root.render(<ModalPiezasAdicionales abierto pedido={pedido({ puede_agregar: true, items: [] })} onClose={() => {}} onActualizado={onActualizado} />));

    expect(document.body.textContent).toContain('Agregar piezas');
    await act(() => boton('Guardar piezas').click());
    expect(axios.post).toHaveBeenCalledTimes(1);
    const datos = axios.post.mock.calls[0][1];
    expect(datos.get('solicitud_id')).toBeTruthy();
    expect(datos.get('evidencias[]')).toBeNull();
    expect(onActualizado).toHaveBeenCalledTimes(1);
});

it('muestra el error del servidor sin cerrar el formulario', async () => {
    axios.post.mockRejectedValue({ response: { data: { errors: { anexo: ['El paquete ya salió a terminal. Las piezas adicionales van en otro envío.'] } } } });
    const onActualizado = vi.fn();
    await act(() => root.render(<ModalPiezasAdicionales abierto pedido={pedido({ puede_agregar: true, items: [] })} onClose={() => {}} onActualizado={onActualizado} />));
    await act(() => boton('Guardar piezas').click());

    expect(document.body.querySelector('[role="alert"]').textContent).toContain('ya salió a terminal');
    expect(onActualizado).not.toHaveBeenCalled();
});

it('cada complemento ofrece solo la acción que el servidor autoriza', async () => {
    const resumen = {
        puede_agregar: false,
        items: [
            complemento({ id: 1, folio: 'A', puede_registrar_pago: true }),
            complemento({ id: 2, folio: 'B', etiqueta: 'Listas para enviar a revisión', pago_registrado: true, puede_registrar_pago: true, puede_enviar: true }),
            complemento({ id: 3, folio: 'C', etiqueta: 'Pendientes de integrar', pagado: true, remisionado: true }),
        ],
    };
    await act(() => root.render(<ModalPiezasAdicionales abierto pedido={pedido(resumen)} onClose={() => {}} onActualizado={() => {}} />));

    expect(document.body.querySelectorAll('button[type="button"]').length).toBeGreaterThan(0);
    expect(boton('Registrar pago')).toBeDefined();
    expect(boton('Enviar a revisión')).toBeDefined();
    expect(boton('Confirmar integración')).toBeUndefined();
    expect(document.body.textContent).not.toMatch(/\bM-0\d\b/);

    axios.post.mockResolvedValue({ data: { ok: true } });
    await act(() => boton('Enviar a revisión').click());
    expect(axios.post.mock.calls[0][0]).toContain('control_pedidos.municipal.complementos.enviar');
});

it('el área solo confirma la integración cuando el servidor lo permite', async () => {
    axios.post.mockResolvedValue({ data: { ok: true } });
    const onActualizado = vi.fn();
    await act(() => root.render(
        <PiezasAdicionalesArea pedidoId={1} onActualizado={onActualizado} items={[complemento({ id: 5, pagado: true, remisionado: true, puede_integrar: true }), complemento({ id: 6, folio: 'OTRO' })]} />
    ));

    expect(document.body.querySelectorAll('button').length).toBe(1);
    await act(() => boton('Confirmar integración').click());
    expect(axios.post.mock.calls[0][0]).toContain('control_pedidos.municipal.complementos.integrar');
    expect(onActualizado).toHaveBeenCalledTimes(1);
});
