// Recorrido visual aislado con componentes reales; no usa ni escribe la base de pedidos.
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || '/tmp/qa-playwright-core/index.mjs');
import fs from 'node:fs';
const status = (await (await fetch('http://selenium:4444/status')).json()).value;
if (status.nodes.some(n => n.slots.some(s => s.session))) throw new Error('Selenium está ocupado.');
const response = await fetch('http://selenium:4444/session', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ capabilities: { alwaysMatch: { browserName: 'chrome', 'se:name': 'gelia-t01-qa', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] } } } }) });
const selenium = (await response.json()).value;
const browser = await chromium.connectOverCDP(selenium.capabilities['se:cdp']);
const destino = '/tmp/gelia-t01-qa';
fs.mkdirSync(destino, { recursive: true });
const report = [];
try {
    for (const size of [{ name: 'escritorio', width: 1440, height: 1000 }, { name: 'movil', width: 390, height: 844 }]) {
        for (const theme of ['claro', 'oscuro']) {
            for (const view of ['t01-importes', 't01-separacion', 't01-entrega']) {
                const page = await browser.newPage({ viewport: size });
                await page.addInitScript(() => {
                    const RealDate = Date;
                    const ahora = RealDate.parse('2026-11-04T16:00:00Z');
                    globalThis.Date = class extends RealDate {
                        constructor(...args) { super(...(args.length ? args : [ahora])); }
                        static now() { return ahora; }
                    };
                });
                const errors = [];
                page.on('pageerror', e => errors.push(e.message));
                await page.goto(`http://laravel.test:5186/scripts/qa/tienda-recorrido/preview.html?vista=${view}&tema=${theme}${theme === 'claro' ? '&personalizado=1' : ''}`, { waitUntil: 'networkidle' });
                await page.waitForSelector('[role="dialog"]');
                const body = await page.locator('body').innerText();
                const expected = { 't01-importes': 'Se paga en tienda', 't01-separacion': 'Confirmar pedido separado', 't01-entrega': 'Número de remisión PDV' }[view];
                if (errors.length || !body.includes(expected) || /Blocked request|host.*not allowed|Internal Server Error/i.test(body)) throw new Error(`${view}: ${errors} ${body.slice(0, 250)}`);
                if (view === 't01-importes') {
                    const panel = page.locator('[aria-labelledby="registrar-tienda-importes"]');
                    const text = await panel.innerText();
                    if (/Reexpedición|Seguro|No aplica/.test(text)) throw new Error('Conceptos sin aplicación visibles');
                    if (await page.locator('[aria-labelledby="registrar-tienda-pago"] input[type="file"]').count()) throw new Error('Comprobante requerido en T-01');
                    await panel.scrollIntoViewIfNeeded();
                }
                if (view === 't01-separacion' && await page.locator('#tienda-productos').count()) throw new Error('Se pide capturar piezas después del cierre');
                if (view === 't01-entrega') await page.locator('#t01-ticket-remision').scrollIntoViewIfNeeded();
                const overflow = await page.evaluate(() => [...document.querySelectorAll('[role="dialog"], .registrar-tienda-panel')].filter(el => el.clientWidth && el.scrollWidth > el.clientWidth + 1).map(el => ({ clase: el.className, ancho: el.clientWidth, contenido: el.scrollWidth, hijos: [...el.querySelectorAll('*')].filter(c => c.getBoundingClientRect().right > el.getBoundingClientRect().right + 1).slice(0, 5).map(c => ({ tag: c.tagName, clase: c.className, texto: c.textContent.slice(0, 80) })) })));
                if (overflow.length) throw new Error(`${view} ${size.name}: desbordamiento ${JSON.stringify(overflow)}`);
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
