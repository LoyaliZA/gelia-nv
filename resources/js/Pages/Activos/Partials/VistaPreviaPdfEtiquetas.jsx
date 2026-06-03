import React from 'react';
import { Loader2 } from 'lucide-react';

import { TAMANOS_HOJA } from './etiquetaLayout';

export default function VistaPreviaPdfEtiquetas({ pdfUrl, cargando, error, grid, conteo, excedeLimite, tamanosHoja = [] }) {
    if (cargando) {
        return (
            <div className="flex flex-col items-center justify-center gap-3 rounded-2xl border theme-border p-10 min-h-[320px]">
                <Loader2 className="w-8 h-8 animate-spin" style={{ color: 'var(--color-primario)' }} />
                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                    Generando vista previa de impresión…
                </p>
            </div>
        );
    }

    if (error) {
        return (
            <div className="rounded-2xl border border-red-500/30 bg-red-500/5 p-6 min-h-[200px]">
                <p className="text-xs font-bold text-red-600 dark:text-red-400 m-0">{error}</p>
            </div>
        );
    }

    if (conteo === 0) {
        return (
            <div className="rounded-2xl border border-dashed theme-border p-8 text-center min-h-[200px] flex items-center justify-center">
                <p className="text-xs font-bold theme-text-muted m-0">
                    Ajusta los filtros para ver la hoja PDF de impresión.
                </p>
            </div>
        );
    }

    if (excedeLimite) {
        return (
            <div className="rounded-2xl border border-red-500/30 bg-red-500/5 p-6 min-h-[200px]">
                <p className="text-xs font-bold text-red-600 dark:text-red-400 m-0">
                    Demasiadas etiquetas para la vista previa. Acota los filtros.
                </p>
            </div>
        );
    }

    if (!pdfUrl) {
        return (
            <div className="rounded-2xl border border-dashed theme-border p-8 text-center min-h-[200px] flex items-center justify-center">
                <p className="text-xs font-bold theme-text-muted m-0">
                    Indica medidas válidas (entre {4} y {20} cm de ancho) para generar la vista previa.
                </p>
            </div>
        );
    }

    const hojaLabel = tamanosHoja.find((t) => t.value === grid.tamanio_hoja)?.label
        || TAMANOS_HOJA[grid.tamanio_hoja]?.label
        || 'A4';
    const orientacionLabel = grid.orientacion_hoja === 'portrait' ? 'vertical' : 'horizontal';
    const etiquetaLabel = grid.orientacion_etiqueta === 'vertical' ? 'etiquetas rotadas' : 'etiquetas horizontales';

    return (
        <div className="space-y-3">
            <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">
                {hojaLabel} {orientacionLabel} · {etiquetaLabel} · {grid.columnas}×{grid.filas} = {grid.por_pagina} por página
                {grid.gap_mm > 0 ? ` · sep. ${grid.gap_mm} mm` : ' · sin separación'}
            </p>
            <div className="rounded-2xl border theme-border overflow-hidden bg-white">
                <iframe
                    src={pdfUrl}
                    title="Vista previa de impresión — etiquetas"
                    className="w-full border-0"
                    style={{ height: 'min(70vh, 640px)' }}
                />
            </div>
        </div>
    );
}
