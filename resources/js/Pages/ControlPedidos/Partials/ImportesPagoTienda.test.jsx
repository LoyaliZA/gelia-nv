// @vitest-environment happy-dom
import React, { act } from 'react';
import { createRoot } from 'react-dom/client';
import { beforeEach, afterEach, it, expect, vi } from 'vitest';
import ImportesPagoTienda from './ImportesPagoTienda';
const get=vi.hoisted(()=>vi.fn());
const post=vi.hoisted(()=>vi.fn());
const pagoProps=vi.hoisted(()=>({current:null}));
vi.mock('axios',()=>({default:{get,post}}));
vi.mock('./CambioModalidadPagoTienda',()=>({default:()=> <p>Control de cambios de modalidad</p>}));
vi.mock('./SeccionPagosExhibicion',()=>({default:(props)=>{pagoProps.current=props;return <p>Historial de pagos</p>;}}));
let root,container;
const callbacks={onTotalMercancia:vi.fn(),onAplicaSaf:vi.fn(),onSaldoFavor:vi.fn(),onSafAplicaciones:vi.fn()};
const props={pedidoId:9,clienteId:8,solicitud:{cobertura_tienda:{tipo:'transferencia'}},editable:true,totalMercancia:8999,aplicaSaf:true,saldoFavor:500,safAplicaciones:[{saf_credito_id:1,monto:500}],importesListos:true,...callbacks};
const render=(extra={})=>act(async()=>{ root.render(<ImportesPagoTienda {...props} {...extra}/>); await Promise.resolve(); });
it('permite solicitar TIENDA MD sin facultad especial de departamento', async () => {
 await render({solicitud:{atencion_tienda:'prevista'},notaMismoDia:'Mismo día: transferencia reportada hasta las 12:15 (sáb).'});
 const check=[...container.querySelectorAll('input[type="checkbox"]')].find(input=>input.closest('label')?.textContent.includes('Mismo día:'));
 expect(check).toBeTruthy();expect(check.disabled).toBe(false);
 expect(container.textContent).toContain('hasta las 12:15 (sáb)');
 expect(container.textContent).not.toContain('Disponible para todos los departamentos');
});
beforeEach(()=>{globalThis.IS_REACT_ACT_ENVIRONMENT=true;globalThis.route=(name,id)=>`${name}/${id}`;get.mockResolvedValue({data:{disponible:0,creditos_usables:[]}});post.mockReset();Object.values(callbacks).forEach(fn=>fn.mockReset());container=document.createElement('div');document.body.append(container);root=createRoot(container);});
afterEach(async()=>{await act(()=>root.unmount());container.remove();});
it('muestra desglose y pendiente antes de la captura de pago y vincula la etiqueta al importe',async()=>{
 await render();
 expect(container.textContent.indexOf('Modalidad de cobertura')).toBeLessThan(container.textContent.indexOf('Importe pendiente por pagar'));
 expect(container.querySelector('label[for="total-mercancia-tienda"]').control).toBe(container.querySelector('#total-mercancia-tienda'));
 expect(pagoProps.current).toMatchObject({puedeRegistrar:true,formaPagoFija:'transferencia',etiquetaCubrir:'Cubrir pendiente'});
});
it('deja desactivar el saldo ya aplicado aunque el disponible restante sea cero',async()=>{
 await render();
 const input=[...container.querySelectorAll('input[type="checkbox"]')].find(i=>i.closest('label').textContent.includes('Saldo a favor'));
 expect(input.disabled).toBe(false);
 await act(()=>input.click());
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
 expect(container.textContent).not.toContain('queden guardados');
 await render({solicitud:{cobertura_tienda:{tipo:'credito'}},importesListos:false,totalMercancia:0});
 expect(container.textContent).toContain('El total a cubrir no está completo.');
 expect(container.textContent).not.toContain('queden guardados');
 await render({solicitud:{cobertura_tienda:{tipo:'transferencia'},atencion_tienda:'prevista'}});
 const select=container.querySelector('select');
 expect(select.disabled).toBe(true);
 expect(post).not.toHaveBeenCalled();
 expect(pagoProps.current.puedeRegistrar).toBe(true);
});
it('oculta cargos sin aplicación y pago al recoger no permite exhibición ni comprobante',async()=>{
 await render({solicitud:{cobertura_tienda:{tipo:'apartado'}},costos:{costo_envio:0,aplica_seguro:false,costo_seguro:0}});
 expect(container.textContent).not.toContain('Reexpedición');
 expect(container.textContent).not.toContain('Seguro');
 expect(container.textContent).toContain('Se paga en tienda');
 expect(pagoProps.current.puedeRegistrar).toBe(false);
 expect(pagoProps.current.ocultarSiVacio).toBe(true);
 expect(container.querySelector('input[type="checkbox"]').classList.contains('gelia-checkbox')).toBe(true);
});
it('conserva cargos existentes y usa la cookie actual sin adjuntar un token CSRF viejo',async()=>{
 const meta=document.createElement('meta');meta.name='csrf-token';meta.content='token-antiguo';document.head.append(meta);
 post.mockResolvedValue({data:{solicitud_preparacion:{cobertura_tienda:{tipo:'transferencia'}}}});
 await render({solicitud:{atencion_tienda:'prevista'},costos:{costo_envio:120,aplica_seguro:true,costo_seguro:20}});
 expect(container.textContent).toContain('Reexpedición');
 expect(container.textContent).toContain('Seguro');
 await act(async()=>{ [...container.querySelectorAll('button')].find(b=>b.textContent==='Guardar modalidad').click(); await Promise.resolve(); });
 expect(post.mock.lastCall[2].headers).not.toHaveProperty('X-CSRF-TOKEN');
 meta.remove();
});
it('guardar Pago al recoger adopta la respuesta y permite enviar la confirmación sin comprobante', async () => {
 const solicitud={area_revision:'preparacion_tienda',revision_disponibilidad:{estado:'sin_excepciones'}};
 const guardada={...solicitud,cobertura_tienda:{tipo:'apartado',requiere_confirmacion_pedido:true,vence_at:'2100-11-04T17:00:00-06:00'}};
 post.mockResolvedValueOnce({data:{solicitud_preparacion:guardada}}).mockResolvedValueOnce({data:{solicitud_preparacion:{...guardada,cobertura_tienda:{...guardada.cobertura_tienda,confirmacion_enviada_at:'2026-11-04T11:00:00-06:00'}}}});
 await render({solicitud});
 const select=container.querySelector('select');
 await act(()=>{select.value='apartado';select.dispatchEvent(new Event('change',{bubbles:true}));});
 await act(async()=>{[...container.querySelectorAll('button')].find(b=>b.textContent==='Guardar modalidad').click();await Promise.resolve();});
 const confirmar=[...container.querySelectorAll('button')].find(b=>b.textContent==='Enviar confirmación a tienda');
 expect(confirmar).toBeTruthy();expect(confirmar.disabled).toBe(false);
 await act(async()=>{confirmar.click();await Promise.resolve();});
 expect(post.mock.lastCall[0]).toBe('/control-pedidos/9/confirmar-pedido-tienda');
 expect(container.textContent).toContain('Enviada a tienda');
 expect(pagoProps.current.puedeRegistrar).toBe(false);
});
it('una modalidad guardada fuera de vigencia explica el bloqueo de confirmación', async () => {
 await render({solicitud:{area_revision:'preparacion_tienda',cobertura_tienda:{tipo:'apartado',vence_at:'2020-11-04T17:00:00-06:00'}}});
 const confirmar=[...container.querySelectorAll('button')].find(b=>b.textContent==='Enviar confirmación a tienda');
 expect(confirmar.disabled).toBe(true);
 expect(container.textContent).toContain('La vigencia terminó');
});

