// PR #34: reproducción de Auditoría desde el build real para revisar la cascada CSS corregida.
// Adaptación del guion verificar-calendario-remision.mjs; todas las escrituras son ficticias.
import fs from 'node:fs';
import http from 'node:http';

const origen = 'http://laravel.test';
const proxy = 'http://laravel.test:9193';
const selenium = 'http://selenium:4444';
const destino = 'resources/design-references/pr34-validacion-local/auditoria';
fs.mkdirSync(destino, { recursive: true });
const htmlBase = await (await fetch(`${origen}/login`)).text();
const paginaBase = JSON.parse(htmlBase.match(/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/s)[1]);
const estado = (await (await fetch(`${selenium}/status`)).json()).value;
if (estado.nodes.some(n => n.slots.some(s => s.session))) throw Error('Selenium está ocupado.');
let envios = [];
const pedido = {
    id: 999999, folio: 'PBMA-EJEMPLO', fecha: '2026-11-03T00:00:00.000000Z', created_at: '2026-11-04T03:30:00Z', numero_remision: 'RM-EJEMPLO',
    cliente: { nombre: 'Cliente ficticio de calendario', numero_cliente: '9999' }, estatus: { fase_ciclo: 'PENDIENTE_AUXILIAR' },
    origen: { nombre: 'Tienda', requiere_logistica: false }, total_mercancia: 566, estatus_envio: 'completo', consulta_cerrada: true, documentos: [], pagos_exhibicion: [],
    solicitud_preparacion: { modalidad_entrega: 'tienda', area_revision: 'control_pedidos', caso_tienda: 'consultar_cedis', cobertura_tienda: { tipo: 'transferencia' } },
};
const servidor = http.createServer(async (req, res) => {
    try {
        if (req.url.startsWith('/qa?')) {
            const params = new URL(req.url, proxy).searchParams;
            const tema = params.get('tema');
            const pagina = structuredClone(paginaBase);
            pagina.component = 'ControlPedidos/Auditar/Partials/ModalRevisarPedido'; pagina.url = '/qa';
            pagina.props.auth.user = { id: 999999, name: 'Usuario ficticio', permissions: ['control_pedidos.auditar.aprobar'], roles: [] };
            pagina.props.auth.webpush = { enabled: false };
            Object.assign(pagina.props, { abierto: true, pedido: structuredClone(pedido), catalogos: {} });
            if (params.get('emitida')) pagina.props.pedido.solicitud_preparacion.remision_tienda = { emitida_at: '2026-11-02T00:00:00-06:00' };
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
            envios.push({ fecha: form.get('fecha_emision'), archivo: form.get('remision')?.name });
            res.writeHead(422, { 'Content-Type': 'application/json' }); res.end('{"message":"Transporte ficticio: archivo recibido sin persistir"}'); return;
        }
        res.writeHead(200, { 'Content-Type': 'application/json' }); res.end('{"data":[],"pedido":null}');
    } catch (e) { res.writeHead(500); res.end(String(e)); }
});
await new Promise(resolve => servidor.listen(9193, '0.0.0.0', resolve));
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
try {
    const sesion = await comando('', { capabilities: { alwaysMatch: { browserName: 'chrome', 'se:name': 'calendario-remision', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] } } } });
    sessionId = sesion.sessionId;
    for (const vista of [1440, 1024, 390].flatMap(width => ['light', 'dark'].map(tema => ({ width, height: width === 390 ? 844 : 932, tema })))) {
        await comando('/goog/cdp/execute', { cmd: 'Emulation.setDeviceMetricsOverride', params: { width: vista.width, height: vista.height, deviceScaleFactor: 1, mobile: vista.width < 600 } });
        await comando('/url', { url: `${proxy}/qa?tema=${vista.tema}` });
        await esperar('Boolean(document.querySelector("#auditoria-fecha-emision"))');
        if (!(await ejecutar("return document.querySelector('#auditoria-fecha-emision').textContent.includes('3 de noviembre de 2026')"))) throw Error('No precargó la fecha del pedido.');
        await ejecutar("document.querySelector('#auditoria-remision').scrollIntoView({block:'center'})");
        await esperar('!document.getAnimations().some(a=>a.playState==="running"&&Number.isFinite(a.effect?.getComputedTiming().endTime))');
        const nombre = `${vista.width}-${vista.tema}`;
        fs.writeFileSync(`${destino}/${nombre}-campo.png`, Buffer.from(await comando('/screenshot', null, 'GET'), 'base64'));
        if (await ejecutar("const r=document.createRange();r.selectNodeContents(document.querySelector('.gelia-selector-fecha-hoy'));return r.getClientRects().length>1")) throw Error('Hoy se divide en varias líneas.');
        await ejecutar("document.querySelector('#auditoria-fecha-emision').click()");
        await esperar('Boolean(document.querySelector(".gelia-calendario"))');
        await ejecutar("document.querySelector('.gelia-calendario').scrollIntoView({block:'center'})");
        await esperar('!document.getAnimations().some(a=>a.playState==="running"&&Number.isFinite(a.effect?.getComputedTiming().endTime))');
        const revision = await ejecutar(`const panel=document.querySelector('.gelia-calendario'),r=panel.getBoundingClientRect();return {errores:window.qaErrores,ancho:r.width,alto:r.height,recortado:r.left<0||r.right>innerWidth||r.top<0||r.bottom>innerHeight,desborde:panel.scrollWidth>panel.clientWidth+1,tema:document.documentElement.className}`);
        if (revision.errores.length || revision.recortado || revision.desborde) throw Error(JSON.stringify(revision));
        fs.writeFileSync(`${destino}/${nombre}.png`, Buffer.from(await comando('/screenshot', null, 'GET'), 'base64'));
        await comando('/actions', { actions: [{ type: 'key', id: 'teclado', actions: [{ type: 'keyDown', value: '\uE00C' }, { type: 'keyUp', value: '\uE00C' }] }] });
        await esperar('!document.querySelector(".gelia-calendario")');
        if (!(await ejecutar("return Boolean(document.querySelector('#auditoria-remision'))&&document.activeElement.id==='auditoria-fecha-emision'"))) throw Error('Escape cerró el modal o perdió el foco.');
        await ejecutar("document.querySelector('#auditoria-fecha-emision').click()");
        await esperar('Boolean(document.querySelector(".gelia-calendario"))');
        await ejecutar("document.querySelector('.gelia-calendario-dia[aria-label=\"10 de noviembre de 2026\"]').click()");
        if (!(await ejecutar("return document.querySelector('#auditoria-fecha-emision').textContent.includes('10 de noviembre')&&!document.querySelector('.gelia-calendario')"))) throw Error('Selección fallida.');
        // Comprueba el valor enviado sin escribir en la aplicación.
        envios = [];
        await ejecutar(`const campo=document.querySelector('input[aria-label="Adjuntar remisión PDF"]'),dt=new DataTransfer();dt.items.add(new File(['%PDF-1.4 ficticio'],'remision-ejemplo.pdf',{type:'application/pdf'}));campo.files=dt.files;campo.dispatchEvent(new Event('change',{bubbles:true}));`);
        await esperar("document.querySelector('#auditoria-fecha-emision').disabled===false");
        if (envios[0]?.fecha !== '2026-11-10') throw Error(`Fecha de adjunto incorrecta ${JSON.stringify(envios)}`);
        await ejecutar("document.querySelector('.gelia-selector-fecha-hoy').click()");
        const hoy = await ejecutar("return document.querySelector('#auditoria-fecha-emision').textContent");
        reporte.push({ ...vista, ...revision, enviado: envios[0], hoy });
        await comando('/url', { url: `${proxy}/qa?tema=${vista.tema}&emitida=1` });
        await esperar('Boolean(document.querySelector("#auditoria-fecha-emision"))');
        if (!(await ejecutar("return document.querySelector('#auditoria-fecha-emision').disabled&&document.querySelector('.gelia-selector-fecha-hoy').disabled&&document.querySelector('#auditoria-fecha-emision').textContent.includes('2 de noviembre')"))) throw Error('No conservó bloqueo de fecha emitida.');
    }
    fs.writeFileSync(`${destino}/verificacion.json`, JSON.stringify(reporte, null, 2));
    console.log(JSON.stringify({ destino, vistas: reporte.length }));
} finally {
    if (sessionId) await comando('', null, 'DELETE');
    await new Promise(resolve => servidor.close(resolve));
}
