let mutacionesEnCurso = 0;

export function mutacionesRecargaEnCurso() {
    return mutacionesEnCurso;
}

export async function conBloqueoRecarga(trabajo) {
    mutacionesEnCurso += 1;
    try {
        return await trabajo();
    } finally {
        mutacionesEnCurso = Math.max(0, mutacionesEnCurso - 1);
    }
}
