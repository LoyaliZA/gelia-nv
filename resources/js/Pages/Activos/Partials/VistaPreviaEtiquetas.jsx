import React from 'react';
import { calcularGridPorHoja } from './etiquetaLayout';

function EtiquetaCard({ activo, anchoMm, altoMm, escala = 1 }) {
    const anchoPx = anchoMm * 3.78 * escala;
    const altoPx = altoMm * 3.78 * escala;

    return (
        <div
            className="bg-white border border-neutral-300 text-black overflow-hidden shrink-0"
            style={{ width: anchoPx, height: altoPx }}
        >
            <div className="flex h-full">
                <div className="w-[42%] flex items-center justify-center p-[4%]">
                    {activo?.qr_url ? (
                        <img src={activo.qr_url} alt="" className="max-w-[88%] max-h-[88%] object-contain" />
                    ) : (
                        <div className="w-[70%] aspect-square bg-neutral-100 border border-dashed border-neutral-300 rounded" />
                    )}
                </div>
                <div className="w-[58%] flex flex-col justify-center pr-[4%] py-[4%] min-w-0">
                    <p className="font-bold leading-tight m-0 truncate" style={{ fontSize: `${altoMm * 0.11}mm` }}>
                        {activo?.folio || 'FOLIO'}
                    </p>
                    <p className="leading-snug m-0 mt-1 line-clamp-3" style={{ fontSize: `${altoMm * 0.08}mm` }}>
                        {activo?.nombre || 'Nombre del activo'}
                    </p>
                    <p className="text-neutral-600 m-0 mt-1 truncate" style={{ fontSize: `${altoMm * 0.06}mm` }}>
                        {activo?.tipo || 'Tipo'}
                    </p>
                    <p className="text-neutral-500 m-0 mt-auto" style={{ fontSize: `${altoMm * 0.05}mm` }}>
                        Escanea para consultar
                    </p>
                </div>
            </div>
        </div>
    );
}

export default function VistaPreviaEtiquetas({ muestra = [], anchoMm, altoMm }) {
    const grid = calcularGridPorHoja(anchoMm, altoMm);
    const ejemplo = muestra[0] || {
        folio: 'TEC-TI-2026-0001',
        nombre: 'Ejemplo de equipo',
        tipo: 'Equipo TI',
    };
    const miniGrid = Math.min(grid.columnas, 2);
    const miniFilas = Math.min(grid.filas, 2);

    return (
        <div className="space-y-4">
            <div className="rounded-2xl border theme-border p-4 md:p-6 bg-black/[0.03] dark:bg-white/[0.03]">
                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-3">
                    Vista previa de impresión
                </p>
                <div className="flex justify-center overflow-x-auto pb-2">
                    <EtiquetaCard activo={ejemplo} anchoMm={anchoMm} altoMm={altoMm} escala={1.1} />
                </div>
            </div>

            <div className="rounded-2xl border theme-border p-4 md:p-6 bg-black/[0.03] dark:bg-white/[0.03]">
                <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-3">
                    Distribución en hoja A4 horizontal ({grid.columnas}×{grid.filas} = {grid.por_pagina} por página)
                </p>
                <div
                    className="mx-auto bg-white border border-neutral-300 p-2 inline-grid gap-1"
                    style={{
                        gridTemplateColumns: `repeat(${miniGrid}, minmax(0, 1fr))`,
                        transform: 'scale(0.55)',
                        transformOrigin: 'top left',
                        width: `${(anchoMm * 3.78 * miniGrid) + 16}px`,
                    }}
                >
                    {Array.from({ length: miniGrid * miniFilas }).map((_, i) => (
                        <EtiquetaCard
                            key={i}
                            activo={muestra[i] || ejemplo}
                            anchoMm={anchoMm}
                            altoMm={altoMm}
                            escala={0.45}
                        />
                    ))}
                </div>
            </div>
        </div>
    );
}
