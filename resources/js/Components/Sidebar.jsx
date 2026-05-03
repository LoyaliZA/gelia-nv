import React, { useState, useRef, useEffect } from 'react';
import { animate, createScope } from 'animejs';
import { Menu, X, Moon, Sun, Search, Bell, LayoutDashboard, Users, Briefcase, ChevronRight, Sparkles, Clock } from 'lucide-react';

export default function Sidebar({ isDarkMode, toggleTheme, user }) {
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [isRecruitmentExpanded, setIsRecruitmentExpanded] = useState(false);
    
    // Referencias principales
    const root = useRef(null);
    const scope = useRef(null);

    const initial = user?.name ? user.name.charAt(0).toUpperCase() : 'U';

    // Inicialización del Scope de AnimeJS
    useEffect(() => {
        scope.current = createScope({ root }).add((self) => {
            
            // Método para el menú principal
            self.add('toggleMainMenu', (isOpen) => {
                const el = document.querySelector('.floating-menu');
                if (isOpen) {
                    el.classList.remove('border-transparent');
                    el.classList.add('border-gray-200');
                    animate('.floating-menu', {
                        height: ['0px', '280px'],
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
                            el.classList.add('border-transparent');
                            el.classList.remove('border-gray-200');
                        }
                    });
                }
            });

            // Método para el acordeón de reclutamiento
            self.add('toggleSubMenu', (isExpanded) => {
                if (isExpanded) {
                    animate('.chevron-icon', { rotate: 90, duration: 300, easing: 'outExpo' });
                    animate('.sub-menu', { height: '84px', opacity: [0, 1], easing: 'outExpo', duration: 400 });
                } else {
                    animate('.chevron-icon', { rotate: 0, duration: 300, easing: 'outExpo' });
                    animate('.sub-menu', { height: '0px', opacity: 0, easing: 'outExpo', duration: 300 });
                }
            });
        });

        // Limpieza de memoria estricta al desmontar el componente
        return () => scope.current.revert();
    }, []);

    // Ejecutores de animación al cambiar el estado
    useEffect(() => {
        if (scope.current) scope.current.methods.toggleMainMenu(isMenuOpen);
    }, [isMenuOpen]);

    useEffect(() => {
        if (scope.current) scope.current.methods.toggleSubMenu(isRecruitmentExpanded);
    }, [isRecruitmentExpanded]);

    return (
        <nav ref={root} className="fixed top-6 left-6 z-50 flex flex-col items-start">
            <div className="bg-white/90 backdrop-blur-md border border-gray-200 rounded-full flex items-center p-1.5 shadow-sm transition-colors duration-300">
                <button 
                    onClick={() => setIsMenuOpen(!isMenuOpen)} 
                    className="p-2 ml-1 mr-2 rounded-full text-black hover:bg-gray-100 transition-colors"
                >
                    {isMenuOpen ? <X className="w-5 h-5" /> : <Menu className="w-5 h-5" />}
                </button>

                <div className="flex items-center px-3 border-l border-gray-200 space-x-3">
                    <button onClick={toggleTheme} className="text-gray-400 hover:text-black transition-colors" title="Cambiar Tema">
                        {isDarkMode ? <Sun className="w-4 h-4" /> : <Moon className="w-4 h-4" />}
                    </button>
                    <button className="text-gray-400 hover:text-black transition-colors">
                        <Search className="w-4 h-4" />
                    </button>
                    <button className="relative text-gray-400 hover:text-black transition-colors mr-2">
                        <Bell className="w-4 h-4" />
                        <span className="absolute -top-1 -right-1 w-2 h-2 bg-white rounded-full border border-pink-500"></span>
                    </button>
                    
                    <div className="w-9 h-9 rounded-full bg-white border border-gray-200 flex items-center justify-center cursor-pointer transition-colors">
                        <span style={{ color: 'var(--color-primario)' }} className="font-bold text-xs">{initial}</span>
                    </div>
                </div>
            </div>

            <div 
                className="floating-menu mt-2 bg-white border border-transparent rounded-3xl shadow-2xl overflow-hidden" 
                style={{ width: '280px', height: '0px', opacity: 0 }}
            >
                <div className="p-3 flex flex-col space-y-1">
                    <button className="menu-item flex items-center justify-between w-full px-4 py-3 rounded-2xl transition-all duration-300 bg-white border border-black text-black ring-1 ring-black">
                        <div className="flex items-center">
                            <LayoutDashboard className="w-4 h-4 mr-3" style={{ color: 'var(--color-primario)' }} />
                            <span className="text-sm font-medium">Panel Principal</span>
                        </div>
                    </button>

                    <div className="flex flex-col">
                        <button 
                            onClick={() => setIsRecruitmentExpanded(!isRecruitmentExpanded)} 
                            className="flex items-center justify-between w-full px-4 py-3 rounded-2xl transition-all duration-300 border border-transparent text-gray-600 hover:border-gray-300 hover:text-black"
                        >
                            <div className="flex items-center">
                                <Briefcase className="w-4 h-4 mr-3" />
                                <span className="text-sm font-medium">Reclutamiento</span>
                            </div>
                            <div className="chevron-icon"><ChevronRight className="w-4 h-4" /></div>
                        </button>

                        <div className="sub-menu flex flex-col overflow-hidden" style={{ height: '0px', opacity: 0 }}>
                            <div className="border-l border-gray-200 ml-6 pl-2 py-1 space-y-1 mt-1">
                                <button className="flex items-center w-full px-3 py-2 rounded-xl text-sm transition-all text-gray-500 hover:text-black hover:bg-gray-50">
                                    <Sparkles className="w-3.5 h-3.5 mr-2" /> IA Generador
                                </button>
                                <button className="flex items-center w-full px-3 py-2 rounded-xl text-sm transition-all text-gray-500 hover:text-black hover:bg-gray-50">
                                    <Clock className="w-3.5 h-3.5 mr-2" /> Entrevistas
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
    );
}