import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { Bell, BellRing, Clock, CheckCircle2, AlertCircle, X, Trash2, MailOpen } from 'lucide-react';

export default function NotificationBell({ notifications = [] }) {
    const [isOpen, setIsOpen] = useState(false);
    
    // Contar solo las no leídas (asumiendo que tu BD manda read_at en null)
    const unreadCount = notifications.filter(n => !n.read_at).length;

    // Bloquear el scroll de la página de fondo cuando el cajón está abierto
    useEffect(() => {
        if (isOpen) document.body.style.overflow = 'hidden';
        else document.body.style.overflow = 'unset';
        return () => { document.body.style.overflow = 'unset'; };
    }, [isOpen]);

    return (
        <>
            {/* --- BOTÓN DE LA CAMPANITA (SE QUEDA EN EL NAVBAR) --- */}
            <button 
                onClick={() => setIsOpen(true)}
                className="relative p-3 theme-element border theme-border rounded-2xl hover:border-[var(--color-primario)] transition-all group outline-none"
            >
                {unreadCount > 0 ? (
                    <BellRing className="w-5 h-5 animate-pulse" style={{ color: 'var(--color-primario)' }} />
                ) : (
                    <Bell className="w-5 h-5 theme-text-muted group-hover:text-[var(--color-primario)] transition-colors" />
                )}
                
                {unreadCount > 0 && (
                    <span className="absolute -top-2 -right-2 w-5 h-5 text-white text-[9px] font-black rounded-full flex items-center justify-center shadow-md border-2 theme-surface" style={{ backgroundColor: 'var(--color-primario)' }}>
                        {unreadCount > 9 ? '9+' : unreadCount}
                    </span>
                )}
            </button>

            {/* --- CAJÓN LATERAL FLOTANTE (OFF-CANVAS) --- */}
            {isOpen && createPortal(
                <div className="fixed inset-0 z-[9999] flex justify-end">
                    {/* Fondo oscuro desenfocado */}
                    <div 
                        className="absolute inset-0 bg-black/60 backdrop-blur-sm animate-fade-in" 
                        onClick={() => setIsOpen(false)}
                    ></div>
                    
                    {/* Panel Lateral que entra desde la derecha */}
                    <div className="relative w-full max-w-md theme-surface border-l theme-border shadow-2xl h-full flex flex-col animate-slide-in-right">
                        
                        {/* Cabecera del Panel */}
                        <div className="p-6 md:p-8 border-b theme-border flex justify-between items-center bg-black/5 dark:bg-white/5 shrink-0">
                            <div className="flex items-center gap-3">
                                <div className="p-2 rounded-xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                                    <BellRing className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                                </div>
                                <div>
                                    <h3 className="text-lg font-black uppercase tracking-tighter theme-text-main italic m-0">Centro de Alertas_</h3>
                                    <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-0.5">{unreadCount} no leídas</p>
                                </div>
                            </div>
                            <button 
                                onClick={() => setIsOpen(false)}
                                className="p-2 theme-text-muted hover:theme-text-main bg-white/10 rounded-full transition-transform hover:scale-110 outline-none"
                            >
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        {/* Lista de Notificaciones */}
                        <div className="flex-1 overflow-y-auto custom-scrollbar p-4 space-y-2">
                            {notifications.length > 0 ? (
                                notifications.map((n, i) => {
                                    // Detectar si es de error/rechazo para cambiar el color
                                    const isError = n.type === 'pago_rechazado' || n.type === 'rechazada';
                                    const iconColor = isError ? 'text-red-500' : 'text-[var(--color-primario)]';
                                    const bgClase = n.read_at ? 'bg-transparent border-transparent' : 'theme-element border theme-border shadow-sm';

                                    return (
                                        <div key={i} className={`p-5 rounded-2xl transition-all cursor-pointer group ${bgClase} hover:border-[var(--color-primario)]`}>
                                            <div className="flex gap-4">
                                                <div className="mt-1 shrink-0">
                                                    {isError ? <AlertCircle className={`w-5 h-5 ${iconColor}`} /> : <CheckCircle2 className={`w-5 h-5 ${iconColor}`} />}
                                                </div>
                                                <div className="space-y-1.5 w-full">
                                                    <div className="flex justify-between items-start gap-2">
                                                        <p className="text-[11px] font-black uppercase tracking-widest" style={{ color: isError ? '#ef4444' : 'var(--color-primario)' }}>
                                                            {n.data.cliente || 'Sistema Global'}
                                                        </p>
                                                        <span className="text-[9px] font-bold theme-text-muted uppercase shrink-0">
                                                            {n.created_at} {/* Idealmente formatea esto a "Hace 5 min" */}
                                                        </span>
                                                    </div>
                                                    <p className="text-xs font-bold theme-text-main leading-snug">
                                                        {n.data.mensaje || 'Nueva actividad en el sistema'}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    )
                                })
                            ) : (
                                <div className="h-full flex flex-col items-center justify-center space-y-4 opacity-50">
                                    <Bell className="w-12 h-12 theme-text-muted" />
                                    <p className="text-[11px] font-black uppercase theme-text-muted italic tracking-widest text-center">Bandeja limpia_<br/>Sin novedades operativas</p>
                                </div>
                            )}
                        </div>

                        {/* Pie del Panel (Botón de marcar todas) */}
                        {notifications.length > 0 && (
                            <div className="p-4 border-t theme-border shrink-0 bg-black/5 dark:bg-white/5">
                                <button className="w-full py-4 text-[10px] font-black uppercase tracking-[0.2em] rounded-xl text-white transition-transform hover:scale-105 outline-none flex items-center justify-center gap-2 shadow-md" style={{ backgroundColor: 'var(--color-primario)' }}>
                                    <MailOpen className="w-4 h-4" /> Marcar como leídas
                                </button>
                            </div>
                        )}
                    </div>
                </div>,
                document.body
            )}
            
            <style>{`
                @keyframes slide-in-right {
                    0% { transform: translateX(100%); }
                    100% { transform: translateX(0); }
                }
                .animate-slide-in-right {
                    animation: slide-in-right 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                }
            `}</style>
        </>
    );
}