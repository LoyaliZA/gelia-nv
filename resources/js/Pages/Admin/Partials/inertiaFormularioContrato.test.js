import { readFileSync } from 'node:fs';
import { expect, it } from 'vitest';

it('el contrato de errores sigue siendo el de Inertia 3.0.3 instalado', () => {
    const version = JSON.parse(readFileSync('node_modules/@inertiajs/core/package.json', 'utf8')).version;
    const nucleo = readFileSync('node_modules/@inertiajs/core/dist/index.js', 'utf8');
    const react = readFileSync('node_modules/@inertiajs/react/dist/index.js', 'utf8');

    expect(version).toBe('3.0.3');
    expect(nucleo).toContain('onHttpException(response) === false');
    expect(nucleo).toContain('onNetworkError(error) === false');
    expect(nucleo).toContain('onError(scopedErrors)');
    expect(react).toContain('setData((data2) => set(cloneDeep(data2), keyOrData, maybeValue))');
});
