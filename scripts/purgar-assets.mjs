import fs from 'node:fs';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

const DIA_MS = 24 * 60 * 60 * 1000;
const HASH = /-[A-Za-z0-9_-]{8,}\.(js|css|woff2?|png|svg|jpg|webp|map)$/;

export function validarParametros(retencionDias, maxBuilds) {
    const dias = Number(retencionDias);
    const maximo = Number(maxBuilds);
    if (!Number.isInteger(dias) || dias < 2 || dias > 3) {
        return 'ASSETS_RETENCION_DIAS debe estar entre 2 y 3.';
    }
    if (!Number.isInteger(maximo) || maximo < 2 || maximo > 10) {
        return 'ASSETS_MAX_BUILDS debe estar entre 2 y 10.';
    }
    return null;
}

function textos(valor) {
    if (!Array.isArray(valor)) {
        return [];
    }
    return valor.filter((item) => typeof item === 'string' && item !== '');
}

export function esChunkHasheado(ruta) {
    if (typeof ruta !== 'string' || !ruta.startsWith('assets/')) {
        return false;
    }
    return HASH.test(ruta);
}

export function archivosDeManifiesto(manifiesto) {
    if (!manifiesto || typeof manifiesto !== 'object') {
        return [];
    }

    const vistos = new Set();
    const cola = Object.keys(manifiesto);
    const archivos = new Set();

    while (cola.length > 0) {
        const clave = cola.shift();
        if (vistos.has(clave)) {
            continue;
        }
        vistos.add(clave);
        const entrada = manifiesto[clave];
        if (!entrada || typeof entrada !== 'object') {
            continue;
        }
        if (typeof entrada.file === 'string' && entrada.file !== '') {
            archivos.add(entrada.file);
        }
        for (const css of textos(entrada.css)) {
            archivos.add(css);
        }
        for (const asset of textos(entrada.assets)) {
            archivos.add(asset);
        }
        for (const campo of ['imports', 'dynamicImports']) {
            for (const ref of textos(entrada[campo])) {
                if (Object.prototype.hasOwnProperty.call(manifiesto, ref)) {
                    cola.push(ref);
                } else if (ref.startsWith('assets/')) {
                    archivos.add(ref);
                }
            }
        }
    }

    return [...archivos];
}

function manifiestoRetenidoLegible(compilacion) {
    return !compilacion.ilegible && compilacion.manifiesto && typeof compilacion.manifiesto === 'object';
}

/**
 * @param {{
 *   compilaciones: Array<{ id: string, mtimeMs: number, manifiesto: object | null, ilegible?: boolean }>,
 *   ahora: number,
 *   retencionDias: number,
 *   maxBuilds: number,
 *   archivos: Array<{ ruta: string, mtimeMs: number }>,
 * }} entrada
 */
export function planificarPurga({ compilaciones, ahora, retencionDias, maxBuilds, archivos }) {
    const error = validarParametros(retencionDias, maxBuilds);
    if (error) {
        return { error, borrar: [], retenidas: [], candidatas: [] };
    }

    const retenidas = [];
    const candidatas = [];
    const porRetener = [];
    const porCandidata = [];

    compilaciones.forEach((compilacion, indice) => {
        const siempre = indice < 2;
        const vieja = ahora - compilacion.mtimeMs > retencionDias * DIA_MS;
        const fueraDeTope = indice >= maxBuilds;
        const candidata = !siempre && (vieja || fueraDeTope);
        if (candidata) {
            candidatas.push(compilacion.id);
            porCandidata.push(compilacion);
            return;
        }
        retenidas.push(compilacion.id);
        porRetener.push(compilacion);
    });

    for (const compilacion of porRetener) {
        if (!manifiestoRetenidoLegible(compilacion)) {
            return {
                error: `No se pudo leer el manifiesto retenido ${compilacion.id}.`,
                borrar: [],
                retenidas,
                candidatas,
            };
        }
    }

    const protegidos = new Set();
    for (const compilacion of porRetener) {
        for (const archivo of archivosDeManifiesto(compilacion.manifiesto)) {
            protegidos.add(archivo);
        }
    }

    const referenciados = new Set(protegidos);
    const borrar = new Set();
    for (const compilacion of porCandidata) {
        if (!manifiestoRetenidoLegible(compilacion)) {
            continue;
        }
        for (const archivo of archivosDeManifiesto(compilacion.manifiesto)) {
            referenciados.add(archivo);
            if (!protegidos.has(archivo) && esChunkHasheado(archivo)) {
                borrar.add(archivo);
            }
        }
    }

    for (const archivo of archivos) {
        if (referenciados.has(archivo.ruta) || !esChunkHasheado(archivo.ruta)) {
            continue;
        }
        if (ahora - archivo.mtimeMs > retencionDias * DIA_MS) {
            borrar.add(archivo.ruta);
        }
    }

    return {
        error: null,
        borrar: [...borrar].sort(),
        retenidas,
        candidatas,
    };
}

