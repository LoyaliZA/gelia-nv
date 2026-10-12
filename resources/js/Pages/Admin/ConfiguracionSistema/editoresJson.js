export function parseObjeto(valor) {
    try {
        const raw = typeof valor === 'string' ? JSON.parse(valor || '{}') : (valor ?? {});
        if (!raw || typeof raw !== 'object' || Array.isArray(raw)) {
            return {};
        }
        return raw;
    } catch {
        return {};
    }
}

export function parseLista(valor) {
    try {
        const raw = typeof valor === 'string' ? JSON.parse(valor || '[]') : (valor ?? []);
        return Array.isArray(raw) ? raw : [];
    } catch {
        return [];
    }
}

export function canonJson(valor) {
    const texto = String(valor ?? '').trim();
    if (!texto) {
        return '';
    }
    try {
        return JSON.stringify(JSON.parse(texto));
    } catch {
        return texto;
    }
}

export function leerRuta(objeto, ruta) {
    return String(ruta).split('.').reduce((cursor, clave) => (
        cursor && typeof cursor === 'object' ? cursor[clave] : undefined
    ), objeto);
}

export function escribirRuta(objeto, ruta, valor) {
    const copia = structuredClone(objeto);
    const partes = String(ruta).split('.');
    let cursor = copia;
    partes.forEach((clave, indice) => {
        if (indice === partes.length - 1) {
            cursor[clave] = valor;
            return;
        }
        if (!cursor[clave] || typeof cursor[clave] !== 'object' || Array.isArray(cursor[clave])) {
            cursor[clave] = {};
        }
        cursor = cursor[clave];
    });
    return copia;
}

export function listaIds(texto) {
    return String(texto || '')
        .split(/[^0-9]+/)
        .map((n) => parseInt(n, 10))
        .filter((n) => Number.isFinite(n) && n > 0);
}

export function idsATexto(valor) {
    return (Array.isArray(valor) ? valor : [])
        .map((n) => parseInt(n, 10))
        .filter((n) => Number.isFinite(n) && n > 0)
        .join(', ');
}

export function filasMapa(mapa) {
    if (!mapa || typeof mapa !== 'object' || Array.isArray(mapa)) {
        return [];
    }
    return Object.entries(mapa).map(([clave, valor]) => ({
        clave,
        valor: typeof valor === 'object' && valor !== null ? JSON.stringify(valor) : String(valor ?? ''),
    }));
}

export function mapaDesdeFilas(filas, modo) {
    const mapa = {};
    filas.forEach((fila) => {
        const clave = String(fila.clave || '').trim();
        if (!clave) {
            return;
        }
        mapa[clave] = textoAValor(fila.valor, modo);
    });
    return mapa;
}

function textoAValor(texto, modo) {
    const limpio = String(texto ?? '').trim();
    if (modo === 'numero') {
        const n = Number(limpio);
        return Number.isFinite(n) ? n : 0;
    }
    if ((limpio.startsWith('{') && limpio.endsWith('}')) || (limpio.startsWith('[') && limpio.endsWith(']'))) {
        try {
            return JSON.parse(limpio);
        } catch {
            return limpio;
        }
    }
    return limpio;
}
