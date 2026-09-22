import { describe, expect, it, vi, beforeEach, afterEach } from 'vitest';
import { MEDIA_UPLOADER_LIMITS, claveArchivoCarga } from './mediaUploader';

describe('mediaUploader', () => {
    beforeEach(() => {
        vi.stubGlobal('indexedDB', undefined);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('expone cola de 4 y 3 reintentos', () => {
        expect(MEDIA_UPLOADER_LIMITS.MAX_CONCURRENT).toBe(4);
        expect(MEDIA_UPLOADER_LIMITS.MAX_REINTENTOS).toBe(3);
        expect(MEDIA_UPLOADER_LIMITS.BACKOFF).toEqual([1000, 3000, 8000]);
    });

    it('arma clave estable por nombre, tamaño y fecha', () => {
        expect(claveArchivoCarga({ name: 'a.mp4', size: 10, lastModified: 5 })).toBe('a.mp4:10:5');
    });
});
