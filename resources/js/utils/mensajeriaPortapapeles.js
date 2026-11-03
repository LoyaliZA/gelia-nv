import { urlAdjuntoMensajeria } from '@/utils/adjuntoPreview';
import { emitirToastGelia } from '@/utils/geliaToast';

export function archivosImagenDesdePortapapeles(clipboardData) {
    const items = clipboardData?.items;
    const archivos = [];
    if (items?.length) {
        for (let i = 0; i < items.length; i += 1) {
            const item = items[i];
            if (!item?.type || item.type.indexOf('image') === -1) continue;
            const file = typeof item.getAsFile === 'function' ? item.getAsFile() : null;
            if (file) archivos.push(file);
        }
    }

    if (!archivos.length && clipboardData?.files?.length) {
        Array.from(clipboardData.files).forEach((file) => {
            if (file?.type?.indexOf('image') !== -1) archivos.push(file);
        });
    }

    return archivos;
}

function blobAPng(blob) {
    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(blob);
        const imagen = new Image();
        imagen.onload = () => {
            const lienzo = document.createElement('canvas');
            lienzo.width = imagen.naturalWidth || imagen.width;
            lienzo.height = imagen.naturalHeight || imagen.height;
            const ctx = lienzo.getContext('2d');
            if (!ctx) {
                URL.revokeObjectURL(url);
                reject(new Error('canvas'));
                return;
            }
            ctx.drawImage(imagen, 0, 0);
            lienzo.toBlob((png) => {
                URL.revokeObjectURL(url);
                if (png) resolve(png);
                else reject(new Error('png'));
            }, 'image/png');
        };
        imagen.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('img'));
        };
        imagen.src = url;
    });
}

export async function copiarImagenMensaje(adjunto) {
    if (!adjunto?.id || typeof navigator === 'undefined' || !navigator.clipboard?.write) {
        emitirToastGelia('Este navegador no permite copiar la imagen.', 'error');
        return false;
    }

    try {
        const respuesta = await fetch(urlAdjuntoMensajeria(adjunto.id, { inline: true }), {
            credentials: 'same-origin',
        });
        if (!respuesta.ok) throw new Error('respuesta');

        const blob = await respuesta.blob();
        const png = blob.type === 'image/png' ? blob : await blobAPng(blob);
        await navigator.clipboard.write([
            new ClipboardItem({ 'image/png': png }),
        ]);
        emitirToastGelia('Imagen copiada.', 'success');
        return true;
    } catch {
        emitirToastGelia('No se pudo copiar la imagen. Revisa el permiso del portapapeles.', 'error');
        return false;
    }
}
