// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, it, expect, vi } from 'vitest';
import ImportesPagoTienda from './ImportesPagoTienda';
const get=vi.hoisted(()=>vi.fn());
const post=vi.hoisted(()=>vi.fn());
const pagoProps=vi.hoisted(()=>({current:null}));
vi.mock('axios',()=>({default:{get,post}}));
vi.mock('./SeccionPagosExhibicion',()=>({default:(props)=>{pagoProps.current=props;return <p>Historial de pagos</p>;}}));
let root,container;
const callbacks={onTotalMercancia:vi.fn(),onAplicaSaf:vi.fn(),onSaldoFavor:vi.fn(),onSafAplicaciones:vi.fn()};
const props={pedidoId:9,clienteId:8,solicitud:{cobertura_tienda:{tipo:'transferencia'}},editable:true,totalMercancia:8999,aplicaSaf:true,saldoFavor:500,safAplicaciones:[{saf_credito_id:1,monto:500}],importesListos:true,...callbacks};
const render=(extra={})=>act(()=>root.render(<ImportesPagoTienda {...props} {...extra}/>));
beforeEach(()=>{globalThis.IS_REACT_ACT_ENVIRONMENT=true;globalThis.route=(name,id)=>`${name}/${id}`;get.mockResolvedValue({data:{disponible:0,creditos_usables:[]}});post.mockReset();Object.values(callbacks).forEach(fn=>fn.mockReset());container=document.createElement('div');document.body.append(container);root=createRoot(container);});
afterEach(async()=>{await act(()=>root.unmount());container.remove();});
it('muestra desglose y pendiente antes de la captura de pago y vincula la etiqueta al importe',async()=>{
 await render();
 expect(container.textContent.indexOf('Importe pendiente por pagar')).toBeLessThan(container.textContent.indexOf('Modalidad de cobertura'));
 expect(container.querySelector('label[for="total-mercancia-tienda"]').control).toBe(container.querySelector('#total-mercancia-tienda'));
 expect(pagoProps.current).toMatchObject({puedeRegistrar:true,formaPagoFija:'transferencia',etiquetaCubrir:'Cubrir pendiente'});
});
it('deja desactivar el saldo ya aplicado aunque el disponible restante sea cero',async()=>{
 await render();
 const checkbox=container.querySelector('input[type="checkbox"]');
 expect(checkbox.disabled).toBe(false);
 await act(()=>checkbox.click());
 expect(callbacks.onAplicaSaf).toHaveBeenCalledWith(false);
 expect(callbacks.onSafAplicaciones).toHaveBeenCalledWith([]);
});
it('una consulta reabierta conserva historial y bloquea el registro de otro pago',async()=>{
 await render({editable:false});
 expect(container.textContent).toContain('Historial de pagos');
 expect(pagoProps.current.puedeRegistrar).toBe(false);
 expect([...container.querySelectorAll('button')].some(b=>b.textContent==='Guardar modalidad')).toBe(false);
});
it('crédito omite captura y seleccionar modalidad no la guarda automáticamente',async()=>{
 await render({solicitud:{cobertura_tienda:{tipo:'credito'}}});
 expect(pagoProps.current.puedeRegistrar).toBe(false);
 expect(container.textContent).toContain('no pide una exhibición');
 await render({solicitud:{cobertura_tienda:{tipo:'transferencia'},atencion_tienda:'prevista'}});
 const select=container.querySelector('select');
 await act(()=>{select.value='apartado';select.dispatchEvent(new Event('change',{bubbles:true}));});
 expect(post).not.toHaveBeenCalled();
 expect(pagoProps.current.puedeRegistrar).toBe(false);
});
