// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, expect, it, vi } from 'vitest';
import EntregaApartadoT01 from './EntregaApartadoT01';
vi.mock('@inertiajs/react', () => ({ router: { post: vi.fn() } }));
let container, root;
const enviar = vi.fn();
const tarea = { id: 1, responsable: { id: 10 }, apartado: { version: 3, estado: 'APARTADO_CONFIRMADO' }, pedido: { es_t01_documental: true, seguimiento_entrega_pdv_habilitado: true, cobertura_tienda: { tipo: 'apartado' } } };
const auth = { user: { id: 10, permissions: ['control_pedidos.tienda.responder'] } };
const mount = (extra={}) => act(() => root.render(<EntregaApartadoT01 tarea={tarea} auth={auth} onEnviar={enviar} {...extra} />));
const button = text => [...container.querySelectorAll('button')].find(b => b.textContent === text);
beforeEach(() => { globalThis.IS_REACT_ACT_ENVIRONMENT=true; globalThis.route=(name,id)=>`${name}/${id}`; enviar.mockReset(); vi.spyOn(URL,'createObjectURL').mockReturnValue('blob:foto-prueba'); container=document.createElement('div');document.body.append(container);root=createRoot(container); });
afterEach(async () => { await act(()=>root.unmount());container.remove(); vi.restoreAllMocks(); });
it('requiere confirmación del cobro externo, remisión y foto; el ticket es opcional', async () => {
 await mount();
 await act(()=>button('Confirmar entrega').click());
 expect(enviar).not.toHaveBeenCalled();
 expect(container.textContent).toContain('Confirme el pago recibido en PDV');
 await act(()=>container.querySelector('input[type="checkbox"]').click());
 const inputs=[...container.querySelectorAll('input:not([type])')];
 await act(()=>{ for(const [i,input] of inputs.entries()) { Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype,'value').set.call(input,i ? 'PDV-123':'Cliente');input.dispatchEvent(new Event('input',{bubbles:true})); } });
 const foto=container.querySelector('input[type="file"]');
 await act(()=>{ Object.defineProperty(foto,'files',{configurable:true,value:[new File(['foto'],'entrega.jpg',{type:'image/jpeg'})]});foto.dispatchEvent(new Event('change',{bubbles:true})); });
 await act(()=>button('Confirmar entrega').click());
 expect(enviar).toHaveBeenCalledOnce();
 const form=enviar.mock.lastCall[1];expect(form.get('numero_remision')).toBe('PDV-123');expect(form.get('pago_confirmado')).toBe('1');expect(form.has('ticket')).toBe(false);
});
it('la liberación requiere motivo y no ocurre automáticamente', async () => {
 await mount();
 expect(enviar).not.toHaveBeenCalled();
 await act(()=>button('El cliente no recogió · Liberar apartado').click());
 await act(()=>button('Confirmar liberación y avisar a Ventas').click());
 expect(enviar).not.toHaveBeenCalled();
 expect(container.textContent).toContain('Indique el motivo');
});
it('otra persona y un apartado finalizado no ofrecen acciones operativas', async () => {
 await mount({auth:{user:{id:11,roles:['Super Admin']}}});expect(container.querySelector('button')).toBeNull();
 await mount({tarea:{...tarea,apartado:{...tarea.apartado,estado:'DEVUELTA_ANAQUEL'}}});expect(container.querySelector('button')).toBeNull();expect(container.textContent).toContain('Apartado liberado');
});

it('el paso apagado no muestra controles ni exige capturar pago o remisión', async () => {
 await mount({ tarea: { ...tarea, pedido: { ...tarea.pedido, seguimiento_entrega_pdv_habilitado: false } } });
 expect(container.textContent).toBe('');
 expect(container.querySelector('input,button')).toBeNull();
 expect(enviar).not.toHaveBeenCalled();
});
it('habilitar el paso no lo adelanta antes de confirmar el apartado', async () => {
 await mount({ tarea: { ...tarea, apartado: { ...tarea.apartado, estado: 'POR_SEPARAR' } } });
 expect(container.textContent).toBe('');
 expect(enviar).not.toHaveBeenCalled();
});
