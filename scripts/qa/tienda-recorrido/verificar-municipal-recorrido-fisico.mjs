// Render aislado del build publicado con datos ficticios; no inicia sesión ni escribe datos.
// Monta el detalle real de resguardo (PuntoVenta/Resguardos/Show) de un envío municipal con respuestas interceptadas.
// Ejecutar: ./vendor/bin/sail exec -e PLAYWRIGHT_MODULE=/tmp/qa-pw-src/node_modules/playwright-core/index.mjs laravel.test node scripts/qa/tienda-recorrido/verificar-municipal-recorrido-fisico.mjs
import fs from 'node:fs';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || '/tmp/qa-playwright-core/index.mjs');
const origen = 'http://laravel.test';
const htmlBase = await (await fetch(`${origen}/login`)).text();
const paginaBase = JSON.parse(htmlBase.match(/<script[^>]*data-page="app"[^>]*>(.*?)<\/script>/s)[1]);
const status = (await (await fetch('http://selenium:4444/status')).json()).value;
if (status.nodes.some(n => n.slots.some(s => s.session))) throw new Error('Selenium está ocupado.');
const respuesta = await fetch('http://selenium:4444/session', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ capabilities: { alwaysMatch: { browserName: 'chrome', 'se:name': 'gelia-municipal-recorrido-qa', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] } } } }) });
const sesion = (await respuesta.json()).value;
const browser = await chromium.connectOverCDP(sesion.capabilities['se:cdp']);
const destino = process.env.QA_DESTINO || '/tmp/gelia-municipal-recorrido-qa';
fs.mkdirSync(destino, { recursive: true });
const reporte = [];

const base = {
    id: 501, version: 4, estado: 'en_custodia', estado_etiqueta: 'En custodia', snapshot_folio: 'PED-EJEMPLO-501', snapshot_cliente_nombre: 'Cliente ficticio',
    referencia_cliente: '9999', pedido_bma_id: 44, cantidad_bultos_esperada: 2, cantidad_bultos_recibida: 2, cantidad_bultos_pendiente: 0, clasificaciones: {}, antiguedad_configurada: true,
    sucursal: { id: 2, nombre: 'Sucursal Norte' }, pedido: { id: 44, folio: 'PED-EJEMPLO-501', folio_remision: '20328' }, salida_cedis_at: '2026-11-20T15:00:00Z', recepcion_fisica_at: '2026-11-21T09:30:00Z',
    bultos: [{ id: 1, folio: 'B-0001', tipo: 'caja', estado: 'en_custodia', recepcion_at: '2026-11-21T09:30:00Z' }, { id: 2, folio: 'B-0002', tipo: 'caja', estado: 'en_custodia', recepcion_at: '2026-11-21T09:30:00Z' }],
    incidencias: [], es_municipal: true, puede_salida_terminal: true, salida_terminal: null,
    caratula: { pedido_id: 44, id: null, puede_generar: true, puede_regenerar: false, puede_descargar: false },
};
const casos = {
    'sin-caratula-error': { resguardo: base },
    'con-caratula-regenerar': { resguardo: { ...base, caratula: { pedido_id: 44, id: 9, version: 2, estado: 'GENERADA', generada_at: '2026-11-21T10:00:00Z', puede_generar: false, puede_regenerar: true, puede_descargar: true } } },
    'salida-registrada': { resguardo: {
        ...base, estado: 'entregado', estado_etiqueta: 'Salida a terminal', puede_salida_terminal: false, entrega_completada_at: '2026-11-21T12:00:00Z',
        salida_terminal: { at: '2026-11-21T12:00:00Z', receptor: 'Juan Pérez Hernández' },
        bultos: base.bultos.map(b => ({ ...b, estado: 'entregado', entrega_at: '2026-11-21T12:00:00Z' })),
        // Tras la salida a terminal el servidor solo permite descargar la carátula.
        caratula: { pedido_id: 44, id: 9, version: 2, estado: 'GENERADA', generada_at: '2026-11-21T10:00:00Z', puede_generar: false, puede_regenerar: false, puede_descargar: true },
    } },
};
const catalogos = { estados: { en_custodia: 'En custodia', entregado: 'Entregado' }, antiguedades: {}, eventos: {} };

