import React from 'react';
import { geliaCardClass } from '../../../utils/geliaTheme';
import { Download, Trash2 } from 'lucide-react';

export default function HistorialTemplates({ templatesHoy, templatesHistorial, permisos }) {
    const renderList = (items, titulo) => (
        items?.length > 0 && (
            <div className="mb-4">
                <h4 className="text-[10px] font-black uppercase tracking-widest theme-text-muted mb-2">{titulo}</h4>
                <div className="space-y-2">
                    {items.map((t) => (
                        <div key={t.id} className="flex items-center justify-between p-3 theme-element border theme-border rounded-xl">
                            <div>
                                <p className="text-xs font-bold theme-text-main">{t.nombre_archivo}</p>
                                <p className="text-[10px] theme-text-muted">{t.tamano_kb}</p>
                            </div>
                            <div className="flex gap-2">
                                <a href={route('woocommerce.descargar', t.id)} className="p-2 rounded-lg border theme-border hover:border-[var(--color-primario)]">
                                    <Download className="w-4 h-4 theme-text-muted" />
                                </a>
                                {permisos.sincronizar && (
                                    <button onClick={async () => {
                                        await fetch(route('woocommerce.templates.eliminar', t.id), {
                                            method: 'DELETE',
                                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content, Accept: 'application/json' },
                                        });
                                        window.location.reload();
                                    }} className="p-2 rounded-lg border theme-border hover:border-red-500">
                                        <Trash2 className="w-4 h-4 text-red-500" />
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        )
    );

    return (
        <div className={`${geliaCardClass()} p-6 md:p-8`}>
            <h2 className="text-lg font-black uppercase theme-text-main border-b theme-border pb-4 mb-4">Historial CSV</h2>
            {renderList(templatesHoy, 'Hoy')}
            {renderList(templatesHistorial, 'Anteriores')}
            {!templatesHoy?.length && !templatesHistorial?.length && (
                <p className="text-xs theme-text-muted text-center py-6">Sin archivos generados aún.</p>
            )}
        </div>
    );
}
