import React, { useState, useRef, useEffect } from 'react';
import { animate } from 'animejs';
import { Link, useForm, usePage } from '@inertiajs/react';
import {
    Menu, X, Moon, Sun, Bell, Home, ArrowLeft,
    LayoutDashboard, Briefcase, ChevronRight,
    Settings, Database, Users, LogOut, Link as LinkIcon,
    FolderTree, Calculator
} from 'lucide-react';

import NotificationBell from './NotificationBell';

/* --- DICCIONARIO FRONTEND --- */
const ADMIN_MENU_CONFIG = [
    { id: 'enlaces', label: 'Generar Enlaces', path: '/admin/enlaces', routeName: 'admin.enlaces', icon: LinkIcon, permission: 'usuarios.gestionar' },
    { id: 'clientes', label: 'Base de Clientes', path: '/admin/clientes', routeName: 'admin.clientes', icon: Database, permission: 'clientes.ver' },
    { id: 'catalogos', label: 'Catálogos Globales', path: '/admin/catalogos', routeName: 'admin.catalogos', icon: FolderTree, permission: 'catalogos.gestionar' },
    { id: 'comisiones', label: 'Comisiones', path: '/admin/comisiones', routeName: 'admin.comisiones', icon: Calculator, permission: 'comisiones.gestionar' },
    { id: 'usuarios', label: 'Usuarios', path: '/admin/usuarios', routeName: 'admin.usuarios', icon: Users, permission: 'usuarios.gestionar' },
];

