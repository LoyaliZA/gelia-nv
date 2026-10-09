import React, { useId, useRef, useState } from 'react';
import { Camera, ImagePlus, FileText, Trash2, CheckCircle2 } from 'lucide-react';
import { BTN_SECONDARY } from './pedidosBmaStyles';
import { archivosImagenDesdeClipboard } from './archivosDesdeClipboard';
import { esDispositivoCampo } from '../../Activos/Partials/useDispositivoCampo';
import ModalConfirmarAccion from './ModalConfirmarAccion';

export default function GaleriaEvidenciasPedido({
    archivos = [], previews = [], onChange, onVer, label = 'Evidencias', obligatorio = false,
    id, error, soloImagenes = false, maxArchivos = Infinity, maxMb = 10, disabled = false,
}) {
    const uid = useId();
    const controlId = id || `evidencias-${uid}`;
    const camaraRef = useRef(null);
    const adjuntosRef = useRef(null);
    const [quitarIdx, setQuitarIdx] = useState(null);
    const [errorArchivo, setErrorArchivo] = useState('');
    const [arrastrando, setArrastrando] = useState(false);
    const esMovil = esDispositivoCampo();
    const formatos = soloImagenes ? 'JPG, PNG o WEBP' : 'JPG, PNG, WEBP o PDF';
    const accept = `image/jpeg,image/png,image/webp${soloImagenes ? '' : ',application/pdf'}`;
    const agregar = (lista) => {
        if (disabled) return;
        const candidatos = Array.from(lista || []);
        if (!candidatos.length) return;
        let aviso = '';
        const validos = candidatos.filter((file) => {
            const tipo = ['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || /\.(jpe?g|png|webp)$/i.test(file.name) || (!soloImagenes && (file.type === 'application/pdf' || /\.pdf$/i.test(file.name)));
            if (!tipo) { aviso = `Usa archivos ${formatos}.`; return false; }
            if (file.size > maxMb * 1024 * 1024) { aviso = `“${file.name}” supera ${maxMb} MB. Adjunta un archivo más pequeño.`; return false; }
            return true;
        });
        const cupo = Math.max(0, maxArchivos - previews.length);
        if (validos.length > cupo) aviso = `Puedes adjuntar hasta ${maxArchivos} fotos. Quita una para agregar otra.`;
        const nuevos = validos.slice(0, cupo);
        setErrorArchivo(aviso);
        if (!nuevos.length) return;
        onChange([...archivos, ...nuevos], [...previews, ...nuevos.map((f) => ({ name: f.name, url: URL.createObjectURL(f), mime: f.type }))]);
    };
    const quitar = (idx) => {
        const preview = previews[idx];
        const localesAntes = previews.slice(0, idx).filter((p) => !p.remoto).length;
        if (preview?.url?.startsWith('blob:')) URL.revokeObjectURL(preview.url);
        onChange(preview?.remoto ? archivos : archivos.filter((_, i) => i !== localesAntes), previews.filter((_, i) => i !== idx));
        setErrorArchivo('');
    };
    const docs = previews.map((p) => ({ url: p.url, nombre_original: p.name, mime_type: p.mime, tipo: 'evidencia_condicion' }));
    const mensaje = error || errorArchivo;
    return (
        <div className="gelia-pedidos-adjuntos space-y-3" onPaste={(event) => {
            if (disabled) return;
            const imagenes = archivosImagenDesdeClipboard(event.clipboardData);
            if (!imagenes.length) return;
            event.preventDefault();
            agregar(imagenes);
        }} onDragOver={(event) => { if (!disabled && event.dataTransfer.types.includes('Files')) { event.preventDefault(); setArrastrando(true); } }}
            onDragLeave={(event) => { if (!event.currentTarget.contains(event.relatedTarget)) setArrastrando(false); }}
            onDrop={(event) => { event.preventDefault(); if (disabled) return; setArrastrando(false); agregar(event.dataTransfer.files); }}>
            <div className="flex items-start justify-between gap-2">
                <p id={`${controlId}-label`} className="text-sm font-semibold theme-text-main m-0">{label}{obligatorio ? ' *' : ''}</p>
                <span className={`inline-flex items-center gap-1 text-xs font-medium shrink-0 ${previews.length ? 'theme-text-exito' : 'theme-text-muted'}`}>
                    {previews.length > 0 && <CheckCircle2 className="w-3.5 h-3.5" aria-hidden="true" />}
                    {previews.length} {previews.length === 1 ? 'archivo' : 'archivos'}
                </span>
            </div>
            <div className="gelia-pedidos-adjuntos-control" data-dragging={arrastrando}>
                <div className="flex flex-wrap gap-2">
                    {esMovil && <button type="button" disabled={disabled} onClick={() => camaraRef.current?.click()} className={`${BTN_SECONDARY} inline-flex items-center justify-center gap-2 flex-1`}>
                        <Camera className="w-4 h-4" aria-hidden="true" /> Tomar foto
                    </button>}
                    <button id={controlId} type="button" disabled={disabled} onClick={() => adjuntosRef.current?.click()}
                        aria-labelledby={`${controlId}-label ${controlId}-accion`} aria-invalid={Boolean(mensaje)} aria-describedby={`${controlId}-ayuda${mensaje ? ` ${controlId}-error` : ''}`}
                        className={`${BTN_SECONDARY} inline-flex items-center justify-center gap-2 ${esMovil ? 'flex-1' : ''}`}>
                        <ImagePlus className="w-4 h-4" aria-hidden="true" /><span id={`${controlId}-accion`}>{esMovil ? 'Galería' : (soloImagenes ? 'Adjuntar fotos' : 'Adjuntar fotos o PDF')}</span>
                    </button>
                </div>
                <input ref={camaraRef} type="file" accept="image/*" capture="environment" className="hidden" aria-label={`Tomar foto: ${label}`} disabled={disabled}
                    onChange={(event) => { agregar(event.target.files); event.target.value = ''; }} />
                <input ref={adjuntosRef} type="file" accept={accept} multiple className="hidden" aria-label={`Adjuntar: ${label}`} disabled={disabled}
                    onChange={(event) => { agregar(event.target.files); event.target.value = ''; }} />
                <p id={`${controlId}-ayuda`} className="text-xs theme-text-muted m-0 mt-2">
                    {esMovil ? 'Revisa la foto antes de registrar.' : 'Arrastra archivos aquí o pega capturas con Ctrl+V.'} {formatos}, hasta {maxMb} MB por archivo.
                </p>
            </div>
            {mensaje && <p id={`${controlId}-error`} role="alert" className="text-sm theme-text-peligro m-0">{mensaje}</p>}
            {previews.length > 0 && <div className="gelia-pedidos-evidencias">
                {previews.map((preview, idx) => (
                    <div key={`${preview.url}-${idx}`} className="gelia-pedidos-evidencia">
                        <button type="button" className="gelia-pedidos-evidencia-vista relative" onClick={() => onVer?.(docs, idx)} aria-label={`Ver evidencia ${idx + 1}: ${preview.name}`}>
                            {/pdf/i.test(preview.mime || '') || /\.pdf$/i.test(preview.name || '')
                                ? <span className="h-full flex flex-col items-center justify-center gap-1 theme-text-muted text-xs"><FileText className="w-6 h-6" aria-hidden="true" /> PDF</span>
                                : <img src={preview.url} alt={preview.name || `Evidencia ${idx + 1}`} width="112" height="112" loading="lazy" className="w-full h-full object-cover" />}
                            {preview.remoto && <span className="gelia-pedidos-evidencia-remota">Desde celular</span>}
                        </button>
                        <p className="gelia-pedidos-evidencia-nombre" title={preview.name}>{preview.name || `Evidencia ${idx + 1}`}</p>
                        <button type="button" disabled={disabled} onClick={() => setQuitarIdx(idx)} className="gelia-pedidos-evidencia-quitar" aria-label={`Quitar evidencia ${idx + 1}: ${preview.name}`}>
                            <Trash2 className="w-3.5 h-3.5" aria-hidden="true" /> Quitar
                        </button>
                    </div>
                ))}
            </div>}
            <ModalConfirmarAccion abierto={quitarIdx != null} titulo="Quitar evidencia" mensaje="Se quitará esta evidencia de la respuesta." etiquetaConfirmar="Quitar" variante="danger"
                onClose={() => setQuitarIdx(null)} onConfirm={() => { if (quitarIdx != null) quitar(quitarIdx); setQuitarIdx(null); }} />
        </div>
    );
}
