import React from 'react';
import { Download, Trash2, FileSpreadsheet } from 'lucide-react';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { router } from '@inertiajs/react';

export default function HistorialBellaroma({ titulo, templates, permisos }) {
    const eliminar = (id) => {
        if (confirm('¿Seguro que deseas eliminar esta plantilla del servidor?')) {
            router.delete(route('plantilla_bellaroma.eliminar', id));
        }
    };

    if (!templates || templates.length === 0) {
        return (
            <div className={`${geliaCardClass()} p-6`}>
                <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4">{titulo}</h3>
                <p className="text-xs font-bold theme-text-muted">No hay plantillas generadas.</p>
            </div>
        );
    }

    return (
        <div className={`${geliaCardClass()} p-6 flex flex-col`}>
            <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4">{titulo}</h3>
            
            <div className="flex-1 overflow-y-auto max-h-[300px] custom-scrollbar pr-2 space-y-3">
                {templates.map(template => (
                    <div key={template.id} className="p-4 rounded-xl border theme-border theme-surface flex justify-between items-center group transition-colors" onMouseEnter={(e) => { e.currentTarget.style.borderColor = 'var(--color-primario)'; }} onMouseLeave={(e) => { e.currentTarget.style.borderColor = ''; }}>
                        <div className="flex items-center gap-3 overflow-hidden">
                            <div className="p-2 rounded-lg bg-black/5 dark:bg-white/5" style={{ color: 'var(--color-primario)' }}>
                                <FileSpreadsheet className="w-5 h-5" />
                            </div>
                            <div className="flex flex-col truncate">
                                <span className="text-xs font-black theme-text-main truncate">{template.nombre_archivo}</span>
                                <span className="text-[10px] font-bold theme-text-muted mt-0.5">
                                    {new Date(template.created_at).toLocaleString()} • {template.tamano_kb}
                                </span>
                            </div>
                        </div>
                        <div className="flex items-center gap-2 ml-4">
                            {permisos?.descargar && (
                                <a 
                                    href={route('plantilla_bellaroma.descargar', template.id)} 
                                    className="p-2 rounded-lg bg-gray-100 text-gray-500 hover:text-white dark:bg-white/5 transition-colors"
                                    style={{ '--tw-ring-color': 'var(--color-primario)' }}
                                    onMouseEnter={(e) => { e.currentTarget.style.backgroundColor = 'var(--color-primario)'; }}
                                    onMouseLeave={(e) => { e.currentTarget.style.backgroundColor = ''; }}
                                    title="Descargar"
                                >
                                    <Download className="w-4 h-4" />
                                </a>
                            )}
                            {permisos?.eliminar && (
                                <button 
                                    onClick={() => eliminar(template.id)}
                                    className="p-2 rounded-lg bg-gray-100 hover:bg-red-500 text-gray-500 hover:text-white dark:bg-white/5 dark:hover:bg-red-500 transition-colors"
                                    title="Eliminar"
                                >
                                    <Trash2 className="w-4 h-4" />
                                </button>
                            )}
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