export default function Sidebar({ isDarkMode, toggleTheme, user, permissions, layout = 'floating_left' }) {
    const { url } = usePage();
    const isAdminActive = url.startsWith('/admin');

    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [isConfigExpanded, setIsConfigExpanded] = useState(isAdminActive);
    const [isMobile, setIsMobile] = useState(false);

    const root = useRef(null);
    const widgetRef = useRef(null);
    const menuRef = useRef(null);
    const menuContentRef = useRef(null);
    const subMenuRef = useRef(null);
    const { post } = useForm();

    // --- ESTILOS INICIALES BLINDADOS (Previene conflictos React vs AnimeJS en el F5) ---
    const initialMenuStyle = useRef({ opacity: 0, height: layout === 'fixed' ? '100vh' : '0px', width: layout === 'fixed' ? '0px' : '' });
    const initialSubMenuStyle = useRef({ opacity: isAdminActive ? 1 : 0, height: isAdminActive ? 'auto' : '0px' });

    useEffect(() => {
        if (isAdminActive) setIsConfigExpanded(true);
    }, [url, isAdminActive]);

    const isRouteActive = (path) => {
        if (path === '/dashboard' && url === '/dashboard') return true;
        if (path !== '/dashboard' && url.startsWith(path)) return true;
        return false;
    };

    const linkBaseClass = "flex items-center w-full px-6 py-4 rounded-[1.5rem] transition-all outline-none ";
    const linkActiveClass = "bg-[var(--color-primario)] text-white shadow-lg border border-transparent";
    const linkInactiveClass = "theme-element theme-text-muted hover:theme-text-main hover:shadow-md border border-transparent hover:border-[var(--color-primario)]";

    useEffect(() => {
        const handleResize = () => setIsMobile(window.innerWidth < 768);
        handleResize();
        window.addEventListener('resize', handleResize);
        return () => window.removeEventListener('resize', handleResize);
    }, []);

    const effectiveLayout = isMobile ? 'mobile_bottom' : layout;
    const isFixed = effectiveLayout === 'fixed';
    const isRight = effectiveLayout === 'floating_right';
    const isMobileMode = effectiveLayout === 'mobile_bottom';

    // --- LÓGICA DE PERMISOS BLINDADA ---
    const can = (permission) => {
        const isSuperAdmin = user?.roles?.includes('Super Admin');
        const hasPermission = permissions?.includes(permission);
        return isSuperAdmin || hasPermission;
    };

    const showAdminMenu = ADMIN_MENU_CONFIG.some(item => can(item.permission));

    useEffect(() => {
        if (root.current) {
            animate(root.current, { y: [10, 0], opacity: [0, 1], duration: 400, ease: 'outExpo' });
        }
    }, []);

    // --- ANIMACIONES CORREGIDAS (Sintaxis AnimeJS V4 con onComplete) ---
    const handleMenuToggle = () => {
        const nextState = !isMenuOpen;
        setIsMenuOpen(nextState);

        if (menuRef.current && menuContentRef.current) {
            if (nextState) {
                // Preparamos el submenú visualmente si le toca estar abierto
                if (isConfigExpanded && subMenuRef.current) {
                    subMenuRef.current.style.height = 'auto';
                    subMenuRef.current.style.opacity = 1;
                }

                if (isFixed) {
                    animate(menuRef.current, { width: [0, 300], opacity: [0, 1], duration: 350, ease: 'outExpo' });
                } else {
                    menuRef.current.style.height = 'auto';
                    const targetHeight = menuContentRef.current.scrollHeight;
                    menuRef.current.style.height = '0px';

                    animate(menuRef.current, {
                        height: [0, targetHeight], opacity: [0, 1], duration: 350, ease: 'outExpo',
                        onComplete: () => { menuRef.current.style.height = 'auto'; } // <- LA LÍNEA QUE FALTABA
                    });
                }
            } else {
                if (isFixed) {
                    animate(menuRef.current, { width: 0, opacity: 0, duration: 250, ease: 'inExpo' });
                } else {
                    const currentHeight = menuRef.current.offsetHeight;
                    animate(menuRef.current, {
                        height: [currentHeight, 0], opacity: [1, 0], duration: 250, ease: 'inExpo',
                        onComplete: () => { menuRef.current.style.height = '0px'; }
                    });
                }
            }
        }
    };

    const toggleConfigMenu = () => {
        const nextState = !isConfigExpanded;
        setIsConfigExpanded(nextState);

        if (subMenuRef.current) {
            if (nextState) {
                subMenuRef.current.style.height = 'auto';
                const targetHeight = subMenuRef.current.scrollHeight;
                subMenuRef.current.style.height = '0px';

                animate(subMenuRef.current, {
                    height: [0, targetHeight], opacity: [0, 1], duration: 350, ease: 'outExpo',
                    onComplete: () => { subMenuRef.current.style.height = 'auto'; }
                });
                animate('.chevron-config', { rotate: 90, duration: 350, ease: 'outExpo' });
            } else {
                const currentHeight = subMenuRef.current.offsetHeight;
                animate(subMenuRef.current, {
                    height: [currentHeight, 0], opacity: [1, 0], duration: 250, ease: 'inExpo',
                    onComplete: () => { subMenuRef.current.style.height = '0px'; }
                });
                animate('.chevron-config', { rotate: 0, duration: 250, ease: 'inExpo' });
            }
        }
    };

    let navClasses = "fixed z-50 flex ";
    if (isMobileMode) navClasses += "bottom-6 left-1/2 -translate-x-1/2 flex-col-reverse items-center w-max";
    else if (isFixed) navClasses += "top-0 left-0 h-screen flex-row";
    else navClasses += `top-6 flex-col ${isRight ? 'right-6 items-end' : 'left-6 items-start'}`;

    let widgetClasses = "theme-surface theme-border sidebar-glass flex relative z-20 ";
    if (isFixed) widgetClasses += "flex-col items-center py-6 w-20 h-full border-r rounded-none";
    else widgetClasses += "items-center p-1.5 rounded-full border shadow-[0_8px_30px_rgba(0,0,0,0.12)]";

    // Se mantiene pointer-events dinámico en React para bloquear clics durante el cierre
    let menuClasses = `floating-menu border shadow-2xl overflow-hidden theme-surface theme-border sidebar-glass relative z-10 ${isMenuOpen ? 'pointer-events-auto' : 'pointer-events-none'} `;
    if (isFixed) menuClasses += "ml-0 rounded-r-[2.5rem] rounded-l-none border-l-0";
    else if (isMobileMode) menuClasses += "mb-4 rounded-[2rem] w-[90vw] max-w-[320px]";
    else menuClasses += "mt-4 rounded-[2.5rem] w-[300px]";

    return (
        <nav ref={root} className={navClasses} style={{ opacity: 0 }}>
            <div ref={widgetRef} className={widgetClasses}>
                <button onClick={handleMenuToggle} className={`rounded-full transition-colors theme-text-main hover:bg-black/5 dark:hover:bg-white/5 outline-none ${isFixed ? 'p-3 mb-8' : 'p-2.5 mx-1'}`}>
                    {isMenuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
                </button>

                <div className={`flex theme-border ${isFixed ? 'flex-col items-center space-y-8 pt-8 border-t w-full' : 'items-center px-3 sm:px-4 border-l space-x-3 sm:space-x-5'}`}>
                    <Link href={route('dashboard')} className="transition-all hover:scale-110 theme-text-muted hover:theme-text-main outline-none hidden sm:block">
                        <Home className="w-5 h-5" />
                    </Link>

                    <button onClick={() => window.history.back()} className="transition-all hover:scale-110 theme-text-muted hover:theme-text-main outline-none">
                        <ArrowLeft className="w-5 h-5" />
                    </button>

                    <button onClick={toggleTheme} className="transition-transform active:scale-90 hover:scale-110 outline-none" style={{ color: 'var(--color-primario)' }}>
                        {isDarkMode ? <Sun className="w-5 h-5" /> : <Moon className="w-5 h-5" />}
                    </button>

                    {/* --- REEMPLAZA LA CAMPANA ANTIGUA CON ESTO --- */}
                    <NotificationBell notifications={user?.notifications || []} />
                    {/* --------------------------------------------- */}

                    <Link
                        href={route('profile.edit')}
                        className={`rounded-full flex items-center justify-center cursor-pointer overflow-hidden transition-all border outline-none group ${isFixed ? 'w-12 h-12 mt-4' : 'w-9 h-9 sm:w-10 sm:h-10'} ${isRouteActive('/profile') ? 'border-[var(--color-primario)] shadow-md' : 'theme-element theme-border'}`}
                    >
                        {user?.foto_perfil ? (
                            <img src={`/storage/${user.foto_perfil}`} alt="Perfil" className="w-full h-full object-cover rounded-full transition-transform group-hover:scale-110" />
                        ) : (
                            <div className="flex items-center justify-center w-full h-full bg-transparent">
                                <span className="font-black text-lg leading-none select-none" style={{ color: 'var(--color-primario)' }}>
                                    {user?.name ? user.name.charAt(0).toUpperCase() : 'U'}
                                </span>
                            </div>
                        )}
                    </Link>
                </div>
            </div>

            <div ref={menuRef} className={menuClasses} style={initialMenuStyle.current}>
                <div ref={menuContentRef} className={`p-5 flex flex-col space-y-3 overflow-y-auto custom-scrollbar ${isFixed ? 'w-[300px] h-full pt-10' : 'max-h-[85vh]'}`}>
                    <span className="text-[11px] font-black tracking-[0.3em] px-5 mb-1 opacity-60 uppercase italic" style={{ color: 'var(--color-primario)' }}>
                        ACCESOS_
                    </span>

                    <Link
                        href={route('dashboard')}
                        className={linkBaseClass + (isRouteActive('/dashboard') ? linkActiveClass : linkInactiveClass)}
                        onMouseEnter={(e) => { if (!isRouteActive('/dashboard')) e.currentTarget.style.borderColor = 'var(--color-primario)' }}
                        onMouseLeave={(e) => { if (!isRouteActive('/dashboard')) e.currentTarget.style.borderColor = 'transparent' }}
                    >
                        <div className="flex items-center">
                            <LayoutDashboard className="w-4 h-4 mr-4" style={{ color: isRouteActive('/dashboard') ? '#ffffff' : 'var(--color-primario)' }} />
                            <span className="text-xs font-black uppercase italic tracking-tighter justify-between">Panel Principal_</span>
                        </div>
                    </Link>

                    {can('solicitudes.ver_listado') && (
                        <Link
                            href={route('solicitudes.index')}
                            className={linkBaseClass + (isRouteActive('/solicitudes') ? linkActiveClass : linkInactiveClass)}
                            onMouseEnter={(e) => { if (!isRouteActive('/solicitudes')) e.currentTarget.style.borderColor = 'var(--color-primario)' }}
                            onMouseLeave={(e) => { if (!isRouteActive('/solicitudes')) e.currentTarget.style.borderColor = 'transparent' }}
                        >
                            <Briefcase className="w-4 h-4 mr-4" />
                            <span className="text-xs font-black uppercase italic tracking-tighter">Solicitudes_</span>
                        </Link>
                    )}

                    {showAdminMenu && (
                        <div className="mt-2 pt-3 border-t theme-border flex flex-col">
                            <button
                                onClick={toggleConfigMenu}
                                className={`flex items-center justify-between w-full px-6 py-4 rounded-3xl transition-all outline-none z-10 ${isAdminActive ? 'theme-element border-[var(--color-primario)] text-[var(--color-primario)] shadow-sm' : 'theme-element theme-text-muted hover:theme-text-main border border-transparent'}`}
                            >
                                <div className="flex items-center">
                                    <Settings className="w-4 h-4 mr-4" />
                                    <span className="text-xs font-black uppercase italic tracking-tighter">Administración_</span>
                                </div>
                                <ChevronRight className={`w-3 h-3 transition-transform duration-300 ${isConfigExpanded ? 'rotate-90' : ''}`} />
                            </button>

                            <div ref={subMenuRef} className="overflow-hidden px-2 mt-2" style={initialSubMenuStyle.current}>
                                <div className="flex flex-col space-y-2">
                                    {ADMIN_MENU_CONFIG.filter(item => can(item.permission)).map((item) => {
                                        const IconComponent = item.icon;
                                        const isActive = isRouteActive(item.path);
                                        return (
                                            <Link
                                                key={item.id}
                                                href={route(item.routeName)}
                                                className={`flex items-center w-full px-5 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all outline-none ${isActive ? 'bg-[var(--color-primario)] text-white shadow-md' : 'theme-element theme-text-muted hover:theme-text-main hover:shadow-sm border border-transparent hover:border-[var(--color-primario)]'}`}
                                            >
                                                <IconComponent className="w-3.5 h-3.5 mr-4" /> {item.label}
                                            </Link>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="mt-auto pt-6 pb-2">
                        <button
                            onClick={() => {
                                // 1. Limpiamos toda la memoria gráfica y configuraciones del navegador
                                localStorage.clear();
                                // 2. Ejecutamos el logout en el servidor
                                post(route('logout'));
                            }}
                            className="flex items-center w-full px-6 py-4 rounded-3xl transition-all theme-element border border-transparent hover:border-red-500 hover:shadow-md outline-none group">
                            <LogOut className="w-4 h-4 mr-4 text-red-500 group-hover:text-red-600 transition-colors" />
                            <span className="text-xs font-black uppercase italic tracking-widest text-red-500 group-hover:text-red-600 transition-colors">Cerrar Sesión_</span>
                        </button>
                    </div>
                </div>
            </div>
        </nav>
    );
}