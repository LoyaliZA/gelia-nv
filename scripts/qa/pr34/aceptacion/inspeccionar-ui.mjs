import fs from 'node:fs';import {Browser,save} from './browser.mjs';
const b=new Browser();try{
 await b.start(process.argv[2]||'ventas');await b.go(process.argv[3]||'/control-pedidos?q=QA34-REALTIME');
 await b.wait('document.body.innerText.trim().length>200');
 await new Promise(r=>setTimeout(r,800));
 console.log(JSON.stringify(await b.js('return {texto:document.body.innerText.slice(-4500),botones:[...document.querySelectorAll("button,summary")].map(b=>({texto:b.textContent.trim(),aria:b.getAttribute("aria-label"),title:b.title}))}')));
 await b.shot('inspeccion-'+(process.argv[2]||'ventas'));
}finally{await b.close();}
