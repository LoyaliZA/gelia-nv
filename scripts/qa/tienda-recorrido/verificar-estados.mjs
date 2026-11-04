// Tabla real con respuestas simuladas. No modifica pedidos ni comparte sesiones de navegador.
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || '/tmp/qa-playwright-core/index.mjs');
import fs from 'node:fs';
const status = (await (await fetch('http://selenium:4444/status')).json()).value;
if (status.nodes.some(n => n.slots.some(s => s.session))) throw new Error('Selenium está ocupado.');
const response = await fetch('http://selenium:4444/session', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ capabilities: { alwaysMatch: { browserName: 'chrome', 'se:name': 'gelia-estados-qa', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] } } } }) });
const selenium = (await response.json()).value;
const browser = await chromium.connectOverCDP(selenium.capabilities['se:cdp']);
const destino = '/tmp/gelia-estados-qa';
fs.mkdirSync(destino, { recursive: true });
const report = [];
try {
    for (const size of [{ name: 'escritorio', width: 1440, height: 1000 }, { name: 'movil', width: 390, height: 844 }]) {
        for (const theme of ['claro', 'oscuro']) {
            const page = await browser.newPage({ viewport: size });
            const errors = [];
            page.on('pageerror', e => errors.push(e.message));
            await page.goto(`http://laravel.test:5186/scripts/qa/tienda-recorrido/preview.html?vista=estados-consulta&tema=${theme}${theme === 'claro' ? '&personalizado=1' : ''}`, { waitUntil: 'networkidle' });
            const body = await page.locator('body').innerText();
            if (errors.length || /Blocked request|host.*not allowed|Internal Server Error/i.test(body)) throw new Error(`${errors} ${body.slice(0, 200)}`);
            for (const label of ['Pendiente de respuesta', 'Consulta respondida', 'Apartado confirmado', 'Pesaje respondido']) {
                if (!body.includes(label)) throw new Error(`Falta etiqueta: ${label}`);
            }
            if (body.includes('apartado_confirmado') || body.includes('Separación pendiente')) throw new Error('Código o etiqueta anterior visible');
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth);
            if (overflow) throw new Error('Desbordamiento horizontal');
            await page.screenshot({ path: `${destino}/estados-${size.name}-${theme}.png`, fullPage: true });
            report.push({ size: size.name, theme, errors, overflow });
            await page.close();
        }
    }
    fs.writeFileSync(`${destino}/verificacion.json`, JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ capturas: report.length, destino }));
} finally {
    await browser.close().catch(() => {});
    await fetch(`http://selenium:4444/session/${selenium.sessionId}`, { method: 'DELETE' });
}
