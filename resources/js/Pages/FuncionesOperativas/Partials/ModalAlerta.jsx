import React, { useEffect } from 'react';
import { createPortal } from 'react-dom';
import { AlertTriangle, XCircle, CheckCircle, Info, X } from 'lucide-react';

// ----------------------------------------------------------------------
// COMPONENTE AUXILIAR: MODAL DE ALERTAS GLOBALES
// ----------------------------------------------------------------------
export default function ModalAlerta({ show, type = 'error', title, message, details = null, onClose }) {
    useEffect(() => {
        if (show) document.body.style.overflow = 'hidden';
        else document.body.style.overflow = '';
        return () => { document.body.style.overflow = ''; };
    }, [show]);

    if (!show) return null;

    // Mapeo de colores e iconos según el tipo de alerta
    const config = {
        error: {
            icon: XCircle,
            colorClass: 'text-red-500',
            bgClass: 'bg-red-500/10 border-red-500/20',
            btnClass: 'bg-red-500 hover:bg-red-600 text-white'
        },
        warning: {
            icon: AlertTriangle,
            colorClass: 'text-orange-500',
            bgClass: 'bg-orange-500/10 border-orange-500/20',
            btnClass: 'bg-orange-500 hover:bg-orange-600 text-white'
        },
        success: {
            icon: CheckCircle,
            colorClass: 'text-emerald-500',
            bgClass: 'bg-emerald-500/10 border-emerald-500/20',
            btnClass: 'bg-emerald-500 hover:bg-emerald-600 text-white'
        },
        info: {
            icon: Info,
            colorClass: 'text-blue-500',
            bgClass: 'bg-blue-500/10 border-blue-500/20',
            btnClass: 'bg-blue-500 hover:bg-blue-600 text-white'
        }
    };

    const activeConfig = config[type] || config.info;
    const IconComponent = activeConfig.icon;

    // Función para renderizar el log/detalles de Laravel si existe
    const renderDetails = () => {
        if (!details) return null;
        
        return (
            <div className="mt-4 w-full text-left bg-zinc-100 dark:bg-black/40 p-4 rounded-xl border border-zinc-200 dark:border-white/5 max-h-32 overflow-y-auto custom-scrollbar shadow-inner">
                {typeof details === 'object' ? (
                    <ul className="list-disc list-inside text-[10px] font-mono text-zinc-600 dark:text-zinc-400 space-y-1">
                        {Object.entries(details).map(([key, value]) => (
                            <li key={key}>
                                <span className="font-bold uppercase tracking-wider">{key.replace('_', ' ')}:</span> {Array.isArray(value) ? value[0] : value}
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-[10px] font-mono text-zinc-600 dark:text-zinc-400 break-words">{String(details)}</p>
                )}
            </div>
        );
    };

    return createPortal(
        <div 
            className="fixed inset-0 z-[99999] flex items-center justify-center p-4 bg-black/60 backdrop-blur-md animate-fade-in" 
            onClick={onClose}
        >
            <div 
                className="relative w-full max-w-sm flex flex-col items-center gap-4 p-8 rounded-[2.5rem] shadow-2xl border backdrop-blur-xl theme-surface theme-border animate-fade-in"
                onClick={e => e.stopPropagation()}
            >
                <div className={`w-16 h-16 rounded-full flex items-center justify-center border shadow-inner ${activeConfig.bgClass}`}>
                    <IconComponent className={`w-8 h-8 ${activeConfig.colorClass}`} />
                </div>

                <div className="text-center space-y-2 w-full">
                    <h3 className={`text-lg font-black uppercase italic tracking-tighter m-0 ${activeConfig.colorClass}`}>
                        {title}
                    </h3>
                    <p className="text-xs font-bold theme-text-main leading-snug">
                        {message}
                    </p>
                    {renderDetails()}
                </div>

                <button
                    onClick={onClose}
                    className={`mt-2 w-full py-4 rounded-2xl font-black uppercase tracking-widest text-[10px] transition-all hover:-translate-y-0.5 shadow-lg outline-none ${activeConfig.btnClass}`}
                >
                    Entendido_
                </button>

                <button
                    onClick={onClose}
                    className="absolute top-4 right-4 p-2 theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 rounded-full transition-colors outline-none"
                >
                    <X className="w-4 h-4" />
                </button>
            </div>
        </div>,
        document.body
    );
}