try {
    for (const size of [{ nombre: 'escritorio', width: 1280, height: 900 }, { nombre: 'movil', width: 375, height: 812 }]) {
        for (const tema of ['light', 'dark']) {
            for (const [nombreCaso, caso] of Object.entries(casos)) {
                const page = await browser.newPage({ viewport: size });
                const errores = [];
                page.on('pageerror', e => errores.push(e.stack));
                await page.addInitScript(t => { localStorage.setItem('theme', t); localStorage.setItem('theme_sidebar_mode', 'collapsed'); }, tema);
                const pagina = structuredClone(paginaBase);
                pagina.component = 'PuntoVenta/Resguardos/Show';
                pagina.url = '/punto-venta/resguardos/501';
                pagina.props.auth.user = { id: 999999, name: 'Usuario de revisión', email: 'revision@example.test', roles: [], permissions: ['punto_venta.acceder'] };
                pagina.props.auth.webpush = { enabled: false };
                pagina.props.auth.tema_visual = { modo: tema };
                Object.assign(pagina.props, { resguardo: caso.resguardo, timeline: [], catalogos, permisos: { ver_etiquetas: false }, almacenes: [] });
                const html = htmlBase.replace(/(<script[^>]*data-page="app"[^>]*>).*?(<\/script>)/s, (_, a, b) => a + JSON.stringify(pagina).replace(/</g, '\\u003c') + b).replaceAll('http://localhost:9080', origen);
                const puts = [];
                await page.route('**/*', async ruta => {
                    const req = ruta.request();
                    if (req.url() === `${origen}/__municipal_qa__`) return ruta.fulfill({ contentType: 'text/html', body: html });
                    if (req.url().includes('/build/')) {
                        const recurso = await fetch(req.url());
                        return ruta.fulfill({ status: recurso.status, contentType: recurso.headers.get('content-type') || 'application/octet-stream', body: Buffer.from(await recurso.arrayBuffer()) });
                    }
                    if (req.method() === 'PUT' && req.url().includes('salida-terminal')) {
                        puts.push(req.postDataJSON());
                        return ruta.fulfill({ status: 409, contentType: 'application/json', body: JSON.stringify({ message: 'Este resguardo ya tiene una salida registrada desde otra terminal.' }) });
                    }
                    if (req.method() !== 'GET') return ruta.fulfill({ status: 200, contentType: 'application/json', body: '{}' });
                    if (['xhr', 'fetch'].includes(req.resourceType())) return ruta.fulfill({ contentType: 'application/json', body: '{"data":[],"conversaciones":[],"notificaciones":[],"unread_count":0}' });
                    return ruta.continue();
                });
                await page.goto(`${origen}/__municipal_qa__`, { waitUntil: 'networkidle' });
                await page.getByRole('heading', { name: 'Salida a terminal', exact: true }).waitFor({ timeout: 10000 }).catch(async () => { throw new Error(JSON.stringify({ errores, contenido: (await page.locator('body').innerText()).slice(0, 600) })); });

                if (nombreCaso === 'sin-caratula-error') {
                    await page.getByLabel('Quién recibe en la terminal').fill('Juan Pérez Hernández');
                    await page.getByRole('button', { name: 'Registrar salida a terminal' }).click();
                    await page.getByRole('alert').waitFor({ timeout: 5000 });
                }
                if (nombreCaso === 'con-caratula-regenerar') {
                    await page.getByLabel('Quién recibe en la terminal').fill('Ana');
                    await page.getByRole('button', { name: 'Regenerar carátula' }).click();
                    await page.getByLabel('Motivo de la regeneración').fill('Corrección del nombre del destinatario');
                }

                const texto = (await page.locator('body').innerText()).replace(/\s+/g, ' ');
                const tiene = t => texto.toLowerCase().includes(t.toLowerCase());
                const medidas = await page.evaluate(() => ({
                    pagina: document.documentElement.scrollWidth > innerWidth + 1,
                    controlesPequenos: [...document.querySelectorAll('main button, main a, main input')]
                        .filter(el => /carátula|salida a terminal|regenerac|actualizar datos|cancelar|quién recibe/i.test((el.textContent || '') + (el.labels?.[0]?.textContent || '')))
                        .filter(el => el.getBoundingClientRect().height > 0 && el.getBoundingClientRect().height < 43.5)
                        .map(el => (el.textContent || el.id).trim().slice(0, 40)),
                }));
                const fallos = [];
                if (errores.length) fallos.push('errores de JavaScript');
                if (medidas.pagina) fallos.push('desbordamiento horizontal de la página');
                if (medidas.controlesPequenos.length) fallos.push(`controles menores de 44 px: ${medidas.controlesPequenos.join(', ')}`);
                if (nombreCaso === 'sin-caratula-error') {
                    if (puts.length !== 1 || puts[0].receptor !== 'Juan Pérez Hernández' || puts[0].version !== 4 || puts[0].idempotency_key.length > 55) fallos.push('solicitud de salida inesperada');
                    if (!/otra terminal/.test(texto) || !tiene('Generar carátula')) fallos.push('falta el error en línea o Generar carátula');
                }
                if (nombreCaso === 'con-caratula-regenerar' && !(tiene('Confirmar regeneración') && tiene('Registrar salida a terminal'))) fallos.push('falta regenerar o salida');
                if (nombreCaso === 'salida-registrada') {
                    if (!tiene('Salida a terminal registrada · Juan Pérez Hernández')) fallos.push('falta la salida registrada');
                    if (/entregado/i.test(texto)) fallos.push('aparece «Entregado»');
                }
                await page.screenshot({ path: `${destino}/${size.nombre}-${tema}-${nombreCaso}.png`, fullPage: true });
                reporte.push({ tamaño: size.nombre, tema, caso: nombreCaso, errores, medidas, fallos });
                await page.close();
                if (fallos.length) throw new Error(JSON.stringify(reporte.at(-1)));
            }
        }
    }
    fs.writeFileSync(`${destino}/verificacion.json`, JSON.stringify(reporte, null, 2));
    console.log(JSON.stringify({ capturas: reporte.length, destino }));
} finally {
    fs.writeFileSync(`${destino}/verificacion.json`, JSON.stringify(reporte, null, 2));
    await browser.close().catch(() => {});
    await fetch(`http://selenium:4444/session/${sesion.sessionId}`, { method: 'DELETE' });
}
