import fs from 'node:fs';
import { Browser, fixture, output, assert, crearArchivoQA } from '../m01-continuidad/navegadorComun.mjs';

// Dos ventanas y cookies por host: sesiones Laravel independientes en la misma base QA.
const b = new Browser();
const resultado = { pasos: [], sesiones: {}, lecturas: {}, medidas: [] };
const pausa = ms => new Promise(resolve => setTimeout(resolve, ms));
let ventas, cedis, archivo;
const seleccionar = handle => b.cmd('/window', { handle });
const esperar = (condicion, limite = 40000) => b.cmd('/execute/async', { script: `const done=arguments[arguments.length-1],fin=Date.now()+${limite};function revisar(){try{if(${condicion})return done(true)}catch{}if(Date.now()>fin)return done(false);setTimeout(revisar,150)}revisar()`, args: [] }).then(ok => assert(ok, `No se cumplió: ${condicion}`));
const entrar = async (host, email) => {
    await b.cmd('/url', { url: `http://${host}:9193/login` });
    await esperar('!!window.axios');
    await b.type('input[type=text]', email); await b.type('input[type=password]', 'M01-solo-pruebas-2026');
    await b.js('document.querySelector("button[type=submit]").click()');
    await esperar('!location.pathname.includes("/login")');
};
const ir = async (host, path) => { await b.cmd('/url', { url: `http://${host}:9193${path}` }); await esperar('document.readyState === "complete" && !!window.axios'); };
const instrumentar = () => b.js(`
    window.Echo?.disconnect();
    window.__qaLecturas=[];window.__qaEstados=[];window.__qaPosts=[];
    window.axios.interceptors.response.use(r=>{const metodo=(r.config.method||'get').toLowerCase();if(metodo==='get'&&r.config.url.includes('/disponibilidad-tienda'))window.__qaLecturas.push({at:Date.now(),id:r.data.id,version:r.data.version_consulta,respuesta:r.data.solicitud_preparacion?.revision_disponibilidad?.respuesta_id,actualizacion:r.data.solicitud_preparacion?.revision_disponibilidad?.actualizacion_id});if(metodo==='post'&&r.config.url.includes('control-pedidos'))window.__qaPosts.push({url:r.config.url,status:r.status});return r;});
    let anterior='';const observar=()=>{const e=document.querySelector('.registrar-tienda-modal,[aria-label="Consulta de disponibilidad en CEDIS"]');if(!e)return;const t=e.textContent;const estado={respondida:t.includes('Consulta respondida'),excepciones:t.includes('Excepciones pendientes'),actualizacion:t.includes('Actualización de consulta'),cerrada:t.includes('Consulta cerrada')};const clave=JSON.stringify(estado);if(clave!==anterior){anterior=clave;window.__qaEstados.push({at:Date.now(),...estado});}};
    new MutationObserver(observar).observe(document.body,{subtree:true,childList:true,characterData:true});observar();
`);
const subir = async () => {
    const selector = 'input[aria-label="Adjuntar: Captura del pedido"]';
    await esperar(`!!document.querySelector('${selector}')`);
    const el = (await b.cmd('/element', { using: 'css selector', value: selector }))['element-6066-11e4-a52e-4f735466cecf'];
    await b.js('document.querySelector(arguments[0]).value=""', [selector]);
    await b.cmd(`/element/${el}/value`, { text: archivo });
    await esperar(`document.querySelector('[aria-label="Captura del pedido"]').textContent.includes('m01-qa.png')`);
};
const abrirPedido = async () => {
    await esperar(`[...document.querySelectorAll('tr,article')].some(e=>e.textContent.includes(${JSON.stringify(fixture.folio)}))`);
    await b.js(`const row=[...document.querySelectorAll('tr,article')].find(e=>e.textContent.includes(arguments[0]));const bs=[...row.querySelectorAll('button')];const e=bs.find(e=>e.textContent.trim()==='Editar');if(e)e.click();else bs.find(e=>e.getAttribute('aria-haspopup')==='menu').click()`, [fixture.folio]);
    if (!await b.js('return !!document.querySelector("#folio-tienda")')) await b.js(`const e=[...document.querySelectorAll('button')].find(e=>['Editar','Continuar'].includes(e.textContent.trim()));if(!e)throw Error('Sin acción Editar');e.click()`);
    await esperar('!!document.querySelector("#folio-tienda")');
};
const datos = () => b.js('return {estados:window.__qaEstados,lecturas:window.__qaLecturas,posts:window.__qaPosts,visible:document.visibilityState}');

