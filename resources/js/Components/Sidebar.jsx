import React, { useState, useRef, useEffect } from 'react';
import { animate, createScope } from 'animejs';
import { Link, useForm } from '@inertiajs/react';
import { 
    Menu, X, Moon, Sun, Search, Bell, 
    LayoutDashboard, Briefcase, ChevronRight, 
    Settings, Database, Users, LogOut, Link as LinkIcon
} from 'lucide-react';

export default function Sidebar({ isDarkMode, toggleTheme, user, permissions }) {
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [isConfigExpanded, setIsConfigExpanded] = useState(false);
    const [searchOpen, setSearchOpen] = useState(false);
    const searchInputRef = useRef(null);

    const root = useRef(null);
    const scope = useRef(null);
    const { post } = useForm(); 

    const initial = user?.name ? user.name.charAt(0).toUpperCase() : 'U';
    const avatarContent = user?.foto_perfil ? (
        <img src={`/storage/${user.foto_perfil}`} alt="Perfil" className="w-full h-full object-cover rounded-full" />
    ) : (
        <span style={{ color: 'var(--color-primario, #ec4899)' }} className="font-bold text-xs">{initial}</span>
    );

    const can = (permission) => permissions?.includes(permission);

    useEffect(() => {
        if (searchOpen && searchInputRef.current) searchInputRef.current.focus();
    }, [searchOpen]);

    useEffect(() => {
        scope.current = createScope({ root }).add((self) => {
            self.add('toggleMainMenu', (isOpen) => {
                const el = document.querySelector('.floating-menu');
                if (!el) return; 

                if (isOpen) {
                    el.classList.remove('border-transparent', 'pointer-events-none');
                    el.classList.add(isDarkMode ? 'border-[#333]' : 'border-gray-200');
                    
                    // Truco: Forzamos 'auto' invisiblemente para saber cuánto debe medir realmente
                    el.style.height = 'auto';
                    const targetHeight = el.scrollHeight;
                    el.style.height = '0px';

                    animate(el, {
                        height: [0, targetHeight], 
                        opacity: [0, 1],
                        easing: 'outElastic(1, .8)',
                        duration: 800,
                        complete: () => { el.style.height = 'auto'; }, // AnimeJS usa 'complete'
                        onComplete: () => { el.style.height = 'auto'; } // Framer Motion usa 'onComplete' (cubrimos ambas)
                    });
                } else {
                    // Fijamos la altura en píxeles antes de colapsar para que la animación no se rompa
                    el.style.height = el.offsetHeight + 'px';
                    
                    animate(el, {
                        height: 0,
                        opacity: 0,
                        easing: 'inQuad',
                        duration: 300,
                        complete: () => {
                            el.classList.add('border-transparent', 'pointer-events-none');
                            el.style.height = '0px';
                        },
                        onComplete: () => {
                            el.classList.add('border-transparent', 'pointer-events-none');
                            el.style.height = '0px';
                        }
                    });
                }
            });

            self.add('toggleSubMenu', (isExpanded) => {
                const subMenu = document.querySelector('.sub-menu-config');
                if (!subMenu) return; 

                if (isExpanded) {
                    animate('.chevron-config', { rotate: 90, duration: 300, easing: 'outExpo' });
                    
                    subMenu.style.height = 'auto';
                    const targetHeight = subMenu.scrollHeight;
                    subMenu.style.height = '0px';

                    animate(subMenu, { 
                        height: [0, targetHeight], 
                        opacity: [0, 1], 
                        easing: 'outExpo', 
                        duration: 400,
                        complete: () => { subMenu.style.height = 'auto'; },
                        onComplete: () => { subMenu.style.height = 'auto'; }
                    });
                } else {
                    animate('.chevron-config', { rotate: 0, duration: 300, easing: 'outExpo' });
                    
                    subMenu.style.height = subMenu.offsetHeight + 'px';

                    animate(subMenu, { 
                        height: 0, 
                        opacity: 0, 
                        easing: 'outExpo', 
                        duration: 300,
                        complete: () => { subMenu.style.height = '0px'; },
                        onComplete: () => { subMenu.style.height = '0px'; }
                    });
                }
            });
        });
        return () => scope.current.revert();
    }, [isDarkMode]);

    useEffect(() => { 
        if (scope.current && isMenuOpen) scope.current.methods.toggleMainMenu(isMenuOpen); 
    }, [isMenuOpen]);

    useEffect(() => { 
        if (scope.current) scope.current.methods.toggleSubMenu(isConfigExpanded); 
    }, [isConfigExpanded]);

    return (
        <nav ref={root} className="fixed top-6 left-6 z-50 flex flex-col items-start">
            
            {/* WIDGET SUPERIOR CORREGIDO */}
            <div className={`backdrop-blur-md rounded-full flex items-center p-1.5 shadow-sm transition-colors duration-300 border ${isDarkMode ? 'bg-[#141414]/90 border-[#333]' : 'bg-white/90 border-gray-200'}`}>
                <button onClick={() => setIsMenuOpen(!isMenuOpen)} className={`p-2 ml-1 mr-2 rounded-full transition-colors ${isDarkMode ? 'text-white hover:bg-[#2A2A2A]' : 'text-black hover:bg-gray-100'}`}>
                    {isMenuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
                </button>

                <div className={`flex items-center px-3 border-l space-x-3 ${isDarkMode ? 'border-[#333]' : 'border-gray-200'}`}>
                    <button onClick={toggleTheme} className={`transition-colors ${isDarkMode ? 'text-gray-400 hover:text-white' : 'text-gray-400 hover:text-black'}`}>
                        {isDarkMode ? <Sun className="w-4 h-4" /> : <Moon className="w-4 h-4" />}
                    </button>
                    
                    <div className="flex items-center">
                        <div className={`overflow-hidden transition-all duration-300 ease-out ${searchOpen ? 'w-[200px] ml-2' : 'w-0'}`}>
                            <input 
                                ref={searchInputRef}
                                type="text" 
                                placeholder="Buscar..." 
                                className={`w-full bg-transparent border-none focus:ring-0 text-sm p-0 ${isDarkMode ? 'text-white placeholder-gray-500' : 'text-black placeholder-gray-400'}`}
                            />
                        </div>
                        <button onClick={() => setSearchOpen(!searchOpen)} className={`transition-colors ${searchOpen ? 'text-pink-500 ml-2' : (isDarkMode ? 'text-gray-400 hover:text-white' : 'text-gray-400 hover:text-black')}`}>
                            <Search className="w-4 h-4" />
                        </button>
                    </div>

                    <button className={`relative transition-colors ml-1 mr-2 ${isDarkMode ? 'text-gray-400 hover:text-white' : 'text-gray-400 hover:text-black'}`}>
                        <Bell className="w-4 h-4" />
                        <span className={`absolute -top-1 -right-1 w-2 h-2 rounded-full border border-pink-500 ${isDarkMode ? 'bg-black' : 'bg-white'}`}></span>
                    </button>

                    <Link 
                        href={route('profile.edit')} 
                        className={`w-9 h-9 rounded-full flex items-center justify-center cursor-pointer overflow-hidden hover:ring-2 hover:ring-pink-500 transition-all border ${isDarkMode ? 'bg-[#1A1A1A] border-[#333]' : 'bg-white border-gray-200'}`}
                    >
                        {avatarContent}
                    </Link>
                </div>
            </div>

            {/* MENÚ DESPLEGABLE CORREGIDO */}
            {isMenuOpen && (
                <div className={`floating-menu mt-2 border border-transparent rounded-3xl shadow-2xl overflow-hidden ${isDarkMode ? 'bg-[#141414]' : 'bg-white'}`} style={{ width: '280px' }}>
                    <div className="p-3 flex flex-col space-y-1 overflow-y-auto max-h-[80vh] scrollbar-hide">
                        
                        <Link href={route('dashboard')} className={`flex items-center justify-between w-full px-4 py-3 rounded-2xl transition-all duration-300 ring-1 border ${isDarkMode ? 'bg-[#1A1A1A] border-transparent text-white ring-[#333]' : 'bg-white border-black text-black ring-black'}`}>
                            <div className="flex items-center">
                                <LayoutDashboard className="w-4 h-4 mr-3 text-pink-500" />
                                <span className="text-sm font-medium uppercase italic font-black tracking-tighter">Panel Principal_</span>
                            </div>
                        </Link>

                        {can('crear_solicitud') && (
                            <Link href={route('solicitudes.index')} className={`flex items-center justify-between w-full px-4 py-3 rounded-2xl transition-all duration-300 border border-transparent ${isDarkMode ? 'text-gray-400 hover:border-[#333] hover:text-white' : 'text-gray-600 hover:border-gray-300 hover:text-black'}`}>
                                <div className="flex items-center">
                                    <Briefcase className="w-4 h-4 mr-3" />
                                    <span className="text-sm font-medium uppercase italic font-black tracking-tighter">Solicitudes_</span>
                                </div>
                            </Link>
                        )}

                        {can('gestionar_usuarios') && (
                            <div className={`mt-2 pt-2 border-t ${isDarkMode ? 'border-[#333]' : 'border-gray-200'}`}>
                                <button 
                                    onClick={() => setIsConfigExpanded(!isConfigExpanded)}
                                    className={`flex items-center justify-between w-full px-4 py-3 rounded-2xl transition-all duration-300 ${isDarkMode ? 'text-gray-400 hover:bg-white/5 hover:text-white' : 'text-gray-600 hover:bg-gray-50'}`}
                                >
                                    <div className="flex items-center">
                                        <Settings className="w-4 h-4 mr-3" />
                                        <span className="text-sm font-black uppercase italic tracking-tighter">Administración_</span>
                                    </div>
                                    <ChevronRight className="w-3 h-3 chevron-config" />
                                </button>

                                <div className="sub-menu-config overflow-hidden h-0 opacity-0 px-2 space-y-1">
                                    <Link href={route('admin.enlaces')} className={`flex items-center w-full px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest ${isDarkMode ? 'text-gray-400 hover:text-white hover:bg-white/5' : 'text-gray-500 hover:text-black hover:bg-gray-50'}`}>
                                        <LinkIcon className="w-3.5 h-3.5 mr-3" /> Generar Enlaces
                                    </Link>
                                    <Link href={route('admin.clientes')} className={`flex items-center w-full px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest ${isDarkMode ? 'text-gray-400 hover:text-white hover:bg-white/5' : 'text-gray-500 hover:text-black hover:bg-gray-50'}`}>
                                        <Database className="w-3.5 h-3.5 mr-3" /> Base de Clientes
                                    </Link>
                                    <Link href={route('admin.usuarios')} className={`flex items-center w-full px-4 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest ${isDarkMode ? 'text-gray-400 hover:text-white hover:bg-white/5' : 'text-gray-500 hover:text-black hover:bg-gray-50'}`}>
                                        <Users className="w-3.5 h-3.5 mr-3" /> Usuarios
                                    </Link>
                                </div>
                            </div>
                        )}

                        <button onClick={() => post(route('logout'))} className={`mt-4 flex items-center w-full px-4 py-3 rounded-2xl transition-all duration-300 border border-transparent text-red-600 ${isDarkMode ? 'hover:bg-red-900/20' : 'hover:bg-red-50'}`}>
                            <LogOut className="w-4 h-4 mr-3" />
                            <span className="text-sm font-black uppercase italic tracking-widest">Cerrar Sesión_</span>
                        </button>
                    </div>
                </div>
            )}
        </nav>
    );
}