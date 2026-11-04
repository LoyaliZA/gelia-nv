// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, it, expect, vi } from 'vitest';
import ApartadoFisicoTienda from './ApartadoFisicoTienda';
vi.mock('@inertiajs/react',()=>({router:{post:vi.fn()}}));
let root,container;
const tarea={id:1,estado:'RESPONDIDA',modalidad:{codigo:'RECOGE_TIENDA'},apartado:{estado:'APARTADO_CONFIRMADO',cantidad:1,version:1,pago_confirmado_at:'2026-11-04T16:00:00Z'},pedido:{}};
const render=(extra={})=>act(()=>root.render(<ApartadoFisicoTienda soloSalida tarea={{...tarea,...extra}} auth={{user:{id:2,roles:['Super Admin']}}}/>));
beforeEach(()=>{globalThis.IS_REACT_ACT_ENVIRONMENT=true;container=document.createElement('div');document.body.append(container);root=createRoot(container);});
afterEach(async()=>{await act(()=>root.unmount());container.remove();});
it('el apartado confirmado ofrece la entrega después de confirmar el pago',async()=>{
 await render();
 expect(container.textContent).toContain('Pago y entrega en tienda');
 expect([...container.querySelectorAll('button')].some(b=>b.textContent==='Confirmar entrega')).toBe(true);
 await render({apartado:{...tarea.apartado,pago_confirmado_at:null}});
 expect([...container.querySelectorAll('button')].some(b=>b.textContent==='Confirmar entrega')).toBe(false);
});
it('un anexo sin separar bloquea la entrega y explica el pendiente',async()=>{
 await render({pedido:{motivo_bloqueo_entrega:'Falta separar el anexo.'}});
 expect(container.textContent).toContain('Falta separar el anexo.');
 expect([...container.querySelectorAll('button')].find(b=>b.textContent==='Confirmar entrega').disabled).toBe(true);
});
