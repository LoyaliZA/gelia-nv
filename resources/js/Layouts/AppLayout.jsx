import React, { useEffect, useState, createContext, useContext, useCallback, useRef } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import Sidebar from '../Components/Sidebar';
import GeliaLogo from '../Components/GeliaLogo';
import NotificationBell from '../Components/NotificationBell';
import MensajeriaWidget from '../Components/Mensajeria/MensajeriaWidget';
import { Bell, X, Menu } from 'lucide-react';

import NotificationService from '../Services/NotificationBrowserService';
import GeliaLoader from '../Components/GeliaLoader';
import WooSyncFloatingTracker from '../Components/WooSyncFloatingTracker';
import ImportacionAlmacenFloatingTracker from '../Components/Almacenes/ImportacionAlmacenFloatingTracker';
import CobranzaReporteFloatingTracker from '../Components/CobranzaReporteFloatingTracker';
import {
    resolveAlertasPrefs,
    getTipoAlerta,
    normalizeNotificationPayload,
    resolveNotificationDestination,
    resolveNotificationVoiceMessage,
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
import {
    applyContentDensityToRoot,
    resolveContentDensity,
} from '../utils/contentDensity';
import useWebPush from '@/hooks/useWebPush';
import { GELIA_PREVENT_OVERFLOW_X } from '../utils/geliaTheme';
import { STORAGE_FILTROS_ACTIVOS } from '../Pages/Activos/Partials/navegarListadoActivos';

const ModalContext = createContext();
export const useModal = () => useContext(ModalContext);

// ponytail: move accentColors outside component body to prevent re-allocation
const accentColors = {
    rosa: '#ec4899',
    azul: '#3b82f6',
    verde: '#10b981',
    amarillo: '#f59e0b'
};

const MOBILE_SIDEBAR_LAYOUT_BOTTOM = 'mobile_bottom';
const MOBILE_SIDEBAR_LAYOUT_TOPBAR = 'mobile_topbar';

export default function AppLayout({ children, fullScreen = false }) {
    const { props: { auth, tonos_alertas = [] }, url } = usePage();

    useWebPush(auth, tonos_alertas);

    useEffect(() => {
        if (typeof window !== 'undefined' && !url.startsWith('/activos')) {
            sessionStorage.removeItem(STORAGE_FILTROS_ACTIVOS);
        }
    }, [url]);

    const [contentDensityMode, setContentDensityMode] = useState(() =>
        resolveContentDensity(auth?.tema_visual || {}).modo
    );
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

    const navigateFromNotification = useCallback((notification) => {
        router.visit(resolveNotificationDestination(notification));
    }, []);

    const handleToastClick = useCallback((toast) => {
        if (toast.conversacionId) {
            abrirConversacionDesdeNotificacion(toast.conversacionId);
        } else if (toast.notification) {
            navigateFromNotification(toast.notification);
        } else {
            return;
        }

        setToasts(prev => prev.filter(t => t.id !== toast.id));
    }, [navigateFromNotification]);

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

    useEffect(() => {
        const onGeliaToast = (event) => {
            const { mensaje, tipo } = event.detail ?? {};
            if (mensaje) {
                addToast({ mensaje, tipo });
            }
        };

        window.addEventListener('gelia-toast', onGeliaToast);
        return () => window.removeEventListener('gelia-toast', onGeliaToast);
    }, []);

    const permisosWoo = auth?.user?.permissions ?? [];
    const rolesWoo = auth?.user?.roles ?? [];
    const esSuperAdmin = rolesWoo.includes('Super Admin') || rolesWoo.includes('Administrador');
    const canViewWooSync = esSuperAdmin || permisosWoo.includes('woocommerce.ver') || permisosWoo.includes('woocommerce.sincronizar');
    const canSyncWoo = esSuperAdmin || permisosWoo.includes('woocommerce.sincronizar');

    const permisosAlmacen = auth?.user?.permissions ?? [];
    const canViewImportacionAlmacen = esSuperAdmin
        || permisosAlmacen.includes('almacenes.productos.gestionar')
        || permisosAlmacen.includes('almacenes.inventarios.importar')
        || permisosAlmacen.includes('almacenes.costos.importar')
        || permisosAlmacen.includes('catalogos.gestionar');
    const canManageImportacionAlmacen = canViewImportacionAlmacen;

    const permisosCobranza = auth?.user?.permissions ?? [];
    const canViewCobranzaReportes = esSuperAdmin || permisosCobranza.includes('cobranza.reportes');

    // --- ESCUCHADORES DE EVENTOS GLOBALES DE INERTIA ---
    useEffect(() => {
        const removeStart = router.on('start', (event) => {
            // REGLA: Si la petición indica showProgress: false, ignoramos el loader
            if (event.detail.visit?.showProgress === false) return;
            
            setIsGlobalLoading(true);
            setGlobalProgress(null);
        });

        const removeProgress = router.on('progress', (event) => {
            if (event.detail.visit?.showProgress === false) return;
            
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
    const echoChannelRef = useRef(null);

    useEffect(() => {
        if (auth?.user && typeof window !== 'undefined' && window.Echo) {
            const channelName = `App.Models.User.${auth.user.id}`;

            if (echoChannelRef.current) {
                window.Echo.leave(echoChannelRef.current);
                echoChannelRef.current = null;
            }

            window.Echo.leave(channelName);

            const echoChannel = window.Echo.private(channelName);
            echoChannelRef.current = channelName;

            echoChannel
                .notification((notification) => {
                    const payload = normalizeNotificationPayload(notification);
                    const prefs = resolveAlertasPrefs(auth);
                    const tipo = getTipoAlerta(payload);

                    if (shouldTriggerChannel(prefs, tipo, 'app')) {
                        const tituloToast = payload.titulo || payload.proceso || 'GELIA ERP';
                        const texto = payload.mensaje_visible || payload.mensaje || 'Nueva actividad';
                        addToast({
                            mensaje: `${tituloToast} — ${texto}`,
                            notification: payload,
                        });
                    }

                    const onNotificationNavigate = () => navigateFromNotification(payload);

                    const sonido = shouldTriggerChannel(prefs, tipo, 'sonido');
                    const voz = shouldTriggerChannel(prefs, tipo, 'voz');
                    const escritorio = shouldTriggerChannel(prefs, tipo, 'escritorio');
                    const mensajeVoz = resolveNotificationVoiceMessage(payload, auth?.user, tipo);
                    const clientesVencidos = Array.isArray(payload.clientes_vencidos) ? payload.clientes_vencidos : [];

                    if (clientesVencidos.length > 0 && (sonido || voz || escritorio)) {
                        const nombre = (auth?.user?.name || 'Usuario').trim().split(/\s+/)[0];
                        const voiceMessages = voz && payload.tipo !== 'cobranza_vencimiento_masivo'
                            ? [
                                mensajeVoz || `Atención ${nombre}. Reporte de cobranza.`,
                                ...clientesVencidos.map(
                                    (c) => `Cliente ${c.nombre} tiene un saldo vencido hace ${c.dias_atraso} días.`
                                ),
                            ]
                            : null;

                        NotificationService.triggerFullAlert(
                            payload.titulo || payload.proceso || 'GELIA ERP',
                            payload.mensaje_visible || payload.mensaje || 'Nueva notificación operativa.',
                            voz && payload.tipo === 'cobranza_vencimiento_masivo' ? mensajeVoz : null,
                            {
                                sonido,
                                voz,
                                escritorio,
                                onClick: onNotificationNavigate,
                                voiceMessages,
                            }
                        );
                    } else if (sonido || voz || escritorio) {
                        NotificationService.triggerFullAlert(
                            payload.titulo || payload.proceso || 'GELIA ERP',
                            payload.mensaje_visible || payload.mensaje || 'Nueva notificación operativa.',
                            voz ? mensajeVoz : null,
                            { sonido, voz, escritorio, onClick: onNotificationNavigate }
                        );
                    }

                    window.dispatchEvent(new CustomEvent('notification-received', { detail: payload }));

                    window.setTimeout(() => {
                        router.reload({ only: ['auth'], preserveScroll: true, preserveState: true, showProgress: false });
                    }, 800);
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
                const channelName = echoChannelRef.current || `App.Models.User.${auth.user.id}`;
                window.Echo.leave(channelName);
                echoChannelRef.current = null;
            }
        };
    }, [auth?.user?.id, navigateFromNotification]);

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

    const [mobileSidebarLayout, setMobileSidebarLayout] = useState(() => {
        if (typeof window !== 'undefined') {
            return localStorage.getItem('theme_layout_mobile')
                || auth?.tema_visual?.layout_sidebar_mobile
                || MOBILE_SIDEBAR_LAYOUT_BOTTOM;
        }
        return MOBILE_SIDEBAR_LAYOUT_BOTTOM;
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
    const useMobileTopBar = isMobileViewport && mobileSidebarLayout === MOBILE_SIDEBAR_LAYOUT_TOPBAR;

    const shellSidebarLayout = isMobileViewport
        ? (useMobileTopBar ? 'mobile-topbar' : 'mobile-bottom')
        : sidebarLayout === 'fixed'
            ? 'fixed'
            : sidebarLayout === 'floating_right'
                ? 'float-right'
                : 'float-left';

    const shellSidebarEdge = ['left', 'right', 'top', 'bottom'].includes(fixedPosition)
        ? fixedPosition
        : 'left';

    const openMobileSidebar = useCallback(() => {
        window.dispatchEvent(new CustomEvent('gelia-sidebar-open-menu'));
    }, []);

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

            const savedMobileLayout = localStorage.getItem('theme_layout_mobile')
                || auth?.tema_visual?.layout_sidebar_mobile
                || MOBILE_SIDEBAR_LAYOUT_BOTTOM;
            setMobileSidebarLayout(savedMobileLayout);
        };

        const onLayoutPreview = (event) => {
            if (event.detail?.layout) setSidebarLayout(event.detail.layout);
        };

        const onMobileLayoutPreview = (event) => {
            if (event.detail?.layout) setMobileSidebarLayout(event.detail.layout);
        };

        const onSidebarModePreview = (event) => {
            if (event.detail?.mode) setSidebarMode(event.detail.mode);
        };

        const onFixedPositionPreview = (event) => {
            if (event.detail?.position) setFixedPosition(event.detail.position);
        };

        window.addEventListener('theme-changed', syncThemeState);
        window.addEventListener('theme-layout-preview', onLayoutPreview);
        window.addEventListener('theme-mobile-layout-preview', onMobileLayoutPreview);
        window.addEventListener('theme-sidebar-mode-preview', onSidebarModePreview);
        window.addEventListener('theme-fixed-position-preview', onFixedPositionPreview);
        return () => {
            window.removeEventListener('theme-changed', syncThemeState);
            window.removeEventListener('theme-layout-preview', onLayoutPreview);
            window.removeEventListener('theme-mobile-layout-preview', onMobileLayoutPreview);
            window.removeEventListener('theme-sidebar-mode-preview', onSidebarModePreview);
            window.removeEventListener('theme-fixed-position-preview', onFixedPositionPreview);
        };
    }, [auth?.tema_visual?.layout_sidebar, auth?.tema_visual?.layout_sidebar_mobile, auth?.tema_visual?.sidebar_modo, auth?.tema_visual?.sidebar_posicion_fija]);

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

        const density = applyContentDensityToRoot(tema);
        setContentDensityMode(density.modo);

    }, [isDarkMode, auth?.tema_visual]);

    useEffect(() => {
        const onThemeChanged = () => {
            const density = applyContentDensityToRoot(auth?.tema_visual || {});
            setContentDensityMode(density.modo);
        };
        window.addEventListener('theme-changed', onThemeChanged);
        return () => window.removeEventListener('theme-changed', onThemeChanged);
    }, [auth?.tema_visual]);

    const toggleTheme = () => {
        const newMode = !isDarkMode;
        setIsDarkMode(newMode);
        localStorage.setItem('theme', newMode ? 'dark' : 'light');
        window.dispatchEvent(new Event('theme-changed'));
    };

    const mainClassName = [
        'gelia-app-main transition-all duration-500 bg-transparent',
        isMensajeriaFull
            ? `gelia-app-main--fullscreen gelia-mensajeria-main box-border w-full max-w-none min-w-0 p-0 overflow-hidden h-dvh max-h-dvh${mensajeriaImmersivaMovil ? ' gelia-mensajeria-main--immersive' : ''}`
            : 'gelia-app-main--default',
        mensajeriaImmersivaMovil ? 'gelia-mensajeria-immersive-main' : '',
    ].filter(Boolean).join(' ');

    return (
        <ModalContext.Provider value={{ openModal, closeModal }}>
            <div
                className="gelia-app-shell min-h-dvh text-gray-950 dark:text-gray-100 transition-colors duration-500"
                data-sidebar-layout={shellSidebarLayout}
                data-sidebar-edge={shellSidebarEdge}
                data-page-fullscreen={isMensajeriaFull ? 'true' : 'false'}
                data-content-density={contentDensityMode}
                data-immersive-mobile={mensajeriaImmersivaMovil ? 'true' : 'false'}
                style={{
                    backgroundColor: 'var(--bg-app)',
                    backgroundImage: 'var(--bg-actual)',
                    backgroundSize: 'cover',
                    backgroundPosition: 'center',
                    backgroundAttachment: 'fixed',
                    backgroundRepeat: 'no-repeat',
                }}
            >
                <GeliaLoader
                    isVisible={isGlobalLoading}
                    progress={globalProgress}
                    message="Procesando_"
                />

                {useMobileTopBar && (
                    <header className="gelia-mobile-topbar md:hidden" role="banner">
                        <button
                            type="button"
                            className="gelia-mobile-topbar__menu-btn"
                            onClick={openMobileSidebar}
                            aria-label="Abrir menú de navegación"
                        >
                            <Menu className="w-5 h-5" style={{ color: 'var(--color-primario)' }} />
                        </button>
                        <Link
                            href={route('dashboard')}
                            className="gelia-mobile-topbar__brand"
                            aria-label="Panel principal"
                        >
                            <GeliaLogo variant="sparkle" className="w-9 h-9 drop-shadow-sm" />
                        </Link>
                        <div className="gelia-mobile-topbar__actions">
                            <NotificationBell iconButtonClassName="gelia-mobile-topbar__icon-btn" />
                            <MensajeriaWidget iconButtonClassName="gelia-mobile-topbar__icon-btn" />
                            <Link
                                href={typeof route === 'function' ? route('profile.index') : '/perfil'}
                                className="gelia-mobile-topbar__icon-btn overflow-hidden"
                                aria-label="Perfil"
                            >
                                {auth?.user?.foto_perfil ? (
                                    <img
                                        src={`/storage/${auth.user.foto_perfil}`}
                                        alt="Perfil"
                                        className="w-full h-full object-cover"
                                    />
                                ) : (
                                    <span className="text-xs font-black" style={{ color: 'var(--color-primario)' }}>
                                        {auth?.user?.name ? auth.user.name.charAt(0).toUpperCase() : 'U'}
                                    </span>
                                )}
                            </Link>
                        </div>
                    </header>
                )}

                <Sidebar
                    isDarkMode={isDarkMode}
                    toggleTheme={toggleTheme}
                    user={auth?.user}
                    permissions={auth?.user?.permissions || []}
                    layout={sidebarLayout}
                    sidebarMode={sidebarMode}
                    fixedPosition={fixedPosition}
                    useMobileTopBar={useMobileTopBar}
                    isMobileViewport={isMobileViewport}
                />

                <div className={`gelia-app-body gelia-ui-scale ${GELIA_PREVENT_OVERFLOW_X} ${isMensajeriaFull ? 'h-dvh overflow-hidden' : 'min-h-dvh'} ${mensajeriaImmersivaMovil ? 'gelia-mensajeria-immersive' : ''}`}>
                    <main className={mainClassName}>
                        <div
                            key={url}
                            className={`gelia-app-content ${GELIA_PREVENT_OVERFLOW_X} ${isMensajeriaFull ? 'h-full' : ''}`}
                        >
                            <div
                                className={`gelia-app-content-inner ${GELIA_PREVENT_OVERFLOW_X} ${isMensajeriaFull ? 'h-full max-w-none' : ''}`}
                            >
                                <div className={isMensajeriaFull ? 'h-full' : 'animate-page-reveal'}>
                                    {children}
                                </div>
                            </div>
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
                    {toasts.map((toast) => {
                        const esClickeable = !!(toast.conversacionId || toast.notification);

                        return (
                        <div
                            key={toast.id}
                            className={`pointer-events-auto theme-surface theme-no-blur border theme-border shadow-2xl rounded-2xl p-4 flex items-start gap-4 animate-slide-in-right overflow-hidden relative group ${esClickeable ? 'cursor-pointer hover:border-[var(--color-primario)] transition-colors' : ''}`}
                            onClick={esClickeable ? () => handleToastClick(toast) : undefined}
                            role={esClickeable ? 'button' : undefined}
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
                        );
                    })}
                    </div>

                    <WooSyncFloatingTracker canView={canViewWooSync} canSync={canSyncWoo} />
                    <ImportacionAlmacenFloatingTracker canView={canViewImportacionAlmacen} canManage={canManageImportacionAlmacen} />
                    <CobranzaReporteFloatingTracker canView={canViewCobranzaReportes} />
                </div>

            </div>
        </ModalContext.Provider>
    );
}