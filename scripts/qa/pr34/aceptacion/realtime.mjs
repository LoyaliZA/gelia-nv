import fs from 'node:fs';import {Browser,save,assert} from './browser.mjs';
const extra=JSON.parse(fs.readFileSync('/var/www/html/storage/app/qa-pr34-extra.json','utf8'));
const a=new Browser(),b=new Browser();const reporte=[];
async function observar(browser){
 await browser.wait("window.Echo?.connector?.pusher?.connection?.state==='connected'");
 await browser.js(`window.qaEventos=[];window.qaPeticiones=[];window.Echo.private('control-pedidos.operaciones').listen('.pedido.actualizado',e=>window.qaEventos.push({tiempo:performance.now(),...e}));window.axios.interceptors.response.use(r=>{window.qaPeticiones.push({url:r.config.url,method:r.config.method,tiempo:performance.now()});return r});`);
 await browser.wait("window.Echo.connector.channels['private-control-pedidos.operaciones']?.subscription?.subscribed===true");
}
try{
 await a.start('ventas');await b.start('auxiliar');
 await a.go('/control-pedidos?q=QA34-REALTIME');await a.wait("document.body.innerText.includes('El auxiliar debe verificar el ingreso')");await a.js("[...document.querySelectorAll('summary')].find(e=>e.textContent.includes('Datos y documentos del recorrido')).click()");
 await observar(a);const antes=await a.js('return {url:location.href,tiempo:performance.now(),navigation:performance.getEntriesByType("navigation").map(e=>({type:e.type,start:e.startTime})),socket:window.Echo.socketId()}');await a.shot('realtime-antes-1440');
 const aprobacion=await b.api(`/control-pedidos/auditar/${extra.realtime}/validar-pago`,'POST',{});assert(aprobacion.status===200,'No aprobó',aprobacion.status);
 await a.wait("document.body.innerText.includes('Ingreso verificado por auxiliar')");
 const despues=await a.js('return {url:location.href,eventos:window.qaEventos,peticiones:window.qaPeticiones,navigation:performance.getEntriesByType("navigation").map(e=>({type:e.type,start:e.startTime})),socket:window.Echo.socketId()}');
 assert(despues.eventos.some(e=>e.pedido_id===extra.realtime),'No recibió evento real',despues);
 const e=despues.eventos.find(e=>e.pedido_id===extra.realtime);const refresco=despues.peticiones.find(r=>r.url.includes(`/${extra.realtime}/transferencia-tienda`)&&r.tiempo>=e.tiempo);
 assert(refresco&&refresco.tiempo-e.tiempo<3000,'No se distingue refresco realtime',despues);
 assert(antes.socket===despues.socket&&JSON.stringify(antes.navigation)===JSON.stringify(despues.navigation),'Navegación o reconexión inesperada',{antes,despues});
 const frames=await a.cmd('/log',{type:'performance'});const websocket=frames.map(x=>JSON.parse(x.message).message).filter(x=>x.method==='Network.webSocketFrameReceived'&&x.params.response.payloadData.includes('pedido.actualizado'));
 reporte.push({caso:'Ventas recibe aprobación de Auxiliar',antes,despues,latencia_ms:refresco.tiempo-e.tiempo,frames:websocket});save('realtime',reporte);await a.shot('realtime-despues-1440');
 await a.close();await a.start('auxiliar');
 await a.go('/control-pedidos/auditar?q=QA34-CAPTURA');await a.wait("document.body.innerText.includes('QA34-CAPTURA')");
 console.log('AUDITORIA',JSON.stringify(await a.js('return [...document.querySelectorAll("button")].map(x=>({texto:x.textContent.trim(),aria:x.getAttribute("aria-label"),title:x.title}))')));
 await a.js(`const btn=[...document.querySelectorAll('button')].find(e=>e.title==='Revisar'||e.title==='Revisar pedido'||e.getAttribute('aria-label')==='Revisar'||e.textContent.trim()==='Revisar pedido');if(!btn)throw Error('No existe Revisar');btn.click()`);
 await a.wait("document.querySelector('#auditoria-numero-remision')");await observar(a);
 const el=await a.cmd('/element',{using:'css selector',value:'#auditoria-numero-remision'});await a.cmd('/element/'+el['element-6066-11e4-a52e-4f735466cecf']+'/value',{text:'QA34-CAPTURA-SIN-GUARDAR'});
 const aprobado2=await b.api(`/control-pedidos/auditar/${extra.captura}/validar-pago`,'POST',{});assert(aprobado2.status===200,'No aprobó captura',aprobado2.status);
 await a.wait(`window.qaEventos.some(e=>e.pedido_id===${extra.captura})`);
 await new Promise(r=>setTimeout(r,700));
 const pausa=await a.js('return {valor:document.querySelector("#auditoria-numero-remision").value,eventos:window.qaEventos,peticiones:window.qaPeticiones}');assert(pausa.valor==='QA34-CAPTURA-SIN-GUARDAR','Sobrescribió captura',pausa);
 assert(!pausa.peticiones.some(r=>r.url.includes('/auditar/'+extra.captura+'/detalle')),'Refrescó auditoría durante captura',pausa);
 await a.shot('realtime-captura-conservada-1440');
 await a.js(`const e=document.querySelector('#auditoria-numero-remision');const setter=Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set;setter.call(e,'');e.dispatchEvent(new Event('input',{bubbles:true}));`);
 await a.wait(`window.qaPeticiones.some(r=>r.url.includes('/auditar/${extra.captura}/detalle'))`);
 const reanudado=await a.js('return {valor:document.querySelector("#auditoria-numero-remision").value,eventos:window.qaEventos,peticiones:window.qaPeticiones,texto:document.body.innerText.slice(-1700)}');
 reporte.push({caso:'Captura pendiente en Auditoría',pausa,reanudado});save('realtime',reporte);
 console.log(JSON.stringify({resultado:'aprobado',latencia_ms:refresco.tiempo-e.tiempo,eventos:despues.eventos.length,frames:websocket.length,captura_conservada:true}));
}finally{await a.close();await b.close();}
