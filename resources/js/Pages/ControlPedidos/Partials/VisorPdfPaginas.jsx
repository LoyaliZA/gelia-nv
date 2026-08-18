import React, { useEffect, useRef, useState } from 'react';
import { Loader2, AlertTriangle } from 'lucide-react';
import { loadPdfJs } from '../../../utils/loadPreviewLibs';

const MAX_PAGINAS = 20;

/**
 * Pinta el PDF como canvases (zoom/scroll nativo). iframe no sirve en iOS/Android.
 */
export default function VisorPdfPaginas({ url, titulo = 'PDF', className = '', maxHeight = 'min(70dvh, calc(100dvh - 14rem))' }) {
    const hostRef = useRef(null);
    const [estado, setEstado] = useState({ cargando: true, error: null, paginas: 0 });

    useEffect(() => {
        if (!url) return undefined;
        const host = hostRef.current;
        if (!host) return undefined;
        let cancelado = false;

        const pintar = async () => {
            setEstado({ cargando: true, error: null, paginas: 0 });
            host.replaceChildren();
            try {
                const pdfjs = await loadPdfJs();
                const pdf = await pdfjs.getDocument({ url, withCredentials: true }).promise;
                if (cancelado) return;
                const total = Math.min(pdf.numPages, MAX_PAGINAS);
                const ancho = Math.max(
                    host.clientWidth,
                    host.parentElement?.clientWidth || 0,
                    Math.min(window.innerWidth - 32, 720),
                    240,
                );
                const dpr = Math.min(window.devicePixelRatio || 1, 2);

                for (let n = 1; n <= total; n += 1) {
                    const page = await pdf.getPage(n);
                    if (cancelado) return;
                    const base = page.getViewport({ scale: 1 });
                    const scale = (ancho / base.width) * dpr;
                    const viewport = page.getViewport({ scale });
                    const canvas = document.createElement('canvas');
                    canvas.width = viewport.width;
                    canvas.height = viewport.height;
                    canvas.style.width = '100%';
                    canvas.style.height = 'auto';
                    canvas.style.display = 'block';
                    canvas.setAttribute('aria-label', `${titulo} página ${n} de ${total}`);
                    host.appendChild(canvas);
                    await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
                }

                setEstado({ cargando: false, error: null, paginas: total });
            } catch (e) {
                if (cancelado) return;
                setEstado({ cargando: false, error: e?.message || 'No se pudo mostrar el PDF.', paginas: 0 });
            }
        };

        pintar();
        return () => {
            cancelado = true;
            host.replaceChildren();
        };
    }, [url, titulo]);

    return (
        <div className={`relative w-full ${className}`}>
            {estado.cargando && (
                <div className="flex flex-col items-center justify-center gap-2 py-10">
                    <Loader2 className="w-7 h-7 animate-spin opacity-60" />
                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted m-0">Cargando PDF…</p>
                </div>
            )}
            {estado.error && (
                <div className="flex flex-col items-center justify-center gap-2 p-4 text-center">
                    <AlertTriangle className="w-7 h-7 text-amber-500" />
                    <p className="text-xs font-bold theme-text-muted m-0">{estado.error}</p>
                </div>
            )}
            <div
                ref={hostRef}
                className="overflow-auto bg-white"
                style={{
                    maxHeight: estado.cargando || estado.error ? 0 : maxHeight,
                    minHeight: estado.cargando ? 0 : undefined,
                    WebkitOverflowScrolling: 'touch',
                    touchAction: 'pan-x pan-y pinch-zoom',
                    visibility: estado.cargando || estado.error ? 'hidden' : 'visible',
                }}
            />
            {estado.paginas > 1 && (
                <p className="text-[10px] font-bold theme-text-muted text-center m-0 py-1">{estado.paginas} páginas — deslice para ver</p>
            )}
        </div>
    );
}
