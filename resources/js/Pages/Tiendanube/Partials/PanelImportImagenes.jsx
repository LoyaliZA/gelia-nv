import React, { useEffect, useState } from 'react';
import { ImagePlus, Upload } from 'lucide-react';
import { geliaCardClass } from '../../../utils/geliaTheme';
import GeliaLoader from '../../../Components/GeliaLoader';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

export default function PanelImportImagenes({
    permisos,
    credencialesOk,
    imageImportActivo,
    ultimosImportImagenes = [],
    onImportStarted,
    embedded = false,
}) {
    const [file, setFile] = useState(null);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState(null);
    const [preview, setPreview] = useState(null);
    const [importId, setImportId] = useState(imageImportActivo?.id || null);
    const [progreso, setProgreso] = useState(null);

    useEffect(() => {
        if (imageImportActivo?.id) {
            setImportId(imageImportActivo.id);
        }
    }, [imageImportActivo?.id]);

    useEffect(() => {
        if (!importId) return undefined;

        let cancelled = false;
        const poll = async () => {
            try {
                const res = await fetch(route('tiendanube.imagenes.importar.progreso', importId), {
                    headers: { Accept: 'application/json' },
                });
                if (!res.ok) return;
                const data = await res.json();
                if (cancelled) return;
                setProgreso(data);
            } catch {
                // ignore
            }
        };

        poll();
        const id = setInterval(poll, 2000);
        return () => {
            cancelled = true;
            clearInterval(id);
        };
    }, [importId]);

    const subir = async (e) => {
        e.preventDefault();
        if (!file || !permisos.editar || !credencialesOk) return;

        setUploading(true);
        setError(null);
        setPreview(null);
        try {
            const body = new FormData();
            body.append('zip', file);
            const res = await fetch(route('tiendanube.imagenes.importar'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    Accept: 'application/json',
                },
                body,
            });
            const data = await res.json();
            if (!res.ok || !data.success) {
                throw new Error(data.message || 'No se pudo iniciar la importación.');
            }
            setPreview(data.preview || null);
            setImportId(data.import_id);
            onImportStarted?.(data.import_id);
            setFile(null);
        } catch (err) {
            setError(err.message);
        } finally {
            setUploading(false);
        }
    };

    const activo = progreso && ['pendiente', 'en_proceso'].includes(progreso.estado);

    return (
        <div className={embedded ? 'space-y-4' : `${geliaCardClass()} p-5 md:p-6 space-y-4`}>
            <GeliaLoader isVisible={uploading} message="Subiendo ZIP_" />
            <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                <div>
                    <h2 className="text-sm font-black uppercase tracking-widest theme-text-main flex items-center gap-2">
                        <ImagePlus className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                        Carga masiva de imágenes
                    </h2>
                    <p className="text-xs theme-text-muted mt-1">
                        ZIP con archivos <code className="font-mono">SKU.webp</code> o{' '}
                        <code className="font-mono">SKU_2.jpg</code>. Se relacionan con el catálogo por SKU.
                    </p>
                </div>
            </div>

            {permisos.editar && (
                <form onSubmit={subir} className="flex flex-col sm:flex-row gap-3 items-stretch sm:items-center">
                    <input
                        type="file"
                        accept=".zip,application/zip"
                        onChange={(e) => setFile(e.target.files?.[0] || null)}
                        className="flex-1 text-xs theme-text-main file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-[10px] file:font-black file:uppercase file:bg-zinc-100 dark:file:bg-zinc-800"
                        disabled={uploading || activo || !credencialesOk}
                    />
                    <button
                        type="submit"
                        disabled={!file || uploading || activo || !credencialesOk}
                        className="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl text-[10px] font-black uppercase text-white disabled:opacity-50"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        <Upload className="w-4 h-4" />
                        {activo ? 'Importando…' : 'Importar ZIP'}
                    </button>
                </form>
            )}

            {!credencialesOk && (
                <p className="text-xs font-bold text-amber-600">Configura credenciales antes de importar.</p>
            )}
            {error && <p className="text-xs font-bold text-red-500">{error}</p>}

            {preview && (
                <p className="text-xs theme-text-muted">
                    Preview: {preview.matched} listos · {preview.sin_match} sin match · {preview.total} total
                </p>
            )}

            {(progreso || imageImportActivo) && (
                <div className="space-y-2">
                    <div className="flex justify-between text-[10px] font-black uppercase tracking-widest theme-text-muted">
                        <span>Estado: {progreso?.estado || imageImportActivo?.estado}</span>
                        <span>{progreso?.porcentaje ?? 0}%</span>
                    </div>
                    <div className="h-2 rounded-full bg-zinc-200 dark:bg-zinc-800 overflow-hidden">
                        <div
                            className="h-full transition-all"
                            style={{
                                width: `${progreso?.porcentaje ?? 0}%`,
                                backgroundColor: 'var(--color-primario)',
                            }}
                        />
                    </div>
                    <p className="text-xs theme-text-muted">
                        Procesados {progreso?.procesados ?? imageImportActivo?.procesados ?? 0}
                        {' / '}
                        {progreso?.total_archivos ?? imageImportActivo?.total_archivos ?? '…'}
                        {' · '}
                        OK {progreso?.exitosos ?? imageImportActivo?.exitosos ?? 0}
                        {' · '}
                        Fallidos {progreso?.fallidos ?? imageImportActivo?.fallidos ?? 0}
                    </p>
                    {progreso?.mensaje_error && (
                        <p className="text-xs font-bold text-red-500">{progreso.mensaje_error}</p>
                    )}
                    {progreso?.errores?.length > 0 && (
                        <ul className="text-xs theme-text-muted space-y-1 max-h-28 overflow-y-auto border-t theme-border pt-2">
                            {progreso.errores.map((err, i) => (
                                <li key={i}>
                                    <span className="font-mono theme-text-main">{err.filename}</span>
                                    {err.sku ? ` (${err.sku})` : ''}: {err.mensaje || err.estado}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            )}

            {ultimosImportImagenes?.length > 0 && (
                <div className="pt-2 border-t theme-border">
                    <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">Últimas importaciones</p>
                    <ul className="space-y-1">
                        {ultimosImportImagenes.map((s) => (
                            <li key={s.id} className="text-xs theme-text-muted flex justify-between gap-2">
                                <span>#{s.id} · {s.estado}</span>
                                <span>{s.exitosos}/{s.total_archivos} ok</span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}
