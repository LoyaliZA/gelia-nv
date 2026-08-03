/**
 * Utilidades canvas para recortar / reescalar imágenes antes de subir a Tiendanube.
 */

/**
 * @param {HTMLImageElement|HTMLCanvasElement} source
 * @param {{ x: number, y: number, w: number, h: number }} crop  coords en píxeles de la imagen natural
 * @param {{ outW?: number, outH?: number, mime?: string, quality?: number }} opts
 * @returns {Promise<Blob>}
 */
export function cropAndResizeToBlob(source, crop, opts = {}) {
    const mime = opts.mime || 'image/webp';
    const quality = opts.quality ?? 0.85;
    const outW = opts.outW || Math.max(1, Math.round(crop.w));
    const outH = opts.outH || Math.max(1, Math.round(crop.h));

    const canvas = document.createElement('canvas');
    canvas.width = outW;
    canvas.height = outH;
    const ctx = canvas.getContext('2d');
    if (!ctx) {
        return Promise.reject(new Error('Canvas no disponible'));
    }

    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(
        source,
        crop.x,
        crop.y,
        crop.w,
        crop.h,
        0,
        0,
        outW,
        outH
    );

    return new Promise((resolve, reject) => {
        const tryMime = (m) => {
            canvas.toBlob(
                (blob) => {
                    if (blob && blob.size > 0) {
                        resolve(blob);
                    } else if (m === 'image/webp') {
                        tryMime('image/jpeg');
                    } else if (m === 'image/jpeg') {
                        tryMime('image/png');
                    } else {
                        reject(new Error('No se pudo exportar la imagen'));
                    }
                },
                m,
                quality
            );
        };
        tryMime(mime);
    });
}

/**
 * Recorte centrado cuadrado del lado mínimo, salida outSide×outSide.
 */
export function squareCropNatural(naturalW, naturalH) {
    const side = Math.min(naturalW, naturalH);
    return {
        x: Math.round((naturalW - side) / 2),
        y: Math.round((naturalH - side) / 2),
        w: side,
        h: side,
    };
}

/**
 * Fit: todo el frame, salida con lado mayor = maxSide (proporcional).
 */
export function fitOutputSize(naturalW, naturalH, maxSide) {
    const ratio = Math.min(maxSide / naturalW, maxSide / naturalH, 1);
    return {
        outW: Math.max(1, Math.round(naturalW * ratio)),
        outH: Math.max(1, Math.round(naturalH * ratio)),
    };
}

export async function blobToFile(blob, baseName = 'imagen') {
    const ext = blob.type === 'image/png' ? 'png' : blob.type === 'image/jpeg' ? 'jpg' : 'webp';
    const name = baseName.replace(/\.[^.]+$/, '') || 'imagen';
    return new File([blob], `${name}.${ext}`, { type: blob.type || 'image/webp' });
}
