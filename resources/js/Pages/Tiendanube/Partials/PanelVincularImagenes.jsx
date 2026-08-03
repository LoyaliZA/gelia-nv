import React, { useCallback, useRef, useState } from 'react';
import { Crop, ImagePlus, Upload, X } from 'lucide-react';
import GeliaLoader from '../../../Components/GeliaLoader';
import ImageEditModal from './ImageEditModal';
import {
    formatBytes,
    parseTiendanubeImageFilename,
    readImageDimensions,
} from '../../../utils/tiendanubeImageSku';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const ALLOWED = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

let rowSeq = 0;

async function resolveSku(sku) {
    const res = await fetch(route('tiendanube.skus.resolver', { sku }), {
        headers: { Accept: 'application/json' },
    });
    if (!res.ok) {
        return { sku, encontrado: false, producto_id: null, nombre: null, imagen_actual: null };
    }
    return res.json();
}

async function buildRow(file) {
    const previewUrl = URL.createObjectURL(file);
    const parsed = parseTiendanubeImageFilename(file.name);
    const dims = await readImageDimensions(file);
    let resolved = null;
    if (parsed?.sku) {
        try {
            resolved = await resolveSku(parsed.sku);
        } catch {
            resolved = { sku: parsed.sku, encontrado: false };
        }
    }

    return {
        id: ++rowSeq,
        file,
        previewUrl,
        filename: file.name,
        size: file.size,
        width: dims.width,
        height: dims.height,
        parsed,
        resolved,
        status: 'pending',
        message: null,
    };
}

