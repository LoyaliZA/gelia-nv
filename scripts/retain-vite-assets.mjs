import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { archivosDeManifiesto, planRetencion } from '../resources/js/utils/retainViteAssets.js';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const buildDir = path.join(root, 'public/build');
const stashDir = path.join(root, 'storage/framework/deploy/stash');
const previoPath = path.join(root, 'storage/framework/deploy/manifest.previous.json');

function leerJson(ruta) {
    if (!fs.existsSync(ruta)) return null;
    return JSON.parse(fs.readFileSync(ruta, 'utf8'));
}

function copiarLista(origenDir, destinoDir, relativos) {
    for (const relativo of relativos) {
        const desde = path.join(origenDir, relativo);
        if (!fs.existsSync(desde)) continue;
        const hacia = path.join(destinoDir, relativo);
        fs.mkdirSync(path.dirname(hacia), { recursive: true });
        fs.copyFileSync(desde, hacia);
    }
}

function listarRelativos(directorio, base) {
    if (!fs.existsSync(directorio)) return [];
    const salida = [];
    for (const entrada of fs.readdirSync(directorio, { withFileTypes: true })) {
        const absoluto = path.join(directorio, entrada.name);
        if (entrada.isDirectory()) {
            salida.push(...listarRelativos(absoluto, base));
            continue;
        }
        salida.push(path.relative(base, absoluto).split(path.sep).join('/'));
    }
    return salida;
}

const manifiestoActual = leerJson(path.join(buildDir, 'manifest.json'));
if (manifiestoActual) {
    fs.mkdirSync(path.dirname(previoPath), { recursive: true });
    fs.writeFileSync(previoPath, JSON.stringify(manifiestoActual));
    fs.rmSync(stashDir, { recursive: true, force: true });
    copiarLista(buildDir, stashDir, archivosDeManifiesto(manifiestoActual));
}

const viteBin = path.join(root, 'node_modules/vite/bin/vite.js');
const compilacion = spawnSync(process.execPath, [viteBin, 'build'], {
    cwd: root,
    stdio: 'inherit',
});

if (compilacion.status !== 0) {
    process.exit(compilacion.status ?? 1);
}

const actual = leerJson(path.join(buildDir, 'manifest.json')) || {};
const anterior = leerJson(previoPath) || {};
const plan = planRetencion({
    anteriores: archivosDeManifiesto(anterior),
    actuales: archivosDeManifiesto(actual),
    presentes: listarRelativos(path.join(buildDir, 'assets'), buildDir),
});

copiarLista(stashDir, buildDir, plan.restaurar);

for (const relativo of plan.borrar) {
    const absoluto = path.join(buildDir, relativo);
    if (fs.existsSync(absoluto)) fs.rmSync(absoluto);
}
