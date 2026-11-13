import fs from 'node:fs';import {Browser,save,assert} from './browser.mjs';
const extra=JSON.parse(fs.readFileSync('/var/www/html/storage/app/qa-pr34-extra.json','utf8'));
const a=new Browser(),b=new Browser();const reporte=[];
async function observar(browser){
 await browser.wait("window.Echo?.connector?.pusher?.connection?.state==='connected'");
 await browser.js(`window.qaEventos=[];window.qaPeticiones=[];window.Echo.private('control-pedidos.operaciones').listen('.pedido.actualizado',e=>window.qaEventos.push({tiempo:performance.now(),...e}));window.axios.interceptors.response.use(r=>{window.qaPeticiones.push({url:r.config.url,method:r.config.method,tiempo:performance.now()});return r});`);
 await browser.wait("window.Echo.connector.channels['private-control-pedidos.operaciones']?.subscription?.subscribed===true");
}
try{
 await a.start('auxiliar');await b.start('auxiliar');
 await a.go('/control-pedidos/auditar?q=QA34-CAPTURA2');await a.wait("document.body.innerText.includes('QA34-CAPTURA')");
 console.log('AUDITORIA',JSON.stringify(await a.js('return [...document.querySelectorAll("button")].map(x=>({texto:x.textContent.trim(),aria:x.getAttribute("aria-label"),title:x.title}))')));
 await a.js(`const btn=[...document.querySelectorAll('button')].find(e=>e.title==='Revisar'||e.title==='Revisar pedido'||e.getAttribute('aria-label')==='Revisar'||e.textContent.trim()==='Revisar pedido');if(!btn)throw Error('No existe Revisar');btn.click()`);
 await a.wait("document.querySelector('#auditoria-numero-remision')");await observar(a);
 const el=await a.cmd('/element',{using:'css selector',value:'#auditoria-numero-remision'});await a.cmd('/element/'+el['element-6066-11e4-a52e-4f735466cecf']+'/value',{text:'QA34-CAPTURA-SIN-GUARDAR'});
 const aprobado2=await b.api(`/control-pedidos/auditar/${extra.captura2}/validar-pago`,'POST',{});assert(aprobado2.status===200,'No aprobó captura',aprobado2.status);
 await a.wait(`window.qaEventos.some(e=>e.pedido_id===${extra.captura2})`);
 await new Promise(r=>setTimeout(r,700));
 const pausa=await a.js('return {valor:document.querySelector("#auditoria-numero-remision").value,eventos:window.qaEventos,peticiones:window.qaPeticiones}');assert(pausa.valor==='QA34-CAPTURA-SIN-GUARDAR','Sobrescribió captura',pausa);
 assert(!pausa.peticiones.some(r=>r.url.includes('/auditar/'+extra.captura2+'/detalle')),'Refrescó auditoría durante captura',pausa);
 await a.shot('realtime-captura-conservada-1440');
 await a.js(`const e=document.querySelector('#auditoria-numero-remision');const setter=Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set;setter.call(e,'');e.dispatchEvent(new Event('input',{bubbles:true}));`);
 await a.wait(`window.qaPeticiones.some(r=>r.url.includes('/auditar/${extra.captura2}/detalle'))`);
 const reanudado=await a.js('return {valor:document.querySelector("#auditoria-numero-remision").value,eventos:window.qaEventos,peticiones:window.qaPeticiones,texto:document.body.innerText.slice(-1700)}');
 reporte.push({caso:'Captura pendiente en Auditoría',pausa,reanudado});save('realtime-captura',reporte);
 console.log(JSON.stringify({resultado:'aprobado',captura_conservada:true}));
}finally{await a.close();await b.close();}
