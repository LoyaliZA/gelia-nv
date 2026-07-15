import React from 'react';
import { Download, Trash2, FileSpreadsheet, Mail } from 'lucide-react';
import { geliaCardClass } from '../../../utils/geliaTheme';
import axios from 'axios';
import { router } from '@inertiajs/react';

export default function HistorialListados({ titulo, listados = [], permisos = {}, onError }) {
    const eliminar = async (id) => {
        if (!confirm('¿Seguro que deseas eliminar este listado del servidor?')) return;
        try {
            await axios.delete(route('listados.generados.eliminar', id));
            router.reload({ only: ['historial_hoy', 'historial_anterior'] });
        } catch (error) {
            onError?.(error.response?.data?.error || error.message);
        }
    };

    if (!listados || listados.length === 0) {
        return (
            <div className={`${geliaCardClass()} p-6`}>
                <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4">{titulo}</h3>
                <p className="text-xs font-bold theme-text-muted">No hay listados guardados.</p>
            </div>
        );
    }

    return (
        <div className={`${geliaCardClass()} p-6 flex flex-col`}>
            <h3 className="text-sm font-black uppercase tracking-widest theme-text-main mb-4">{titulo}</h3>

            <div className="flex-1 overflow-y-auto max-h-[320px] custom-scrollbar pr-2 space-y-3">
                {listados.map((item) => (
                    <div
                        key={item.id}
                        className="p-4 rounded-xl border theme-border theme-surface flex justify-between items-center group transition-colors"
                        onMouseEnter={(e) => { e.currentTarget.style.borderColor = 'var(--color-primario)'; }}
                        onMouseLeave={(e) => { e.currentTarget.style.borderColor = ''; }}
                    >
                        <div className="flex items-center gap-3 overflow-hidden min-w-0">
                            <div className="p-2 rounded-lg bg-black/5 dark:bg-white/5 shrink-0" style={{ color: 'var(--color-primario)' }}>
                                <FileSpreadsheet className="w-5 h-5" />
                            </div>
                            <div className="flex flex-col truncate min-w-0">
                                <span className="text-xs font-black theme-text-main truncate">{item.nombre_archivo}</span>
                                <span className="text-[10px] font-bold theme-text-muted mt-0.5 truncate">
                                    {new Date(item.created_at).toLocaleString()} • {item.tamano_kb}
                                    {item.user?.name ? ` • ${item.user.name}` : ''}
                                    {item.enviado_correo ? ' • Enviado' : ''}
                                </span>
                            </div>
                        </div>
                        <div className="flex items-center gap-2 ml-4 shrink-0">
                            {item.enviado_correo && (
                                <span className="p-2 rounded-lg bg-emerald-500/10 text-emerald-500" title="Enviado por correo">
                                    <Mail className="w-4 h-4" />
                                </span>
                            )}
                            {permisos.visualizar && (
                                <a
                                    href={route('listados.generados.descargar', item.id)}
                                    className="p-2 rounded-lg bg-gray-100 text-gray-500 dark:bg-white/5 transition-colors"
                                    onMouseEnter={(e) => { e.currentTarget.style.backgroundColor = 'var(--color-primario)'; e.currentTarget.style.color = '#fff'; }}
                                    onMouseLeave={(e) => { e.currentTarget.style.backgroundColor = ''; e.currentTarget.style.color = ''; }}
                                    title="Descargar"
                                >
                                    <Download className="w-4 h-4" />
                                </a>
                            )}
                            {permisos.guardar_generado && (
                                <button
                                    type="button"
                                    onClick={() => eliminar(item.id)}
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
