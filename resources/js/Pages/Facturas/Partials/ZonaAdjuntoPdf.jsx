import React, { useCallback, useState } from 'react';
import { Upload, Trash2, FileText } from 'lucide-react';
import { MAX_PDFS_EMITIDOS, archivoExcedeLimite, mensajeLimiteArchivo } from './limitesAdjuntosFactura';

export default function ZonaAdjuntoPdf({
    archivos,
    onChange,
    error,
    maxTotal = MAX_PDFS_EMITIDOS,
}) {
    const [errorLocal, setErrorLocal] = useState('');

    const agregar = useCallback((files) => {
        const lista = Array.from(files || []);
        if (!lista.length) return;

        const actuales = [...archivos];
        const cupo = maxTotal - actuales.length;
        let rechazadoTam = false;

        for (const file of lista) {
            if (actuales.length >= maxTotal) break;
            if (file.type !== 'application/pdf' && !String(file.name || '').toLowerCase().endsWith('.pdf')) {
                continue;
            }
            if (archivoExcedeLimite(file)) {
                rechazadoTam = true;
                continue;
            }
            actuales.push(file);
        }

        if (rechazadoTam) setErrorLocal(mensajeLimiteArchivo());
        else setErrorLocal('');

        onChange(actuales);
    }, [archivos, maxTotal, onChange]);

    const quitar = (index) => {
        onChange(archivos.filter((_, i) => i !== index));
    };

    const mensajeError = error || errorLocal;

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between ml-1">
                <label className="text-[10px] font-black uppercase theme-text-muted tracking-widest">
                    PDF de factura emitida *
                </label>
                <span className="text-[9px] font-black theme-text-muted">{archivos.length}/{maxTotal}</span>
            </div>

            <div className={`border-2 border-dashed rounded-2xl p-4 space-y-2 ${mensajeError ? 'border-red-500' : 'theme-border'}`}>
                {archivos.map((f, i) => (
                    <div key={`pdf-${i}`} className="flex items-center gap-3 p-2 rounded-xl theme-element border theme-border">
                        <FileText className="w-8 h-8 shrink-0" style={{ color: 'var(--color-primario)' }} />
                        <span className="text-[10px] font-bold theme-text-main truncate flex-1">{f.name}</span>
                        <button type="button" onClick={() => quitar(i)} className="p-1 text-red-500 hover:bg-red-500/10 rounded-lg outline-none">
                            <Trash2 className="w-4 h-4" />
                        </button>
                    </div>
                ))}

                {archivos.length < maxTotal && (
                    <label className="flex flex-col items-center justify-center py-4 cursor-pointer rounded-xl border border-dashed theme-border hover:border-[var(--color-primario)] transition-colors">
                        <Upload className="w-6 h-6 mb-2 theme-text-muted" />
                        <span className="text-[9px] font-black uppercase tracking-widest theme-text-muted text-center px-2">
                            Agregar PDF · máx. 5 archivos, 5 MB c/u
                        </span>
                        <input
                            type="file"
                            className="hidden"
                            accept=".pdf,application/pdf"
                            multiple
                            onChange={(e) => { agregar(e.target.files); e.target.value = ''; }}
                        />
                    </label>
                )}
            </div>

            {mensajeError && <p className="text-xs text-red-500 font-bold m-0">{mensajeError}</p>}
        </div>
    );
}
