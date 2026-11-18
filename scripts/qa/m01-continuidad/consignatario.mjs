import fs from 'node:fs';
import { Browser, fixture, output, assert, crearArchivoQA } from './navegadorComun.mjs';
const b = new Browser(); const result = { pasos: [], medidas: [] };
try {
    const state=(await (await fetch('http://selenium:4444/status')).json()).value;
    assert(!state.nodes.some(n=>n.slots.some(s=>s.session)),'Selenium ocupado');
    b.id=(await b.cmd('',{capabilities:{alwaysMatch:{browserName:'chrome','goog:chromeOptions':{args:['--headless=new','--no-sandbox','--disable-dev-shm-usage']},'goog:loggingPrefs':{browser:'ALL'}}}})).sessionId;
    const archivo=await crearArchivoQA(b);
    await b.go('/login');await b.type('input[type=text]',fixture.email);await b.type('input[type=password]','M01-solo-pruebas-2026');
    await b.js('document.querySelector("button[type=submit]").click()');await b.wait('!location.pathname.includes("/login")');
    await b.go('/control-pedidos?q=QA-M01-NAVEGADOR');await b.click('Editar');
    await b.wait('!!document.querySelector("#folio-tienda")');
    await b.click('Agregar consignatario');
    await b.type('[role=dialog] form label:first-of-type input','Persona ficticia de continuidad');
    await b.type('[role=dialog] form label:last-of-type input','3311111111');
    await b.click('Guardar consignatario');await b.wait('document.body.textContent.includes("Adjuntar identificación")');
    result.pasos.push('Alta contextual con Guardar consignatario; selección de la persona creada.');
    await b.js(`document.querySelector('.registrar-tienda-modal li input[type=file]').addEventListener('click',()=>{window.qaSelectorAbierto=true})`);
    const pulsarArchivo=async texto=>{const e=await b.js(`const e=[...document.querySelectorAll('button')].find(e=>e.textContent.trim()===arguments[0]);e.scrollIntoView({block:'center'});return e`,[texto]);await b.cmd(`/element/${e['element-6066-11e4-a52e-4f735466cecf']}/click`,{});};
    await pulsarArchivo('Adjuntar identificación');
    assert(await b.js('return window.qaSelectorAbierto'),'El botón no abre el selector de archivo');
    const subir=async()=>{const e=(await b.cmd('/element',{using:'css selector',value:'.registrar-tienda-modal li input[type=file]'}))['element-6066-11e4-a52e-4f735466cecf'];await b.cmd(`/element/${e}/value`,{text:archivo});};
    await subir();await b.wait('document.body.textContent.includes("Identificación vigente, versión 1")');
    await pulsarArchivo('Sustituir identificación');await subir();await b.wait('document.body.textContent.includes("Identificación vigente, versión 2")');
    assert(!await b.js('return document.body.textContent.includes("Unable to create")'),'Continúa el fallo de escritura');
    result.pasos.push('Botón real abre selector; carga privada y sustitución correctas como sail, versiones 1 y 2; mensaje en español.');
    for(const width of [1440,1024,390])for(const dark of [false,true]){
        await b.cmd('/goog/cdp/execute',{cmd:'Emulation.setDeviceMetricsOverride',params:{width,height:1000,deviceScaleFactor:1,mobile:width===390}});
        await b.js(`[...document.querySelectorAll('button')].find(e=>e.textContent.trim()==='Sustituir identificación').scrollIntoView({block:'center'})`);
        result.medidas.push(await b.shot('consignatario',width,dark));
        assert(await b.js(`const d=document.querySelector('.registrar-tienda-modal'),body=d.querySelector('.gelia-modal-body');return d.scrollWidth<=d.clientWidth+1&&body.scrollWidth<=body.clientWidth+1`),'Desborde de consignatario');
    }
    result.console=await b.cmd('/log',{type:'browser'});assert(!result.console.some(e=>e.level==='SEVERE'),'Error de consola');result.resultado='aprobado';
} catch(error){result.resultado='fallo';result.error=String(error.stack||error);if(b.id)result.pantalla=await b.js('return document.body.innerText');process.exitCode=1;}
finally{fs.writeFileSync(`${output}/consignatario.json`,JSON.stringify(result,null,2));if(b.id)await b.cmd('',null,'DELETE');console.log(JSON.stringify(result,null,2));}
