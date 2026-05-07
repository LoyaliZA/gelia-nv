import React, { useState, useRef, useEffect } from 'react';
import { animate } from 'animejs/animation';
import { Link, useForm } from '@inertiajs/react';
import { 
    Menu, X, Moon, Sun, Bell, Home, ArrowLeft,
    LayoutDashboard, Briefcase, ChevronRight, 
    Settings, Database, Users, LogOut, Link as LinkIcon
} from 'lucide-react';

export default function Sidebar({ isDarkMode, toggleTheme, user, permissions, layout = 'floating_left' }) {
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [isConfigExpanded, setIsConfigExpanded] = useState(false);
    
    // --- RADAR MÓVIL ---
    const [isMobile, setIsMobile] = useState(false);
    
    const root = useRef(null);
    const widgetRef = useRef(null);
    const menuRef = useRef(null);
    const menuContentRef = useRef(null);
    const { post } = useForm(); 

    // Detectar tamaño de pantalla al montar y redimensionar
    useEffect(() => {
        const handleResize = () => setIsMobile(window.innerWidth < 768);
        handleResize(); // Chequeo inicial
        window.addEventListener('resize', handleResize);
        return () => window.removeEventListener('resize', handleResize);
    }, []);

    // --- LÓGICA DE POSICIONAMIENTO RESPONSIVA ---
    // Si es celular, forzamos 'mobile_bottom'. Si no, respetamos el layout elegido.
    const effectiveLayout = isMobile ? 'mobile_bottom' : layout;
    
    const isFixed = effectiveLayout === 'fixed';
    const isRight = effectiveLayout === 'floating_right';
    const isMobileMode = effectiveLayout === 'mobile_bottom';

    const initial = user?.name ? user.name.charAt(0).toUpperCase() : 'U';

    const avatarContent = (
        <div className="flex items-center justify-center w-full h-full bg-transparent">
            <span className="font-black text-lg leading-none select-none" style={{ color: 'var(--color-primario)' }}>
                {initial}
            </span>
        </div>
    );

    const can = (permission) => permissions?.includes(permission);

    // ANIMACIÓN DE REVELADO INICIAL
    useEffect(() => {
        animate(root.current, { y: [20, 0], opacity: [0, 1] }, { duration: 1000, easing: 'easeOutExpo', delay: 100 });
    }, []);

    // LÓGICA PRINCIPAL DEL MENÚ (Apertura y Cierre)
    const handleMenuToggle = () => {
        const nextState = !isMenuOpen;
        setIsMenuOpen(nextState);
        
        if (menuRef.current && menuContentRef.current) {
            if (nextState) {
                menuRef.current.classList.remove('pointer-events-none');
                
                const subMenu = document.querySelector('.sub-menu-config');
                if (isConfigExpanded && subMenu) subMenu.style.height = 'auto';

                if (isFixed) {
                    // Animación Lateral (Solo Fijo en PC)
                    menuContentRef.current.style.width = '300px';
                    animate(menuRef.current, { width: [0, 300], opacity: [0, 1] }, { duration: 600, easing: 'easeOutExpo' });
                } else {
                    // Animación Vertical (Pastilla Flotante PC y Móvil Inferior)
                    const targetHeight = menuContentRef.current.scrollHeight;
                    animate(menuRef.current, { height: [0, targetHeight], opacity: [0, 1] }, { 
                        duration: 600, 
                        easing: 'easeOutExpo',
                        complete: () => { menuRef.current.style.height = 'auto'; }
                    });
                }
            } else {
                if (isFixed) {
                    // Cierre Lateral
                    animate(menuRef.current, { width: 0, opacity: 0 }, { duration: 300, easing: 'easeInQuad', complete: () => menuRef.current.classList.add('pointer-events-none') });
                } else {
                    // Cierre Vertical
                    menuRef.current.style.height = `${menuRef.current.offsetHeight}px`;
                    animate(menuRef.current, { height: 0, opacity: 0 }, { duration: 300, easing: 'easeInQuad', complete: () => menuRef.current.classList.add('pointer-events-none') });
                }
            }
        }
    };

    // LÓGICA DEL SUBMENÚ ADMINISTRACIÓN
    useEffect(() => {
        const subMenu = document.querySelector('.sub-menu-config');
        if (subMenu && menuRef.current && isMenuOpen) {
            
            animate(subMenu, { height: isConfigExpanded ? [0, subMenu.scrollHeight] : 0, opacity: isConfigExpanded ? [0, 1] : 0 }, { duration: 400, easing: 'easeOutExpo' });
            animate('.chevron-config', { rotate: isConfigExpanded ? 90 : 0 }, { duration: 300 });

            // Solo recalculamos la altura total si es Flotante (PC o Móvil). Si es Fijo, la altura siempre es 100vh.
            if (!isFixed) {
                const currentMenuHeight = menuRef.current.offsetHeight;
                subMenu.style.height = isConfigExpanded ? 'auto' : '0px';
                const newMenuHeight = menuContentRef.current.scrollHeight;
                subMenu.style.height = isConfigExpanded ? '0px' : 'auto';

                animate(menuRef.current, { height: [currentMenuHeight, newMenuHeight] }, { 
                    duration: 400, 
                    easing: 'easeOutExpo',
                    complete: () => { menuRef.current.style.height = 'auto'; }
                });
            }
        }
    }, [isConfigExpanded, isFixed, isMenuOpen]);

    // --- CONSTRUCCIÓN DINÁMICA DE CLASES CSS ---
    
    // Contenedor NAV
    let navClasses = "fixed z-50 flex ";
    if (isMobileMode) navClasses += "bottom-6 left-1/2 -translate-x-1/2 flex-col-reverse items-center w-max"; 
    else if (isFixed) navClasses += "top-0 left-0 h-screen flex-row";
    else navClasses += `top-6 flex-col ${isRight ? 'right-6 items-end' : 'left-6 items-start'}`;

    // Pastilla / Barra
    let widgetClasses = "theme-surface theme-border sidebar-glass flex ";
    if (isFixed) widgetClasses += "flex-col items-center py-6 w-20 h-full border-r rounded-none";
    else widgetClasses += "items-center p-1.5 rounded-full border shadow-[0_8px_30px_rgba(0,0,0,0.12)]";

    // Contenedor del Menú Expandido
    let menuClasses = "floating-menu border shadow-2xl overflow-hidden pointer-events-none theme-surface theme-border sidebar-glass ";
    if (isFixed) menuClasses += "ml-0 rounded-r-[2.5rem] rounded-l-none border-l-0";
    else if (isMobileMode) menuClasses += "mb-4 rounded-[2rem] w-[90vw] max-w-[320px]"; // Abre hacia arriba en móvil
    else menuClasses += "mt-4 rounded-[2.5rem] w-[300px]";

    return (
        <nav ref={root} className={navClasses} style={{ opacity: 0 }}>
            {/* WIDGET SUPERIOR / BARRA LATERAL / PASTILLA MÓVIL */}
            <div ref={widgetRef} className={widgetClasses}>
                <button onClick={handleMenuToggle} className={`rounded-full transition-colors theme-text-main hover:bg-black/5 dark:hover:bg-white/5 outline-none ${isFixed ? 'p-3 mb-8' : 'p-2.5 mx-1'}`}>
                    {isMenuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
                </button>

                {/* Controles de la pastilla */}
                <div className={`flex theme-border ${isFixed ? 'flex-col items-center space-y-8 pt-8 border-t w-full' : 'items-center px-3 sm:px-4 border-l space-x-3 sm:space-x-5'}`}>
                    
                    {/* Ocultamos el botón "Home" en celular para ahorrar espacio */}
                    <Link href={route('dashboard')} className="transition-all hover:scale-110 theme-text-muted hover:theme-text-main outline-none hidden sm:block">
                        <Home className="w-5 h-5" />
                    </Link>

                    <button onClick={() => window.history.back()} className="transition-all hover:scale-110 theme-text-muted hover:theme-text-main outline-none">
                        <ArrowLeft className="w-5 h-5" />
                    </button>

                    <button onClick={toggleTheme} className="transition-transform active:scale-90 hover:scale-110 outline-none" style={{ color: 'var(--color-primario)' }}>
                        {isDarkMode ? <Sun className="w-5 h-5" /> : <Moon className="w-5 h-5" />}
                    </button>

                    <button className="relative transition-all hover:scale-110 theme-text-muted hover:theme-text-main outline-none">
                        <Bell className="w-5 h-5" />
                        <span className="absolute -top-0.5 -right-0.5 w-2.5 h-2.5 rounded-full border-2 theme-surface" style={{ backgroundColor: 'var(--color-primario)' }}></span>
                    </button>

                    <Link 
                        href={route('profile.edit')} 
                        className={`rounded-full flex items-center justify-center cursor-pointer overflow-hidden transition-all border theme-element theme-border hover:border-transparent outline-none group ${isFixed ? 'w-12 h-12 mt-4' : 'w-9 h-9 sm:w-10 sm:h-10'}`}
                        onMouseEnter={(e) => e.currentTarget.style.borderColor = 'var(--color-primario)'}
                        onMouseLeave={(e) => e.currentTarget.style.borderColor = ''}
                    >
                        {user?.foto_perfil ? (
                            <img src={`/storage/${user.foto_perfil}`} alt="Perfil" className="w-full h-full object-cover rounded-full transition-transform group-hover:scale-110" />
                        ) : avatarContent}
                    </Link>
                </div>
            </div>

            {/* MENÚ DESPLEGABLE */}
            <div 
                ref={menuRef}
                className={menuClasses} 
                style={{ 
                    width: isFixed ? 0 : '', // En móvil y flotante el ancho lo maneja Tailwind
                    height: isFixed ? '100vh' : 0, 
                    opacity: 0 
                }}
            >
                {/* max-h-[60vh] asegura que en celulares pequeños el menú sea deslizable si hay muchos botones */}
                <div ref={menuContentRef} className={`p-5 flex flex-col space-y-2 overflow-y-auto custom-scrollbar ${isFixed ? 'w-[300px] h-full pt-10' : 'max-h-[65vh] sm:max-h-none'}`}>
                    <span className="text-[11px] font-black tracking-[0.3em] px-5 mb-3 opacity-60 uppercase italic" style={{ color: 'var(--color-primario)' }}>
                        ACCESOS_
                    </span>

                    <Link href={route('dashboard')} className="flex items-center justify-between w-full px-6 py-4 rounded-[1.5rem] transition-all border border-transparent theme-text-main hover:bg-black/5 dark:hover:bg-white/5 outline-none" onMouseEnter={(e) => e.currentTarget.style.borderColor = 'var(--color-primario)'} onMouseLeave={(e) => e.currentTarget.style.borderColor = 'transparent'}>
                        <div className="flex items-center">
                            <LayoutDashboard className="w-4 h-4 mr-4" style={{ color: 'var(--color-primario)' }} />
                            <span className="text-xs font-black uppercase italic tracking-tighter">Panel Principal_</span>
                        </div>
                    </Link>

                    {can('crear_solicitud') && (
                        <Link href={route('solicitudes.index')} className="flex items-center w-full px-6 py-4 rounded-[1.5rem] transition-all border border-transparent theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 outline-none">
                            <Briefcase className="w-4 h-4 mr-4" />
                            <span className="text-xs font-black uppercase italic tracking-tighter">Solicitudes_</span>
                        </Link>
                    )}

                    {can('gestionar_usuarios') && (
                        <div className="mt-3 pt-3 border-t theme-border">
                            <button onClick={() => setIsConfigExpanded(!isConfigExpanded)} className="flex items-center justify-between w-full px-6 py-4 rounded-[1.5rem] transition-all theme-text-muted hover:theme-text-main outline-none">
                                <div className="flex items-center">
                                    <Settings className="w-4 h-4 mr-4" />
                                    <span className="text-xs font-black uppercase italic tracking-tighter">Administración_</span>
                                </div>
                                <ChevronRight className="w-3 h-3 chevron-config transition-transform" />
                            </button>

                            <div className="sub-menu-config overflow-hidden h-0 opacity-0 px-4 space-y-1">
                                <Link href={route('admin.enlaces')} className="flex items-center w-full px-5 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 outline-none">
                                    <LinkIcon className="w-3.5 h-3.5 mr-4" /> Generar Enlaces
                                </Link>
                                <Link href={route('admin.clientes')} className="flex items-center w-full px-5 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 outline-none">
                                    <Database className="w-3.5 h-3.5 mr-4" /> Base de Clientes
                                </Link>
                                <Link href={route('admin.usuarios')} className="flex items-center w-full px-5 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors theme-text-muted hover:theme-text-main hover:bg-black/5 dark:hover:bg-white/5 outline-none">
                                    <Users className="w-3.5 h-3.5 mr-4" /> Usuarios
                                </Link>
                            </div>
                        </div>
                    )}

                    <div className="mt-auto pt-6">
                        <button onClick={() => post(route('logout'))} className="flex items-center w-full px-6 py-4 rounded-[1.5rem] transition-all text-red-500 font-black hover:bg-red-500/10 outline-none">
                            <LogOut className="w-4 h-4 mr-4" />
                            <span className="text-xs font-black uppercase italic tracking-widest">Cerrar Sesión_</span>
                        </button>
                    </div>
                </div>
            </div>
        </nav>
    );
}