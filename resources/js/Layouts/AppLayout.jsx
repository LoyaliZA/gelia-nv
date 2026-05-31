import React, { useEffect, useState, createContext, useContext } from 'react';
import { usePage, router } from '@inertiajs/react';
import Sidebar from '../Components/Sidebar';
// ELIMINADO: import { animate } from 'animejs/animation';
import { Bell, X } from 'lucide-react';

import NotificationService from '../Services/NotificationBrowserService';
import GeliaLoader from '../Components/GeliaLoader';
import {
    resolveAlertasPrefs,
    getTipoAlerta,
    shouldTriggerChannel,
    MENSAJERIA_TIPO_ALERTA,
} from '../utils/alertasPrefs';
import {
    notificarMensajeNuevo,
    notificarMensajeLeido,
    abrirConversacionDesdeNotificacion,
} from '../utils/mensajeriaNotificaciones';
import {
    clampFontScale,
    FONT_SCALE_DEFAULT,
    FONT_SCALE_STORAGE_KEY,
    applyFontScaleToRoot,
} from '../utils/fontScale';

const ModalContext = createContext();
export const useModal = () => useContext(ModalContext);

export default function AppLayout({ children, fullScreen = false }) {
    const { props: { auth, tonos_alertas = [] }, url } = usePage();

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [modalContent, setModalContent] = useState(null);

    // ESTADO PARA TOASTS
    const [toasts, setToasts] = useState([]);

    // --- 2. ESTADOS GLOBALES PARA EL LOADER ---
    const [isGlobalLoading, setIsGlobalLoading] = useState(false);
    const [globalProgress, setGlobalProgress] = useState(null);

    const addToast = (notif) => {
        const id = Date.now();
        setToasts(prev => [...prev, { id, ...notif }]);
        setTimeout(() => {
            setToasts(prev => prev.filter(t => t.id !== id));
        }, 5000);
    };

    // INICIALIZAR PERMISOS Y PREFERENCIAS DE ALERTAS
    useEffect(() => {
        NotificationService.requestDesktopPermissions();

        const syncPrefs = () => {
            const prefs = resolveAlertasPrefs(auth);
            NotificationService.setTonosCatalog(tonos_alertas);
            NotificationService.setPreferences(prefs);
        };

        syncPrefs();

        const onPrefsChanged = (event) => {
            NotificationService.setPreferences(event.detail);
        };

        window.addEventListener('alertas-prefs-changed', onPrefsChanged);
        return () => window.removeEventListener('alertas-prefs-changed', onPrefsChanged);
    }, [auth?.tema_visual?.alertas_prefs, tonos_alertas, auth]);

    // --- ESCUCHADORES DE EVENTOS GLOBALES DE INERTIA ---
    useEffect(() => {
        const removeStart = router.on('start', (event) => {
            // REGLA: Si la petición indica showProgress: false, ignoramos el loader
            if (event.detail.visit.showProgress === false) return;
            
            setIsGlobalLoading(true);
            setGlobalProgress(null);
        });

        const removeProgress = router.on('progress', (event) => {
            if (event.detail.visit.showProgress === false) return;
            
            if (event.detail.progress && event.detail.progress.percentage) {
                setGlobalProgress(event.detail.progress.percentage);
            }
        });

        const removeFinish = router.on('finish', () => {
            // Siempre apagamos por seguridad al terminar cualquier ciclo
            setIsGlobalLoading(false);
            setGlobalProgress(null);
        });

        return () => {
            removeStart();
            removeProgress();
            removeFinish();
        };
    }, []);

    // REFACTORIZACIÓN DEL ESCUCHADOR DE REVERB
    useEffect(() => {
        if (auth?.user && typeof window !== 'undefined' && window.Echo) {
            const channelName = `App.Models.User.${auth.user.id}`;
            
            window.Echo.leave(channelName);

            window.Echo.private(channelName)
                .notification((notification) => {
                    const prefs = resolveAlertasPrefs(auth);
                    const tipo = getTipoAlerta(notification);

                    if (shouldTriggerChannel(prefs, tipo, 'app')) {
                        const tituloToast = notification.titulo || notification.proceso || 'GELIA ERP';
                        const texto = notification.mensaje_visible || notification.mensaje || 'Nueva actividad';
                        addToast({ mensaje: `${tituloToast} — ${texto}` });
                    }

                    const sonido = shouldTriggerChannel(prefs, tipo, 'sonido');
                    const voz = shouldTriggerChannel(prefs, tipo, 'voz');
                    const escritorio = shouldTriggerChannel(prefs, tipo, 'escritorio');

                    if (sonido || voz || escritorio) {
                        NotificationService.triggerFullAlert(
                            notification.titulo || notification.proceso || 'GELIA ERP',
                            notification.mensaje_visible || notification.mensaje || 'Nueva notificación operativa.',
                            notification.mensaje_voz,
                            { sonido, voz, escritorio }
                        );
                    }

                    window.dispatchEvent(new CustomEvent('notification-received', { detail: notification }));

                    router.reload({ only: ['auth'], preserveScroll: true, preserveState: true });
                })
                .listen('.mensaje.leido', (event) => {
                    const mensaje = event?.mensaje;
                    if (!mensaje) return;
                    notificarMensajeLeido(mensaje, auth);
                })
                .listen('.presencia.contacto', (event) => {
                    const presencia = event?.presencia;
                    if (!presencia) return;
                    window.dispatchEvent(new CustomEvent('gelia-presencia-contacto', { detail: presencia }));
                })
                .listen('.presencia.actualizada', (event) => {
                    const presencia = event?.presencia;
                    if (!presencia) return;
                    window.dispatchEvent(new CustomEvent('gelia-presencia-propia', { detail: presencia }));
                })
                .listen('.mensaje.recibido', (event) => {
                    const mensaje = event?.mensaje;
                    if (!mensaje) return;

                    const prefs = resolveAlertasPrefs(auth);
                    if (shouldTriggerChannel(prefs, MENSAJERIA_TIPO_ALERTA, 'app')) {
                        const nombre = mensaje.user?.name || 'Contacto';
                        const texto = mensaje.contenido || 'Nuevo mensaje';
                        addToast({
                            mensaje: `${nombre} — ${texto}`,
                            conversacionId: mensaje.conversacion_id,
                        });
                    }

                    notificarMensajeNuevo(mensaje, auth);

                    router.reload({
                        only: ['auth'],
                        preserveScroll: true,
                        preserveState: true,
                        showProgress: false,
                    });
                });
        }

        return () => {
            if (auth?.user && typeof window !== 'undefined' && window.Echo) {
                window.Echo.leave(`App.Models.User.${auth.user.id}`);
            }
        };
    }, [auth?.user]);

    const accentColors = {
        rosa: '#ec4899',
        azul: '#3b82f6',
        verde: '#10b981',
        amarillo: '#f59e0b'
    };

    const [isDarkMode, setIsDarkMode] = useState(() => {
        if (typeof window !== 'undefined') {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) return savedTheme === 'dark';
        }
        return auth?.tema_visual?.modo === 'dark';
    });

    const [sidebarLayout, setSidebarLayout] = useState(() => {
        if (typeof window !== 'undefined') return localStorage.getItem('theme_layout') || auth?.tema_visual?.layout_sidebar || 'floating_left';
        return 'floating_left';
    });

    const [sidebarMode, setSidebarMode] = useState(() => {
        if (typeof window !== 'undefined') return localStorage.getItem('theme_sidebar_mode') || auth?.tema_visual?.sidebar_modo || 'collapsed';
        return 'collapsed';
    });

    const [fixedPosition, setFixedPosition] = useState(() => {
        if (typeof window !== 'undefined') return localStorage.getItem('theme_fixed_position') || auth?.tema_visual?.sidebar_posicion_fija || 'left';
        return 'left';
    });

    const isMensajeriaFull = fullScreen || url.startsWith('/mensajeria');

    const [isMobileViewport, setIsMobileViewport] = useState(() => {
        if (typeof window === 'undefined') return false;
        return window.innerWidth < 768;
    });

    useEffect(() => {
        const onResize = () => setIsMobileViewport(window.innerWidth < 768);
        window.addEventListener('resize', onResize);
        return () => window.removeEventListener('resize', onResize);
    }, []);

    const mensajeriaImmersivaMovil = isMensajeriaFull && isMobileViewport;

    const getMensajeriaLayoutClasses = () => {
        const base = 'gelia-mensajeria-main box-border w-full max-w-none p-0 overflow-hidden h-dvh max-h-dvh';

        if (mensajeriaImmersivaMovil) {
            return `${base} gelia-mensajeria-main--immersive`;
        }

        if (sidebarLayout !== 'fixed') {
            if (sidebarLayout === 'floating_right') {
                return `${base} gelia-mensajeria-main--float gelia-mensajeria-main--float-right`;
            }
            return `${base} gelia-mensajeria-main--float gelia-mensajeria-main--float-left`;
        }

        const edge = ['left', 'right', 'top', 'bottom'].includes(fixedPosition) ? fixedPosition : 'left';
        return `${base} gelia-mensajeria-main--fixed gelia-mensajeria-main--fixed-${edge}`;
    };

    const getMainLayoutClasses = () => {
        if (isMensajeriaFull) {
            return getMensajeriaLayoutClasses();
        }
        if (sidebarLayout !== 'fixed') {
            return 'pt-6 md:pt-24';
        }
        switch (fixedPosition) {
            case 'right':
                return 'md:mr-[5.5rem] pt-6 md:pt-12';
            case 'top':
                return 'pt-[4.75rem] md:pt-20 pb-32 md:pb-20';
            case 'bottom':
                return 'pt-6 md:pt-12 pb-28 md:pb-24';
            default:
                return 'md:ml-[5.5rem] pt-6 md:pt-12';
        }
    };

    const openModal = (content) => {
        setModalContent(content);
        setIsModalOpen(true);
    };

    const closeModal = () => {
        setIsModalOpen(false);
        setTimeout(() => setModalContent(null), 300);
    };

    useEffect(() => {
        if (isModalOpen) document.body.style.overflow = 'hidden';
        else document.body.style.overflow = 'unset';
        return () => { document.body.style.overflow = 'unset'; };
    }, [isModalOpen]);

    useEffect(() => {
        const syncThemeState = () => {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme) setIsDarkMode(savedTheme === 'dark');

            const savedLayout = localStorage.getItem('theme_layout')
                || auth?.tema_visual?.layout_sidebar
                || 'floating_left';
            setSidebarLayout(savedLayout);

            const savedSidebarMode = localStorage.getItem('theme_sidebar_mode')
                || auth?.tema_visual?.sidebar_modo
                || 'collapsed';
            setSidebarMode(savedSidebarMode);

            const savedFixedPosition = localStorage.getItem('theme_fixed_position')
                || auth?.tema_visual?.sidebar_posicion_fija
                || 'left';
            setFixedPosition(savedFixedPosition);
        };

        const onLayoutPreview = (event) => {
            if (event.detail?.layout) setSidebarLayout(event.detail.layout);
        };

        const onSidebarModePreview = (event) => {
            if (event.detail?.mode) setSidebarMode(event.detail.mode);
        };

        const onFixedPositionPreview = (event) => {
            if (event.detail?.position) setFixedPosition(event.detail.position);
        };

        window.addEventListener('theme-changed', syncThemeState);
        window.addEventListener('theme-layout-preview', onLayoutPreview);
        window.addEventListener('theme-sidebar-mode-preview', onSidebarModePreview);
        window.addEventListener('theme-fixed-position-preview', onFixedPositionPreview);
        return () => {
            window.removeEventListener('theme-changed', syncThemeState);
            window.removeEventListener('theme-layout-preview', onLayoutPreview);
            window.removeEventListener('theme-sidebar-mode-preview', onSidebarModePreview);
            window.removeEventListener('theme-fixed-position-preview', onFixedPositionPreview);
        };
    }, [auth?.tema_visual?.layout_sidebar, auth?.tema_visual?.sidebar_modo, auth?.tema_visual?.sidebar_posicion_fija]);

    useEffect(() => {
        const layout = localStorage.getItem('theme_layout')
            || auth?.tema_visual?.layout_sidebar
            || 'floating_left';
        setSidebarLayout(layout);

        const mode = localStorage.getItem('theme_sidebar_mode')
            || auth?.tema_visual?.sidebar_modo
            || 'collapsed';
        setSidebarMode(mode);

        const position = localStorage.getItem('theme_fixed_position')
            || auth?.tema_visual?.sidebar_posicion_fija
            || 'left';
        setFixedPosition(position);
    }, [url, auth?.tema_visual?.layout_sidebar, auth?.tema_visual?.sidebar_modo, auth?.tema_visual?.sidebar_posicion_fija]);

    useEffect(() => {
        const root = document.documentElement;
        const tema = auth?.tema_visual || {};

        const savedColorName = typeof window !== 'undefined' ? localStorage.getItem('theme_color') : null;
        const activeColorName = savedColorName || tema.color_nombre?.toLowerCase() || 'rosa';
        const activeAccent = activeColorName.startsWith('#') ? activeColorName : (accentColors[activeColorName] || accentColors.rosa);
        root.style.setProperty('--color-primario', activeAccent);

        if (isDarkMode) {
            root.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        } else {
            root.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        }

        const nombreFondo = (typeof window !== 'undefined' ? localStorage.getItem('bg_base') : null) || tema.fondo_base || 'none';
        if (nombreFondo === 'none') {
            root.style.setProperty('--bg-image-pc', 'none');
            root.style.setProperty('--bg-image-movil', 'none');
        } else if (nombreFondo.startsWith('#')) {
            root.style.setProperty('--bg-image-pc', `linear-gradient(to right, ${nombreFondo}, ${nombreFondo})`);
            root.style.setProperty('--bg-image-movil', `linear-gradient(to right, ${nombreFondo}, ${nombreFondo})`);
        } else if (nombreFondo.startsWith('data:image') || nombreFondo.startsWith('/storage')) {
            root.style.setProperty('--bg-image-pc', `url(${nombreFondo})`);
            root.style.setProperty('--bg-image-movil', `url(${nombreFondo})`);
        } else {
            root.style.setProperty('--bg-image-pc', `url('/assets/backgrounds/${nombreFondo}_pc.svg')`);
            root.style.setProperty('--bg-image-movil', `url('/assets/backgrounds/${nombreFondo}_movil.svg')`);
        }

        const savedFont = typeof window !== 'undefined' ? localStorage.getItem('theme_font') : null;
        const activeFont = savedFont || tema.fuente_principal || 'inter';
        const fontMap = {
            inter: "'Inter', sans-serif",
            montserrat: "'Montserrat', sans-serif",
            poppins: "'Poppins', sans-serif",
            nunito: "'Nunito', sans-serif",
            roboto: "'Roboto', sans-serif",
            mono: "'JetBrains Mono', monospace"
        };
        root.style.setProperty('--font-principal', fontMap[activeFont] || fontMap.inter);

        const savedScale = typeof window !== 'undefined' ? localStorage.getItem(FONT_SCALE_STORAGE_KEY) : null;
        const activeScale = clampFontScale(
            savedScale !== null && savedScale !== ''
                ? savedScale
                : (tema.escala_fuente ?? FONT_SCALE_DEFAULT)
        );
        applyFontScaleToRoot(activeScale);

        const savedGlass = typeof window !== 'undefined' ? localStorage.getItem('theme_glass') : null;
        const isGlassActive = savedGlass !== null
            ? savedGlass === 'true'
            : (String(tema.efecto_cristal) !== '0' && tema.efecto_cristal !== false);

        if (isGlassActive) root.classList.add('glass-active');
        else root.classList.remove('glass-active');

    }, [isDarkMode, auth?.tema_visual]);

    const toggleTheme = () => {
        const newMode = !isDarkMode;
        setIsDarkMode(newMode);
        localStorage.setItem('theme', newMode ? 'dark' : 'light');
        window.dispatchEvent(new Event('theme-changed'));
    };

    return (
        <ModalContext.Provider value={{ openModal, closeModal }}>
            <div
                className="min-h-dvh text-gray-950 dark:text-gray-100 transition-colors duration-500 w-full overflow-x-hidden"
                style={{
                    backgroundColor: 'var(--bg-app)',
                    backgroundImage: 'var(--bg-actual)',
                    backgroundSize: 'cover',
                    backgroundPosition: 'center',
                    backgroundAttachment: 'fixed',
                    backgroundRepeat: 'no-repeat',
                }}
            >
                {/* --- 4. INSTANCIA ÚNICA DEL LOADER --- */}
                <GeliaLoader 
                    isVisible={isGlobalLoading} 
                    progress={globalProgress} 
                    message="Procesando_" 
                />

                <Sidebar
                    isDarkMode={isDarkMode}
                    toggleTheme={toggleTheme}
                    user={auth?.user}
                    permissions={auth?.user?.permissions || []}
                    layout={sidebarLayout}
                    sidebarMode={sidebarMode}
                    fixedPosition={fixedPosition}
                />

                {/* Zoom solo en contenido: el sidebar fixed queda fuera y funciona igual en /perfil */}
                <div className={`gelia-ui-scale w-full ${isMensajeriaFull ? 'h-dvh overflow-hidden' : 'min-h-dvh'} ${mensajeriaImmersivaMovil ? 'gelia-mensajeria-immersive' : ''}`}>
                    <main className={`transition-all duration-500 bg-transparent mx-auto ${isMensajeriaFull ? 'max-w-none w-full h-full' : 'max-w-7xl min-h-screen px-4 md:px-6 pb-32 md:pb-20'} ${getMainLayoutClasses()} ${mensajeriaImmersivaMovil ? 'gelia-mensajeria-immersive-main' : ''}`}>
                        <div key={url} className={isMensajeriaFull ? 'h-full' : 'animate-page-reveal'}>
                            {children}
                        </div>
                    </main>

                    {/* MODAL GLOBAL */}
                    {isModalOpen && (
                        <div className="gelia-modal-overlay" onClick={closeModal}>
                            <div className="gelia-modal-shell max-w-lg p-6 modal-pop" onClick={(e) => e.stopPropagation()}>
                                {modalContent}
                            </div>
                        </div>
                    )}

                    {/* TOASTS FLOTANTES */}
                    <div className="fixed top-6 right-6 z-[10000] flex flex-col gap-3 w-full max-w-sm pointer-events-none">
                    {toasts.map((toast) => (
                        <div
                            key={toast.id}
                            className={`pointer-events-auto theme-surface theme-no-blur border theme-border shadow-2xl rounded-2xl p-4 flex items-start gap-4 animate-slide-in-right overflow-hidden relative group ${toast.conversacionId ? 'cursor-pointer hover:border-[var(--color-primario)] transition-colors' : ''}`}
                            onClick={toast.conversacionId ? () => {
                                abrirConversacionDesdeNotificacion(toast.conversacionId);
                                setToasts(prev => prev.filter(t => t.id !== toast.id));
                            } : undefined}
                            role={toast.conversacionId ? 'button' : undefined}
                        >
                            <div className="absolute bottom-0 left-0 h-1 bg-[var(--color-primario)] animate-progress-shrink" />
                            <div className="p-2 rounded-xl" style={{ backgroundColor: 'color-mix(in srgb, var(--color-primario) 15%, transparent)' }}>
                                <Bell className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                            </div>
                            <div className="flex-1">
                                <p className="text-[10px] font-black uppercase tracking-widest text-[var(--color-primario)] mb-0.5">Nueva Alerta_</p>
                                <p className="text-xs font-bold theme-text-main leading-snug">{toast.mensaje}</p>
                            </div>
                            <button
                                onClick={(e) => {
                                    e.stopPropagation();
                                    setToasts(prev => prev.filter(t => t.id !== toast.id));
                                }}
                                className="theme-text-muted hover:theme-text-main transition-colors outline-none"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        </div>
                    ))}
                    </div>
                </div>

            </div>
        </ModalContext.Provider>
    );
}