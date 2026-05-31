import React, { useState, useEffect } from 'react';
import { createPortal } from 'react-dom';
import { Bell, BellRing, CheckCircle2, AlertCircle, X, MailOpen } from 'lucide-react';
import { usePage, router } from '@inertiajs/react';
import NotificationBrowserService from '@/Services/NotificationBrowserService';
import { ALERTAS_TIPOS } from '@/utils/alertasPrefs';

const ordenarPorFecha = (lista) =>
    [...lista].sort((a, b) => {
        const fechaA = new Date(a.created_at || 0).getTime();
        const fechaB = new Date(b.created_at || 0).getTime();
        return fechaB - fechaA;
    });

export function NotificationCountBadge({ count, className = '-top-1.5 -right-1.5' }) {
    if (!count || count <= 0) return null;

    return (
        <span
            className={`absolute z-30 flex items-center justify-center min-w-[1.125rem] h-[1.125rem] px-1 text-[9px] font-black leading-none text-white rounded-full shadow-md border-2 theme-surface pointer-events-none ${className}`}
            style={{ backgroundColor: 'var(--color-primario)' }}
            aria-hidden="true"
        >
            {count > 9 ? '9+' : count}
        </span>
    );
}

export default function NotificationBell({ notifications: propNotifications = [], iconButtonClassName = '' }) {
    const { auth } = usePage().props;
    const [isOpen, setIsOpen] = useState(false);

    const fuenteServidor = auth?.notificaciones?.length > 0
        ? auth.notificaciones
        : propNotifications;

    const [notifications, setNotifications] = useState(() => ordenarPorFecha(fuenteServidor || []));

    const unreadCount = notifications.filter(n => !n.read_at).length;

    useEffect(() => {
        if (isOpen) document.body.style.overflow = 'hidden';
        else document.body.style.overflow = 'unset';
        return () => { document.body.style.overflow = 'unset'; };
    }, [isOpen]);

    useEffect(() => {
        const base = auth?.notificaciones?.length > 0 ? auth.notificaciones : propNotifications;

        setNotifications(prev => {
            const registroMap = new Map();
            (base || []).forEach(n => { if (n?.id) registroMap.set(n.id, n); });
            prev.forEach(n => { if (n?.id && !registroMap.has(n.id)) registroMap.set(n.id, n); });
            return ordenarPorFecha(Array.from(registroMap.values()));
        });
    }, [auth?.notificaciones, propNotifications]);

    useEffect(() => {
        const handleNotificationReceived = (event) => {
            const notification = event.detail;
            const entryId = notification?.id
                || (notification?.activo_id
                    ? `live-activo-${notification.activo_id}-${notification.tipo || 'evt'}-${Date.now()}`
                    : notification?.solicitud_id
                    ? `live-${notification.solicitud_id}-${notification.tipo || 'evt'}-${Date.now()}`
                    : null);
            if (!entryId) return;

            setNotifications(prev => {
                if (prev.some(n => n.id === entryId)) return prev;
                return ordenarPorFecha([
                    {
                        id: entryId,
                        data: notification,
                        read_at: null,
                        created_at: new Date().toISOString(),
                        type: notification.tipo || notification.type,
                    },
                    ...prev,
                ]);
            });
        };

        window.addEventListener('notification-received', handleNotificationReceived);
        return () => window.removeEventListener('notification-received', handleNotificationReceived);
    }, []);

    const handleNotificationClick = (n) => {
        let destino = '/solicitudes';

        if (n.data?.activo_id) {
            destino = `/activos/${n.data.activo_id}`;
        } else if (n.data?.solicitud_id) {
            destino = `/solicitudes?folio=${n.data.solicitud_id}`;
        }

        router.visit(destino);
        setIsOpen(false);
    };

    const handleLimpiarBandeja = () => {
        router.post(route('notifications.clear'), {}, {
            preserveScroll: true,
            onSuccess: () => {
                setNotifications([]);
                setIsOpen(false);
            },
        });
    };

    const handleOpenDrawer = () => {
        setIsOpen(true);
        NotificationBrowserService.requestDesktopPermissions();
    };

    const formatearFecha = (fecha) => {
        if (!fecha || fecha === 'Ahora mismo') return 'Recién';
        try {
            return new Date(fecha).toLocaleString('es-MX', {
                day: '2-digit', month: 'short', hour: 'numeric', minute: '2-digit', hour12: true,
            });
        } catch {
            return fecha;
        }
    };

    return (
        <>
            <button
                type="button"
                onClick={handleOpenDrawer}
                className={`relative overflow-visible theme-element border theme-border rounded-2xl hover:border-[var(--color-primario)] transition-all group outline-none shrink-0 ${iconButtonClassName || 'p-3'}`}
            >
                {unreadCount > 0 ? (
                    <BellRing className="w-5 h-5 animate-pulse" style={{ color: 'var(--color-primario)' }} />
                ) : (
                    <Bell className="w-5 h-5 theme-text-muted group-hover:text-[var(--color-primario)] transition-colors" />
                )}
                <NotificationCountBadge count={unreadCount} />
            </button>

            {isOpen && createPortal(
                <div className="fixed inset-0 z-[9999] flex justify-end">
                    <div className="absolute inset-0 bg-black/60 backdrop-blur-sm animate-fade-in" onClick={() => setIsOpen(false)} />
                    <div className="relative w-full max-w-md theme-surface border-l theme-border shadow-2xl h-full flex flex-col animate-slide-in-right">
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

                        <div className="flex-1 overflow-y-auto custom-scrollbar p-4 space-y-3">
                            {notifications.length > 0 ? notifications.map((n, i) => {
                                const tipo = n.type || n.data?.tipo;
                                const esResumen = n.data?.total_vencidos !== undefined || n.data?.total_activos !== undefined;
                                const isError = tipo === 'pago_rechazado' || tipo === 'rechazada' || tipo === 'alerta_pago_insuficiente' || tipo === 'cancelada' || tipo === 'activo_baja' || tipo === 'activo_vencimiento' || esResumen;
                                const iconColor = isError ? 'text-red-500' : 'text-[var(--color-primario)]';
                                const bgClase = n.read_at
                                    ? 'theme-surface opacity-60 border-transparent shadow-none'
                                    : 'theme-element border-[var(--color-primario)] shadow-lg';
                                const etiqueta = ALERTAS_TIPOS[tipo] || (esResumen ? ALERTAS_TIPOS.resumen_vencidos : 'Sistema');
                                const tituloProceso = n.data?.titulo || n.data?.proceso || etiqueta;
                                const textoVisible = n.data?.mensaje_visible || n.data?.mensaje || 'Nueva actividad en el sistema';

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
                                                    <div>
                                                        <span className="text-[9px] font-black uppercase tracking-widest px-2 py-0.5 rounded-md bg-black/5 dark:bg-white/10 theme-text-muted">
                                                            {etiqueta}
                                                        </span>
                                                        <p className="text-[11px] font-black uppercase tracking-widest mt-1" style={{ color: isError ? '#ef4444' : 'var(--color-primario)' }}>
                                                            {tituloProceso}
                                                        </p>
                                                    </div>
                                                    <span className="text-[9px] font-bold theme-text-muted uppercase shrink-0">
                                                        {formatearFecha(n.created_at)}
                                                    </span>
                                                </div>
                                                <p className="text-xs font-bold theme-text-main leading-snug line-clamp-2">
                                                    {textoVisible}
                                                </p>
                                                {n.data?.activo_id && (
                                                    <span className="inline-block text-[9px] font-black uppercase px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 mt-1">
                                                        {n.data?.folio || `ACT-${n.data.activo_id}`}
                                                    </span>
                                                )}
                                                {n.data?.solicitud_id && (
                                                    <span className="inline-block text-[9px] font-black uppercase px-2 py-0.5 rounded bg-purple-500/10 text-purple-500 border border-purple-500/20 mt-1">
                                                        FOL-{n.data.solicitud_id}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                );
                            }) : (
                                <div className="h-full flex flex-col items-center justify-center space-y-4 opacity-50">
                                    <Bell className="w-12 h-12 theme-text-muted" />
                                    <p className="text-[11px] font-black uppercase theme-text-muted italic tracking-widest text-center">Bandeja limpia_<br />Sin novedades operativas</p>
                                </div>
                            )}
                        </div>

                        {notifications.length > 0 && (
                            <div className="p-4 border-t theme-border shrink-0 bg-black/5 dark:bg-white/5">
                                <button
                                    onClick={handleLimpiarBandeja}
                                    className="w-full py-4 text-[10px] font-black uppercase tracking-[0.2em] rounded-xl text-white transition-transform hover:scale-105 outline-none flex items-center justify-center gap-2 shadow-md"
                                    style={{ backgroundColor: 'var(--color-primario)' }}
                                >
                                    <MailOpen className="w-4 h-4" /> Limpiar Bandeja
                                </button>
                            </div>
                        )}
                    </div>
                </div>,
                document.body
            )}

            <style>{`
                @keyframes slide-in-right { 0% { transform: translateX(100%); } 100% { transform: translateX(0); } }
                .animate-slide-in-right { animation: slide-in-right 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
                .custom-scrollbar::-webkit-scrollbar { width: 4px; }
                .custom-scrollbar::-webkit-scrollbar-thumb { background: var(--color-primario); border-radius: 10px; }
            `}</style>
        </>
    );
}
