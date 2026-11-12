import fs from 'node:fs';import {Browser,save,assert} from './browser.mjs';
const extra=JSON.parse(fs.readFileSync('/var/www/html/storage/app/qa-pr34-extra.json','utf8'));
extra.realtime=extra.asincrono2;
const a=new Browser(),b=new Browser();const reporte=[];
async function observar(browser){
 await browser.wait("window.Echo?.connector?.pusher?.connection?.state==='connected'");
 await browser.js(`window.qaEventos=[];window.qaPeticiones=[];window.Echo.private('control-pedidos.operaciones').listen('.pedido.actualizado',e=>window.qaEventos.push({tiempo:performance.now(),...e}));window.axios.interceptors.response.use(r=>{window.qaPeticiones.push({url:r.config.url,method:r.config.method,tiempo:performance.now()});return r});`);
 await browser.wait("window.Echo.connector.channels['private-control-pedidos.operaciones']?.subscription?.subscribed===true");
}
try{
 await a.start('ventas');await b.start('auxiliar');
 await a.go('/control-pedidos?q=QA34-ASINCRONO2');await a.wait("document.body.innerText.includes('El auxiliar debe verificar el ingreso')");await a.js("[...document.querySelectorAll('summary')].find(e=>e.textContent.includes('Datos y documentos del recorrido')).click()");
 await observar(a);const antes=await a.js('return {url:location.href,tiempo:performance.now(),navigation:performance.getEntriesByType("navigation").map(e=>({type:e.type,start:e.startTime})),socket:window.Echo.socketId()}');await a.shot('realtime-asincrono-antes-1440');
 const aprobacion=await b.api(`/control-pedidos/auditar/${extra.realtime}/validar-pago`,'POST',{});assert(aprobacion.status===200,'No aprobó',aprobacion.status);const persistido=await b.api(`/control-pedidos/auditar/${extra.realtime}/detalle`);assert(persistido.data.pedido.pago_validado_at,'No quedó validado el pago',persistido.data.pedido.estatus);
 await a.wait("document.body.innerText.includes('Ingreso verificado por auxiliar')");
 const despues=await a.js('return {url:location.href,eventos:window.qaEventos,peticiones:window.qaPeticiones,navigation:performance.getEntriesByType("navigation").map(e=>({type:e.type,start:e.startTime})),socket:window.Echo.socketId()}');
 assert(despues.eventos.some(e=>e.pedido_id===extra.realtime),'No recibió evento real',despues);
 const e=despues.eventos.find(e=>e.pedido_id===extra.realtime);const refresco=despues.peticiones.find(r=>r.url.includes(`/${extra.realtime}/transferencia-tienda`)&&r.tiempo>=e.tiempo);
 assert(refresco&&refresco.tiempo-e.tiempo<3000,'No se distingue refresco realtime',despues);
 assert(antes.socket===despues.socket&&JSON.stringify(antes.navigation)===JSON.stringify(despues.navigation),'Navegación o reconexión inesperada',{antes,despues});
 const frames=await a.cmd('/log',{type:'performance'});const websocket=frames.map(x=>JSON.parse(x.message).message).filter(x=>x.method==='Network.webSocketFrameReceived'&&x.params.response.payloadData.includes('pedido.actualizado'));
 reporte.push({caso:'Ventas recibe aprobación de Auxiliar',antes,despues,latencia_ms:refresco.tiempo-e.tiempo,frames:websocket});save('realtime-asincrono',reporte);await a.shot('realtime-asincrono-despues-1440');

 console.log(JSON.stringify({resultado:'aprobado',latencia_ms:reporte[0].latencia_ms,frames:reporte[0].frames.length,cola:'database'}));
}finally{await a.close();await b.close();}