export default function PanelVincularImagenes({
    permisos,
    credencialesOk,
    onChanged,
}) {
    const [rows, setRows] = useState([]);
    const [permitirVarias, setPermitirVarias] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState(null);
    const [dragOver, setDragOver] = useState(false);
    const [editRowId, setEditRowId] = useState(null);
    const inputRef = useRef(null);

    const revokeAll = (list) => {
        list.forEach((r) => {
            if (r.previewUrl) URL.revokeObjectURL(r.previewUrl);
        });
    };

    const addFiles = useCallback(async (fileList) => {
        const files = Array.from(fileList || []).filter((f) => {
            const okMime = ALLOWED.includes(f.type) || /\.(jpe?g|png|gif|webp)$/i.test(f.name);
            return okMime && f.size <= 10 * 1024 * 1024;
        });
        if (!files.length) {
            setError('Usa JPG, PNG, GIF o WEBP de hasta 10 MB.');
            return;
        }
        setError(null);
        const built = [];
        for (const f of files) {
            built.push(await buildRow(f));
        }
        setRows((prev) => [...prev, ...built]);
    }, []);

    const removeRow = (id) => {
        setRows((prev) => {
            const row = prev.find((r) => r.id === id);
            if (row?.previewUrl) URL.revokeObjectURL(row.previewUrl);
            return prev.filter((r) => r.id !== id);
        });
    };

    const clearAll = () => {
        setRows((prev) => {
            revokeAll(prev);
            return [];
        });
    };

    const applyEditedFile = async (rowId, nextFile) => {
        const dims = await readImageDimensions(nextFile);
        const previewUrl = URL.createObjectURL(nextFile);
        setRows((prev) => prev.map((r) => {
            if (r.id !== rowId) return r;
            if (r.previewUrl) URL.revokeObjectURL(r.previewUrl);
            return {
                ...r,
                file: nextFile,
                previewUrl,
                filename: nextFile.name,
                size: nextFile.size,
                width: dims.width,
                height: dims.height,
                status: 'pending',
                message: 'Editada · lista para subir',
            };
        }));
        setEditRowId(null);
    };

    const editRow = rows.find((r) => r.id === editRowId);

    const subir = async () => {
        if (!permisos.editar || !credencialesOk || uploading) return;
        const listos = rows.filter((r) => r.resolved?.encontrado && r.resolved?.producto_id);
        if (!listos.length) {
            setError('No hay archivos con SKU vinculado a un producto.');
            return;
        }

        setUploading(true);
        setError(null);
        const reemplazar = !permitirVarias;

        for (const row of listos) {
            setRows((prev) => prev.map((r) => (r.id === row.id ? { ...r, status: 'uploading', message: null } : r)));
            try {
                const body = new FormData();
                body.append('file', row.file);
                body.append('reemplazar', reemplazar ? '1' : '0');
                if (row.parsed?.position) {
                    body.append('position', String(row.parsed.position));
                }
                const res = await fetch(route('tiendanube.productos.imagenes.store', row.resolved.producto_id), {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken(),
                        Accept: 'application/json',
                    },
                    body,
                });
                const data = await res.json();
                if (!res.ok || !data.success) {
                    throw new Error(data.message || 'Error al subir.');
                }
                setRows((prev) => prev.map((r) => (r.id === row.id ? { ...r, status: 'ok', message: data.message || 'OK' } : r)));
            } catch (err) {
                setRows((prev) => prev.map((r) => (r.id === row.id ? { ...r, status: 'error', message: err.message } : r)));
            }
        }

        setUploading(false);
        onChanged?.();
    };

    const onDrop = (e) => {
        e.preventDefault();
        setDragOver(false);
        addFiles(e.dataTransfer.files);
    };

    const listos = rows.filter((r) => r.resolved?.encontrado).length;

    return (
        <div className="space-y-4">
            <GeliaLoader isVisible={uploading} message="Subiendo imágenes_" />
            <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                <div>
                    <h3 className="text-sm font-black uppercase tracking-widest theme-text-main flex items-center gap-2">
                        <ImagePlus className="w-4 h-4" style={{ color: 'var(--color-primario)' }} />
                        Carga individual
                    </h3>
                    <p className="text-xs theme-text-muted mt-1">
                        Arrastra archivos <code className="font-mono">SKU.webp</code> o{' '}
                        <code className="font-mono">SKU_2.jpg</code>. Por defecto reemplaza todas las imágenes del producto.
                    </p>
                </div>
                <label className="inline-flex items-center gap-2 text-[10px] font-black uppercase tracking-widest theme-text-muted cursor-pointer select-none">
                    <input
                        type="checkbox"
                        checked={permitirVarias}
                        onChange={(e) => setPermitirVarias(e.target.checked)}
                        className="rounded border theme-border"
                    />
                    Permitir varias imágenes
                </label>
            </div>

            <div
                onDragOver={(e) => {
                    e.preventDefault();
                    setDragOver(true);
                }}
                onDragLeave={() => setDragOver(false)}
                onDrop={onDrop}
                onClick={() => inputRef.current?.click()}
                className={`border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer transition-colors ${
                    dragOver ? 'border-amber-500 bg-amber-500/5' : 'theme-border hover:bg-black/[0.02] dark:hover:bg-white/[0.02]'
                } ${!credencialesOk || !permisos.editar ? 'opacity-50 pointer-events-none' : ''}`}
            >
                <Upload className="w-8 h-8 mx-auto mb-2 theme-text-muted" />
                <p className="text-sm font-bold theme-text-main">Arrastra imágenes aquí o haz clic</p>
                <p className="text-[10px] theme-text-muted mt-1 uppercase tracking-widest">JPG · PNG · GIF · WEBP · máx 10 MB</p>
                <input
                    ref={inputRef}
                    type="file"
                    accept=".jpg,.jpeg,.png,.gif,.webp,image/*"
                    multiple
                    className="hidden"
                    onChange={(e) => {
                        addFiles(e.target.files);
                        e.target.value = '';
                    }}
                />
            </div>

            {!credencialesOk && (
                <p className="text-xs font-bold text-amber-600">Configura credenciales antes de subir.</p>
            )}
            {error && <p className="text-xs font-bold text-red-500">{error}</p>}

            {rows.length > 0 && (
                <div className="space-y-3">
                    <div className="flex justify-between items-center gap-2">
                        <p className="text-[10px] font-black uppercase tracking-widest theme-text-muted">
                            {rows.length} archivo(s) · {listos} listos
                        </p>
                        <button
                            type="button"
                            onClick={clearAll}
                            className="text-[10px] font-black uppercase tracking-widest theme-text-muted hover:theme-text-main"
                        >
                            Limpiar
                        </button>
                    </div>
                    <ul className="space-y-2 max-h-80 overflow-y-auto">
                        {rows.map((row) => {
                            const ok = row.resolved?.encontrado;
                            return (
                                <li
                                    key={row.id}
                                    className="flex gap-3 items-start p-3 rounded-xl border theme-border"
                                >
                                    <img
                                        src={row.previewUrl}
                                        alt=""
                                        className="w-14 h-14 rounded-lg object-cover border theme-border shrink-0"
                                    />
                                    <div className="flex-1 min-w-0 space-y-0.5">
                                        <p className="text-xs font-bold theme-text-main truncate">{row.filename}</p>
                                        <p className="text-[10px] theme-text-muted font-mono">
                                            {formatBytes(row.size)}
                                            {row.width && row.height ? ` · ${row.width}×${row.height}` : ''}
                                            {row.parsed ? ` · SKU ${row.parsed.sku}` : ' · nombre inválido'}
                                        </p>
                                        {ok ? (
                                            <p className="text-[10px] font-bold theme-text-main">
                                                → {row.resolved.nombre} (#{row.resolved.producto_id})
                                            </p>
                                        ) : (
                                            <p className="text-[10px] font-bold text-amber-600">
                                                {row.parsed
                                                    ? 'SKU no encontrado en el catálogo'
                                                    : 'Nombre no válido (usa SKU.ext)'}
                                            </p>
                                        )}
                                        {row.status === 'ok' && (
                                            <p className="text-[10px] font-bold text-emerald-600">{row.message || 'OK'}</p>
                                        )}
                                        {row.status === 'error' && (
                                            <p className="text-[10px] font-bold text-red-500">{row.message}</p>
                                        )}
                                        {row.status === 'uploading' && (
                                            <p className="text-[10px] theme-text-muted">Subiendo…</p>
                                        )}
                                    </div>
                                    <div className="flex flex-col gap-1 shrink-0">
                                        {row.status !== 'uploading' && row.status !== 'ok' && (
                                            <button
                                                type="button"
                                                onClick={() => setEditRowId(row.id)}
                                                className="p-1 theme-text-muted hover:theme-text-main"
                                                title="Editar antes de subir"
                                                disabled={uploading}
                                            >
                                                <Crop className="w-4 h-4" />
                                            </button>
                                        )}
                                        <button
                                            type="button"
                                            onClick={() => removeRow(row.id)}
                                            className="p-1 theme-text-muted hover:theme-text-main"
                                            disabled={uploading}
                                        >
                                            <X className="w-4 h-4" />
                                        </button>
                                    </div>
                                </li>
                            );
                        })}
                    </ul>
                    <button
                        type="button"
                        onClick={subir}
                        disabled={!listos || uploading || !credencialesOk || !permisos.editar}
                        className="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl text-[10px] font-black uppercase text-white disabled:opacity-50"
                        style={{ backgroundColor: 'var(--color-primario)' }}
                    >
                        <Upload className="w-4 h-4" />
                        {permitirVarias ? `Agregar ${listos}` : `Reemplazar ${listos}`}
                    </button>
                </div>
            )}

            {editRow && (
                <ImageEditModal
                    file={editRow.file}
                    onClose={() => setEditRowId(null)}
                    onSave={(nextFile) => applyEditedFile(editRow.id, nextFile)}
                />
            )}
        </div>
    );
}
