import fs from 'node:fs';
import { Browser, fixture, output, assert } from './navegadorComun.mjs';
const b = new Browser(); const result = {};
try {
    const state=(await (await fetch('http://selenium:4444/status')).json()).value;
    assert(!state.nodes.some(n=>n.slots.some(s=>s.session)),'Selenium ocupado');
    b.id=(await b.cmd('',{capabilities:{alwaysMatch:{browserName:'chrome','goog:chromeOptions':{args:['--headless=new','--no-sandbox','--disable-dev-shm-usage']},'goog:loggingPrefs':{browser:'ALL'}}}})).sessionId;
    await b.go('/login');await b.type('input[type=text]',fixture.email);await b.type('input[type=password]','M01-solo-pruebas-2026');
    await b.js('document.querySelector("button[type=submit]").click()');await b.wait('!location.pathname.includes("/login")');
    await b.go('/control-pedidos');await b.click('Nuevo pedido');await b.wait('!!document.querySelector("#modalidad-tienda")');
    await b.js(`const e=document.querySelector('#modalidad-tienda');e.value='municipal';e.dispatchEvent(new Event('change',{bubbles:true}))`);
    await b.click('Guardar solicitud');
    await b.wait(`document.querySelector('.registrar-tienda-modal').textContent.includes('Seleccione un transporte habilitado')`);
    assert(await b.js(`return [...document.querySelectorAll('.registrar-tienda-modal button')].find(e=>e.textContent.trim()==='Enviar consulta').disabled`),'Envía solicitud incompleta');
    await b.shot('validacion',390,false);
    await b.js(`document.querySelector('button[aria-label="Cerrar registro"]').click()`);
    await b.wait('!document.querySelector(".registrar-tienda-modal")');
    result.pasos=['Solicitud incompleta muestra validación servidor en el formulario, bloquea Enviar consulta y permite cerrar después de terminar la operación.'];
    result.console=await b.cmd('/log',{type:'browser'});
    // El 422 es la respuesta esperada de la validación, no un fallo JavaScript.
    assert(result.console.filter(e=>e.level==='SEVERE').every(e=>e.message.includes('422')),'Error JavaScript inesperado');result.resultado='aprobado';
} catch(error){result.resultado='fallo';result.error=String(error.stack||error);if(b.id)result.pantalla=await b.js('return document.body.innerText');process.exitCode=1;}
finally{fs.writeFileSync(`${output}/validacion.json`,JSON.stringify(result,null,2));if(b.id)await b.cmd('',null,'DELETE');console.log(JSON.stringify(result,null,2));}
