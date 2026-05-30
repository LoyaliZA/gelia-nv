import React from 'react';
import { X, FileText } from 'lucide-react';

export default function AdjuntoPreview({ preview, onEliminar }) {
    if (!preview) return null;

    const { file, url, tipo } = preview;

    return (
        <div className="relative inline-block m-2">
            {tipo === 'imagen' && (
                <img src={url} alt="Preview" className="h-20 w-20 object-cover rounded-xl border theme-border" />
            )}
            {tipo === 'video' && (
                <video src={url} className="h-20 w-32 object-cover rounded-xl border theme-border" muted />
            )}
            {(tipo === 'archivo' || tipo === 'audio') && (
                <div className="flex items-center gap-2 px-3 py-2 rounded-xl theme-element border theme-border">
                    <FileText className="w-5 h-5 opacity-60" />
                    <span className="text-xs truncate max-w-[120px]">{file?.name}</span>
                </div>
            )}
            <button
                type="button"
                onClick={onEliminar}
                className="absolute -top-2 -right-2 w-5 h-5 rounded-full bg-red-500 text-white flex items-center justify-center"
            >
                <X className="w-3 h-3" />
            </button>
        </div>
    );
}
