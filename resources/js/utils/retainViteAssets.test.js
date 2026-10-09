// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { archivosDeManifiesto, planRetencion } from './retainViteAssets';

describe('planRetencion', () => {
    it('conserva los archivos del build anterior y borra los que ya no están en ninguna generación', () => {
        const anteriores = archivosDeManifiesto({
            'resources/js/Pages/Vieja.jsx': { file: 'assets/vieja-AAA.js' },
        });
        const actuales = archivosDeManifiesto({
            'resources/js/Pages/Nueva.jsx': { file: 'assets/nueva-BBB.js', css: ['assets/app-CCC.css'] },
        });
        const presentes = ['assets/nueva-BBB.js', 'assets/app-CCC.css', 'assets/huerfano-ZZZ.js'];

        const plan = planRetencion({ anteriores, actuales, presentes });

        expect(plan.restaurar).toEqual(['assets/vieja-AAA.js']);
        expect(plan.borrar).toEqual(['assets/huerfano-ZZZ.js']);
    });
});
