import React, { useEffect, useId, useRef, useState } from 'react';
import { Upload, Trash2, FileText, ExternalLink, Loader2 } from 'lucide-react';
import { compressImageToWebp, validateImageSource } from '../../../utils/compressImage';
import EvidenciaSolicitud from '@/Components/Solicitudes/EvidenciaSolicitud';
import { MAX_VOUCHERS, archivoExcedeLimite, mensajeLimiteArchivo } from './limitesAdjuntosFactura';

export default function ZonaAdjuntoVoucher({ vouchers, onChange, error, existentes = [], onQuitarExistente, maxTotal = MAX_VOUCHERS, disabled = false, onBusyChange }) {
    const id = useId();
    const [previews, setPreviews] = useState(new Map());
    const [errorLocal, setErrorLocal] = useState('');
    const [busy, setBusy] = useState(false);
    const busyRef = useRef(false);
    const total = existentes.length + vouchers.length;
    useEffect(() => {
        const urls = new Map(vouchers.map((file) => [file, URL.createObjectURL(file)]));
        setPreviews(urls);
        return () => urls.forEach((url) => URL.revokeObjectURL(url));
    }, [vouchers]);

    const agregar = async (files) => {
        if (disabled || busyRef.current) return;
        const lista = Array.from(files || []);
        if (!lista.length) return;
        busyRef.current = true; setBusy(true); onBusyChange?.(true);
        const actuales = [...vouchers]; const problemas = new Set();
        try {
            for (const file of lista) {
                if (actuales.length + existentes.length >= maxTotal) { problemas.add(`Puedes adjuntar hasta ${maxTotal} comprobantes.`); continue; }
                if (archivoExcedeLimite(file)) { problemas.add(`${file.name}: ${mensajeLimiteArchivo()}`); continue; }
                if (actuales.some((v) => v.name === file.name && v.size === file.size && v.lastModified === file.lastModified)) { problemas.add(`${file.name} ya está adjunto.`); continue; }
                if (file.type === 'application/pdf' || /\.pdf$/i.test(file.name)) { actuales.push(file); continue; }
                if (!file.type.startsWith('image/')) { problemas.add(`${file.name}: selecciona una imagen JPG, PNG, WebP o un PDF.`); continue; }
                const problema = validateImageSource(file, 'Comprobante');
                if (problema) { problemas.add(problema); continue; }
                try { actuales.push(await compressImageToWebp(file)); }
                catch { problemas.add(`No se pudo preparar ${file.name}. Intenta agregarlo de nuevo.`); }
            }
            onChange(actuales);
            setErrorLocal([...problemas].join(' '));
        } finally { busyRef.current = false; setBusy(false); onBusyChange?.(false); }
    };

    return <section className="space-y-3" aria-labelledby={`${id}-titulo`} aria-busy={busy}
        onPaste={(event) => { const files = Array.from(event.clipboardData?.items || []).filter((item) => item.type.startsWith('image/')).map((item) => item.getAsFile()).filter(Boolean); if (files.length) { event.preventDefault(); agregar(files); } }}>
        <div className="flex items-center justify-between gap-3"><h4 id={`${id}-titulo`} className="text-sm font-medium theme-text-main m-0">Comprobantes de pago</h4><span className="text-xs tabular-nums theme-text-muted">{total}/{maxTotal}</span></div>
        <div tabIndex={0} aria-label="Zona para adjuntar comprobantes" className="border border-dashed theme-border rounded-xl p-3 space-y-3"
            onDragOver={(event) => event.preventDefault()} onDrop={(event) => { event.preventDefault(); agregar(event.dataTransfer.files); }}>
            {existentes.map((v) => <div key={v.id} className="flex items-center gap-3 p-3 rounded-lg theme-element border theme-border">
                {v.previewUrl ? <img src={v.previewUrl} alt="" width="48" height="48" className="w-12 h-12 object-cover rounded-lg" /> : <FileText className="w-5 h-5 theme-text-primario shrink-0" aria-hidden="true" />}
                <div className="min-w-0 flex-1"><span className="text-sm theme-text-main truncate block">{v.label}</span><span className="text-xs theme-text-muted">Guardado en la solicitud</span></div>
                {v.verUrl && <a href={v.verUrl} target="_blank" rel="noopener noreferrer" className="gelia-workflow-close" aria-label={`Abrir ${v.label}`}><ExternalLink className="w-4 h-4" aria-hidden="true" /></a>}
                <button type="button" disabled={disabled || busy} onClick={() => onQuitarExistente?.(v.id)} className="gelia-workflow-close theme-text-peligro" aria-label={`Quitar ${v.label}`}><Trash2 className="w-4 h-4" aria-hidden="true" /></button>
            </div>)}
            {vouchers.map((v, i) => <div key={`${v.name}-${i}`} className="flex items-center gap-3 p-3 rounded-lg theme-element border theme-border">
                {previews.get(v) && v.type.startsWith('image/') ? <img src={previews.get(v)} alt="" width="48" height="48" className="w-12 h-12 object-cover rounded-lg" /> : <FileText className="w-5 h-5 theme-text-primario shrink-0" aria-hidden="true" />}
                <div className="min-w-0 flex-1"><span className="text-sm theme-text-main break-words block">{v.name}</span><span className="text-xs theme-text-muted">{new Intl.NumberFormat('es-MX', { maximumFractionDigits: 1 }).format(v.size / 1024)} KB · listo para guardar</span></div>
                {previews.get(v) && <EvidenciaSolicitud url={previews.get(v)} esPdf={v.type === 'application/pdf' || /\.pdf$/i.test(v.name)} label="Revisar" title={v.name} dialogClassName="gelia-facturas-dialog-overlay" />}
                <button type="button" disabled={disabled || busy} onClick={() => { setErrorLocal(''); onChange(vouchers.filter((_, index) => index !== i)); }} className="gelia-workflow-close theme-text-peligro" aria-label={`Quitar ${v.name}`}><Trash2 className="w-4 h-4" aria-hidden="true" /></button>
            </div>)}
            {total < maxTotal && <label className={`gelia-adjunto-control w-full py-4 ${disabled || busy ? 'opacity-50' : ''}`}>
                {busy ? <Loader2 className="w-5 h-5 animate-spin" aria-hidden="true" /> : <Upload className="w-5 h-5" aria-hidden="true" />}{busy ? 'Preparando archivos…' : 'Agregar comprobantes'}
                <input type="file" className="sr-only" aria-label="Agregar comprobantes de pago" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" multiple disabled={disabled || busy} onChange={(event) => { agregar(event.target.files); event.target.value = ''; }} />
            </label>}
            <p className="text-xs theme-text-muted m-0">Arrastra archivos, selecciona imágenes o PDFs, o pega una captura. Hasta {maxTotal} comprobantes de 5 MB cada uno.</p>
        </div>
        <p className="sr-only" role="status">{busy ? 'Preparando archivos. Espera antes de guardar.' : `${total} comprobantes adjuntos`}</p>
        {(error || errorLocal) && <p role="alert" className="text-sm theme-text-peligro m-0">{error || errorLocal}</p>}
    </section>;
}
