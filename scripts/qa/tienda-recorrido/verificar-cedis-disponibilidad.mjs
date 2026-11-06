// Pago al recoger: guardar y confirmar con respuestas ficticias del servidor. Sin sesión ni escrituras reales.
import fs from 'node:fs';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || '/tmp/qa-playwright-core/index.mjs');
const origen = 'http://laravel.test';
const htmlBase = await (await fetch(`${origen}/login`)).text();
const paginaBase = JSON.parse(htmlBase.match(/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/s)[1]);
const status = (await (await fetch('http://selenium:4444/status')).json()).value;
if (status.nodes.some(n => n.slots.some(s => s.session))) throw new Error('Selenium está ocupado.');
const respuesta = await fetch('http://selenium:4444/session', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ capabilities: { alwaysMatch: { browserName: 'chrome', 'se:name': 'gelia-cedis-disponibilidad-qa', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] } } } }) });
const sesion = (await respuesta.json()).value;
const browser = await chromium.connectOverCDP(sesion.capabilities['se:cdp']);
const destino = '/tmp/gelia-cedis-disponibilidad-qa';
fs.mkdirSync(destino, { recursive: true });
const reporte = [];
try {
    for (const size of [{ nombre: 'escritorio', width: 1440, height: 1000 }, { nombre: 'movil', width: 390, height: 844 }]) {
        for (const tema of [size.nombre === 'escritorio' ? 'dark' : 'light']) {
            const page = await browser.newPage({ viewport: size });
            const errores = [];
            page.on('pageerror', e => errores.push(e.stack));
            await page.addInitScript(tema => { localStorage.setItem('theme', tema); localStorage.setItem('theme_sidebar_mode', 'collapsed'); }, tema);
            const pagina = structuredClone(paginaBase);
            pagina.component = 'ControlPedidos/Partials/PanelRespuestaDisponibilidad';
            pagina.url = '/admin/configuracion-operativa';
            pagina.props.auth.user = { id: 999999, name: 'Usuario de revisión', email: 'revision@example.test', roles: [], permissions: ['configuracion_sistema.gestionar'] };
            pagina.props.auth.webpush = { enabled: false };
            pagina.props.auth.tema_visual = { modo: tema };
            const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="800"><rect width="600" height="800" fill="white"/><text x="35" y="60" font-size="26">PEDIDO DE EJEMPLO</text><text x="35" y="125" font-size="20">SKU-01 · Perfume de ejemplo · 2 pzas.</text><path d="M35 155H565" stroke="gray"/></svg>';
            const documento = { id: 1, nombre_original: 'pedido-ejemplo.svg', mime_type: 'image/svg+xml', url: 'data:image/svg+xml,' + encodeURIComponent(svg) };
            Object.assign(pagina.props, { modo: 'dialogo', abierto: true, tomada: true, puedeResponder: true,
                pedido: { id: 99, folio: 'CEDIS-EJEMPLO', cliente: { nombre: 'Cliente de ejemplo' }, almacen: { id: 1, nombre: 'CEDIS' } }, documentos: [documento],
            });
            const html = htmlBase.replace('<html lang="es">', `<html lang="es" class="${tema === 'dark' ? 'dark' : ''}">`).replace('</head>', '<style>#app{max-width:1100px;margin:auto;padding:16px}body{background:var(--theme-bg-base)}</style></head>').replace(/(<script[^>]*data-page="app"[^>]*>).*?(<\/script>)/s, (_, a, b) => a + JSON.stringify(pagina).replace(/</g, '\\u003c') + b).replaceAll('http://localhost:9080', origen);
            await page.route('**/*', async ruta => {
                const req = ruta.request();
                if (req.url() === `${origen}/__operativa_qa__`) return ruta.fulfill({ contentType: 'text/html', body: html });
                if (req.url().includes('/build/')) {
                    const recurso = await fetch(req.url());
                    return ruta.fulfill({ status: recurso.status, contentType: recurso.headers.get('content-type') || 'application/octet-stream', body: Buffer.from(await recurso.arrayBuffer()) });
                }
                if (req.url().endsWith('/cobertura-tienda')) return ruta.fulfill({ contentType: 'application/json', body: '{}' });
                if (req.url().endsWith('/confirmar-pedido-tienda')) return ruta.fulfill({ contentType: 'application/json', body: '{}' });
                if (req.method() !== 'GET') return ruta.fulfill({ status: 200, contentType: 'application/json', body: '{}' });
                if (!req.url().includes('/build/') && ['xhr', 'fetch'].includes(req.resourceType())) return ruta.fulfill({ contentType: 'application/json', body: '{"data":[],"conversaciones":[],"notificaciones":[],"unread_count":0}' });
                return ruta.continue();
            });
            await page.goto(`${origen}/__operativa_qa__`, { waitUntil: 'networkidle' });
            await page.getByRole('heading', { name: 'Disponibilidad · CEDIS', exact: true }).waitFor();
            await page.getByLabel('Con detalle o faltante', { exact: true }).check();
            await page.getByRole('button', { name: 'Capturar sin catálogo', exact: true }).click();
            await page.getByLabel('Pieza', { exact: true }).fill('Perfume de ejemplo');
            const imagen = await page.evaluate(() => {
                const canvas = document.createElement('canvas'); canvas.width = 600; canvas.height = 400;
                const ctx = canvas.getContext('2d'); ctx.fillStyle = '#e8edf2'; ctx.fillRect(0, 0, 600, 400);
                ctx.fillStyle = '#34495e'; ctx.fillRect(200, 100, 100, 210);ctx.fillStyle = '#243342';ctx.font = '24px sans-serif';ctx.fillText('Evidencia de ejemplo', 180, 60);
                return canvas.toDataURL('image/png').split(',')[1];
            });
            await page.getByLabel('Adjuntar: Captura del pedido', { exact: true }).setInputFiles({ name: 'evidencia.png', mimeType: 'image/png', buffer: Buffer.from(imagen, 'base64') });
            await page.locator('img[alt="evidencia.png"]').waitFor();
            const desborde = await page.evaluate(() => [...document.querySelectorAll('[role=dialog], .gelia-preparacion-captura')].filter(el => el.scrollWidth > el.clientWidth + 1).map(el => el.className));
            if (errores.length || desborde.length) throw new Error(JSON.stringify({ errores, desborde }));
            await page.screenshot({ path: `${destino}/captura-${size.nombre}-${tema}.png`, fullPage: true });
            if (size.nombre === 'movil') {
                const enviar = page.getByRole('button', { name: 'Enviar respuesta', exact: true });
                await enviar.scrollIntoViewIfNeeded();
                if (!await enviar.isVisible()) throw new Error('Acción inaccesible en móvil');
                await page.getByRole('button', { name: 'Ver pedido', exact: true }).click();
            } else await page.getByRole('button', { name: 'Ver evidencia 1: evidencia.png', exact: true }).click();
            await page.evaluate(() => Promise.all(document.getAnimations().filter(a => Number.isFinite(a.effect?.getComputedTiming().endTime)).map(a => a.finished.catch(() => {}))));
            await page.screenshot({ path: `${destino}/visor-${size.nombre}-${tema}.png`, fullPage: true });
            if (errores.length) throw new Error(JSON.stringify(errores));
            reporte.push({ tamaño: size.nombre, tema, errores, desborde, evidencia: true, visor: true });
            await page.close();
        }
    }
    fs.writeFileSync(`${destino}/verificacion.json`, JSON.stringify(reporte, null, 2));
    console.log(JSON.stringify({ capturas: reporte.length * 2, destino }));
} finally {
    await browser.close().catch(() => {});
    await fetch(`http://selenium:4444/session/${sesion.sessionId}`, { method: 'DELETE' });
}
