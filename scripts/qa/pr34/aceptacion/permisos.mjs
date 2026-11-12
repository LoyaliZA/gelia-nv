import {Browser,fixtures,save,assert} from './browser.mjs';
const b=new Browser();const reporte=[];
try{
 await b.start('tienda');const tarea=await b.api('/control-pedidos/tienda/tareas/1');assert(tarea.status===200,'Sin acceso a tarea',tarea.status);
 for(const d of tarea.data.tarea.respuesta.documentos){const r=await b.api(d.url);assert(r.status===200,'No puede leer respuesta',d.url);reporte.push({url:d.url,status:r.status});}
 const ajeno=await b.api(`/control-pedidos/pedidos/5/documentos/${fixtures.pedidos['lectura-ajena'].documento_id}`);assert(ajeno.status===403,'Accedió a pedido ajeno',ajeno.status);
 const expediente=await b.api('/control-pedidos/1/disponibilidad-tienda');assert(expediente.status===403,'Se amplió acceso al expediente',expediente.status);
 const descargar=await b.api('/control-pedidos/tienda/tareas/1/solicitud/1');assert(descargar.status===200,'No lee pedido original',descargar.status);
 await b.go('/control-pedidos/tienda?q=QA34-CONSULTA');await b.wait("document.body.innerText.includes('Continuar preparación')");await b.click('Continuar preparación');await b.wait("document.body.innerText.includes('Respuesta enviada')");
 for(const width of [1440,1024,390]){await b.wait("[...document.querySelectorAll('.gelia-preparacion-modal img')].filter(i=>{const r=i.getBoundingClientRect();return r.width>0&&r.bottom>0&&r.top<innerHeight}).every(i=>i.complete&&i.naturalWidth>0)");await b.shot('permisos-corregidos-'+width,width);const imagenes=await b.js('return [...document.querySelectorAll(".gelia-preparacion-modal img")].filter(i=>{const r=i.getBoundingClientRect();return r.width>0&&r.bottom>0&&r.top<innerHeight}).map(i=>({src:i.src,cargada:i.complete&&i.naturalWidth>0}))');reporte.push({width,imagenes});assert(imagenes.every(i=>i.cargada),'Imagen rota',imagenes);}
 reporte.push({ajeno:ajeno.status,expediente:expediente.status,pedido_original:descargar.status,console:await b.cmd('/log',{type:'browser'})});save('permisos',reporte);console.log(JSON.stringify({resultado:'aprobado',lectura_asignada:true,ajeno:403,expediente:403}));
}finally{await b.close();}
