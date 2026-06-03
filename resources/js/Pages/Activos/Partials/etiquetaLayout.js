export const ETIQUETA_RATIO = 2;
export const MM_PER_CM = 10;

export const DEFAULT_ANCHO_MM = 100;
export const DEFAULT_ANCHO_CM = DEFAULT_ANCHO_MM / MM_PER_CM;
export const MIN_ANCHO_MM = 40;
export const MAX_ANCHO_MM = 200;
export const MIN_ANCHO_CM = MIN_ANCHO_MM / MM_PER_CM;
export const MAX_ANCHO_CM = MAX_ANCHO_MM / MM_PER_CM;

export const DEFAULT_GAP_MM = 0;
export const MAX_GAP_MM = 10;
export const DEFAULT_GAP_CM = DEFAULT_GAP_MM / MM_PER_CM;
export const MAX_GAP_CM = MAX_GAP_MM / MM_PER_CM;

export const MARGEN_MM = 3;

export function mmToCm(mm) {
    return Number(mm) / MM_PER_CM;
}

export function cmToMm(cm) {
    return Number(cm) * MM_PER_CM;
}

export function formatCm(cm) {
    return String(round1(cm));
}

export function parseCmInput(str) {
    if (str === '' || str === '-' || str === '.' || str === ',') {
        return null;
    }

    const normalized = String(str).trim().replace(',', '.');
    const n = parseFloat(normalized);

    return Number.isFinite(n) ? n : null;
}

export function clampGapMm(gapMm) {
    const n = Number(gapMm);
    if (!Number.isFinite(n)) return DEFAULT_GAP_MM;
    return Math.min(MAX_GAP_MM, Math.max(0, round1(n)));
}

export function clampGapCm(gapCm) {
    return mmToCm(clampGapMm(cmToMm(gapCm)));
}

export function calcularAltoDesdeAncho(anchoMm, proporcion = '2:1') {
    const ancho = clampAncho(Number(anchoMm));
    if (proporcion === '1:1') return round1(ancho);
    return round1(ancho / ETIQUETA_RATIO);
}

export function calcularAnchoDesdeAlto(altoMm, proporcion = '2:1') {
    const alto = Number(altoMm);
    if (!Number.isFinite(alto) || alto <= 0) return DEFAULT_ANCHO_MM;
    if (proporcion === '1:1') return clampAncho(alto);
    return clampAncho(alto * ETIQUETA_RATIO);
}

export function clampAncho(anchoMm) {
    const n = Number(anchoMm);
    if (!Number.isFinite(n)) return DEFAULT_ANCHO_MM;
    return Math.min(MAX_ANCHO_MM, Math.max(MIN_ANCHO_MM, n));
}

export function clampAnchoCm(anchoCm) {
    const n = Number(anchoCm);
    if (!Number.isFinite(n)) return DEFAULT_ANCHO_CM;
    return Math.min(MAX_ANCHO_CM, Math.max(MIN_ANCHO_CM, n));
}

export function round1(value) {
    return Math.round(Number(value) * 10) / 10;
}

export function dimensionesDesdeCm(anchoCm, proporcion = '2:1') {
    const cm = clampAnchoCm(anchoCm);
    const anchoMm = clampAncho(cmToMm(cm));
    const altoMm = calcularAltoDesdeAncho(anchoMm, proporcion);

    return {
        anchoCm: mmToCm(anchoMm),
        altoCm: mmToCm(altoMm),
        anchoMm,
        altoMm,
        proporcion: proporcion === '1:1' ? '1:1' : '2:1',
    };
}

export const DEFAULT_TAMANIO_HOJA = 'a4';

export const TAMANOS_HOJA = {
    a4: { ancho_mm: 210, alto_mm: 297, label: 'A4' },
    carta: { ancho_mm: 215.9, alto_mm: 279.4, label: 'Carta (Letter)' },
    oficio: { ancho_mm: 215.9, alto_mm: 355.6, label: 'Oficio (Legal)' },
};

