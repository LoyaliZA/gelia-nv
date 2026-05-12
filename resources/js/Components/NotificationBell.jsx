import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { Bell, BellRing, Clock, CheckCircle2, AlertCircle, X, MailOpen } from 'lucide-react';
import { usePage, router } from '@inertiajs/react';
// Servicio para audio y notificaciones nativas de escritorio
import NotificationBrowserService from '@/Services/NotificationBrowserService';

/**
 * COMPONENTE: NotificationBell
 * DESCRIPCIÓN: Gestiona la bandeja de notificaciones en tiempo real, 
 * persistencia en BD y navegación directa a solicitudes.
 */
export default function NotificationBell({ notifications: propNotifications = [] }) {
    // SECCIÓN: Estado y Contexto
    const { auth } = usePage().props;
    const [isOpen, setIsOpen] = useState(false);
    const [notifications, setNotifications] = useState(propNotifications);
    
    // Contamos solo las que no tienen fecha de lectura en la BD
    const unreadCount = notifications.filter(n => !n.read_at).length;

    // SECCIÓN: Sincronización de Datos
    useEffect(() => {
        setNotifications(propNotifications);
    }, [propNotifications]);

    // SECCIÓN: Bloqueo de Scroll (UX)
    useEffect(() => {
        if (isOpen) document.body.style.overflow = 'hidden';
        else document.body.style.overflow = 'unset';
        return () => { document.body.style.overflow = 'unset'; };
    }, [isOpen]);

    // SECCIÓN: Escucha de WebSockets (Real-Time)
    useEffect(() => {
        if (auth && auth.user && typeof window !== 'undefined' && window.Echo) {
            
            // Suscripción al canal privado del usuario en Reverb
            const channel = window.Echo.private(`App.Models.User.${auth.user.id}`);
            
            channel.notification((notification) => {
                // 1. Alerta sónica y de escritorio
                NotificationBrowserService.triggerFullAlert(
                    "Gelia ERP",
                    notification.mensaje || "Nueva notificación operativa."
                );

                // 2. Inyectar la notificación al inicio de la lista local
                setNotifications(prev => [
                    {
                        id: notification.id,
                        data: notification,
                        read_at: null,
                        created_at: 'Ahora mismo',
                        type: notification.tipo
                    },
                    ...prev
                ]);
            });

            return () => {
                window.Echo.leave(`App.Models.User.${auth.user.id}`);
            };
        }
    }, [auth]);

    // SECCIÓN: Lógica de Interacción (Click y Navegación)
    const handleNotificationClick = (n) => {
        const destino = `/solicitudes?folio=${n.data?.solicitud_id || ''}`;

        // Si ya está leída, solo navegamos directamente
        if (n.read_at) {
            if (n.data?.solicitud_id) {
                router.visit(destino);
                setIsOpen(false);
            }
            return;
        }

        // Si es nueva, la marcamos en la base de datos y luego navegamos
        router.post(route('notifications.read', n.id), {}, {
            preserveScroll: true,
            onSuccess: () => {
                if (n.data?.solicitud_id) {
                    router.visit(destino);
                    setIsOpen(false);
                }
            }
        });
    };

    const handleOpenDrawer = () => {
        setIsOpen(true);
        // Desbloqueamos el permiso de audio/notificaciones en la primera interacción
        NotificationBrowserService.requestDesktopPermissions();
    };

    // SECCIÓN: Renderizado
    return (
        <>
            {/* --- ACTIVADOR (CAMPANA) --- */}
            <button 
                onClick={handleOpenDrawer}
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

            {/* --- BANDEJA LATERAL (PORTAL) --- */}
            {isOpen && createPortal(
                <div className="fixed inset-0 z-[9999] flex justify-end">
                    {/* Overlay de fondo */}
                    <div className="absolute inset-0 bg-black/60 backdrop-blur-sm animate-fade-in" onClick={() => setIsOpen(false)}></div>
                    
                    {/* Contenedor del Panel */}
                    <div className="relative w-full max-w-md theme-surface border-l theme-border shadow-2xl h-full flex flex-col animate-slide-in-right">
                        
                        {/* Cabecera de Bandeja */}
                        <div className="p-6 md:p-8 border-b theme-border flex justify-between items-center bg-black/5 dark:bg-white/5 shrink-0">
                            <div className="flex items-center gap-3">
                                <div className="p-2 rounded-xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                                    <BellRing className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                                </div>
                                <div>
                                    <h3 className="text-lg font-black uppercase tracking-tighter theme-text-main italic m-0">Centro de Alertas_</h3>
                                    <p className="text-[10px] font-bold theme-text-muted uppercase tracking-widest mt-0.5">{unreadCount} por revisar</p>
                                </div>
                            </div>
                            <button onClick={() => setIsOpen(false)} className="p-2 theme-text-muted hover:theme-text-main bg-white/10 rounded-full transition-transform hover:scale-110 outline-none">
                                <X className="w-5 h-5" />
                            </button>
                        </div>

                        {/* Listado de Notificaciones */}
                        <div className="flex-1 overflow-y-auto custom-scrollbar p-4 space-y-3">
                            {notifications.length > 0 ? (
                                notifications.map((n, i) => {
                                    const tipo = n.type || n.data?.tipo;
                                    const isError = tipo === 'pago_rechazado' || tipo === 'rechazada';
                                    const iconColor = isError ? 'text-red-500' : 'text-[var(--color-primario)]';
                                    
                                    // Efecto visual: Las leídas son más tenues y sin borde de resalte
                                    const bgClase = n.read_at 
                                        ? 'theme-surface opacity-60 border-transparent shadow-none' 
                                        : 'theme-element border-[var(--color-primario)] shadow-lg';

                                    return (
                                        <div 
                                            key={n.id || i} 
                                            onClick={() => handleNotificationClick(n)}
                                            className={`p-5 rounded-2xl transition-all cursor-pointer group border ${bgClase} hover:scale-[1.02] active:scale-95`}
                                        >
                                            <div className="flex gap-4">
                                                <div className="mt-1 shrink-0">
                                                    {isError ? <AlertCircle className={`w-5 h-5 ${iconColor}`} /> : <CheckCircle2 className={`w-5 h-5 ${iconColor}`} />}
                                                </div>
                                                <div className="space-y-1.5 w-full">
                                                    <div className="flex justify-between items-start gap-2">
                                                        <p className="text-[11px] font-black uppercase tracking-widest" style={{ color: isError ? '#ef4444' : 'var(--color-primario)' }}>
                                                            {n.data?.cliente || 'Sistema Global'}
                                                        </p>
                                                        <span className="text-[9px] font-bold theme-text-muted uppercase shrink-0">
                                                            {n.created_at} 
                                                        </span>
                                                    </div>
                                                    <p className="text-xs font-bold theme-text-main leading-snug">
                                                        {n.data?.mensaje || 'Nueva actividad en el sistema'}
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

                        {/* Acciones Globales */}
                        {notifications.length > 0 && (
                            <div className="p-4 border-t theme-border shrink-0 bg-black/5 dark:bg-white/5">
                                <button className="w-full py-4 text-[10px] font-black uppercase tracking-[0.2em] rounded-xl text-white transition-transform hover:scale-105 outline-none flex items-center justify-center gap-2 shadow-md" style={{ backgroundColor: 'var(--color-primario)' }}>
                                    <MailOpen className="w-4 h-4" /> Limpiar Bandeja
                                </button>
                            </div>
                        )}
                    </div>
                </div>,
                document.body
            )}
            
            {/* SECCIÓN: Animaciones Locales */}
            <style>{`
                @keyframes slide-in-right {
                    0% { transform: translateX(100%); }
                    100% { transform: translateX(0); }
                }
                .animate-slide-in-right {
                    animation: slide-in-right 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
                }
                .custom-scrollbar::-webkit-scrollbar { width: 4px; }
                .custom-scrollbar::-webkit-scrollbar-thumb { background: var(--color-primario); border-radius: 10px; }
            `}</style>
        </>
    );
}