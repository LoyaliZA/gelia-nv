// Pago al recoger: guardar y confirmar con respuestas ficticias del servidor. Sin sesión ni escrituras reales.
import fs from 'node:fs';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || '/tmp/qa-playwright-core/index.mjs');
const origen = 'http://laravel.test';
const htmlBase = await (await fetch(`${origen}/login`)).text();
const paginaBase = JSON.parse(htmlBase.match(/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/s)[1]);
const status = (await (await fetch('http://selenium:4444/status')).json()).value;
if (status.nodes.some(n => n.slots.some(s => s.session))) throw new Error('Selenium está ocupado.');
const respuesta = await fetch('http://selenium:4444/session', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ capabilities: { alwaysMatch: { browserName: 'chrome', 'se:name': 'gelia-confirmacion-tienda-qa', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] } } } }) });
const sesion = (await respuesta.json()).value;
const browser = await chromium.connectOverCDP(sesion.capabilities['se:cdp']);
const destino = '/tmp/gelia-confirmacion-tienda-qa';
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
            pagina.component = 'ControlPedidos/Partials/ImportesPagoTienda';
            pagina.url = '/admin/configuracion-operativa';
            pagina.props.auth.user = { id: 999999, name: 'Usuario de revisión', email: 'revision@example.test', roles: [], permissions: ['configuracion_sistema.gestionar'] };
            pagina.props.auth.webpush = { enabled: false };
            pagina.props.auth.tema_visual = { modo: tema };
            const solicitud = { area_revision: 'preparacion_tienda', modalidad_entrega: 'tienda', revision_disponibilidad: { estado: 'sin_excepciones' } };
            const guardada = { ...solicitud, cobertura_tienda: { tipo: 'apartado', requiere_confirmacion_pedido: true, vence_at: '2026-11-04T17:00:00-06:00' } };
            const enviada = { ...guardada, cobertura_tienda: { ...guardada.cobertura_tienda, confirmacion_enviada_at: '2026-11-04T10:00:00-06:00' } };
            Object.assign(pagina.props, { pedidoId: 99, solicitud, editable: true, importesListos: true, totalMercancia: 899 });
            await page.addInitScript(() => {
                const RealDate = Date;
                const instante = RealDate.parse('2026-11-04T16:00:00Z');
                globalThis.Date = class extends RealDate {
                    constructor(...args) { super(...(args.length ? args : [instante])); }
                    static now() { return instante; }
                };
            });
            const html = htmlBase.replace('<html lang="es">', `<html lang="es" class="${tema === 'dark' ? 'dark' : ''}">`).replace('</head>', '<style>#app{max-width:1100px;margin:auto;padding:16px}body{background:var(--theme-bg-base)}</style></head>').replace(/(<script[^>]*data-page="app"[^>]*>).*?(<\/script>)/s, (_, a, b) => a + JSON.stringify(pagina).replace(/</g, '\\u003c') + b).replaceAll('http://localhost:9080', origen);
            await page.route('**/*', async ruta => {
                const req = ruta.request();
                if (req.url() === `${origen}/__operativa_qa__`) return ruta.fulfill({ contentType: 'text/html', body: html });
                if (req.url().includes('/build/')) {
                    const recurso = await fetch(req.url());
                    return ruta.fulfill({ status: recurso.status, contentType: recurso.headers.get('content-type') || 'application/octet-stream', body: Buffer.from(await recurso.arrayBuffer()) });
                }
                if (req.url().endsWith('/cobertura-tienda')) return ruta.fulfill({ contentType: 'application/json', body: JSON.stringify({ solicitud_preparacion: guardada }) });
                if (req.url().endsWith('/confirmar-pedido-tienda')) return ruta.fulfill({ contentType: 'application/json', body: JSON.stringify({ solicitud_preparacion: enviada }) });
                if (req.method() !== 'GET') return ruta.fulfill({ status: 200, contentType: 'application/json', body: '{}' });
                if (!req.url().includes('/build/') && ['xhr', 'fetch'].includes(req.resourceType())) return ruta.fulfill({ contentType: 'application/json', body: '{"data":[],"conversaciones":[],"notificaciones":[],"unread_count":0}' });
                return ruta.continue();
            });
            await page.goto(`${origen}/__operativa_qa__`, { waitUntil: 'networkidle' });
            await page.getByRole('heading', { name: 'Pago', exact: true }).waitFor();
            await page.locator('select').selectOption('apartado');
            await page.getByRole('button', { name: 'Guardar modalidad', exact: true }).click();
            const confirmar = page.getByRole('button', { name: 'Enviar confirmación a tienda', exact: true });
            await confirmar.waitFor();
            if (!await confirmar.isEnabled()) throw new Error('La modalidad no habilitó la confirmación');
            const desborde = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth + 1);
            if (errores.length || desborde) throw new Error(JSON.stringify({ errores, desborde }));
            await page.screenshot({ path: `${destino}/modalidad-${size.nombre}-${tema}.png`, fullPage: true });
            await confirmar.click();
            await page.getByText('Enviada a tienda', { exact: true }).waitFor();
            await page.screenshot({ path: `${destino}/confirmacion-${size.nombre}-${tema}.png`, fullPage: true });
            reporte.push({ tamaño: size.nombre, tema, errores, desborde, modalidadGuardada: true, confirmacionEnviada: true });
            await page.close();
        }
    }
    fs.writeFileSync(`${destino}/verificacion.json`, JSON.stringify(reporte, null, 2));
    console.log(JSON.stringify({ capturas: reporte.length * 2, destino }));
} finally {
    await browser.close().catch(() => {});
    await fetch(`http://selenium:4444/session/${sesion.sessionId}`, { method: 'DELETE' });
}
