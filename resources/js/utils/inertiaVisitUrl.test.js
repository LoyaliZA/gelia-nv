import { describe, expect, it } from 'vitest';
import { inertiaVisitUrl } from './inertiaVisitUrl';

describe('inertiaVisitUrl', () => {
    it('convierte absolutas http a path relativo (evita mixed content)', () => {
        expect(inertiaVisitUrl('http://gelianv.neobash.site/tiendanube?page=2')).toBe(
            '/tiendanube?page=2',
        );
    });

    it('conserva path relativo y search', () => {
        expect(inertiaVisitUrl('/tiendanube?page=3&search=foo')).toBe('/tiendanube?page=3&search=foo');
    });

    it('devuelve null si no hay url', () => {
        expect(inertiaVisitUrl(null)).toBeNull();
        expect(inertiaVisitUrl('')).toBeNull();
    });
});
