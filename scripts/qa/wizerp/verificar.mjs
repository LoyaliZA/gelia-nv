// Render aislado del componente real, con datos ficticios. No abre rutas de negocio.
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
const root = '/tmp/gelia-wizerp-preview';
const output = '/tmp/gelia-wizerp-evidencia';
fs.mkdirSync(output, { recursive: true });
const status = (await (await fetch('http://selenium:4444/status')).json()).value;
if (status.nodes.some(n => n.slots.some(s => s.session))) throw new Error('Selenium ocupado.');
const server = http.createServer((req, res) => {
    const filename = path.join(root, new URL(req.url, 'http://qa.test').pathname);
    if (!filename.startsWith(root + '/') || !fs.existsSync(filename)) { res.writeHead(req.url === '/favicon.ico' ? 204 : 404); return res.end(); }
    res.setHeader('Content-Type', filename.endsWith('.js') ? 'text/javascript' : filename.endsWith('.css') ? 'text/css' : 'text/html');
    fs.createReadStream(filename).pipe(res);
});
await new Promise(resolve => server.listen(5188, '0.0.0.0', resolve));
let session;
const report = [];
async function command(method, endpoint, body) {
    const response = await fetch(`http://selenium:4444/session${session ? '/' + session : ''}${endpoint}`, { method, headers: { 'Content-Type': 'application/json' }, ...(body ? { body: JSON.stringify(body) } : {}) });
    const json = await response.json();
    if (json.value?.error) throw new Error(JSON.stringify(json.value));
    return json.value;
}
try {
    session = (await command('POST', '', { capabilities: { alwaysMatch: { browserName: 'chrome', 'se:name': 'wizerp-fase1-ficticio', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] } } } })).sessionId;
    for (const [width, height, theme] of [[1440, 1000, 'dark'], [390, 844, 'light']]) {
        await command('POST', '/window/rect', { width, height });
        await command('POST', '/url', { url: `http://laravel.test:5188/scripts/qa/wizerp/preview.html?theme=${theme}` });
        let info;
        for (let attempt = 0; attempt < 50; attempt++) {
            info = await command('POST', '/execute/sync', { script: 'return {ready: document.querySelectorAll("dt").length === 9, overflow: document.documentElement.scrollWidth > innerWidth, text: document.body.innerText}', args: [] });
            if (info.ready) break;
            await new Promise(resolve => setTimeout(resolve, 100));
        }
        if (!info.ready || info.overflow || !info.text.includes('Parcial') || !info.text.includes('Fila 11')) throw new Error(JSON.stringify(info));
        fs.writeFileSync(`${output}/${theme}-${width}.png`, Buffer.from(await command('GET', '/screenshot'), 'base64'));
        report.push({ width, height, theme, ...info });
    }
    fs.writeFileSync(`${output}/resultado.json`, JSON.stringify(report, null, 2));
    console.log(JSON.stringify(report));
} finally {
    if (session) await command('DELETE', '');
    await new Promise(resolve => server.close(resolve));
}
