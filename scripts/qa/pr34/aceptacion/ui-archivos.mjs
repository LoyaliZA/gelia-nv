import {Browser,fixtures,save,assert} from './browser.mjs';
const a=new Browser(),b=new Browser();const id=5;
try{
 await a.start('ventas');await b.start('cedis');let p=(await a.api(`/control-pedidos/${id}/disponibilidad-tienda`)).data;
 let r=await a.api(`/control-pedidos/${id}/solicitud-tienda`,'POST',{area_revision:'control_pedidos',revision_esperada:p.revision_registro,base_captura:p.base_captura});assert(r.status===200,'No solicitó consulta',r);
 r=await b.api(`/control-pedidos/cedis/${id}/disponibilidad`,'POST',{confirmacion_explicita:1},[{name:'QA34-ui-conjunto.pdf',size:1024}]);assert(r.status===200,'No respondió',r);await b.close();
 await a.go('/control-pedidos?q=QA34-LECTURA-AJENA');await a.wait("document.querySelector('[aria-label=\"Cerrar detalle\"]')");await a.js("document.querySelector('[aria-label=\"Cerrar detalle\"]').click()");
 console.log('BOTONES',JSON.stringify(await a.js('return [...document.querySelectorAll("button")].map(x=>({texto:x.textContent.trim(),aria:x.getAttribute("aria-label"),title:x.title}))')));
 await a.click('Continuar');await a.wait("document.body.innerText.includes('Modificar pedido')");await a.click('Modificar pedido');
 console.log('INPUTS',JSON.stringify(await a.js('return {botones:[...document.querySelectorAll("button")].map(e=>e.textContent.trim()),inputs:[...document.querySelectorAll("input[type=file]")].map(e=>({aria:e.getAttribute("aria-label"),multiple:e.multiple}))}')));
 await a.wait("document.querySelector('input[aria-label=\"Adjuntar: Piezas a agregar\"]')");
 await a.js(`const dt=new DataTransfer();for(let i=0;i<2;i++){const bytes=new Uint8Array(6*1024*1024);bytes.fill(32);bytes.set(new TextEncoder().encode('%PDF-1.4\\n% Archivo ficticio UI '+i+'\\n%%EOF'));dt.items.add(new File([bytes],'QA34-ui-multiple-'+i+'.pdf',{type:'application/pdf'}));}const e=document.querySelector('input[aria-label="Adjuntar: Piezas a agregar"]');e.files=dt.files;e.dispatchEvent(new Event('change',{bubbles:true}));`);
 await a.wait("document.body.innerText.includes('2 archivos')");
 for(const width of [1440,1024,390])await a.shot('archivos-seleccion-multiple-'+width,width);
 await a.js(`window.qaLotes=[];window.axios.interceptors.request.use(c=>{if(c.url.includes('/anexo-tienda'))window.qaLotes.push({url:c.url,lote:c.data.get('lote'),solicitud_id:c.data.get('solicitud_id'),archivos:c.data.getAll('evidencias[]').map(f=>({name:f.name,bytes:f.size}))});return c});`);
 await a.click('Solicitar agregado');
 await a.wait("window.qaLotes.length===2 && ![...document.querySelectorAll('button')].some(e=>e.textContent.includes('Enviando'))");
 const final=await a.api(`/control-pedidos/${id}/disponibilidad-tienda`);const anexos=final.data.solicitud_preparacion.anexo_sin_remision;
 assert(anexos.length===1&&anexos[0].documentos.length===2,'No guardó dos archivos en una solicitud',anexos);
 const lotes=await a.js('return window.qaLotes');assert(lotes.length===2&&lotes[0].solicitud_id===lotes[1].solicitud_id,'No conservó identidad entre lotes',lotes);
 save('archivos-ui',{lotes,anexos,console:await a.cmd('/log',{type:'browser'})});console.log(JSON.stringify({resultado:'aprobado',archivos:2,lotes:lotes.length,bytes:12*1024*1024}));
}finally{await a.close();await b.close();}
