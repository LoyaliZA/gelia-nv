import React, { useState, useRef, useEffect } from 'react';
import { animate, createScope } from 'animejs';
import { Menu, X, Moon, Sun, Search, Bell, LayoutDashboard, Users, Briefcase, ChevronRight, Settings, Link as LinkIcon, Database, LogOut } from 'lucide-react';
import { Link, useForm } from '@inertiajs/react'; // Importamos Link para la navegación

export default function Sidebar({ isDarkMode, toggleTheme, user }) {
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [isConfigExpanded, setIsConfigExpanded] = useState(false);

    const root = useRef(null);
    const scope = useRef(null);
    const { post } = useForm(); // Para el cierre de sesión

    // Lógica para el avatar: Foto de perfil o Inicial
    const initial = user?.name ? user.name.charAt(0).toUpperCase() : 'U';
    const avatarContent = user?.foto_perfil ? (
        <img src={`/storage/${user.foto_perfil}`} alt="Perfil" className="w-full h-full object-cover rounded-full" />
    ) : (
        <span style={{ color: 'var(--color-primario, #ec4899)' }} className="font-bold text-xs">{initial}</span>
    );

    // Función auxiliar para verificar roles
    const hasRole = (rolesToCheck) => {
        if (!user || !user.roles) return false;
        if (Array.isArray(rolesToCheck)) {
            return rolesToCheck.some(r => user.roles.includes(r));
        }
        return user.roles.includes(rolesToCheck);
    };

    useEffect(() => {
        scope.current = createScope({ root }).add((self) => {
            self.add('toggleMainMenu', (isOpen) => {
                const el = document.querySelector('.floating-menu');
                if (isOpen) {
                    el.classList.remove('border-transparent', 'pointer-events-none');
                    el.classList.add('border-gray-200', 'dark:border-[#333]');
                    animate('.floating-menu', {
                        height: ['0px', el.scrollHeight + 'px'], // Usamos scrollHeight para que se adapte dinámicamente
                        opacity: [0, 1],
                        easing: 'outElastic(1, .8)',
                        duration: 800,
                    });
                } else {
                    animate('.floating-menu', {
                        height: '0px',
                        opacity: 0,
                        easing: 'inQuad',
                        duration: 300,
                        onComplete: () => {
                            el.classList.add('border-transparent', 'pointer-events-none');
                            el.classList.remove('border-gray-200', 'dark:border-[#333]');
                        }
                    });
                }
            });

            self.add('toggleSubMenu', (isExpanded) => {
                const subMenu = document.querySelector('.sub-menu-config');
                if (isExpanded) {
                    animate('.chevron-config', { rotate: 90, duration: 300, easing: 'outExpo' });
                    animate(subMenu, { height: subMenu.scrollHeight + 'px', opacity: [0, 1], easing: 'outExpo', duration: 400 });
                } else {
                    animate('.chevron-config', { rotate: 0, duration: 300, easing: 'outExpo' });
                    animate(subMenu, { height: '0px', opacity: 0, easing: 'outExpo', duration: 300 });
                }
            });
        });
        return () => scope.current.revert();
    }, []);

    useEffect(() => { if (scope.current) scope.current.methods.toggleMainMenu(isMenuOpen); }, [isMenuOpen]);
    useEffect(() => { if (scope.current) scope.current.methods.toggleSubMenu(isConfigExpanded); }, [isConfigExpanded]);

    const handleLogout = () => {
        post(route('logout'));
    };

    return (
        <nav ref={root} className="fixed top-6 left-6 z-50 flex flex-col items-start">
            <div className="bg-white/90 dark:bg-[#141414]/90 backdrop-blur-md border border-gray-200 dark:border-[#333] rounded-full flex items-center p-1.5 shadow-sm transition-colors duration-300">
                <button onClick={() => setIsMenuOpen(!isMenuOpen)} className="p-2 ml-1 mr-2 rounded-full text-black dark:text-white hover:bg-gray-100 dark:hover:bg-[#2A2A2A] transition-colors">
                    {isMenuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
                </button>

                <div className="flex items-center px-3 border-l border-gray-200 dark:border-[#333] space-x-3">
                    <button onClick={toggleTheme} className="text-gray-400 hover:text-black dark:hover:text-white transition-colors" title="Cambiar Tema">
                        {isDarkMode ? <Sun className="w-4 h-4" /> : <Moon className="w-4 h-4" />}
                    </button>
                    <button className="text-gray-400 hover:text-black dark:hover:text-white transition-colors">
                        <Search className="w-4 h-4" />
                    </button>
                    <button className="relative text-gray-400 hover:text-black dark:hover:text-white transition-colors mr-2">
                        <Bell className="w-4 h-4" />
                        <span className="absolute -top-1 -right-1 w-2 h-2 bg-white dark:bg-black rounded-full border border-pink-500"></span>
                    </button>

                    <div className="w-9 h-9 rounded-full bg-white dark:bg-[#1A1A1A] border border-gray-200 dark:border-[#333] flex items-center justify-center cursor-pointer overflow-hidden">
                        {avatarContent}
                    </div>
                </div>
            </div>

            <div className="floating-menu mt-2 bg-white dark:bg-[#141414] border border-transparent rounded-3xl shadow-2xl overflow-hidden pointer-events-none" style={{ width: '280px', height: '0px', opacity: 0 }}>
                <div className="p-3 flex flex-col space-y-1 overflow-y-auto max-h-[80vh] scrollbar-hide">
                    
                    <Link href={route('dashboard')} className="flex items-center justify-between w-full px-4 py-3 rounded-2xl transition-all duration-300 bg-white dark:bg-[#1A1A1A] border border-black dark:border-white text-black dark:text-white ring-1 ring-black dark:ring-white">
                        <div className="flex items-center">
                            <LayoutDashboard className="w-4 h-4 mr-3" style={{ color: 'var(--color-primario, #ec4899)' }} />
                            <span className="text-sm font-medium">Panel Principal</span>
                        </div>
                    </Link>

                    {/* Módulo Central: Solicitudes (Visible para todos) */}
                    <Link href={route('solicitudes.index')} className="flex items-center justify-between w-full px-4 py-3 rounded-2xl transition-all duration-300 border border-transparent text-gray-600 dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-600 hover:text-black dark:hover:text-white">
                        <div className="flex items-center">
                            <Briefcase className="w-4 h-4 mr-3" />
                            <span className="text-sm font-medium">Módulo de Solicitudes</span>
                        </div>
                    </Link>

                    {/* Secciones de Administración (Super Admin y Administrador) */}
                    {hasRole(['Super Admin', 'Administrador']) && (
                        <div className="flex flex-col">
                            <button onClick={() => setIsConfigExpanded(!isConfigExpanded)} className="flex items-center justify-between w-full px-4 py-3 rounded-2xl transition-all duration-300 border border-transparent text-gray-600 dark:text-gray-400 hover:border-gray-300 dark:hover:border-gray-600 hover:text-black dark:hover:text-white">
                                <div className="flex items-center">
                                    <Settings className="w-4 h-4 mr-3" />
                                    <span className="text-sm font-medium">Administración</span>
                                </div>
                                <div className="chevron-config"><ChevronRight className="w-4 h-4" /></div>
                            </button>

                            <div className="sub-menu-config flex flex-col overflow-hidden" style={{ height: '0px', opacity: 0 }}>
                                <div className="border-l border-gray-200 dark:border-[#333] ml-6 pl-2 py-1 space-y-1 mt-1">
                                    <button className="flex items-center w-full px-3 py-2 rounded-xl text-sm transition-all duration-200 border border-transparent text-gray-500 hover:text-black dark:hover:text-white hover:bg-gray-50 dark:hover:bg-[#1A1A1A]">
                                        <LinkIcon className="w-3.5 h-3.5 mr-2 text-gray-400" /> Generar Enlaces
                                    </button>
                                    <button className="flex items-center w-full px-3 py-2 rounded-xl text-sm transition-all duration-200 border border-transparent text-gray-500 hover:text-black dark:hover:text-white hover:bg-gray-50 dark:hover:bg-[#1A1A1A]">
                                        <Users className="w-3.5 h-3.5 mr-2 text-gray-400" /> Usuarios
                                    </button>
                                    <button className="flex items-center w-full px-3 py-2 rounded-xl text-sm transition-all duration-200 border border-transparent text-gray-500 hover:text-black dark:hover:text-white hover:bg-gray-50 dark:hover:bg-[#1A1A1A]">
                                        <Database className="w-3.5 h-3.5 mr-2 text-gray-400" /> Catálogos
                                    </button>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Botón de Cerrar Sesión */}
                    <button onClick={handleLogout} className="mt-4 flex items-center w-full px-4 py-3 rounded-2xl transition-all duration-300 border border-transparent text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20">
                        <LogOut className="w-4 h-4 mr-3" />
                        <span className="text-sm font-medium">Cerrar Sesión</span>
                    </button>

                </div>
            </div>
        </nav>
    );
}