export function aplicarLista(rutas, { aplicar, unlink }) {
    if (!aplicar) {
        return [];
    }
    const borrados = [];
    for (const ruta of rutas) {
        unlink(ruta);
        borrados.push(ruta);
    }
    return borrados;
}

function leerManifiesto(ruta) {
    try {
        const json = JSON.parse(fs.readFileSync(ruta, 'utf8'));
        if (!json || typeof json !== 'object' || Array.isArray(json)) {
            return { ilegible: true, manifiesto: null };
        }
        return { ilegible: false, manifiesto: json };
    } catch {
        return { ilegible: true, manifiesto: null };
    }
}

function listarCompilaciones(buildDir) {
    const dir = path.join(buildDir, 'manifests');
    if (!fs.existsSync(dir)) {
        return [];
    }
    return fs.readdirSync(dir)
        .filter((nombre) => nombre.endsWith('.json'))
        .sort()
        .reverse()
        .map((nombre) => {
            const ruta = path.join(dir, nombre);
            const stat = fs.statSync(ruta);
            return {
                id: nombre,
                mtimeMs: stat.mtimeMs,
                ...leerManifiesto(ruta),
            };
        });
}

function listarArchivos(buildDir) {
    const assets = path.join(buildDir, 'assets');
    if (!fs.existsSync(assets)) {
        return [];
    }
    const encontrados = [];
    const pila = [assets];
    while (pila.length > 0) {
        const actual = pila.pop();
        for (const nombre of fs.readdirSync(actual)) {
            const absoluto = path.join(actual, nombre);
            const stat = fs.statSync(absoluto);
            if (stat.isDirectory()) {
                pila.push(absoluto);
                continue;
            }
            encontrados.push({
                ruta: path.relative(buildDir, absoluto).split(path.sep).join('/'),
                mtimeMs: stat.mtimeMs,
                absoluto,
            });
        }
    }
    return encontrados;
}

function rutaDentroDeAssets(buildDir, relativo) {
    const absoluto = path.resolve(buildDir, relativo);
    const assets = path.resolve(buildDir, 'assets') + path.sep;
    if (!absoluto.startsWith(assets)) {
        return null;
    }
    return absoluto;
}

function escribirLog(linea) {
    const dir = path.resolve('storage/logs');
    fs.mkdirSync(dir, { recursive: true });
    fs.appendFileSync(path.join(dir, 'deploy-assets.log'), `${linea}\n`);
}

export function ejecutarPurga({
    buildDir = 'public/build',
    aplicar = false,
    retencionDias = Number(process.env.ASSETS_RETENCION_DIAS || 3),
    maxBuilds = Number(process.env.ASSETS_MAX_BUILDS || 5),
    ahora = Date.now(),
    sha = process.env.DEPLOY_SHA || '',
} = {}) {
    const compilaciones = listarCompilaciones(buildDir);
    const archivos = listarArchivos(buildDir);
    const plan = planificarPurga({
        compilaciones,
        ahora,
        retencionDias,
        maxBuilds,
        archivos,
    });

    if (plan.error) {
        escribirLog(`${new Date(ahora).toISOString()} sha=${sha || '-'} error=${plan.error}`);
        return plan;
    }

    let bytes = 0;
    const borrados = [];
    if (aplicar) {
        for (const relativo of plan.borrar) {
            const absoluto = rutaDentroDeAssets(buildDir, relativo);
            if (!absoluto || !fs.existsSync(absoluto)) {
                continue;
            }
            bytes += fs.statSync(absoluto).size;
            fs.unlinkSync(absoluto);
            borrados.push(relativo);
        }
    } else {
        for (const relativo of plan.borrar) {
            const absoluto = rutaDentroDeAssets(buildDir, relativo);
            if (absoluto && fs.existsSync(absoluto)) {
                bytes += fs.statSync(absoluto).size;
            }
        }
    }

    escribirLog([
        new Date(ahora).toISOString(),
        `sha=${sha || '-'}`,
        `modo=${aplicar ? 'aplicar' : 'dry-run'}`,
        `retencion_dias=${retencionDias}`,
        `max_builds=${maxBuilds}`,
        `retenidas=${plan.retenidas.join(',') || '-'}`,
        `candidatas=${plan.candidatas.join(',') || '-'}`,
        `archivos=${(aplicar ? borrados : plan.borrar).join(',') || '-'}`,
        `bytes=${bytes}`,
    ].join(' '));

    return { ...plan, bytes, borrados };
}

function esEjecucionDirecta() {
    const arg = process.argv[1];
    if (!arg) {
        return false;
    }
    return import.meta.url === pathToFileURL(path.resolve(arg)).href;
}

if (esEjecucionDirecta()) {
    const aplicar = process.argv.includes('--apply') && !process.argv.includes('--dry-run');
    const resultado = ejecutarPurga({ aplicar });
    if (resultado.error) {
        process.stderr.write(`${resultado.error}\n`);
        process.exit(1);
    }
    const verbo = aplicar ? 'Eliminados' : 'Se borrarían';
    process.stdout.write(`${verbo} ${resultado.borrar.length} archivos (${resultado.bytes} bytes).\n`);
}
