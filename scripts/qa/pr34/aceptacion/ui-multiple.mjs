import {Browser,save,assert} from './browser.mjs';
const b=new Browser();try{
 await b.start('ventas');await b.go('/control-pedidos?q=QA34-LECTURA-AJENA');await b.wait("document.body.innerText.includes('QA34-LECTURA-AJENA')");
 const datos=await b.js(`const p=JSON.parse(document.querySelector('script[data-page="app"]').textContent).props;return {auth:p.auth?.user,flags:p.pedidos?.data?.map(x=>({id:x.id,puede_editar:x.puede_editar,estatus:x.estatus}))}`);save('ui-permisos-ventas',datos);console.log(JSON.stringify(datos));
 await b.js("document.querySelector('[aria-label=\"Cerrar detalle\"]')?.click()");
 console.log(JSON.stringify(await b.js('return [...document.querySelectorAll("button")].map(e=>({texto:e.textContent.trim(),aria:e.getAttribute("aria-label"),title:e.title}))')));
}finally{await b.close();}
