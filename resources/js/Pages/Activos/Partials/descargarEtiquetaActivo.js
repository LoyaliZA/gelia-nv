function cargarImagen(src) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = src;
    });
}

function dibujarTextoMultilinea(ctx, texto, x, y, maxAncho, altoLinea) {
    const palabras = String(texto || '').split(/\s+/).filter(Boolean);
    if (!palabras.length) return y;

    let linea = '';
    let cursorY = y;

    palabras.forEach((palabra) => {
        const prueba = linea ? `${linea} ${palabra}` : palabra;
        if (ctx.measureText(prueba).width > maxAncho && linea) {
            ctx.fillText(linea, x, cursorY);
            linea = palabra;
            cursorY += altoLinea;
        } else {
            linea = prueba;
        }
    });

    if (linea) {
        ctx.fillText(linea, x, cursorY);
    }

    return cursorY;
}

export async function descargarEtiquetaActivo({ activo, qrPngSrc, tipoNombre }) {
    const ancho = 800;
    const alto = 400;
    const padding = 40;
    const canvas = document.createElement('canvas');
    canvas.width = ancho;
    canvas.height = alto;

    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#ffffff';
    ctx.fillRect(0, 0, ancho, alto);

    ctx.strokeStyle = '#d4d4d4';
    ctx.lineWidth = 2;
    ctx.strokeRect(1, 1, ancho - 2, alto - 2);

    const qrTam = alto - padding * 2;
    const qrImg = await cargarImagen(qrPngSrc);
    ctx.drawImage(qrImg, padding, padding, qrTam, qrTam);

    const textoX = padding + qrTam + 32;
    const maxTexto = ancho - textoX - padding;

    ctx.fillStyle = '#000000';
    ctx.font = 'bold 36px system-ui, -apple-system, sans-serif';
    ctx.fillText(activo.folio || '', textoX, padding + 40);

    ctx.font = '26px system-ui, -apple-system, sans-serif';
    const finNombre = dibujarTextoMultilinea(ctx, activo.nombre || '', textoX, padding + 88, maxTexto, 32);

    ctx.fillStyle = '#525252';
    ctx.font = '20px system-ui, -apple-system, sans-serif';
    ctx.fillText(tipoNombre || activo.tipo?.nombre || 'Activo', textoX, finNombre + 40);

    ctx.fillStyle = '#737373';
    ctx.font = '16px system-ui, -apple-system, sans-serif';
    ctx.fillText('Escanea para consultar', textoX, alto - padding);

    await new Promise((resolve, reject) => {
        canvas.toBlob((blob) => {
            if (!blob) {
                reject(new Error('No se pudo generar la etiqueta.'));
                return;
            }
            const url = URL.createObjectURL(blob);
            const enlace = document.createElement('a');
            enlace.href = url;
            enlace.download = `etiqueta-${activo.folio || activo.id}.png`;
            enlace.click();
            URL.revokeObjectURL(url);
            resolve();
        }, 'image/png');
    });
}
