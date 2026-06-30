import React from 'react';
import { Database } from 'lucide-react';

export default function InstruccionesImportacion({ columnas = [], notas = [] }) {
    return (
        <div className="mt-6 pt-6 border-t theme-border space-y-4">
            <div className="flex items-center gap-2 text-amber-500 dark:text-amber-400">
                <Database className="w-4 h-4" />
                <p className="text-[9px] font-black uppercase tracking-widest italic m-0">Cómo importar_</p>
            </div>
            <ol className="text-[10px] theme-text-muted font-bold leading-relaxed space-y-1 list-decimal list-inside m-0 p-0">
                <li>Descarga la plantilla y llénala con tus datos (no modifiques los nombres de la primera fila).</li>
                <li>Guarda el archivo en CSV o Excel (.xlsx, .xls).</li>
                <li>Arrastra el archivo o haz clic en examinar.</li>
                <li>Confirma la importación — se crearán registros nuevos o se actualizarán los existentes.</li>
            </ol>
            <div>
                <p className="text-[9px] font-black uppercase tracking-widest theme-text-muted mb-2">Columnas soportadas</p>
                <ul className="text-[10px] theme-text-muted font-bold leading-relaxed space-y-1 m-0 p-0">
                    {columnas.map((col) => (
                        <li key={col.key}>
                            <strong className="theme-text-main" style={{ color: 'var(--color-primario)' }}>{col.label}</strong>
                            {col.requerido ? ' (Requerido)' : ' (Opcional)'}
                            {col.nota ? ` — ${col.nota}` : ''}
                        </li>
                    ))}
                </ul>
            </div>
            {notas.length > 0 && (
                <div className="space-y-1">
                    {notas.map((nota, i) => (
                        <p key={i} className="text-[9px] font-black uppercase tracking-widest text-amber-600 dark:text-amber-400 m-0">
                            {nota}
                        </p>
                    ))}
                </div>
            )}
        </div>
    );
}