try {
    const estado = (await (await fetch('http://selenium:4444/status')).json()).value;
    assert(!estado.nodes.some(n => n.slots.some(s => s.session)), 'Selenium ocupado');
    b.id = (await b.cmd('', { capabilities: { alwaysMatch: { browserName: 'chrome', 'se:name': 'M01-sincronizacion-aislada', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage', '--disable-background-timer-throttling'] }, 'goog:loggingPrefs': { browser: 'ALL' } } } })).sessionId;
    await b.cmd('/timeouts', { script: 45000, pageLoad: 30000 });
    ventas = await b.cmd('/window', null, 'GET');
    await entrar('laravel.test', fixture.email);
    await ir('laravel.test', '/control-pedidos?q='+fixture.folio);
    await instrumentar();
    await b.js(`document.querySelector('[aria-label="Cerrar detalle"]')?.click()`);
    await b.click('Nuevo pedido');
    await esperar('!!document.querySelector("#modalidad-tienda")');
    await pausa(1200);
    await b.js(`const e=document.querySelector('#modalidad-tienda');e.value='municipal';e.dispatchEvent(new Event('change',{bubbles:true}));`);
    await pausa(1200);
    assert((await b.js('return window.__qaPosts')).length === 0, 'Alta vacía persistió sin captura');
    await b.js(`document.querySelector('[aria-label="Cerrar registro"]').click()`);
    resultado.pasos.push('Alta vacía y elección aislada de Municipal sin POST ni borrador.');
    await abrirPedido();
    await esperar('document.querySelector(".registrar-tienda-modal").textContent.includes("Esperando respuesta de CEDIS")');
    const nodoVentas = await b.js('window.__qaModal=document.querySelector(".registrar-tienda-modal");return !!window.__qaModal');
    assert(nodoVentas, 'Sin modal Ventas');
    cedis = (await b.cmd('/window/new', { type: 'window' })).handle;
    await seleccionar(cedis);
    await entrar('gelianv-laravel.test-1', fixture.cedis_email);
    await ir('gelianv-laravel.test-1', '/control-pedidos/cedis?q='+fixture.folio);
    await instrumentar();
    archivo = await crearArchivoQA(b);
    await b.click('Revisar disponibilidad');
    await esperar(`!!document.querySelector('[aria-label="Consulta de disponibilidad en CEDIS"]')`);
    await b.js('window.__qaModal=document.querySelector(".gelia-preparacion-modal");const e=[...document.querySelectorAll("label")].find(e=>e.textContent.trim()==="Con detalle o faltante");e.querySelector("input").click()');
    await b.click('Capturar sin catálogo');
    await b.type('.gelia-cedis-producto input', 'Pieza ficticia sin existencias');
    await b.js(`document.querySelector('[role=switch][aria-label="Sin existencias"]').click()`);
    await subir(); await b.click('Enviar respuesta');
    await esperar('document.body.textContent.includes("Respuesta enviada a Ventas.")');
    const respuestaInicialAt = Date.now();
    await pausa(17500);
    const antesVentas = Date.now();
    await seleccionar(ventas);
    await esperar('document.querySelector(".registrar-tienda-modal").textContent.includes("Pieza ficticia sin existencias")');
    const recibida = await datos();
    assert(recibida.estados.some(e => e.at >= respuestaInicialAt-1000 && e.at < antesVentas && e.excepciones), 'No se observó respuesta automática antes de volver a Ventas');
    assert(!await b.js(`return [...document.querySelectorAll('.registrar-tienda-modal button')].some(e=>e.textContent.trim()==='Cerrar consulta')`), 'Cierre habilitado con faltante');
    resultado.pasos.push('CEDIS responde faltante sin SKU; Ventas recibe automáticamente la evidencia y el faltante con ambos modales abiertos (respaldo, Echo desconectado).');
    await b.shot('faltante-ventas', 1440, true);
    await b.js('document.querySelector(".tienda-respuesta-menu summary").click()');
    await b.click('Retirar'); await b.click('Confirmar retiro');
    await pausa(17500);
    const antesCedis = Date.now();
    await seleccionar(cedis);
    await esperar('document.body.textContent.includes("Actualización de consulta") && [...document.querySelectorAll("button")].some(e=>e.textContent.trim()==="Enviar respuesta")');
    const cambioRecibido = await datos();
    assert(cambioRecibido.estados.some(e => e.at < antesCedis && e.actualizacion), 'CEDIS no actualizó antes de volver a su ventana');
    assert(await b.js('return window.__qaModal===document.querySelector(".gelia-preparacion-modal")'), 'Se desmontó el modal CEDIS');
    await subir();
    // El respaldo durante captura no debe quitar archivos ni regresar a respuesta enviada.
    await pausa(16000);
    assert(await b.js(`return document.querySelector('[aria-label="Captura del pedido"]').textContent.includes('m01-qa.png')`), 'Se perdió la captura pendiente CEDIS');
    await b.click('Enviar respuesta');
    await esperar('document.body.textContent.includes("Respuesta enviada a Ventas.")');
    await pausa(17500);
    const antesSegunda = Date.now();
    await seleccionar(ventas);
    await esperar(`[...document.querySelectorAll('button')].some(e=>e.textContent.trim()==='Cerrar consulta')`);
    const segunda = await datos();
    assert(segunda.estados.some(e => e.at < antesSegunda && e.respondida && !e.excepciones), 'Segunda respuesta no llegó automáticamente');
    assert(await b.js('return window.__qaModal===document.querySelector(".registrar-tienda-modal")'), 'Se desmontó el modal Ventas');
    await b.click('Cerrar consulta'); await esperar('document.body.textContent.includes("Solicitar nueva revisión")');
    await b.click('Solicitar nueva revisión');
    await pausa(17500);
    const antesReapertura = Date.now();
    await seleccionar(cedis);
    await esperar('document.body.textContent.includes("Actualización de consulta")');
    const reapertura = await datos();
    assert(reapertura.estados.filter(e => e.actualizacion && e.at < antesReapertura).length >= 2, 'Reapertura no visible automáticamente');
    await subir(); await b.click('Enviar respuesta');
    await esperar('document.body.textContent.includes("Respuesta enviada a Ventas.")');
    await pausa(17500);
    await seleccionar(ventas);
    await esperar(`[...document.querySelectorAll('button')].some(e=>e.textContent.trim()==='Cerrar consulta')`);
    resultado.lecturas.ventas = await datos();
    resultado.pasos.push('Retiro documental visible automáticamente en CEDIS; segunda respuesta vigente habilita cierre; reapertura y tercera respuesta sin cerrar ninguno de los modales.');
    for (const ancho of [1440, 1024, 390]) for (const oscuro of [true, false]) resultado.medidas.push(await b.shot('ventas-final', ancho, oscuro));
    resultado.lecturas.cedis = await (async () => { await seleccionar(cedis); return datos(); })();
    resultado.sesiones = { ventas: fixture.usuario_id, cedis: fixture.cedis_id, hosts: ['laravel.test', 'gelianv-laravel.test-1'], transporte: 'BROADCAST_CONNECTION=null; Echo desconectado; respaldo real de 15 segundos' };
    resultado.console = await b.cmd('/log', { type: 'browser' });
    assert(!resultado.console.some(e => /Uncaught|TypeError|ReferenceError/.test(e.message)), 'Excepciones JavaScript');
    assert(!resultado.medidas.some(m => m.desborde), 'Desbordamiento');
    resultado.resultado = 'aprobado';
} catch (error) {
    resultado.resultado = 'fallo'; resultado.error = String(error.stack || error); process.exitCode = 1;
    if (b.id) {
        resultado.pantalla = await b.js('return document.body.innerText').catch(() => 'No disponible');
        resultado.console = await b.cmd('/log', { type: 'browser' }).catch(() => []);
        await b.shot('fallo', 1440, true).catch(() => {});
    }
} finally {
    fs.writeFileSync(`${output}/sincronizacion.json`, JSON.stringify(resultado, null, 2));
    if (b.id) await b.cmd('', null, 'DELETE');
    console.log(JSON.stringify(resultado, null, 2));
}
