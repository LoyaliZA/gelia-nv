import React, { useEffect, useId, useState } from 'react';
import { Upload, Trash2, FileText } from 'lucide-react';
import EvidenciaSolicitud from '@/Components/Solicitudes/EvidenciaSolicitud';
import { MAX_PDFS_EMITIDOS, archivoExcedeLimite, mensajeLimiteArchivo } from './limitesAdjuntosFactura';

function ArchivoPdf({ file, disabled, onRemove }) {
    const [url, setUrl] = useState(null);
    useEffect(() => {
        const src = URL.createObjectURL(file);
        setUrl(src);
        return () => URL.revokeObjectURL(src);
    }, [file]);
    return (
        <div className="flex flex-wrap items-center gap-2 p-3 rounded-xl theme-element border theme-border">
            <FileText className="w-5 h-5 theme-text-primario shrink-0" aria-hidden="true" />
            <span className="text-sm theme-text-main break-words min-w-0 flex-1">{file.name}</span>
            {url && <EvidenciaSolicitud url={url} esPdf label="Revisar PDF" title={file.name} />}
            <button type="button" disabled={disabled} onClick={onRemove} className="gelia-workflow-close theme-text-peligro" aria-label={`Quitar ${file.name}`}><Trash2 className="w-4 h-4" aria-hidden="true" /></button>
        </div>
    );
}

export default function ZonaAdjuntoPdf({ archivos, onChange, error, maxTotal = MAX_PDFS_EMITIDOS, disabled = false }) {
    const [errorLocal, setErrorLocal] = useState('');
    const id = useId();
    const agregar = (files) => {
        if (disabled) return;
        const actuales = [...archivos];
        const problemas = new Set();
        for (const file of Array.from(files || [])) {
            if (file.type !== 'application/pdf' && !/\.pdf$/i.test(file.name)) { problemas.add('Solo se admiten archivos PDF.'); continue; }
            if (archivoExcedeLimite(file)) { problemas.add(mensajeLimiteArchivo()); continue; }
            if (actuales.length >= maxTotal) { problemas.add(`Puedes adjuntar hasta ${maxTotal} PDFs.`); continue; }
            if (actuales.some((f) => f.name === file.name && f.size === file.size && f.lastModified === file.lastModified)) { problemas.add(`${file.name} ya está adjunto.`); continue; }
            actuales.push(file);
        }
        setErrorLocal([...problemas].join(' '));
        onChange(actuales);
    };
    const mensajeError = error || errorLocal;
    return (
        <section className="space-y-3" aria-labelledby={`${id}-titulo`}>
            <div className="flex justify-between items-center gap-2">
                <h3 id={`${id}-titulo`} className="text-sm font-medium theme-text-main m-0">PDF de factura emitida (obligatorio)</h3>
                <span className="text-xs tabular-nums theme-text-muted">{archivos.length}/{maxTotal}</span>
            </div>
            <div className="space-y-2" onDragOver={(event) => event.preventDefault()} onDrop={(event) => { event.preventDefault(); agregar(event.dataTransfer.files); }}>
                {archivos.map((file, index) => <ArchivoPdf key={`${file.name}-${file.lastModified}-${index}`} file={file} disabled={disabled} onRemove={() => { setErrorLocal(''); onChange(archivos.filter((_, i) => i !== index)); }} />)}
                {archivos.length < maxTotal && (
                    <label className={`gelia-adjunto-control w-full border-dashed py-4 ${disabled ? 'opacity-50' : ''}`}>
                        <Upload className="w-5 h-5" aria-hidden="true" /> Agregar PDF
                        <input type="file" className="sr-only" aria-label="Agregar PDF de factura emitida" accept=".pdf,application/pdf" multiple disabled={disabled} onChange={(event) => { agregar(event.target.files); event.target.value = ''; }} />
                    </label>
                )}
            </div>
            <p className="text-xs theme-text-muted m-0">Hasta {maxTotal} PDFs de 5 MB cada uno. Puedes arrastrarlos aquí y revisarlos antes de enviar.</p>
            {mensajeError && <p role="alert" className="text-sm theme-text-peligro m-0">{mensajeError}</p>}
        </section>
    );
}
