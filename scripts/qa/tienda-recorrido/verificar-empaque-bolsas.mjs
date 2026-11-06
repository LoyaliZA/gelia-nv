// Modal real del build con datos y transporte ficticios. No modifica pedidos operativos.
import fs from 'node:fs';
import http from 'node:http';

const origen = 'http://laravel.test';
const proxy = 'http://laravel.test:9194';
const selenium = 'http://selenium:4444';
const destino = 'resources/design-references/empaque-bolsas-2026-11-06';
fs.mkdirSync(destino, { recursive: true });
const htmlBase = await (await fetch(`${origen}/login`)).text();
const paginaBase = JSON.parse(htmlBase.match(/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/s)[1]);
const estado = (await (await fetch(`${selenium}/status`)).json()).value;
if (estado.nodes.some(n => n.slots.some(s => s.session))) throw Error('Selenium está ocupado.');
let envios = [];
const pedido = {
    id: 999999, folio: 'PBMA-EJEMPLO', folio_remision: '2', requiere_bultos_empaque: true,
    cliente: { nombre: 'Cliente ficticio de empaque' }, sucursal_destino: { nombre: 'Carmen Cadena', codigo: 'PDV-01' },
    solicitud_preparacion: { modalidad_entrega: 'tienda', cobertura_tienda: { tipo: 'transferencia' } },
};
const servidor = http.createServer(async (req, res) => {
    try {
        if (req.url.startsWith('/qa?')) {
            const params = new URL(req.url, proxy).searchParams;
            const tema = params.get('tema');
            const pagina = structuredClone(paginaBase);
            pagina.component = 'ControlPedidos/Cedis/Partials/ModalMarcarEmpacadoBultos'; pagina.url = '/qa';
            pagina.props.auth.user = { id: 999999, name: 'Usuario ficticio', permissions: ['control_pedidos.auditar.aprobar'], roles: [] };
            pagina.props.auth.webpush = { enabled: false };
            Object.assign(pagina.props, { abierto: true, pedido: structuredClone(pedido), catalogos: {} });
            const init = `<script>localStorage.setItem('theme',${JSON.stringify(tema)});window.qaErrores=[];window.addEventListener('error',e=>window.qaErrores.push(e.message));window.addEventListener('unhandledrejection',e=>window.qaErrores.push(String(e.reason)));const rutaOriginal=window.route;window.route=(...args)=>{const v=rutaOriginal(...args);if(typeof v==='string'&&v.startsWith('http')){const u=new URL(v);return location.origin+u.pathname+u.search}return v};</script>`;
            const html = htmlBase.replace('<html lang="es">', `<html lang="es" class="${tema === 'dark' ? 'dark' : ''}">`).replace('</head>', `${init}</head>`).replace(/(<script[^>]*data-page="app"[^>]*>).*?(<\/script>)/s, (_, a, b) => a + JSON.stringify(pagina).replace(/</g, '\\u003c') + b).replaceAll('http://localhost:9080', proxy).replaceAll(`${origen}/`, `${proxy}/`);
            res.writeHead(200, { 'Content-Type': 'text/html' }); res.end(html); return;
        }
        if (req.url.startsWith('/build/')) {
            const r = await fetch(`${origen}${req.url}`); res.writeHead(r.status, { 'Content-Type': r.headers.get('content-type') || 'application/octet-stream' }); res.end(Buffer.from(await r.arrayBuffer())); return;
        }
        if (req.method === 'POST' && req.headers['content-type']?.includes('multipart/form-data')) {
            const partes = []; for await (const c of req) partes.push(c);
            const form = await new Response(Buffer.concat(partes), { headers: { 'Content-Type': req.headers['content-type'] } }).formData();
            envios.push({ campos: [...form.keys()], fotos: [...form.values()].map(f => f.name) });
            res.writeHead(422, { 'Content-Type': 'application/json' }); res.end('{"message":"Transporte ficticio: archivo recibido sin persistir"}'); return;
        }
        res.writeHead(200, { 'Content-Type': 'application/json' }); res.end('{"data":[],"pedido":null}');
    } catch (e) { res.writeHead(500); res.end(String(e)); }
});
await new Promise(resolve => servidor.listen(9194, '0.0.0.0', resolve));
let sessionId;
async function comando(path, body, method = 'POST') {
    const r = await fetch(`${selenium}/session${sessionId ? `/${sessionId}` : ''}${path}`, { method, ...(body ? { headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : {}) });
    const d = await r.json(); if (d.value?.error) throw Error(JSON.stringify(d.value)); return d.value;
}
const ejecutar = script => comando('/execute/sync', { script, args: [] });
async function esperar(condicion) {
    const ok = await comando('/execute/async', { script: `const done=arguments[arguments.length-1];let n=0;function ver(){if(${condicion})done(true);else if(++n>100)done(false);else setTimeout(ver,100)}ver();`, args: [] });
    if (!ok) throw Error(`No se cumplió ${condicion}: ${JSON.stringify(await ejecutar('return window.qaErrores'))}`);
}
const reporte = [];
async function captura(nombre) {
    await esperar('!document.getAnimations().some(a=>a.playState==="running"&&Number.isFinite(a.effect?.getComputedTiming().endTime))');
    const revision = await ejecutar(`const modal=document.querySelector('.gelia-empaque-dialog'),pie=document.querySelector('.gelia-empaque-pie');return {errores:window.qaErrores,desborde:modal.scrollWidth>modal.clientWidth+1,pieFuera:[...pie.querySelectorAll('button')].some(b=>{const r=b.getBoundingClientRect();return r.left<0||r.right>innerWidth||r.top<0||r.bottom>innerHeight}),rotas:[...document.images].filter(i=>i.complete&&!i.naturalWidth).length,ticket:modal.textContent.toLowerCase().includes('ticket')}`);
    if(revision.errores.length||revision.desborde||revision.pieFuera||revision.rotas||revision.ticket) throw Error(JSON.stringify(revision));
    fs.writeFileSync(`${destino}/${nombre}.png`,Buffer.from(await comando('/screenshot',null,'GET'),'base64'));
    return revision;
}
const boton = texto => ejecutar(`const b=[...document.querySelectorAll('button')].find(b=>b.textContent.trim()===${JSON.stringify(texto)});if(!b)throw Error('Botón ausente');b.click()`);
async function foto(indice=0) {
    await comando('/execute/async',{script:`const done=arguments[arguments.length-1],canvas=document.createElement('canvas');canvas.width=600;canvas.height=450;const c=canvas.getContext('2d');c.fillStyle='whitesmoke';c.fillRect(0,0,600,450);c.fillStyle='tan';c.fillRect(170,140,260,260);c.strokeStyle='saddlebrown';c.lineWidth=12;c.beginPath();c.arc(300,145,65,Math.PI,0);c.stroke();c.fillStyle='saddlebrown';c.font='20px sans-serif';c.fillText('BOLSA DE EJEMPLO',200,280);canvas.toBlob(blob=>{const input=document.querySelectorAll('input[capture]')[${indice}],dt=new DataTransfer();dt.items.add(new File([blob],'bolsa-ejemplo.jpg',{type:'image/jpeg'}));input.files=dt.files;input.dispatchEvent(new Event('change',{bubbles:true}));done(true)},'image/jpeg');`,args:[]});
    await esperar(`Boolean(document.querySelectorAll('.gelia-empaque-camara img')[${indice}]?.complete)`);
}
try {
    const sesion=await comando('',{capabilities:{alwaysMatch:{browserName:'chrome','se:name':'empaque-bolsas','goog:chromeOptions':{args:['--headless=new','--no-sandbox','--disable-dev-shm-usage']}}}});sessionId=sesion.sessionId;
    for(const vista of [{width:390,height:844,tema:'dark'},{width:390,height:844,tema:'light'},{width:390,height:640,tema:'dark'},{width:1311,height:932,tema:'dark'}]) {
        await comando('/goog/cdp/execute',{cmd:'Emulation.setDeviceMetricsOverride',params:{width:vista.width,height:vista.height,deviceScaleFactor:1,mobile:vista.width<600}});
        await comando('/url',{url:`${proxy}/qa?tema=${vista.tema}`});
        await esperar('Boolean(document.querySelector(".gelia-empaque-dialog"))');
        const nombre=`${vista.width}-${vista.height}-${vista.tema}`;
        reporte.push({...vista,caso:'vacio',...await captura(`${nombre}-vacio`)});
        await foto();
        reporte.push({...vista,caso:'foto',...await captura(`${nombre}-foto`)});
        await boton('Agregar otra bolsa');
        await foto(1);
        await ejecutar("document.querySelector('[aria-label=\"Quitar bolsa 2\"]').click()");
        await esperar("document.querySelectorAll('.gelia-empaque-camara img').length===1");
        if(await ejecutar("return !document.querySelector('.gelia-empaque-camara img').naturalWidth"))throw Error('Se perdió la foto conservada.');
        envios=[];
        await boton('Marcar empacado');
        await esperar("!document.querySelector('.gelia-empaque-pie button[type=submit]').disabled");
        if(envios.length!==1||envios[0].campos.length!==1||!envios[0].campos[0].endsWith('[foto_bulto]'))throw Error(JSON.stringify(envios));
        reporte.push({...vista,caso:'envio-solo-bolsa',enviado:envios[0]});
    }
    fs.writeFileSync(`${destino}/verificacion.json`,JSON.stringify(reporte,null,2));
    console.log(JSON.stringify({destino,vistas:4,capturas:8}));
} finally {
    if(sessionId)await comando('',null,'DELETE');
    await new Promise(resolve=>servidor.close(resolve));
}
