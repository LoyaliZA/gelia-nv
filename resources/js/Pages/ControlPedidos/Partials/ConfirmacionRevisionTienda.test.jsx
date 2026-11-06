// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import axios from 'axios';
import ConfirmacionRevisionTienda from './ConfirmacionRevisionTienda';
vi.mock('axios', () => ({ default: { post: vi.fn(), put: vi.fn() } }));
let container, root;
const pedido = { id: 55, sucursal_destino_id: null, estatus: { fase_ciclo: 'PESAJE_RESPONDIDO' }, solicitud_preparacion: { area_revision: 'control_pedidos', cobertura_tienda: { tipo: 'credito' } } };
const props = { pedido, sucursales: [{ id: 2, nombre: 'Sucursal destino' }], consultaCerrada: true, importesListos: true };
const boton = nombre => [...container.querySelectorAll('button')].find(b => b.textContent.includes(nombre));
beforeEach(() => { vi.clearAllMocks(); globalThis.IS_REACT_ACT_ENVIRONMENT = true; container = document.createElement('div'); document.body.append(container); root = createRoot(container); });
afterEach(async () => { await act(() => root.unmount()); container.remove(); });
it('exige guardar destino antes de confirmar crédito y adopta las respuestas guardadas', async () => {
    const onLectura = vi.fn();
    await act(() => root.render(<ConfirmacionRevisionTienda {...props} onLectura={onLectura} />));
    expect(boton('Confirmar pedido').disabled).toBe(true);
    await act(() => { const select = container.querySelector('select'); select.value = '2'; select.dispatchEvent(new Event('change', { bubbles: true })); });
    expect(boton('Confirmar pedido').disabled).toBe(true);
    axios.post.mockResolvedValue({ data: { ...pedido, sucursal_destino_id: 2 } });
    await act(async () => boton('Guardar sucursal').click());
    expect(onLectura).toHaveBeenCalledWith(expect.objectContaining({ sucursal_destino_id: 2 }));
    await act(() => root.render(<ConfirmacionRevisionTienda {...props} pedido={{ ...pedido, sucursal_destino_id: 2 }} onLectura={onLectura} />));
    axios.put.mockResolvedValue({ data: { estatus: { fase_ciclo: 'PENDIENTE_AUXILIAR' } } });
    await act(async () => boton('Confirmar pedido').click());
    expect(axios.put).toHaveBeenCalledWith('/control-pedidos/55/enviar', {}, expect.any(Object));
    expect(container.textContent).toContain('Pedido enviado a revisión del auxiliar');
});
it('bloquea confirmación con consulta abierta o importes aún sin guardar y conserva errores del servidor', async () => {
    const conDestino = { ...pedido, sucursal_destino_id: 2 };
    await act(() => root.render(<ConfirmacionRevisionTienda {...props} pedido={conDestino} consultaCerrada={false} />));
    expect(boton('Confirmar pedido').disabled).toBe(true);
    await act(() => root.render(<ConfirmacionRevisionTienda {...props} pedido={conDestino} importesListos={false} />));
    expect(boton('Confirmar pedido').disabled).toBe(true);
    await act(() => root.render(<ConfirmacionRevisionTienda {...props} pedido={conDestino} />));
    axios.put.mockRejectedValue({ response: { status: 422, data: { message: 'Falta registrar exhibiciones de pago.' } } });
    await act(async () => boton('Confirmar pedido').click());
    expect(container.textContent).toContain('Falta registrar exhibiciones de pago.');
});
it('pago al recoger conserva su confirmación propia y una revisión enviada no ofrece reenviar', async () => {
    await act(() => root.render(<ConfirmacionRevisionTienda {...props} pedido={{ ...pedido, solicitud_preparacion: { cobertura_tienda: { tipo: 'apartado' } } }} />));
    expect(boton('Confirmar pedido')).toBeUndefined();
    await act(() => root.render(<ConfirmacionRevisionTienda {...props} pedido={{ ...pedido, estatus: { fase_ciclo: 'PENDIENTE_AUXILIAR' } }} />));
    expect(boton('Confirmar pedido')).toBeUndefined();
    expect(container.textContent).toContain('Pedido en revisión del auxiliar');
});

it('resume CEDIS y destino guardado en badges, y solo despliega el selector al cambiarlo', async () => {
 await act(()=>root.render(<ConfirmacionRevisionTienda {...props} pedido={{...pedido,sucursal_destino_id:2}}/>));
 expect(container.querySelector('[aria-label="Origen y destino del pedido"]').textContent).toContain('CEDIS');
 expect(container.textContent).toContain('Sucursal destino');expect(container.querySelector('select')).toBeNull();
 expect(container.textContent).not.toContain('recolección');
 await act(()=>boton('Cambiar destino').click());expect(container.querySelector('select')).not.toBeNull();
 await act(()=>boton('Cancelar').click());expect(container.querySelector('select')).toBeNull();
});
it('Tienda no presenta origen/destino ni un selector, y conserva la confirmación que corresponda', async () => {
 const enTienda={...pedido,sucursal_destino_id:2,solicitud_preparacion:{area_revision:'preparacion_tienda',cobertura_tienda:{tipo:'apartado'}}};
 await act(()=>root.render(<ConfirmacionRevisionTienda {...props} pedido={enTienda}/>));
 expect(container.textContent).toBe('');
 await act(()=>root.render(<ConfirmacionRevisionTienda {...props} pedido={{...enTienda,solicitud_preparacion:{...enTienda.solicitud_preparacion,cobertura_tienda:{tipo:'credito'}}}}/>));
 expect(container.querySelector('[aria-label="Origen y destino del pedido"]')).toBeNull();
 expect(container.querySelector('select')).toBeNull();expect(boton('Confirmar pedido').disabled).toBe(false);
 await act(()=>root.render(<ConfirmacionRevisionTienda {...props} pedido={{...enTienda,sucursal_destino_id:null,solicitud_preparacion:{...enTienda.solicitud_preparacion,cobertura_tienda:{tipo:'credito'}}}}/>));
 expect(boton('Confirmar pedido').disabled).toBe(true);
});

it('ubica la acción en el pie compartido y comunica el resultado al encabezado sin duplicarlo en el cuerpo', async () => {
    const pie = document.createElement('footer');
    document.body.append(pie);
    const onAviso = vi.fn();
    try {
        await act(() => root.render(<ConfirmacionRevisionTienda {...props} pedido={{ ...pedido, sucursal_destino_id: 2 }} destinoAccion={pie} onAviso={onAviso} />));
        expect(boton('Confirmar pedido')).toBeUndefined();
        const accion = pie.querySelector('button');
        expect(accion.textContent).toContain('Confirmar pedido y enviar a revisión');
        axios.put.mockResolvedValue({ data: { estatus: { fase_ciclo: 'PENDIENTE_AUXILIAR' } } });
        await act(async () => accion.click());
        expect(onAviso).toHaveBeenLastCalledWith('Pedido enviado a revisión del auxiliar.');
        expect(container.textContent).not.toContain('Pedido enviado a revisión');
        await act(() => root.render(<ConfirmacionRevisionTienda {...props} pedido={{ ...pedido, estatus: { fase_ciclo: 'PENDIENTE_AUXILIAR' } }} destinoAccion={pie} onAviso={onAviso} />));
        expect(pie.querySelector('button')).toBeNull();
        expect(container.textContent).not.toContain('Pedido en revisión del auxiliar');
    } finally { pie.remove(); }
});
