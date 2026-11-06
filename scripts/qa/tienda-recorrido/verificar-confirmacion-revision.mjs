// Componente real del build, respuestas ficticias y Selenium. No inicia sesión ni escribe pedidos.
import fs from 'node:fs';
import http from 'node:http';
const origen = 'http://laravel.test';
const proxy = 'http://laravel.test:9192';
const selenium = 'http://selenium:4444';
const htmlBase = await (await fetch(`${origen}/login`)).text();
const paginaBase = JSON.parse(htmlBase.match(/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/s)[1]);
const destino = 'resources/design-references/tienda-confirmacion-revision';
fs.mkdirSync(destino, { recursive: true });
const status = (await (await fetch(`${selenium}/status`)).json()).value;
if (status.nodes.some(n => n.slots.some(s => s.session))) throw new Error('Selenium está ocupado.');
const servidor = http.createServer(async (req, res) => {
    try {
        if (req.url.startsWith('/qa?')) {
            const params = new URL(req.url, proxy).searchParams;
            const tema = params.get('tema');
            const pagina = structuredClone(paginaBase);
            pagina.component = 'ControlPedidos/Partials/ConfirmacionRevisionTienda';
            pagina.url = '/qa';
            pagina.props.auth.user = { id: 999999, name: 'Usuario ficticio', email: 'qa@example.test', roles: [], permissions: ['control_pedidos.crear'] };
            pagina.props.auth.webpush = { enabled: false };
            Object.assign(pagina.props, { pedido: { id: 99, sucursal_destino_id: 2, estatus: { fase_ciclo: 'PESAJE_RESPONDIDO' }, solicitud_preparacion: { cobertura_tienda: { tipo: params.get('cobertura') } } }, sucursales: [{ id: 2, nombre: 'Sucursal de ejemplo · recepción y custodia' }], consultaCerrada: true, importesListos: true });
            const init = `<script>localStorage.setItem('theme',${JSON.stringify(tema)});window.qaErrores=[];window.addEventListener('error',e=>window.qaErrores.push(e.message));window.addEventListener('unhandledrejection',e=>window.qaErrores.push(String(e.reason)));</script>`;
            const html = htmlBase.replace('<html lang="es">', `<html lang="es" class="${tema === 'dark' ? 'dark' : ''}">`).replace('</head>', `${init}<style>#app{max-width:1000px;margin:auto;padding:16px}body{background:var(--theme-surface-bg)}</style></head>`).replace(/(<script[^>]*data-page="app"[^>]*>).*?(<\/script>)/s, (_, a, b) => a + JSON.stringify(pagina).replace(/</g, '\\u003c') + b).replaceAll('http://localhost:9080', proxy).replaceAll(`${origen}/`, `${proxy}/`);
            res.writeHead(200, { 'Content-Type': 'text/html' }); res.end(html); return;
        }
        if (req.method !== 'GET') {
            res.writeHead(200, { 'Content-Type': 'application/json' });
            res.end(JSON.stringify({ estatus: { fase_ciclo: 'PENDIENTE_AUXILIAR' } })); return;
        }
        if (!req.url.startsWith('/build/')) {
            res.writeHead(200, { 'Content-Type': 'application/json' });res.end('{"data":[],"conversaciones":[],"notificaciones":[],"unread_count":0}'); return;
        }
        const recurso = await fetch(`${origen}${req.url}`);
        res.writeHead(recurso.status, { 'Content-Type': recurso.headers.get('content-type') || 'application/octet-stream' });
        res.end(Buffer.from(await recurso.arrayBuffer()));
    } catch (error) { res.writeHead(500); res.end(String(error)); }
});
await new Promise(resolve => servidor.listen(9192, '0.0.0.0', resolve));
let sessionId;
const reporte = [];
async function comando(path, body, method = 'POST') {
    const r = await fetch(`${selenium}/session${sessionId ? `/${sessionId}` : ''}${path}`, { method, ...(body ? { headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) } : {}) });
    const respuesta = await r.json();
    if (respuesta.value?.error) throw new Error(JSON.stringify(respuesta.value));
    return respuesta.value;
}
async function esperar(texto) {
    return comando('/execute/async', { script: 'const texto=arguments[0],done=arguments[arguments.length-1];let n=0;function revisar(){if(document.body.textContent.includes(texto))done(true);else if(++n>150)done(false);else setTimeout(revisar,100)}revisar();', args: [texto] }).then(ok => { if (!ok) throw new Error(`No apareció ${texto}`); });
}
try {
    const session = await comando('', { capabilities: { alwaysMatch: { browserName: 'chrome', 'se:name': 'gelia-confirmacion-revision-qa', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] } } } });
    sessionId = session.sessionId;
    for (const vista of [{ nombre: 'escritorio', width: 1440, height: 1000, tema: 'dark', cobertura: 'transferencia' }, { nombre: 'movil', width: 390, height: 844, tema: 'light', cobertura: 'credito' }]) {
        await comando('/goog/cdp/execute', { cmd: 'Emulation.setDeviceMetricsOverride', params: { width: vista.width, height: vista.height, deviceScaleFactor: 1, mobile: vista.nombre === 'movil' } });
        await comando('/url', { url: `${proxy}/qa?tema=${vista.tema}&cobertura=${vista.cobertura}` });
        await esperar('Confirmar pedido y enviar a revisión');
        const revision = await comando('/execute/sync', { script: 'return {errores:window.qaErrores,desborde:document.documentElement.scrollWidth>innerWidth+1,ancho:innerWidth};', args: [] });
        if (revision.errores.length || revision.desborde) throw new Error(JSON.stringify(revision));
        fs.writeFileSync(`${destino}/confirmar-${vista.nombre}-${vista.tema}.png`, Buffer.from(await comando('/screenshot', null, 'GET'), 'base64'));
        await comando('/execute/sync', { script: "[...document.querySelectorAll('button')].find(b=>b.textContent.includes('Confirmar pedido y enviar a revisión')).click();", args: [] });
        await esperar('Pedido enviado a revisión del auxiliar.');
        reporte.push({ ...vista, ...revision, confirmacionEnviada: true });
    }
    fs.writeFileSync(`${destino}/verificacion.json`, JSON.stringify(reporte, null, 2));
    console.log(JSON.stringify({ destino, capturas: reporte.length, resultados: reporte }));
} catch (error) {
    console.error(await comando('/execute/sync', { script: 'return {texto:document.body.innerText.slice(0,1500),errores:window.qaErrores, scripts:[...document.scripts].map(s=>s.src).filter(Boolean)};', args: [] }).catch(() => ({})));
    throw error;
} finally {
    if (sessionId) await comando('', null, 'DELETE').catch(() => {});
    await new Promise(resolve => servidor.close(resolve));
}
