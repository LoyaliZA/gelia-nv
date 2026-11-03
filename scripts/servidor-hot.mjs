import http from 'node:http';
import https from 'node:https';

const respondeHttp = (protocol, host, port) => new Promise((resolve) => {
    const lib = protocol === 'https:' ? https : http;
    let settled = false;
    const fin = (ok) => { if (!settled) { settled = true; resolve(ok); } };
    const req = lib.get({ host, port, path: '/', timeout: 400, rejectUnauthorized: false }, (res) => {
        res.resume();
        fin(true);
    });
    req.on('timeout', () => { req.destroy(); fin(false); });
    req.on('error', () => fin(false));
});

export async function servidorHotActivo(contenido) {
    let url;
    try { url = new URL(String(contenido).trim()); } catch { return false; }
    const port = Number(url.port || (url.protocol === 'https:' ? 443 : 80));
    if (!Number.isInteger(port) || port < 1 || port > 65535) return false;
    // El proxy de Docker acepta TCP aunque Vite esté apagado; hace falta respuesta HTTP.
    const hosts = url.hostname === '127.0.0.1' ? [url.hostname] : [url.hostname, '127.0.0.1'];
    for (const host of hosts) if (await respondeHttp(url.protocol, host, port)) return true;
    return false;
}
