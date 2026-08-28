export const MAX_VOUCHERS = 5;
export const MAX_PDFS_EMITIDOS = 5;
export const MAX_KB_POR_ARCHIVO = 5120;
export const MAX_BYTES_POR_ARCHIVO = MAX_KB_POR_ARCHIVO * 1024;

export function archivoExcedeLimite(file) {
    return file && file.size > MAX_BYTES_POR_ARCHIVO;
}

export function mensajeLimiteArchivo() {
    return 'Cada archivo debe pesar máximo 5 MB.';
}
