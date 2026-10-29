import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { aplicarLista, esChunkHasheado, planificarPurga } from './purgar-assets.mjs';

const AHORA = Date.parse('2026-10-29T00:00:00Z');
const DIA = 24 * 60 * 60 * 1000;

function compilacion(id, dias, archivo) {
    return {
        id,
        mtimeMs: AHORA - dias * DIA,
        manifiesto: {
            'resources/js/app.jsx': {
                file: `assets/${archivo}`,
                css: [`assets/${archivo.replace('.js', '.css')}`],
                imports: ['_vendor'],
            },
            _vendor: { file: 'assets/vendor-vvvvvvvv.js' },
        },
    };
}

describe('planificarPurga', () => {
    it('retiene actual y anterior aunque tengan más de 3 días, y aplica tope y antigüedad', () => {
        const compilaciones = [
            compilacion('actual', 10, 'a-aaaaaaaa.js'),
            compilacion('anterior', 9, 'b-bbbbbbbb.js'),
            compilacion('reciente-2', 1, 'c-cccccccc.js'),
            compilacion('reciente-3', 1, 'd-dddddddd.js'),
            compilacion('vieja-en-tope', 4, 'e-eeeeeeee.js'),
            compilacion('fuera-5', 1, 'f-ffffffff.js'),
            compilacion('fuera-6', 1, 'g-gggggggg.js'),
        ];

        const plan = planificarPurga({
            compilaciones,
            ahora: AHORA,
            retencionDias: 3,
            maxBuilds: 5,
            archivos: [],
        });

        assert.equal(plan.error, null);
        assert.deepEqual(plan.retenidas, ['actual', 'anterior', 'reciente-2', 'reciente-3']);
        assert.deepEqual(plan.candidatas, ['vieja-en-tope', 'fuera-5', 'fuera-6']);
        assert.equal(plan.borrar.includes('assets/a-aaaaaaaa.js'), false);
        assert.equal(plan.borrar.includes('assets/b-bbbbbbbb.js'), false);
        assert.equal(plan.borrar.includes('assets/vendor-vvvvvvvv.js'), false);
        assert.equal(plan.borrar.includes('assets/e-eeeeeeee.js'), true);
        assert.equal(plan.borrar.includes('assets/f-ffffffff.js'), true);
        assert.equal(plan.borrar.includes('assets/g-gggggggg.js'), true);
    });

    it('borra huérfanos viejos con hash y conserva los recientes o sin hash', () => {
        const plan = planificarPurga({
            compilaciones: [compilacion('actual', 0, 'a-aaaaaaaa.js')],
            ahora: AHORA,
            retencionDias: 3,
            maxBuilds: 5,
            archivos: [
                { ruta: 'assets/huerfano-hhhhhhhh.js', mtimeMs: AHORA - 5 * DIA },
                { ruta: 'assets/nuevo-iiiiiiii.js', mtimeMs: AHORA - DIA },
                { ruta: 'assets/sinhash.js', mtimeMs: AHORA - 5 * DIA },
                { ruta: 'manifest.json', mtimeMs: AHORA - 5 * DIA },
            ],
        });

        assert.deepEqual(plan.borrar, ['assets/huerfano-hhhhhhhh.js']);
    });

    it('rechaza retención o tope fuera de rango sin proponer borrados', () => {
        for (const retencionDias of [1, 11]) {
            const plan = planificarPurga({
                compilaciones: [compilacion('actual', 0, 'a-aaaaaaaa.js')],
                ahora: AHORA,
                retencionDias,
                maxBuilds: 5,
                archivos: [],
            });
            assert.notEqual(plan.error, null);
            assert.deepEqual(plan.borrar, []);
        }
    });

    it('aborta sin borrar si un manifiesto retenido no se lee', () => {
        const plan = planificarPurga({
            compilaciones: [
                { id: 'actual', mtimeMs: AHORA, manifiesto: null, ilegible: true },
                compilacion('anterior', 1, 'b-bbbbbbbb.js'),
            ],
            ahora: AHORA,
            retencionDias: 3,
            maxBuilds: 5,
            archivos: [{ ruta: 'assets/huerfano-hhhhhhhh.js', mtimeMs: AHORA - 5 * DIA }],
        });

        assert.match(plan.error, /actual/);
        assert.deepEqual(plan.borrar, []);
    });

    it('solo trata como chunk un archivo de assets con hash', () => {
        assert.equal(esChunkHasheado('assets/app-abcdefgh.js'), true);
        assert.equal(esChunkHasheado('assets/app.js'), false);
        assert.equal(esChunkHasheado('manifest.json'), false);
        assert.equal(esChunkHasheado('manifests/20261029-abc1234.json'), false);
    });
});

describe('aplicarLista', () => {
    it('en dry-run no llama a unlink', () => {
        let llamadas = 0;
        const borrados = aplicarLista(['assets/a-aaaaaaaa.js'], {
            aplicar: false,
            unlink: () => {
                llamadas += 1;
            },
        });
        assert.equal(llamadas, 0);
        assert.deepEqual(borrados, []);
    });
});
