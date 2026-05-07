import React, { useState, useRef, useEffect } from 'react';
import { animate } from 'animejs/animation';
import { Link, useForm } from '@inertiajs/react';
import { 
    Menu, X, Moon, Sun, Bell, Home, ArrowLeft,
    LayoutDashboard, Briefcase, ChevronRight, 
    Settings, Database, Users, LogOut, Link as LinkIcon
} from 'lucide-react';

export default function Sidebar({ isDarkMode, toggleTheme, user, permissions }) {
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [isConfigExpanded, setIsConfigExpanded] = useState(false);
    
    const root = useRef(null);
    const widgetRef = useRef(null);
    const menuRef = useRef(null);
    const menuContentRef = useRef(null); // Ref para calcular la altura total del contenido
    const { post } = useForm(); 

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
        animate(root.current, {
            y: [20, 0],
            opacity: [0, 1],
        }, {
            duration: 1000,
            easing: 'easeOutExpo',
            delay: 100
        });
    }, []);

    // LÓGICA PRINCIPAL DEL MENÚ (Apertura y Cierre Base)
    const handleMenuToggle = () => {
        const nextState = !isMenuOpen;
        setIsMenuOpen(nextState);
        
        if (menuRef.current && menuContentRef.current) {
            if (nextState) {
                menuRef.current.classList.remove('pointer-events-none');
                
                // Forzamos altura 'auto' en el submenú si estaba abierto para calcular bien
                const subMenu = document.querySelector('.sub-menu-config');
                if (isConfigExpanded && subMenu) subMenu.style.height = 'auto';

                const targetHeight = menuContentRef.current.scrollHeight;
                
                animate(menuRef.current, {
                    height: [0, targetHeight],
                    opacity: [0, 1]
                }, { 
                    duration: 600, 
                    easing: 'easeOutExpo',
                    // Importante: al terminar, lo dejamos en auto para que pueda crecer si abren submenús
                    complete: () => { menuRef.current.style.height = 'auto'; }
                });
            } else {
                // Al cerrar, fijamos la altura actual antes de animar a 0
                menuRef.current.style.height = `${menuRef.current.offsetHeight}px`;
                
                animate(menuRef.current, {
                    height: 0,
                    opacity: 0
                }, { 
                    duration: 300, 
                    easing: 'easeInQuad',
                    complete: () => menuRef.current.classList.add('pointer-events-none')
                });
            }
        }
    };

    // LÓGICA DEL SUBMENÚ ADMINISTRACIÓN (Recalcula la altura del padre)
    useEffect(() => {
        const subMenu = document.querySelector('.sub-menu-config');
        if (subMenu && menuRef.current && isMenuOpen) {
            
            // 1. Animamos el submenú
            animate(subMenu, {
                height: isConfigExpanded ? [0, subMenu.scrollHeight] : 0,
                opacity: isConfigExpanded ? [0, 1] : 0
            }, { duration: 400, easing: 'easeOutExpo' });
            
            animate('.chevron-config', {
                rotate: isConfigExpanded ? 90 : 0
            }, { duration: 300 });

            // 2. EL TRUCO: Animamos el contenedor padre para que acompañe al submenú
            // Guardamos la altura actual
            const currentMenuHeight = menuRef.current.offsetHeight;
            
            // Calculamos la nueva altura forzando el submenú temporalmente
            subMenu.style.height = isConfigExpanded ? 'auto' : '0px';
            const newMenuHeight = menuContentRef.current.scrollHeight;
            
            // Restauramos el estado del submenú para que la animación fluya
            subMenu.style.height = isConfigExpanded ? '0px' : 'auto';

            animate(menuRef.current, {
                height: [currentMenuHeight, newMenuHeight]
            }, { 
                duration: 400, 
                easing: 'easeOutExpo',
                complete: () => { menuRef.current.style.height = 'auto'; } // Siempre auto al final
            });
        }
    }, [isConfigExpanded]);

    return (
        <nav ref={root} className="fixed top-6 left-6 z-50 flex flex-col items-start" style={{ opacity: 0 }}>
            {/* WIDGET SUPERIOR */}
            <div 
                ref={widgetRef}
                className="flex items-center p-1.5 rounded-full border shadow-[0_8px_30px_rgb(0,0,0,0.12)] dark:shadow-[0_8px_30px_rgba(0,0,0,0.5)]"
                style={{ 
                    backgroundColor: isDarkMode ? '#111111' : '#FFFFFF',
                    borderColor: isDarkMode ? '#222222' : '#E5E7EB'
                }}
            >
                <button onClick={handleMenuToggle} className={`p-2.5 ml-1 mr-1 rounded-full transition-colors ${isDarkMode ? 'text-white hover:bg-white/5' : 'text-black hover:bg-black/5'}`}>
                    {isMenuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
                </button>

                <div className={`flex items-center px-4 border-l space-x-5 ${isDarkMode ? 'border-white/10' : 'border-black/10'}`}>
                    <Link href={route('dashboard')} className={`transition-all hover:scale-110 ${isDarkMode ? 'text-gray-400 hover:text-white' : 'text-gray-500 hover:text-black'}`}>
                        <Home className="w-4 h-4" />
                    </Link>

                    <button onClick={() => window.history.back()} className={`transition-all hover:scale-110 ${isDarkMode ? 'text-gray-400 hover:text-white' : 'text-gray-500 hover:text-black'}`}>
                        <ArrowLeft className="w-4 h-4" />
                    </button>

                    <button onClick={toggleTheme} className="transition-transform active:scale-90 hover:scale-110" style={{ color: 'var(--color-primario)' }}>
                        {isDarkMode ? <Sun className="w-4 h-4" /> : <Moon className="w-4 h-4" />}
                    </button>

                    <button className={`relative transition-all hover:scale-110 ${isDarkMode ? 'text-gray-400 hover:text-white' : 'text-gray-500 hover:text-black'}`}>
                        <Bell className="w-4 h-4" />
                        <span className="absolute -top-0.5 -right-0.5 w-2 h-2 rounded-full border" style={{ backgroundColor: 'var(--color-primario)', borderColor: isDarkMode ? '#111' : '#fff' }}></span>
                    </button>

                    <Link 
                        href={route('profile.edit')} 
                        className={`w-10 h-10 rounded-full flex items-center justify-center cursor-pointer overflow-hidden transition-all border ${isDarkMode ? 'bg-white/5 border-white/10 hover:border-[var(--color-primario)]' : 'bg-black/5 border-black/5 hover:border-black'}`}
                    >
                        {user?.foto_perfil ? (
                            <img src={`/storage/${user.foto_perfil}`} alt="Perfil" className="w-full h-full object-cover rounded-full" />
                        ) : avatarContent}
                    </Link>
                </div>
            </div>

            {/* MENÚ FLOTANTE */}
            <div 
                ref={menuRef}
                className="floating-menu mt-4 border rounded-[2.5rem] shadow-2xl overflow-hidden pointer-events-none" 
                style={{ 
                    width: '300px', 
                    height: 0, 
                    opacity: 0,
                    backgroundColor: isDarkMode ? '#111111' : '#FFFFFF',
                    borderColor: isDarkMode ? '#222222' : '#E5E7EB'
                }}
            >
                {/* Nuevo contenedor interno para calcular scrollHeight correctamente */}
                <div ref={menuContentRef} className="p-5 flex flex-col space-y-2">
                    <span className="text-[11px] font-black tracking-[0.3em] px-5 mb-3 opacity-60 uppercase italic" style={{ color: 'var(--color-primario)' }}>
                        ACCESOS_
                    </span>

                    <Link href={route('dashboard')} className={`flex items-center justify-between w-full px-6 py-4 rounded-[1.5rem] transition-all border ${isDarkMode ? 'bg-white/5 border-transparent text-white hover:border-[var(--color-primario)]' : 'bg-black/5 border-transparent text-black hover:border-black'}`}>
                        <div className="flex items-center">
                            <LayoutDashboard className="w-4 h-4 mr-4" style={{ color: 'var(--color-primario)' }} />
                            <span className="text-xs font-black uppercase italic tracking-tighter">Panel Principal_</span>
                        </div>
                    </Link>

                    {can('crear_solicitud') && (
                        <Link href={route('solicitudes.index')} className={`flex items-center w-full px-6 py-4 rounded-[1.5rem] transition-all border border-transparent ${isDarkMode ? 'text-gray-400 hover:text-white hover:bg-white/5' : 'text-gray-600 hover:text-black hover:bg-black/5'}`}>
                            <Briefcase className="w-4 h-4 mr-4" />
                            <span className="text-xs font-black uppercase italic tracking-tighter">Solicitudes_</span>
                        </Link>
                    )}

                    {can('gestionar_usuarios') && (
                        <div className={`mt-3 pt-3 border-t ${isDarkMode ? 'border-white/10' : 'border-black/10'}`}>
                            <button 
                                onClick={() => setIsConfigExpanded(!isConfigExpanded)}
                                className={`flex items-center justify-between w-full px-6 py-4 rounded-[1.5rem] transition-all ${isDarkMode ? 'text-gray-400 hover:text-white' : 'text-gray-600 hover:text-black'}`}
                            >
                                <div className="flex items-center">
                                    <Settings className="w-4 h-4 mr-4" />
                                    <span className="text-xs font-black uppercase italic tracking-tighter">Administración_</span>
                                </div>
                                <ChevronRight className="w-3 h-3 chevron-config transition-transform" />
                            </button>

                            <div className="sub-menu-config overflow-hidden h-0 opacity-0 px-4 space-y-1">
                                <Link href={route('admin.enlaces')} className={`flex items-center w-full px-5 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors ${isDarkMode ? 'text-gray-500 hover:text-white hover:bg-white/5' : 'text-gray-500 hover:text-black hover:bg-black/5'}`}>
                                    <LinkIcon className="w-3.5 h-3.5 mr-4" /> Generar Enlaces
                                </Link>
                                <Link href={route('admin.clientes')} className={`flex items-center w-full px-5 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors ${isDarkMode ? 'text-gray-500 hover:text-white hover:bg-white/5' : 'text-gray-500 hover:text-black hover:bg-black/5'}`}>
                                    <Database className="w-3.5 h-3.5 mr-4" /> Base de Clientes
                                </Link>
                                <Link href={route('admin.usuarios')} className={`flex items-center w-full px-5 py-3.5 rounded-xl text-[10px] font-black uppercase tracking-widest transition-colors ${isDarkMode ? 'text-gray-500 hover:text-white hover:bg-white/5' : 'text-gray-500 hover:text-black hover:bg-black/5'}`}>
                                    <Users className="w-3.5 h-3.5 mr-4" /> Usuarios
                                </Link>
                            </div>
                        </div>
                    )}

                    <button onClick={() => post(route('logout'))} className={`mt-6 flex items-center w-full px-6 py-4 rounded-[1.5rem] transition-all text-red-500 font-black ${isDarkMode ? 'hover:bg-red-500/10' : 'hover:bg-red-500/5'}`}>
                        <LogOut className="w-4 h-4 mr-4" />
                        <span className="text-xs font-black uppercase italic tracking-widest">Cerrar Sesión_</span>
                    </button>
                </div>
            </div>
        </nav>
    );
}