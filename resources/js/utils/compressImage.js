/** Tipos de imagen aceptados antes de comprimir (origen). */
export const IMAGE_SOURCE_TYPES = [
    'image/jpeg',
    'image/png',
    'image/jpg',
    'image/webp',
    'image/gif',
    'image/bmp',
];

/** Límite de archivo original antes de comprimir (~20 MB). */
export const MAX_SOURCE_IMAGE_BYTES = 20 * 1024 * 1024;

function fitDimensions(width, height, maxDimension) {
    if (width <= maxDimension && height <= maxDimension) {
        return { width, height };
    }
    const ratio = Math.min(maxDimension / width, maxDimension / height);
    return {
        width: Math.max(1, Math.round(width * ratio)),
        height: Math.max(1, Math.round(height * ratio)),
    };
}

function loadImageElement(file) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
            URL.revokeObjectURL(url);
            resolve(img);
        };
        img.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('IMAGE_LOAD_FAILED'));
        };
        img.src = url;
    });
}

function canvasToWebpBlob(canvas, quality) {
    return new Promise((resolve, reject) => {
        canvas.toBlob(
            (blob) => (blob ? resolve(blob) : reject(new Error('CANVAS_BLOB_FAILED'))),
            'image/webp',
            quality
        );
    });
}

/**
 * Comprime y convierte una imagen a WebP usando canvas.
 * Reduce calidad iterativamente hasta cumplir maxBytes.
 */
export async function compressImageToWebp(file, {
    maxDimension = 1920,
    quality = 0.82,
    maxBytes = 2048 * 1024,
    minQuality = 0.45,
} = {}) {
    const img = await loadImageElement(file);
    const { width, height } = fitDimensions(img.naturalWidth || img.width, img.naturalHeight || img.height, maxDimension);

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;

    const ctx = canvas.getContext('2d');
    if (!ctx) throw new Error('CANVAS_CONTEXT_FAILED');

    ctx.drawImage(img, 0, 0, width, height);

    let currentQuality = quality;
    let blob = await canvasToWebpBlob(canvas, currentQuality);

    while (blob.size > maxBytes && currentQuality > minQuality) {
        currentQuality = Math.max(minQuality, Number((currentQuality - 0.07).toFixed(2)));
        blob = await canvasToWebpBlob(canvas, currentQuality);
    }

    if (blob.size > maxBytes) {
        throw new Error('COMPRESS_TOO_LARGE');
    }

    const baseName = (file.name || 'imagen').replace(/\.[^.]+$/, '');
    return new File([blob], `${baseName}.webp`, {
        type: 'image/webp',
        lastModified: Date.now(),
    });
}

export function validateImageSource(file, label = 'Imagen') {
    if (!file) return 'No se seleccionó ningún archivo.';
    if (!IMAGE_SOURCE_TYPES.includes(file.type)) {
        return `${label}: formato no válido. Usa JPG, PNG, WEBP o GIF.`;
    }
    if (file.size > MAX_SOURCE_IMAGE_BYTES) {
        return `${label}: el archivo original excede ${Math.round(MAX_SOURCE_IMAGE_BYTES / (1024 * 1024))} MB.`;
    }
    return null;
}