export function dimensionesPaginaMm(tamanioHoja = DEFAULT_TAMANIO_HOJA, orientacionHoja = 'landscape') {
    const base = TAMANOS_HOJA[tamanioHoja] || TAMANOS_HOJA.a4;
    const portrait = orientacionHoja === 'portrait';

    return {
        page_ancho_mm: portrait ? base.ancho_mm : base.alto_mm,
        page_alto_mm: portrait ? base.alto_mm : base.ancho_mm,
        tamanio_hoja: TAMANOS_HOJA[tamanioHoja] ? tamanioHoja : DEFAULT_TAMANIO_HOJA,
    };
}

export function calcularGridPorHoja(anchoMm, altoMm, opciones = {}) {
    const orientacionHoja = opciones.orientacion_hoja === 'portrait' ? 'portrait' : 'landscape';
    const orientacionEtiqueta = opciones.orientacion_etiqueta === 'vertical' ? 'vertical' : 'horizontal';
    const tamanioHoja = opciones.tamanio_hoja || DEFAULT_TAMANIO_HOJA;
    const gap = clampGapMm(opciones.gap_mm ?? DEFAULT_GAP_MM);

    const pagina = dimensionesPaginaMm(tamanioHoja, orientacionHoja);
    const pageAncho = pagina.page_ancho_mm;
    const pageAlto = pagina.page_alto_mm;

    let celdaAncho = anchoMm;
    let celdaAlto = altoMm;
    if (orientacionEtiqueta === 'vertical') {
        celdaAncho = altoMm;
        celdaAlto = anchoMm;
    }

    const utilAncho = pageAncho - MARGEN_MM * 2;
    const utilAlto = pageAlto - MARGEN_MM * 2;

    const columnas = calcularCeldasPorEje(utilAncho, celdaAncho, gap);
    const filas = calcularCeldasPorEje(utilAlto, celdaAlto, gap);

    return {
        columnas,
        filas,
        por_pagina: columnas * filas,
        gap_mm: gap,
        orientacion_hoja: orientacionHoja,
        orientacion_etiqueta: orientacionEtiqueta,
        tamanio_hoja: pagina.tamanio_hoja,
        page_ancho_mm: pageAncho,
        page_alto_mm: pageAlto,
    };
}

function calcularCeldasPorEje(utilMm, celdaMm, gapMm) {
    if (celdaMm <= 0) return 1;
    if (gapMm <= 0) return Math.max(1, Math.floor(utilMm / celdaMm));
    return Math.max(1, Math.floor((utilMm + gapMm) / (celdaMm + gapMm)));
}

export function paramsEtiquetasPdf(filtros, anchoMm, altoMm, layoutOpciones = {}) {
    const params = new URLSearchParams();
    Object.entries(filtros || {}).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') return;
        if (key === 'responsable_user_ids' && Array.isArray(value)) {
            value.forEach((id) => params.append('responsable_user_ids[]', String(id)));
            return;
        }
        params.set(key, String(value));
    });
    params.set('ancho_mm', String(anchoMm));
    params.set('alto_mm', String(altoMm));
    params.set('gap_mm', String(clampGapMm(layoutOpciones.gap_mm ?? DEFAULT_GAP_MM)));
    params.set('proporcion', layoutOpciones.proporcion === '1:1' ? '1:1' : '2:1');
    params.set('tamanio_hoja', layoutOpciones.tamanio_hoja || DEFAULT_TAMANIO_HOJA);
    params.set('orientacion_hoja', layoutOpciones.orientacion_hoja === 'portrait' ? 'portrait' : 'landscape');
    params.set('orientacion_etiqueta', layoutOpciones.orientacion_etiqueta === 'vertical' ? 'vertical' : 'horizontal');
    return params;
}

export function layoutOpcionesDesdeProps(layout = {}) {
    return {
        proporcion: layout.proporcion === '1:1' ? '1:1' : '2:1',
        tamanio_hoja: TAMANOS_HOJA[layout.tamanio_hoja] ? layout.tamanio_hoja : DEFAULT_TAMANIO_HOJA,
        orientacion_hoja: layout.orientacion_hoja === 'portrait' ? 'portrait' : 'landscape',
        orientacion_etiqueta: layout.orientacion_etiqueta === 'vertical' ? 'vertical' : 'horizontal',
        gap_mm: clampGapMm(layout.gap_mm ?? DEFAULT_GAP_MM),
    };
}
