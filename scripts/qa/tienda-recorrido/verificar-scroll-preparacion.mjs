// Reproducción aislada del modal real; solo datos ficticios, sin escrituras operativas.
import fs from 'node:fs';
import http from 'node:http';
const origen='http://laravel.test', proxy='http://laravel.test:9193', selenium='http://selenium:4444';
const destino='/tmp/gelia-scroll-preparacion-sail';fs.mkdirSync(destino,{recursive:true});
const htmlBase=await(await fetch(origen+'/login')).text();
const paginaBase=JSON.parse(htmlBase.match(/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/s)[1]);
const status=(await(await fetch(selenium+'/status')).json()).value;
if(status.nodes.some(n=>n.slots.some(s=>s.session)))throw Error('Selenium ocupado');
const tarea={id:77,estado:'EN_ATENCION',estado_label:'Pendiente de respuesta',version:1,observaciones_solicitud:'Indicaciones de ejemplo. '.repeat(100),puede_preparar:true,responsable:{id:999999},almacen:{id:2,nombre:'Sucursal de ejemplo'},productos:[],pedido:{id:999,folio:'PEDIDO-EJEMPLO',cliente_nombre:'Cliente ficticio',area_revision:'preparacion_tienda',horarios_activos:false}};
const servidor=http.createServer(async(req,res)=>{try{
 if(req.url.startsWith('/qa')){
  const pagina=structuredClone(paginaBase);pagina.component='ControlPedidos/Tienda/Partials/ModalPreparacionTienda';pagina.url='/qa';
  Object.assign(pagina.props,{tareaId:77,auth:{user:{id:999999,name:'Auxiliar ficticio',roles:['Super Admin'],permissions:[]},webpush:{enabled:false}}});
  const init=`<script>window.qaErrores=[];window.addEventListener('error',e=>window.qaErrores.push(e.message));localStorage.setItem('theme', 'dark');const original=window.route;window.route=(...a)=>{const v=original(...a);if(typeof v==='string'&&v.startsWith('http')){const u=new URL(v);return location.origin+u.pathname+u.search}return v};</script>`;
  res.writeHead(200,{'Content-Type':'text/html'});res.end(htmlBase.replace('</head>',init+'</head>').replace(/(<script[^>]*data-page="app"[^>]*>).*?(<\/script>)/s,(_,a,b)=>a+JSON.stringify(pagina).replace(/</g,'\\u003c')+b).replaceAll('http://localhost:9080',proxy).replaceAll(origen+'/',proxy+'/'));return;
 }
 if(req.url.startsWith('/build/')){const r=await fetch(origen+req.url);res.writeHead(r.status,{'Content-Type':r.headers.get('content-type')});res.end(Buffer.from(await r.arrayBuffer()));return;}
 if(req.method!=='GET')throw Error('La comprobación no permite guardar');
 res.writeHead(200,{'Content-Type':'application/json'});res.end(JSON.stringify(req.url.includes('/tienda/tareas/77')?{tarea,documentos_solicitud:[],documentos:[],requisitos:{}}:{data:[],conversaciones:[],notificaciones:[],unread_count:0}));
}catch(e){res.writeHead(500);res.end(String(e));}});
await new Promise(r=>servidor.listen(9193,'0.0.0.0',r));
let sessionId;
async function cmd(path,body,method='POST'){const r=await fetch(selenium+'/session'+(sessionId?'/'+sessionId:'')+path,{method,...(body?{headers:{'Content-Type':'application/json'},body:JSON.stringify(body)}:{})});const d=await r.json();if(d.value?.error)throw Error(JSON.stringify(d.value));return d.value;}
const js=script=>cmd('/execute/sync',{script,args:[]});
try{
 sessionId=(await cmd('',{capabilities:{alwaysMatch:{browserName:'chrome','goog:chromeOptions':{args:['--headless=new','--no-sandbox','--disable-dev-shm-usage']}}}})).sessionId;
 const reporte=[];
 for(const size of [{width:1440,height:800},{width:1024,height:600},{width:390,height:844}]){
  await cmd('/goog/cdp/execute',{cmd:'Emulation.setDeviceMetricsOverride',params:{...size,deviceScaleFactor:1,mobile:size.width<600}});
  await cmd('/url',{url:proxy+'/qa'});
  const listo=await cmd('/execute/async',{script:`const done=arguments[arguments.length-1];let n=0;function ver(){if([...document.querySelectorAll('button')].some(b=>b.textContent.includes('Enviar respuesta')))done(true);else if(++n>100)done(false);else setTimeout(ver,100)}ver()`,args:[]});
  if(!listo)throw Error('No se abrió el modal: '+await js('return JSON.stringify({texto:document.body.innerText,errores:window.qaErrores})'));
  const medir=()=>js(`const b=[...document.querySelectorAll('button')].find(b=>b.textContent.includes('Enviar respuesta'));document.querySelectorAll('*').forEach(e=>{if(['auto','scroll'].includes(getComputedStyle(e).overflowY))e.scrollTop=e.scrollHeight});const r=b.getBoundingClientRect();let e=b,padres=[];while(e){const s=getComputedStyle(e),c=e.getBoundingClientRect();if(s.overflowY!=='visible')padres.push({clase:e.className,alto:e.clientHeight,total:e.scrollHeight,top:c.top,bottom:c.bottom,overflow:s.overflowY});e=e.parentElement}return {boton:{top:r.top,bottom:r.bottom},visible:r.top>=0&&r.bottom<=innerHeight&&document.elementFromPoint(r.x+r.width/2,r.y+r.height/2)?.closest('button')===b,padres,errores:window.qaErrores}`);
  const antes=await medir();
  
  const despues=await medir();reporte.push({size,antes,despues});
  fs.writeFileSync(destino+'/'+size.width+'.png',Buffer.from(await cmd('/screenshot',null,'GET'),'base64'));
 }
 fs.writeFileSync(destino+'/reporte.json',JSON.stringify(reporte,null,2));console.log(JSON.stringify(reporte.map(({size,despues})=>({size,...despues})),null,2));
 if(!process.env.QA_BASELINE&&reporte.some(r=>!r.despues.visible||r.despues.errores.length))throw Error('Acción final inaccesible');
}finally{if(sessionId)await cmd('',null,'DELETE');await new Promise(r=>servidor.close(r));}
