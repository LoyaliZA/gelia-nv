import React, { useState } from 'react';
import { Bell, BellRing, Clock, CheckCircle2, AlertCircle, X } from 'lucide-react';
import { animate } from 'animejs';

export default function NotificationBell({ notifications = [] }) {
    const [isOpen, setIsOpen] = useState(false);
    
    // Contar solo las no leídas (read_at es null en la BD)
    const unreadCount = notifications.filter(n => !n.read_at).length;

    const toggleMenu = () => {
        setIsOpen(!isOpen);
        if (!isOpen) {
            setTimeout(() => {
                animate('.notif-item', {
                    opacity: [0, 1],
                    translateX: [10, 0],
                    delay: (el, i) => i * 50,
                    easing: 'easeOutExpo'
                });
            }, 10);
        }
    };

    return (
        <div className="relative">
            <button 
                onClick={toggleMenu}
                className="relative p-3 theme-element border-2 theme-border rounded-2xl hover:border-pink-500 transition-all group"
            >
                {unreadCount > 0 ? (
                    <BellRing className="w-5 h-5 text-pink-500 animate-pulse" />
                ) : (
                    <Bell className="w-5 h-5 theme-text-muted group-hover:text-pink-500" />
                )}
                
                {unreadCount > 0 && (
                    <span className="absolute -top-1 -right-1 w-5 h-5 bg-pink-600 text-white text-[10px] font-black rounded-full flex items-center justify-center border-2 theme-surface">
                        {unreadCount}
                    </span>
                )}
            </button>

            {isOpen && (
                <>
                    <div className="fixed inset-0 z-40" onClick={() => setIsOpen(false)}></div>
                    <div className="absolute right-0 mt-4 w-80 theme-surface border-2 theme-border rounded-[2rem] shadow-2xl z-50 overflow-hidden tab-content">
                        <div className="p-6 border-b theme-border flex justify-between items-center bg-zinc-50/50 dark:bg-zinc-900/50">
                            <h3 className="text-xs font-black uppercase tracking-widest theme-text-main italic">Centro de Alertas_</h3>
                            <button onClick={() => setIsOpen(false)}><X className="w-4 h-4 theme-text-muted" /></button>
                        </div>

                        <div className="max-h-[400px] overflow-y-auto p-2">
                            {notifications.length > 0 ? (
                                notifications.map((n, i) => (
                                    <div key={i} className="notif-item p-4 hover:bg-zinc-100 dark:hover:bg-zinc-800/50 rounded-2xl transition-colors cursor-pointer border-b last:border-0 theme-border group">
                                        <div className="flex gap-4">
                                            <div className="mt-1">
                                                {n.type.includes('Solicitud') ? <Clock className="w-4 h-4 text-amber-500" /> : <CheckCircle2 className="w-4 h-4 text-emerald-500" />}
                                            </div>
                                            <div className="space-y-1">
                                                <p className="text-[11px] font-bold theme-text-main leading-tight group-hover:text-pink-500 transition-colors">
                                                    {n.data.mensaje || 'Nueva actividad en el sistema'}
                                                </p>
                                                <p className="text-[9px] font-black uppercase theme-text-muted italic tracking-tighter">
                                                    {n.created_at}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <div className="p-10 text-center space-y-3">
                                    <AlertCircle className="w-8 h-8 theme-text-muted mx-auto opacity-20" />
                                    <p className="text-[10px] font-black uppercase theme-text-muted italic">Sin notificaciones nuevas_</p>
                                </div>
                            )}
                        </div>

                        {notifications.length > 0 && (
                            <button className="w-full py-4 text-[9px] font-black uppercase tracking-[0.2em] text-pink-500 bg-pink-500/5 hover:bg-pink-500/10 transition-colors">
                                Marcar todas como leídas
                            </button>
                        )}
                    </div>
                </>
            )}
        </div>
    );
}