// Build publicado, documentos ficticios y red interceptada. No modifica pedidos.
import fs from 'node:fs';
import http from 'node:http';
const origen = 'http://laravel.test';
const proxy = 'http://laravel.test:9192';
const selenium = 'http://selenium:4444';
const destino = 'resources/design-references/archivos-pedidos-2026-11-08';
const status = (await (await fetch(`${selenium}/status`)).json()).value;
if (status.nodes.some(n => n.slots.some(s => s.session))) throw Error('Selenium está ocupado.');
fs.mkdirSync(destino, { recursive: true });
const htmlBase = await (await fetch(`${origen}/login`)).text();
const paginaBase = JSON.parse(htmlBase.match(/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/s)[1]);
const url = `${proxy}/control-pedidos/pedidos/45/documentos/123`;
const documento = { id: 123, nombre_original: 'pedido-45-123.png', mime_type: 'image/png', tipo: 'pdf_pedido', url };
const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="900" height="1200"><rect width="900" height="1200" fill="white"/><text x="60" y="90" font-size="38" fill="#222">PEDIDO FICTICIO · 45</text><text x="60" y="150" font-size="24" fill="#555">Captura de prueba de lectura</text><path d="M60 190H840" stroke="#ddd"/>'+[0,1,2,3,4,5].map(i=>`<rect x="60" y="${230+i*140}" width="100" height="100" fill="#e5e7eb"/><text x="190" y="${270+i*140}" font-size="24">Producto de ejemplo ${i+1}</text><text x="190" y="${310+i*140}" font-size="20">Cantidad: 1 · Información ficticia</text>`).join('')+'</svg>';
let solicitudes = [];
const servidor = http.createServer(async (req, res) => {
    try {
        if (req.url.startsWith('/qa?')) {
            solicitudes = [];
            const params = new URL(req.url, proxy).searchParams;
            const tema = params.get('tema');
            const modo = params.get('modo');
            const pagina = structuredClone(paginaBase);
            pagina.component = modo === 'visor' ? 'ControlPedidos/Partials/ModalVistaPreviaDocumento' : 'ControlPedidos/Partials/GaleriaEvidenciasPedido';
            pagina.url = '/qa';
            pagina.props.auth.user = { id: 999999, name: 'Usuario ficticio', roles: [], permissions: [] };
            pagina.props.auth.webpush = { enabled: false };
            Object.assign(pagina.props, modo === 'visor' ? { abierto: true, documento } : { disabled: true, previews: [{ name: documento.nombre_original, mime: documento.mime_type, url: documento.url, vigente: true }], label: 'Captura del pedido' });
            const init = `<script>localStorage.setItem('theme',${JSON.stringify(tema)});localStorage.setItem('theme_glass','true');document.documentElement.classList.add('glass-active');window.qaErrores=[];window.addEventListener('error',e=>window.qaErrores.push(e.message));window.addEventListener('unhandledrejection',e=>window.qaErrores.push(String(e.reason)));</script>`;
            const html = htmlBase.replace('<html lang="es">', `<html lang="es" class="${tema === 'dark' ? 'dark' : ''}">`).replace('</head>', `${init}</head>`).replace(/(<script[^>]*data-page="app"[^>]*>).*?(<\/script>)/s, (_, a, b) => a + JSON.stringify(pagina).replace(/</g, '\\u003c') + b).replaceAll('http://localhost:9080', proxy).replaceAll(`${origen}/`, `${proxy}/`);
            res.writeHead(200, { 'Content-Type': 'text/html' }); res.end(html); return;
        }
        if (req.url.startsWith('/control-pedidos/pedidos/45/documentos/123')) {
            solicitudes.push(req.url);
            res.writeHead(200, { 'Content-Type': 'image/svg+xml' }); res.end(svg); return;
        }
        if (!req.url.startsWith('/build/')) { res.writeHead(200, { 'Content-Type': 'application/json' }); res.end('{"data":[]}'); return; }
        const recurso = await fetch(`${origen}${req.url}`);
        res.writeHead(recurso.status, { 'Content-Type': recurso.headers.get('content-type') || 'application/octet-stream' });
        res.end(Buffer.from(await recurso.arrayBuffer()));
    } catch (e) { res.writeHead(500); res.end(String(e)); }
});
await new Promise(resolve => servidor.listen(9192, '0.0.0.0', resolve));
let sessionId;
const reporte = [];
async function comando(path, body, method = 'POST') {
    const r = await fetch(`${selenium}/session${sessionId ? `/${sessionId}` : ''}${path}`, { method, ...(body ? { headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : {}) });
    const d = await r.json(); if (d.value?.error) throw Error(JSON.stringify(d.value)); return d.value;
}
try {
    const sesion = await comando('', { capabilities: { alwaysMatch: { browserName: 'chrome', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] } } } });
    sessionId = sesion.sessionId;
    for (const size of [{ nombre: 'escritorio', width: 1440, height: 1000, tema: 'dark' }, { nombre: 'movil', width: 390, height: 844, tema: 'light' }]) {
        for (const modo of ['galeria', 'visor']) {
            await comando('/goog/cdp/execute', { cmd: 'Emulation.setDeviceMetricsOverride', params: { width: size.width, height: size.height, deviceScaleFactor: 1, mobile: size.width < 600 } });
            await comando('/url', { url: `${proxy}/qa?modo=${modo}&tema=${size.tema}` });
            const listo = await comando('/execute/async', { script: 'const done=arguments[arguments.length-1];let n=0;function f(){const img=document.querySelector("img");if(img?.complete&&img.naturalWidth>0)done(true);else if(++n>100)done(false);else setTimeout(f,100)}f();', args: [] });
            if (!listo) throw Error('La imagen no se mostró');
            const datos = await comando('/execute/sync', { script: 'return {errores:window.qaErrores,desborde:document.documentElement.scrollWidth>innerWidth+1,imagen:document.querySelector("img").src,descargas:[...document.querySelectorAll("a[download]")].map(a=>a.href),texto:document.body.innerText}', args: [] });
            if (datos.errores.length || datos.desborde || !datos.imagen.includes(`vista=${modo === 'galeria' ? 'miniatura' : 'previa'}`)) throw Error(JSON.stringify(datos));
            if (modo === 'visor' && datos.descargas.some(href => href !== url)) throw Error('La descarga cambió el original');
            if (!datos.texto.includes(documento.nombre_original)) throw Error('Nombre breve ausente');
            fs.writeFileSync(`${destino}/${modo}-${size.nombre}.png`, Buffer.from(await comando('/screenshot', null, 'GET'), 'base64'));
            reporte.push({ modo, ...size, ...datos, solicitudes: [...solicitudes] });
        }
    }
    fs.writeFileSync(`${destino}/verificacion.json`, JSON.stringify(reporte, null, 2));
    console.log(JSON.stringify({ escenarios: reporte.length, destino }));
} finally {
    if (sessionId) await comando('', null, 'DELETE');
    await new Promise(resolve => servidor.close(resolve));
}
