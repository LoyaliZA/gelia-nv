/**
 * Espejo de App\Services\Tiendanube\TiendanubeImageSkuParser
 * @returns {{ sku: string, position: number, extension: string } | null}
 */
export function parseTiendanubeImageFilename(filename) {
    const base = String(filename || '').replace(/\\/g, '/').split('/').pop() || '';
    if (!base || base.startsWith('.')) return null;

    const dot = base.lastIndexOf('.');
    if (dot <= 0) return null;

    const ext = base.slice(dot + 1).toLowerCase();
    const allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!allowed.includes(ext)) return null;

    const name = base.slice(0, dot);
    if (!name) return null;

    const m = name.match(/^(.+?)(?:_(\d+))?$/);
    if (!m) return null;

    const sku = (m[1] || '').trim();
    if (!sku) return null;

    const position = m[2] !== undefined && m[2] !== '' ? Math.max(1, parseInt(m[2], 10)) : 1;

    return { sku, position, extension: ext };
}

export function formatBytes(bytes) {
    const n = Number(bytes) || 0;
    if (n < 1024) return `${n} B`;
    if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} KB`;
    return `${(n / (1024 * 1024)).toFixed(2)} MB`;
}

export function readImageDimensions(file) {
    return new Promise((resolve) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
            const width = img.naturalWidth || 0;
            const height = img.naturalHeight || 0;
            URL.revokeObjectURL(url);
            resolve({ width, height });
        };
        img.onerror = () => {
            URL.revokeObjectURL(url);
            resolve({ width: 0, height: 0 });
        };
        img.src = url;
    });
}
