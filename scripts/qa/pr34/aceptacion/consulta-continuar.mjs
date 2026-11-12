import {Browser,fixtures,save,assert} from './browser.mjs';
const b=new Browser(), ventas=new Browser();const reporte=[];
try {
 await b.start('tienda');await b.go('/control-pedidos/tienda');
 await b.wait("document.body.innerText.includes('QA34-CONSULTA')");
 console.log('BANDEJA',JSON.stringify(await b.js('return [...document.querySelectorAll("button")].map(b=>b.textContent.trim()).filter(Boolean)')));
 await b.click('Continuar preparación');
 await b.wait("[...document.querySelectorAll('button')].some(b=>b.textContent.trim()==='Enviar respuesta')");
 console.log('MODAL',JSON.stringify(await b.js('return {texto:document.body.innerText.slice(-3500),inputs:[...document.querySelectorAll("input")].map(e=>({type:e.type,id:e.id,name:e.name})),echo:window.Echo?.connector?.pusher?.connection?.state}')));
 for(const width of [1440,1024,390]) await b.shot('consulta-modal-inicial-'+width,width);
 await b.js("document.querySelector('input[name=estado-disponibilidad-cedis]').click()");
 await b.wait("document.querySelector('input[type=file]')");
 await b.js(`const bytes=Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jL1sAAAAASUVORK5CYII='),c=>c.charCodeAt(0));const dt=new DataTransfer();dt.items.add(new File([bytes],'QA-PR34-conjunto.png',{type:'image/png'}));const el=document.querySelector('input[aria-label="Adjuntar: Captura del pedido"]');el.files=dt.files;el.dispatchEvent(new Event('change',{bubbles:true}));`);
 await b.wait("document.body.innerText.includes('1 archivo')");
 await b.click('Enviar respuesta');
 await b.wait("document.body.innerText.includes('Respuesta enviada')");
 const inicial=await b.api('/control-pedidos/tienda/tareas/1');reporte.push({paso:'respuesta inicial por interfaz',estado:inicial.data.tarea.respuesta});save('consulta-resultados',reporte);
 for(const width of [1440,1024,390]){await b.js("const e=document.querySelector('.gelia-modal-body');e.scrollTop=e.scrollHeight");await b.shot('consulta-respondida-'+width,width);}
 await ventas.start('ventas');
 const anexo=await ventas.api('/control-pedidos/1/anexo-tienda','POST',{entrada:'documento',solicitud_id:crypto.randomUUID(),lote:'0'},[{name:'QA-PR34-anexo.pdf',size:1024}]);
 reporte.push({paso:'anexo',...anexo});save('consulta-resultados',reporte);assert(anexo.status===200,'Falló anexo',anexo);
 const actual=await ventas.api('/control-pedidos/1/disponibilidad-tienda');
 const revision=actual.data.solicitud_preparacion.revision_disponibilidad;
 const respuesta=await b.api('/control-pedidos/tienda/tareas/1/disponibilidad','POST',{confirmacion_explicita:1,actualizacion_id:revision.actualizacion_id,'cambios_revisados[0][tipo]':'anexo','cambios_revisados[0][indice]':0,'cambios_revisados[0][resultado]':'correcto'},[{name:'QA-PR34-conjunto-actualizado.pdf',size:2048}]);
 reporte.push({paso:'respuesta actualización HTTP real',...respuesta});save('consulta-resultados',reporte);assert(respuesta.status===200,'No respondió actualización',respuesta);
 const cierre=await ventas.api('/control-pedidos/1/cerrar-consulta','POST',{});reporte.push({paso:'cerrar consulta',...cierre});save('consulta-resultados',reporte);assert(cierre.status===200,'No cerró consulta',cierre);
 const estadoFinal=await ventas.api('/control-pedidos/1/disponibilidad-tienda');save('consulta-estado-final',estadoFinal);
 console.log(JSON.stringify({resultado:'consulta y actualización cerradas',consulta_cerrada:estadoFinal.data.consulta_cerrada,console:await b.cmd('/log',{type:'browser'})}));
}finally{await b.close();await ventas.close();}
