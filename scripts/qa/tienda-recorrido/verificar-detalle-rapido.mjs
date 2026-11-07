// Build real y Selenium con documentos, catálogo y transporte ficticios. No escribe pedidos.
import fs from 'node:fs';
import http from 'node:http';
const origen = 'http://laravel.test';
const proxy = 'http://laravel.test:9192';
const selenium = 'http://selenium:4444';
const destino = '/tmp/gelia-detalle-rapido-qa';
fs.mkdirSync(destino, { recursive: true });
const htmlBase = await (await fetch(`${origen}/login`)).text();
const paginaBase = JSON.parse(htmlBase.match(/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/s)[1]);
const productos = [1, 2, 3, 4].map(i => ({ id: i, sku: `SKU-${i}`, descripcion: `Perfume de ejemplo ${i} · presentación de catálogo`, codigo_barras: `75000000000${i}` }));
const status = (await (await fetch(`${selenium}/status`)).json()).value;
if (status.nodes.some(n => n.slots.some(s => s.session))) throw new Error('Selenium está ocupado.');
const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="800"><rect width="600" height="800" fill="white"/><rect x="50" y="160" width="500" height="100" fill="lightgray"/><rect x="50" y="290" width="500" height="100" fill="lightgray"/><rect x="50" y="420" width="500" height="100" fill="lightgray"/><text x="35" y="70" font-size="30">CAPTURA DE EJEMPLO</text></svg>';
const documento = { id: 1, nombre_original: 'pedido-ejemplo.svg', mime_type: 'image/svg+xml', url: 'data:image/svg+xml,' + encodeURIComponent(svg) };
const servidor = http.createServer(async (req, res) => {
    try {
        if (req.url.startsWith('/qa?')) {
            const params = new URL(req.url, proxy).searchParams; const tema = params.get('tema'); const vista = params.get('vista');
            const pagina = structuredClone(paginaBase);
            pagina.component = 'ControlPedidos/Partials/PanelRespuestaDisponibilidad'; pagina.url = '/qa';
            pagina.props.auth.user = { id: 999999, name: 'Usuario ficticio', email: 'qa@example.test', roles: [], permissions: ['control_pedidos.cedis'] };
            pagina.props.auth.webpush = { enabled: false };
            Object.assign(pagina.props, { modo: 'dialogo', abierto: true, tomada: true, puedeResponder: true, pedido: { id: 99, folio_remision: '2', cliente: { nombre: 'Cliente ficticio de revisión' }, sucursal_destino: { id: 2, nombre: 'Carmen Cadena' } }, documentos: [documento] });
            const revision = {estado:'sin_excepciones',actualizacion_pendiente:true,actualizacion_id:'123e4567-e89b-42d3-a456-426614174000'};
            const solicitud = {revision_disponibilidad:revision,
                acciones_disponibilidad:{8:{accion:'sustituir',revision_id:8,descripcion:'PERFUME ORIGINAL DE EJEMPLO 100 ML',sku:'SKU-ANTERIOR',por:'Ventas de ejemplo'}},
                anexo_sin_remision:[{entrada:'documento',documento_id:1,descripcion:'Piezas anexadas en captura',pendiente_revision:true},
                    {entrada:'catalogo',sustituye_revision_id:8,sku:'SKU-NUEVO',descripcion:'PERFUME NUEVO DE EJEMPLO CON NOMBRE LARGO 100 ML',cantidad:2}],
                retiro_piezas:[{entrada:'documento',estado:'pendiente',documentos_piezas:[{id:2}]}]};
            Object.assign(pagina.props,{revision,pedido:{...pagina.props.pedido,solicitud_preparacion:solicitud,documentos:[documento,{...documento,id:2,nombre_original:'piezas-a-retirar.svg'}]}});
            if(vista==='tienda') {
                pagina.component='ControlPedidos/Tienda/Partials/AjustesSolicitadosTienda';
                pagina.props.tarea={id:77,estado:'RESPONDIDA',puede_preparar:true,almacen:{id:1,nombre:'Sucursal de ejemplo'},pedido:{...solicitud,area_revision:'preparacion_tienda',id:99,documentos:[documento,{...documento,id:2,nombre_original:'piezas-a-retirar.svg'}],anexos_pendientes:[{indice:0,entrada:'documento'}],retiros:[]}};
            }
            if(vista==='respuesta') {
                pagina.component='ControlPedidos/Partials/RespuestaDisponibilidadPedido';
                Object.assign(pagina.props,{titulo:'Respuesta de disponibilidad',origen:'CEDIS',estado:'sin_excepciones',responsable:'Auxiliar de ejemplo',generales:[{...documento,id:2,nombre_original:'captura-actual.svg'}],anteriores:[{...documento,id:1,nombre_original:'captura-original.svg'}]});
            }
            const init = `<script>localStorage.setItem('theme',${JSON.stringify(tema)});localStorage.setItem('theme_glass','true');document.documentElement.classList.add('glass-active');window.qaErrores=[];window.addEventListener('error',e=>window.qaErrores.push(e.message));window.addEventListener('unhandledrejection',e=>window.qaErrores.push(String(e.reason)));</script>`;
            const html = htmlBase.replace('<html lang="es">', `<html lang="es" class="${tema === 'dark' ? 'dark' : ''}">`).replace('</head>', `${init}</head>`).replace(/(<script[^>]*data-page="app"[^>]*>).*?(<\/script>)/s, (_, a, b) => a + JSON.stringify(pagina).replace(/</g, '\\u003c') + b).replaceAll('http://localhost:9080', proxy).replaceAll(`${origen}/`, `${proxy}/`);
            res.writeHead(200, { 'Content-Type': 'text/html' }); res.end(html); return;
        }
        if (req.url.includes('productos')) { res.writeHead(200, { 'Content-Type': 'application/json' }); res.end(JSON.stringify({ data: productos, current_page: 1, last_page: 1 })); return; }
        if (!req.url.startsWith('/build/')) { res.writeHead(200, { 'Content-Type': 'application/json' }); res.end('{"data":[],"conversaciones":[],"notificaciones":[],"unread_count":0}'); return; }
        const recurso = await fetch(`${origen}${req.url}`); res.writeHead(recurso.status, { 'Content-Type': recurso.headers.get('content-type') || 'application/octet-stream' }); res.end(Buffer.from(await recurso.arrayBuffer()));
    } catch (e) { res.writeHead(500); res.end(String(e)); }
});
await new Promise(resolve => servidor.listen(9192, '0.0.0.0', resolve));
let sessionId;
const reporte = [];
let capturasTotales = 0;
async function comando(path, body, method = 'POST') {
    const r = await fetch(`${selenium}/session${sessionId ? `/${sessionId}` : ''}${path}`, { method, ...(body ? { headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : {}) });
    const respuesta = await r.json(); if (respuesta.value?.error) throw new Error(JSON.stringify(respuesta.value)); return respuesta.value;
}
const ejecutar = script => comando('/execute/sync', { script, args: [] });
async function esperar(script) {
    const ok = await comando('/execute/async', { script: `const done=arguments[arguments.length-1];let n=0;function revisar(){if(${script})done(true);else if(++n>100)done(false);else setTimeout(revisar,100)}revisar();`, args: [] });
    if (!ok) throw new Error(`No se cumplió: ${script}`);
}
async function click(texto) { await ejecutar(`const b=[...document.querySelectorAll('button')].find(b=>b.textContent.trim()===${JSON.stringify(texto)});if(!b)throw Error('Botón ausente');b.click();`); }
async function foto(label, cantidad = 1, camara = false) {
    await comando('/execute/async', { script: `const done=arguments[arguments.length-1];const c=document.createElement('canvas');c.width=320;c.height=220;const ctx=c.getContext('2d');ctx.fillStyle='lightgray';ctx.fillRect(0,0,320,220);ctx.fillStyle='navy';ctx.fillRect(120,40,80,160);c.toBlob(blob=>{const input=document.querySelector('input[aria-label="${camara ? 'Tomar foto' : 'Adjuntar'}: ${label}"]');const dt=new DataTransfer();for(let i=0;i<${cantidad};i++)dt.items.add(new File([blob],'${label === 'Captura del pedido' ? 'captura' : 'defecto'}-'+i+'.png',{type:'image/png'}));input.files=dt.files;input.dispatchEvent(new Event('change',{bubbles:true}));done(true);});`, args: [] });
}
async function captura(nombre) {
    capturasTotales += 1;
    await esperar('!document.getAnimations().some(a=>a.playState==="running" && Number.isFinite(a.effect?.getComputedTiming().endTime))');
    fs.writeFileSync(`${destino}/${nombre}.png`, Buffer.from(await comando('/screenshot', null, 'GET'), 'base64'));
}
try {
 const sesion=await comando('',{capabilities:{alwaysMatch:{browserName:'chrome','goog:chromeOptions':{args:['--headless=new','--no-sandbox','--disable-dev-shm-usage']}}}});sessionId=sesion.sessionId;
 for(const tamaño of [{nombre:'escritorio',width:1440,height:1000,tema:'dark'},{nombre:'modal',width:475,height:932,tema:'dark'},{nombre:'movil',width:390,height:844,tema:'light'}]) {
  await comando('/goog/cdp/execute',{cmd:'Emulation.setDeviceMetricsOverride',params:{width:tamaño.width,height:tamaño.height,deviceScaleFactor:1,mobile:tamaño.width<600}});
  for(const vista of ['cedis','tienda']) {
   await comando('/url',{url:`${proxy}/qa?tema=${tamaño.tema}&vista=${vista}`});
   await esperar('document.body.textContent.includes("Registrar detalle")');
   if(await ejecutar('return /\\bincidencias?\\b/i.test(document.body.textContent)'))throw Error('Terminología anterior visible');
   await captura(`${tamaño.nombre}-${vista}-consulta`);
   await ejecutar(`const card=document.querySelector('[data-cambio="anexo-0"]');[...card.querySelectorAll('button')].find(b=>b.textContent==='Registrar detalle').click()`);
   await esperar(`!!document.querySelector('[aria-label="Registrar detalle de productos"]')`);
   await captura(`${tamaño.nombre}-${vista}-seleccion`);
   if(tamaño.nombre==='modal' && vista==='cedis') {
    await ejecutar(`document.querySelector('[aria-label="Registrar detalle de productos"] button[aria-label="Escanear producto"]').click()`);
    await esperar('!!document.querySelector(".gelia-escaner-overlay")');
    const capa = await ejecutar(`return {escaner:Number(getComputedStyle(document.querySelector('.gelia-escaner-overlay')).zIndex),seleccion:Number(getComputedStyle(document.querySelector('.consulta-seleccion-detalle').parentElement).zIndex)}`);
    if(capa.escaner<=capa.seleccion)throw Error('Escáner detrás de la selección');
    await ejecutar("document.querySelector('.gelia-escaner-overlay button').click()");
    await esperar('!document.querySelector(".gelia-escaner-overlay")');
   }
   await ejecutar("const original=window.route;window.route=(name,...args)=>name==='gestion_interna.productos.buscar'?location.origin+'/qa/productos':original(name,...args);document.querySelector('#detalle-rapido-producto').focus()");
   await esperar('document.querySelector(".selector-productos-lista")?.textContent.includes("SKU-1")');
   await ejecutar("document.querySelector('.selector-productos-lista button').click()");
   await esperar(`document.querySelectorAll('[aria-label="Piezas seleccionadas"] li').length===1`);
   await ejecutar(`const m=document.querySelector('[aria-label="Registrar detalle de productos"]');[...m.querySelectorAll('button')].find(b=>b.textContent==='Sin catálogo').click()`);
   await ejecutar(`const input=document.querySelector('#detalle-rapido-descripcion');Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value').set.call(input,'Pieza descrita de la captura');input.dispatchEvent(new Event('input',{bubbles:true}))`);
   await ejecutar(`const m=document.querySelector('[aria-label="Registrar detalle de productos"]');[...m.querySelectorAll('button')].find(b=>b.textContent==='Agregar pieza').click()`);
   await esperar(`document.querySelectorAll('[aria-label="Piezas seleccionadas"] li').length===2`);
   await captura(`${tamaño.nombre}-${vista}-piezas`);
   if(await ejecutar('return document.querySelectorAll(".gelia-cedis-producto").length'))throw Error('Piezas agregadas antes de confirmar selección');
   await ejecutar(`const m=document.querySelector('[aria-label="Registrar detalle de productos"]');[...m.querySelectorAll('button')].find(b=>b.textContent==='Agregar al pedido (2)').click()`);
   await esperar(`!document.querySelector('[aria-label="Registrar detalle de productos"]') && document.querySelectorAll('.gelia-cedis-producto').length===2`);
   await captura(`${tamaño.nombre}-${vista}-detalle-pedido`);
   const errores=await ejecutar('return window.qaErrores');
   const desborde=await ejecutar('return document.documentElement.scrollWidth>innerWidth+1');
   if(errores.length||desborde)throw Error(JSON.stringify({errores,desborde,vista}));
   reporte.push({vista,...tamaño,errores,desborde,piezasEnPedido:2});
  }
 }
 fs.writeFileSync(`${destino}/verificacion.json`,JSON.stringify(reporte,null,2));
 console.log(JSON.stringify({destino,capturas:capturasTotales}));
} finally { if(sessionId)await comando('',null,'DELETE');await new Promise(r=>servidor.close(r)); }
