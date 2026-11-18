import fs from 'node:fs';
import { Browser, fixture, output, assert, crearArchivoQA } from './navegadorComun.mjs';
const b=new Browser();const folio='WIZ-AUTO-'+fixture.usuario_id;const result={pasos:[],medidas:[]};
const seleccionar=(label,value)=>b.js(`const e=[...document.querySelectorAll('.registrar-tienda-modal label')].find(e=>e.textContent.trim().startsWith(arguments[0])).querySelector('select');e.value=arguments[1];e.dispatchEvent(new Event('change',{bubbles:true}))`,[label,String(value)]);
try{
    const state=(await(await fetch('http://selenium:4444/status')).json()).value;assert(!state.nodes.some(n=>n.slots.some(s=>s.session)),'Selenium ocupado');
    b.id=(await b.cmd('',{capabilities:{alwaysMatch:{browserName:'chrome','goog:chromeOptions':{args:['--headless=new','--no-sandbox','--disable-dev-shm-usage']},'goog:loggingPrefs':{browser:'ALL'}}}})).sessionId;
    const archivo=await crearArchivoQA(b);
    await b.go('/login');await b.type('input[type=text]',fixture.email);await b.type('input[type=password]','M01-solo-pruebas-2026');await b.js('document.querySelector("button[type=submit]").click()');await b.wait('!location.pathname.includes("/login")');
    await b.go('/control-pedidos?q=QA-M01-NAVEGADOR');await b.click('Editar');await b.wait('!!document.querySelector("#folio-tienda")');
    await b.type('#folio-tienda',folio);
    await b.click('Agregar consignatario');await b.type('[role=dialog] form label:first-of-type input','Persona ficticia de continuidad');await b.type('[role=dialog] form label:last-of-type input','3311111111');await b.click('Guardar consignatario');
    await b.wait('document.body.textContent.includes("Adjuntar identificación")');
    const el=(await b.cmd('/element',{using:'css selector',value:'.registrar-tienda-documento input[type=file]'}))['element-6066-11e4-a52e-4f735466cecf'];await b.cmd(`/element/${el}/value`,{text:archivo});await b.wait('document.body.textContent.includes("Listo. m01-qa.png")');
    await seleccionar('Terminal destino',fixture.transporte_id);await seleccionar('Almacén de la mercancía',fixture.almacen_cedis_id);await seleccionar('Costo de transporte','diferido');
    assert(await b.js(`return ![...document.querySelectorAll('button')].find(e=>e.textContent.trim()==='Enviar consulta').disabled`),'Exige guardar antes de enviar');
    result.pasos.push('Campos completos habilitan Enviar consulta sin pulsar Guardar solicitud.');
    for(const width of [1440,1024,390]){await b.cmd('/goog/cdp/execute',{cmd:'Emulation.setDeviceMetricsOverride',params:{width,height:1000,deviceScaleFactor:1,mobile:width===390}});result.medidas.push(await b.shot('enviar-sin-guardar',width,true));}
    await b.js(`window.qaPosts=[];window.axios.interceptors.request.use(c=>{if(c.method==='post')window.qaPosts.push(c.url);return c})`);
    await b.click('Enviar consulta');await b.wait('document.body.textContent.includes("La consulta ya fue enviada.")');
    result.solicitudes=await b.js('return window.qaPosts');
    const guardar=result.solicitudes.findIndex(u=>u.includes('/municipal/')&&u.endsWith('/solicitud'));
    const enviar=result.solicitudes.findIndex(u=>u.includes('/municipal/')&&u.endsWith('/consulta'));
    assert(guardar>=0&&enviar>guardar,'No confirmó la solicitud antes de enviarla');
    result.pasos.push('Un clic guarda datos comunes y municipales antes del endpoint de envío.');
    await b.js(`document.querySelector('button[aria-label="Cerrar registro"]').click()`);
    await b.go('/control-pedidos?q='+folio);await b.click('Editar');await b.wait('document.body.textContent.includes("La consulta ya fue enviada.")');
    assert(await b.js(`return [...document.querySelectorAll('label')].find(e=>e.textContent.startsWith('Terminal destino')).querySelector('select').value`)===String(fixture.transporte_id),'No conservó terminal');
    assert(await b.js(`return [...document.querySelectorAll('label')].find(e=>e.textContent.startsWith('Costo de transporte')).querySelector('select').value`)==='diferido','No conservó condición de transporte');
    assert(await b.js('return document.querySelector("#folio-tienda").value')===folio,'No conservó folio');
    result.pasos.push('Cerrar, recargar Registro y reabrir conserva solicitud enviada, terminal, folio y costo diferido.');
    result.console=await b.cmd('/log',{type:'browser'});assert(!result.console.some(e=>e.level==='SEVERE'),'Error de consola');result.resultado='aprobado';
}catch(error){result.resultado='fallo';result.error=String(error.stack||error);if(b.id)result.pantalla=await b.js('return document.body.innerText');process.exitCode=1;}
finally{fs.writeFileSync(`${output}/envio-automatico.json`,JSON.stringify(result,null,2));if(b.id)await b.cmd('',null,'DELETE');console.log(JSON.stringify(result,null,2));}