it('horarios apagados permiten confirmar aun con fecha y bloqueo guardados anteriormente', async () => {
 await render({solicitud:{area_revision:'preparacion_tienda',horarios_operativos:{activos:false},cobertura_tienda:{tipo:'apartado',vence_at:'2020-11-04T17:00:00-06:00',motivo_vigencia:'Cierre anterior'}}});
 expect([...container.querySelectorAll('button')].find(b=>b.textContent==='Enviar confirmación a tienda').disabled).toBe(false);
 expect(container.textContent).not.toContain('Cierre anterior');
 expect(container.textContent).not.toContain('Vigencia terminada');
});
it('horarios activos muestran un contenedor de alerta con días, límite y zona', async () => {
 await render({solicitud:{area_revision:'preparacion_tienda',horarios_operativos:{activos:true,detalle_apartado:'Lunes a viernes. Límite: 17:00. America/Mexico_City.'},cobertura_tienda:{tipo:'apartado',vence_at:'2100-11-04T17:00:00-06:00'}}});
 const alerta=container.querySelector('[role="alert"]');
 expect(alerta.textContent).toContain('Horario de confirmación');
 expect(alerta.textContent).toContain('17:00');
 expect(alerta.textContent).toContain('America/Mexico_City');
});

it('la confirmación queda bloqueada con importes pendientes o modalidad sin guardar', async () => {
 const solicitud={area_revision:'preparacion_tienda',cobertura_tienda:{tipo:'apartado',vence_at:'2100-11-04T17:00:00-06:00'}};
 await render({solicitud,importesListos:false});
 const boton=()=>[...container.querySelectorAll('button')].find(b=>b.textContent==='Enviar confirmación a tienda');
 expect(boton().disabled).toBe(true);
 await render({solicitud,importesListos:true});
 expect(boton().disabled).toBe(false);
 expect(container.querySelector('select').disabled).toBe(true);
 expect(container.textContent).toContain('Control de cambios de modalidad');
});
it('comunica la cobertura pendiente de transferencia y conserva la confirmación visible de una consulta reabierta', async () => {
 const onEstadoConfirmacion=vi.fn();
 await render({onEstadoConfirmacion});
 expect(onEstadoConfirmacion).toHaveBeenLastCalledWith(false);
 await render({onEstadoConfirmacion,solicitud:{area_revision:'preparacion_tienda',cobertura_tienda:{tipo:'apartado',vence_at:'2100-11-04T17:00:00-06:00'}},editable:false});
 const boton=[...container.querySelectorAll('button')].find(b=>b.textContent==='Enviar confirmación a tienda');
 expect(boton.disabled).toBe(true);
 expect(container.querySelector('[aria-label="Pago y remisión en PDV"]')).toBeTruthy();
});

it('transferencia espera cobertura completa sin bloqueos y guardar los cambios de Mismo día', async () => {
 const onEstadoConfirmacion=vi.fn();
 await render({onEstadoConfirmacion});
 expect(onEstadoConfirmacion).toHaveBeenLastCalledWith(false);
 await act(()=>pagoProps.current.onResumenChange({cubierto:true,bloqueos:[],pendiente:0.4,tolerancia_aplicada:0.44}));
 expect(onEstadoConfirmacion).toHaveBeenLastCalledWith(true);
 await act(()=>pagoProps.current.onResumenChange({cubierto:true,bloqueos:['Comprobante rechazado']}));
 expect(onEstadoConfirmacion).toHaveBeenLastCalledWith(false);
 await act(()=>pagoProps.current.onResumenChange({cubierto:true,bloqueos:[]}));
 const check=[...container.querySelectorAll('input[type="checkbox"]')].find(i=>i.closest('label').textContent.includes('Mismo día:'));
 expect(check.disabled).toBe(true);
});
