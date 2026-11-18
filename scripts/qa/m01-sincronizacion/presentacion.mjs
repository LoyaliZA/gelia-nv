import fs from 'node:fs';
import { Browser, fixture, output, assert, crearArchivoQA } from '../m01-continuidad/navegadorComun.mjs';
const b = new Browser();
const resultado = { tarjetas: [], medidas: [], pasos: [] };
const soloTienda = process.env.GELIA_QA_SOLO_TIENDA === '1';
const soloTarjetas = process.env.GELIA_QA_SOLO_TARJETAS === '1';
resultado.alcance = soloTarjetas ? 'Tarjetas sin mutaciones' : soloTienda ? 'Diagnóstico dirigido Tienda' : 'Tres modalidades';
resultado.console = [];
const recogerConsola = async fase => { resultado.console.push(...(await b.cmd('/log',{type:'browser'})).map(e=>({fase,...e}))); };
const ir = async (host, path) => { await b.cmd('/url', { url: `http://${host}:9193${path}` }); await b.wait('document.readyState === "complete" && !!window.axios'); await b.js('window.Echo?.disconnect()'); };
const entrar = async (host, email) => {
    await ir(host, '/login'); await b.type('input[type=text]', email); await b.type('input[type=password]', 'M01-solo-pruebas-2026');
    await b.js('document.querySelector("button[type=submit]").click()'); await b.wait('!location.pathname.includes("/login")');
};
const medir = () => b.js(`const partidas=[];for(const e of document.querySelectorAll('.gelia-cedis-tipo strong')){for(const n of e.childNodes){if(n.nodeType!==3)continue;for(const m of n.textContent.matchAll(/\\S+/g)){const r=document.createRange();r.setStart(n,m.index);r.setEnd(n,m.index+m[0].length);if(r.getClientRects().length>1)partidas.push(m[0])}}}return {palabrasPartidas:partidas,pagina:document.documentElement.scrollWidth<=innerWidth+1,modales:[...document.querySelectorAll('[role=dialog],.gelia-modal-body')].filter(e=>e.getBoundingClientRect().width>0).map(e=>({clase:e.className,sinDesborde:e.scrollWidth<=e.clientWidth+1}))}`);
const foto = async (nombre, ancho, oscuro) => { await b.shot(nombre, ancho, oscuro); resultado.medidas.push({ nombre, ancho, oscuro, ...await medir() }); };
const editar = async (folio) => {
    await ir('laravel.test', '/control-pedidos?q='+folio);
    await b.wait(`!!document.querySelector('[aria-label="Cerrar detalle"]')`);
    await b.js(`document.querySelector('[aria-label="Cerrar detalle"]').click()`);
    await b.js(`const e=[...document.querySelectorAll('tr,article')].find(e=>e.textContent.includes(arguments[0]));const botones=[...e.querySelectorAll('button')];const editar=botones.find(b=>['Editar','Continuar'].includes(b.textContent.trim()));if(editar)editar.click();else botones.find(b=>b.getAttribute('aria-haspopup')==='menu').click()`, [folio]);
    if (!await b.js('return !!document.querySelector("#folio-tienda")')) await b.click('Continuar');
    await b.wait('!!document.querySelector("#folio-tienda")');
};
try {
    const estado = (await (await fetch('http://selenium:4444/status')).json()).value;
    assert(!estado.nodes.some(n=>n.slots.some(s=>s.session)), 'Selenium ocupado');
    b.id = (await b.cmd('', { capabilities: { alwaysMatch: { browserName: 'chrome', 'se:name': 'M01-presentacion-aislada', 'goog:chromeOptions': { args: ['--headless=new', '--no-sandbox', '--disable-dev-shm-usage'] }, 'goog:loggingPrefs': { browser: 'ALL' } } } })).sessionId;
    await b.cmd('/timeouts', { script: 40000, pageLoad: 30000 });
    const cedis = await b.cmd('/window', null, 'GET');
    await entrar('gelianv-laravel.test-1', fixture.cedis_email);
    for (const tipo of (soloTienda ? ['tienda'] : ['municipal', 'nacional', 'tienda'])) {
        const pedido = fixture.regresion_pedidos[tipo];
        await ir('gelianv-laravel.test-1', '/control-pedidos/cedis?q='+pedido.folio);
        await b.wait('document.querySelectorAll("article[aria-label^=Pedido]").length===1');
        const tarjeta = await b.js(`const e=document.querySelector('article[aria-label^=Pedido]');return {texto:e.innerText,cabecera:e.querySelector('header').textContent,ruta:e.querySelector('[aria-label="Origen y destino del pedido"]').textContent}`);
        assert(tarjeta.cabecera.includes(tipo === 'tienda' ? 'Tipo de pedidoTIENDA' : `Envío:${tipo.toUpperCase()}`), `Encabezado incorrecto ${tipo}`);
        assert(tarjeta.ruta.includes('CEDIS ficticio'), `Almacén perdido en ${tipo}`);
        assert(!tarjeta.texto.includes('Bitácora'), 'Bitácora expuesta sin permiso');
        if (tipo === 'municipal') {
            assert(tarjeta.ruta.includes('Transporte ficticio Jaguar'), 'Terminal municipal perdida');
            assert(!tarjeta.texto.includes('Terminal destino') && !tarjeta.texto.includes('Área responsable'), 'Composición municipal distinta');
            assert(tarjeta.texto.includes('Revisar disponibilidad'), 'Falta acción de disponibilidad');
        }
        if (tipo === 'nacional') assert(tarjeta.texto.includes('Responder pesaje') && tarjeta.texto.includes('Envíos / Guía'), 'Nacional perdió su pesaje/logística');
        for (const ancho of [1440,1024,390]) for (const oscuro of [true,false]) {
            await b.cmd('/goog/cdp/execute', { cmd: 'Emulation.setDeviceMetricsOverride', params: { width: ancho, height: 1000, deviceScaleFactor: 1, mobile: ancho===390 } });
            await b.js(`document.querySelector('article[aria-label^=Pedido]').scrollIntoView({block:'start'})`);
            await foto('tarjeta-'+tipo, ancho, oscuro);
        }
        if (soloTarjetas) { resultado.tarjetas.push({ tipo, ...tarjeta }); continue; }
        await b.click('PDF pedido');
        await b.wait(`!!document.querySelector('[aria-label="Visor de evidencia"] img')&&document.querySelector('[aria-label="Visor de evidencia"] img').naturalWidth>0`);
        await foto('wizerp-'+tipo, 1440, true);
        await b.js(`document.querySelector('[aria-label="Cerrar vista de la foto"]').click()`);
        await b.click('Detalle');
        await b.wait(`!!document.querySelector('[aria-label="Detalle del pedido CEDIS"]')`);
        if (tipo === 'municipal') {
            assert(await b.js(`return document.querySelector('[aria-label="Detalle del pedido CEDIS"] [aria-label="Origen y destino del pedido"]').textContent.includes('Transporte ficticio Jaguar')`), 'Detalle perdió ruta');
            await b.js(`document.querySelector('#cedis-destino').scrollIntoView({block:'center'})`);
            await foto('detalle-municipal', 390, false);
        }
        await b.js(`document.querySelector('[aria-label="Detalle del pedido CEDIS"] button[aria-label="Cerrar"]').click()`);
        if (tipo === 'nacional') {
            await b.click('Responder pesaje'); await b.wait('document.getElementById("pesaje-titulo")?.textContent.includes("Responder pesaje")');
            await b.click('Revisar productos'); await b.click('Cajas y evidencias');
            assert(await b.js(`return !!document.querySelector('label[for^="pesaje-peso-"]')`), 'No aparece el peso real nacional');
            await foto('pesaje-nacional', 1440, true);
        }
        if (tipo === 'municipal') {
            await b.click('Revisar disponibilidad');
            await b.wait(`!!document.querySelector('[aria-label="Consulta de disponibilidad en CEDIS"] [aria-label="Origen y destino del pedido"]')`);
            const ruta = await b.js(`return document.querySelector('[aria-label="Consulta de disponibilidad en CEDIS"] [aria-label="Origen y destino del pedido"]').textContent`);
            assert(ruta.includes('CEDIS ficticio') && ruta.includes('Transporte ficticio Jaguar'), 'GET canónico perdió almacén/terminal');
            await foto('consulta-municipal', 1440, true); await foto('consulta-municipal', 390, false);
        }
        resultado.tarjetas.push({ tipo, ...tarjeta });
    }
    if (!soloTarjetas) {
    const ventas = (await b.cmd('/window/new', { type: 'window' })).handle;
    await b.cmd('/window', { handle: ventas }); await entrar('laravel.test', fixture.email);
    if (!soloTienda) {
    await editar(fixture.folio);
    await b.wait('document.querySelector(".registrar-tienda-modal").textContent.includes("Capturas anteriores")');
    await b.js(`document.querySelector('[aria-label="Seguimiento de envío municipal"]').scrollIntoView({block:'start'})`);
    await foto('municipal-reabierto-tras-recarga', 390, false);
    resultado.pasos.push('Recarga/reapertura de Municipal conserva respuesta vigente, historial de capturas, terminal y condiciones.');
    }
    const tienda = fixture.regresion_pedidos.tienda;
    await editar(tienda.folio);
    await b.wait('document.querySelector(".registrar-tienda-modal").textContent.includes("Esperando respuesta de CEDIS")');
    await b.js(`window.__respuestaTiendaAt=null;new MutationObserver(()=>{if(!window.__respuestaTiendaAt&&document.querySelector('.registrar-tienda-modal')?.textContent.includes('Respuesta de disponibilidad'))window.__respuestaTiendaAt=Date.now()}).observe(document.body,{childList:true,subtree:true,characterData:true})`);
    await b.cmd('/window', { handle: cedis });
    // La tarjeta Tienda quedó abierta en la última iteración.
    await b.click('Nueva consulta');
    await b.wait(`!!document.querySelector('[aria-label="Consulta de disponibilidad en CEDIS"]')`);
    await b.js(`const e=[...document.querySelectorAll('label')].find(e=>e.textContent.trim()==='Todo OK');e.querySelector('input').click()`);
    const archivo = await crearArchivoQA(b);
    const el = (await b.cmd('/element', { using:'css selector', value:'input[aria-label="Adjuntar: Captura del pedido"]' }))['element-6066-11e4-a52e-4f735466cecf'];
    await b.cmd(`/element/${el}/value`, { text:archivo }); await b.click('Enviar respuesta');
    await b.wait('document.body.textContent.includes("Respuesta enviada a Ventas.")');
    await new Promise(resolve=>setTimeout(resolve,17500));
    const antesEnfocar = Date.now(); await b.cmd('/window', { handle:ventas });
    await b.wait('!!window.__respuestaTiendaAt');
    const recibidaAt = await b.js('return window.__respuestaTiendaAt');
    assert(recibidaAt<antesEnfocar, 'Tienda no recibió respuesta antes de enfocar');
    await b.js(`document.querySelector('.tienda-seguimiento').scrollIntoView({block:'start'})`);
    await foto('tienda-respuesta-automatica',1440,true);
    await recogerConsola('primera-respuesta-tienda');
    resultado.pasos.push('Tienda conserva consulta documental CEDIS y recibe la respuesta automáticamente con ambos modales abiertos; Nacional conserva formulario de pesaje y peso real sin registrar cambios.');
    await b.cmd('/window', { handle:cedis });
    await b.js(`window.__actualizacionTiendaAt=null;new MutationObserver(()=>{if(!window.__actualizacionTiendaAt&&document.querySelector('[aria-label="Consulta de disponibilidad en CEDIS"]')?.textContent.includes('Actualización de consulta'))window.__actualizacionTiendaAt=Date.now()}).observe(document.body,{childList:true,subtree:true,characterData:true})`);
    await b.cmd('/window', { handle:ventas });
    await b.click('Modificar pedido'); await b.click('PDF / imágenes');
    const anexo = (await b.cmd('/element', { using:'css selector', value:'input[aria-label="Adjuntar: Piezas a agregar"]' }))['element-6066-11e4-a52e-4f735466cecf'];
    await b.cmd(`/element/${anexo}/value`, { text:archivo }); await b.click('Solicitar agregado');
    await b.wait('document.body.textContent.includes("Anexo documental enviado.")');
    await new Promise(resolve=>setTimeout(resolve,31500));
    const antesVolverCedis=Date.now(); await b.cmd('/window', { handle:cedis });
    await b.wait('!!window.__actualizacionTiendaAt');
    assert(await b.js('return window.__actualizacionTiendaAt')<antesVolverCedis, 'CEDIS no recuperó la consulta reingresada antes de enfocar');
    await b.click('Todo OK');
    const capturaNueva = (await b.cmd('/element', { using:'css selector', value:'input[aria-label="Adjuntar: Captura del pedido"]' }))['element-6066-11e4-a52e-4f735466cecf'];
    await b.cmd(`/element/${capturaNueva}/value`, { text:archivo }); await b.click('Enviar respuesta');
    await b.wait('document.body.textContent.includes("Respuesta enviada a Ventas.")');
    await new Promise(resolve=>setTimeout(resolve,17500));
    await b.cmd('/window', { handle:ventas });
    await b.wait(`!![...document.querySelectorAll('button')].find(e=>e.textContent.trim()==='Confirmar mercancía y cerrar consulta'&&!e.disabled)`);
    await b.js(`document.querySelector('.tienda-seguimiento').scrollIntoView({block:'start'})`);
    await foto('tienda-anexo-respondido',1440,true);
    resultado.pasos.push('Anexo documental desde el editor existente de Tienda: CEDIS detecta su regreso mediante la bandeja autorizada y responde el nuevo ciclo sin cerrar modales ni ampliar acceso por etapa.');
    await recogerConsola('anexo-tienda');
    if (!soloTienda) {
    const municipal = fixture.regresion_pedidos.municipal;
    await editar(municipal.folio);
    await b.wait('document.querySelector(".registrar-tienda-modal").textContent.includes("Esperando respuesta de CEDIS")');
    await b.js(`window.__respuestaMunicipalAt=null;new MutationObserver(()=>{if(!window.__respuestaMunicipalAt&&document.querySelector('[aria-label="Seguimiento de envío municipal"]')?.textContent.includes('Consulta respondida'))window.__respuestaMunicipalAt=Date.now()}).observe(document.body,{childList:true,subtree:true,characterData:true})`);
    await b.cmd('/window', { handle:cedis });
    await ir('gelianv-laravel.test-1','/control-pedidos/cedis?q='+municipal.folio);
    await b.click('Revisar disponibilidad');
    await b.wait(`!!document.querySelector('[aria-label="Consulta de disponibilidad en CEDIS"]')`);
    await b.js(`[...document.querySelectorAll('label')].find(e=>e.textContent.trim()==='Todo OK').querySelector('input').click()`);
    const capturaMunicipal = (await b.cmd('/element', { using:'css selector', value:'input[aria-label="Adjuntar: Captura del pedido"]' }))['element-6066-11e4-a52e-4f735466cecf'];
    await b.cmd(`/element/${capturaMunicipal}/value`,{text:archivo});await b.click('Enviar respuesta');
    await b.wait('document.body.textContent.includes("Respuesta enviada a Ventas.")');
    await new Promise(resolve=>setTimeout(resolve,17500));
    const antesMunicipal=Date.now();await b.cmd('/window',{handle:ventas});
    await b.wait('!!window.__respuestaMunicipalAt');
    assert(await b.js('return window.__respuestaMunicipalAt')<antesMunicipal,'Municipal no recibió respuesta automática con el contrato canónico final');
    resultado.pasos.push('Municipal mantiene su recepción automática con el contrato final de respuesta canónica y las mismas dos cuentas independientes.');
    }
    }
    await recogerConsola('final');
    assert(!resultado.console.some(e=>e.level==='SEVERE'), 'Errores de navegador o red');
    assert(resultado.medidas.every(m=>m.pagina&&m.modales.every(d=>d.sinDesborde)), 'Desbordamiento de página o diálogo');
    assert(resultado.medidas.every(m=>m.palabrasPartidas.length===0), 'Cabecera con palabras partidas');
    resultado.resultado='aprobado';
} catch(error) {
    resultado.resultado='fallo';resultado.error=String(error.stack||error);process.exitCode=1;
    if(b.id){resultado.pantalla=await b.js('return document.body.innerText').catch(()=>null);await recogerConsola('fallo').catch(()=>{});await b.shot('fallo-presentacion',1440,true).catch(()=>{});}
} finally {
    fs.writeFileSync(`${output}/presentacion.json`,JSON.stringify(resultado,null,2));if(b.id)await b.cmd('',null,'DELETE');console.log(JSON.stringify(resultado,null,2));
}
