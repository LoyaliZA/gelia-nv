// Consulta y edición con componentes reales y transporte simulado; no escribe pedidos.
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || '/tmp/qa-playwright-core/index.mjs');
import fs from 'node:fs';
const status = (await (await fetch('http://selenium:4444/status')).json()).value;
if (status.nodes.some(n => n.slots.some(s => s.session))) throw new Error('Selenium está ocupado; no reutilizar una sesión ajena.');
const response = await fetch('http://selenium:4444/session', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ capabilities: { alwaysMatch: { browserName: 'chrome', 'se:name': 'gelia-modales-qa', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] } } } }) });
const selenium = (await response.json()).value;
const browser = await chromium.connectOverCDP(selenium.capabilities['se:cdp']);
const destino = '/tmp/gelia-modales-qa';
fs.mkdirSync(destino, { recursive: true });
const report = [];
try {
    for (const size of [{ name: 'escritorio', width: 1440, height: 1000 }, { name: 'movil', width: 390, height: 844 }]) {
        for (const theme of ['claro', 'oscuro']) {
            for (const view of ['editar-borrador', 'detalle', 'detalle-cedis', 'auditar']) {
                const page = await browser.newPage({ viewport: size });
                const errors = [];
                page.on('pageerror', e => errors.push(e.message));
                await page.goto(`http://laravel.test:5186/scripts/qa/tienda-recorrido/preview.html?vista=${view}&tema=${theme}${theme === 'claro' ? '&personalizado=1' : ''}`, { waitUntil: 'networkidle' });
                await page.waitForSelector('[role="dialog"]');
                const body = await page.locator('body').innerText();
                const expected = view === 'editar-borrador' ? 'Editar pedido' : 'Resultado de la revisión en tienda';
                if (!body.toLocaleLowerCase().includes(expected.toLocaleLowerCase()) || /Blocked request|host.*not allowed|Internal Server Error/i.test(body) || errors.length) throw new Error(`${view}: ${errors.join(';')} ${body.slice(0, 250)}`);
                if (view !== 'editar-borrador') {
                    if (await page.getByRole('button', { name: 'Cerrar consulta', exact: true }).count()) throw new Error('Consulta habilita mutaciones');
                }
                const overflow = await page.evaluate(() => [...document.querySelectorAll('[role="dialog"], .registrar-tienda-panel')].filter(el => el.clientWidth && el.scrollWidth > el.clientWidth + 1).map(el => el.className));
                if (overflow.length) throw new Error(`${view} ${size.name}: desbordamiento ${overflow}`);
                if (await page.locator('img').evaluateAll(imgs => imgs.some(el => el.clientWidth && (!el.complete || !el.naturalWidth)))) throw new Error('Imagen rota');
                if (view !== 'editar-borrador') await page.getByRole('heading', { name: expected, exact: true }).scrollIntoViewIfNeeded();
                await page.screenshot({ path: `${destino}/${view}-${size.name}-${theme}.png`, fullPage: true });
                report.push({ view, size: size.name, theme, errors, overflow });
                await page.close();
            }
        }
    }
    fs.writeFileSync(`${destino}/verificacion.json`, JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ capturas: report.length, destino }));
} finally {
    await browser.close().catch(() => {});
    await fetch(`http://selenium:4444/session/${selenium.sessionId}`, { method: 'DELETE' });
}
