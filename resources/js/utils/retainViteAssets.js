export function archivosDeManifiesto(manifiesto) {
    const archivos = new Set();
    if (!manifiesto || typeof manifiesto !== 'object') return archivos;

    for (const entrada of Object.values(manifiesto)) {
        if (typeof entrada?.file === 'string' && entrada.file !== '') {
            archivos.add(entrada.file);
        }
        if (Array.isArray(entrada?.css)) {
            for (const css of entrada.css) {
                if (typeof css === 'string' && css !== '') archivos.add(css);
            }
        }
    }

    return archivos;
}

export function planRetencion({ anteriores, actuales, presentes }) {
    const conservar = new Set([...anteriores, ...actuales]);
    const yaPresentes = new Set(presentes);
    const restaurar = [...anteriores].filter((archivo) => !yaPresentes.has(archivo));
    const borrar = [...yaPresentes].filter((archivo) => !conservar.has(archivo));

    return { restaurar, borrar };
}
